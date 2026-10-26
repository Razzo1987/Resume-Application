<?php

use App\Models\Profile;
use App\Models\User;
use App\Support\Install\EnvWriter;
use App\Support\Install\Installer;
use App\Support\Install\InstallException;
use App\Support\System\Htaccess;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->envPath = sys_get_temp_dir().'/resume-test-'.uniqid().'.env';
    config(['resume.installer.env_path' => $this->envPath]);
});

afterEach(fn () => @unlink($this->envPath));

function installData(array $overrides = []): array
{
    return [
        'url' => 'https://cv.example.com',
        'owner' => 'Mario Rossi',
        'email' => 'mario@example.com',
        'password' => 'una-password-lunga',
        'password_confirmation' => 'una-password-lunga',
        'admin_path' => 'gestione',
        'default_language' => 'it-IT',
        'languages' => ['en-GB'],
        // Il database dei test (phpunit.xml o variabili d'ambiente della CI)
        'db_host' => (string) config('database.connections.mysql.host'),
        'db_port' => (int) config('database.connections.mysql.port'),
        'db_database' => (string) config('database.connections.mysql.database'),
        'db_username' => (string) config('database.connections.mysql.username'),
        'db_password' => (string) config('database.connections.mysql.password'),
        ...$overrides,
    ];
}

it('sends every page to the installer until the app is installed', function () {
    $this->get('/it-IT')->assertRedirect('http://localhost/install');
    $this->get('/admin')->assertRedirect('http://localhost/install');
});

it('shows the requirements and the form in the browser language', function () {
    $this->get('/install', ['Accept-Language' => 'it-IT,it;q=0.9'])
        ->assertOk()
        ->assertSee('Installazione di Resume App')
        ->assertSee('ext-pdo_mysql')
        ->assertSee('name="db_database"', escape: false)
        ->assertHeaderMissing('Set-Cookie');

    $this->get('/install', ['Accept-Language' => 'en-US'])->assertSee('Resume App installation');
});

it('validates the form without a session', function () {
    $this->withHeader('Accept-Language', 'it')
        ->post('/install', installData(['password_confirmation' => 'diversa', 'admin_path' => 'Admin!', 'url' => 'non-un-url']))
        ->assertStatus(422)
        ->assertSee('Installazione non completata');
});

it('runs the installer and shows the admin address', function () {
    $this->mock(Installer::class)
        ->shouldReceive('install')
        ->once()
        ->withArgs(fn (array $data): bool => $data['default_language'] === 'it-IT'
            && $data['languages'] === ['en-GB']
            && $data['admin_path'] === 'gestione'
            && $data['force_https'] === true
            && $data['demo'] === false);

    $this->post('/install', installData(['force_https' => '1']))
        ->assertOk()
        ->assertSee('https://cv.example.com/gestione');
});

it('shows installer errors on the form', function () {
    $this->mock(Installer::class)->shouldReceive('install')->andThrow(new InstallException('Database non raggiungibile'));

    $this->post('/install', installData())
        ->assertStatus(422)
        ->assertSee('Database non raggiungibile')
        ->assertSee('value="Mario Rossi"', escape: false)
        ->assertDontSee('una-password-lunga');
});

it('installs on the existing database keeping its content', function () {
    seedDemo();
    $admin = User::factory()->create(['email' => 'mario@example.com', 'app_authentication_secret' => 'segreto']);

    $existing = app(Installer::class)->install([
        ...installData(),
        'admin_locale' => 'it',
        'force_https' => false,
        'strip_www' => false,
        'demo' => false,
    ]);

    $env = Dotenv::parse((string) file_get_contents($this->envPath));

    expect($existing)->toBeTrue()
        ->and(Profile::current()->full_name)->toBe('John Doe')
        ->and(User::query()->count())->toBe(1)
        ->and(Hash::check('una-password-lunga', (string) $admin->fresh()->password))->toBeTrue()
        ->and($admin->fresh()->app_authentication_secret)->toBeNull()
        ->and($env['APP_ENV'])->toBe('production')
        ->and($env['APP_URL'])->toBe('https://cv.example.com')
        ->and($env['RESUME_ADMIN_PATH'])->toBe('gestione')
        ->and($env['DB_DATABASE'])->toBe('resume_app_test')
        ->and($env['APP_KEY'])->toStartWith('base64:');
});

it('closes the installer once installed', function () {
    file_put_contents($this->envPath, "APP_KEY=x\n");

    $this->get('/install')->assertNotFound();
    $this->post('/install', installData())->assertNotFound();
});

it('writes a .env file that Laravel reads back', function () {
    app(EnvWriter::class)->write($this->envPath, [
        'APP_NAME' => 'Mario "Super" Rossi',
        'DB_PASSWORD' => 'p@ss w$rd#1',
        'APP_DEBUG' => false,
        '' => null,
        'SIMPLE' => 'base64:abc+/=',
    ]);

    $values = Dotenv::parse((string) file_get_contents($this->envPath));

    expect($values['APP_NAME'])->toBe('Mario "Super" Rossi')
        ->and($values['DB_PASSWORD'])->toBe('p@ss w$rd#1')
        ->and($values['APP_DEBUG'])->toBe('false')
        ->and($values['SIMPLE'])->toBe('base64:abc+/=')
        ->and(substr(sprintf('%o', fileperms($this->envPath)), -4))->toBe('0600');
});

it('writes the .htaccess block and keeps the lines of the hosting', function () {
    $path = sys_get_temp_dir().'/resume-htaccess-'.uniqid();
    file_put_contents($path, "# php -- BEGIN cPanel-generated handler\nAddHandler application/x-httpd-ea-php84 .php\n# php -- END cPanel-generated handler\n");
    $htaccess = app(Htaccess::class)->usePath($path);

    $htaccess->write(forceHttps: true, stripWww: false);
    $content = (string) file_get_contents($path);

    expect($content)->toStartWith(Htaccess::BEGIN)
        ->toContain('AddHandler application/x-httpd-ea-php84 .php')
        ->toContain("    RewriteCond %{HTTPS} !=on\n")
        ->toContain('#RewriteCond %{HTTP_HOST} ^www')
        ->and($htaccess->options())->toBe(['installed' => true, 'force_https' => true, 'strip_www' => false]);

    // Riscrittura: un solo blocco, opzioni aggiornate, righe di cPanel intatte
    $htaccess->write(forceHttps: false, stripWww: true);
    $content = (string) file_get_contents($path);

    expect(substr_count($content, Htaccess::BEGIN))->toBe(1)
        ->and($content)->toContain('AddHandler application/x-httpd-ea-php84 .php')
        ->and($htaccess->options())->toBe(['installed' => true, 'force_https' => false, 'strip_www' => true]);

    @unlink($path);
});
