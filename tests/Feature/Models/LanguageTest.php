<?php

use App\Models\Language;
use App\Support\Languages\Locales;

it('keeps a single default language', function () {
    $italian = Language::query()->create(['code' => 'it-IT', 'name' => 'Italiano', 'is_default' => true]);
    $english = Language::query()->create(['code' => 'en-GB', 'name' => 'English', 'is_default' => true]);

    expect($italian->fresh()->is_default)->toBeFalse()
        ->and($english->fresh()->is_default)->toBeTrue()
        ->and(app(Locales::class)->default())->toBe('en-GB');
});

it('always keeps the default language active', function () {
    $italian = Language::query()->create(['code' => 'it-IT', 'name' => 'Italiano', 'is_default' => true, 'is_active' => false]);

    expect($italian->fresh()->is_active)->toBeTrue();
});

it('refuses to remove the default flag or delete the default language', function () {
    $italian = Language::query()->create(['code' => 'it-IT', 'name' => 'Italiano', 'is_default' => true]);

    expect(fn () => $italian->update(['is_default' => false]))->toThrow(LogicException::class)
        ->and(fn () => $italian->delete())->toThrow(LogicException::class);
});

it('lists the active languages with the default one first', function () {
    Language::query()->create(['code' => 'en-GB', 'name' => 'English', 'sort_order' => 1]);
    Language::query()->create(['code' => 'it-IT', 'name' => 'Italiano', 'is_default' => true, 'sort_order' => 2]);
    Language::query()->create(['code' => 'de-DE', 'name' => 'Deutsch', 'is_active' => false]);

    expect(app(Locales::class)->active())->toBe(['it-IT', 'en-GB']);
});

it('resolves URL segments: admin override, then language pack, then English', function () {
    $italian = Language::query()->create(['code' => 'it-IT', 'name' => 'Italiano', 'url_segments' => ['projects' => 'lavori']]);
    $romanian = Language::query()->create(['code' => 'ro-RO', 'name' => 'Română']);

    expect($italian->segment('projects'))->toBe('lavori')
        ->and($italian->segment('experience'))->toBe('esperienze')
        ->and($romanian->segment('projects'))->toBe('projects');
});
