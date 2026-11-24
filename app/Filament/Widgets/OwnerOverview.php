<?php

namespace App\Filament\Widgets;

use App\Models\Bike;
use App\Models\Rental;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ringkasan operasional real time (tabel OLTP) untuk semua petugas. */
class OwnerOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $heading = 'Ringkasan operasional';

    protected int|array|null $columns = ['md' => 3, 'xl' => 6];

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $overdue = Rental::where('status', 'active')->where('end_time', '<', now())->count();

        return [
            Stat::make('Motor siap disewa', Bike::where('status', 'available')->count())
                ->description('dari '.Bike::count().' unit terdaftar')->color('success'),
            Stat::make('Menunggu verifikasi', Rental::where('status', 'pending_verification')->count())
                ->description('Perlu tindakan admin')->color('warning'),
            Stat::make('Diambil hari ini', Rental::whereIn('status', ['approved', 'active'])->whereBetween('start_time', [today(), today()->endOfDay()])->count())
                ->description('Jadwal serah terima motor')->color('primary'),
            Stat::make('Sedang disewa', Rental::where('status', 'active')->count())
                ->description('Unit aktif di jalan')->color('primary'),
            Stat::make('Terlambat kembali', $overdue)
                ->description($overdue ? 'Segera hubungi penyewa' : 'Semua tepat waktu')
                ->descriptionIcon($overdue ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdue ? 'danger' : 'success'),
            Stat::make('Selesai bulan ini', Rental::where('status', 'completed')->whereMonth('updated_at', now()->month)->whereYear('updated_at', now()->year)->count())
                ->description('Transaksi tuntas')->color('success'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }
}
