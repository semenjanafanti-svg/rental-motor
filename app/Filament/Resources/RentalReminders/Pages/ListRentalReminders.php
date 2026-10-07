<?php

namespace App\Filament\Resources\RentalReminders\Pages;

use App\Filament\Resources\RentalReminders\RentalReminderResource;
use App\Services\OverdueReminderService;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListRentalReminders extends ListRecords
{
    protected static string $resource = RentalReminderResource::class;

    protected function getTableQuery(): Builder|Relation|null
    {
        app(OverdueReminderService::class)->createMissing();

        return parent::getTableQuery();
    }

    public function getSubheading(): ?string
    {
        return 'Kirim pengingat WhatsApp manual ke penyewa. Reminder muncul setelah waktu jadwalnya tiba.';
    }
}
