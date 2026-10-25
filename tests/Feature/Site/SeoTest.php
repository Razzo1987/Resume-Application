<?php

use App\Models\Language;
use App\Models\Profile;
use App\Support\Settings\Settings;

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
    $this->get('/it-IT/progetti/data-catalog-e-lineage')
        ->assertSee('<link rel="canonical" href="http://localhost/it-IT/progetti/data-catalog-e-lineage">', escape: false)
        ->assertSee('<link rel="alternate" hreflang="en-GB" href="http://localhost/en-GB/projects/data-catalog-and-lineage">', escape: false)
        ->assertSee('<a href="http://localhost/en-GB/projects/data-catalog-and-lineage" hreflang="en-GB" lang="en-GB"', escape: false);
});

it('omits inactive languages from hreflang and the language switcher', function () {
    Language::query()->create(['code' => 'de-DE', 'name' => 'Deutsch', 'is_active' => false]);

    $this->get('/it-IT')->assertDontSee('hreflang="de-DE"', escape: false);
});

it('uses the SEO title and description from the settings, with a default', function () {
    $this->get('/it-IT')->assertSee('<title>John Doe — Data Governance</title>', escape: false);

    app(Settings::class)->setMany([
        'seo.title' => ['it-IT' => 'John Doe | Consulente dati'],
        'seo.description' => ['it-IT' => 'CV di John Doe.'],
    ]);

    $this->get('/it-IT')
        ->assertSee('<title>John Doe | Consulente dati</title>', escape: false)
        ->assertSee('<meta name="description" content="CV di John Doe.">', escape: false);

    // in inglese non c'è un titolo: si usa quello della lingua di default
    $this->get('/en-GB')->assertSee('<title>John Doe | Consulente dati</title>', escape: false);
});

it('describes the person in JSON-LD without personal contact data by default', function () {
    $nodes = jsonLdNodes((string) $this->get('/it-IT')->getContent());
    $person = collect($nodes)->firstWhere('@type', 'Person');

    expect(collect($nodes)->pluck('@type')->all())->toContain('WebSite', 'ProfilePage', 'Person', 'Thesis')
        ->and($person['@id'])->toBe('http://localhost/#person')
        ->and($person['name'])->toBe('John Doe')
        ->and($person['sameAs'])->toBe(['https://www.linkedin.com/in/example', 'https://github.com/example'])
        ->and($person['worksFor'][0]['name'])->toBe('Acme Consulting')
        ->and($person['alumniOf'][0]['@type'])->toBe('EducationalOrganization')
        ->and(collect($person['knowsLanguage'])->pluck('alternateName')->all())->toBe(['it', 'en'])
        ->and($person['address'])->toBe(['@type' => 'PostalAddress', 'addressLocality' => 'Milano', 'addressCountry' => 'IT'])
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
    $nodes = collect(jsonLdNodes((string) $this->get('/en-GB/projects/data-catalog-and-lineage')->getContent()));
    $breadcrumbs = $nodes->firstWhere('@type', 'BreadcrumbList');

    expect(collect($breadcrumbs['itemListElement'])->pluck('name')->all())->toBe(['John Doe', 'Projects', 'Data catalog and lineage'])
        ->and($breadcrumbs['itemListElement'][1]['item'])->toBe('http://localhost/en-GB#projects')
        ->and($nodes->firstWhere('@type', 'WebPage')['inLanguage'])->toBe('en-GB');
});

it('keeps JSON-LD safe from script injection', function () {
    Profile::current()->update(['full_name' => 'John </script><script>alert(1)</script>']);

    $html = (string) $this->get('/it-IT')->getContent();

    expect($html)->not->toContain('</script><script>alert(1)')
        ->and(collect(jsonLdNodes($html))->firstWhere('@type', 'Person')['name'])->toBe('John </script><script>alert(1)</script>');
});
