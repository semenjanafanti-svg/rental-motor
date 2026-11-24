<?php

namespace App\Filament\Widgets\Warehouse;

use Filament\Forms\Components\Select;
use Filament\Support\RawJs;

/** Pertanyaan 3: hari apa permintaan tinggi (akhir pekan vs hari kerja). */
class DemandByWeekdayChart extends WarehouseChart
{
    protected ?string $heading = 'Pesanan menurut hari mulai sewa';

    protected static ?int $sort = 5;

    protected function extraFilters(): array
    {
        return [
            Select::make('kelompok')
                ->label('Status pesanan')
                ->placeholder('Semua pesanan masuk')
                ->options(['Berhasil' => 'Berhasil (selesai)', 'Gagal' => 'Gagal (batal, kedaluwarsa, tidak datang)'])
                ->native(false),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = $this->analytics()->demandByWeekday($this->year(), $this->filterValue('kelompok'));

        return [
            'labels' => $rows->pluck('label')->all(),
            'datasets' => [$this->barDataset('Pesanan', $rows->pluck('jumlah')->all())],
        ];
    }

    protected function getOptions(): RawJs
    {
        return $this->baseOptions('angka');
    }
}
