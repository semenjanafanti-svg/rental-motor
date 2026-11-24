<?php

namespace App\Services\DataWarehouse;

use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query analitik di atas data warehouse untuk dashboard owner.
 * Setiap metode menjawab satu pertanyaan strategis dari laporan DW (Tabel 1).
 * $year = null berarti seluruh periode.
 */
class WarehouseAnalytics
{
    public const MONTHS = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public const WEEKDAYS = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    public function __construct(private readonly WarehouseSchema $schema) {}

    public function isReady(): bool
    {
        return $this->schema->isReady();
    }

    private function dw(): Connection
    {
        return DB::connection(WarehouseSchema::connection());
    }

    /** @return array<int, int> */
    public function years(): array
    {
        if (! $this->isReady()) {
            return [];
        }

        return $this->dw()->table('fact_penyewaan as f')
            ->join('dim_waktu as w', 'w.waktu_key', '=', 'f.waktu_mulai_key')
            ->distinct()->orderBy('w.tahun')->pluck('w.tahun')
            ->map(fn ($year) => (int) $year)->all();
    }

    /** @return object{mulai:string, selesai:?string, status:string}|null */
    public function lastEtl(): ?object
    {
        if (! $this->isReady()) {
            return null;
        }

        return $this->dw()->table('etl_log')->orderByDesc('id')->first();
    }

    private function rentals(?int $year, string $timeKey = 'waktu_mulai_key'): Builder
    {
        return $this->dw()->table('fact_penyewaan as f')
            ->join('dim_waktu as w', 'w.waktu_key', '=', "f.{$timeKey}")
            ->join('dim_status_rental as s', 's.status_key', '=', 'f.status_key')
            ->when($year, fn (Builder $q) => $q->where('w.tahun', $year));
    }

    private function payments(?int $year): Builder
    {
        return $this->dw()->table('fact_pembayaran as p')
            ->join('dim_waktu as w', 'w.waktu_key', '=', 'p.waktu_bayar_key')
            ->join('dim_jenis_pembayaran as j', 'j.jenis_key', '=', 'p.jenis_key')
            ->when($year, fn (Builder $q) => $q->where('w.tahun', $year));
    }

    private function returns(?int $year): Builder
    {
        return $this->dw()->table('fact_pengembalian as r')
            ->join('dim_waktu as w', 'w.waktu_key', '=', 'r.waktu_kembali_aktual_key')
            ->when($year, fn (Builder $q) => $q->where('w.tahun', $year));
    }

    /** Ringkasan KPI (pertanyaan 2, 4, 5, 6, 8). */
    public function kpis(?int $year): array
    {
        $rental = $this->rentals($year)
            ->selectRaw('COUNT(*) as pesanan')
            ->selectRaw("SUM(CASE WHEN s.kelompok_status = 'Berhasil' THEN 1 ELSE 0 END) as berhasil")
            ->selectRaw("SUM(CASE WHEN s.kelompok_status = 'Gagal' THEN 1 ELSE 0 END) as gagal")
            ->selectRaw("SUM(CASE WHEN s.kelompok_status = 'Berhasil' THEN f.durasi_hari ELSE 0 END) as hari_berhasil")
            ->selectRaw("SUM(CASE WHEN s.kelompok_status = 'Berhasil' THEN f.total_harga ELSE 0 END) as nilai_berhasil")
            ->first();

        $pay = $this->payments($year)
            ->selectRaw('SUM(p.pendapatan_bersih) as bersih')
            ->selectRaw('SUM(p.nominal_refund) as refund')
            ->selectRaw("SUM(CASE WHEN j.tipe = 'dp' THEN p.nominal_bayar ELSE 0 END) as dp_masuk")
            ->first();

        $customers = $this->rentals($year)
            ->join('dim_pelanggan as c', 'c.pelanggan_key', '=', 'f.pelanggan_key')
            ->where('s.kelompok_status', 'Berhasil')
            ->select('c.pelanggan_key', 'c.segmen')->distinct()->get();

        $berhasil = (int) $rental->berhasil;
        $pesanan = (int) $rental->pesanan;

        return [
            'pendapatan_bersih' => (float) $pay->bersih,
            'pesanan' => $pesanan,
            'sewa_berhasil' => $berhasil,
            'rasio_gagal' => $pesanan ? $rental->gagal / $pesanan : 0,
            'pesanan_gagal' => (int) $rental->gagal,
            'rata_durasi' => $berhasil ? $rental->hari_berhasil / $berhasil : 0,
            'rata_nilai_sewa' => $berhasil ? $rental->nilai_berhasil / $berhasil : 0,
            'refund' => (float) $pay->refund,
            'rasio_refund_dp' => $pay->dp_masuk > 0 ? $pay->refund / $pay->dp_masuk : 0,
            'utilisasi' => $this->fleetUtilisation($year),
            'pelanggan_aktif' => $customers->count(),
            'pelanggan_berulang' => $customers->where('segmen', 'Berulang')->count(),
        ];
    }

    /**
     * Utilisasi armada = hari motor disewa (sewa berhasil & berjalan) / (jumlah motor x hari dalam periode).
     * Periode berjalan dihitung sampai hari ini, bukan sampai akhir tahun.
     */
    public function fleetUtilisation(?int $year): float
    {
        [$from, $to] = $this->period($year);
        $days = max(1, $from->diffInDays($to) + 1);

        $bikes = $this->dw()->table('dim_motor')
            ->where('is_versi_aktif', true)->where('is_dihapus', false)->count();

        $rented = $this->rentals($year)
            ->whereIn('s.kelompok_status', ['Berhasil', 'Berjalan'])
            ->where('w.tanggal', '<=', $to->toDateString())
            ->sum('f.durasi_hari');

        return $bikes ? min(1, $rented / ($bikes * $days)) : 0;
    }

    /** @return array{0:Carbon, 1:Carbon} */
    private function period(?int $year): array
    {
        $today = Carbon::today();

        if ($year) {
            $from = Carbon::create($year)->startOfYear();
            $to = $year === $today->year ? $today : Carbon::create($year)->endOfYear()->startOfDay();
        } else {
            $first = $this->dw()->table('fact_penyewaan as f')
                ->join('dim_waktu as w', 'w.waktu_key', '=', 'f.waktu_mulai_key')
                ->min('w.tanggal');
            $from = $first ? Carbon::parse($first) : $today->copy();
            $to = $today;
        }

        return [$from, $to->lt($from) ? $from->copy() : $to];
    }

    /** Pendapatan bersih per bulan dipecah DP / Pelunasan / Denda (pertanyaan 6). */
    public function revenueByMonth(?int $year): Collection
    {
        $rows = $this->payments($year)
            ->select('w.tahun', 'w.bulan', 'j.label_tipe')
            ->selectRaw('SUM(p.pendapatan_bersih) as nilai')
            ->groupBy('w.tahun', 'w.bulan', 'j.label_tipe')
            ->orderBy('w.tahun')->orderBy('w.bulan')
            ->get();

        return $rows->groupBy(fn ($r) => sprintf('%04d-%02d', $r->tahun, $r->bulan))
            ->map(fn (Collection $group) => [
                'label' => self::MONTHS[(int) $group->first()->bulan].' '.substr((string) $group->first()->tahun, 2),
                'DP' => (float) $group->firstWhere('label_tipe', 'DP')?->nilai,
                'Pelunasan' => (float) $group->firstWhere('label_tipe', 'Pelunasan')?->nilai,
                'Denda' => (float) $group->firstWhere('label_tipe', 'Denda')?->nilai,
            ])->values();
    }

    /** Jumlah sewa berhasil per bulan (pertanyaan 3). */
    public function rentalsByMonth(?int $year): Collection
    {
        return $this->rentals($year)
            ->where('s.kelompok_status', 'Berhasil')
            ->select('w.tahun', 'w.bulan')
            ->selectRaw('SUM(f.jumlah_sewa) as jumlah')
            ->groupBy('w.tahun', 'w.bulan')
            ->orderBy('w.tahun')->orderBy('w.bulan')
            ->get()
            ->map(fn ($r) => [
                'label' => self::MONTHS[(int) $r->bulan].' '.substr((string) $r->tahun, 2),
                'jumlah' => (int) $r->jumlah,
            ]);
    }

    /** Pola permintaan per hari dalam seminggu, semua pesanan masuk (pertanyaan 3). */
    public function demandByWeekday(?int $year): Collection
    {
        $rows = $this->rentals($year)
            ->select('w.hari_ke')
            ->selectRaw('COUNT(*) as jumlah')
            ->groupBy('w.hari_ke')
            ->pluck('jumlah', 'hari_ke');

        return collect(self::WEEKDAYS)->map(fn ($name, $day) => [
            'label' => $name,
            'jumlah' => (int) ($rows[$day] ?? 0),
        ])->values();
    }

    /** Denda per kategori motor (pertanyaan 9, 10). */
    public function finesByCategory(?int $year): Collection
    {
        return $this->returns($year)
            ->join('dim_motor as m', 'm.motor_key', '=', 'r.motor_key')
            ->select('m.kategori')
            ->selectRaw('SUM(r.jumlah_pengembalian) as pengembalian')
            ->selectRaw('SUM(CASE WHEN r.is_terlambat THEN 1 ELSE 0 END) as telat')
            ->selectRaw('SUM(CASE WHEN r.is_rusak THEN 1 ELSE 0 END) as rusak')
            ->selectRaw('SUM(r.jam_terlambat) as jam_telat')
            ->selectRaw('SUM(r.denda_telat) as denda_telat')
            ->selectRaw('SUM(r.denda_kerusakan) as denda_kerusakan')
            ->selectRaw('SUM(r.denda_bensin) as denda_bensin')
            ->groupBy('m.kategori')
            ->orderBy('m.kategori')
            ->get();
    }

    /** Motor dengan kejadian kerusakan (pertanyaan 10). */
    public function damageByBike(?int $year): Collection
    {
        return $this->returns($year)
            ->join('dim_motor as m', 'm.motor_key', '=', 'r.motor_key')
            ->where('r.is_rusak', true)
            ->select('m.nama_motor')
            ->selectRaw('COUNT(*) as kejadian')
            ->selectRaw('SUM(r.denda_kerusakan) as nilai')
            ->groupBy('m.nama_motor')
            ->orderByDesc('kejadian')
            ->get();
    }

    /** Pelanggan paling bernilai (pertanyaan 5). */
    public function topCustomers(?int $year, int $limit = 8): Collection
    {
        $revenue = $this->payments($year)
            ->join('dim_pelanggan as c', 'c.pelanggan_key', '=', 'p.pelanggan_key')
            ->select('c.pelanggan_key', 'c.nama', 'c.segmen')
            ->selectRaw('SUM(p.pendapatan_bersih) as pendapatan')
            ->groupBy('c.pelanggan_key', 'c.nama', 'c.segmen')
            ->having('pendapatan', '>', 0)
            ->orderByDesc('pendapatan')
            ->limit($limit)
            ->get();

        $counts = $this->rentals($year)
            ->where('s.kelompok_status', 'Berhasil')
            ->whereIn('f.pelanggan_key', $revenue->pluck('pelanggan_key'))
            ->select('f.pelanggan_key')->selectRaw('COUNT(*) as jumlah')
            ->groupBy('f.pelanggan_key')
            ->pluck('jumlah', 'pelanggan_key');

        return $revenue->map(fn ($r) => [
            'nama' => $r->nama,
            'segmen' => $r->segmen,
            'sewa' => (int) ($counts[$r->pelanggan_key] ?? 0),
            'pendapatan' => (float) $r->pendapatan,
        ]);
    }

    public function rupiah(float|int $value): string
    {
        return 'Rp'.number_format($value, 0, ',', '.');
    }

    public function pct(float|int $ratio): string
    {
        return number_format($ratio * 100, 1, ',', '.').'%';
    }
}
