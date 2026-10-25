@extends('install.layout')

@section('title', __('install.done.title'))

@section('content')
    <section class="card">
        <p>{{ __('install.done.text') }}</p>
        @if ($existing)
            <p class="muted">{{ __('install.done.existing') }}</p>
        @endif
        <p><a class="button" href="{{ $adminUrl }}">{{ __('install.done.admin') }}</a> <a class="button button-secondary" href="{{ $siteUrl }}">{{ __('install.done.site') }}</a></p>
        <p class="muted">{{ __('install.done.admin_address', ['url' => $adminUrl]) }}</p>
    </section>
    <section class="card">
        <h2>{{ __('install.done.next') }}</h2>
        <ul>
            <li>{{ __('install.done.step_mfa') }}</li>
            <li>{{ __('install.done.step_profile') }}</li>
            <li>{{ __('install.done.step_cv') }}</li>
        </ul>
    </section>
@endsection
