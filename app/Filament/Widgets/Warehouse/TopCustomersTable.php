<?php

namespace App\Filament\Widgets\Warehouse;

use App\Services\DataWarehouse\WarehouseAnalytics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/** Pertanyaan 5: siapa pelanggan yang paling bernilai. */
class TopCustomersTable extends Widget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.warehouse.top-customers';

    /** Filter milik tabel ini. Tahun kosong = ikut filter Periode halaman, "semua" = semua tahun. */
    public string $tahun = '';

    public string $segmen = '';

    protected function getViewData(): array
    {
        $analytics = app(WarehouseAnalytics::class);
        $year = $this->tahun !== '' ? $this->tahun : ($this->pageFilters['tahun'] ?? null);
        $year = filled($year) && $year !== 'semua' ? (int) $year : null;

        return [
            'rows' => $analytics->topCustomers($year, segment: $this->segmen !== '' ? $this->segmen : null),
            'years' => $analytics->years(),
            'analytics' => $analytics,
        ];
    }

    public static function canView(): bool
    {
        return app(WarehouseAnalytics::class)->isReady();
    }
}
