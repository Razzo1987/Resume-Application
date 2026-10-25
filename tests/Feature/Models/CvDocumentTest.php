<?php

use App\Models\CvDocument;
use App\Models\Language;
use App\Models\Profile;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(CvDocument::DISK);
    $this->italian = Language::query()->create(['code' => 'it-IT', 'name' => 'Italiano', 'is_default' => true]);
    Profile::current()->update(['full_name' => 'Luca Racchetti']);
});

function storeCv(Language $language, string $timestamp, bool $current = true): CvDocument
{
    $path = CvDocument::DIRECTORY.'/'.CvDocument::storageName($language->code, new DateTimeImmutable($timestamp));
    Storage::disk(CvDocument::DISK)->put($path, '%PDF-1.7 '.$timestamp);

    return CvDocument::query()->create([
        'language_id' => $language->id,
        'path' => $path,
        'original_name' => 'CV.pdf',
        'is_current' => $current,
    ]);
}

it('stores the file with a timestamped name and computes size and hash', function () {
    $document = storeCv($this->italian, '2026-10-25 14:30:12');

    expect($document->path)->toBe('cv/cv_it-IT_20261025-143012.pdf')
        ->and($document->size)->toBe(strlen('%PDF-1.7 2026-10-25 14:30:12'))
        ->and($document->sha256)->toHaveLength(64);
});

it('downloads with the standard name, without dates', function () {
    $document = storeCv($this->italian, '2026-10-25 14:30:12');

    expect($document->downloadName())->toBe('CV Luca Racchetti - IT.pdf');

    app(Settings::class)->set('cv.filename_pattern', '{name} CV ({locale})');

    expect($document->fresh()->downloadName())->toBe('Luca Racchetti CV (it-IT).pdf');
});

it('keeps a single current version per language', function () {
    $old = storeCv($this->italian, '2026-01-13 09:00:00');
    $new = storeCv($this->italian, '2026-10-25 14:30:12');
    $english = Language::query()->create(['code' => 'en-GB', 'name' => 'English']);
    $englishCv = storeCv($english, '2026-10-25 15:00:00');

    expect($old->fresh()->is_current)->toBeFalse()
        ->and($new->fresh()->is_current)->toBeTrue()
        ->and($englishCv->fresh()->is_current)->toBeTrue();
});

it('promotes the latest remaining version when the current one is deleted', function () {
    $old = storeCv($this->italian, '2026-01-13 09:00:00');
    $new = storeCv($this->italian, '2026-10-25 14:30:12');

    $new->delete();

    expect($old->fresh()->is_current)->toBeTrue();
    Storage::disk(CvDocument::DISK)->assertMissing($new->path);
});
