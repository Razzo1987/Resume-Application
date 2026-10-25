<?php

use App\Enums\ArticleStatus;
use App\Enums\PageSectionKey;
use App\Models\Article;
use App\Models\PageSection;
use App\Models\Redirect;
use App\Site\Blog;
use App\Site\Cache\PageCache;
use Illuminate\Support\Carbon;

beforeEach(fn () => seedDemo());

function article(array $title, array $attributes = []): Article
{
    return Article::query()->create([
        'title' => $title,
        'body' => array_map(fn (string $text): string => "<p>{$text}: ".str_repeat('parola ', 450).'</p>', $title),
        'status' => ArticleStatus::Published,
        'published_at' => now()->subDays(2),
        ...$attributes,
    ]);
}

it('lists the published articles written in the page language', function () {
    article(['it-IT' => 'Solo in italiano']);
    article(['it-IT' => 'Bozza', 'en-GB' => 'Draft'], ['status' => ArticleStatus::Draft]);
    article(['it-IT' => 'Programmato', 'en-GB' => 'Scheduled'], ['published_at' => now()->addDay()]);

    $this->get('/it-IT/blog')
        ->assertOk()
        ->assertSee('Solo in italiano')
        ->assertSee('Perché la Data Governance conviene')
        ->assertDontSee('Bozza')
        ->assertDontSee('Programmato')
        ->assertSee('3 minuti di lettura');

    $this->get('/en-GB/blog')
        ->assertOk()
        ->assertSee('Why data governance pays off')
        ->assertDontSee('Solo in italiano');
});

it('opens an article only in the languages it is written in', function () {
    $italian = article(['it-IT' => 'Solo in italiano']);

    $this->get('/it-IT/blog/solo-in-italiano')
        ->assertOk()
        ->assertSee('<h1 class="detail-title">Solo in italiano</h1>', escape: false)
        ->assertDontSee('hreflang="en-GB" href="http://localhost/en-GB/blog', escape: false);

    $this->get('/en-GB/blog/solo-in-italiano')->assertNotFound();

    expect($italian->t('slug', 'en-GB', false))->toBeNull();
});

it('hides drafts and scheduled articles until their time', function () {
    $scheduled = article(['it-IT' => 'In arrivo'], ['published_at' => Carbon::parse('2026-11-01 09:00')]);
    Carbon::setTestNow('2026-10-31 12:00');

    $this->get('/it-IT/blog/in-arrivo')->assertNotFound();

    Carbon::setTestNow('2026-11-01 09:00');
    app()->forgetScopedInstances();

    $this->get('/it-IT/blog/in-arrivo')->assertOk();
    expect($scheduled->fresh()->isPublished())->toBeTrue();
});

it('keeps cached pages only until the next scheduled publication', function () {
    Carbon::setTestNow('2026-10-31 12:00');
    article(['it-IT' => 'In arrivo'], ['published_at' => Carbon::parse('2026-10-31 13:00')]);

    expect(app(PageCache::class)->ttl())->toBe(3600)
        ->and(app(Blog::class)->nextScheduledAt()?->toDateTimeString())->toBe('2026-10-31 13:00:00');
});

it('publishes immediately when no date is set', function () {
    $article = Article::query()->create(['title' => ['it-IT' => 'Subito'], 'status' => ArticleStatus::Published]);

    expect($article->published_at)->not->toBeNull()
        ->and($article->isPublished())->toBeTrue();
});

it('paginates the blog with canonical addresses', function () {
    foreach (range(1, 12) as $number) {
        article(['it-IT' => "Articolo {$number}"], ['published_at' => now()->subDays(100 - $number)]);
    }

    $this->get('/it-IT/blog?page=2')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="http://localhost/it-IT/blog?page=2">', escape: false)
        ->assertSee('rel="prev"', escape: false)
        ->assertDontSee('<link rel="alternate" hreflang=', escape: false);

    $this->get('/it-IT/blog?page=1')->assertRedirect('http://localhost/it-IT/blog');
    $this->get('/it-IT/blog?page=9')->assertNotFound();
    $this->get('/it-IT/blog?page=abc')->assertNotFound();
});

it('returns 404 for the blog of a language without articles', function () {
    Article::query()->get()->each->delete();

    $this->get('/it-IT/blog')->assertNotFound();
    $this->get('/it-IT/feed.xml')->assertNotFound();
    $this->get('/it-IT')->assertDontSee('href="http://localhost/it-IT/blog"', escape: false);
});

it('describes articles as BlogPosting and links the feed', function () {
    $html = (string) $this->get('/en-GB/blog/why-data-governance-pays-off')->getContent();
    $posting = collect(jsonLdNodes($html))->firstWhere('@type', 'BlogPosting');

    expect($posting['headline'])->toBe('Why data governance pays off')
        ->and($posting['author'])->toBe(['@id' => 'http://localhost/#person'])
        ->and($posting['inLanguage'])->toBe('en-GB')
        ->and($html)->toContain('<link rel="alternate" type="application/rss+xml"')
        ->and($html)->toContain('<link rel="alternate" hreflang="it-IT" href="http://localhost/it-IT/blog/perche-la-data-governance-conviene">');
});

it('publishes an RSS feed per language', function () {
    $response = $this->get('/it-IT/feed.xml');

    $response->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

    $xml = simplexml_load_string((string) $response->getContent());

    expect((string) $xml->channel->language)->toBe('it-IT')
        ->and((string) $xml->channel->item[0]->title)->toBe('Perché la Data Governance conviene')
        ->and((string) $xml->channel->item[0]->link)->toBe('http://localhost/it-IT/blog/perche-la-data-governance-conviene');
});

it('shows the latest articles on the CV when the section is enabled, and the blog in the menu', function () {
    $this->get('/it-IT')
        ->assertDontSee('id="latest_articles"', escape: false)
        ->assertSee('<li><a href="http://localhost/it-IT/blog">Blog</a></li>', escape: false);

    PageSection::query()->where('key', PageSectionKey::LatestArticles)->sole()->update(['is_enabled' => true]);

    $this->get('/it-IT')
        ->assertSee('id="latest_articles"', escape: false)
        ->assertSee('Perché la Data Governance conviene');
});

it('lists the blog and its articles in the sitemap', function () {
    $xml = (string) $this->get('/sitemap.xml')->getContent();

    expect($xml)->toContain('<loc>http://localhost/it-IT/blog</loc>')
        ->toContain('<loc>http://localhost/en-GB/blog/why-data-governance-pays-off</loc>');
});

it('redirects the old address when an article slug changes', function () {
    $article = Article::query()->firstOrFail();
    $article->setTranslation('slug', 'it-IT', 'data-governance');
    $article->save();

    $this->get('/it-IT/blog/perche-la-data-governance-conviene')->assertRedirect('http://localhost/it-IT/blog/data-governance');
    expect(Redirect::query()->count())->toBe(1);
});

it('marks the blog as the current menu item on blog pages', function () {
    $this->get('/it-IT/blog')->assertSee('<li><a href="http://localhost/it-IT/blog" aria-current="true">Blog</a></li>', escape: false);
});

it('has a single blog menu item, driven by the latest articles section', function () {
    $section = PageSection::query()->where('key', PageSectionKey::LatestArticles)->sole();
    $section->update(['is_enabled' => true, 'in_menu' => true]);

    $html = (string) $this->get('/it-IT')->getContent();

    // Una sola voce, verso la pagina del blog (non l'ancora della sezione nel CV)
    expect(substr_count($html, '>Blog</a></li>'))->toBe(1)
        ->and($html)->toContain('<li><a href="http://localhost/it-IT/blog">Blog</a></li>')
        ->and($html)->not->toContain('href="#latest_articles"');

    // La posizione segue l'ordine delle sezioni
    $section->update(['sort_order' => 0]);
    $html = (string) $this->get('/it-IT')->getContent();
    expect(strpos($html, '>Blog</a></li>'))->toBeLessThan(strpos($html, '>Profilo</a></li>'));

    // Un titolo personalizzato vale anche per la voce del menu
    $section->update(['title' => ['it-IT' => 'Articoli']]);
    $this->get('/it-IT')->assertSee('<li><a href="http://localhost/it-IT/blog">Articoli</a></li>', escape: false);

    // Fuori dal menu: nessuna voce, ma la pagina del blog resta raggiungibile
    $section->update(['in_menu' => false]);
    $this->get('/it-IT')->assertDontSee('<li><a href="http://localhost/it-IT/blog">', escape: false);
    $this->get('/it-IT/blog')->assertOk();
});

it('keeps the blog menu item when the latest articles section is hidden on the CV', function () {
    PageSection::query()->where('key', PageSectionKey::LatestArticles)->update(['is_enabled' => false, 'in_menu' => true]);

    $this->get('/it-IT')
        ->assertDontSee('id="latest_articles"', escape: false)
        ->assertSee('<li><a href="http://localhost/it-IT/blog">Blog</a></li>', escape: false);
});
