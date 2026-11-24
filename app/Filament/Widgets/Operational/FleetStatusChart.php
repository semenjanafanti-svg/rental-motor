<?php

namespace App\Filament\Widgets\Operational;

use App\Models\Bike;
use App\Models\Rental;
use Filament\Forms\Components\Select;
use Filament\Support\RawJs;

/** Kondisi armada saat ini: tersedia, sedang disewa, atau dalam perawatan. */
class FleetStatusChart extends OperationalChart
{
    protected ?string $heading = 'Status armada saat ini';

    protected static ?int $sort = 4;

    protected function filterComponents(): array
    {
        return [
            Select::make('kategori')
                ->label('Kategori motor')
                ->placeholder('Semua kategori')
                ->options(self::categoryOptions())
                ->helperText('Pilih satu kategori untuk melihat rincian per motor.')
                ->native(false),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $category = $this->filterValue('kategori');
        $rented = Rental::where('status', 'active')->pluck('bike_id')->flip();

        $bikes = Bike::query()
            ->where('status', '!=', 'inactive')
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderBy('category')->orderBy('name')
            ->get(['id', 'name', 'category', 'status'])
            ->map(fn (Bike $bike) => [
                'group' => $category ? $bike->name : ucfirst($bike->category),
                'state' => match (true) {
                    isset($rented[$bike->id]) => 'Disewa',
                    $bike->status === 'maintenance' => 'Perawatan',
                    default => 'Tersedia',
                },
            ]);

        $groups = $bikes->pluck('group')->unique()->values();
        $series = fn (string $state) => $groups
            ->map(fn ($g) => $bikes->where('group', $g)->where('state', $state)->count())->all();

        return [
            'labels' => $groups->all(),
            'datasets' => [
                $this->barDataset('Tersedia', $series('Tersedia'), 0, ['borderRadius' => 0]),
                $this->barDataset('Disewa', $series('Disewa'), 1, ['borderRadius' => 0]),
                $this->barDataset('Perawatan', $series('Perawatan'), 2, ['borderRadius' => ['topLeft' => 4, 'topRight' => 4]]),
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return $this->baseOptions('angka', legend: true, stacked: true);
    }
}
