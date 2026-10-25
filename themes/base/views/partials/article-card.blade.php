{{-- Anteprima di un articolo (elenco del blog, ultimi articoli nel CV): $article, $headingLevel (default h3) --}}
@php
    $title = $article->t('title');
    $path = $urls->articlePath($article, $layout->locale);
    $excerpt = $article->t('excerpt') ?? $richText->plain($article->t('body'), 200);
    $cover = $images->attributes($article->cover_image);
    $tag = $headingLevel ?? 'h3';
@endphp
<article @class(['article-card', 'article-card-with-image' => (bool) $cover])>
    @if ($cover)
        <img class="article-card-image" {{ $images->html($cover) }} alt="" loading="lazy" decoding="async">
    @endif
    <div class="article-card-body">
        <p class="meta">
            <span class="meta-item"><x-theme-icon name="calendar" /> <time datetime="{{ $article->published_at?->toAtomString() }}">{{ $article->published_at ? $dates->day($article->published_at) : '' }}</time></span>
            <span class="meta-item"><x-theme-icon name="clock" /> {{ trans_choice('site.blog.reading_time', $article->readingMinutes(), ['count' => $article->readingMinutes()]) }}</span>
        </p>
        <{{ $tag }} class="article-card-title"><a href="{{ url($path) }}">{{ $title }}</a></{{ $tag }}>
        @if ($excerpt)
            <p class="article-card-excerpt">{{ $excerpt }}</p>
        @endif
        <p class="details-link"><a href="{{ url($path) }}">{{ __('site.actions.read_more') }}<span class="visually-hidden">: {{ $title }}</span> <x-theme-icon name="arrow-right" /></a></p>
    </div>
</article>
