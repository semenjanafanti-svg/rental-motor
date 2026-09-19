<?php

namespace Tests\Unit;

use App\Services\PricingService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    private function service(): PricingService
    {
        return new PricingService(dpPercent: 30, dpRounding: 1000);
    }

    #[DataProvider('cases')]
    public function test_calculate(int $hourly, int $daily, int $hours, int $total, int $dp, int $balance): void
    {
        $result = $this->service()->calculate($hourly, $daily, $hours);

        $this->assertSame($hours, $result['total_hours']);
        $this->assertSame($total, $result['total_price']);
        $this->assertSame($dp, $result['dp_amount']);
        $this->assertSame($balance, $result['balance_amount']);
    }

    public static function cases(): array
    {
        // [hourly, daily, jam, total, dp, balance]
        return [
            '12 jam: tarif jam melebihi harian, dibatasi harian' => [12000, 100000, 12, 100000, 30000, 70000],
            '13 jam: DP dibulatkan ke atas ke Rp1.000'          => [9000, 75000, 13, 75000, 23000, 52000],
            '24 jam tepat'                                       => [30000, 250000, 24, 250000, 75000, 175000],
            '30 jam: 1 hari + 6 jam'                             => [9000, 75000, 30, 129000, 39000, 90000],
            '36 jam: sisa 12 jam dibatasi tarif harian'          => [18000, 150000, 36, 300000, 90000, 210000],
            '50 jam: 2 hari + 2 jam'                             => [10000, 85000, 50, 190000, 57000, 133000],
        ];
    }

    public function test_total_hours_rounds_up_to_full_hour(): void
    {
        $start = Carbon::parse('2026-09-20 08:00:00');

        $this->assertSame(12, $this->service()->totalHours($start, $start->copy()->addHours(12)));
        $this->assertSame(13, $this->service()->totalHours($start, $start->copy()->addHours(12)->addMinute()));
    }
}
