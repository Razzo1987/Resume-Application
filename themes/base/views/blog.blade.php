{{-- Elenco degli articoli: $articles (paginatore) --}}
@extends('theme::layouts.site')

@section('content')
    @include('theme::partials.breadcrumbs')

    <header class="detail-header">
        <h1 class="detail-title">{{ __('site.sections.blog') }}</h1>
        <p class="lead-text">{{ __('site.blog.description', ['name' => $layout->profile->full_name]) }}</p>
    </header>

    <div class="article-list">
        @foreach ($articles as $article)
            @include('theme::partials.article-card', ['article' => $article, 'headingLevel' => 'h2'])
        @endforeach
    </div>

    @if ($articles->hasPages())
        <nav class="pager" aria-label="{{ __('site.blog.pagination') }}">
            @if ($articles->previousPageUrl())
                <a href="{{ $articles->currentPage() === 2 ? $articles->path() : $articles->previousPageUrl() }}" rel="prev"><x-theme-icon name="arrow-left" /> {{ __('site.blog.newer') }}</a>
            @endif
            <span class="pager-current">{{ __('site.blog.page', ['page' => $articles->currentPage()]) }} / {{ $articles->lastPage() }}</span>
            @if ($articles->nextPageUrl())
                <a href="{{ $articles->nextPageUrl() }}" rel="next">{{ __('site.blog.older') }} <x-theme-icon name="arrow-right" /></a>
            @endif
        </nav>
    @endif

@endsection
