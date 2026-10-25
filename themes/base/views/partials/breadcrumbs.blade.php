@if ($page->breadcrumbs)
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <ol>
            @foreach ($page->breadcrumbs as $crumb)
                <li>
                    @if ($crumb['url'])
                        <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                    @else
                        <span aria-current="page">{{ $crumb['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
