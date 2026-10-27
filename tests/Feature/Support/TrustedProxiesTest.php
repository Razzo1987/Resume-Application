<?php

// Behind a proxy (Cloudflare, Codespaces) the public address arrives in the X-Forwarded-* headers

$forwarded = [
    'HTTP_X_FORWARDED_HOST' => 'cv.example.org',
    'HTTP_X_FORWARDED_PROTO' => 'https',
    'HTTP_X_FORWARDED_FOR' => '93.44.12.7',
];

it('ignores the forwarded headers when no proxy is trusted', function () use ($forwarded) {
    $this->withServerVariables($forwarded)->get('/admin')->assertRedirect();

    expect(session('url.intended'))->toBe('http://localhost/admin');
});

it('uses the public address of a trusted proxy (return after login)', function () use ($forwarded) {
    config(['trustedproxy.proxies' => '*']);

    $this->withServerVariables($forwarded)->get('/admin')->assertRedirect();

    expect(session('url.intended'))->toBe('https://cv.example.org/admin');
});

it('reads the visitor IP from a trusted proxy only', function () use ($forwarded) {
    Route::get('/_ip', fn () => request()->ip());

    expect($this->withServerVariables($forwarded)->get('/_ip')->getContent())->toBe('127.0.0.1');

    config(['trustedproxy.proxies' => '127.0.0.1']);

    expect($this->withServerVariables($forwarded)->get('/_ip')->getContent())->toBe('93.44.12.7');
});
