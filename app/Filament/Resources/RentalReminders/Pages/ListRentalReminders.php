<?php

namespace App\Filament\Resources\RentalReminders\Pages;

use App\Filament\Resources\RentalReminders\RentalReminderResource;
use Filament\Resources\Pages\ListRecords;

class ListRentalReminders extends ListRecords
{
    protected static string $resource = RentalReminderResource::class;

    public function getSubheading(): ?string
    {
        return 'Kirim pengingat WhatsApp manual ke penyewa. Reminder muncul setelah waktu jadwalnya tiba.';
    }
}
