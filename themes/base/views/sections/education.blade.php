<section id="education" class="cv-section" aria-labelledby="education-title">
    @include('theme::partials.section-heading', ['key' => 'education', 'icon' => 'graduation'])

    <ol class="timeline">
        @foreach ($cv->educations() as $education)
            @php
                $degree = $education->t('degree');
                $field = $education->t('field_of_study');
                $summary = $richText->render($education->t('summary'));
                $details = $richText->render($education->t('details'));
                $thesis = $education->t('thesis_title');
            @endphp
            <li class="timeline-item">
                <article class="timeline-card">
                    <header class="timeline-header">
                        @include('theme::partials.organization-logo', ['organization' => $education->organization])
                        <div class="timeline-heading">
                            <h3 class="timeline-title">
                                @if ($education->link)
                                    <a href="{{ $education->link }}" rel="noopener">{{ $degree }}</a>
                                @else
                                    {{ $degree }}
                                @endif
                            </h3>
                            @if ($field || $education->organization)
                                <p class="timeline-org">
                                    {{ $field }}@if ($field && $education->organization) · @endif
                                    @include('theme::partials.organization-name', ['organization' => $education->organization])
                                </p>
                            @endif
                            @if ($education->start_date || $education->end_date)
                                <p class="meta">
                                    @include('theme::partials.period', ['start' => $education->start_date, 'end' => $education->end_date, 'withDuration' => false])
                                </p>
                            @endif
                        </div>
                    </header>

                    @if ($education->grade || $thesis)
                        <dl class="facts">
                            @if ($education->grade)
                                <div class="fact">
                                    <dt>{{ __('site.education.grade') }}</dt>
                                    <dd>{{ $education->grade }}@if ($education->grade_scale)/{{ $education->grade_scale }}@endif @if ($education->cum_laude) {{ __('site.education.cum_laude') }}@endif</dd>
                                </div>
                            @endif
                            @if ($thesis)
                                <div class="fact">
                                    <dt>{{ __('site.education.thesis') }}</dt>
                                    <dd>{{ $thesis }}</dd>
                                </div>
                            @endif
                        </dl>
                    @endif

                    @if ($summary)
                        <div class="rich-text">{{ $summary }}</div>
                    @endif

                    @if ($details)
                        <details class="more">
                            <summary>{{ __('site.actions.details') }}<span class="visually-hidden">: {{ $degree }}</span></summary>
                            <div class="rich-text">{{ $details }}</div>
                        </details>
                    @endif
                </article>
            </li>
        @endforeach
    </ol>
</section>
