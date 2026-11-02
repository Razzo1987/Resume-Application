<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

it('redirects guests to the admin login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('shows the admin login in Italian', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Accedi');
});

it('loads only local assets in the admin (no CDN, no external fonts)', function () {
    $html = (string) $this->get('/admin/login')->getContent();

    preg_match_all('#(?:src|href)="(https?://[^/"]+)#', $html, $matches);

    $hosts = array_unique(array_map(fn (string $url): string => (string) parse_url($url, PHP_URL_HOST), $matches[1]));

    expect($hosts)->each->toBe('localhost')
        ->and($html)->not->toContain('fonts.bunny.net')
        ->and($html)->not->toContain('fonts.googleapis.com');
});

it('loads only local assets once logged in (avatar included)', function () {
    $html = (string) $this->actingAs(User::factory()->create(['name' => 'Luca Racchetti']))->get('/admin')->getContent();

    preg_match_all('#(?:src|href)="(https?://[^/"]+)#', $html, $matches);

    $hosts = array_unique(array_map(fn (string $url): string => (string) parse_url($url, PHP_URL_HOST), $matches[1]));

    expect($hosts)->each->toBe('localhost')
        ->and($html)->not->toContain('ui-avatars.com')
        ->and($html)->toContain('data:image/svg+xml;base64,');
});

it('lets an administrator open the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertOk();
});

it('uses session cookies only in the admin', function () {
    $response = $this->get('/admin/login');

    $names = array_map(fn ($cookie) => $cookie->getName(), $response->headers->getCookies());

    expect($names)->toContain(config('session.cookie'));
});

it('keeps the admin out of search engines', function () {
    $this->get('/admin/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('sends security headers on the admin pages: never inside a frame of another site', function (bool $signedIn) {
    if ($signedIn) {
        $this->actingAs(User::factory()->create());
    }

    $this->get($signedIn ? '/admin' : '/admin/login')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
})->with(['login' => false, 'dashboard' => true]);

it('sends guests to the login from every admin page and refuses uploads without a session', function () {
    $paths = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => in_array('GET', $route->methods(), true)
            && str_starts_with($route->uri(), 'admin')
            && ! str_contains($route->uri(), '{')
            && ! preg_match('#admin/(login|password-reset|multi-factor|email-verification)#', $route->uri()))
        ->map(fn ($route): string => '/'.$route->uri())
        ->values();

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        $this->get($path)->assertRedirect('/admin/login');
    }

    $status = $this->post('/admin/system/update-upload?id='.str_repeat('a', 32).'&index=0&count=1&name=x.zip', [], ['Accept' => 'application/json'])->status();

    expect($status)->toBeIn([401, 419]);
});

it('reminds the administrator to turn on two-step verification until it is on', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertSee('La verifica in due passaggi non è attiva');

    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $this->actingAs($user->fresh())->get('/admin')->assertDontSee('La verifica in due passaggi non è attiva');
});

it('hides the error details in production', function () {
    config(['app.debug' => false]);
    Route::get('/__boom', fn () => throw new RuntimeException('secret detail /home/user/core'));

    $this->get('/__boom')->assertStatus(500)->assertDontSee('secret detail')->assertDontSee('/home/user/core');
});
