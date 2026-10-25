@php
    $certifications = $cv->certifications();
    $badges = $certifications->filter(fn ($certification) => $certification->badge && $images->url($certification->badge));
@endphp
<section id="certifications" class="cv-section" aria-labelledby="certifications-title">
    @include('theme::partials.section-heading', ['key' => 'certifications', 'icon' => 'certificate'])

    @if ($badges->isNotEmpty())
        <ul class="badges">
            @foreach ($badges as $certification)
                @php $badge = $images->attributes($certification->badge); @endphp
                <li>
                    @if ($certification->verification_url)
                        <a href="{{ $certification->verification_url }}" rel="noopener">
                            <img @foreach ($badge as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach alt="{{ $certification->t('title') }}" loading="lazy" decoding="async">
                        </a>
                    @else
                        <img @foreach ($badge as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach alt="{{ $certification->t('title') }}" loading="lazy" decoding="async">
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <ul class="certifications">
        @foreach ($certifications as $certification)
            @php $title = $certification->t('title'); @endphp
            <li class="certification">
                <div class="certification-main">
                    <h3 class="certification-title">{{ $title }}</h3>
                    <p class="meta">
                        <span class="tag">{{ __('site.certifications.types.'.$certification->type->value) }}</span>
                        @if ($certification->organization)
                            <span class="meta-item">@include('theme::partials.organization-name', ['organization' => $certification->organization])</span>
                        @endif
                        @if ($certification->issued_on)
                            <span class="meta-item"><x-theme-icon name="calendar" /> <time datetime="{{ $dates->machine($certification->issued_on) }}">{{ $dates->monthYear($certification->issued_on) }}</time></span>
                        @endif
                        @if ($certification->expires_on)
                            <span class="meta-item">{{ __('site.certifications.expires') }}: <time datetime="{{ $dates->machine($certification->expires_on) }}">{{ $dates->monthYear($certification->expires_on) }}</time></span>
                        @endif
                    </p>
                    @if ($certification->credential_id)
                        <p class="small-print">{{ __('site.certifications.credential_id') }}: {{ $certification->credential_id }}</p>
                    @endif
                </div>
                @if ($certification->verification_url || $certification->file)
                    <p class="certification-links">
                        @if ($certification->verification_url)
                            <a href="{{ $certification->verification_url }}" rel="noopener"><x-theme-icon name="verified" /> {{ __('site.actions.verify') }}<span class="visually-hidden">: {{ $title }}</span></a>
                        @endif
                        @if ($certification->file && ($file = $images->url($certification->file)))
                            <a href="{{ $file }}" type="application/pdf"><x-theme-icon name="file-pdf" /> PDF<span class="visually-hidden">: {{ $title }}</span></a>
                        @endif
                    </p>
                @endif
            </li>
        @endforeach
    </ul>
</section>
