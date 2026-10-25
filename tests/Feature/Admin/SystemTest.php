<?php

use App\Filament\Pages\ManageSystem;
use App\Models\Experience;
use App\Models\PageSection;
use App\Models\Profile;
use App\Models\User;
use App\Support\Settings\Settings;
use App\Support\System\Backup;
use App\Support\System\Maintenance;
use App\Support\System\OrphanFiles;
use App\Support\System\Updater;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedDemo();
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');
});

afterEach(fn () => app(Maintenance::class)->off());

it('puts only the public site in maintenance', function () {
    Livewire::test(ManageSystem::class)->callAction('maintenance');

    expect(app(Maintenance::class)->isOn())->toBeTrue();

    $this->get('/it-IT')
        ->assertStatus(503)
        ->assertHeader('Retry-After', '600')
        ->assertSee('Sito in aggiornamento');

    $this->get('/admin')->assertOk();

    Livewire::test(ManageSystem::class)->callAction('maintenance');

    $this->get('/it-IT')->assertOk();
});

it('completes an update: migrations, caches and version', function () {
    app(Settings::class)->set(Updater::VERSION_KEY, '1.9.0');

    $this->get('/admin')->assertSee(__('admin.system.alerts.update_pending'));

    Livewire::test(ManageSystem::class)
        ->callAction('completeUpdate')
        ->assertNotified(__('admin.system.update_completed'));

    expect(app(Updater::class)->isPending())->toBeFalse();
    $this->get('/admin')->assertDontSee(__('admin.system.alerts.update_pending'));
});

it('exports the content without users and secrets', function () {
    $path = app(Backup::class)->create();

    $zip = new ZipArchive;
    $zip->open($path);
    $data = json_decode((string) $zip->getFromName('database.json'), true);
    $names = collect(range(0, $zip->numFiles - 1))->map(fn (int $index): string => (string) $zip->getNameIndex($index));
    $zip->close();
    @unlink($path);

    expect($data['tables'])->toHaveKeys(['profiles', 'experiences', 'languages', 'settings'])
        ->not->toHaveKey('users')
        ->not->toHaveKey('sessions')
        ->and($names->contains(fn (string $name): bool => str_starts_with($name, 'cv/cv_it-IT_')))->toBeTrue()
        ->and($names->contains(fn (string $name): bool => str_starts_with($name, 'uploads/profile/')))->toBeTrue();
});

it('offers the download of the export', function () {
    Livewire::test(ManageSystem::class)
        ->callAction('backup')
        ->assertFileDownloaded();
});

it('removes only the files left over by older versions, with their empty folders', function () {
    $web = sys_get_temp_dir().'/orphans-'.bin2hex(random_bytes(4));
    $files = [
        'core/app/Kept.php' => true,
        'core/app/Legacy/Deep/OldThing.php' => false,
        'core/app/Legacy/Deep/Other.php' => false,
        'themes/base/assets/app-NEW.css' => true,
        'themes/base/assets/app-OLD.css' => false,
        'uploads/photo.jpg' => null,          // dati: mai considerati
        'core/storage/app/cv/cv.pdf' => null,
    ];

    foreach (array_keys($files) as $file) {
        @mkdir(dirname("{$web}/{$file}"), 0777, true);
        file_put_contents("{$web}/{$file}", $file);
    }

    $manifest = collect($files)->filter(fn (?bool $known): bool => $known === true)->map(fn (): string => 'hash')->all();
    file_put_contents("{$web}/core/manifest.json", json_encode(['files' => $manifest]));

    $orphans = new OrphanFiles("{$web}/core");

    expect($orphans->find())->toBe([
        'core/app/Legacy/Deep/OldThing.php',
        'core/app/Legacy/Deep/Other.php',
        'themes/base/assets/app-OLD.css',
    ]);

    // Un file non orfano passato per errore non viene cancellato
    expect($orphans->delete(['core/app/Legacy/Deep/OldThing.php', 'core/app/Kept.php', 'uploads/photo.jpg']))->toBe(1)
        ->and(is_dir("{$web}/core/app/Legacy/Deep"))->toBeTrue();

    expect($orphans->delete($orphans->find()))->toBe(2)
        ->and($orphans->find())->toBe([])
        ->and(is_dir("{$web}/core/app/Legacy"))->toBeFalse()
        ->and(is_dir("{$web}/core/app"))->toBeTrue()
        ->and(is_dir("{$web}/themes/base/assets"))->toBeTrue()
        ->and(is_file("{$web}/uploads/photo.jpg"))->toBeTrue()
        ->and(is_file("{$web}/core/storage/app/cv/cv.pdf"))->toBeTrue();

    File::deleteDirectory($web);
});

it('restores an export, keeping users and the installed version', function () {
    app(Settings::class)->set(Updater::VERSION_KEY, '2.0.0');
    $export = app(Backup::class)->create();
    $photo = Profile::current()->photo;
    $experiences = Experience::query()->count();

    // Contenuti cambiati dopo l'esportazione
    Experience::query()->get()->each->delete();
    Profile::current()->update(['full_name' => 'Altro Nome']);
    Storage::disk('uploads')->delete($photo);
    app(Settings::class)->set(Updater::VERSION_KEY, '2.0.1');
    $users = User::query()->count();

    $result = app(Backup::class)->restore($export);

    expect(Experience::query()->count())->toBe($experiences)
        ->and(Profile::current()->full_name)->toBe('John Doe')
        ->and(User::query()->count())->toBe($users)
        ->and(app(Settings::class)->get(Updater::VERSION_KEY))->toBe('2.0.1')
        ->and($result['files'])->toBeGreaterThan(0)
        ->and(storage_path('app/private/backups/'.$result['backup']))->toBeFile();

    Storage::disk('uploads')->assertExists($photo);
    $this->get('/it-IT')->assertOk()->assertSee('John Doe');

    @unlink($export);
    @unlink(storage_path('app/private/backups/'.$result['backup']));
});

it('repeats on an older export the data changes of the migrations it did not have', function () {
    $export = app(Backup::class)->create();

    // Esportazione "di una 2.0.1": senza la migrazione della voce Blog, con la sezione fuori dal menu
    $zip = new ZipArchive;
    $zip->open($export);
    $data = json_decode((string) $zip->getFromName('database.json'), true);
    $data['tables']['migrations'] = array_values(array_filter($data['tables']['migrations'], fn (array $row): bool => $row['migration'] !== '2026_10_26_000100_blog_menu_entry'));
    $data['tables']['page_sections'] = array_map(fn (array $row): array => $row['key'] === 'latest_articles' ? ['in_menu' => 0] + $row : $row, $data['tables']['page_sections']);
    $zip->addFromString('database.json', (string) json_encode($data));
    $zip->close();

    $result = app(Backup::class)->restore($export);

    expect(PageSection::query()->where('key', 'latest_articles')->value('in_menu'))->toBeTruthy();

    // Un'esportazione della stessa versione resta com'è
    PageSection::query()->where('key', 'latest_articles')->first()->update(['in_menu' => false]);
    $current = app(Backup::class)->create();
    $second = app(Backup::class)->restore($current);

    expect(PageSection::query()->where('key', 'latest_articles')->value('in_menu'))->toBeFalsy();

    foreach ([$export, $current, storage_path('app/private/backups/'.$result['backup']), storage_path('app/private/backups/'.$second['backup'])] as $file) {
        @unlink($file);
    }
});

it('refuses exports that are not valid or come from a newer version', function (array $entries) {
    $path = sys_get_temp_dir().'/restore-'.bin2hex(random_bytes(4)).'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);

    foreach ($entries as $name => $content) {
        $zip->addFromString($name, $content);
    }

    $zip->close();
    $before = Experience::query()->count();

    expect(fn () => app(Backup::class)->restore($path))->toThrow(RuntimeException::class)
        ->and(Experience::query()->count())->toBe($before);

    @unlink($path);
})->with([
    'no database' => [['uploads/a.webp' => 'x']],
    'newer schema' => [['database.json' => json_encode(['tables' => ['migrations' => [['migration' => '2099_01_01_000000_future']], 'experiences' => []]])]],
    'path traversal' => [['database.json' => json_encode(['tables' => []]), 'uploads/../../.env' => 'x']],
    'other folder' => [['database.json' => json_encode(['tables' => []]), 'core/app.php' => '<?php']],
]);

it('imports an export from the system page', function () {
    $export = app(Backup::class)->create();
    Experience::query()->get()->each->delete();

    Livewire::test(ManageSystem::class)
        ->callAction('restore', ['package' => UploadedFile::fake()->createWithContent('export.zip', (string) file_get_contents($export))])
        ->assertNotified();

    expect(Experience::query()->count())->toBeGreaterThan(0);

    @unlink($export);
    array_map('unlink', glob(storage_path('app/private/backups/resume-backup-*.zip')) ?: []);
});

it('imports an export already on the server, chosen from the list', function () {
    $export = app(Backup::class)->create();
    $name = basename($export);
    Experience::query()->get()->each->delete();

    expect(app(Backup::class)->available())->toHaveKey($name)
        ->and(app(Backup::class)->find('../'.$name))->toBeNull();

    Livewire::test(ManageSystem::class)
        ->callAction('restore', ['existing' => $name])
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect(Experience::query()->count())->toBeGreaterThan(0);

    array_map('unlink', glob(storage_path('app/private/backups/resume-backup-*.zip')) ?: []);
});
