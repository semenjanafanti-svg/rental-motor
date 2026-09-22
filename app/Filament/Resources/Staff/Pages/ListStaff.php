<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStaff extends ListRecords
{
    protected static string $resource = StaffResource::class;

    public function getSubheading(): ?string
    {
        return 'Kelola akun admin operasional (hanya Super Admin).';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Staf')];
    }
}