<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use App\Support\Languages\AdminLocales;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('offers the admin languages that have a translation file', function () {
    expect(app(AdminLocales::class)->available())->toBe(['en' => 'English', 'it' => 'Italiano'])
        ->and(app(AdminLocales::class)->default())->toBe('it');
});

it('shows the admin in the language chosen by each administrator, with the default for the others', function () {
    $english = User::factory()->create(['locale' => 'en'])->fresh();
    $italian = User::factory()->create()->fresh();

    $this->actingAs($english)->get('/admin/settings')->assertOk()->assertSee('Settings')->assertDontSee('Impostazioni');
    $this->actingAs($italian)->get('/admin/settings')->assertOk()->assertSee('Impostazioni');

    // Language no longer available: falls back to the default one
    $english->update(['locale' => 'xx']);
    $this->actingAs($english)->get('/admin/settings')->assertOk()->assertSee('Impostazioni');
});

it('lets administrators choose their language in the profile', function () {
    $user = User::factory()->create()->fresh();
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');

    Livewire::test(EditProfile::class)
        ->fillForm(['locale' => 'en'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect(EditProfile::getUrl());

    expect($user->fresh()->locale)->toBe('en');

    $this->get('/admin')->assertSee('lang="en"', escape: false);
});

it('opens the site from the top bar in the language of the administrator', function () {
    seedDemo();
    config(['resume.admin_locale' => 'en']);

    $italian = User::factory()->create(['locale' => 'it'])->fresh();
    $english = User::factory()->create(['locale' => 'en'])->fresh();

    $this->actingAs($italian)->get('/admin')->assertOk()->assertSee('href="'.url('/it-IT').'"', escape: false);
    $this->actingAs($english)->get('/admin')->assertOk()->assertSee('href="'.url('/en-GB').'"', escape: false);
});
