{{-- Horizontal bar ranking: $title, $items (key, label, views), $filter (click = filter: "page" or a StatsQuery filter), $empty --}}
@php($max = max([1, ...array_column($items, 'views')]))
<x-filament::section compact>
    <x-slot name="heading">{{ $title }}</x-slot>
    @if ($items)
        <ul class="resume-stats-list">
            @foreach ($items as $item)
                <li>
                    <span class="resume-stats-bar" style="width: {{ round($item['views'] / $max * 100, 1) }}%"></span>
                    @if (($filter ?? null) === 'page')
                        <button type="button" class="resume-stats-label" wire:click="filterPage(@js($item['key']))" title="{{ $item['label'] }}">{{ $item['label'] }}</button>
                    @elseif ($filter ?? null)
                        <button type="button" class="resume-stats-label" wire:click="addFilter(@js($filter), @js($item['key']))" title="{{ $item['label'] }}">{{ $item['label'] }}</button>
                    @else
                        <span class="resume-stats-label" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                    @endif
                    <span class="resume-stats-value">{{ \Illuminate\Support\Number::format($item['views']) }}</span>
                </li>
            @endforeach
        </ul>
    @else
        <p class="resume-stats-empty">{{ $empty ?? __('admin.stats.lists.empty') }}</p>
    @endif
</x-filament::section>
