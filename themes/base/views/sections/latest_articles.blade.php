<section id="latest_articles" class="cv-section" aria-labelledby="latest_articles-title">
    @include('theme::partials.section-heading', ['key' => 'latest_articles', 'icon' => 'blog'])

    <div class="article-list">
        @foreach ($cv->latestArticles() as $article)
            @include('theme::partials.article-card', ['article' => $article])
        @endforeach
    </div>

    @if ($layout->blogUrl)
        <p class="details-link"><a href="{{ $layout->blogUrl }}">{{ __('site.actions.all_articles') }} <x-theme-icon name="arrow-right" /></a></p>
    @endif
</section>
