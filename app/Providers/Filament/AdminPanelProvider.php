<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Support\InitialsAvatarProvider;
use App\Filament\Support\SiteActions;
use App\Filament\Widgets\ContentOverviewWidget;
use App\Filament\Widgets\CvOverviewWidget;
use App\Filament\Widgets\LanguagesOverviewWidget;
use App\Filament\Widgets\StatisticsOverviewWidget;
use App\Filament\Widgets\SystemAlertsWidget;
use App\Filament\Widgets\SystemOverviewWidget;
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
            // Configurable path (RESUME_ADMIN_PATH), "admin" by default
            ->path((string) config('resume.admin_path'))
            ->brandName(fn (): string => (string) config('app.name'))
            ->login()
            ->profile(EditProfile::class)
            ->passwordReset()
            // Two-factor authentication with an app (Google Authenticator, 1Password, ...), with recovery codes
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ])
            ->colors([
                'primary' => Color::Slate,
            ])
            // Avatar generated locally: the default provider uses ui-avatars.com
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            // No CDN fonts: Filament uses its own local font (LocalFontProvider) unless another one is set
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            // Dashboard: alerts, then one group per type (System, Languages, Content, CV, Statistics)
            ->widgets([
                AccountWidget::class,
                SystemAlertsWidget::class,
                SystemOverviewWidget::class,
                LanguagesOverviewWidget::class,
                ContentOverviewWidget::class,
                CvOverviewWidget::class,
                StatisticsOverviewWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make(fn (): string => __('admin.nav.cv')),
                NavigationGroup::make(fn (): string => __('admin.nav.content')),
                NavigationGroup::make(fn (): string => __('admin.nav.site')),
            ])
            ->sidebarCollapsibleOnDesktop()
            // Chunked upload of the update package (System page)
            ->authenticatedRoutes(function (): void {
                Route::post('system/update-upload', UpdateUploadController::class)->name('update-upload');
            })
            // "Go to site" in the top bar, before the user menu
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
