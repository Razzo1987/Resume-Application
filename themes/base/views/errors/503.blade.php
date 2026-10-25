@extends('theme::layouts.site')

@section('content')
    <div class="not-found">
        <h1 class="detail-title">{{ __('site.errors.maintenance_title') }}</h1>
        <p class="lead-text">{{ __('site.errors.maintenance_text') }}</p>
    </div>
@endsection
