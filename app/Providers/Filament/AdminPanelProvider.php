<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Support\Enums\Width;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->sidebarWidth('15rem')
            ->maxContentWidth(Width::Full)
            // Pelat "SM" + SEWAMOTOR, sama dengan .brand di navbar customer.
            // Gayanya ada di public/css/filament-admin-theme.css (.fi-logo .brand-plate).
            ->brandName(new HtmlString('<span class="brand-plate">SM</span> SEWAMOTOR'))
            // Work Sans untuk isi. Oswald untuk judul dimuat lewat render hook di bawah.
            ->font('Work Sans')
            // Warna diambil langsung dari token prototipe, bukan preset bawaan Filament.
            ->colors([
                'primary' => Color::hex('#f0b429'),
                'danger' => Color::hex('#c0392b'),
                'success' => Color::hex('#2f6f5e'),
                'warning' => Color::hex('#f0b429'),
            ])
            // CSS statis (bukan lewat Vite/Tailwind) agar tidak bentrok dengan Tailwind v3
            // yang dipakai frontend customer. ?v= memaksa browser memuat ulang saat file berubah.
            ->renderHook(PanelsRenderHook::HEAD_END, function (): string {
                $css = public_path('css/filament-admin-theme.css');
                $version = is_file($css) ? filemtime($css) : 0;

                return '<link rel="preconnect" href="https://fonts.bunny.net">'
                    . '<link rel="stylesheet" href="https://fonts.bunny.net/css?family=oswald:500,600,700&display=swap">'
                    . '<link rel="stylesheet" href="' . e(asset('css/filament-admin-theme.css')) . '?v=' . $version . '">';
            })
            ->navigationGroups(['Operasional', 'Owner'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // FilamentInfoWidget dihapus: isinya tautan dokumentasi dan logo Filament.
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}