<title>{{ $page->title }}</title>
@if ($page->description)
<meta name="description" content="{{ $page->description }}">
@endif
@if ($page->robots)
<meta name="robots" content="{{ $page->robots }}">
@endif
@if ($page->canonical)
<link rel="canonical" href="{{ $page->canonical }}">
@endif
@foreach ($page->alternates as $code => $url)
<link rel="alternate" hreflang="{{ $code }}" href="{{ $url }}">
@endforeach
@if ($page->xDefault())
<link rel="alternate" hreflang="x-default" href="{{ $page->xDefault() }}">
@endif
<meta property="og:type" content="{{ $page->ogType }}">
<meta property="og:title" content="{{ $page->title }}">
@if ($page->description)
<meta property="og:description" content="{{ $page->description }}">
@endif
@if ($page->canonical)
<meta property="og:url" content="{{ $page->canonical }}">
@endif
<meta property="og:site_name" content="{{ $layout->profile->full_name }}">
<meta property="og:locale" content="{{ str_replace('-', '_', $layout->locale) }}">
@foreach (array_keys($page->alternates) as $code)
@if ($code !== $layout->locale)
<meta property="og:locale:alternate" content="{{ str_replace('-', '_', $code) }}">
@endif
@endforeach
@if ($page->image)
<meta property="og:image" content="{{ $page->image }}">
@if ($page->imageAlt)
<meta property="og:image:alt" content="{{ $page->imageAlt }}">
@endif
<meta name="twitter:card" content="summary_large_image">
@else
<meta name="twitter:card" content="summary">
@endif
<meta name="theme-color" content="#16324f">
@if ($layout->feedUrl)
<link rel="alternate" type="application/rss+xml" title="{{ __('site.blog.feed') }} — {{ $layout->profile->full_name }}" href="{{ $layout->feedUrl }}">
@endif
@foreach ($page->jsonLd as $data)
<script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
