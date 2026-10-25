<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Certification;
use App\Models\CvDocument;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Highlight;
use App\Models\Interest;
use App\Models\Language;
use App\Models\LanguageSkill;
use App\Models\Organization;
use App\Models\PageSection;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Publication;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\SocialLink;
use App\Site\Blog;
use App\Site\Cache\PageCache;
use App\Site\CvData;
use App\Site\CvFiles;
use App\Site\LastUpdated;
use App\Site\LayoutFactory;
use App\Site\Pages;
use App\Site\Seo\StructuredData;
use App\Site\SiteContext;
use App\Site\SiteUrls;
use App\Site\UrlChangeRedirects;
use App\Support\Languages\LanguagePacks;
use App\Support\Languages\Locales;
use App\Support\Settings\Settings;
use App\Support\Translatable\SlugRefresher;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Modelli il cui salvataggio cambia le pagine pubbliche (svuota la cache HTML).
     *
     * @var list<class-string<Model>>
     */
    public const array CONTENT_MODELS = [
        Profile::class, SocialLink::class, Organization::class, Experience::class, Project::class,
        Skill::class, SkillCategory::class, Education::class, Certification::class, LanguageSkill::class,
        Publication::class, Interest::class, Highlight::class, PageSection::class, Article::class,
        CvDocument::class, Language::class,
    ];

    public function register(): void
    {
        $this->app->singleton(Locales::class);
        $this->app->singleton(LanguagePacks::class);
        $this->app->singleton(Settings::class);
        $this->app->singleton(PageCache::class);
        $this->app->singleton(SiteUrls::class);

        // Stato della singola richiesta pubblica
        $this->app->scoped(SiteContext::class);
        $this->app->scoped(CvData::class);
        $this->app->scoped(CvFiles::class);
        $this->app->scoped(LastUpdated::class);
        $this->app->scoped(StructuredData::class);
        $this->app->scoped(Pages::class);
        $this->app->scoped(LayoutFactory::class);
        $this->app->scoped(Blog::class);
    }

    public function boot(): void
    {
        // In sviluppo: errori espliciti per lazy loading e attributi inesistenti
        Model::shouldBeStrict(! $this->app->isProduction());

        // In produzione tutti gli indirizzi generati (canonical, sitemap, link) usano il dominio di APP_URL
        if ($this->app->isProduction() && filled(config('app.url'))) {
            URL::forceRootUrl((string) config('app.url'));

            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        foreach (self::CONTENT_MODELS as $model) {
            Event::listen(["eloquent.saved: {$model}", "eloquent.deleted: {$model}"], fn () => $this->app->make(PageCache::class)->flush());
        }

        // Il riordino delle tabelle admin aggiorna l'ordine con una query unica (senza eventi dei modelli)
        Table::configureUsing(fn (Table $table): Table => $table->afterReordering(fn () => $this->app->make(PageCache::class)->flush()));

        // Indirizzi cambiati → redirect 301 dal vecchio al nuovo
        Event::listen(['eloquent.updated: '.Project::class, 'eloquent.updated: '.Experience::class, 'eloquent.updated: '.Article::class], function (Model $model): void {
            $this->app->make(UrlChangeRedirects::class)->slugsChanged($model);
        });

        Event::listen('eloquent.updated: '.Language::class, function (Language $language): void {
            $this->app->make(UrlChangeRedirects::class)->segmentsChanged($language);
        });

        // Lingua appena attivata: slug per tutte le pagine dedicate
        Event::listen('eloquent.saved: '.Language::class, function (Language $language): void {
            if ($language->is_active && ($language->wasRecentlyCreated || $language->wasChanged('is_active'))) {
                $this->app->make(SlugRefresher::class)->refresh();
            }
        });
    }
}
