<?php

namespace App\Filament\Auth\Pages;

use Filament\Auth\Pages\Login;

class ActiveLogin extends Login
{
    protected function getCredentialsFromFormData(#[\SensitiveParameter] array $data): array
    {
        return [
            'email' => $data['email'],
            'password' => $data['password'],
            'is_active' => true,
        ];
    }
}
