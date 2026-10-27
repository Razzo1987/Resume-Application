<?php

use App\Filament\Pages\ManageSettings;
use App\Filament\Pages\Statistics;
use App\Models\Article;
use App\Models\StatVisit;
use App\Models\User;
use App\Support\Settings\Settings;
use App\Support\Stats\StatsQuery;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// Data is stored in UTC; days and times are shown (and statistics are counted) in the time zone of the site

beforeEach(fn () => seedDemo());

it('guesses the time zone from the default language until one is chosen', function () {
    $settings = app(Settings::class);

    expect($settings->timezone())->toBe('Europe/Rome');

    $settings->set('site.timezone', 'America/New_York');
    expect($settings->timezone())->toBe('America/New_York');

    $settings->set('site.timezone', 'Mars/Olympus');
    expect($settings->timezone())->toBe('Europe/Rome');
});

it('counts a visit in the local day of the site', function () {
    app(Settings::class)->set('stats.enabled', true);
    $this->travelTo(Carbon::parse('2026-10-27 23:30:00', 'UTC'));

    $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36'])->get('/it-IT');

    $visit = StatVisit::query()->sole();

    expect($visit->day->toDateString())->toBe('2026-10-28')
        ->and($visit->visited_at->toDateTimeString())->toBe('2026-10-27 23:30:00')
        ->and(StatsQuery::today()->toDateString())->toBe('2026-10-28');

    // In the admin the time is shown in the time zone of the site
    $this->actingAs(User::factory()->create());
    $this->get('/admin/statistics')->assertOk()->assertSee('28/10/2026 00:30');
});

it('shows the publication day of an article in the time zone of the site', function () {
    Article::query()->firstOrFail()->update(['published_at' => Carbon::parse('2026-01-14 23:30:00', 'UTC')]);

    $this->get('/it-IT/blog')->assertSee('15 gennaio 2026');

    app(Settings::class)->set('site.timezone', 'UTC');

    $this->get('/it-IT/blog')->assertSee('14 gennaio 2026');
});

it('lets the administrator choose the time zone in the settings', function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageSettings::class)
        ->assertSet('data.site.timezone', 'Europe/Rome')
        ->set('data.site.timezone', 'Europe/London')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(Settings::class)->timezone())->toBe('Europe/London');
});
