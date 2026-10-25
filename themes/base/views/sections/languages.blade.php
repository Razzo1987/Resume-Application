<section id="languages" class="cv-section" aria-labelledby="languages-title">
    @include('theme::partials.section-heading', ['key' => 'languages', 'icon' => 'language'])

    <ul class="language-skills">
        @foreach ($cv->languageSkills() as $skill)
            @php $note = $skill->t('note'); @endphp
            <li class="language-skill">
                <h3 class="language-name">{{ $skill->displayName() }}</h3>
                @if ($skill->is_native)
                    <p><span class="tag tag-accent">{{ __('site.languages.native') }}</span></p>
                @else
                    <dl class="cefr-levels">
                        @foreach (['oral', 'written', 'comprehension'] as $kind)
                            @if ($level = $skill->{$kind})
                                <div class="cefr-row">
                                    <dt>{{ __('site.languages.'.$kind) }}</dt>
                                    <dd>
                                        <span class="cefr-bar" aria-hidden="true">
                                            @for ($step = 1; $step <= 6; $step++)
                                                <span @class(['on' => $step <= $level->rank()])></span>
                                            @endfor
                                        </span>
                                        <abbr title="{{ __('site.languages.levels.'.$level->value) }}">{{ $level->value }}</abbr>
                                    </dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                @endif
                @if ($note)
                    <p class="small-print">{{ $note }}</p>
                @endif
                @if ($skill->certification)
                    <p class="small-print"><x-theme-icon name="certificate" /> {{ $skill->certification->t('title') }}</p>
                @endif
            </li>
        @endforeach
    </ul>
    <p class="small-print">{{ __('site.languages.cefr') }}</p>
</section>
