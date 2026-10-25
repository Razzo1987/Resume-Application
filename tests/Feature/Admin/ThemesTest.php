<?php

use App\Filament\Pages\ManageThemes;
use App\Models\User;
use App\Support\Settings\Settings;
use App\Themes\Compatibility;
use App\Themes\ThemeException;
use App\Themes\ThemeInstaller;
use App\Themes\ThemeManager;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

/*
| Temi installati da zip: si lavora in cartelle temporanee (temi + webroot), con il tema base
| collegato a quello vero, così i test non toccano themes/ e public/themes/ del progetto.
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
    ));
});

afterEach(function () {
    @unlink("{$this->sandbox}/themes/base");
    @unlink("{$this->sandbox}/public/themes/base");
    File::deleteDirectory($this->sandbox);
});

/**
 * Zip di un tema di prova: $extra aggiunge o sostituisce file, null li toglie.
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

    $upload = UploadedFile::fake()->createWithContent('demo-pro-1.0.0.zip', (string) file_get_contents(themeZip()));

    Livewire::test(ManageThemes::class)
        ->callAction('install', ['package' => $upload])
        ->assertNotified(__('admin.themes.installed', ['theme' => 'Demo PRO', 'version' => '1.0.0']))
        ->assertSee('Demo PRO');

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

    $upload = UploadedFile::fake()->createWithContent('bad.zip', (string) file_get_contents(themeZip(extra: ['dist/x.php' => '<?php'])));

    Livewire::test(ManageThemes::class)
        ->callAction('install', ['package' => $upload])
        ->assertNotified(__('admin.themes.errors.title'));

    expect(app(ThemeManager::class)->installed())->toBe(['base' => 'Base']);
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
