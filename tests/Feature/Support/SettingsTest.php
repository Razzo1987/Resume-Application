<?php

use App\Models\Language;
use App\Support\Settings\Settings;

it('returns defaults until a value is saved', function () {
    $settings = app(Settings::class);

    expect($settings->bool('footer.show_last_updated'))->toBeFalse()
        ->and($settings->bool('print.enabled'))->toBeTrue()
        ->and($settings->bool('jsonld.include_email'))->toBeFalse();

    $settings->set('footer.show_last_updated', true);

    expect(app(Settings::class)->bool('footer.show_last_updated'))->toBeTrue();
});

it('translates texts with fallback on the default language', function () {
    Language::query()->create(['code' => 'it-IT', 'name' => 'Italiano', 'is_default' => true]);
    Language::query()->create(['code' => 'en-GB', 'name' => 'English']);

    $settings = app(Settings::class);
    $settings->set('footer.gdpr_text', ['it-IT' => 'Autorizzo il trattamento dei dati.']);

    expect($settings->translated('footer.gdpr_text', 'en-GB'))->toBe('Autorizzo il trattamento dei dati.');
});
