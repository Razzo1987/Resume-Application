<header class="site-sidebar">
    <div class="sidebar-inner">
        <div class="profile">
            @if ($page->isCv)
                <h1 class="profile-name">{{ $layout->profile->full_name }}</h1>
            @else
                <p class="profile-name"><a href="{{ $layout->homeUrl }}">{{ $layout->profile->full_name }}</a></p>
            @endif

            @if ($layout->photo)
                <img class="profile-photo" {{ $images->html($layout->photo) }} alt="{{ $layout->photoAlt }}" fetchpriority="high">
            @endif

            @include('theme::partials.profile-headlines')
        </div>

        @if ($layout->cvUrl || $layout->email || $layout->printEnabled)
            <div class="sidebar-actions">
                @if ($layout->cvUrl)
                    <a class="btn btn-accent" href="{{ $layout->cvUrl }}" type="application/pdf">
                        <x-theme-icon name="download" />
                        {{ __('site.actions.download_cv') }}
                        <span class="visually-hidden">— {{ __('site.actions.download_cv_lang', ['language' => $layout->cvLanguageName]) }}</span>
                    </a>
                @endif
                @if ($layout->email)
                    <a class="btn btn-outline-light js-cloak" href="{{ $page->isCv ? '#about' : $layout->homeUrl.'#about' }}" data-cloak-href="{{ $layout->email['href'] }}">
                        <x-theme-icon name="email" />
                        {{ __('site.actions.contact') }}
                    </a>
                @endif
                @if ($layout->printEnabled)
                    <button type="button" class="btn btn-link js-print" hidden>
                        <x-theme-icon name="print" />
                        {{ __('site.actions.print') }}
                    </button>
                @endif
            </div>
        @endif

        @if ($layout->menu)
            <nav class="site-nav" aria-label="{{ __('site.a11y.main_menu') }}">
                <ul>
                    @foreach ($layout->menu as $item)
                        <li><a href="{{ $item['url'] }}"@if ($item['current']) aria-current="true"@endif>{{ $item['title'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if ($layout->socials)
            <ul class="socials" aria-label="{{ __('site.a11y.social_links') }}">
                @foreach ($layout->socials as $social)
                    <li>
                        <a href="{{ $social['url'] }}" rel="me noopener" title="{{ $social['label'] }}">
                            <x-theme-icon :name="$social['icon']" />
                            <span class="visually-hidden">{{ $social['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        @if (count($layout->languages) > 1)
            <nav class="language-switcher" aria-label="{{ __('site.a11y.language_switcher') }}">
                <x-theme-icon name="language" />
                <ul>
                    @foreach ($layout->languages as $language)
                        <li>
                            <a href="{{ $language['url'] }}" hreflang="{{ $language['code'] }}" lang="{{ $language['code'] }}" @if ($language['current']) aria-current="true" @endif>{{ $language['name'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </div>
</header>
