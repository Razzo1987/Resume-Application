{{-- Pagina dedicata di un'esperienza (lavoro o volontariato): $experience --}}
@extends('theme::layouts.site')

@section('content')
    @php
        $role = $experience->t('role');
        $location = $experience->t('location');
        $summary = $richText->render($experience->t('summary'));
        $details = $richText->render($experience->t('details'));
        $section = $experience->kind === \App\Enums\ExperienceKind::Volunteering ? 'volunteering' : 'experience';
    @endphp

    @include('theme::partials.breadcrumbs')

    <article class="detail">
        <header class="detail-header">
            <div class="detail-heading">
                @include('theme::partials.organization-logo', ['organization' => $experience->organization])
                <div>
                    <h1 class="detail-title">{{ $role }}</h1>
                    @if ($experience->organization)
                        <p class="timeline-org">@include('theme::partials.organization-name', ['organization' => $experience->organization])</p>
                    @endif
                </div>
            </div>
            <p class="meta">
                @include('theme::partials.period', ['start' => $experience->start_date, 'end' => $experience->end_date])
                @if ($location)
                    <span class="meta-item"><x-theme-icon name="location" /> {{ $location }}</span>
                @endif
            </p>
        </header>

        @if ($summary)
            <div class="rich-text lead-text">{{ $summary }}</div>
        @endif

        @if ($details)
            <div class="rich-text">{{ $details }}</div>
        @endif

        @include('theme::partials.skills', ['skills' => $experience->skills])

        @if ($experience->projects->isNotEmpty())
            <section class="related" aria-labelledby="related-projects">
                <h2 id="related-projects" class="section-title"><x-theme-icon name="project" class="section-icon" /> <span>{{ __('site.experience.related_projects') }}</span></h2>
                <ul class="related-list">
                    @foreach ($experience->projects as $project)
                        @php $path = $project->hasDetailPage() ? $urls->projectPath($project, $layout->locale) : null; @endphp
                        <li>
                            <h3 class="related-title">
                                @if ($path)
                                    <a href="{{ url($path) }}">{{ $project->t('title') }}</a>
                                @else
                                    {{ $project->t('title') }}
                                @endif
                            </h3>
                            <p class="meta">
                                @include('theme::partials.period', ['start' => $project->start_date, 'end' => $project->end_date, 'withDuration' => false])
                                @if ($project->client)
                                    <span class="meta-item">{{ $project->client->name }}</span>
                                @endif
                            </p>
                            @if ($projectSummary = $richText->render($project->t('summary')))
                                <div class="rich-text">{{ $projectSummary }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <p class="back-link">
            <a href="{{ url($urls->sectionPath($layout->locale, $section)) }}"><x-theme-icon name="arrow-left" /> {{ __('site.actions.back_to_cv') }}</a>
        </p>
    </article>
@endsection
