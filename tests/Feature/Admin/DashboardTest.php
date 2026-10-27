<?php

use App\Filament\Widgets\ContentOverviewWidget;
use App\Filament\Widgets\CvOverviewWidget;
use App\Filament\Widgets\LanguagesOverviewWidget;
use App\Filament\Widgets\StatisticsOverviewWidget;
use App\Filament\Widgets\SystemOverviewWidget;
use App\Models\StatVisit;
use App\Models\User;
use App\Support\Settings\Settings;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    seedDemo();
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');
    app()->setLocale('it');
});

it('groups the dashboard by type: system, languages, content, CV and statistics', function () {
    $this->get('/admin')->assertOk()->assertSeeInOrder([
        SystemOverviewWidget::class, LanguagesOverviewWidget::class, ContentOverviewWidget::class, CvOverviewWidget::class, StatisticsOverviewWidget::class,
    ], escape: false);

    Livewire::test(SystemOverviewWidget::class)->assertSee('Sistema')->assertSee('Non configurato')->assertSee('Ultima copia dei contenuti');
    Livewire::test(LanguagesOverviewWidget::class)->assertSee('Lingue del sito')->assertSee('Traduzioni it-IT');
    Livewire::test(ContentOverviewWidget::class)->assertSee('Esperienze di lavoro')->assertSee('1 con pagina dedicata')->assertSee('più 1 di volontariato')->assertDontSee('{0}')->assertSee('Articoli pubblicati');
    Livewire::test(CvOverviewWidget::class)->assertSee('CV Italiano')->assertSee('Caricato il');
    Livewire::test(StatisticsOverviewWidget::class)->assertSee('Spente')->assertSee('Attivale per vedere visite');
});

it('summarises the visits of the last days when statistics are on', function () {
    app(Settings::class)->set('stats.enabled', true);

    foreach (range(1, 3) as $i) {
        StatVisit::query()->create([
            'visited_at' => now(), 'day' => today(), 'page' => 'cv', 'path' => '/it-IT', 'locale' => 'it-IT',
            'referrer_host' => 'linkedin.com', 'country' => 'IT', 'ip' => '93.44.12.x',
        ]);
    }

    Livewire::test(StatisticsOverviewWidget::class)
        ->assertSee('Visualizzazioni (7 giorni)')
        ->assertSee('linkedin.com')
        ->assertSee('Paese principale: Italia')
        ->assertSee('Nessun dato nei 7 giorni prima');
});
