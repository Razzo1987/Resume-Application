<?php

use App\Enums\ExperienceKind;
use App\Filament\Pages\ManageProfile;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\CvDocuments\Pages\CreateCvDocument;
use App\Filament\Resources\Experiences\Pages\CreateExperience;
use App\Filament\Resources\Experiences\Pages\ListExperiences;
use App\Filament\Resources\Languages\Pages\CreateLanguage;
use App\Filament\Resources\Languages\Pages\EditLanguage;
use App\Filament\Resources\PageSections\Pages\ManagePageSections;
use App\Filament\Resources\Volunteering\Pages\CreateVolunteering;
use App\Filament\Resources\Volunteering\Pages\ListVolunteering;
use App\Models\CvDocument;
use App\Models\Experience;
use App\Models\Language;
use App\Models\Organization;
use App\Models\PageSection;
use App\Models\Profile;
use App\Models\User;
use App\Site\Cache\PageCache;
use App\Support\Settings\Settings;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');
});

it('creates an experience with its translations and per-language slugs', function () {
    $organization = Organization::query()->create(['name' => 'Acme S.p.A.']);

    Livewire::test(CreateExperience::class)
        ->fillForm([
            'organization_id' => $organization->id,
            'start_date' => '2020-03-01',
            'role' => ['it-IT' => 'Sviluppatore', 'en-GB' => 'Developer'],
            'summary' => ['it-IT' => '<p>Riassunto</p>'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $experience = Experience::query()->sole();

    expect($experience->kind)->toBe(ExperienceKind::Work)
        ->and($experience->t('role', 'en-GB'))->toBe('Developer')
        ->and($experience->slug)->toEqual(['it-IT' => 'sviluppatore-acme-spa', 'en-GB' => 'developer-acme-spa']);
});

it('requires the text in the default language only', function () {
    Livewire::test(CreateExperience::class)
        ->fillForm([
            'start_date' => '2020-03-01',
            'role' => ['en-GB' => 'Developer'],
        ])
        ->call('create')
        ->assertHasFormErrors(['role.it-IT' => 'required']);
});

it('rejects an end date before the start date', function () {
    Livewire::test(CreateExperience::class)
        ->fillForm([
            'start_date' => '2020-03-01',
            'end_date' => '2019-01-01',
            'role' => ['it-IT' => 'Sviluppatore'],
        ])
        ->call('create')
        ->assertHasFormErrors(['end_date']);
});

it('keeps work experience and volunteering in separate lists', function () {
    $work = Experience::query()->create(['kind' => ExperienceKind::Work, 'role' => ['it-IT' => 'Sviluppatore'], 'start_date' => '2020-01-01']);
    $volunteering = Experience::query()->create(['kind' => ExperienceKind::Volunteering, 'role' => ['it-IT' => 'Volontario'], 'start_date' => '2021-01-01']);

    Livewire::test(ListExperiences::class)
        ->assertCanSeeTableRecords([$work])
        ->assertCanNotSeeTableRecords([$volunteering]);

    Livewire::test(ListVolunteering::class)
        ->assertCanSeeTableRecords([$volunteering])
        ->assertCanNotSeeTableRecords([$work]);
});

it('creates volunteering entries from the volunteering section', function () {
    Livewire::test(CreateVolunteering::class)
        ->fillForm([
            'start_date' => '2022-09-01',
            'role' => ['it-IT' => 'Docente volontario'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Experience::query()->sole()->kind)->toBe(ExperienceKind::Volunteering);
});

it('stores an uploaded CV with a timestamped name and keeps the original name', function () {
    Storage::fake(CvDocument::DISK);
    $italian = Language::query()->where('code', 'it-IT')->sole();

    Livewire::test(CreateCvDocument::class)
        ->fillForm([
            'language_id' => $italian->id,
            'path' => UploadedFile::fake()->createWithContent('Il mio CV.pdf', DemoSeeder::samplePdf('CV')),
            'is_current' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $cv = CvDocument::query()->sole();

    expect($cv->path)->toMatch('#^cv/cv_it-IT_\d{8}-\d{6}\.pdf$#')
        ->and($cv->original_name)->toBe('Il mio CV.pdf')
        ->and($cv->is_current)->toBeTrue()
        ->and($cv->size)->toBe(strlen(DemoSeeder::samplePdf('CV')))
        ->and($cv->sha256)->toBe(hash('sha256', DemoSeeder::samplePdf('CV')));

    Storage::disk(CvDocument::DISK)->assertExists($cv->path);
});

it('accepts only PDF files as CV', function () {
    Storage::fake(CvDocument::DISK);
    $italian = Language::query()->where('code', 'it-IT')->sole();

    Livewire::test(CreateCvDocument::class)
        ->fillForm([
            'language_id' => $italian->id,
            'path' => UploadedFile::fake()->create('cv.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->call('create')
        ->assertHasFormErrors(['path']);

    expect(CvDocument::query()->count())->toBe(0);
});

it('saves the settings, dropping empty translations', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm([
            'cv' => ['filename_pattern' => 'Curriculum {name} ({lang}).pdf'],
            'footer' => ['show_last_updated' => true],
            'jsonld' => ['include_email' => true],
            'seo' => ['title' => ['it-IT' => 'Il mio CV', 'en-GB' => '']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(Settings::class);

    expect($settings->get('cv.filename_pattern'))->toBe('Curriculum {name} ({lang}).pdf')
        ->and($settings->bool('footer.show_last_updated'))->toBeTrue()
        ->and($settings->bool('jsonld.include_email'))->toBeTrue()
        ->and($settings->bool('jsonld.include_phone'))->toBeFalse()
        ->and($settings->get('seo.title'))->toBe(['it-IT' => 'Il mio CV'])
        ->and($settings->translated('seo.title', 'en-GB'))->toBe('Il mio CV');
});

it('saves the profile data', function () {
    Livewire::test(ManageProfile::class)
        ->fillForm([
            'full_name' => 'Luca Racchetti',
            'email' => 'luca@example.com',
            'career_start_date' => '2012-09-01',
            'headlines' => ['it-IT' => "Consulente\nSviluppatore", 'en-GB' => "Consultant\nDeveloper"],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $profile = Profile::current();

    expect($profile->full_name)->toBe('Luca Racchetti')
        ->and($profile->headlineList('en-GB'))->toBe(['Consultant', 'Developer']);
});

it('adds a language, taking the name from the code', function () {
    Livewire::test(CreateLanguage::class)
        ->fillForm(['code' => 'de-DE'])
        ->assertFormSet(['name' => 'Deutsch'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Language::query()->where('code', 'de-DE')->sole()->segment('projects'))->toBe('projekte');
});

it('rejects malformed language codes', function (string $code) {
    Livewire::test(CreateLanguage::class)
        ->fillForm(['code' => $code, 'name' => 'Test'])
        ->call('create')
        ->assertHasFormErrors(['code']);
})->with(['it', 'it_IT', 'IT-it', 'it-IT-x']);

it('makes another language the default one', function () {
    $english = Language::query()->where('code', 'en-GB')->sole();

    Livewire::test(EditLanguage::class, ['record' => $english->getRouteKey()])
        ->assertSeeText(__('admin.help.default_language_cache'))
        ->fillForm(['is_default' => true])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('admin.messages.default_language_changed', ['language' => 'English']));

    expect(Language::query()->where('is_default', true)->pluck('code')->all())->toBe(['en-GB']);
});

it('does not offer to delete the default language', function () {
    $italian = Language::query()->where('code', 'it-IT')->sole();
    $english = Language::query()->where('code', 'en-GB')->sole();

    Livewire::test(EditLanguage::class, ['record' => $italian->getRouteKey()])
        ->assertActionHidden(DeleteAction::class);

    Livewire::test(EditLanguage::class, ['record' => $english->getRouteKey()])
        ->assertActionVisible(DeleteAction::class);
});

it('refreshes the public pages after reordering the CV sections', function () {
    $this->get('/it-IT');
    expect(app(PageCache::class)->get(app(PageCache::class)->key('http://localhost/it-IT')))->not->toBeNull();

    $order = PageSection::query()->orderByDesc('sort_order')->pluck('id')->map(fn ($id) => (string) $id)->all();

    Livewire::test(ManagePageSections::class)
        ->call('reorderTable', $order);

    expect(app(PageCache::class)->get(app(PageCache::class)->key('http://localhost/it-IT')))->toBeNull();
});
