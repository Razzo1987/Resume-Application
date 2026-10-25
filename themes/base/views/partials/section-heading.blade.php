{{-- Titolo di sezione: $key (ancora), $icon (icona semantica), $title --}}
<h2 id="{{ $key }}-title" class="section-title">
    <x-theme-icon :name="$icon" class="section-icon" />
    <span>{{ $title }}</span>
</h2>
