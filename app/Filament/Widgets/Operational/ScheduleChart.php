<?php

namespace App\Filament\Widgets\Operational;

use App\Models\Rental;
use Filament\Forms\Components\Select;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;

/** Jadwal serah terima ke depan: berapa motor diambil dan dikembalikan tiap hari. */
class ScheduleChart extends OperationalChart
{
    protected ?string $heading = 'Jadwal ambil & kembali';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /** Status yang punya jadwal serah terima nyata. */
    private const SCHEDULED = ['approved', 'active', 'completed'];

    protected function filterComponents(): array
    {
        return [
            Select::make('rentang')
                ->label('Rentang')
                ->options(['7' => '7 hari ke depan', '14' => '14 hari ke depan'])
                ->default('7')
                ->selectablePlaceholder(false)
                ->native(false),
            Select::make('kategori')
                ->label('Kategori motor')
                ->placeholder('Semua kategori')
                ->options(self::categoryOptions())
                ->native(false),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $days = (int) ($this->filterValue('rentang') ?? 7);
        $from = today();
        $to = today()->addDays($days - 1)->endOfDay();
        $category = $this->filterValue('kategori');

        $rentals = Rental::query()
            ->whereIn('status', self::SCHEDULED)
            ->where(fn (Builder $q) => $q->whereBetween('start_time', [$from, $to])->orWhereBetween('end_time', [$from, $to]))
            ->when($category, fn (Builder $q) => $q->whereHas('bike', fn (Builder $b) => $b->where('category', $category)))
            ->get(['start_time', 'end_time']);

        $labels = $pickups = $returns = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $labels[] = $i === 0 ? 'Hari ini' : $day->locale('id')->translatedFormat('D d/m');
            $pickups[] = $rentals->filter(fn ($r) => $r->start_time->isSameDay($day))->count();
            $returns[] = $rentals->filter(fn ($r) => $r->end_time->isSameDay($day))->count();
        }

        return [
            'labels' => $labels,
            'datasets' => [
                $this->barDataset('Diambil', $pickups, 0),
                $this->barDataset('Dikembalikan', $returns, 1),
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return $this->baseOptions('angka', legend: true);
    }
}
