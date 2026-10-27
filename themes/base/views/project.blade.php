{{-- Dedicated page of a project: $project --}}
@extends('theme::layouts.site')

@section('content')
    @php
        $title = $project->t('title');
        $image = $images->attributes($project->image);
        $summary = $richText->render($project->t('summary'));
        $details = $richText->render($project->t('details'));
        $experience = $project->experience;
        $experiencePath = $experience && $experience->is_visible && $experience->hasDetailPage() ? $urls->experiencePath($experience, $layout->locale) : null;
    @endphp

    @include('theme::partials.breadcrumbs')

    <article class="detail">
        <header class="detail-header">
            <h1 class="detail-title">{{ $title }}</h1>
            <p class="meta">
                @include('theme::partials.period', ['start' => $project->start_date, 'end' => $project->end_date])
            </p>
            @include('theme::partials.project-facts', ['project' => $project])
            @if ($experience)
                <p class="detail-context">
                    <x-theme-icon name="briefcase" />
                    @if ($experiencePath)
                        <a href="{{ url($experiencePath) }}">{{ $experience->t('role') }}</a>
                    @else
                        {{ $experience->t('role') }}
                    @endif
                    @if ($experience->organization)
                        · {{ $experience->organization->name }}
                    @endif
                </p>
            @endif
        </header>

        @if ($image)
            <img class="detail-image" {{ $images->html($image) }} alt="{{ $project->t('image_alt') ?? '' }}" decoding="async">
        @endif

        @if ($summary)
            <div class="rich-text lead-text">{{ $summary }}</div>
        @endif

        @if ($details)
            <div class="rich-text">{{ $details }}</div>
        @endif

        @include('theme::partials.skills', ['skills' => $project->skills])

        <p class="back-link">
            <a href="{{ url($urls->sectionPath($layout->locale, 'projects')) }}"><x-theme-icon name="arrow-left" /> {{ __('site.actions.back_to_cv') }}</a>
        </p>
    </article>
@endsection
