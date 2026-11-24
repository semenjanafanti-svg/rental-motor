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

    protected function getViewData(): array
    {
        $analytics = app(WarehouseAnalytics::class);
        $year = filled($this->pageFilters['tahun'] ?? null) ? (int) $this->pageFilters['tahun'] : null;

        return [
            'rows' => $analytics->topCustomers($year),
            'analytics' => $analytics,
        ];
    }

    public static function canView(): bool
    {
        return app(WarehouseAnalytics::class)->isReady();
    }
}
