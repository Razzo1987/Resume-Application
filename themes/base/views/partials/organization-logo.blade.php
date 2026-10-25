{{-- Logo di un'organizzazione (decorativo: il nome è sempre scritto accanto) --}}
@if ($organization?->logo && ($logo = $images->attributes($organization->logo)))
    <img class="org-logo" @foreach ($logo as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach alt="" loading="lazy" decoding="async">
@endif
