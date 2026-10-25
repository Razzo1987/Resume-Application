{{-- Elenco cronologico di esperienze (lavoro o volontariato): $experiences --}}
<ol class="timeline">
    @foreach ($experiences as $experience)
        @php
            $role = $experience->t('role');
            $path = $experience->hasDetailPage() ? $urls->experiencePath($experience, $layout->locale) : null;
            $summary = $richText->render($experience->t('summary'));
            $location = $experience->t('location');
        @endphp
        <li class="timeline-item">
            <article class="timeline-card">
                <header class="timeline-header">
                    @include('theme::partials.organization-logo', ['organization' => $experience->organization])
                    <div class="timeline-heading">
                        <h3 class="timeline-title">
                            @if ($path)
                                <a href="{{ url($path) }}">{{ $role }}</a>
                            @else
                                {{ $role }}
                            @endif
                        </h3>
                        @if ($experience->organization)
                            <p class="timeline-org">@include('theme::partials.organization-name', ['organization' => $experience->organization])</p>
                        @endif
                        <p class="meta">
                            @include('theme::partials.period', ['start' => $experience->start_date, 'end' => $experience->end_date])
                            @if ($location)
                                <span class="meta-item"><x-theme-icon name="location" /> {{ $location }}</span>
                            @endif
                        </p>
                    </div>
                </header>

                @if ($summary)
                    <div class="rich-text">{{ $summary }}</div>
                @endif

                @include('theme::partials.skills', ['skills' => $experience->skills])

                @if ($path)
                    <p class="details-link">
                        <a href="{{ url($path) }}">{{ __('site.actions.details') }}<span class="visually-hidden">: {{ $role }}</span> <x-theme-icon name="arrow-right" /></a>
                    </p>
                @endif
            </article>
        </li>
    @endforeach
</ol>
