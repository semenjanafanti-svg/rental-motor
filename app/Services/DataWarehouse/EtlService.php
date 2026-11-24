<?php

namespace App\Services\DataWarehouse;

use App\Models\Bike;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * ETL database operasional (rentals, payments, rental_returns, bikes, users)
 * ke data warehouse Mitra Jalan.
 *
 *   EXTRACT   : baca tabel OLTP lewat koneksi default.
 *   TRANSFORM : surrogate key, label Indonesia, kelompok status, segmen pelanggan,
 *               SCD tipe 2 untuk motor, hitung ulang sisa pelunasan, pendapatan bersih.
 *   LOAD      : dimensi di-upsert (riwayat dimensi tetap tersimpan),
 *               fakta dimuat ulang penuh setiap kali ETL dijalankan.
 */
class EtlService
{
    private const OPEN_END = '9999-12-31 23:59:59';

    private Connection $source;

    private Connection $dw;

    private CarbonInterface $now;

    /** @var array<int, int> user_id => staf_key */
    private array $staffKeys = [];

    /** @var array<int, int> user_id => pelanggan_key */
    private array $customerKeys = [];

    /** @var array<int, array<int, object>> bike_id => versi dim_motor */
    private array $motorVersions = [];

    /** @var array<string, int> */
    private array $statusKeys = [];

    /** @var array<string, int> "tipe|metode|status" => jenis_key */
    private array $paymentTypeKeys = [];

    /** @var array<string, int> */
    private array $rowCounts = [];

    public function __construct(private readonly WarehouseSchema $schema) {}

    /** @return array<string, int> jumlah baris per tabel setelah ETL */
    public function run(): array
    {
        $this->schema->ensure();

        $this->source = DB::connection(config('database.default'));
        $this->dw = DB::connection(WarehouseSchema::connection());
        $this->now = Carbon::now();
        $this->rowCounts = [];

        $logId = $this->dw->table('etl_log')->insertGetId([
            'mulai' => $this->now,
            'status' => 'running',
        ]);

        try {
            // --- EXTRACT ---
            $bikes = $this->source->table('bikes')->get();
            $users = $this->source->table('users')->get(['id', 'name', 'phone_number', 'role', 'is_active', 'created_at']);
            $rentals = $this->source->table('rentals')->orderBy('id')->get();
            $payments = $this->source->table('payments')->orderBy('id')->get();
            $returns = $this->source->table('rental_returns')->orderBy('id')->get();

            $this->dw->transaction(function () use ($bikes, $users, $rentals, $payments, $returns) {
                // Fakta dikosongkan dulu (full reload) supaya FK ke dimensi tidak menghalangi.
                foreach (WarehouseSchema::FACTS as $fact) {
                    $this->dw->table($fact)->delete();
                }

                // --- TRANSFORM + LOAD dimensi ---
                $this->loadDimWaktu($rentals, $payments, $returns);
                $this->loadDimStatusRental();
                $this->loadDimJenisPembayaran();
                $this->loadDimStaf($users);
                $this->loadDimPelanggan($users, $rentals);
                $this->loadDimMotor($bikes);

                // --- TRANSFORM + LOAD fakta ---
                $rentalsById = $rentals->keyBy('id');
                $this->loadFactPenyewaan($rentals);
                $this->loadFactPembayaran($payments, $rentalsById);
                $this->loadFactPengembalian($returns, $rentalsById);
            });

            foreach ([...WarehouseSchema::DIMENSIONS, ...WarehouseSchema::FACTS] as $table) {
                $this->rowCounts[$table] = $this->dw->table($table)->count();
            }

            $this->dw->table('etl_log')->where('id', $logId)->update([
                'selesai' => Carbon::now(),
                'status' => 'sukses',
                'jumlah_baris' => json_encode($this->rowCounts),
            ]);

            return $this->rowCounts;
        } catch (Throwable $e) {
            $this->dw->table('etl_log')->where('id', $logId)->update([
                'selesai' => Carbon::now(),
                'status' => 'gagal',
                'pesan' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            throw $e;
        }
    }

    /** dim_waktu: satu baris per tanggal, 1 Januari tahun data pertama s.d. 31 Desember tahun terakhir. */
    private function loadDimWaktu(Collection $rentals, Collection $payments, Collection $returns): void
    {
        $dates = collect()
            ->merge($rentals->pluck('created_at'))
            ->merge($rentals->pluck('start_time'))
            ->merge($rentals->pluck('end_time'))
            ->merge($payments->pluck('paid_at'))
            ->merge($payments->pluck('created_at'))
            ->merge($returns->pluck('actual_return_time'))
            ->push($this->now)
            ->filter()
            ->map(fn ($value) => Carbon::parse($value));

        $from = $dates->min()->copy()->startOfYear();
        $to = $dates->max()->copy()->endOfYear()->startOfDay();

        $existing = $this->dw->table('dim_waktu')->pluck('waktu_key')->flip();
        $holidays = config('dw.holidays', []);
        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $monthNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $rows = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $key = (int) $date->format('Ymd');
            if ($existing->has($key)) {
                continue;
            }

            $iso = $date->toDateString();
            $rows[] = [
                'waktu_key' => $key,
                'tanggal' => $iso,
                'hari' => $date->day,
                'nama_hari' => $dayNames[$date->dayOfWeekIso],
                'hari_ke' => $date->dayOfWeekIso,
                'minggu_ke' => $date->isoWeek,
                'bulan' => $date->month,
                'nama_bulan' => $monthNames[$date->month],
                'kuartal' => 'Q'.$date->quarter,
                'tahun' => $date->year,
                'is_akhir_pekan' => $date->dayOfWeekIso >= 6,
                'is_hari_libur' => isset($holidays[$iso]),
                'nama_libur' => $holidays[$iso] ?? null,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->dw->table('dim_waktu')->insert($chunk);
        }
    }

    private function loadDimStatusRental(): void
    {
        $key = 1;
        foreach (config('dw.rental_statuses') as $status => [$label, $group]) {
            $this->dw->table('dim_status_rental')->updateOrInsert(
                ['status' => $status],
                ['status_key' => $key, 'label_status' => $label, 'kelompok_status' => $group],
            );
            $this->statusKeys[$status] = $key++;
        }
    }

    private function loadDimJenisPembayaran(): void
    {
        $key = 1;
        foreach (config('dw.payment_types') as $type => $typeLabel) {
            foreach (config('dw.payment_methods') as $method => [$methodLabel, $channel]) {
                foreach (config('dw.payment_statuses') as $status => $statusLabel) {
                    $this->dw->table('dim_jenis_pembayaran')->updateOrInsert(
                        ['tipe' => $type, 'metode' => $method, 'status_pembayaran' => $status],
                        [
                            'jenis_key' => $key,
                            'label_tipe' => $typeLabel,
                            'label_metode' => $methodLabel,
                            'kanal' => $channel,
                            'label_status' => $statusLabel,
                        ],
                    );
                    $this->paymentTypeKeys["{$type}|{$method}|{$status}"] = $key++;
                }
            }
        }
    }

    /** dim_staf (SCD tipe 1): key 0 dipakai untuk transaksi tanpa petugas. */
    private function loadDimStaf(Collection $users): void
    {
        $this->dw->table('dim_staf')->updateOrInsert(
            ['staf_key' => 0],
            ['user_id' => null, 'nama' => 'Tanpa petugas', 'role' => '-'],
        );

        $roles = ['admin' => 'Admin', 'super_admin' => 'Super Admin (Owner)'];
        $existing = $this->dw->table('dim_staf')->whereNotNull('user_id')->pluck('staf_key', 'user_id');
        $nextKey = (int) $this->dw->table('dim_staf')->max('staf_key') + 1;

        foreach ($users->whereIn('role', array_keys($roles)) as $user) {
            $key = $existing[$user->id] ?? $nextKey++;
            $this->dw->table('dim_staf')->updateOrInsert(
                ['staf_key' => $key],
                ['user_id' => $user->id, 'nama' => $user->name, 'role' => $roles[$user->role]],
            );
            $this->staffKeys[$user->id] = $key;
        }
    }

    /** dim_pelanggan (SCD tipe 1) dengan segmen Baru / Berulang dari jumlah sewa berhasil. */
    private function loadDimPelanggan(Collection $users, Collection $rentals): void
    {
        $completed = $rentals->where('status', 'completed')->countBy('user_id');
        $renterIds = $rentals->pluck('user_id')->unique();
        $minRepeat = (int) config('dw.repeat_customer_min_rentals', 2);

        $existing = $this->dw->table('dim_pelanggan')->pluck('pelanggan_key', 'user_id');
        $nextKey = (int) $this->dw->table('dim_pelanggan')->max('pelanggan_key') + 1;

        $customers = $users->filter(fn ($user) => $user->role === 'customer' || $renterIds->contains($user->id));

        foreach ($customers as $user) {
            $count = (int) ($completed[$user->id] ?? 0);
            $key = $existing[$user->id] ?? $nextKey++;

            $this->dw->table('dim_pelanggan')->updateOrInsert(
                ['pelanggan_key' => $key],
                [
                    'user_id' => $user->id,
                    'nama' => $user->name,
                    'no_hp' => $user->phone_number,
                    'tanggal_daftar' => Carbon::parse($user->created_at)->toDateString(),
                    'segmen' => $count >= $minRepeat ? 'Berulang' : 'Baru',
                    'jumlah_sewa_berhasil' => $count,
                    'status_akun' => $user->is_active ? 'Aktif' : 'Nonaktif',
                ],
            );
            $this->customerKeys[$user->id] = $key;
        }
    }

    /**
     * dim_motor dengan SCD tipe 2: bila tarif harian, kategori, atau status fisik berubah,
     * versi lama ditutup (berlaku_sampai diisi) dan versi baru dibuat. Atribut lain (nama,
     * warna, dll.) cukup ditimpa pada versi aktif (SCD tipe 1).
     */
    private function loadDimMotor(Collection $bikes): void
    {
        $nextKey = (int) $this->dw->table('dim_motor')->max('motor_key') + 1;

        foreach ($bikes as $bike) {
            $daily = (float) $bike->daily_rate;
            $attributes = [
                'nama_motor' => $bike->name,
                'merk' => $bike->brand,
                'plat_nomor' => $bike->license_plate,
                'cc' => $bike->cc,
                'kelas_cc' => $this->ccClass($bike->cc),
                'tahun_produksi' => $bike->year,
                'warna' => $bike->color,
                'tarif_per_jam' => Bike::calculateHourlyRate($daily),
                'is_dihapus' => $bike->deleted_at !== null,
            ];
            $tracked = [
                'tarif_harian' => $daily,
                'kategori' => $bike->category,
                'status_fisik' => $bike->status,
            ];

            $current = $this->dw->table('dim_motor')
                ->where('bike_id', $bike->id)
                ->where('is_versi_aktif', true)
                ->first();

            if (! $current) {
                // Versi pertama berlaku sejak awal agar transaksi lama tetap punya pasangan.
                $this->dw->table('dim_motor')->insert($attributes + $tracked + [
                    'motor_key' => $nextKey++,
                    'bike_id' => $bike->id,
                    'berlaku_mulai' => '1900-01-01 00:00:00',
                    'berlaku_sampai' => null,
                    'is_versi_aktif' => true,
                ]);
            } elseif ((float) $current->tarif_harian !== $daily
                || $current->kategori !== $bike->category
                || $current->status_fisik !== $bike->status) {
                $this->dw->table('dim_motor')->where('motor_key', $current->motor_key)->update([
                    'berlaku_sampai' => $this->now,
                    'is_versi_aktif' => false,
                ]);
                $this->dw->table('dim_motor')->insert($attributes + $tracked + [
                    'motor_key' => $nextKey++,
                    'bike_id' => $bike->id,
                    'berlaku_mulai' => $this->now,
                    'berlaku_sampai' => null,
                    'is_versi_aktif' => true,
                ]);
            } else {
                $this->dw->table('dim_motor')->where('motor_key', $current->motor_key)->update($attributes);
            }
        }

        $this->motorVersions = $this->dw->table('dim_motor')
            ->orderBy('berlaku_mulai')
            ->get()
            ->groupBy('bike_id')
            ->map(fn (Collection $versions) => $versions->all())
            ->all();
    }

    private function loadFactPenyewaan(Collection $rentals): void
    {
        $rows = $rentals->map(function ($rental) {
            $start = Carbon::parse($rental->start_time);
            $end = Carbon::parse($rental->end_time);
            $hours = (int) ceil($start->diffInMinutes($end, true) / 60);
            $total = (float) $rental->total_price;
            $dp = (float) $rental->dp_amount;

            return [
                'rental_id' => $rental->id,
                'booking_code' => $rental->booking_code,
                'waktu_booking_key' => $this->dateKey($rental->created_at),
                'waktu_mulai_key' => $this->dateKey($start),
                'waktu_kembali_key' => $this->dateKey($end),
                'motor_key' => $this->motorKey($rental->bike_id, $rental->created_at),
                'pelanggan_key' => $this->customerKey($rental->user_id),
                'status_key' => $this->statusKeys[$rental->status] ?? throw new RuntimeException("Status rental tidak dikenal: {$rental->status}"),
                'staf_serah_terima_key' => $this->staffKey($rental->handed_over_by),
                'jam_mulai' => $start->hour,
                'jumlah_sewa' => 1,
                'durasi_hari' => (int) ceil($hours / 24),
                'total_jam' => $hours,
                'total_harga' => $total,
                'nominal_dp' => $dp,
                // OLTP mengosongkan balance_amount setelah pelunasan, jadi dihitung ulang.
                'sisa_pelunasan' => $total - $dp,
            ];
        });

        $this->insertChunks('fact_penyewaan', $rows);
    }

    private function loadFactPembayaran(Collection $payments, Collection $rentalsById): void
    {
        $rows = $payments->map(function ($payment) use ($rentalsById) {
            $rental = $rentalsById[$payment->rental_id];
            $received = in_array($payment->payment_status, ['settlement', 'refund'], true);
            $paid = $received ? (float) $payment->gross_amount : 0.0;
            $refund = (float) $payment->refunded_amount;
            $typeKey = "{$payment->type}|{$payment->method}|{$payment->payment_status}";

            return [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'booking_code' => $rental->booking_code,
                // Pembayaran yang belum/tidak dibayar memakai tanggal tagihan dibuat.
                'waktu_bayar_key' => $this->dateKey($payment->paid_at ?? $payment->created_at),
                'motor_key' => $this->motorKey($rental->bike_id, $rental->created_at),
                'pelanggan_key' => $this->customerKey($rental->user_id),
                'jenis_key' => $this->paymentTypeKeys[$typeKey] ?? throw new RuntimeException("Jenis pembayaran tidak dikenal: {$typeKey}"),
                'staf_penerima_key' => $this->staffKey($payment->received_by),
                'jumlah_transaksi' => 1,
                'nominal_bayar' => $paid,
                'nominal_refund' => $refund,
                'pendapatan_bersih' => $paid - $refund,
            ];
        });

        $this->insertChunks('fact_pembayaran', $rows);
    }

    private function loadFactPengembalian(Collection $returns, Collection $rentalsById): void
    {
        $rows = $returns->map(function ($return) use ($rentalsById) {
            $rental = $rentalsById[$return->rental_id];
            $late = (float) $return->late_fee;
            $damage = (float) $return->damage_fee;
            $fuel = (float) $return->fuel_fee;

            return [
                'return_id' => $return->id,
                'booking_code' => $rental->booking_code,
                'waktu_kembali_aktual_key' => $this->dateKey($return->actual_return_time),
                'motor_key' => $this->motorKey($rental->bike_id, $rental->created_at),
                'pelanggan_key' => $this->customerKey($rental->user_id),
                'staf_pemeriksa_key' => $this->staffKey($return->checked_by),
                'jumlah_pengembalian' => 1,
                'jam_terlambat' => (int) $return->late_hours,
                'denda_telat' => $late,
                'denda_kerusakan' => $damage,
                'denda_bensin' => $fuel,
                'total_denda' => $late + $damage + $fuel,
                'is_terlambat' => (int) $return->late_hours > 0,
                'is_rusak' => $damage > 0,
            ];
        });

        $this->insertChunks('fact_pengembalian', $rows);
    }

    private function insertChunks(string $table, Collection $rows): void
    {
        foreach ($rows->chunk(200) as $chunk) {
            $this->dw->table($table)->insert($chunk->values()->all());
        }
    }

    private function dateKey(mixed $value): int
    {
        return (int) Carbon::parse($value)->format('Ymd');
    }

    /** Versi motor yang berlaku saat pesanan dibuat (point-in-time lookup SCD 2). */
    private function motorKey(int $bikeId, mixed $at): int
    {
        $moment = Carbon::parse($at)->toDateTimeString();

        foreach ($this->motorVersions[$bikeId] ?? [] as $version) {
            $until = $version->berlaku_sampai ?? self::OPEN_END;
            if ($version->berlaku_mulai <= $moment && $moment < $until) {
                return (int) $version->motor_key;
            }
        }

        throw new RuntimeException("Motor #{$bikeId} tidak ditemukan di dim_motor.");
    }

    private function customerKey(int $userId): int
    {
        return $this->customerKeys[$userId] ?? throw new RuntimeException("Pelanggan #{$userId} tidak ditemukan di dim_pelanggan.");
    }

    private function staffKey(?int $userId): int
    {
        return $userId === null ? 0 : ($this->staffKeys[$userId] ?? 0);
    }

    private function ccClass(?int $cc): string
    {
        return match (true) {
            $cc === null => 'Tidak diketahui',
            $cc <= 125 => 's.d. 125 cc',
            $cc <= 160 => '126-160 cc',
            default => 'di atas 160 cc',
        };
    }
}
