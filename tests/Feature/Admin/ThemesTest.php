<?php

use App\Filament\Pages\ManageThemes;
use App\Models\User;
use App\Support\Settings\Settings;
use App\Support\Update\UpdateStorage;
use App\Themes\Compatibility;
use App\Themes\ThemeException;
use App\Themes\ThemeInstaller;
use App\Themes\ThemeManager;
use App\Themes\ThemeUpdates;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
| Themes installed from a zip: the tests work in temporary folders (themes + webroot), with the base theme
| linked to the real one, so they do not touch the project's themes/ and public/themes/.
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->sandbox = sys_get_temp_dir().'/resume-themes-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$this->sandbox}/themes");
    File::ensureDirectoryExists("{$this->sandbox}/public/themes");
    symlink(base_path('themes/base'), "{$this->sandbox}/themes/base");
    symlink(public_path('themes/base'), "{$this->sandbox}/public/themes/base");

    $this->app->singleton(ThemeManager::class, fn (): ThemeManager => new ThemeManager(
        themesPath: "{$this->sandbox}/themes",
        activeName: (string) (app(Settings::class)->get('site.theme') ?: 'base'),
        baseName: 'base',
        publicPath: "{$this->sandbox}/public",
        activeVariant: app(Settings::class)->get('site.theme_variant'),
    ));

    // Check results and downloaded packages in the sandbox too
    $this->app->singleton(UpdateStorage::class, fn (): UpdateStorage => new UpdateStorage("{$this->sandbox}/storage"));
});

afterEach(function () {
    @unlink("{$this->sandbox}/themes/base");
    @unlink("{$this->sandbox}/public/themes/base");
    File::deleteDirectory($this->sandbox);
});

/**
 * Zip of a test theme: $extra adds or replaces files, null removes them.
 *
 * @param  array<string, mixed>  $manifest
 * @param  array<string, string|null>  $extra
 */
function themeZip(array $manifest = [], array $extra = [], string $folder = 'demo-pro'): string
{
    $manifest += [
        'name' => 'demo-pro',
        'title' => 'Demo PRO',
        'version' => '1.0.0',
        'extends' => 'base',
        'requires' => ['resume-app' => '^2.0'],
        'entrypoints' => ['themes/demo-pro/resources/scss/app.scss'],
    ];

    $files = array_replace([
        'theme.json' => json_encode($manifest),
        'icons.php' => "<?php return ['briefcase' => 'fal fa-briefcase'];",
        'views/partials/footer.blade.php' => '<footer>demo pro footer</footer>',
        'lang/it-IT/site.php' => "<?php return ['hello' => 'Ciao'];",
        'dist/manifest.json' => json_encode(['themes/demo-pro/resources/scss/app.scss' => ['file' => 'assets/app-123.css', 'src' => 'themes/demo-pro/resources/scss/app.scss', 'isEntry' => true]]),
        'dist/assets/app-123.css' => 'body{color:red}',
        'dist/assets/lato-400.woff2' => 'font',
    ], $extra);

    $path = sys_get_temp_dir().'/theme-'.bin2hex(random_bytes(4)).'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);

    foreach (array_filter($files, fn (?string $content): bool => $content !== null) as $name => $content) {
        $zip->addFromString(($folder !== '' ? "{$folder}/" : '').$name, $content);
    }

    $zip->close();

    return $path;
}

it('installs a theme from its zip, with assets in the web root', function () {
    $result = app(ThemeInstaller::class)->install(themeZip());

    expect($result['theme']->name)->toBe('demo-pro')
        ->and($result['updated'])->toBeFalse()
        ->and("{$this->sandbox}/themes/demo-pro/views/partials/footer.blade.php")->toBeFile()
        ->and("{$this->sandbox}/themes/demo-pro/lang/it-IT/site.php")->toBeFile()
        ->and("{$this->sandbox}/public/themes/demo-pro/manifest.json")->toBeFile()
        ->and("{$this->sandbox}/public/themes/demo-pro/assets/app-123.css")->toBeFile()
        ->and("{$this->sandbox}/themes/demo-pro/dist")->not->toBeDirectory()
        ->and(app(ThemeManager::class)->installed())->toBe(['base' => 'Base', 'demo-pro' => 'Demo PRO'])
        ->and(glob("{$this->sandbox}/themes/*.new-*") ?: [])->toBe([]);
});

it('updates an installed theme, removing the files of the old version', function () {
    app(ThemeInstaller::class)->install(themeZip(extra: ['dist/assets/old-1.css' => 'old']));

    $result = app(ThemeInstaller::class)->install(themeZip(['version' => '1.1.0']));

    expect($result['updated'])->toBeTrue()
        ->and($result['previous'])->toBe('1.0.0')
        ->and($result['theme']->version)->toBe('1.1.0')
        ->and("{$this->sandbox}/public/themes/demo-pro/assets/old-1.css")->not->toBeFile()
        ->and(glob("{$this->sandbox}/public/themes/*.old-*") ?: [])->toBe([]);
});

it('accepts a zip without the main folder', function () {
    expect(app(ThemeInstaller::class)->inspect(themeZip(folder: ''))->name)->toBe('demo-pro');
});

it('rejects packages that are not valid themes', function (array $manifest, array $extra, string $folder) {
    $zip = themeZip($manifest, $extra, $folder);

    expect(fn () => app(ThemeInstaller::class)->install($zip))->toThrow(ThemeException::class);
    expect("{$this->sandbox}/themes/demo-pro")->not->toBeDirectory()
        ->and("{$this->sandbox}/public/themes/demo-pro")->not->toBeDirectory();
})->with([
    'PHP among the public assets' => [[], ['dist/assets/shell.php' => '<?php echo 1;'], 'demo-pro'],
    'htaccess among the assets' => [[], ['dist/.htaccess' => 'Options +ExecCGI'], 'demo-pro'],
    'path traversal' => [[], ['views/../../evil.blade.php' => 'x'], 'demo-pro'],
    'unexpected PHP file' => [[], ['app/Backdoor.php' => '<?php'], 'demo-pro'],
    'no compiled assets' => [[], ['dist/manifest.json' => null], 'demo-pro'],
    'entrypoint not compiled' => [['entrypoints' => ['themes/demo-pro/missing.js']], [], 'demo-pro'],
    'folder not matching the name' => [[], [], 'other-name'],
    'the base theme' => [['name' => 'base'], [], 'base'],
    'invalid name' => [['name' => 'Demo PRO'], [], ''],
    'missing parent theme' => [['extends' => 'missing'], [], 'demo-pro'],
    'incompatible version' => [['requires' => ['resume-app' => '^3.0']], [], 'demo-pro'],
    'no name' => [['name' => null], [], ''],
]);

it('rejects files that are not zip archives', function () {
    $path = sys_get_temp_dir().'/not-a-zip-'.bin2hex(random_bytes(4)).'.zip';
    file_put_contents($path, 'hello');

    expect(fn () => app(ThemeInstaller::class)->install($path))->toThrow(ThemeException::class);
});

it('uses the installed theme only while its assets are there', function () {
    app(ThemeInstaller::class)->install(themeZip());
    app(Settings::class)->set('site.theme', 'demo-pro');
    app()->forgetInstance(ThemeManager::class);

    expect(app(ThemeManager::class)->active()->name)->toBe('demo-pro');

    File::deleteDirectory("{$this->sandbox}/public/themes/demo-pro");
    app(ThemeManager::class)->forget();

    expect(app(ThemeManager::class)->active()->name)->toBe('base');
});

it('removes a theme that is not in use, never the active or the base one', function () {
    app(ThemeInstaller::class)->install(themeZip());
    app(Settings::class)->set('site.theme', 'demo-pro');
    app()->forgetInstance(ThemeManager::class);

    expect(fn () => app(ThemeInstaller::class)->remove('demo-pro'))->toThrow(ThemeException::class)
        ->and(fn () => app(ThemeInstaller::class)->remove('base'))->toThrow(ThemeException::class);

    app(Settings::class)->set('site.theme', null);
    app()->forgetInstance(ThemeManager::class);
    app(ThemeInstaller::class)->remove('demo-pro');

    expect("{$this->sandbox}/themes/demo-pro")->not->toBeDirectory()
        ->and("{$this->sandbox}/public/themes/demo-pro")->not->toBeDirectory();
});

it('installs, activates and removes themes from the admin page', function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    $this->get('/admin/themes')->assertOk()->assertSee('Base');

    // The modal uploads the zip in chunks (UpdateUploadController, target=theme), then calls installUploaded()
    $uploads = app(UpdateStorage::class)->themeUploadsDirectory();
    File::ensureDirectoryExists($uploads);
    copy(themeZip(), "{$uploads}/demo-pro-1.0.0.zip");

    Livewire::test(ManageThemes::class)
        ->mountAction('install')
        ->call('installUploaded', 'demo-pro-1.0.0.zip')
        ->assertNotified(__('admin.themes.installed', ['theme' => 'Demo PRO', 'version' => '1.0.0']))
        ->assertSee('Demo PRO');

    expect("{$uploads}/demo-pro-1.0.0.zip")->not->toBeFile();
    copy(themeZip(), "{$uploads}/demo-pro-1.0.0.zip");

    Livewire::test(ManageThemes::class)
        ->call('installUploaded', 'demo-pro-1.0.0.zip')
        ->assertNotified(__('admin.themes.reinstalled', ['theme' => 'Demo PRO', 'version' => '1.0.0']));

    Livewire::test(ManageThemes::class)
        ->callAction('activate', arguments: ['theme' => 'demo-pro']);

    expect(app(Settings::class)->get('site.theme'))->toBe('demo-pro');

    app(Settings::class)->set('site.theme', null);
    app()->forgetInstance(ThemeManager::class);

    Livewire::test(ManageThemes::class)
        ->callAction('remove', arguments: ['theme' => 'demo-pro'])
        ->assertNotified(__('admin.themes.removed'));

    expect(app(ThemeManager::class)->installed())->toBe(['base' => 'Base']);
});

it('shows why an upload is refused', function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    $uploads = app(UpdateStorage::class)->themeUploadsDirectory();
    File::ensureDirectoryExists($uploads);
    copy(themeZip(extra: ['dist/x.php' => '<?php']), "{$uploads}/bad.zip");

    Livewire::test(ManageThemes::class)
        ->call('installUploaded', 'bad.zip')
        ->assertNotified(__('admin.themes.errors.title'))
        ->call('installUploaded', '../../.env')
        ->assertNotified(__('admin.themes.errors.title'));

    expect(app(ThemeManager::class)->installed())->toBe(['base' => 'Base']);
});

it('receives theme packages in chunks, next to the update packages but not among them', function () {
    $this->actingAs(User::factory()->create());
    $zip = (string) file_get_contents(themeZip());
    $half = intdiv(strlen($zip), 2);
    $id = str_repeat('ab', 16);

    foreach ([substr($zip, 0, $half), substr($zip, $half)] as $index => $chunk) {
        $this->call('POST', "/admin/system/update-upload?target=theme&id={$id}&index={$index}&count=2&name=folio-1.0.0.zip", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], $chunk)
            ->assertOk();
    }

    $storage = app(UpdateStorage::class);

    expect(file_get_contents($storage->themeUploadsDirectory().'/folio-1.0.0.zip'))->toBe($zip)
        ->and($storage->packages())->toBe([]);
});

it('checks version constraints', function (string $version, string $constraint, bool $expected) {
    expect(Compatibility::satisfies($version, $constraint))->toBe($expected);
})->with([
    ['2.0.0-dev', '^2.0', true],
    ['2.3.1', '^2.0', true],
    ['3.0.0', '^2.0', false],
    ['1.9.9', '^2.0', false],
    ['2.1.4', '~2.1', true],
    ['3.0.0', '~2.1', false],
    ['2.1.4', '~2.1.0', true],
    ['2.2.0', '~2.1.0', false],
    ['2.0.0', '>=2.0', true],
    ['1.0.0', '>=2.0', false],
    ['2.1.7', '2.1.*', true],
    ['2.2.0', '2.1.*', false],
    ['2.0.0', '2.0.0', true],
    ['3.1.0', '^2.0 || ^3.0', true],
    ['2.0.0', '*', true],
    ['dev', '^9.0', true],
]);

// --- Variants ------------------------------------------------------------------------------------------

/**
 * Manifest and compiled assets of a theme with two variants (light, default, and dark).
 *
 * @return array{0: array<string, mixed>, 1: array<string, string>}
 */
function variantTheme(string $name = 'demo-pro', string $version = '1.0.0'): array
{
    $entries = [
        "themes/{$name}/resources/js/app.js" => 'assets/app-1.js',
        "themes/{$name}/resources/scss/light.scss" => 'assets/light-1.css',
        "themes/{$name}/resources/scss/dark.scss" => 'assets/dark-1.css',
    ];

    return [
        [
            'name' => $name,
            'title' => ucfirst($name),
            'version' => $version,
            'entrypoints' => ["themes/{$name}/resources/js/app.js"],
            'theme_color' => '#111111',
            'default_variant' => 'light',
            'variants' => [
                'light' => ['title' => 'Light', 'entrypoints' => ["themes/{$name}/resources/scss/light.scss"]],
                'dark' => ['title' => 'Dark', 'entrypoints' => ["themes/{$name}/resources/scss/dark.scss"], 'theme_color' => '#000000'],
                'Bad Name' => ['title' => 'ignored'],
            ],
            'updates' => ['repository' => 'acme/resume-themes', 'tag_prefix' => "{$name}-v"],
        ],
        [
            'dist/manifest.json' => json_encode(array_map(fn (string $file): array => ['file' => $file, 'isEntry' => true], $entries)),
            'dist/assets/app-1.js' => 'console.log(1)',
            'dist/assets/light-1.css' => 'body{color:#111}',
            'dist/assets/dark-1.css' => 'body{color:#eee}',
            'dist/assets/app-123.css' => null,
        ],
    ];
}

it('uses the variant chosen in the admin, the default one otherwise', function () {
    [$manifest, $files] = variantTheme();
    app(ThemeInstaller::class)->install(themeZip($manifest, $files));

    app(Settings::class)->setMany(['site.theme' => 'demo-pro', 'site.theme_variant' => 'dark']);
    app()->forgetInstance(ThemeManager::class);
    $active = app(ThemeManager::class)->active();

    expect($active->variant)->toBe('dark')
        ->and($active->entrypoints)->toBe(['themes/demo-pro/resources/js/app.js', 'themes/demo-pro/resources/scss/dark.scss'])
        ->and($active->themeColor)->toBe('#000000')
        ->and(array_keys($active->variants))->toBe(['light', 'dark'])
        ->and($active->updateRepository)->toBe('acme/resume-themes')
        ->and($active->updateTagPrefix)->toBe('demo-pro-v');

    foreach ([null, 'unknown'] as $variant) {
        app(Settings::class)->set('site.theme_variant', $variant);
        app()->forgetInstance(ThemeManager::class);
        $active = app(ThemeManager::class)->active();

        expect($active->variant)->toBe('light')
            ->and($active->entrypoints)->toContain('themes/demo-pro/resources/scss/light.scss')
            ->and($active->themeColor)->toBe('#111111');
    }
});

it('refuses a theme whose variants are not compiled', function () {
    [$manifest, $files] = variantTheme();
    $files['dist/manifest.json'] = json_encode(['themes/demo-pro/resources/js/app.js' => ['file' => 'assets/app-1.js']]);

    expect(fn () => app(ThemeInstaller::class)->install(themeZip($manifest, $files)))->toThrow(ThemeException::class);
});

it('activates a theme variant from the admin page', function () {
    [$manifest, $files] = variantTheme();
    app(ThemeInstaller::class)->install(themeZip($manifest, $files));

    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageThemes::class)
        ->assertSee('Light')
        ->assertSee('Dark')
        ->callAction('activate', arguments: ['theme' => 'demo-pro', 'variant' => 'dark'])
        ->assertNotified(__('admin.themes.activated_variant', ['theme' => 'Demo-pro', 'variant' => 'Dark']));

    expect(app(Settings::class)->get('site.theme'))->toBe('demo-pro')
        ->and(app(Settings::class)->get('site.theme_variant'))->toBe('dark');
});

// --- Packages with several themes ----------------------------------------------------------------------

/**
 * Zip with several themes, each in its own folder.
 *
 * @param  list<array{0: array<string, mixed>, 1: array<string, string|null>}>  $themes
 */
function bundleZip(array $themes): string
{
    $path = sys_get_temp_dir().'/themes-'.bin2hex(random_bytes(4)).'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);

    foreach ($themes as [$manifest, $extra]) {
        $single = new ZipArchive;
        $single->open(themeZip($manifest, $extra, folder: $manifest['name']));

        for ($index = 0; $index < $single->numFiles; $index++) {
            $zip->addFromString((string) $single->getNameIndex($index), (string) $single->getFromIndex($index));
        }

        $single->close();
    }

    $zip->close();

    return $path;
}

it('installs every theme of a package with several themes', function () {
    [$folio, $folioFiles] = variantTheme('folio');
    [$bento, $bentoFiles] = variantTheme('bento');
    $bento['extends'] = 'folio'; // a parent in the same package is installed first

    $results = app(ThemeInstaller::class)->installAll(bundleZip([[$bento, $bentoFiles], [$folio, $folioFiles]]));

    expect(array_map(fn (array $result): string => $result['theme']->name, $results))->toBe(['folio', 'bento'])
        ->and(app(ThemeManager::class)->installed())->toBe(['base' => 'Base', 'bento' => 'Bento', 'folio' => 'Folio'])
        ->and("{$this->sandbox}/public/themes/bento/assets/dark-1.css")->toBeFile()
        ->and(app(ThemeInstaller::class)->inspectAll(bundleZip([[$folio, $folioFiles]])))->toHaveCount(1);
});

it('refuses a package with several themes if one of them is not valid', function () {
    [$folio, $folioFiles] = variantTheme('folio');
    [$bento, $bentoFiles] = variantTheme('bento');
    $bentoFiles['secret.php'] = '<?php';

    expect(fn () => app(ThemeInstaller::class)->installAll(bundleZip([[$folio, $folioFiles], [$bento, $bentoFiles]])))->toThrow(ThemeException::class)
        ->and(app(ThemeManager::class)->installed())->toBe(['base' => 'Base']);
});

// --- Updates from GitHub ---------------------------------------------------------------------------------

/**
 * A release of a theme as returned by the GitHub API.
 *
 * @return array<string, mixed>
 */
function themeRelease(string $name, string $version, bool $draft = false): array
{
    return [
        'tag_name' => "{$name}-v{$version}",
        'name' => "{$name} {$version}",
        'draft' => $draft,
        'prerelease' => false,
        'body' => 'Novità',
        'html_url' => "https://github.com/acme/resume-themes/releases/tag/{$name}-v{$version}",
        'published_at' => '2026-11-02T10:00:00Z',
        'assets' => [
            ['id' => 1, 'name' => "{$name}-{$version}.zip", 'size' => 2000, 'url' => "https://api.github.com/repos/acme/resume-themes/releases/assets/{$name}-{$version}", 'browser_download_url' => "https://github.com/acme/resume-themes/releases/download/{$name}-v{$version}/{$name}-{$version}.zip"],
            ['id' => 2, 'name' => "{$name}-{$version}.zip.sha256", 'size' => 90, 'url' => "https://api.github.com/repos/acme/resume-themes/releases/assets/{$name}-{$version}-sha", 'browser_download_url' => "https://github.com/acme/resume-themes/releases/download/{$name}-v{$version}/{$name}-{$version}.zip.sha256"],
        ],
    ];
}

it('finds, announces and installs theme updates published on GitHub', function () {
    [$manifest, $files] = variantTheme();
    app(ThemeInstaller::class)->install(themeZip($manifest, $files));
    User::factory()->create(['email' => 'admin@example.com']);

    [$next, $nextFiles] = variantTheme(version: '1.2.0');
    $package = (string) file_get_contents(themeZip($next, $nextFiles));
    $calls = 0;

    Http::fake(function (Request $request) use ($package, &$calls) {
        if (str_contains($request->url(), '/releases?')) {
            $calls++;

            return $request->hasHeader('If-None-Match')
                ? Http::response(null, 304)
                : Http::response([
                    themeRelease('demo-pro', '1.1.0'),
                    themeRelease('demo-pro', '1.2.0'),
                    themeRelease('demo-pro', '2.0.0', draft: true),
                    themeRelease('other', '9.0.0'),
                ], 200, ['ETag' => '"abc"']);
        }

        return str_ends_with($request->url(), '-sha') || str_ends_with($request->url(), '.sha256')
            ? Http::response(hash('sha256', $package).'  demo-pro-1.2.0.zip')
            : Http::response($package);
    });

    $updates = app(ThemeUpdates::class);

    expect($updates->run(true))->toBe('themes available demo-pro 1.2.0')
        ->and($updates->run(true))->toBe('themes available demo-pro 1.2.0') // 304: same answer, one email only
        ->and($calls)->toBe(2)
        ->and($updates->available()['demo-pro']->version)->toBe('1.2.0');

    $mails = app('mail.manager')->mailer()->getSymfonyTransport()->messages()->all();

    expect($mails)->toHaveCount(1)
        ->and($mails[0]->getOriginalMessage()->getSubject())->toContain('tema Demo-pro: disponibile la versione 1.2.0')
        ->and($mails[0]->getOriginalMessage()->getTextBody())->toContain('installata: 1.0.0');

    $result = $updates->update('demo-pro');

    expect($result['previous'])->toBe('1.0.0')
        ->and($result['theme']->version)->toBe('1.2.0')
        ->and($updates->available())->toBe([])
        ->and(glob("{$this->sandbox}/storage/app/updates/packages/*") ?: [])->toBe([]);
});

it('explains that a private repository of themes needs the token', function () {
    [$manifest, $files] = variantTheme();
    app(ThemeInstaller::class)->install(themeZip($manifest, $files));

    Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

    expect(app(ThemeUpdates::class)->run(true))->toBe(__('admin.themes.updates.errors.private'))
        ->and(app(ThemeUpdates::class)->available())->toBe([]);
});

it('has nothing to check without themes that declare updates', function () {
    Http::fake();

    expect(app(ThemeUpdates::class)->run(true))->toBe('no themes');

    Http::assertNothingSent();
});
