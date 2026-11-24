<?php

namespace Database\Seeders;

use App\Models\Bike;
use App\Support\Format;
use Carbon\Carbon;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 200 transaksi sewa dummy (Januari 2025 sampai beberapa hari ke depan) untuk latihan
 * data warehouse. Rentang dua tahun (2025 & 2026) supaya dashboard bisa membandingkan antartahun, beserta customer, pembayaran, pengembalian, dan reminder-nya.
 *
 * Semua nilai mengikuti aturan aplikasi: tarif per 24 jam, DP 30% dibulatkan ke Rp1.000,
 * denda telat = jam telat x tarif per jam (20% tarif harian), jam mulai 06:00-21:00.
 *
 * Jalankan: php artisan db:seed --class=DummyRentalSeeder
 * Aman dijalankan ulang: data dummy sebelumnya (customer @dummy.test) dihapus dulu.
 */
class DummyRentalSeeder extends Seeder
{
    private const EMAIL_DOMAIN = 'dummy.test';

    private const CUSTOMER_COUNT = 40;

    private const HISTORY_START = '2025-01-01';

    /** Customer pertama mendaftar sebelum periode data dimulai. */
    private const EARLY_SIGNUP = ['2024-12-01', '2024-12-15'];

    /** Jumlah rental per skenario, total 200. */
    private const PLAN = [
        'completed' => 141,
        'cancelled_by_customer' => 8,
        'cancelled_document' => 6,
        'cancelled_proof' => 6,
        'expired' => 20,
        'no_show' => 8,
        'active' => 4,
        'approved' => 4,
        'pending_verification' => 3,
    ];

    /** Musim ramai: libur akhir tahun, Lebaran (akhir Maret 2025 & Maret 2026), libur sekolah Juni-Juli. */
    private const MONTH_WEIGHT = [1 => 1.1, 2 => 0.8, 3 => 1.5, 4 => 1.0, 5 => 0.9, 6 => 1.3, 7 => 1.4, 8 => 1.0, 9 => 0.8, 10 => 0.9, 11 => 1.0, 12 => 1.4];

    /** 0 = Minggu. Jumat & Sabtu paling ramai. */
    private const DAY_OF_WEEK_WEIGHT = [0 => 1.0, 1 => 0.7, 2 => 0.7, 3 => 0.8, 4 => 0.9, 5 => 1.4, 6 => 1.6];

    private const DURATION_WEIGHT = [1 => 40, 2 => 25, 3 => 15, 4 => 7, 5 => 5, 7 => 5, 10 => 2, 14 => 1];

    private const START_HOUR_WEIGHT = [6 => 3, 7 => 8, 8 => 12, 9 => 12, 10 => 10, 11 => 6, 12 => 5, 13 => 6, 14 => 6, 15 => 6, 16 => 7, 17 => 6, 18 => 5, 19 => 4, 20 => 2, 21 => 2];

    /** Jarak waktu booking sebelum jam mulai, dalam jam [min, max] => bobot. */
    private const LEAD_HOURS_WEIGHT = ['2-6' => 10, '6-24' => 20, '24-72' => 35, '72-168' => 25, '168-336' => 10];

    private const DOCUMENT_REJECTIONS = ['Foto KTP buram, data tidak terbaca', 'SIM C sudah kedaluwarsa', 'Nama di KTP dan SIM tidak sama'];

    private const PROOF_REJECTIONS = ['Nominal transfer tidak sesuai DP', 'Bukti transfer tidak terbaca', 'Bukti transfer bukan untuk pesanan ini'];

    private const DAMAGE_NOTES = [50000 => 'Spion kiri lecet', 75000 => 'Lampu sein belakang retak', 150000 => 'Body samping baret', 250000 => 'Footstep patah'];

    private Generator $faker;

    private Carbon $now;

    /** @var array<int, object{id:int, daily_rate:string}> */
    private array $bikes = [];

    /** @var array<int, string> staff id => role */
    private array $staff = [];

    /** @var array<int, array{id:int, created_at:Carbon, weight:float}> */
    private array $customers = [];

    /** @var array<int, array<int, array{0:Carbon, 1:Carbon}>> bike id => slot terpakai */
    private array $busy = [];

    /** @var array<string, int> prefix kode booking => nomor terakhir */
    private array $codeCounters = [];

    public function run(): void
    {
        mt_srand(2026);
        $this->faker = FakerFactory::create('id_ID');
        $this->faker->seed(2026);
        $this->now = Carbon::now()->startOfMinute();

        DB::transaction(function () {
            $this->purgePreviousDummy();
            $this->loadReferenceData();
            $this->createCustomers();

            $specs = [];
            foreach (self::PLAN as $scenario => $count) {
                for ($i = 0; $i < $count; $i++) {
                    $specs[] = $this->buildSpec($scenario);
                }
            }

            // Simpan urut waktu booking supaya id dan kode booking ikut kronologis.
            usort($specs, fn (array $a, array $b) => $a['created_at'] <=> $b['created_at']);

            foreach ($specs as $spec) {
                $this->insertRental($spec);
            }
        });

        $this->command?->info('DummyRentalSeeder: '.self::CUSTOMER_COUNT.' customer dan '.array_sum(self::PLAN).' rental dummy dibuat.');
    }

    private function purgePreviousDummy(): void
    {
        $userIds = DB::table('users')->where('email', 'like', '%@'.self::EMAIL_DOMAIN)->pluck('id');

        if ($userIds->isEmpty()) {
            return;
        }

        $rentalIds = DB::table('rentals')->whereIn('user_id', $userIds)->pluck('id');

        DB::table('rental_reminders')->whereIn('rental_id', $rentalIds)->delete();
        DB::table('rental_returns')->whereIn('rental_id', $rentalIds)->delete();
        DB::table('payments')->whereIn('rental_id', $rentalIds)->delete();
        DB::table('rentals')->whereIn('id', $rentalIds)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();
    }

    private function loadReferenceData(): void
    {
        $this->bikes = DB::table('bikes')
            ->whereNull('deleted_at')
            ->where('status', 'available')
            ->get(['id', 'daily_rate'])
            ->all();

        $this->staff = DB::table('users')
            ->whereIn('role', ['admin', 'super_admin'])
            ->where('is_active', true)
            ->pluck('role', 'id')
            ->all();

        if ($this->bikes === [] || $this->staff === []) {
            throw new RuntimeException('Butuh minimal 1 motor available dan 1 admin. Jalankan php artisan db:seed dulu.');
        }

        // Slot rental asli yang sudah ada tidak boleh bertabrakan dengan data dummy.
        $existing = DB::table('rentals')->whereNotIn('status', ['expired', 'cancelled'])->get(['bike_id', 'start_time', 'end_time']);
        foreach ($existing as $rental) {
            $this->busy[$rental->bike_id][] = [Carbon::parse($rental->start_time), Carbon::parse($rental->end_time)->addHours(6)];
        }
    }

    private function createCustomers(): void
    {
        $password = Hash::make('password');
        [$earlyStart, $earlyEnd] = array_map(fn (string $date) => Carbon::parse($date), self::EARLY_SIGNUP);

        for ($i = 1; $i <= self::CUSTOMER_COUNT; $i++) {
            // 8 customer pertama sudah terdaftar sebelum periode data dimulai.
            $createdAt = $i <= 8
                ? $this->randomMoment($earlyStart, $earlyEnd)
                : $this->randomMoment(Carbon::parse(self::HISTORY_START), $this->now->copy()->subMonth());

            $name = $this->faker->firstName().' '.$this->faker->lastName();
            $email = Str::slug($name, '.').$i.'@'.self::EMAIL_DOMAIN;

            $id = DB::table('users')->insertGetId([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'phone_number' => '628'.$this->faker->numerify('##########'),
                'role' => 'customer',
                'is_active' => mt_rand(1, 100) > 4,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Sebagian kecil customer jadi pelanggan setia (bobot besar), sisanya jarang menyewa.
            $this->customers[] = ['id' => $id, 'created_at' => $createdAt, 'weight' => 1 / ($i ** 0.6)];
        }

        shuffle($this->customers);
    }

    /** @return array<string, mixed> */
    private function buildSpec(string $scenario): array
    {
        $blocksSlot = ! in_array($scenario, ['expired', 'cancelled_by_customer', 'cancelled_document', 'cancelled_proof'], true);

        for ($attempt = 0; $attempt < 500; $attempt++) {
            $days = $this->weighted(self::DURATION_WEIGHT);
            $start = $this->pickStart($scenario, $days);
            if (! $start) {
                continue;
            }
            $end = $start->copy()->addDays($days);

            $bike = $this->pickBike();
            if ($blocksSlot && $this->overlaps($bike->id, $start, $end)) {
                continue;
            }

            $createdAt = $this->pickCreatedAt($scenario, $start);
            if (! $createdAt) {
                continue;
            }

            $customer = $this->pickCustomer($createdAt);
            if (! $customer) {
                continue;
            }

            if ($blocksSlot) {
                // Jeda 6 jam untuk keterlambatan & pengecekan unit.
                $this->busy[$bike->id][] = [$start, $end->copy()->addHours(6)];
            }

            return [
                'scenario' => $scenario,
                'bike' => $bike,
                'user_id' => $customer['id'],
                'days' => $days,
                'start' => $start,
                'end' => $end,
                'created_at' => $createdAt,
            ];
        }

        throw new RuntimeException("Gagal membuat slot rental untuk skenario {$scenario}.");
    }

    private function pickStart(string $scenario, int $days): ?Carbon
    {
        $hour = $this->weighted(self::START_HOUR_WEIGHT);
        $today = $this->now->copy()->startOfDay();

        switch ($scenario) {
            case 'active':
                $start = $today->copy()->subDays(mt_rand(0, $days - 1))->setTime($hour, 0);

                // Sudah diambil minimal 1 jam lalu dan belum waktunya kembali.
                return $start->lte($this->now->copy()->subHour()) && $start->copy()->addDays($days)->gt($this->now->copy()->addHours(2))
                    ? $start
                    : null;

            case 'approved':
            case 'pending_verification':
                return $today->copy()->addDays(mt_rand(1, 16))->setTime($hour, 0);

            default:
                // Transaksi yang sudah selesai: tanggal mulai dipilih dengan bobot musim & hari.
                $first = Carbon::parse(self::HISTORY_START);
                // Sisakan 2 hari supaya pengembalian yang telat pun sudah terjadi.
                $last = $this->now->copy()->subDays($days + 2);
                $span = $first->diffInDays($last);

                for ($try = 0; $try < 50; $try++) {
                    $date = $first->copy()->addDays(mt_rand(0, (int) $span));
                    $weight = self::MONTH_WEIGHT[$date->month] * self::DAY_OF_WEEK_WEIGHT[$date->dayOfWeek];

                    if (mt_rand() / mt_getrandmax() * 2.4 <= $weight) {
                        return $date->setTime($hour, 0);
                    }
                }

                return null;
        }
    }

    private function pickCreatedAt(string $scenario, Carbon $start): ?Carbon
    {
        if ($scenario === 'pending_verification') {
            return $this->now->copy()->subMinutes(mt_rand(30, 20 * 60));
        }

        [$min, $max] = array_map('intval', explode('-', $this->weighted(self::LEAD_HOURS_WEIGHT)));

        // Pembatalan oleh penyewa hanya boleh H-3, jadi booking dibuat jauh hari.
        if ($scenario === 'cancelled_by_customer') {
            [$min, $max] = [5 * 24, 14 * 24];
        }

        $createdAt = $start->copy()->subMinutes(mt_rand($min * 60, $max * 60));

        // Orang memesan antara jam 07:00 dan 22:59.
        if ($createdAt->hour < 7 || $createdAt->hour > 22) {
            $createdAt->setTime(mt_rand(7, 22), mt_rand(0, 59));
        }

        if ($createdAt->gte($start->copy()->subHour())
            || $createdAt->lt(Carbon::parse(self::EARLY_SIGNUP[1]))
            || $createdAt->gte($this->now->copy()->subHour())) {
            return null;
        }

        return $createdAt;
    }

    /** @return array{id:int, created_at:Carbon, weight:float}|null */
    private function pickCustomer(Carbon $bookedAt): ?array
    {
        $eligible = array_values(array_filter(
            $this->customers,
            fn (array $customer) => $customer['created_at']->lt($bookedAt->copy()->subHour()),
        ));

        if ($eligible === []) {
            return null;
        }

        $weights = array_column($eligible, 'weight');

        return $eligible[$this->weightedIndex($weights)];
    }

    private function pickBike(): object
    {
        // Motor murah lebih sering disewa.
        $weights = array_map(fn (object $bike) => 1 / ((float) $bike->daily_rate ** 1.2), $this->bikes);

        return $this->bikes[$this->weightedIndex($weights)];
    }

    private function overlaps(int $bikeId, Carbon $start, Carbon $end): bool
    {
        foreach ($this->busy[$bikeId] ?? [] as [$busyStart, $busyEnd]) {
            if ($start->lt($busyEnd) && $end->copy()->addHours(6)->gt($busyStart)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $spec */
    private function insertRental(array $spec): void
    {
        $scenario = $spec['scenario'];
        /** @var Carbon $start */
        $start = $spec['start'];
        /** @var Carbon $end */
        $end = $spec['end'];
        /** @var Carbon $createdAt */
        $createdAt = $spec['created_at'];

        $daily = (int) round((float) $spec['bike']->daily_rate);
        $hourly = Bike::calculateHourlyRate($daily);
        $total = $spec['days'] * $daily;
        $dp = (int) min($total, ceil($total * 0.30 / 1000) * 1000);
        $balance = $total - $dp;

        $code = $this->nextBookingCode($createdAt);
        $verifier = $this->pickStaff();
        $handler = $this->pickStaff();

        $proofAt = $createdAt->copy()->addMinutes(mt_rand(2, 9));
        $minutesToStart = max(10, (int) $proofAt->diffInMinutes($start->copy()->subMinutes(15), true));
        $verifiedAt = $proofAt->copy()->addMinutes(mt_rand(5, min(240, $minutesToStart)));
        if ($verifiedAt->gt($this->now)) {
            $verifiedAt = $this->now->copy()->subMinutes(mt_rand(1, 10));
        }

        $rental = [
            'booking_code' => $code,
            'user_id' => $spec['user_id'],
            'bike_id' => $spec['bike']->id,
            'start_time' => $start,
            'end_time' => $end,
            'hourly_rate_applied' => $hourly,
            'total_price' => $total,
            'dp_amount' => $dp,
            'balance_amount' => $balance,
            'payment_status' => 'unpaid',
            'status' => 'pending_payment',
            'expires_at' => $createdAt->copy()->addMinutes(10),
            'ktp_photo' => null,
            'sim_photo' => null,
            'verification_status' => 'pending',
            'verification_rejection_reason' => null,
            'verified_by' => null,
            'verified_at' => null,
            'picked_up_at' => null,
            'handed_over_by' => null,
            'cancelled_reason' => null,
            'notes' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        $dpPayment = [
            'order_id' => $code.'-DP',
            'type' => 'dp',
            'method' => 'manual_transfer',
            'gross_amount' => $dp,
            'payment_status' => 'pending',
            'paid_at' => null,
            'refunded_amount' => 0,
            'received_by' => null,
            'proof_photo' => null,
            'proof_uploaded_at' => $proofAt,
            'rejection_reason' => null,
            'rejection_count' => 0,
            'created_at' => $createdAt,
            'updated_at' => $proofAt,
        ];

        $approved = [
            'verification_status' => 'approved',
            'verified_by' => $verifier,
            'verified_at' => $verifiedAt,
            'payment_status' => 'dp_paid',
            'status' => 'approved',
            'updated_at' => $verifiedAt,
        ];
        $dpSettled = ['payment_status' => 'settlement', 'paid_at' => $verifiedAt, 'received_by' => $verifier, 'updated_at' => $verifiedAt];

        $extraPayments = [];
        $return = null;
        $reminders = [];

        switch ($scenario) {
            case 'expired':
                $expiredAt = $rental['expires_at']->copy()->addMinutes(mt_rand(0, 5));
                $rental = array_merge($rental, ['status' => 'expired', 'updated_at' => $expiredAt]);
                $dpPayment = array_merge($dpPayment, ['payment_status' => 'expire', 'proof_uploaded_at' => null, 'updated_at' => $expiredAt]);
                break;

            case 'pending_verification':
                $rental = array_merge($rental, ['status' => 'pending_verification', 'updated_at' => $proofAt]);
                break;

            case 'approved':
                $rental = array_merge($rental, $approved);
                $dpPayment = array_merge($dpPayment, $dpSettled);
                break;

            case 'cancelled_by_customer':
                $latestCancel = $start->copy()->startOfDay()->subDays(3)->setTime(21, 0);
                $cancelAt = $this->randomMoment($verifiedAt->copy()->addHour(), $latestCancel);
                $refundAt = $cancelAt->copy()->addMinutes(mt_rand(30, 600));
                $rental = array_merge($rental, $approved, [
                    'status' => 'cancelled',
                    'payment_status' => 'refunded',
                    'cancelled_reason' => 'Dibatalkan oleh penyewa.',
                    'notes' => 'Dibatalkan penyewa; DP '.Format::rupiah($dp).' dikembalikan penuh. Cek mutasi rekening sebelum transfer refund.',
                    'updated_at' => $refundAt,
                ]);
                $dpPayment = array_merge($dpPayment, $dpSettled, ['payment_status' => 'refund', 'refunded_amount' => $dp, 'updated_at' => $refundAt]);
                break;

            case 'cancelled_document':
                $reason = self::DOCUMENT_REJECTIONS[array_rand(self::DOCUMENT_REJECTIONS)];
                $rental = array_merge($rental, [
                    'status' => 'cancelled',
                    'payment_status' => 'refunded',
                    'verification_status' => 'rejected',
                    'verification_rejection_reason' => $reason,
                    'verified_by' => $verifier,
                    'verified_at' => $verifiedAt,
                    'cancelled_reason' => 'Dokumen ditolak: '.$reason,
                    'notes' => 'Dokumen ditolak; DP '.Format::rupiah($dp).' direfund penuh. Refund DP dicatat oleh admin.',
                    'updated_at' => $verifiedAt,
                ]);
                $dpPayment = array_merge($dpPayment, $dpSettled, ['payment_status' => 'refund', 'refunded_amount' => $dp]);
                break;

            case 'cancelled_proof':
                $reason = self::PROOF_REJECTIONS[array_rand(self::PROOF_REJECTIONS)];
                $rental = array_merge($rental, [
                    'status' => 'cancelled',
                    'payment_status' => 'refunded',
                    'cancelled_reason' => 'Bukti pembayaran DP ditolak: '.$reason,
                    'notes' => 'Refund DP '.Format::rupiah($dp).' dicatat oleh admin.',
                    'updated_at' => $verifiedAt,
                ]);
                $dpPayment = array_merge($dpPayment, $dpSettled, [
                    'payment_status' => 'refund',
                    'refunded_amount' => $dp,
                    'rejection_reason' => $reason,
                    'rejection_count' => 1,
                ]);
                break;

            case 'no_show':
                $markedAt = $start->copy()->addMinutes(180 + mt_rand(0, 30));
                $rental = array_merge($rental, $approved, [
                    'status' => 'no_show',
                    'cancelled_reason' => 'Penyewa tidak datang mengambil motor sampai batas toleransi.',
                    'updated_at' => $markedAt,
                ]);
                $dpPayment = array_merge($dpPayment, $dpSettled);
                break;

            case 'active':
            case 'completed':
                $maxPickupDelay = $scenario === 'active'
                    ? min(90, (int) $start->diffInMinutes($this->now, true) - 5)
                    : 90;
                $pickedUpAt = $start->copy()->addMinutes($this->skewedMinutes(max(0, $maxPickupDelay)));

                $rental = array_merge($rental, $approved, [
                    'status' => 'active',
                    'payment_status' => 'fully_paid',
                    'balance_amount' => 0,
                    'picked_up_at' => $pickedUpAt,
                    'handed_over_by' => $handler,
                    'updated_at' => $pickedUpAt,
                ]);
                $dpPayment = array_merge($dpPayment, $dpSettled);

                if ($balance > 0) {
                    $extraPayments[] = [
                        'order_id' => $code.'-BAL',
                        'type' => 'balance',
                        'method' => mt_rand(1, 100) <= 55 ? 'cash' : 'manual_transfer',
                        'gross_amount' => $balance,
                        'payment_status' => 'settlement',
                        'paid_at' => $pickedUpAt,
                        'received_by' => $handler,
                        'created_at' => $pickedUpAt,
                        'updated_at' => $pickedUpAt,
                    ];
                }

                $reminders[] = $this->reminder('pickup_confirmation', $pickedUpAt, 'sent', $handler, $pickedUpAt->copy()->addMinutes(mt_rand(1, 15)));
                $returnReminderAt = $end->copy()->subMinutes(30);

                if ($scenario === 'active') {
                    $reminders[] = $returnReminderAt->gt($this->now)
                        ? $this->reminder('return_30m', $returnReminderAt, 'pending')
                        : $this->reminder('return_30m', $returnReminderAt, 'sent', $handler, $returnReminderAt->copy()->addMinutes(mt_rand(0, 10)));
                    break;
                }

                // Pengembalian: 75% tepat waktu, 25% telat 10 menit sampai 5 jam.
                $actualReturn = mt_rand(1, 100) <= 75
                    ? $end->copy()->subMinutes(mt_rand(0, 90))
                    : $end->copy()->addMinutes(mt_rand(10, 300));

                $lateHours = $actualReturn->gt($end) ? (int) ceil($end->diffInSeconds($actualReturn, true) / 3600) : 0;
                $lateFee = $lateHours * $hourly;
                $damageFee = 0;
                $fuelFee = 0;
                $notes = [];

                if (mt_rand(1, 100) <= 8) {
                    $damageFee = array_rand(self::DAMAGE_NOTES);
                    $notes[] = self::DAMAGE_NOTES[$damageFee];
                }
                if (mt_rand(1, 100) <= 12) {
                    $fuelFee = [10000, 15000, 20000, 25000][mt_rand(0, 3)];
                    $notes[] = 'Bensin kurang dari posisi awal';
                }

                $checker = $this->pickStaff();
                $return = [
                    'actual_return_time' => $actualReturn,
                    'late_hours' => $lateHours,
                    'late_fee' => $lateFee,
                    'damage_fee' => $damageFee,
                    'fuel_fee' => $fuelFee,
                    'condition_notes' => $notes === [] ? 'Kondisi baik' : implode('; ', $notes),
                    'checked_by' => $checker,
                    'created_at' => $actualReturn,
                    'updated_at' => $actualReturn,
                ];

                $totalFee = $lateFee + $damageFee + $fuelFee;
                if ($totalFee > 0) {
                    $paidAt = $actualReturn->copy()->addMinutes(mt_rand(2, 15));
                    $extraPayments[] = [
                        'order_id' => $code.'-FINE',
                        'type' => 'fine',
                        'method' => mt_rand(1, 100) <= 70 ? 'cash' : 'manual_transfer',
                        'gross_amount' => $totalFee,
                        'payment_status' => 'settlement',
                        'paid_at' => $paidAt,
                        'received_by' => $checker,
                        'created_at' => $paidAt,
                        'updated_at' => $paidAt,
                    ];
                }

                $reminders[] = $actualReturn->lte($returnReminderAt)
                    ? $this->reminder('return_30m', $returnReminderAt, 'skipped')
                    : $this->reminder('return_30m', $returnReminderAt, 'sent', $handler, $returnReminderAt->copy()->addMinutes(mt_rand(0, 10)));

                if ($lateHours >= 2) {
                    $overdueAt = $end->copy()->addMinutes(mt_rand(15, 45));
                    $reminders[] = $this->reminder('overdue', $overdueAt, 'sent', $handler, $overdueAt->copy()->addMinutes(mt_rand(1, 10)));
                }

                $rental = array_merge($rental, ['status' => 'completed', 'updated_at' => $actualReturn]);
                break;
        }

        $rentalId = DB::table('rentals')->insertGetId($rental);

        DB::table('payments')->insert(array_merge($dpPayment, ['rental_id' => $rentalId]));

        foreach ($extraPayments as $payment) {
            DB::table('payments')->insert(array_merge([
                'refunded_amount' => 0,
                'proof_photo' => null,
                'proof_uploaded_at' => null,
                'rejection_reason' => null,
                'rejection_count' => 0,
            ], $payment, ['rental_id' => $rentalId]));
        }

        if ($return) {
            DB::table('rental_returns')->insert(array_merge($return, ['rental_id' => $rentalId]));
        }

        foreach ($reminders as $reminder) {
            DB::table('rental_reminders')->insert(array_merge($reminder, ['rental_id' => $rentalId]));
        }
    }

    /** @return array<string, mixed> */
    private function reminder(string $type, Carbon $scheduledAt, string $status, ?int $sentBy = null, ?Carbon $sentAt = null): array
    {
        return [
            'type' => $type,
            'scheduled_at' => $scheduledAt,
            'status' => $status,
            'sent_by' => $status === 'sent' ? $sentBy : null,
            'sent_at' => $status === 'sent' ? $sentAt : null,
            'message_snapshot' => null,
            'created_at' => $scheduledAt,
            'updated_at' => $sentAt ?? $scheduledAt,
        ];
    }

    /** Format aplikasi: BK-YYYYMMDD-0001, berurutan per tanggal booking. */
    private function nextBookingCode(Carbon $createdAt): string
    {
        $prefix = 'BK-'.$createdAt->format('Ymd').'-';

        $this->codeCounters[$prefix] ??= DB::table('rentals')->where('booking_code', 'like', $prefix.'%')->count();
        $this->codeCounters[$prefix]++;

        return $prefix.str_pad((string) $this->codeCounters[$prefix], 4, '0', STR_PAD_LEFT);
    }

    /** Admin biasa melayani sekitar 3x lebih sering daripada owner. */
    private function pickStaff(): int
    {
        $ids = array_keys($this->staff);
        $weights = array_map(fn (string $role) => $role === 'admin' ? 3 : 1, array_values($this->staff));

        return $ids[$this->weightedIndex($weights)];
    }

    /** Menit keterlambatan pengambilan: kebanyakan cepat, sesekali lama. */
    private function skewedMinutes(int $max): int
    {
        return (int) round($max * ((mt_rand() / mt_getrandmax()) ** 2));
    }

    private function randomMoment(Carbon $from, Carbon $to): Carbon
    {
        if ($to->lte($from)) {
            return $from->copy();
        }

        return $from->copy()->addSeconds(mt_rand(0, (int) $from->diffInSeconds($to, true)))->startOfMinute();
    }

    /**
     * @param  array<int|string, int|float>  $weights  nilai => bobot
     */
    private function weighted(array $weights): int|string
    {
        $keys = array_keys($weights);

        return $keys[$this->weightedIndex(array_values($weights))];
    }

    /** @param  array<int, int|float>  $weights */
    private function weightedIndex(array $weights): int
    {
        $roll = mt_rand() / mt_getrandmax() * array_sum($weights);

        foreach (array_values($weights) as $index => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $index;
            }
        }

        return count($weights) - 1;
    }
}
