@extends('theme::layouts.site')

@section('content')
    <h1 class="display-5">
        <x-theme-icon name="briefcase" class="me-2" />{{ config('app.name') }}
    </h1>
    <p class="lead">Resume App v2 — {{ __('site.sections.experience') }}, {{ __('site.sections.projects') }}, {{ __('site.sections.blog') }}.</p>
@endsection
