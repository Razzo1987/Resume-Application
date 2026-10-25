<?php

use App\Models\User;

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
