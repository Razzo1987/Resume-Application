{{-- Period and duration: $start, $end, $withDuration (default true) --}}
<span class="meta-item">
    <x-theme-icon name="calendar" />
    {{ $dates->period($start, $end) }}
</span>
@if (($withDuration ?? true) && $start)
    <span class="meta-item">
        <x-theme-icon name="clock" />
        {{ $dates->duration($start, $end) }}
    </span>
@endif
