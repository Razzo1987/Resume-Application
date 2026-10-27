<?php

namespace App\Providers\Filament;

use App\Filament\Support\InitialsAvatarProvider;
use App\Filament\Support\SiteActions;
use App\Filament\Widgets\SiteStatusWidget;
use App\Filament\Widgets\SystemAlertsWidget;
use App\Http\Controllers\Admin\UpdateUploadController;
use App\Http\Middleware\AdminActivity;
use App\Http\Middleware\NoIndex;
use App\Http\Middleware\SetAdminLocale;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            // Percorso configurabile (RESUME_ADMIN_PATH), "admin" di default
            ->path((string) config('resume.admin_path'))
            ->brandName(fn (): string => (string) config('app.name'))
            ->login()
            ->profile()
            ->passwordReset()
            // Autenticazione a due fattori con app (Google Authenticator, 1Password, ...), con codici di recupero
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ])
            ->colors([
                'primary' => Color::Slate,
            ])
            // Avatar generato in locale: il provider di default usa ui-avatars.com
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            // Nessun font da CDN: Filament usa il proprio font locale (LocalFontProvider) se non se ne imposta un altro
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                SystemAlertsWidget::class,
                SiteStatusWidget::class,
                AccountWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make(fn (): string => __('admin.nav.cv')),
                NavigationGroup::make(fn (): string => __('admin.nav.content')),
                NavigationGroup::make(fn (): string => __('admin.nav.site')),
            ])
            ->sidebarCollapsibleOnDesktop()
            // Caricamento a blocchi del pacchetto di aggiornamento (pagina Sistema)
            ->authenticatedRoutes(function (): void {
                Route::post('system/update-upload', UpdateUploadController::class)->name('update-upload');
            })
            // "Vai al sito" nella barra in alto, prima del menu utente
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn (): View => view('filament.site-link', ['url' => SiteActions::homeUrl()]))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                SetAdminLocale::class,
                NoIndex::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                AdminActivity::class,
            ]);
    }
}
