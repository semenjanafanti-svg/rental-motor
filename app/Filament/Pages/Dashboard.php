<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OwnerOverview;
use App\Filament\Widgets\Warehouse\DemandByWeekdayChart;
use App\Filament\Widgets\Warehouse\FinesByCategoryChart;
use App\Filament\Widgets\Warehouse\RentalsByMonthChart;
use App\Filament\Widgets\Warehouse\RevenueByMonthChart;
use App\Filament\Widgets\Warehouse\TopCustomersTable;
use App\Filament\Widgets\Warehouse\WarehouseKpis;
use App\Services\DataWarehouse\EtlService;
use App\Services\DataWarehouse\WarehouseAnalytics;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

/**
 * Satu-satunya dashboard panel admin (/admin).
 * - Semua petugas: ringkasan operasional (tabel OLTP, real time).
 * - Owner (super admin): ditambah analitik dari data warehouse, filter periode,
 *   tombol ETL dan OLAP. Query analitik membaca dw_mitra_jalan, bukan tabel
 *   operasional, sehingga tidak membebani proses booking.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = -2;

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    private static function isOwner(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function filtersForm(Schema $schema): Schema
    {
        $years = app(WarehouseAnalytics::class)->years();

        return $schema->components([
            Select::make('tahun')
                ->visible(self::isOwner())
                ->label('Periode')
                ->placeholder('Semua periode')
                ->options(array_combine($years, array_map(fn ($y) => 'Tahun '.$y, $years)))
                ->native(false),
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (! self::isOwner()) {
            return null;
        }

        $last = app(WarehouseAnalytics::class)->lastEtl();

        if (! $last) {
            return 'Data warehouse belum dibangun. Klik "Jalankan ETL" untuk memuat data dari database operasional.';
        }

        return sprintf('Sumber: data warehouse dw_mitra_jalan · ETL terakhir %s (%s)',
            Carbon::parse($last->selesai ?? $last->mulai)->translatedFormat('d M Y H:i'), $last->status);
    }

    public function getColumns(): int|array
    {
        return ['md' => 2];
    }

    public function getWidgets(): array
    {
        if (! self::isOwner()) {
            return [OwnerOverview::class];
        }

        return [
            OwnerOverview::class,
            WarehouseKpis::class,
            RevenueByMonthChart::class,
            RentalsByMonthChart::class,
            DemandByWeekdayChart::class,
            FinesByCategoryChart::class,
            TopCustomersTable::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        if (! self::isOwner()) {
            return [];
        }

        return [
            Action::make('olap')
                ->label('Buka OLAP')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(OlapExplorer::getUrl()),
            Action::make('etl')
                ->label('Jalankan ETL')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Jalankan ETL sekarang?')
                ->modalDescription('Dimensi diperbarui dan tabel fakta dimuat ulang dari database operasional. Biasanya selesai dalam beberapa detik.')
                ->action(function () {
                    try {
                        $counts = app(EtlService::class)->run();
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->title('ETL gagal')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title('ETL selesai')
                        ->body(sprintf('%d penyewaan, %d pembayaran, %d pengembalian dimuat.',
                            $counts['fact_penyewaan'], $counts['fact_pembayaran'], $counts['fact_pengembalian']))
                        ->success()->send();

                    $this->redirect(static::getUrl());
                }),
        ];
    }
}
