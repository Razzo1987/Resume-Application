<?php

use App\Models\Language;
use App\Models\Profile;
use App\Support\Media\ImageOptimizer;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(fn () => seedDemo());

it('declares canonical address and language versions on the CV', function () {
    $this->get('/en-GB')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="http://localhost/en-GB">', escape: false)
        ->assertSee('<link rel="alternate" hreflang="it-IT" href="http://localhost/it-IT">', escape: false)
        ->assertSee('<link rel="alternate" hreflang="en-GB" href="http://localhost/en-GB">', escape: false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="http://localhost/it-IT">', escape: false)
        ->assertSee('<meta property="og:locale" content="en_GB">', escape: false)
        ->assertSee('<meta property="og:locale:alternate" content="it_IT">', escape: false)
        ->assertSee('<meta property="og:url" content="http://localhost/en-GB">', escape: false);
});

it('links the translated address of a detail page in every language', function () {
    $this->get('/it-IT/progetti/portale-prenotazioni')
        ->assertSee('<link rel="canonical" href="http://localhost/it-IT/progetti/portale-prenotazioni">', escape: false)
        ->assertSee('<link rel="alternate" hreflang="en-GB" href="http://localhost/en-GB/projects/booking-portal">', escape: false)
        ->assertSee('<a href="http://localhost/en-GB/projects/booking-portal" hreflang="en-GB" lang="en-GB"', escape: false);
});

it('omits inactive languages from hreflang and the language switcher', function () {
    Language::query()->create(['code' => 'de-DE', 'name' => 'Deutsch', 'is_active' => false]);

    $this->get('/it-IT')->assertDontSee('hreflang="de-DE"', escape: false);
});

it('uses the SEO title and description from the settings, with a default', function () {
    $this->get('/it-IT')->assertSee('<title>John Doe - Sviluppatore full-stack</title>', escape: false);

    app(Settings::class)->setMany([
        'seo.title' => ['it-IT' => 'John Doe | Consulente dati'],
        'seo.description' => ['it-IT' => 'CV di John Doe.'],
    ]);

    $this->get('/it-IT')
        ->assertSee('<title>John Doe | Consulente dati</title>', escape: false)
        ->assertSee('<meta name="description" content="CV di John Doe.">', escape: false);

    // there is no English title: the one of the default language is used
    $this->get('/en-GB')->assertSee('<title>John Doe | Consulente dati</title>', escape: false);
});

it('describes the person in JSON-LD without personal contact data by default', function () {
    $nodes = jsonLdNodes((string) $this->get('/it-IT')->getContent());
    $person = collect($nodes)->firstWhere('@type', 'Person');

    expect(collect($nodes)->pluck('@type')->all())->toContain('WebSite', 'ProfilePage', 'Person', 'Thesis')
        ->and($person['@id'])->toBe('http://localhost/#person')
        ->and($person['name'])->toBe('John Doe')
        ->and($person['sameAs'])->toBe(['https://www.linkedin.com/in/example', 'https://github.com/example'])
        ->and($person['worksFor'][0]['name'])->toBe('Brightline Software')
        ->and($person['alumniOf'][0]['@type'])->toBe('EducationalOrganization')
        ->and(collect($person['knowsLanguage'])->pluck('alternateName')->all())->toBe(['it', 'en'])
        ->and($person['address'])->toBe(['@type' => 'PostalAddress', 'addressLocality' => 'Torino', 'addressCountry' => 'IT'])
        ->and($person)->not->toHaveKeys(['email', 'telephone', 'birthDate']);
});

it('adds email, phone and birth date to JSON-LD only when enabled', function () {
    Profile::current()->update(['birth_date' => '1987-05-10']);

    app(Settings::class)->setMany([
        'jsonld.include_email' => true,
        'jsonld.include_phone' => true,
        'jsonld.include_birthdate' => true,
    ]);

    $person = collect(jsonLdNodes((string) $this->get('/it-IT')->getContent()))->firstWhere('@type', 'Person');

    expect($person['email'])->toBe('john.doe@example.com')
        ->and($person['telephone'])->toBe('+39 000 000 0000')
        ->and($person['birthDate'])->toBe('1987-05-10');
});

it('adds breadcrumbs to detail pages', function () {
    $nodes = collect(jsonLdNodes((string) $this->get('/en-GB/projects/booking-portal')->getContent()));
    $breadcrumbs = $nodes->firstWhere('@type', 'BreadcrumbList');

    expect(collect($breadcrumbs['itemListElement'])->pluck('name')->all())->toBe(['John Doe', 'Projects', 'Booking portal'])
        ->and($breadcrumbs['itemListElement'][1]['item'])->toBe('http://localhost/en-GB#projects')
        ->and($nodes->firstWhere('@type', 'WebPage')['inLanguage'])->toBe('en-GB');
});

it('keeps JSON-LD safe from script injection', function () {
    Profile::current()->update(['full_name' => 'John </script><script>alert(1)</script>']);

    $html = (string) $this->get('/it-IT')->getContent();

    expect($html)->not->toContain('</script><script>alert(1)')
        ->and(collect(jsonLdNodes($html))->firstWhere('@type', 'Person')['name'])->toBe('John </script><script>alert(1)</script>');
});

it('links the site icon in the pages and from /favicon.ico', function () {
    $this->get('/favicon.ico')->assertNotFound();

    $source = sys_get_temp_dir().'/icon-'.bin2hex(random_bytes(4)).'.png';
    imagepng(imagecreatetruecolor(600, 600), $source);
    app(Settings::class)->set('site.icon', app(ImageOptimizer::class)->store($source, 'icon.png', 'uploads', 'site', 512, 'png'));
    @unlink($source);

    $html = (string) $this->get('/it-IT')->assertOk()->getContent();

    expect($html)->toMatch('#<link rel="icon" type="image/png" sizes="32x32" href="[^"]+/site/icon-[a-z0-9]+-32\.png">#')
        ->toMatch('#<link rel="apple-touch-icon" href="[^"]+-180\.png">#');

    $icon = (string) preg_replace('#^.*/(site/)#', '$1', (string) Str::match('#href="([^"]+-32\.png)"#', $html));
    Storage::disk('uploads')->assertExists($icon);
    expect(getimagesize(Storage::disk('uploads')->path($icon))[0])->toBe(32);

    $this->get('/favicon.ico')->assertStatus(301)->assertRedirectContains('-32.png');
});
