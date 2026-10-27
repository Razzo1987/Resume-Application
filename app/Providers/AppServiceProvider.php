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
use App\Support\Cron\Cron;
use App\Support\Cron\CronJobs;
use App\Support\Install\InstallState;
use App\Support\Languages\LanguagePacks;
use App\Support\Languages\Locales;
use App\Support\Settings\Settings;
use App\Support\Stats\GeoIp;
use App\Support\Translatable\SlugRefresher;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Models whose saving changes the public pages (clears the HTML cache).
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
        $this->app->singleton(GeoIp::class);
        $this->app->singleton(Cron::class, function (): Cron {
            $cron = new Cron;
            CronJobs::register($cron);

            return $cron;
        });

        // State of the single public request
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
        // In development: explicit errors for lazy loading and missing attributes
        Model::shouldBeStrict(! $this->app->isProduction());

        // In production all generated addresses (canonical, sitemap, links) use the APP_URL domain
        if ($this->app->isProduction() && filled(config('app.url')) && $this->app->make(InstallState::class)->isInstalled()) {
            URL::forceRootUrl((string) config('app.url'));

            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        foreach (self::CONTENT_MODELS as $model) {
            Event::listen(["eloquent.saved: {$model}", "eloquent.deleted: {$model}"], fn () => $this->app->make(PageCache::class)->flush());
        }

        // Reordering admin tables updates the order with a single query (without model events)
        Table::configureUsing(fn (Table $table): Table => $table->afterReordering(fn () => $this->app->make(PageCache::class)->flush()));

        // Changed addresses → 301 redirect from the old one to the new one
        Event::listen(['eloquent.updated: '.Project::class, 'eloquent.updated: '.Experience::class, 'eloquent.updated: '.Article::class], function (Model $model): void {
            $this->app->make(UrlChangeRedirects::class)->slugsChanged($model);
        });

        Event::listen('eloquent.updated: '.Language::class, function (Language $language): void {
            $this->app->make(UrlChangeRedirects::class)->segmentsChanged($language);
        });

        // Newly activated language: slugs for all the dedicated pages
        Event::listen('eloquent.saved: '.Language::class, function (Language $language): void {
            if ($language->is_active && ($language->wasRecentlyCreated || $language->wasChanged('is_active'))) {
                $this->app->make(SlugRefresher::class)->refresh();
            }
        });
    }
}
