<?php

namespace App\Filament\Widgets\Warehouse;

use Filament\Forms\Components\Select;
use Filament\Support\RawJs;

/** Pertanyaan 9 & 10: keterlambatan, kerusakan, dan bensin per kategori motor. */
class FinesByCategoryChart extends WarehouseChart
{
    protected ?string $heading = 'Denda pengembalian per kategori';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected function extraFilters(): array
    {
        return [
            Select::make('kuartal')
                ->label('Kuartal')
                ->placeholder('Sepanjang tahun')
                ->options(['Q1' => 'Q1 (Jan-Mar)', 'Q2' => 'Q2 (Apr-Jun)', 'Q3' => 'Q3 (Jul-Sep)', 'Q4' => 'Q4 (Okt-Des)'])
                ->native(false),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = $this->analytics()->finesByCategory($this->year(), $this->filterValue('kuartal'));

        return [
            'labels' => $rows->map(fn ($r) => ucfirst($r->kategori))->all(),
            'datasets' => [
                $this->barDataset('Telat', $rows->pluck('denda_telat')->map(fn ($v) => (float) $v)->all(), 0, ['borderRadius' => 0]),
                $this->barDataset('Kerusakan', $rows->pluck('denda_kerusakan')->map(fn ($v) => (float) $v)->all(), 1, ['borderRadius' => 0]),
                $this->barDataset('Bensin', $rows->pluck('denda_bensin')->map(fn ($v) => (float) $v)->all(), 2, ['borderRadius' => ['topLeft' => 4, 'topRight' => 4]]),
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return $this->baseOptions('rupiah', legend: true, stacked: true);
    }
}
