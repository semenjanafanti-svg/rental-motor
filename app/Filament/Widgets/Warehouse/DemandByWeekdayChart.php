<?php

namespace App\Filament\Widgets\Warehouse;

use Filament\Support\RawJs;

/** Pertanyaan 3: hari apa permintaan tinggi (akhir pekan vs hari kerja). */
class DemandByWeekdayChart extends WarehouseChart
{
    protected ?string $heading = 'Pesanan menurut hari mulai sewa';

    protected ?string $description = 'Fact_Penyewaan × Dim_Waktu (nama_hari) · semua pesanan masuk';

    protected static ?int $sort = 5;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = $this->analytics()->demandByWeekday($this->year());

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
