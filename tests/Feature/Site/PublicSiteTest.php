<?php

use App\Models\Profile;
use App\Site\Security\Csp;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => seedDemo());

dataset('public pages', [
    'CV (it)' => '/it-IT',
    'CV (en)' => '/en-GB',
    'experience page' => '/it-IT/esperienze/tech-lead-brightline-software',
    'project page' => '/en-GB/projects/booking-portal',
    'sitemap' => '/sitemap.xml',
    'robots' => '/robots.txt',
    'CV download' => '/it-IT/cv',
    'redirect' => '/',
    'not found' => '/it-IT/pagina-inesistente',
    'unknown route' => '/qualcosa/di/strano.php',
]);

it('never sets cookies on public pages', function (string $path) {
    $response = $this->get($path);

    expect($response->headers->getCookies())->toBeEmpty()
        ->and($response->headers->has('Set-Cookie'))->toBeFalse();
})->with('public pages');

it('strips cookies added by mistake on public routes', function () {
    Route::middleware('site')->get('/__cookie-probe', fn () => response('ok')->cookie('tracking', '1'));

    $response = $this->get('/__cookie-probe');

    $response->assertOk();
    expect($response->headers->getCookies())->toBeEmpty();
});

it('does not start a session on public routes', function () {
    Route::middleware('site')->get('/__session-probe', fn () => response(request()->hasSession() ? 'session' : 'none'));

    $this->get('/__session-probe')->assertSeeText('none');
});

it('renders the CV through the active theme', function () {
    $this->get('/it-IT')
        ->assertOk()
        ->assertSee('<html lang="it-IT">', escape: false)
        ->assertSee('<h1 class="profile-name">John Doe</h1>', escape: false)
        ->assertSee('/themes/base/assets/', escape: false)
        ->assertSee(__('site.a11y.skip_to_content'));
});

it('loads only local resources (no CDN, fonts or trackers)', function (string $path) {
    $html = (string) $this->get($path)->getContent();

    expect(resourceHosts($html))->each->toBe('localhost')
        ->and($html)->not->toContain('fonts.googleapis.com')
        ->and($html)->not->toContain('fonts.bunny.net');
})->with(['/it-IT', '/en-GB/projects/booking-portal', '/it-IT/pagina-inesistente']);

it('sends security headers with a strict content security policy', function () {
    $response = $this->get('/it-IT');

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'")
        ->toContain("style-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->not->toContain('unsafe-inline')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});

it('allows only the known inline script through its hash', function () {
    $html = (string) $this->get('/it-IT')->getContent();

    preg_match_all('#<script(?![^>]*(?:\ssrc=|application/ld\+json))[^>]*>(.*?)</script>#s', $html, $inline);

    expect($inline[1])->toBe([Csp::JS_FLAG]);
});

it('shows the theme 404 page in the language of the URL', function () {
    $this->get('/en-GB/does-not-exist')
        ->assertNotFound()
        ->assertSee('<html lang="en-GB">', escape: false)
        ->assertSee('Page not found')
        ->assertSee('<meta name="robots" content="noindex">', escape: false);

    $this->get('/qualcosa/di/strano.php')
        ->assertNotFound()
        ->assertSee('Pagina non trovata');
});

it('works before the profile is filled in', function () {
    Profile::query()->delete();

    $this->get('/it-IT')->assertOk();
});
