<?php

namespace App\Filament\Widgets\Warehouse;

use App\Filament\Widgets\BaseChart;
use App\Services\DataWarehouse\WarehouseAnalytics;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Dasar semua grafik dashboard DW (gaya grafik ada di BaseChart).
 *
 * Setiap grafik punya filter sendiri (ikon corong di pojok kanan atas):
 * tahun, ditambah satu filter dimensi khusus grafik lewat extraFilters().
 * Bila tahun grafik dibiarkan kosong, grafik mengikuti filter Periode di atas halaman.
 */
abstract class WarehouseChart extends BaseChart
{
    use HasFiltersSchema;
    use InteractsWithPageFilters;

    /** Nilai filter tahun untuk "semua tahun" (beda dengan kosong = ikuti filter halaman). */
    protected const ALL_YEARS = 'semua';

    protected static bool $isDiscovered = false;

    protected function analytics(): WarehouseAnalytics
    {
        return app(WarehouseAnalytics::class);
    }

    public function filtersSchema(Schema $schema): Schema
    {
        $years = $this->analytics()->years();

        return $schema->components([
            Select::make('tahun')
                ->label('Tahun')
                ->placeholder('Ikuti filter Periode')
                ->options([self::ALL_YEARS => 'Semua tahun'] + array_combine($years, array_map(fn ($y) => 'Tahun '.$y, $years)))
                ->native(false),
            ...$this->extraFilters(),
        ]);
    }

    /**
     * Filter dimensi tambahan khusus grafik.
     *
     * @return array<Component>
     */
    protected function extraFilters(): array
    {
        return [];
    }

    /** Nilai satu filter grafik; string kosong dianggap tidak difilter. */
    protected function filterValue(string $name): ?string
    {
        $value = $this->filters[$name] ?? null;

        return filled($value) ? (string) $value : null;
    }

    /** Tahun dari filter grafik; bila kosong, ikut filter Periode halaman. null = semua tahun. */
    protected function year(): ?int
    {
        $year = $this->filterValue('tahun') ?? ($this->pageFilters['tahun'] ?? null);

        return filled($year) && $year !== self::ALL_YEARS ? (int) $year : null;
    }

    /** @return array<string, string> */
    protected function categoryOptions(): array
    {
        return collect($this->analytics()->categories())
            ->mapWithKeys(fn (string $c) => [$c => ucfirst($c)])->all();
    }

    public static function canView(): bool
    {
        return app(WarehouseAnalytics::class)->isReady();
    }
}
