<?php

namespace App\Filament\Resources\Bikes\Pages;

use App\Filament\Resources\Bikes\BikeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBike extends CreateRecord
{
    protected static string $resource = BikeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}