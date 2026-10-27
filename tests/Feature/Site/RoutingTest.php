<?php

use App\Http\Middleware\NormalizeSitePath;
use App\Models\Language;
use App\Models\Project;
use App\Models\Redirect;
use Illuminate\Http\Request;

beforeEach(fn () => seedDemo());

it('redirects the home page to the default language', function () {
    $this->get('/')
        ->assertStatus(301)
        ->assertRedirect('http://localhost/it-IT');

    Language::query()->where('code', 'en-GB')->sole()->update(['is_default' => true]);

    $this->get('/')->assertRedirect('http://localhost/en-GB');
});

it('normalizes language codes, trailing slashes and short codes', function (string $from, string $to) {
    $this->get($from)->assertStatus(301)->assertRedirect('http://localhost'.$to);
})->with([
    'lowercase' => ['/it-it', '/it-IT'],
    'uppercase' => ['/EN-GB', '/en-GB'],
    'deep path' => ['/en-gb/projects/booking-portal', '/en-GB/projects/booking-portal'],
    'double slash' => ['/it-IT//progetti/portale-prenotazioni', '/it-IT/progetti/portale-prenotazioni'],
    'short code' => ['/en', '/en-GB'],
    'query kept' => ['/it-it?utm_source=x', '/it-IT?utm_source=x'],
]);

it('removes trailing and double slashes', function () {
    // The test client strips the trailing slash from URLs: the middleware is tested directly
    $response = NormalizeSitePath::redirect(Request::create('http://localhost/it-IT/progetti/?a=1'));

    expect($response?->getStatusCode())->toBe(301)
        ->and($response?->headers->get('Location'))->toBe('http://localhost/it-IT/progetti?a=1')
        ->and(NormalizeSitePath::redirect(Request::create('http://localhost/it-IT')))->toBeNull()
        ->and(NormalizeSitePath::redirect(Request::create('http://localhost/')))->toBeNull()
        ->and(NormalizeSitePath::redirect(Request::create('http://localhost/it-IT/', 'POST')))->toBeNull();
});

it('returns 404 for inactive or unknown languages', function () {
    Language::query()->create(['code' => 'de-DE', 'name' => 'Deutsch', 'is_active' => false]);

    $this->get('/de-DE')->assertNotFound();
    $this->get('/xx-XX')->assertNotFound();
    $this->get('/de')->assertNotFound();
});

it('serves detail pages only at their canonical address', function (string $from, string $to) {
    $this->get($from)->assertStatus(301)->assertRedirect('http://localhost'.$to);
})->with([
    'uppercase slug' => ['/it-IT/progetti/Portale-Prenotazioni', '/it-IT/progetti/portale-prenotazioni'],
    'segment of another language' => ['/en-GB/progetti/booking-portal', '/en-GB/projects/booking-portal'],
    'slug of another language' => ['/en-GB/projects/portale-prenotazioni', '/en-GB/projects/booking-portal'],
]);

it('opens project and experience pages', function () {
    $this->get('/it-IT/progetti/portale-prenotazioni')
        ->assertOk()
        ->assertSee('<h1 class="detail-title">Portale prenotazioni</h1>', escape: false)
        ->assertSee('300.000 prenotazioni nel primo anno');

    $this->get('/en-GB/experience/tech-lead-brightline-software')
        ->assertOk()
        ->assertSee('Projects in this role');
});

it('has no page for items without details, hidden items or unknown slugs', function () {
    // project without detail text or image
    $this->get('/it-IT/progetti/app-per-i-biglietti')->assertNotFound();

    Project::query()->where('slug->it-IT', 'portale-prenotazioni')->update(['is_visible' => false]);
    $this->get('/it-IT/progetti/portale-prenotazioni')->assertNotFound();

    $this->get('/it-IT/progetti/non-esiste')->assertNotFound();
    $this->get('/it-IT/sconosciuto/qualcosa')->assertNotFound();
});

it('sends listing segments to the CV section', function () {
    $this->get('/it-IT/progetti')->assertRedirect('http://localhost/it-IT#projects');
    $this->get('/en-GB/experience')->assertRedirect('http://localhost/en-GB#experience');
    $this->get('/en-GB/progetti')->assertRedirect('http://localhost/en-GB/projects');
});

it('keeps old addresses working when a slug changes', function () {
    $project = Project::query()->where('slug->it-IT', 'portale-prenotazioni')->sole();
    $project->setTranslation('slug', 'it-IT', 'catalogo-dati');
    $project->save();

    $this->get('/it-IT/progetti/portale-prenotazioni')
        ->assertStatus(301)
        ->assertRedirect('http://localhost/it-IT/progetti/catalogo-dati');

    $this->get('/it-IT/progetti/catalogo-dati')->assertOk();

    // A second change does not create redirect chains
    $project->setTranslation('slug', 'it-IT', 'catalogo');
    $project->save();

    expect(Redirect::query()->pluck('to_path', 'from_path')->all())->toEqual([
        '/it-IT/progetti/portale-prenotazioni' => '/it-IT/progetti/catalogo',
        '/it-IT/progetti/catalogo-dati' => '/it-IT/progetti/catalogo',
    ]);
});

it('keeps old addresses working when a URL segment changes', function () {
    $italian = Language::query()->where('code', 'it-IT')->sole();
    $italian->update(['url_segments' => ['projects' => 'lavori']]);

    $this->get('/it-IT/progetti/portale-prenotazioni')
        ->assertStatus(301)
        ->assertRedirect('http://localhost/it-IT/lavori/portale-prenotazioni');

    $this->get('/it-IT/lavori/portale-prenotazioni')->assertOk();
});

it('applies the redirects managed in the admin, also to sub-paths', function () {
    Redirect::query()->create(['from_path' => '/vecchio-cv', 'to_path' => '/it-IT', 'status_code' => 302]);
    Redirect::query()->create(['from_path' => '/en-gb', 'to_path' => '/en-GB']);
    Redirect::query()->create(['from_path' => '/spento', 'to_path' => '/it-IT', 'is_active' => false]);

    $this->get('/vecchio-cv')->assertStatus(302)->assertRedirect('http://localhost/it-IT');
    $this->get('/vecchio-cv/pagina')->assertStatus(302)->assertRedirect('http://localhost/it-IT/pagina');
    $this->get('/spento')->assertNotFound();

    expect(Redirect::query()->where('from_path', '/vecchio-cv')->value('hits'))->toBe(2);
});

it('lists every public page with its language versions in the sitemap', function () {
    $xml = simplexml_load_string((string) $this->get('/sitemap.xml')->assertOk()->getContent());
    $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $xml->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');

    $locations = array_map('strval', $xml->xpath('//s:url/s:loc'));

    expect($locations)->toContain(
        'http://localhost/it-IT',
        'http://localhost/en-GB',
        'http://localhost/it-IT/progetti/portale-prenotazioni',
        'http://localhost/en-GB/experience/tech-lead-brightline-software',
    )->not->toContain('http://localhost/it-IT/progetti/app-per-i-biglietti')
        ->and($xml->xpath('//s:url[s:loc="http://localhost/en-GB"]/x:link[@hreflang="x-default"]/@href')[0]->__toString())
        ->toBe('http://localhost/it-IT');
});

it('publishes a robots.txt that points to the sitemap and hides the admin path', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Sitemap: http://localhost/sitemap.xml')
        ->assertDontSee('admin');
});

it('gives existing pages an address in a newly activated language', function () {
    Language::query()->create(['code' => 'de-DE', 'name' => 'Deutsch']);

    $project = Project::query()->where('slug->it-IT', 'portale-prenotazioni')->sole();

    // content not yet translated into German: the slug comes from the title in the default language
    expect($project->t('slug', 'de-DE', false))->toBe('portale-prenotazioni')
        ->and(Redirect::query()->count())->toBe(0);

    $this->get('/de-DE/projekte/portale-prenotazioni')
        ->assertOk()
        ->assertSee('<html lang="de-DE">', escape: false)
        ->assertSee('Zurück zum Lebenslauf');
});

it('has no static files that would hide the generated ones', function (string $file) {
    // Apache serves existing files before falling back to index.php
    expect(public_path($file))->not->toBeFile();
})->with(['favicon.ico', 'robots.txt', 'sitemap.xml']);
