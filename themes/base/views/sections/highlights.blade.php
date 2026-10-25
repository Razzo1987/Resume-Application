<section id="highlights" class="cv-section cv-section-highlights" aria-labelledby="highlights-title">
    @include('theme::partials.section-heading', ['key' => 'highlights', 'icon' => 'chart'])

    <ul class="highlights">
        @foreach ($cv->highlights() as $highlight)
            <li class="highlight">
                @if ($highlight->icon)
                    <x-theme-icon :name="$highlight->icon" class="highlight-icon" />
                @endif
                <span class="highlight-value" data-value="{{ $highlight->resolvedValue() }}">{{ $highlight->formattedValue() }}</span>
                <span class="highlight-label">{{ $highlight->displayLabel() }}</span>
            </li>
        @endforeach
    </ul>
</section>
