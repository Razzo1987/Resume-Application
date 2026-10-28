<?php

use App\Enums\PageSectionKey;
use App\Enums\SocialPlatform;
use App\Models\CvDocument;
use App\Models\Experience;
use App\Models\Interest;
use App\Models\Language;
use App\Models\PageSection;
use App\Models\SocialLink;
use App\Site\Contact\Cloak;
use App\Support\Settings\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => seedDemo());

/**
 * Order of the sections in the page (ids of the <section> elements).
 *
 * @return list<string>
 */
function sectionIds(string $html): array
{
    preg_match_all('#<section id="([a-z_]+)" class="cv-section#', $html, $matches);

    return $matches[1];
}

it('shows the enabled sections in the configured order', function () {
    expect(sectionIds((string) $this->get('/it-IT')->getContent()))->toBe([
        'about', 'highlights', 'experience', 'volunteering', 'projects', 'education',
        'certifications', 'languages', 'publications', 'interests',
    ]);

    PageSection::query()->where('key', PageSectionKey::Interests)->sole()->update(['sort_order' => 1]);
    PageSection::query()->where('key', PageSectionKey::Highlights)->sole()->update(['is_enabled' => false]);

    $html = (string) $this->get('/it-IT')->getContent();

    expect(sectionIds($html)[0])->toBe('interests')
        ->and(sectionIds($html))->not->toContain('highlights');
});

it('hides empty sections and hidden items, also from the menu', function () {
    Interest::query()->update(['is_visible' => false]);
    Experience::query()->where('kind', 'volunteering')->update(['is_visible' => false]);

    $html = (string) $this->get('/it-IT')->getContent();

    expect(sectionIds($html))->not->toContain('interests')->not->toContain('volunteering')
        ->and($html)->not->toContain('href="#interests"')
        ->and($html)->not->toContain('Docente volontario');
});

it('builds the menu from the sections marked for it, with custom titles', function () {
    PageSection::query()->where('key', PageSectionKey::Experience)->update(['title' => ['it-IT' => 'Carriera']]);
    PageSection::query()->where('key', PageSectionKey::Languages)->update(['in_menu' => false]);

    $html = (string) $this->get('/it-IT')->getContent();

    expect($html)->toContain('<li><a href="#experience">Carriera</a></li>')
        ->not->toContain('<li><a href="#languages">')
        ->and($html)->not->toContain('<li><a href="#highlights">');

    // from the detail pages the menu links back to the CV
    $this->get('/it-IT/progetti/portale-prenotazioni')
        ->assertSee('<li><a href="http://localhost/it-IT#experience">Carriera</a></li>', escape: false);
});

it('never writes email and phone in clear text', function () {
    $html = (string) $this->get('/it-IT')->getContent();
    $cloak = app(Cloak::class);

    expect($html)->not->toContain('john.doe@example.com')
        ->not->toContain('mailto:')
        ->not->toContain('000 000 0000')
        ->toContain('data-cloak-href="'.$cloak->encode('mailto:john.doe@example.com').'"')
        ->toContain('data-cloak-text="'.$cloak->encode('+39 000 000 0000').'"')
        ->toContain(__('site.contact.reveal_hint'));

    expect($cloak->decode($cloak->encode('mailto:ünïcode@example.com')))->toBe('mailto:ünïcode@example.com');
});

it('respects the contact visibility settings', function () {
    app(Settings::class)->setMany(['privacy.show_email' => false, 'privacy.show_phone' => false]);

    $html = (string) $this->get('/it-IT')->getContent();

    expect($html)->not->toContain('data-cloak-href')
        ->not->toContain(__('site.actions.contact'));
});

it('formats dates, durations and key figures in the page language', function () {
    Carbon::setTestNow('2026-10-25');

    $this->get('/it-IT')
        ->assertSee('gen 2021 - in corso')
        ->assertSee('5 anni e 10 mesi')
        ->assertSee('40+');

    $this->get('/en-GB')
        ->assertSee('Jan 2021 - present')
        ->assertSee('5 years and 10 months')
        ->assertSee('websites and apps released');
});

it('links to the CV download only when a PDF exists for the language', function () {
    $this->get('/it-IT')->assertSee('href="http://localhost/it-IT/cv"', escape: false);

    CvDocument::query()->get()->each->delete();

    $this->get('/it-IT')->assertDontSee('href="http://localhost/it-IT/cv"', escape: false);
});

it('shows the print button and the last update date only when enabled', function () {
    $this->get('/it-IT')
        ->assertSee('js-print', escape: false)
        ->assertDontSee('CV aggiornato a');

    app(Settings::class)->setMany(['print.enabled' => false, 'footer.show_last_updated' => true]);

    $this->get('/it-IT')
        ->assertDontSee('js-print', escape: false)
        ->assertSee('CV aggiornato a');
});

it('applies the custom site strings of a language', function () {
    Language::query()->where('code', 'it-IT')->sole()->update(['string_overrides' => ['actions.download_cv' => 'Scarica il curriculum']]);

    $this->get('/it-IT')
        ->assertSee('Scarica il curriculum')
        ->assertSee(__('site.actions.contact', locale: 'it-IT'));
});

it('sanitizes the HTML written in the editors', function () {
    Experience::query()->where('end_date', null)->update(['summary' => json_encode([
        'it-IT' => '<p onclick="steal()">Testo <script>alert(1)</script><a href="javascript:alert(1)">link</a><img src="x" onerror="alert(1)"></p>',
    ])]);

    $html = (string) $this->get('/it-IT')->getContent();

    expect($html)->toContain('Testo')
        ->not->toContain('alert(1)')
        ->not->toContain('onclick')
        ->not->toContain('javascript:');
});

it('loads the CV with a bounded number of queries', function () {
    DB::enableQueryLog();

    $this->get('/it-IT')->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThan(40);
});

it('shows the profile photo right after the name', function () {
    $html = (string) $this->get('/it-IT')->getContent();

    expect($html)->toMatch('#<h1 class="profile-name">John Doe</h1>\s*<img class="profile-photo" src="http://localhost/[a-z/]+/profile/john-doe-[a-z0-9]{6}\.webp" width="600" height="600" alt="Foto di John Doe"#');
});

it('names a personal website link in the page language, never with an admin key', function () {
    SocialLink::query()->create(['platform' => SocialPlatform::Website, 'url' => 'https://example.org', 'sort_order' => 0]);

    $this->get('/it-IT')->assertOk()->assertSee('title="Sito web"', escape: false)->assertDontSee('admin.');
    $this->get('/en-GB')->assertOk()->assertSee('title="Website"', escape: false)->assertDontSee('admin.');

    SocialLink::query()->where('platform', 'website')->sole()->update(['label' => 'Example.org']);

    $this->get('/it-IT')->assertSee('title="Example.org"', escape: false);
});
