<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Support\Stats\StatsQuery;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Reactive;

/**
 * Visualizzazioni e download del CV per giorno (pagina Statistiche e dashboard).
 */
class StatsTimelineChart extends ChartWidget
{
    #[Reactive]
    public string $period = '30';

    #[Reactive]
    public string $pageFilter = '';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected string $color = 'info';

    protected static bool $isDiscovered = false;

    public function getHeading(): string
    {
        return (string) __('admin.stats.timeline');
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $timeline = StatsQuery::forPeriod($this->period, $this->pageFilter)->timeline();
        $long = count($timeline['labels']) > 120;

        $datasets = [[
            'label' => (string) __($this->pageFilter === StatsQuery::DOWNLOAD ? 'admin.stats.downloads' : 'admin.stats.views'),
            'data' => $timeline['views'],
            'fill' => true,
            'tension' => 0.25,
            'pointRadius' => $long ? 0 : 2,
            // disegnata per prima: il riempimento non deve coprire la linea dei download
            'order' => 2,
        ]];

        if ($this->pageFilter === '') {
            $datasets[] = [
                'label' => (string) __('admin.stats.downloads'),
                'data' => $timeline['downloads'],
                'borderColor' => 'rgb(245, 158, 11)',
                'pointBackgroundColor' => 'rgb(245, 158, 11)',
                'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                'tension' => 0.25,
                'pointRadius' => $long ? 0 : 2,
                'order' => 1,
            ];
        }

        return [
            'labels' => array_map(fn (string $day): string => Carbon::parse($day)->translatedFormat($long ? 'M y' : 'j M'), $timeline['labels']),
            'datasets' => $datasets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            'plugins' => ['legend' => ['display' => $this->pageFilter === '', 'reverse' => true]],
            'interaction' => ['intersect' => false, 'mode' => 'index'],
        ];
    }
}
