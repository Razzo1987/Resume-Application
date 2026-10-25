<?php

use App\Models\CvDocument;
use App\Models\Language;
use App\Support\Settings\Settings;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => seedDemo());

it('downloads the current CV of the language with the standard file name', function () {
    $response = $this->get('/it-IT/cv');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Robots-Tag', 'noindex');

    expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('CV John Doe - IT.pdf')
        ->and($response->headers->get('ETag'))->toBe('"'.CvDocument::query()->whereRelation('language', 'code', 'it-IT')->value('sha256').'"');
});

it('uses the configured file name pattern and the translated segment', function () {
    app(Settings::class)->set('cv.filename_pattern', 'Curriculum {name} ({locale})');
    Language::query()->where('code', 'en-GB')->sole()->update(['url_segments' => ['cv' => 'resume']]);

    $response = $this->get('/en-GB/resume');

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('Curriculum John Doe (en-GB).pdf');

    $this->get('/en-GB/cv')->assertRedirect('http://localhost/en-GB/resume');
});

it('serves the newest current version after a new upload', function () {
    $italian = Language::query()->where('code', 'it-IT')->sole();
    $path = CvDocument::DIRECTORY.'/'.CvDocument::storageName('it-IT', now()->addMinute());
    Storage::disk(CvDocument::DISK)->put($path, DemoSeeder::samplePdf('Nuova versione'));

    $new = CvDocument::query()->create(['language_id' => $italian->id, 'path' => $path, 'original_name' => 'cv-nuovo.pdf', 'is_current' => true]);

    expect($this->get('/it-IT/cv')->headers->get('ETag'))->toBe('"'.$new->sha256.'"');
});

it('answers 304 when the browser already has the same file', function () {
    $etag = $this->get('/it-IT/cv')->headers->get('ETag');

    $this->get('/it-IT/cv', ['If-None-Match' => $etag])->assertStatus(304);
});

it('falls back to the default language CV only when enabled', function () {
    DB::table('cv_documents')->where('language_id', Language::query()->where('code', 'en-GB')->value('id'))->delete();

    $this->get('/en-GB/cv')->assertNotFound();

    app(Settings::class)->set('cv.fallback_to_default', true);

    $response = $this->get('/en-GB/cv');

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('CV John Doe - IT.pdf');
});

it('returns 404 when the file is missing from the disk', function () {
    Storage::disk(CvDocument::DISK)->deleteDirectory(CvDocument::DIRECTORY);

    $this->get('/it-IT/cv')->assertNotFound();
});
