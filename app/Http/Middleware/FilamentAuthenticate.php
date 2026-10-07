<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;

class FilamentAuthenticate extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        // Let a signed-in user end their session even if their panel access was
        // revoked while this browser tab was still open.
        if ($request->routeIs('filament.admin.auth.logout') && Filament::auth()->check()) {
            return;
        }

        parent::authenticate($request, $guards);
    }

    protected function redirectTo($request): ?string
    {
        return route('login');
    }
}
