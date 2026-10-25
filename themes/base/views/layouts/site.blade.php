<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <script>document.documentElement.classList.add('js')</script>
    @vite($theme->entrypoints, $theme->buildDirectory())
</head>
<body>
    <a class="visually-hidden-focusable" href="#content">{{ __('site.a11y.skip_to_content') }}</a>
    <main id="content" class="container py-5">
        @yield('content')
    </main>
</body>
</html>
