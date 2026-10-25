@php
    $profile = $cv->profile();
    $bio = $richText->render($profile->t('bio'));
    $location = $profile->t('location');
    $showBirthdate = $profile->birth_date && app(\App\Support\Settings\Settings::class)->bool('privacy.show_birthdate');
@endphp
<section id="about" class="cv-section" aria-labelledby="about-title">
    @include('theme::partials.section-heading', ['key' => 'about', 'icon' => 'user'])

    @if ($bio)
        <div class="rich-text lead-text">{{ $bio }}</div>
    @endif

    @if ($location || $layout->email || $layout->phone || $showBirthdate)
        <ul class="contact-list">
            @if ($location)
                <li>
                    <x-theme-icon name="location" />
                    <span class="visually-hidden">{{ __('site.contact.location') }}:</span>
                    {{ $location }}
                </li>
            @endif
            @if ($layout->email)
                <li>
                    <x-theme-icon name="email" />
                    <span class="visually-hidden">{{ __('site.contact.email') }}:</span>
                    <a class="js-cloak" href="#about" data-cloak-href="{{ $layout->email['href'] }}" data-cloak-text="{{ $layout->email['text'] }}"><span class="cloak-hint">{{ __('site.contact.reveal_hint') }}</span></a>
                </li>
            @endif
            @if ($layout->phone)
                <li>
                    <x-theme-icon name="phone" />
                    <span class="visually-hidden">{{ __('site.contact.phone') }}:</span>
                    <a class="js-cloak" href="#about" data-cloak-href="{{ $layout->phone['href'] }}" data-cloak-text="{{ $layout->phone['text'] }}"><span class="cloak-hint">{{ __('site.contact.reveal_hint') }}</span></a>
                </li>
            @endif
            @if ($showBirthdate)
                <li>
                    <x-theme-icon name="calendar" />
                    <span class="visually-hidden">{{ __('site.contact.birthdate') }}:</span>
                    <time datetime="{{ $profile->birth_date->format('Y-m-d') }}">{{ $dates->day($profile->birth_date) }}</time>
                </li>
            @endif
        </ul>
    @endif
</section>
