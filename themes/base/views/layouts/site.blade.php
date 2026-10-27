{{--
    Layout of the public pages.
    Variables: $page (page metadata), $layout (shared data), $theme (active theme).
--}}
<!DOCTYPE html>
<html lang="{{ $layout->locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>{!! \App\Site\Security\Csp::JS_FLAG !!}</script>
    @include('theme::partials.head')
    @vite($theme->entrypoints, $theme->buildDirectory())
</head>
<body id="top" @class(['page-cv' => $page->isCv, 'page-detail' => ! $page->isCv])>
    <a class="skip-link" href="#content">{{ __('site.a11y.skip_to_content') }}</a>

    <div class="site">
        @include('theme::partials.sidebar')

        <div class="site-body">
            <main id="content" class="site-main" tabindex="-1">
                @yield('content')
            </main>

            @include('theme::partials.footer')
        </div>
    </div>

    <a href="#top" class="back-to-top" title="{{ __('site.a11y.back_to_top') }}">
        <x-theme-icon name="arrow-up" />
        <span class="visually-hidden">{{ __('site.a11y.back_to_top') }}</span>
    </a>

    {{-- Description for external links, which the script opens in a new tab --}}
    <span id="new-tab-hint" hidden>{{ __('site.a11y.opens_new_tab') }}</span>
</body>
</html>
