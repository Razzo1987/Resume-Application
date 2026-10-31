{{-- Horizontal bar ranking: $title, $items (key, label, views, icon), $filter (click = filter: "page" or a StatsQuery filter), $empty,
     $help (description), $mark (button to mark a network as "provider" or "organization") --}}
@php($max = max([1, ...array_column($items, 'views')]))
<x-filament::section compact>
    <x-slot name="heading">{{ $title }}</x-slot>
    @if ($help ?? null)
        <x-slot name="description">{{ $help }}</x-slot>
    @endif
    @if ($items)
        <ul class="resume-stats-list">
            @foreach ($items as $item)
                <li>
                    <span class="resume-stats-bar" style="width: {{ round($item['views'] / $max * 100, 1) }}%"></span>
                    @if (($filter ?? null) === 'page')
                        <button type="button" class="resume-stats-label" wire:click="filterPage(@js($item['key']))" title="{{ $item['label'] }}">{{ $item['icon'] ?? '' }}{{ $item['label'] }}</button>
                    @elseif ($filter ?? null)
                        <button type="button" class="resume-stats-label" wire:click="addFilter(@js($filter), @js($item['key']))" title="{{ $item['label'] }}">{{ $item['icon'] ?? '' }}{{ $item['label'] }}</button>
                    @else
                        <span class="resume-stats-label" title="{{ $item['label'] }}">{{ $item['icon'] ?? '' }}{{ $item['label'] }}</span>
                    @endif
                    @if ($mark ?? null)
                        <button type="button" class="resume-stats-mark" wire:click="markNetwork(@js($item['key']), @js($mark))" title="{{ __('admin.stats.networks.mark_'.$mark) }}" aria-label="{{ __('admin.stats.networks.mark_'.$mark).': '.$item['label'] }}">{{ $icons->svg($mark === 'provider' ? 'router' : 'building') }}</button>
                    @endif
                    <span class="resume-stats-value">{{ \Illuminate\Support\Number::format($item['views']) }}</span>
                </li>
            @endforeach
        </ul>
    @else
        <p class="resume-stats-empty">{{ $empty ?? __('admin.stats.lists.empty') }}</p>
    @endif
</x-filament::section>
