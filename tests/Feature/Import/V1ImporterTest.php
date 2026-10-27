<?php

use App\Enums\CertificationType;
use App\Enums\ExperienceKind;
use App\Enums\OrganizationType;
use App\Models\Certification;
use App\Models\CvDocument;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\Project;
use App\Models\SocialLink;
use App\Support\Settings\Settings;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/*
| v1 has one row per language (odd id = it-IT, even = en-GB). Here the v1 database is an in-memory SQLite
| database with the same tables; the files (images, PDFs, languages) are in a temporary folder.
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('uploads');
    Storage::fake('local');

    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    $schema = Schema::connection('legacy');

    $schema->create('profile', function (Blueprint $table) {
        foreach (['language', 'name', 'images', 'address', 'birthdate', 'phone', 'email', 'designation', 'socials', 'first_work_experience', 'information'] as $column) {
            $table->text($column)->nullable();
        }
    });
    $schema->create('experiences', function (Blueprint $table) {
        $table->integer('id');
        foreach (['language', 'company_name', 'job_title', 'start', 'end', 'location', 'images', 'link', 'description', 'details'] as $column) {
            $table->text($column)->nullable();
        }
    });
    $schema->create('educations', function (Blueprint $table) {
        foreach (['language', 'institution_name', 'degree_type', 'field_of_study', 'start', 'end', 'grade_value', 'grade_laude', 'grade_scale', 'images', 'link', 'description', 'details'] as $column) {
            $table->text($column)->nullable();
        }
    });
    $schema->create('projects', function (Blueprint $table) {
        $table->integer('id');
        foreach (['language', 'title', 'role', 'start', 'end', 'company', 'sector', 'description', 'details'] as $column) {
            $table->text($column)->nullable();
        }
    });
    $schema->create('certifications', function (Blueprint $table) {
        $table->integer('id');
        foreach (['language', 'date', 'title', 'type', 'institution', 'images', 'link'] as $column) {
            $table->text($column)->nullable();
        }
    });
    $schema->create('skills', function (Blueprint $table) {
        $table->integer('id');
        foreach (['language', 'name', 'type'] as $column) {
            $table->text($column)->nullable();
        }
    });
    $schema->create('experience_skill', function (Blueprint $table) {
        $table->integer('id_experience');
        $table->integer('id_skill');
        $table->text('language');
    });

    $legacy = DB::connection('legacy');
    $images = json_encode(['profile' => 'img/avatars/me.png', 'profile_alt' => 'Mario Rossi']);
    $socials = json_encode(['linkedin' => 'https://www.linkedin.com/in/mario/', 'github' => 'https://github.com/mario']);

    $legacy->table('profile')->insert([
        ['language' => 'it-IT', 'name' => 'Mario Rossi', 'images' => $images, 'address' => 'Milano, MI', 'birthdate' => '1980-01-02', 'phone' => '+39 000', 'email' => 'mario@example.com', 'designation' => 'Data Governance, Data Quality', 'socials' => $socials, 'first_work_experience' => '2010-01-01', 'information' => '<p>Ciao</p>'],
        ['language' => 'en-GB', 'name' => 'Mario Rossi', 'images' => $images, 'address' => 'Milan, Italy', 'birthdate' => '1980-01-02', 'phone' => '+39 000', 'email' => 'mario@example.com', 'designation' => 'Data Governance, Data Quality', 'socials' => $socials, 'first_work_experience' => '2010-01-01', 'information' => '<p>Hello</p>'],
    ]);

    $acme = json_encode(['icon' => 'img/acme.png', 'icon_alt' => 'Acme']);
    $legacy->table('experiences')->insert([
        ['id' => 1, 'language' => 'it-IT', 'company_name' => 'Acme', 'job_title' => 'Consulente', 'start' => '2015-01-01', 'end' => '2019-12-31', 'location' => 'Via Roma 1, 20100 Milano', 'images' => $acme, 'link' => 'https://acme.example', 'description' => '<p>IT</p>', 'details' => null],
        ['id' => 2, 'language' => 'en-GB', 'company_name' => 'Acme', 'job_title' => 'Consultant', 'start' => '2015-01-01', 'end' => '2019-12-31', 'location' => 'Via Roma 1, 20100 Milano', 'images' => $acme, 'link' => 'https://acme.example/en', 'description' => '<p>EN</p>', 'details' => null],
        ['id' => 3, 'language' => 'it-IT', 'company_name' => 'Beta', 'job_title' => 'Manager', 'start' => '2020-01-01', 'end' => null, 'location' => 'Roma', 'images' => null, 'link' => null, 'description' => null, 'details' => '<p>Dettagli</p>'],
        ['id' => 4, 'language' => 'en-GB', 'company_name' => 'Beta', 'job_title' => 'Manager', 'start' => '2020-01-01', 'end' => null, 'location' => 'Rome', 'images' => null, 'link' => null, 'description' => null, 'details' => '<p>Details</p>'],
        ['id' => 5, 'language' => 'it-IT', 'company_name' => 'Associazione Esempio', 'job_title' => 'Volontario', 'start' => '2012-01-01', 'end' => '2014-12-31', 'location' => null, 'images' => null, 'link' => null, 'description' => null, 'details' => null],
        ['id' => 6, 'language' => 'en-GB', 'company_name' => 'Associazione Esempio', 'job_title' => 'Volunteer', 'start' => '2012-01-01', 'end' => '2014-12-31', 'location' => null, 'images' => null, 'link' => null, 'description' => null, 'details' => null],
    ]);

    $legacy->table('skills')->insert([
        ['id' => 1, 'language' => 'it-IT', 'name' => 'Lavoro di squadra', 'type' => 'soft-skill'],
        ['id' => 2, 'language' => 'en-GB', 'name' => 'Teamwork', 'type' => 'soft-skill'],
        ['id' => 3, 'language' => 'it-IT', 'name' => 'SQL', 'type' => 'tool'],
        ['id' => 4, 'language' => 'en-GB', 'name' => 'SQL', 'type' => 'tool'],
    ]);

    $legacy->table('experience_skill')->insert([
        ['id_experience' => 1, 'id_skill' => 3, 'language' => 'it-IT'],
        ['id_experience' => 2, 'id_skill' => 4, 'language' => 'en-GB'],
        ['id_experience' => 1, 'id_skill' => 2, 'language' => 'it-IT'], // Italian row pointing to the English skill
    ]);

    $legacy->table('projects')->insert([
        ['id' => 1, 'language' => 'it-IT', 'title' => 'Progetto A', 'role' => 'Sviluppo', 'start' => '2018-03-01', 'end' => '2018-12-31', 'company' => 'Cliente Uno', 'sector' => 'Banche', 'description' => '<p>A</p>', 'details' => '<p>Dettagli A</p>'],
        ['id' => 2, 'language' => 'en-GB', 'title' => 'Project A', 'role' => 'Development', 'start' => '2018-03-01', 'end' => '2018-12-31', 'company' => 'Cliente Uno', 'sector' => 'Banking', 'description' => '<p>A</p>', 'details' => '<p>Details A</p>'],
        ['id' => 3, 'language' => 'it-IT', 'title' => 'Progetto B', 'role' => null, 'start' => '2021-05-01', 'end' => null, 'company' => 'Cliente Uno', 'sector' => null, 'description' => null, 'details' => null],
        ['id' => 4, 'language' => 'en-GB', 'title' => 'Project B', 'role' => null, 'start' => '2021-05-01', 'end' => null, 'company' => 'Cliente Uno', 'sector' => null, 'description' => null, 'details' => null],
    ]);

    $unimib = json_encode(['icon' => 'img/unimib.png', 'icon_alt' => 'Unimib']);
    $legacy->table('educations')->insert([
        ['language' => 'en-GB', 'institution_name' => 'Università degli Studi di Milano-Bicocca', 'degree_type' => 'Master Degree', 'field_of_study' => 'Computer Science', 'start' => '2013-11-04', 'end' => '2017-07-19', 'grade_value' => '110', 'grade_laude' => '1', 'grade_scale' => '110', 'images' => $unimib, 'link' => 'https://www.unimib.it', 'description' => 'EN', 'details' => '<table class="table"><tr><td>Exam</td></tr></table>'],
        ['language' => 'it-IT', 'institution_name' => 'Università degli Studi di Milano-Bicocca', 'degree_type' => 'Laurea Magistrale', 'field_of_study' => 'Informatica', 'start' => '2013-11-04', 'end' => '2017-07-19', 'grade_value' => '110', 'grade_laude' => '1', 'grade_scale' => '110', 'images' => $unimib, 'link' => 'https://www.unimib.it', 'description' => 'IT', 'details' => null],
    ]);

    $legacy->table('certifications')->insert([
        ['id' => 1, 'language' => 'it-IT', 'date' => '2020-05-15', 'title' => 'Certificazione X', 'type' => 'Certificazione', 'institution' => 'Ente', 'images' => 'img/badge x.png', 'link' => 'attachment/Cert X (2020).pdf'],
        ['id' => 2, 'language' => 'en-GB', 'date' => '2020-05-15', 'title' => 'Certification X', 'type' => 'Certification', 'institution' => 'Ente', 'images' => 'img/badge x.png', 'link' => 'attachment/attachment/Cert X (2020).pdf'],
        ['id' => 3, 'language' => 'it-IT', 'date' => '2018-02-22', 'title' => 'Esame Y', 'type' => 'Percorso formativo', 'institution' => 'Ente', 'images' => null, 'link' => 'https://verify.example/y'],
        ['id' => 4, 'language' => 'en-GB', 'date' => '2018-09-22', 'title' => 'Exam Y', 'type' => 'Training Path', 'institution' => 'Ente', 'images' => null, 'link' => 'https://verify.example/y'],
    ]);

    // v1 files
    $this->files = sys_get_temp_dir().'/v1-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$this->files}/img/avatars");
    File::ensureDirectoryExists("{$this->files}/attachment");
    File::ensureDirectoryExists("{$this->files}/resume");
    File::ensureDirectoryExists("{$this->files}/languages");

    File::ensureDirectoryExists("{$this->files}/img/favicon");

    foreach (['img/avatars/me.png', 'img/acme.png', 'img/unimib.png', 'img/badge x.png', 'img/favicon/android-chrome-512x512.png'] as $image) {
        $gd = imagecreatetruecolor(40, 40);
        imagepng($gd, "{$this->files}/{$image}");
    }

    file_put_contents("{$this->files}/attachment/Cert X (2020).pdf", DemoSeeder::samplePdf('Cert X'));
    file_put_contents("{$this->files}/resume/CV Mario Rossi - 2026_IT.pdf", DemoSeeder::samplePdf('CV'));
    file_put_contents("{$this->files}/resume/CV Mario Rossi - 2025.docx", 'docx');
    file_put_contents("{$this->files}/languages/it-IT.php", "<?php return ['FOOTER_COPYRIGHT' => 'Testo protetto.', 'FOOTER_PRIVACY' => 'Autorizzo il trattamento dei dati (D.Lgs. 2018/101).'];");
});

afterEach(fn () => File::deleteDirectory($this->files));

function importV1(array $options = []): string
{
    Artisan::call('resume:import-v1', ['--files' => test()->files, '--force' => true] + $options);

    return Artisan::output();
}

it('imports the profile, its photo and the social profiles', function () {
    importV1();

    $profile = Profile::current();

    expect($profile->full_name)->toBe('Mario Rossi')
        ->and($profile->headlineList('it-IT'))->toBe(['Data Governance', 'Data Quality'])
        ->and($profile->t('bio', 'en-GB'))->toBe('<p>Hello</p>')
        ->and($profile->t('location', 'en-GB'))->toBe('Milan, Italy')
        ->and($profile->country_code)->toBe('IT')
        ->and($profile->career_start_date->toDateString())->toBe('2010-01-01')
        ->and($profile->photo)->toEndWith('.webp')
        ->and(SocialLink::query()->orderBy('sort_order')->get()->map(fn (SocialLink $link): string => $link->platform->value)->all())->toBe(['linkedin', 'github']);

    Storage::disk('uploads')->assertExists($profile->photo);
});

it('imports experiences in pairs of languages, with organisations, skills and volunteering', function () {
    $output = importV1(['--volunteering' => '6']);

    $acme = Experience::query()->whereHas('organization', fn ($query) => $query->where('name', 'Acme'))->sole();
    $volunteering = Experience::query()->where('kind', ExperienceKind::Volunteering)->sole();

    expect(Experience::query()->count())->toBe(3)
        ->and($acme->t('role', 'it-IT'))->toBe('Consulente')
        ->and($acme->t('role', 'en-GB'))->toBe('Consultant')
        ->and($acme->t('location', 'it-IT'))->toBe('Milano')
        ->and($acme->organization->website)->toBe('https://acme.example')
        ->and($acme->organization->logo)->toStartWith('logos/')
        // the Italian row that pointed to the English skill is mapped to the same skill
        ->and($acme->skills->map(fn ($skill) => $skill->t('name', 'en-GB'))->sort()->values()->all())->toBe(['SQL', 'Teamwork'])
        ->and($volunteering->t('role', 'it-IT'))->toBe('Volontario')
        ->and($volunteering->organization->type)->toBe(OrganizationType::Association)
        ->and($output)->toContain('Experience: 3 (1 of them volunteering)');
});

it('links each project to the experience of its dates and to its client', function () {
    importV1();

    $a = Project::query()->where('slug->it-IT', 'progetto-a')->sole();
    $b = Project::query()->where('slug->it-IT', 'progetto-b')->sole();

    expect($a->experience->t('role', 'en-GB'))->toBe('Consultant')
        ->and($b->experience->t('role', 'en-GB'))->toBe('Manager')
        ->and($a->client->name)->toBe('Cliente Uno')
        ->and($a->client->type)->toBe(OrganizationType::Client)
        ->and($b->client_id)->toBe($a->client_id)
        ->and($a->t('details', 'en-GB'))->toBe('<p>Details A</p>');
});

it('imports education matching the languages by dates, sharing the organisation', function () {
    importV1();

    $education = Education::query()->sole();

    expect($education->t('degree', 'it-IT'))->toBe('Laurea Magistrale')
        ->and($education->t('degree', 'en-GB'))->toBe('Master Degree')
        ->and($education->grade)->toBe('110')
        ->and($education->cum_laude)->toBeTrue()
        ->and($education->organization->type)->toBe(OrganizationType::University)
        ->and(Organization::query()->where('name', 'Università degli Studi di Milano-Bicocca')->count())->toBe(1);
});

it('imports certifications with their PDF and badge, redirecting the old addresses', function () {
    $output = importV1();

    $x = Certification::query()->where('title->en-GB', 'Certification X')->sole();
    $y = Certification::query()->where('title->en-GB', 'Exam Y')->sole();

    expect($x->type)->toBe(CertificationType::Certification)
        ->and($x->file)->toBe('certificates/cert-x-2020.pdf')
        ->and($x->badge)->toStartWith('badges/')
        ->and($y->type)->toBe(CertificationType::TrainingPath)
        ->and($y->verification_url)->toBe('https://verify.example/y')
        ->and($y->issued_on->toDateString())->toBe('2018-02-22')
        ->and($output)->toContain('dates differ between languages');

    $this->get('/attachment/Cert%20X%20(2020).pdf')->assertRedirect('/uploads/certificates/cert-x-2020.pdf')->assertStatus(301);
    $this->get('/attachment/attachment/Cert%20X%20(2020).pdf')->assertRedirect('/uploads/certificates/cert-x-2020.pdf');
});

it('imports the current CV, redirects the old CV files and keeps the footer texts', function () {
    importV1(['--cv' => ['it-IT='.test()->files.'/resume/CV Mario Rossi - 2026_IT.pdf']]);

    $cv = CvDocument::query()->sole();

    expect($cv->language->code)->toBe('it-IT')
        ->and($cv->is_current)->toBeTrue()
        ->and(app(Settings::class)->bool('cv.fallback_to_default'))->toBeTrue()
        ->and(app(Settings::class)->translated('footer.gdpr_text', 'it-IT'))->toBe("Testo protetto.\nAutorizzo il trattamento dei dati (D.Lgs. 2018/101).")
        ->and(app(Settings::class)->get('site.icon'))->toStartWith('site/')->toEndWith('.png');

    Storage::disk('local')->assertExists($cv->path);

    $this->get('/resume/CV%20Mario%20Rossi%20-%202026_IT.pdf')->assertRedirect('/it-IT/cv');
    $this->get('/resume/CV%20Mario%20Rossi%20-%202025.docx')->assertRedirect('/it-IT/cv');
    $this->get('/en-GB/cv')->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('can replace the existing content', function () {
    importV1();
    importV1(['--fresh' => true]);

    expect(Experience::query()->count())->toBe(3)
        ->and(Project::query()->count())->toBe(2)
        ->and(Organization::query()->where('name', 'Acme')->count())->toBe(1);
});

it('refuses to start without the v1 files', function () {
    expect(Artisan::call('resume:import-v1', ['--files' => '/does/not/exist']))->toBe(1);
});
