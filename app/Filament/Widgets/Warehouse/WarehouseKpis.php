<?php

namespace App\Filament\Widgets\Warehouse;

use App\Services\DataWarehouse\WarehouseAnalytics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WarehouseKpis extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected ?string $heading = 'Analitik data warehouse';

    protected int|array|null $columns = ['md' => 3, '2xl' => 6];

    protected function getStats(): array
    {
        $a = app(WarehouseAnalytics::class);
        $year = filled($this->pageFilters['tahun'] ?? null) ? (int) $this->pageFilters['tahun'] : null;
        $k = $a->kpis($year);

        return [
            Stat::make('Pendapatan bersih', $this->compact($k['pendapatan_bersih']))
                ->description($a->rupiah($k['pendapatan_bersih']).' · DP + pelunasan + denda − refund'),
            Stat::make('Sewa berhasil', number_format($k['sewa_berhasil'], 0, ',', '.'))
                ->description('dari '.$k['pesanan'].' pesanan masuk'),
            Stat::make('Pesanan gagal', $a->pct($k['rasio_gagal']))
                ->description($k['pesanan_gagal'].' batal, kedaluwarsa, tidak datang'),
            Stat::make('Utilisasi armada', $a->pct($k['utilisasi']))
                ->description('hari motor disewa ÷ hari tersedia'),
            Stat::make('Rata-rata sewa', number_format($k['rata_durasi'], 1, ',', '.').' hari')
                ->description($a->rupiah($k['rata_nilai_sewa']).' per sewa'),
            Stat::make('Pelanggan berulang', $k['pelanggan_berulang'].' / '.$k['pelanggan_aktif'])
                ->description('menyewa '.config('dw.repeat_customer_min_rentals').'x atau lebih'),
        ];
    }

    /** Rp25.548.000 -> "Rp25,5 jt" supaya muat di kartu. */
    private function compact(float $value): string
    {
        return match (true) {
            $value >= 1e9 => 'Rp'.number_format($value / 1e9, 1, ',', '.').' M',
            $value >= 1e6 => 'Rp'.number_format($value / 1e6, 1, ',', '.').' jt',
            default => 'Rp'.number_format($value, 0, ',', '.'),
        };
    }

    public static function canView(): bool
    {
        return app(WarehouseAnalytics::class)->isReady();
    }
}
