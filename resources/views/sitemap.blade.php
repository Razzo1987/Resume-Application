{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($entries as $entry)
@foreach ($entry['alternates'] as $code => $url)
    <url>
        <loc>{{ $url }}</loc>
@if ($entry['lastmod'])
        <lastmod>{{ $entry['lastmod']->toAtomString() }}</lastmod>
@endif
@foreach ($entry['alternates'] as $alternateCode => $alternateUrl)
        <xhtml:link rel="alternate" hreflang="{{ $alternateCode }}" href="{{ $alternateUrl }}"/>
@endforeach
@if (isset($entry['alternates'][$default]))
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $entry['alternates'][$default] }}"/>
@endif
    </url>
@endforeach
@endforeach
</urlset>
