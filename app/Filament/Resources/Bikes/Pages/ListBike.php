<?php

namespace App\Filament\Resources\Bikes\Pages;

use App\Filament\Resources\Bikes\BikeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBike extends ListRecords
{
    protected static string $resource = BikeResource::class;

    public function getSubheading(): ?string
    {
        return 'Atur armada, tarif, foto, dan fasilitas yang tampil di katalog pelanggan.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Motor')];
    }
}
