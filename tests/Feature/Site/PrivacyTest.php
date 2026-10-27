<?php

use App\Filament\Pages\ManageSettings;
use App\Models\Profile;
use App\Models\User;
use App\Site\Contact\Cloak;
use App\Support\Settings\Settings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(fn () => seedDemo());

it('has no privacy page until it is turned on', function () {
    app(Settings::class)->set('privacy.page', false);

    $this->get('/it-IT/privacy')->assertNotFound();
    $this->get('/it-IT')->assertOk()->assertDontSee('/it-IT/privacy');
});

it('shows the template notice with the name and the encoded email, linked from the footer', function () {
    app(Settings::class)->set('privacy.page', true);
    $profile = Profile::query()->firstOrFail();

    $html = (string) $this->get('/it-IT/privacy')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', escape: false)
        ->assertSee('Niente cookie')
        ->assertSee('curriculum online di '.$profile->full_name)
        ->assertDontSee('Statistiche delle visite')
        ->getContent();

    expect($html)->not->toContain((string) $profile->email)
        ->and($html)->toContain(app(Cloak::class)->encode('mailto:'.$profile->email))
        ->and($html)->not->toContain('{email}');

    $this->get('/it-IT')->assertSee('href="'.url('/it-IT/privacy').'"', escape: false);
    $this->get('/en-GB/privacy')->assertOk()->assertSee('No cookies');
});

it('uses the text written in the admin, sanitized', function () {
    app(Settings::class)->setMany([
        'privacy.page' => true,
        'privacy.text' => ['it-IT' => '<h2>Il mio testo</h2><p>Scrivi a {email}, firmato {name}.</p><script>alert(1)</script>'],
    ]);

    $this->get('/it-IT/privacy')
        ->assertOk()
        ->assertSee('Il mio testo')
        ->assertSee('firmato '.Profile::query()->firstOrFail()->full_name)
        ->assertDontSee('<script>alert(1)</script>', escape: false)
        ->assertDontSee('Niente cookie');

    // Le lingue senza testo usano il modello
    $this->get('/en-GB/privacy')->assertSee('No cookies');
});

it('is always on with visit statistics, and describes them from the settings', function () {
    app(Settings::class)->setMany(['privacy.page' => false, 'stats.enabled' => true, 'stats.retention_months' => 6]);

    $this->get('/it-IT/privacy')
        ->assertOk()
        ->assertSee('Statistiche delle visite')
        ->assertSee('93.44.12.x')
        ->assertSee('dopo 6 mesi')
        // Senza database GeoLite niente provenienza dall'IP
        ->assertDontSee('GeoLite2');
});

it('lets the administrator turn the page on and start from the template', function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageSettings::class)
        ->assertSee(__('admin.sections.privacy_policy'))
        ->set('data.privacy.page', true)
        ->call('save')
        ->assertNotified();

    expect(app(Settings::class)->bool('privacy.page'))->toBeTrue();

    Livewire::test(ManageSettings::class)
        ->callAction(TestAction::make('privacyTemplate_it-IT')->schemaComponent('privacy-texts-it-IT.privacy.text.it-IT', 'form'))
        ->assertSet('data.privacy.text.it-IT', fn ($value) => str_contains((string) json_encode($value), 'Niente cookie'));
});
