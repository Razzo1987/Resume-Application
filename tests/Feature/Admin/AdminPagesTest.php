<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;

/*
| Ogni pagina dell'admin si apre senza errori, con i dati di esempio caricati:
| elenco, creazione e modifica di ogni risorsa, più le pagine singole (profilo, impostazioni).
| L'elenco delle risorse è letto dal filesystem perché i dataset vengono valutati prima dell'avvio dell'app.
*/

$resources = array_map(
    fn (string $file): string => 'App\\Filament\\Resources\\'.basename(dirname($file)).'\\'.basename($file, '.php'),
    glob(dirname(__DIR__, 3).'/app/Filament/Resources/*/*Resource.php') ?: [],
);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('uploads');
    $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');
});

it('opens the list, create and edit pages', function (string $resource) {
    $this->get($resource::getUrl('index'))->assertOk();

    if ($resource::hasPage('create')) {
        $this->get($resource::getUrl('create'))->assertOk();
    }

    if ($resource::hasPage('edit')) {
        $record = $resource::getEloquentQuery()->first();

        expect($record)->not->toBeNull("{$resource}: i dati di esempio non contengono record da modificare");

        $this->get($resource::getUrl('edit', ['record' => $record]))->assertOk();
    }
})->with($resources);

it('opens the dashboard, the profile and the settings pages', function (string $path) {
    $this->get('/admin'.$path)->assertOk();
})->with(['', '/profile-data', '/settings', '/system']);

it('shows the admin in English when configured', function () {
    config(['resume.admin_locale' => 'en']);

    $this->get('/admin/settings')
        ->assertOk()
        ->assertSee('Settings');
});
