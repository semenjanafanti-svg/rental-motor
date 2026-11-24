<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Pages\ActiveLogin;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\FilamentAuthenticate;
use Filament\Enums\ThemeMode;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(ActiveLogin::class)
            // Panel admin selalu memakai tampilan terang tanpa pilihan tema.
            ->darkMode(false)
            ->themeSwitcher(false)
            ->defaultThemeMode(ThemeMode::Light)
            ->globalSearch(false)
            ->sidebarWidth('15rem')
            ->maxContentWidth(Width::Full)
            // Logo + Mitra Jalan, sama dengan navbar customer.
            ->brandName(new HtmlString('<img src="'.e(asset('images/logo.png')).'" alt="" class="brand-logo"> Mitra Jalan'))
            // Font sistem, sama dengan frontend (tidak mengunduh font apa pun).
            ->font('system-ui', provider: LocalFontProvider::class)
            // Palet putih kuning, sama dengan token di resources/css/app.css.
            ->colors([
                'primary' => Color::hex('#f5a623'),
                'danger' => Color::hex('#c0392b'),
                'success' => Color::hex('#1f7a52'),
                'warning' => Color::hex('#f5a623'),
            ])
            // CSS statis (bukan lewat Vite/Tailwind) agar tidak bentrok dengan Tailwind v3
            // yang dipakai frontend customer. ?v= memaksa browser memuat ulang saat file berubah.
            ->renderHook(PanelsRenderHook::HEAD_END, function (): string {
                $css = public_path('css/filament-admin-theme.css');
                $version = is_file($css) ? filemtime($css) : 0;

                return '<link rel="stylesheet" href="'.e(asset('css/filament-admin-theme.css')).'?v='.$version.'">';
            })
            ->navigationGroups(['Operasional', 'Owner'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // FilamentInfoWidget dihapus: isinya tautan dokumentasi dan logo Filament.
            ->widgets([])
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
                FilamentAuthenticate::class,
            ]);
    }
}
