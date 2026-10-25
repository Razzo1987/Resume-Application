<section id="projects" class="cv-section" aria-labelledby="projects-title">
    @include('theme::partials.section-heading', ['key' => 'projects', 'icon' => 'project'])

    <div class="projects">
        @foreach ($cv->projects() as $project)
            @php
                $title = $project->t('title');
                $path = $project->hasDetailPage() ? $urls->projectPath($project, $layout->locale) : null;
                $summary = $richText->render($project->t('summary'));
                $image = $images->attributes($project->image);
            @endphp
            <article class="project-card">
                @if ($image)
                    <img class="project-image" {{ $images->html($image) }} alt="{{ $project->t('image_alt') ?? '' }}" loading="lazy" decoding="async">
                @endif
                <div class="project-body">
                    <h3 class="project-title">
                        @if ($path)
                            <a href="{{ url($path) }}">{{ $title }}</a>
                        @else
                            {{ $title }}
                        @endif
                    </h3>
                    <p class="meta">
                        @include('theme::partials.period', ['start' => $project->start_date, 'end' => $project->end_date])
                    </p>
                    @include('theme::partials.project-facts', ['project' => $project])
                    @if ($summary)
                        <div class="rich-text">{{ $summary }}</div>
                    @endif
                    @if ($path)
                        <p class="details-link">
                            <a href="{{ url($path) }}">{{ __('site.actions.details') }}<span class="visually-hidden">: {{ $title }}</span> <x-theme-icon name="arrow-right" /></a>
                        </p>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>
