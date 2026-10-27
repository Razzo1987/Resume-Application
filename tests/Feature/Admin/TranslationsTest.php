<?php

use App\Enums\PageSectionKey;
use App\Filament\Pages\Translations;
use App\Filament\Widgets\LanguagesOverviewWidget;
use App\Models\Interest;
use App\Models\PageSection;
use App\Models\User;
use App\Support\Content\TranslationReport;
use App\Support\Settings\Settings;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');
});

it('lists what is missing in each language, settings and section titles included', function () {
    $report = app(TranslationReport::class);
    expect($report->missingByLocale())->toBe(['it-IT' => 0, 'en-GB' => 0]);

    Interest::query()->create(['name' => ['it-IT' => 'Montagna'], 'description' => ['it-IT' => 'Escursioni']]);
    PageSection::query()->where('key', PageSectionKey::Interests)->update(['title' => json_encode(['it-IT' => 'Passioni'])]);
    app(Settings::class)->set('seo.title', ['it-IT' => 'Titolo']);

    $missing = $report->missing('en-GB');

    expect($report->missingByLocale())->toBe(['it-IT' => 0, 'en-GB' => 3])
        ->and(array_map(fn (array $item): array => $item['fields'], $missing))->toBe([['name', 'description'], ['title'], ['seo.title']])
        ->and($missing[2]['record'])->toBeNull();
});

it('shows the missing translations with links to edit them', function () {
    $interest = Interest::query()->create(['name' => ['it-IT' => 'Montagna'], 'description' => ['it-IT' => 'Escursioni']]);
    app(Settings::class)->set('seo.description', ['it-IT' => 'Descrizione']);

    $this->get(Translations::getUrl(['locale' => 'en-GB']))
        ->assertOk()
        ->assertSee('Montagna')
        ->assertSee('Nome')
        ->assertSee('Descrizione')
        ->assertSee('Testi del sito (SEO e footer)')
        ->assertSee('/admin/settings', escape: false);

    Livewire::test(Translations::class)
        ->assertSet('locale', 'en-GB')
        ->call('selectLocale', 'it-IT')
        ->assertSee('Tutto tradotto');
});

it('links each dashboard translation box to its list', function () {
    Interest::query()->create(['name' => ['it-IT' => 'Montagna']]);

    // I widget della dashboard si caricano a parte (lazy): si verifica il componente
    Livewire::test(LanguagesOverviewWidget::class)->assertSeeHtml(Translations::getUrl(['locale' => 'en-GB']));
});
