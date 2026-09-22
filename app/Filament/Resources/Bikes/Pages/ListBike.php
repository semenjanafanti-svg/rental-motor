<?php

namespace App\Filament\Resources\Bikes\Pages;

use App\Filament\Resources\Bikes\BikeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBikes extends ListRecords
{
    protected static string $resource = BikeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Motor')];
    }
}