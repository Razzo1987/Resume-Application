<?php

use App\Filament\Pages\ManageSystem;
use App\Models\User;
use App\Support\Cron\Cron;
use App\Support\Cron\CronJobs;
use App\Support\Languages\Locales;
use App\Support\Settings\Settings;
use App\Support\System\Maintenance;
use App\Support\System\Updater;
use App\Support\Update\AutoUpdate;
use App\Support\Update\CronStop;
use App\Support\Update\PackageInspector;
use App\Support\Update\Release;
use App\Support\Update\ReleaseChecker;
use App\Support\Update\ReleaseDownloader;
use App\Support\Update\UpdateStorage;
use App\Themes\ThemeManager;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
| Aggiornamenti dall'admin: controllo delle release su GitHub, download e caricamento a blocchi, controlli sul
| pacchetto, estrazione fuori da Laravel (resources/update/updater.php) e completamento.
| Tutto avviene in una cartella di prova con la struttura di un'installazione da pacchetto (webroot + core/),
| così i test non toccano i file del progetto.
*/

require_once dirname(__DIR__, 3).'/resources/update/updater.php';

// Versioni relative a quella del progetto (VERSION), così i test valgono a ogni rilascio:
// UPDATES_FIX è una versione di correzione più recente (stessa x.y), UPDATES_FEATURE la x.y successiva
[$major, $minor, $patch] = array_map(intval(...), explode('.', trim((string) file_get_contents(dirname(__DIR__, 3).'/VERSION'))));
define('UPDATES_CURRENT', "{$major}.{$minor}.{$patch}");
define('UPDATES_FIX', "{$major}.{$minor}.".($patch + 6));
define('UPDATES_FIX_NEXT', "{$major}.{$minor}.".($patch + 7));
define('UPDATES_FEATURE', "{$major}.".($minor + 1).'.0');

beforeEach(function () {
    Http::preventStrayRequests();
    app()->setLocale('it');

    $this->web = sys_get_temp_dir().'/resume-update-'.bin2hex(random_bytes(4));
    $this->core = "{$this->web}/core";

    foreach (['core/storage/framework', 'core/storage/app/private', 'core/app', 'uploads', 'themes', 'core/themes'] as $directory) {
        File::ensureDirectoryExists("{$this->web}/{$directory}");
    }

    // Installazione da pacchetto, con dati e configurazione del sito che un aggiornamento non deve toccare
    file_put_contents("{$this->core}/manifest.json", '{"name":"resume-app","version":"2.0.2","files":{}}');
    file_put_contents("{$this->core}/.env", 'APP_KEY=secret');
    file_put_contents("{$this->web}/.htaccess", 'custom rules');
    file_put_contents("{$this->web}/uploads/photo.webp", 'original');
    file_put_contents("{$this->core}/app/Old.php", '<?php // 2.0.2');

    // Temi: solo il base (più quelli aggiunti dai singoli test)
    symlink(base_path('themes/base'), "{$this->web}/core/themes/base");
    $this->app->singleton(ThemeManager::class, fn (): ThemeManager => new ThemeManager(
        themesPath: "{$this->web}/core/themes",
        activeName: 'base',
        baseName: 'base',
        publicPath: $this->web,
    ));

    $this->app->instance(UpdateStorage::class, new UpdateStorage("{$this->core}/storage"));
    $this->app->bind(PackageInspector::class, fn (): PackageInspector => new PackageInspector(app(Updater::class), app(ThemeManager::class), $this->core, $this->web));
    $this->state = "{$this->core}/storage/framework/resume-update.json";
});

afterEach(function () {
    @unlink("{$this->web}/core/themes/base");
    File::deleteDirectory($this->web);
    app(Maintenance::class)->off();
});

/**
 * Pacchetto di prova: $extra aggiunge o sostituisce elementi (null li toglie, "nome/" è una cartella).
 *
 * @param  array<string, string|null>  $extra
 * @param  array<string, mixed>  $requires
 */
function updatePackage(string $version, array $extra = [], array $requires = ['php' => '8.4.0', 'extensions' => ['zip']]): string
{
    $path = sys_get_temp_dir().'/resume-package-'.bin2hex(random_bytes(4)).'.zip';
    $entries = array_replace([
        'index.php' => '<?php // front controller '.$version,
        'core/VERSION' => $version."\n",
        'core/manifest.json' => json_encode(['name' => 'resume-app', 'version' => $version, 'requires' => $requires, 'files' => []]),
        'core/artisan' => '#!/usr/bin/env php',
        'core/bootstrap/app.php' => '<?php // '.$version,
        'core/vendor/autoload.php' => '<?php // '.$version,
        'core/app/Old.php' => '<?php // '.$version,
        'core/app/NewFeature.php' => '<?php // added in '.$version,
        'core/storage/logs/' => '',
        'core/storage/logs/.gitignore' => '*',
        'themes/base/assets/app-NEW.css' => 'body{}',
    ], $extra);

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);

    foreach ($entries as $name => $content) {
        if ($content === null) {
            continue;
        }

        str_ends_with($name, '/') ? $zip->addEmptyDir(rtrim($name, '/')) : $zip->addFromString($name, $content);
    }

    $zip->close();

    return $path;
}

/**
 * Risposta dell'API di GitHub per una release.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function githubRelease(string $version, array $overrides = [], int $size = 30): array
{
    $base = 'https://api.github.com/repos/Razzo1987/Resume-Application/releases/assets';
    $download = "https://github.com/Razzo1987/Resume-Application/releases/download/v{$version}";

    return array_replace([
        'tag_name' => "v{$version}",
        'name' => "Resume App {$version}",
        'draft' => false,
        'prerelease' => false,
        'body' => "## Novità\n- prima cosa\n- <script>alert(1)</script>",
        'html_url' => "https://github.com/Razzo1987/Resume-Application/releases/tag/v{$version}",
        'published_at' => '2026-11-02T10:00:00Z',
        'assets' => [
            ['id' => 11, 'name' => "resume-app-{$version}.zip", 'size' => $size, 'url' => "{$base}/11", 'browser_download_url' => "{$download}/resume-app-{$version}.zip"],
            ['id' => 12, 'name' => "resume-app-{$version}.zip.sha256", 'size' => 90, 'url' => "{$base}/12", 'browser_download_url' => "{$download}/resume-app-{$version}.zip.sha256"],
        ],
    ], $overrides);
}

function fakeLatestRelease(array $data, int $status = 200): void
{
    Http::fake(['api.github.com/repos/Razzo1987/Resume-Application/releases/latest' => Http::response($data, $status)]);
}

// --- Estrazione (script fuori da Laravel) ---------------------------------------------------------------

it('maps package paths to the core and the web root, never to site data', function (string $name, ?string $expected) {
    expect(resume_updater_target($name, '/web', '/home/core'))->toBe($expected);
})->with([
    ['index.php', '/web/index.php'],
    ['themes/base/assets/app.css', '/web/themes/base/assets/app.css'],
    ['core/app/X.php', '/home/core/app/X.php'],
    ['core/themes/base/theme.json', '/home/core/themes/base/theme.json'],
    ['core/storage/logs/', '/home/core/storage/logs'],
    ['core/.env', null],
    ['core/storage/app/private/cv/cv.pdf', null],
    ['core/themes/demo-pro/theme.json', null],
    ['themes/demo-pro/assets/app.css', null],
    ['uploads/photo.webp', null],
    ['.htaccess', null],
    ['core-path.php', null],
]);

it('refuses package paths that point outside the site', function (string $name) {
    expect(fn () => resume_updater_target($name, '/web', '/home/core'))->toThrow(RuntimeException::class);
})->with(['../evil.php', 'core/../../evil.php', '/etc/passwd', 'C:/evil.php', 'core\\evil.php', "core/a\0b"]);

it('extracts a package in short steps, leaving data and configuration untouched', function () {
    $package = updatePackage(UPDATES_FIX, [
        'core/.env' => 'APP_KEY=from-package',
        '.htaccess' => 'from package',
        'uploads/photo.webp' => 'from package',
        'core/storage/app/private/cv.pdf' => 'from package',
        'themes/demo-pro/assets/app.css' => 'pro',
        'core/themes/demo-pro/theme.json' => '{}',
    ]);
    file_put_contents($this->state, json_encode(['token' => 'abc', 'zip' => $package, 'next' => 0, 'total' => 17, 'return' => '/admin/system?update=finish']));

    $steps = 0;

    do {
        $result = resume_updater_step($this->state, $this->web, $this->core, 5.0, 4);
        $steps++;
    } while (! $result['done']);

    expect($steps)->toBe(5)
        ->and($result['redirect'])->toBe('/admin/system?update=finish')
        ->and($this->state)->not->toBeFile()
        ->and(file_get_contents("{$this->core}/app/Old.php"))->toContain(UPDATES_FIX)
        ->and(file_get_contents("{$this->core}/app/NewFeature.php"))->toContain(UPDATES_FIX)
        ->and(file_get_contents("{$this->web}/themes/base/assets/app-NEW.css"))->toBe('body{}')
        ->and("{$this->core}/storage/logs")->toBeDirectory()
        ->and(file_get_contents("{$this->core}/.env"))->toBe('APP_KEY=secret')
        ->and(file_get_contents("{$this->web}/.htaccess"))->toBe('custom rules')
        ->and(file_get_contents("{$this->web}/uploads/photo.webp"))->toBe('original')
        ->and("{$this->core}/storage/app/private/cv.pdf")->not->toBeFile()
        ->and("{$this->web}/themes/demo-pro")->not->toBeDirectory()
        ->and("{$this->core}/themes/demo-pro")->not->toBeDirectory()
        ->and(glob("{$this->web}/**/*.resume-tmp") ?: [])->toBe([]);

    @unlink($package);
});

it('stops on an unsafe entry without losing its place, so it can be retried', function () {
    $package = updatePackage(UPDATES_FIX, ['core/zz/../../../evil.php' => 'x']);
    file_put_contents($this->state, json_encode(['token' => 'abc', 'zip' => $package, 'next' => 0, 'total' => 12, 'return' => '/']));

    expect(fn () => resume_updater_step($this->state, $this->web, $this->core))->toThrow(RuntimeException::class)
        ->and(json_decode((string) file_get_contents($this->state), true)['next'])->toBe(0)
        ->and(dirname($this->web).'/evil.php')->not->toBeFile();

    @unlink($package);
});

// --- Controlli sul pacchetto ----------------------------------------------------------------------------

it('accepts a newer package that this server can run', function () {
    $report = app(PackageInspector::class)->inspect($package = updatePackage(UPDATES_FIX));

    expect($report->ok())->toBeTrue()
        ->and($report->version)->toBe(UPDATES_FIX)
        ->and($report->currentVersion)->toBe(app(Updater::class)->codeVersion())
        ->and($report->entries)->toBe(11);

    @unlink($package);
});

it('refuses packages that cannot be installed', function (string $package, string $expected) {
    $report = app(PackageInspector::class)->inspect($package);

    expect($report->ok())->toBeFalse()
        ->and(implode("\n", $report->problems))->toContain($expected);

    @unlink($package);
})->with([
    'not a zip' => [fn () => tap(sys_get_temp_dir().'/resume-not-zip.zip', fn (string $path) => file_put_contents($path, 'text')), 'non è un archivio zip'],
    'not a package' => [fn () => updatePackage(UPDATES_FIX, ['core/vendor/autoload.php' => null]), 'core/vendor/autoload.php'],
    'older version' => [fn () => updatePackage('1.9.0'), 'più vecchia'],
    'php too old' => [fn () => updatePackage(UPDATES_FIX, [], ['php' => '99.0.0']), 'PHP 99.0.0'],
    'missing extension' => [fn () => updatePackage(UPDATES_FIX, [], ['extensions' => ['resume_missing_extension']]), 'resume_missing_extension'],
    'unsafe path' => [fn () => updatePackage(UPDATES_FIX, ['../evil.php' => 'x']), '../evil.php'],
    'incompatible theme' => [function () {
        File::ensureDirectoryExists("{$this->core}/themes/demo-pro/views");
        file_put_contents("{$this->core}/themes/demo-pro/theme.json", json_encode(['name' => 'demo-pro', 'title' => 'Demo PRO', 'version' => '1.0.0', 'requires' => ['resume-app' => '^2.0']]));

        return updatePackage('3.0.0');
    }, 'Demo PRO'],
    'development copy' => [function () {
        unlink("{$this->core}/manifest.json");

        return updatePackage(UPDATES_FIX);
    }, 'installazione da pacchetto'],
]);

// --- Release su GitHub ----------------------------------------------------------------------------------

it('finds a newer release on GitHub, sending the token when there is one', function () {
    app(UpdateStorage::class)->setToken('github_pat_test');
    fakeLatestRelease(githubRelease(UPDATES_FIX));

    $status = app(ReleaseChecker::class)->check();
    $release = app(ReleaseChecker::class)->available();

    expect($status['error'])->toBeNull()
        ->and($release?->version)->toBe(UPDATES_FIX)
        ->and($release?->package['name'])->toBe('resume-app-'.UPDATES_FIX.'.zip')
        ->and(app(ReleaseChecker::class)->isStale())->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer github_pat_test')
        && $request->hasHeader('X-GitHub-Api-Version'));
});

it('keeps the GitHub token encrypted, with the one in .env taking precedence', function () {
    $storage = app(UpdateStorage::class);
    $storage->setToken('github_pat_secret');

    expect(file_get_contents($storage->tokenFile()))->not->toContain('github_pat_secret')
        ->and($storage->token())->toBe('github_pat_secret')
        ->and($storage->tokenSource())->toBe('admin');

    config(['resume.updates.token' => 'from_env']);

    expect($storage->token())->toBe('from_env')->and($storage->tokenSource())->toBe('env');

    config(['resume.updates.token' => null]);
    $storage->setToken(null);

    expect($storage->token())->toBeNull()->and($storage->tokenFile())->not->toBeFile();
});

it('does not offer older versions, drafts or pre-releases', function (array $data) {
    fakeLatestRelease($data);
    app(ReleaseChecker::class)->check();

    expect(app(ReleaseChecker::class)->available())->toBeNull();
})->with([
    'same version' => [fn () => githubRelease(app(Updater::class)->codeVersion())],
    'older' => [fn () => githubRelease('1.9.0')],
    'draft' => [fn () => githubRelease(UPDATES_FIX, ['draft' => true])],
    'pre-release' => [fn () => githubRelease(UPDATES_FIX, ['prerelease' => true])],
    'not a version' => [fn () => githubRelease(UPDATES_FIX, ['tag_name' => 'nightly'])],
]);

it('explains a failed check and keeps the last known release', function () {
    Http::fake(['api.github.com/*' => Http::sequence()->push(githubRelease(UPDATES_FIX))->push(['message' => 'Not Found'], 404)]);
    app(ReleaseChecker::class)->check();
    $status = app(ReleaseChecker::class)->check();

    expect($status['error'])->toContain('privato')
        ->and(app(ReleaseChecker::class)->available()?->version)->toBe(UPDATES_FIX);
});

// --- Download e caricamento a blocchi ----------------------------------------------------------------------

it('downloads the package in chunks and checks its SHA-256', function () {
    $content = str_repeat('resume-app', 3);
    $release = Release::fromGitHub(githubRelease(UPDATES_FIX, size: strlen($content)));

    Http::fake(function (Request $request) use ($content) {
        if (str_ends_with($request->url(), '.sha256')) {
            return Http::response(hash('sha256', $content).'  resume-app-'.UPDATES_FIX.'.zip');
        }

        preg_match('/bytes=(\d+)-(\d+)/', $request->header('Range')[0] ?? '', $range);

        return Http::response(substr($content, (int) $range[1], (int) $range[2] - (int) $range[1] + 1), 206);
    });

    $downloader = new ReleaseDownloader(app(UpdateStorage::class), app(ReleaseChecker::class), 8);
    $calls = 0;

    do {
        $result = $downloader->next($release);
        $calls++;
    } while (! $result['done']);

    expect($calls)->toBe(4)
        ->and($result['file'])->toBe('resume-app-'.UPDATES_FIX.'.zip')
        ->and(file_get_contents((string) app(UpdateStorage::class)->package('resume-app-'.UPDATES_FIX.'.zip')))->toBe($content);
});

it('deletes a download that does not match the published SHA-256', function () {
    $release = Release::fromGitHub(githubRelease(UPDATES_FIX, size: 10));

    Http::fake(fn (Request $request) => str_ends_with($request->url(), '.sha256')
        ? Http::response(str_repeat('0', 64).'  resume-app-'.UPDATES_FIX.'.zip')
        : Http::response('0123456789', 206));

    expect(fn () => (new ReleaseDownloader(app(UpdateStorage::class), app(ReleaseChecker::class)))->next($release))
        ->toThrow(RuntimeException::class)
        ->and(app(UpdateStorage::class)->packages())->toBe([]);
});

it('assembles a package uploaded in chunks, only for administrators', function () {
    $url = fn (array $query): string => route('filament.admin.update-upload').'?'.http_build_query($query);
    $send = fn (array $query, string $body) => $this->call('POST', $url($query), [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], $body);
    $query = ['id' => str_repeat('a', 32), 'count' => 3, 'name' => 'resume-app-'.UPDATES_FIX.'.zip'];

    Filament::setCurrentPanel('admin');
    $send([...$query, 'index' => 0], 'one|')->assertUnauthorized();

    $this->actingAs(User::factory()->create());

    $send([...$query, 'index' => 0], 'one|')->assertOk()->assertJson(['next' => 1]);
    $send([...$query, 'index' => 2], 'three')->assertStatus(409)->assertJson(['next' => 1]);
    $send([...$query, 'index' => 1], 'two|')->assertOk()->assertJson(['next' => 2]);
    $send([...$query, 'index' => 2], 'three')->assertOk()->assertJson(['done' => true, 'file' => 'resume-app-'.UPDATES_FIX.'.zip']);
    $send([...$query, 'index' => 0, 'name' => '../evil.php'], 'x')->assertStatus(422);

    expect(file_get_contents((string) app(UpdateStorage::class)->package('resume-app-'.UPDATES_FIX.'.zip')))->toBe('one|two|three');
});

// --- Dall'admin ---------------------------------------------------------------------------------------

it('shows a new version on the dashboard and on the system page', function () {
    seedDemo();
    $this->actingAs(User::factory()->create());
    app(Settings::class)->set(Updater::VERSION_KEY, app(Updater::class)->codeVersion());
    fakeLatestRelease(githubRelease(UPDATES_FIX));
    app(ReleaseChecker::class)->check();

    $this->get('/admin')->assertOk()->assertSee('Disponibile la versione '.UPDATES_FIX);

    $this->get('/admin/system')
        ->assertOk()
        ->assertSee('Scarica e installa')
        ->assertSee('prima cosa')
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});

it('checks GitHub on request from the system page', function () {
    seedDemo();
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');
    fakeLatestRelease(githubRelease(UPDATES_FIX));

    Livewire::test(ManageSystem::class)
        ->callAction('checkUpdates')
        ->assertNotified('Disponibile la versione '.UPDATES_FIX);
});

it('updates from the system page: checks, safety copy, extraction outside Laravel, completion', function () {
    seedDemo();
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    $storage = app(UpdateStorage::class);
    File::ensureDirectoryExists($storage->packagesDirectory());
    rename(updatePackage(UPDATES_FIX), $storage->packagesDirectory().'/resume-app-'.UPDATES_FIX.'.zip');
    $backups = glob(storage_path('app/private/backups/*.zip')) ?: [];

    Livewire::test(ManageSystem::class)
        ->callAction('install', arguments: ['package' => 'resume-app-'.UPDATES_FIX.'.zip'])
        ->assertRedirectContains('?resume-update=');

    $state = json_decode((string) file_get_contents($storage->stateFile()), true);

    expect(app(Maintenance::class)->isOn())->toBeTrue()
        ->and($storage->updaterFile())->toBeFile()
        ->and($state['to'])->toBe(UPDATES_FIX)
        ->and($state['strings']['visitor_title'])->toBe(__('site.errors.maintenance_title', [], app(Locales::class)->default()))
        ->and($state['strings']['title'])->toBe('Aggiornamento in corso')
        ->and(count(glob(storage_path('app/private/backups/*.zip')) ?: []))->toBe(count($backups) + 1);

    // Estrazione: le richieste che index.php passa allo script finché esiste lo stato
    do {
        $result = resume_updater_step($storage->stateFile(), $this->web, $this->core);
    } while (! $result['done']);

    expect(file_get_contents("{$this->core}/app/NewFeature.php"))->toContain(UPDATES_FIX);

    // Ritorno alla pagina Sistema: completamento automatico
    app(Settings::class)->set(Updater::VERSION_KEY, '2.0.1');

    Livewire::withQueryParams(['update' => 'finish'])
        ->test(ManageSystem::class)
        ->assertNotified(__('admin.system.update_completed'));

    expect(app(Maintenance::class)->isOn())->toBeFalse()
        ->and(app(Updater::class)->isPending())->toBeFalse()
        ->and($storage->packages())->toBe([])
        ->and($storage->updaterFile())->not->toBeFile()
        ->and($storage->runFile())->not->toBeFile();

    foreach (array_diff(glob(storage_path('app/private/backups/*.zip')) ?: [], $backups) as $file) {
        @unlink($file);
    }
});

it('refuses to start an update with a package that fails the checks', function () {
    seedDemo();
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    $storage = app(UpdateStorage::class);
    File::ensureDirectoryExists($storage->packagesDirectory());
    rename(updatePackage('1.9.0'), $storage->packagesDirectory().'/resume-app-1.9.0.zip');

    Livewire::test(ManageSystem::class)
        ->callAction('install', arguments: ['package' => 'resume-app-1.9.0.zip'])
        ->assertNotified(__('admin.updates.install_failed'))
        ->assertNoRedirect();

    expect($storage->isExtracting())->toBeFalse()->and(app(Maintenance::class)->isOn())->toBeFalse();
});

// --- Aggiornamenti automatici col cron ----------------------------------------------------------------

/**
 * GitHub finto con la release $version e il suo pacchetto (scaricabile a blocchi, con SHA-256).
 */
function fakeGitHubWithPackage(string $version): void
{
    $content = (string) file_get_contents(updatePackage($version));

    Http::fake(function (Request $request) use ($version, $content) {
        if (str_ends_with($request->url(), 'releases/latest')) {
            return Http::response(githubRelease($version, size: strlen($content)));
        }

        if (str_ends_with($request->url(), '.sha256')) {
            return Http::response(hash('sha256', $content)."  resume-app-{$version}.zip");
        }

        preg_match('/bytes=(\d+)-(\d+)/', $request->header('Range')[0] ?? '', $range);

        return Http::response(substr($content, (int) $range[1], (int) $range[2] - (int) $range[1] + 1), 206);
    });
}

function autoUpdate(string $level): AutoUpdate
{
    app()->instance(Cron::class, $cron = new Cron(test()->core.'/storage'));
    CronJobs::register($cron);
    $cron->rememberWebRuntime();
    app(Settings::class)->set(AutoUpdate::SETTING, $level);
    app(Settings::class)->set(Updater::VERSION_KEY, app(Updater::class)->codeVersion());

    return app(AutoUpdate::class);
}

function sentMails(): array
{
    return app('mail.manager')->mailer()->getSymfonyTransport()->messages()->all();
}

it('only checks for new versions when automatic updates are off or the cron job is missing', function () {
    seedDemo();
    fakeGitHubWithPackage(UPDATES_FIX);
    $auto = autoUpdate(AutoUpdate::OFF);

    expect($auto->run(fromCron: true))->toBe('available '.UPDATES_FIX)
        ->and(app(UpdateStorage::class)->packages())->toBe([]);

    app(Settings::class)->set(AutoUpdate::SETTING, AutoUpdate::PATCH);

    expect($auto->run(fromCron: false))->toBe('available '.UPDATES_FIX)
        ->and(app(UpdateStorage::class)->packages())->toBe([]);
});

it('downloads a new version and tells the administrators', function () {
    seedDemo();
    User::factory()->create(['email' => 'admin@example.com']);
    fakeGitHubWithPackage(UPDATES_FEATURE);

    expect(autoUpdate(AutoUpdate::DOWNLOAD)->run(fromCron: true))->toBe('downloaded '.UPDATES_FEATURE)
        ->and(app(UpdateStorage::class)->package('resume-app-'.UPDATES_FEATURE.'.zip'))->toBeFile()
        ->and(app(AutoUpdate::class)->lastEvent()['type'])->toBe('downloaded')
        ->and(sentMails())->toHaveCount(1);

    $this->actingAs(User::query()->first());
    $this->get('/admin')->assertSee('La versione '.UPDATES_FEATURE.' è pronta da installare');
});

it('never installs a feature release by itself, only downloads it', function () {
    seedDemo();
    fakeGitHubWithPackage(UPDATES_FEATURE);

    expect(autoUpdate(AutoUpdate::PATCH)->run(fromCron: true))->toBe('downloaded '.UPDATES_FEATURE)
        ->and(app(UpdateStorage::class)->isExtracting())->toBeFalse()
        ->and(app(Maintenance::class)->isOn())->toBeFalse();
});

it('recognises fix releases', function (string $version, bool $patch) {
    expect(app(AutoUpdate::class)->isPatch($version))->toBe($patch);
})->with([
    [UPDATES_FIX, true],
    [UPDATES_FIX_NEXT, true],
    [UPDATES_FEATURE, false],
    ['3.0.0', false],
    [UPDATES_CURRENT, false],
    ['1.9.0', false],
]);

it('installs a fix release from the cron job and completes it in a new process', function () {
    seedDemo();
    User::factory()->create(['email' => 'admin@example.com']);
    fakeGitHubWithPackage(UPDATES_FIX);
    $auto = autoUpdate(AutoUpdate::PATCH);
    $backups = glob(storage_path('app/private/backups/*.zip')) ?: [];

    // Primo giro: download, controlli, copia dei contenuti, manutenzione, estrazione; poi il giro si ferma
    expect(fn () => $auto->run(fromCron: true))->toThrow(CronStop::class, 'extracted '.UPDATES_FIX)
        ->and(file_get_contents("{$this->core}/app/NewFeature.php"))->toContain(UPDATES_FIX)
        ->and(file_get_contents("{$this->web}/uploads/photo.webp"))->toBe('original')
        ->and(app(UpdateStorage::class)->isExtracting())->toBeFalse()
        ->and(app(Maintenance::class)->isOn())->toBeTrue()
        ->and(count(glob(storage_path('app/private/backups/*.zip')) ?: []))->toBe(count($backups) + 1);

    // Giro successivo (o "resume:update --finish"): migrazioni, cache, fine della manutenzione
    app(Settings::class)->set(Updater::VERSION_KEY, '2.0.1');

    expect(Artisan::call('resume:update', ['--finish' => true]))->toBe(0)
        ->and(app(Cron::class)->jobs()['updates']['message'])->toBe('installed '.UPDATES_FIX)
        ->and(app(Maintenance::class)->isOn())->toBeFalse()
        ->and(app(UpdateStorage::class)->runFile())->not->toBeFile()
        ->and($auto->lastEvent()['type'])->toBe('installed')
        ->and(collect(sentMails())->map(fn ($message) => $message->getOriginalMessage()->getSubject())->last())->toContain(UPDATES_FIX);

    foreach (array_diff(glob(storage_path('app/private/backups/*.zip')) ?: [], $backups) as $file) {
        @unlink($file);
    }
});

it('does not install a fix release the web server cannot run', function () {
    seedDemo();
    $content = (string) file_get_contents(updatePackage(UPDATES_FIX, requires: ['php' => '8.4.0', 'extensions' => ['zip', 'imaginary']]));

    Http::fake(function (Request $request) use ($content) {
        return match (true) {
            str_ends_with($request->url(), 'releases/latest') => Http::response(githubRelease(UPDATES_FIX, size: strlen($content))),
            str_ends_with($request->url(), '.sha256') => Http::response(hash('sha256', $content).'  resume-app-'.UPDATES_FIX.'.zip'),
            default => Http::response($content, 200),
        };
    });

    $auto = autoUpdate(AutoUpdate::PATCH);

    expect($auto->run(fromCron: true))->toContain('not installed '.UPDATES_FIX)
        ->and($auto->lastEvent()['type'])->toBe('failed')
        ->and(app(UpdateStorage::class)->isExtracting())->toBeFalse()
        ->and(app(Maintenance::class)->isOn())->toBeFalse();

    // Dopo tre tentativi la versione resta all'amministratore
    $auto->run(fromCron: true);
    $auto->run(fromCron: true);

    expect($auto->run(fromCron: true))->toContain('too many attempts');
});

it('lets the administrator choose the automatic update level', function () {
    seedDemo();
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageSystem::class)
        ->callAction('autoUpdates', data: ['level' => AutoUpdate::PATCH])
        ->assertNotified(__('admin.messages.saved'));

    expect(app(AutoUpdate::class)->level())->toBe(AutoUpdate::PATCH);

    $this->get('/admin/system')->assertOk()->assertSee('Installa da solo le versioni di correzione')->assertSee('schedule:run');
});
