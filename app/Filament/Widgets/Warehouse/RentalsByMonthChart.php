<?php

namespace App\Filament\Widgets\Warehouse;

use Filament\Forms\Components\Select;
use Filament\Support\RawJs;

/** Pertanyaan 3: kapan permintaan tinggi dan rendah (tren bulanan). */
class RentalsByMonthChart extends WarehouseChart
{
    protected ?string $heading = 'Sewa berhasil per bulan';

    protected static ?int $sort = 4;

    protected function extraFilters(): array
    {
        return [
            Select::make('kategori')
                ->label('Kategori motor')
                ->placeholder('Semua kategori')
                ->options(fn () => $this->categoryOptions())
                ->native(false),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $rows = $this->analytics()->rentalsByMonth($this->year(), $this->filterValue('kategori'));

        return [
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [[
                'label' => 'Sewa berhasil',
                'data' => $rows->pluck('jumlah')->all(),
                'borderColor' => self::SERIES[0],
                'backgroundColor' => self::SERIES[0],
                'borderWidth' => 2,
                'pointRadius' => 4,
                'pointHoverRadius' => 6,
                'pointBorderColor' => '#ffffff',
                'pointBorderWidth' => 2,
                'cubicInterpolationMode' => 'monotone', // tidak melampaui titik data
                'fill' => false,
            ]],
        ];
    }

    protected function getOptions(): RawJs
    {
        return $this->baseOptions('angka');
    }
}
