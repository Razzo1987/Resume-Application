<?php

use App\Models\Language;
use App\Models\Project;

beforeEach(function () {
    Language::query()->create(['code' => 'it-IT', 'name' => 'Italiano', 'is_default' => true]);
    Language::query()->create(['code' => 'en-GB', 'name' => 'English', 'sort_order' => 10]);
});

function makeProject(array $title, array $extra = []): Project
{
    return Project::query()->create(['title' => $title, 'start_date' => '2024-01-01'] + $extra);
}

it('returns the translation in the requested language', function () {
    $project = makeProject(['it-IT' => 'Migrazione', 'en-GB' => 'Migration']);

    expect($project->t('title', 'en-GB'))->toBe('Migration')
        ->and($project->t('title', 'it-IT'))->toBe('Migrazione');
});

it('falls back to the default language, then to any translation', function () {
    $project = makeProject(['it-IT' => 'Migrazione']);
    $onlyEnglish = makeProject(['en-GB' => 'Migration']);

    expect($project->t('title', 'en-GB'))->toBe('Migrazione')
        ->and($project->t('title', 'en-GB', fallback: false))->toBeNull()
        ->and($onlyEnglish->t('title', 'it-IT'))->toBe('Migration');
});

it('treats empty HTML as a missing translation', function () {
    $project = makeProject(['it-IT' => 'Titolo'], ['summary' => ['it-IT' => '<p>Testo</p>', 'en-GB' => '<p></p>']]);

    expect($project->hasTranslation('summary', 'en-GB'))->toBeFalse()
        ->and($project->missingTranslations('en-GB'))->toContain('title', 'summary')
        ->and($project->missingTranslations('it-IT'))->toBe([]);
});

it('finds records by a translated field with a hyphenated locale', function () {
    makeProject(['it-IT' => 'Migrazione Data Catalog', 'en-GB' => 'Data Catalog Migration']);

    expect(Project::query()->whereTranslation('slug', 'it-IT', 'migrazione-data-catalog')->count())->toBe(1)
        ->and(Project::query()->whereTranslation('slug', 'en-GB', 'data-catalog-migration')->count())->toBe(1);
});

it('generates unique slugs per language', function () {
    $first = makeProject(['it-IT' => 'Progetto', 'en-GB' => 'Project']);
    $second = makeProject(['it-IT' => 'Progetto', 'en-GB' => 'Project']);

    expect($first->t('slug', 'it-IT'))->toBe('progetto')
        ->and($second->t('slug', 'it-IT'))->toBe('progetto-2')
        ->and($second->t('slug', 'en-GB'))->toBe('project-2');
});

it('keeps a slug written by hand, normalised', function () {
    $project = makeProject(['it-IT' => 'Progetto'], ['slug' => ['it-IT' => 'Il Mio Progetto!']]);

    expect($project->t('slug', 'it-IT'))->toBe('il-mio-progetto');
});
