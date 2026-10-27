{{-- Logo of an organization (decorative: the name is always written next to it) --}}
@if ($organization?->logo && ($logo = $images->attributes($organization->logo)))
    <img class="org-logo" {{ $images->html($logo) }} alt="" loading="lazy" decoding="async">
@endif
