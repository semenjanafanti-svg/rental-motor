<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var User $user */
        $user = $this->record;
        abort_unless(UserResource::canEdit($user), 403);

        $role = $data['role'] ?? $user->role;
        abort_unless(in_array($role, ['customer', 'admin'], true), 422);

        // role and is_active are intentionally guarded by the User model's fillable list.
        $user->forceFill([
            'role' => $role,
            'is_active' => (bool) ($data['is_active'] ?? $user->is_active),
        ]);
        unset($data['role'], $data['is_active']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->fill($data)->save();

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
