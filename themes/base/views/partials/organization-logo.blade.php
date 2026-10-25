{{-- Logo di un'organizzazione (decorativo: il nome è sempre scritto accanto) --}}
@if ($organization?->logo && ($logo = $images->attributes($organization->logo)))
    <img class="org-logo" {{ $images->html($logo) }} alt="" loading="lazy" decoding="async">
@endif
