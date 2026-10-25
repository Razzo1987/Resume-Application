@if ($organization)
    @if ($organization->website)
        <a href="{{ $organization->website }}" rel="noopener">{{ $organization->name }}</a>
    @else
        {{ $organization->name }}
    @endif
@endif
