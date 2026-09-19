<?php

namespace App\Services;

use Carbon\CarbonInterface;

class PricingService
{
    /**
     * Nilai null berarti dibaca dari config/rental.php.
     * Di unit test, isi langsung agar tidak butuh aplikasi Laravel.
     */
    public function __construct(
        private readonly ?float $dpPercent = null,
        private readonly ?int $dpRounding = null,
    ) {
    }

    /** Jumlah jam sewa, dibulatkan ke atas ke jam penuh. */
    public function totalHours(CarbonInterface $start, CarbonInterface $end): int
    {
        $seconds = $end->getTimestamp() - $start->getTimestamp();

        return (int) ceil($seconds / 3600);
    }

    /**
     * Rumus harga dari dokumen rancangan (semua nominal dalam rupiah utuh):
     *   hari      = floor(total_hours / 24)
     *   sisa_jam  = total_hours mod 24
     *   total     = hari * daily + min(sisa_jam * hourly, daily)
     *   dp        = ceil(total * persen / rounding) * rounding
     *   balance   = total - dp
     *
     * @return array{total_hours:int, total_price:int, dp_amount:int, balance_amount:int}
     */
    public function calculate(float|int|string $hourlyRate, float|int|string $dailyRate, int $totalHours): array
    {
        $hourly = (int) round((float) $hourlyRate);
        $daily = (int) round((float) $dailyRate);

        $days = intdiv($totalHours, 24);
        $remainingHours = $totalHours % 24;

        $total = ($days * $daily) + min($remainingHours * $hourly, $daily);

        $percent = $this->dpPercent ?? (float) config('rental.dp_percent');
        $rounding = $this->dpRounding ?? (int) config('rental.dp_rounding');

        $dp = (int) (ceil(($total * $percent / 100) / $rounding) * $rounding);
        $dp = min($dp, $total); // pembulatan tidak boleh membuat DP melebihi total

        return [
            'total_hours' => $totalHours,
            'total_price' => $total,
            'dp_amount' => $dp,
            'balance_amount' => $total - $dp,
        ];
    }
}
