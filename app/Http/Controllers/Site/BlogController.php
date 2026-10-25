<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Site\Blog;
use App\Site\Content\RichText;
use App\Site\CvData;
use App\Site\Pages;
use App\Site\SiteUrls;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Blog: elenco paginato ("/{lingua}/blog?page=2") e feed RSS ("/{lingua}/feed.xml").
 * Una lingua senza articoli pubblicati non ha il blog (404).
 */
class BlogController extends Controller
{
    public function __construct(
        private readonly Blog $blog,
        private readonly SiteUrls $urls,
    ) {}

    public function index(Request $request, string $locale, Pages $pages, CvData $cv): View|RedirectResponse
    {
        $page = $request->query('page');

        // "?page=1" è la prima pagina: un solo indirizzo
        if ($page === '1') {
            return redirect()->to(url($this->urls->blogPath($locale)), 301);
        }

        abort_unless($page === null || (is_string($page) && ctype_digit($page) && (int) $page > 1), 404);

        $articles = $this->blog->page($locale, (int) ($page ?? 1));

        abort_if($articles->isEmpty(), 404);

        $articles->withPath(url($this->urls->blogPath($locale)));

        return view('theme::blog', [
            'page' => $pages->blog($locale, $articles),
            'articles' => $articles,
            'cv' => $cv,
        ]);
    }

    public function feed(string $locale, CvData $cv, RichText $richText): Response
    {
        $articles = $this->blog->query($locale)->limit(20)->get();

        abort_if($articles->isEmpty(), 404);

        $name = $cv->profile()->full_name;

        $xml = view('feed', [
            'title' => __('site.sections.blog').' — '.$name,
            'description' => __('site.blog.description', ['name' => $name]),
            'locale' => $locale,
            'link' => url($this->urls->blogPath($locale)),
            'self' => url($this->urls->feedPath($locale)),
            'updated' => $articles->max(fn (Article $article) => $article->published_at),
            'items' => $articles->map(fn (Article $article): array => [
                'title' => (string) $article->t('title', $locale),
                'link' => url((string) $this->urls->articlePath($article, $locale)),
                'date' => $article->published_at,
                'description' => $article->t('excerpt', $locale) ?? $richText->plain($article->t('body', $locale), 300),
            ])->all(),
        ])->render();

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
