<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = new User;
        $user->fill($data);
        $user->forceFill([
            // Creation from this resource is reserved for staff; customers self-register.
            'role' => 'admin',
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();

        return $user;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
