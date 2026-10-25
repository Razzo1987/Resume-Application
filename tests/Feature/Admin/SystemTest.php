<?php

use App\Filament\Pages\ManageSystem;
use App\Models\User;
use App\Support\Settings\Settings;
use App\Support\System\Backup;
use App\Support\System\Maintenance;
use App\Support\System\OrphanFiles;
use App\Support\System\Updater;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
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
