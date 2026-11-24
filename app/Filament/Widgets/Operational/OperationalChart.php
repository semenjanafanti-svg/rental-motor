<?php

namespace App\Filament\Widgets\Operational;

use App\Filament\Widgets\BaseChart;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;

/**
 * Dasar grafik operasional petugas: data langsung dari database operasional (real time),
 * dan setiap grafik punya filter sendiri (ikon corong di pojok kanan atas).
 */
abstract class OperationalChart extends BaseChart
{
    use HasFiltersSchema;

    /** @return array<Component> */
    abstract protected function filterComponents(): array;

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components($this->filterComponents());
    }

    protected function filterValue(string $name): ?string
    {
        $value = $this->filters[$name] ?? null;

        return filled($value) ? (string) $value : null;
    }

    /** @return array<string, string> */
    protected static function categoryOptions(): array
    {
        return ['matic' => 'Matic', 'manual' => 'Manual', 'sport' => 'Sport'];
    }

    public static function canView(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }
}
