@extends('theme::layouts.site')

@section('content')
    <div class="not-found">
        <h1 class="detail-title">{{ __('site.errors.not_found_title') }}</h1>
        <p class="lead-text">{{ __('site.errors.not_found_text') }}</p>
        <p class="back-link">
            <a class="btn btn-primary" href="{{ $layout->homeUrl }}"><x-theme-icon name="arrow-left" /> {{ __('site.actions.back_to_cv') }}</a>
        </p>
    </div>
@endsection
