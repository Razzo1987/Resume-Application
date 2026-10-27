{{-- Articolo del blog: $article --}}
@extends('theme::layouts.site')

@section('content')
    @php
        $title = $article->t('title');
        $cover = $images->attributes($article->cover_image);
        $body = $richText->render($article->t('body'));
        $updated = $article->updated_at && $article->published_at && $article->updated_at->gt($article->published_at->copy()->addDay()) ? $article->updated_at : null;
    @endphp

    @include('theme::partials.breadcrumbs')

    <article class="detail article">
        <header class="detail-header">
            <h1 class="detail-title">{{ $title }}</h1>
            <p class="meta">
                @if ($article->published_at)
                    <span class="meta-item"><x-theme-icon name="calendar" /> <time datetime="{{ $article->published_at->toAtomString() }}">{{ __('site.blog.published_on', ['date' => $dates->localDay($article->published_at)]) }}</time></span>
                @endif
                @if ($updated)
                    <span class="meta-item"><time datetime="{{ $updated->toAtomString() }}">{{ __('site.blog.updated_on', ['date' => $dates->localDay($updated)]) }}</time></span>
                @endif
                <span class="meta-item"><x-theme-icon name="clock" /> {{ trans_choice('site.blog.reading_time', $article->readingMinutes(), ['count' => $article->readingMinutes()]) }}</span>
            </p>
        </header>

        @if ($cover)
            <img class="detail-image" {{ $images->html($cover) }} alt="{{ $article->t('cover_alt') ?? '' }}" fetchpriority="high">
        @endif

        @if ($body)
            <div class="rich-text article-body">{{ $body }}</div>
        @endif

        <p class="back-link">
            <a href="{{ $layout->blogUrl ?? $layout->homeUrl }}"><x-theme-icon name="arrow-left" /> {{ __('site.blog.back_to_blog') }}</a>
        </p>
    </article>
@endsection
