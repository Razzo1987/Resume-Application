<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Settings\Settings;
use App\Themes\ThemeManager;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeManager::class, fn (): ThemeManager => new ThemeManager(
            themesPath: (string) config('resume.themes.path'),
            // Tema scelto in admin (impostazioni), altrimenti quello di configurazione
            activeName: (string) ($this->app->make(Settings::class)->get('site.theme') ?: config('resume.themes.active')),
            baseName: (string) config('resume.themes.base'),
        ));
    }

    public function boot(ThemeManager $themes): void
    {
        // view('theme::cv') cerca prima nel tema attivo, poi nei temi da cui eredita.
        View::addNamespace('theme', $themes->viewPaths());

        // Stringhe proprie del tema attivo: __('theme::file.chiave').
        Lang::addNamespace('theme', $themes->active()->langPath());

        View::composer('theme::*', function (\Illuminate\View\View $view) use ($themes): void {
            $view->with('theme', $themes->active());
        });
    }
}
