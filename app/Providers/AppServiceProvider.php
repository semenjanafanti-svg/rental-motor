<?php

namespace App\Providers;

use App\Filament\Auth\Responses\LandingLogoutResponse;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->bind(LogoutResponse::class, LandingLogoutResponse::class);

        // Pagination Sebelumnya / Berikutnya mengikuti tema (resources/views/pagination/rental.blade.php)
        Paginator::defaultView('pagination.rental');
        Paginator::defaultSimpleView('pagination.rental');
    }
}
