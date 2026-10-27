<?php

use App\Models\User;
use App\Support\Cron\Cron;
use App\Support\Cron\CronJobs;
use App\Support\Update\CronStop;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->storage = sys_get_temp_dir().'/resume-cron-'.bin2hex(random_bytes(4));
    $this->cron = new Cron($this->storage);
    $this->runs = new ArrayObject;
});

afterEach(function () {
    File::deleteDirectory($this->storage);
});

function job(string $name): Closure
{
    return function (bool $fromCron) use ($name): string {
        test()->runs->append($name.($fromCron ? ':cron' : ':admin'));

        return 'done';
    };
}

it('runs the jobs that are due and remembers when', function () {
    $this->cron->register('often', 5, light: true, run: job('often'));
    $this->cron->register('daily', 24 * 60, light: false, run: job('daily'));

    expect($this->cron->run(fromCron: true))->toBe(['often' => 'done', 'daily' => 'done']);

    $this->travel(10)->minutes();

    expect($this->cron->run(fromCron: true))->toBe(['often' => 'done'])
        ->and($this->runs->getArrayCopy())->toBe(['often:cron', 'daily:cron', 'often:cron'])
        ->and($this->cron->jobs()['daily']['at'])->not->toBeNull()
        ->and($this->cron->isActive())->toBeTrue()
        ->and($this->cron->cronPhpVersion())->toBe(PHP_VERSION);

    $this->travel(Cron::ACTIVE_MINUTES + 1)->minutes();

    expect($this->cron->isActive())->toBeFalse();
});

it('runs only the light jobs from the admin, at most once an hour, when there is no cron job', function () {
    $this->cron->register('light', 5, light: true, run: job('light'));
    $this->cron->register('heavy', 5, light: false, run: job('heavy'));

    expect($this->cron->run(fromCron: false))->toBe(['light' => 'done'])
        ->and($this->cron->isActive())->toBeFalse();

    // Il giro dall'admin parte dopo aver risposto (terminating) e non più di una volta l'ora
    expect($this->cron->runFromAdminIfNeeded())->toBeFalse();

    $this->travel(Cron::FALLBACK_MINUTES + 1)->minutes();

    expect($this->cron->runFromAdminIfNeeded())->toBeTrue();

    $this->app->terminate();

    expect($this->runs->getArrayCopy())->toBe(['light:admin', 'light:admin'])
        ->and($this->cron->runFromAdminIfNeeded())->toBeFalse();

    // Con il cron attivo l'admin non fa partire niente
    $this->cron->run(fromCron: true, only: 'heavy');
    $this->travel(Cron::FALLBACK_MINUTES + 1)->minutes();

    expect($this->cron->runFromAdminIfNeeded())->toBeFalse();
});

it('records a failure without stopping the other jobs', function () {
    $this->cron->register('broken', 5, light: true, run: fn () => throw new RuntimeException('GitHub is down'));
    $this->cron->register('next', 5, light: true, run: job('next'));

    expect($this->cron->run(fromCron: true))->toBe(['broken' => 'error: GitHub is down', 'next' => 'done'])
        ->and($this->cron->jobs()['broken']['ok'])->toBeFalse();
});

it('stops the round right after the application files were replaced', function () {
    $this->cron->register('updates', 5, light: true, run: fn () => throw new CronStop('extracted 2.0.9'));
    $this->cron->register('next', 5, light: true, run: job('next'));

    expect($this->cron->run(fromCron: true))->toBe(['updates' => 'extracted 2.0.9'])
        ->and($this->runs->getArrayCopy())->toBe([]);
});

it('runs one round at a time', function () {
    $this->cron->register('job', 5, light: true, run: job('job'));
    File::ensureDirectoryExists($this->cron->directory());
    $lock = fopen($this->cron->directory().'/cron.lock', 'c');
    flock($lock, LOCK_EX);

    expect($this->cron->run(fromCron: true))->toBe(['cron' => 'busy']);

    flock($lock, LOCK_UN);
    fclose($lock);
});

it('suggests the cron command with the PHP of the command line', function () {
    expect($this->cron->command())->toStartWith('*/5 * * * * '.PHP_BINARY)->toEndWith('artisan schedule:run >/dev/null 2>&1');
});

it('remembers the PHP of the web server', function () {
    $this->cron->rememberWebRuntime();

    expect($this->cron->webRuntime())->php->toBe(PHP_VERSION)->extensions->toContain('zip');
});

it('runs the jobs from schedule:run and resume:cron', function () {
    CronJobs::register($this->cron);
    $this->app->instance(Cron::class, $this->cron);

    Artisan::call('schedule:run');

    expect($this->cron->isActive())->toBeTrue()
        ->and($this->cron->jobs()['cleanup']['message'])->toBe('deleted 0')
        ->and($this->cron->jobs()['updates']['message'])->toBe('development copy');

    expect(Artisan::call('resume:cron', ['--job' => 'cleanup']))->toBe(0)
        ->and(Artisan::call('resume:cron', ['--job' => 'nothing']))->toBe(1);
});

it('shows the cron status and the command to add on the system page', function () {
    $this->app->instance(Cron::class, tap($this->cron, fn (Cron $cron) => CronJobs::register($cron)));
    $this->actingAs(User::factory()->create());

    $this->get('/admin/system')
        ->assertOk()
        ->assertSee('Lavori programmati (cron)')
        ->assertSee('non configurato')
        ->assertSee('schedule:run')
        ->assertSee('Nuove versioni e aggiornamenti automatici');

    $this->cron->run(fromCron: true);

    $this->get('/admin/system')->assertOk()->assertSee('attivo')->assertDontSee('Come attivarlo');
});
