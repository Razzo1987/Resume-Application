{{-- Informativa privacy: $policy (HTML già sanificato, vedi App\Site\PrivacyPolicy) --}}
@extends('theme::layouts.site')

@section('content')
    @include('theme::partials.breadcrumbs')

    <article class="detail" id="privacy">
        <header class="detail-header">
            <h1 class="detail-title">{{ __('site.privacy.title') }}</h1>
        </header>

        <div class="rich-text">{{ $policy }}</div>

        <p class="back-link">
            <a href="{{ $layout->homeUrl }}"><x-theme-icon name="arrow-left" /> {{ __('site.actions.back_to_cv') }}</a>
        </p>
    </article>
@endsection
