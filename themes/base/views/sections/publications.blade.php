<section id="publications" class="cv-section" aria-labelledby="publications-title">
    @include('theme::partials.section-heading', ['key' => 'publications', 'icon' => 'publication'])

    <ol class="publications">
        @foreach ($cv->publications() as $publication)
            @php
                $title = $publication->t('title');
                $link = $publication->url ?? $images->url($publication->file);
                $venue = $publication->t('venue');
                $description = $richText->render($publication->t('description'));
            @endphp
            <li class="publication">
                <p class="meta">
                    <span class="tag">{{ __('site.publications.types.'.$publication->type->value) }}</span>
                    @if ($publication->published_on)
                        <span class="meta-item"><time datetime="{{ $dates->machine($publication->published_on, true) }}">{{ $dates->monthYear($publication->published_on) }}</time></span>
                    @endif
                </p>
                <h3 class="publication-title">
                    @if ($link)
                        <a href="{{ $link }}" rel="noopener" @if (! $publication->url) type="application/pdf" @endif @if ($publication->language_code) hreflang="{{ $publication->language_code }}" @endif>{{ $title }}</a>
                    @else
                        {{ $title }}
                    @endif
                </h3>
                @if ($venue)
                    <p class="publication-venue">{{ $venue }}</p>
                @endif
                @if ($publication->coauthors)
                    <p class="small-print">{{ __('site.publications.with', ['authors' => $publication->coauthors]) }}</p>
                @endif
                @if ($description)
                    <div class="rich-text">{{ $description }}</div>
                @endif
            </li>
        @endforeach
    </ol>
</section>
