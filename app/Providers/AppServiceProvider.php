<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Pagination Sebelumnya / Berikutnya mengikuti tema (resources/views/pagination/rental.blade.php)
        Paginator::defaultView('pagination.rental');
        Paginator::defaultSimpleView('pagination.rental');
    }
}
