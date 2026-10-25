<?php

use App\Models\Profile;
use App\Site\Cache\PageCache;
use App\Support\Settings\Settings;

beforeEach(fn () => seedDemo());

it('serves repeated visits from the page cache', function () {
    $this->get('/it-IT')->assertHeader('X-Page-Cache', 'MISS');
    $this->get('/it-IT')->assertHeader('X-Page-Cache', 'HIT')->assertSee('John Doe');
});

it('refreshes the cache when content or settings change', function () {
    $this->get('/it-IT');

    Profile::current()->update(['full_name' => 'Jane Doe']);

    $this->get('/it-IT')->assertHeader('X-Page-Cache', 'MISS')->assertSee('Jane Doe');
    $this->get('/it-IT')->assertHeader('X-Page-Cache', 'HIT');

    app(Settings::class)->set('print.enabled', false);

    $this->get('/it-IT')->assertHeader('X-Page-Cache', 'MISS');
});

it('answers 304 to a browser that has the current version', function () {
    $etag = $this->get('/it-IT')->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $this->get('/it-IT', ['If-None-Match' => $etag])->assertStatus(304);
});

it('bypasses the cache for query strings, errors and redirects', function () {
    $this->get('/it-IT?ref=x')->assertHeaderMissing('X-Page-Cache');
    $this->get('/it-IT/non-esiste')->assertHeaderMissing('X-Page-Cache');
    $this->get('/')->assertHeaderMissing('X-Page-Cache');
});

it('can be turned off', function () {
    config(['resume.page_cache' => false]);

    $this->get('/it-IT');
    $this->get('/it-IT')->assertHeader('X-Page-Cache', 'MISS');
});

it('does not serve pages cached before an update of the theme assets', function () {
    $cache = app(PageCache::class);
    $key = $cache->key('http://localhost/it-IT');

    $other = new PageCache;
    (fn () => $this->assetsVersion = 'base|0|1.0.0')->call($other);

    expect($other->key('http://localhost/it-IT'))->not->toBe($key);
});
