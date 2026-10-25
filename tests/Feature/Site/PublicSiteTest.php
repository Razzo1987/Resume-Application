<?php

use Illuminate\Support\Facades\Route;

it('serves the public home page through the active theme', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<html lang="it-IT">', escape: false)
        ->assertSee('fa-solid fa-briefcase', escape: false)
        ->assertSee(__('site.a11y.skip_to_content'));
});

it('sets no cookies on the public home page', function () {
    $response = $this->get('/');

    expect($response->headers->getCookies())->toBeEmpty()
        ->and($response->headers->has('Set-Cookie'))->toBeFalse();
});

it('sets no cookies on the health check', function () {
    $response = $this->get('/up');

    $response->assertOk();
    expect($response->headers->getCookies())->toBeEmpty();
});

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

it('loads only local assets on the public home page', function () {
    $html = (string) $this->get('/')->getContent();

    preg_match_all('#(?:src|href)="(https?://[^/"]+)#', $html, $matches);

    $hosts = array_unique(array_map(fn (string $url): string => (string) parse_url($url, PHP_URL_HOST), $matches[1]));

    expect($hosts)->each->toBe('localhost');
});
