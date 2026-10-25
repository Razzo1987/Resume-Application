<section id="interests" class="cv-section" aria-labelledby="interests-title">
    @include('theme::partials.section-heading', ['key' => 'interests', 'icon' => 'interests'])

    <ul class="interests">
        @foreach ($cv->interests() as $interest)
            @php $description = $interest->t('description'); @endphp
            <li class="interest">
                @if ($interest->icon)
                    <x-theme-icon :name="$interest->icon" class="interest-icon" />
                @endif
                <span>
                    <span class="interest-name">{{ $interest->t('name') }}</span>
                    @if ($description)
                        <span class="interest-description">{{ $description }}</span>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>
</section>
