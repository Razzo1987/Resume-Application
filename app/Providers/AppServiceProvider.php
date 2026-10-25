<?php

namespace App\Providers;

use App\Support\Languages\LanguagePacks;
use App\Support\Languages\Locales;
use App\Support\Settings\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Locales::class);
        $this->app->singleton(LanguagePacks::class);
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        // In sviluppo: errori espliciti per lazy loading e attributi inesistenti
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
