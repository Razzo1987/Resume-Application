<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Settings\Settings;
use App\Themes\ThemeManager;
use App\View\Composers\SiteViewComposer;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeManager::class, fn (): ThemeManager => new ThemeManager(
            themesPath: (string) config('resume.themes.path'),
            // Theme chosen in the admin (settings), otherwise the configured one
            activeName: (string) ($this->app->make(Settings::class)->get('site.theme') ?: config('resume.themes.active')),
            baseName: (string) config('resume.themes.base'),
        ));
    }

    public function boot(ThemeManager $themes): void
    {
        // view('theme::cv') looks in the active theme first, then in the themes it inherits from.
        View::addNamespace('theme', $themes->viewPaths());

        // The active theme's own strings: __('theme::file.key').
        Lang::addNamespace('theme', $themes->active()->langPath());

        View::composer('theme::*', function (\Illuminate\View\View $view) use ($themes): void {
            $view->with('theme', $themes->active());
        });

        // Shared data for the public pages (menu, languages, contacts, footer) and content tools
        View::composer('theme::*', SiteViewComposer::class);
    }
}
