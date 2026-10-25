<?php

use App\Enums\HighlightDisplay;
use App\Enums\HighlightSource;
use App\Models\Highlight;
use App\Models\Language;
use App\Models\Profile;
use App\Models\Project;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Language::query()->create(['code' => 'it-IT', 'name' => 'Italiano', 'is_default' => true]);
    Language::query()->create(['code' => 'en-GB', 'name' => 'English']);
});

it('formats manual values for each language, exact or with plus', function () {
    $exact = Highlight::query()->create(['value' => 100000, 'display' => HighlightDisplay::Exact, 'label' => ['it-IT' => 'asset catalogati']]);
    $plus = Highlight::query()->create(['value' => 460, 'display' => HighlightDisplay::Plus]);
    $percent = Highlight::query()->create(['value' => 98.5, 'unit' => '%']);

    expect($exact->formattedValue('it-IT'))->toBe('100.000')
        ->and($exact->formattedValue('en-GB'))->toBe('100,000')
        ->and($plus->formattedValue('it-IT'))->toBe('460+')
        ->and($percent->formattedValue('it-IT'))->toBe('98,5%')
        ->and($exact->displayLabel('en-GB'))->toBe('asset catalogati');
});

it('computes years of experience from the career start', function () {
    Carbon::setTestNow('2026-10-25');
    Profile::current()->update(['career_start_date' => '2015-01-01']);

    $years = Highlight::query()->create(['source' => HighlightSource::YearsExperience]);

    expect($years->formattedValue('it-IT'))->toBe('11')
        ->and($years->displayLabel('it-IT'))->toBe('anni di esperienza')
        ->and($years->displayLabel('en-GB'))->toBe('years of experience');
});

it('counts only visible projects', function () {
    Project::query()->create(['title' => ['it-IT' => 'A'], 'start_date' => '2024-01-01']);
    Project::query()->create(['title' => ['it-IT' => 'B'], 'start_date' => '2024-01-01']);
    Project::query()->create(['title' => ['it-IT' => 'C'], 'start_date' => '2024-01-01', 'is_visible' => false]);

    $count = Highlight::query()->create(['source' => HighlightSource::ProjectsCount, 'display' => HighlightDisplay::Plus]);

    expect($count->formattedValue('it-IT'))->toBe('2+');
});
