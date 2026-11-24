<?php

namespace App\Filament\Widgets\Warehouse;

use Filament\Forms\Components\Select;
use Filament\Support\RawJs;

/** Pertanyaan 6: dari mana dan berapa pendapatan yang diperoleh. */
class RevenueByMonthChart extends WarehouseChart
{
    protected ?string $heading = 'Pendapatan bersih per bulan';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    protected function extraFilters(): array
    {
        return [
            Select::make('metode')
                ->label('Metode pembayaran')
                ->placeholder('Semua metode')
                ->options(collect(config('dw.payment_methods'))->map(fn ($m) => $m[0])->all())
                ->native(false),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = $this->analytics()->revenueByMonth($this->year(), $this->filterValue('metode'));

        return [
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [
                $this->barDataset('DP', $rows->pluck('DP')->all(), 0, ['borderRadius' => 0]),
                $this->barDataset('Pelunasan', $rows->pluck('Pelunasan')->all(), 1, ['borderRadius' => 0]),
                $this->barDataset('Denda', $rows->pluck('Denda')->all(), 2, ['borderRadius' => ['topLeft' => 4, 'topRight' => 4]]),
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return $this->baseOptions('rupiah', legend: true, stacked: true);
    }
}
