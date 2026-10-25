{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $title }}</title>
        <link>{{ $link }}</link>
        <description>{{ $description }}</description>
        <language>{{ $locale }}</language>
        <atom:link href="{{ $self }}" rel="self" type="application/rss+xml"/>
@if ($updated)
        <lastBuildDate>{{ $updated->toRssString() }}</lastBuildDate>
@endif
@foreach ($items as $item)
        <item>
            <title>{{ $item['title'] }}</title>
            <link>{{ $item['link'] }}</link>
            <guid isPermaLink="true">{{ $item['link'] }}</guid>
@if ($item['date'])
            <pubDate>{{ $item['date']->toRssString() }}</pubDate>
@endif
            <description>{{ $item['description'] }}</description>
        </item>
@endforeach
    </channel>
</rss>
