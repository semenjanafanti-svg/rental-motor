<?php

namespace App\Filament\Widgets\Operational;

use App\Models\Rental;
use Filament\Forms\Components\Select;
use Filament\Support\RawJs;

/** Pesanan masuk per hari (menurut waktu booking dibuat). */
class IncomingOrdersChart extends OperationalChart
{
    protected ?string $heading = 'Pesanan masuk per hari';

    protected static ?int $sort = 3;

    protected function filterComponents(): array
    {
        return [
            Select::make('rentang')
                ->label('Rentang')
                ->options(['14' => '14 hari terakhir', '30' => '30 hari terakhir'])
                ->default('14')
                ->selectablePlaceholder(false)
                ->native(false),
            Select::make('kelompok')
                ->label('Status pesanan')
                ->placeholder('Semua status')
                ->options([
                    'Berhasil' => 'Selesai',
                    'Berjalan' => 'Terkonfirmasi / sedang disewa',
                    'Menunggu' => 'Menunggu',
                    'Gagal' => 'Batal / kedaluwarsa / tidak datang',
                ])
                ->native(false),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = (int) ($this->filterValue('rentang') ?? 14);
        $from = today()->subDays($days - 1);
        $group = $this->filterValue('kelompok');

        // Status operasional -> kelompok, sama dengan dim_status_rental di data warehouse.
        $statuses = collect(config('dw.rental_statuses'))
            ->filter(fn (array $s) => $group === null || $s[1] === $group)
            ->keys();

        $counts = Rental::query()
            ->where('created_at', '>=', $from)
            ->whereIn('status', $statuses)
            ->get(['created_at'])
            ->countBy(fn ($r) => $r->created_at->toDateString());

        $labels = $data = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $labels[] = $day->locale('id')->translatedFormat('d M');
            $data[] = $counts[$day->toDateString()] ?? 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Pesanan',
                'data' => $data,
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
