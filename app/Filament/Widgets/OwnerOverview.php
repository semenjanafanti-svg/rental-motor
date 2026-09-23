<?php

namespace App\Filament\Widgets;

use App\Models\Bike;
use App\Models\Rental;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OwnerOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Ringkasan Mitra Jalan';

    protected function getStats(): array
    {
        return [
            Stat::make('Motor siap disewa', Bike::where('status', 'available')->count())
                ->description('dari ' . Bike::count() . ' unit terdaftar')->color('success'),
            Stat::make('Menunggu verifikasi', Rental::where('status', 'pending_verification')->count())
                ->description('Perlu tindakan admin')->color('warning'),
            Stat::make('Sedang disewa', Rental::where('status', 'active')->count())
                ->description('Unit aktif di jalan')->color('primary'),
            Stat::make('Selesai bulan ini', Rental::where('status', 'completed')->whereMonth('updated_at', now()->month)->whereYear('updated_at', now()->year)->count())
                ->description('Transaksi tuntas')->color('success'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }
}
