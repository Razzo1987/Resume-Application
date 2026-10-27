<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\Statistics;
use App\Support\Settings\Settings;
use App\Support\Stats\Aggregator;
use App\Support\Stats\PageResolver;
use App\Support\Stats\StatsQuery;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use Locale;

/**
 * Dashboard, gruppo Statistiche: visite e download degli ultimi 7 giorni (con l'andamento e il confronto con i 7
 * precedenti), pagina più vista e provenienza principale degli ultimi 30 giorni.
 */
class StatisticsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return __('admin.dashboard.statistics');
    }

    protected function getStats(): array
    {
        $url = Statistics::getUrl();

        if (! app(Settings::class)->bool('stats.enabled')) {
            return [
                Stat::make(__('admin.stats.title'), __('admin.dashboard.stats_off'))
                    ->description(__('admin.dashboard.stats_off_help'))
                    ->descriptionIcon('heroicon-m-arrow-right')
                    ->url($url),
            ];
        }

        $today = StatsQuery::today();
        rescue(fn () => app(Aggregator::class)->aggregate($today), report: false);

        $week = new StatsQuery($today->copy()->subDays(6), $today);
        $previous = new StatsQuery($today->copy()->subDays(13), $today->copy()->subDays(7));
        $month = StatsQuery::forPeriod('30');
        $timeline = (new StatsQuery($today->copy()->subDays(13), $today))->timeline();
        $totals = $week->totals();
        $before = $previous->totals();
        $topPage = array_key_first($month->pages(1));
        $topSource = array_key_first($month->top('referrer', 1));
        $topCountry = array_key_first($month->top('country', 1));

        return [
            Stat::make(__('admin.dashboard.views_week'), Number::format($totals['views']))
                ->description($this->change($totals['views'], $before['views']))
                ->color($totals['views'] >= $before['views'] ? 'success' : 'warning')
                ->chart(array_map(floatval(...), $timeline['views']))
                ->url($url.'?period=7'),
            Stat::make(__('admin.dashboard.downloads_week'), Number::format($totals['downloads']))
                ->description($this->change($totals['downloads'], $before['downloads']))
                ->chart(array_map(floatval(...), $timeline['downloads']))
                ->chartColor('warning')
                ->url($url.'?period=7&page_filter='.StatsQuery::DOWNLOAD),
            Stat::make(__('admin.dashboard.top_page'), $topPage !== null ? app(PageResolver::class)->label((string) $topPage) : '-')
                ->description(__('admin.dashboard.last_30_days'))
                ->url($url),
            Stat::make(__('admin.dashboard.top_source'), $topSource !== null ? (string) $topSource : __('admin.dashboard.direct'))
                ->description($topCountry !== null ? __('admin.dashboard.top_country', ['country' => Locale::getDisplayRegion('-'.$topCountry, app()->getLocale()) ?: $topCountry]) : __('admin.dashboard.last_30_days'))
                ->url($url),
        ];
    }

    private function change(int $now, int $before): string
    {
        if ($before === 0) {
            return __('admin.dashboard.vs_previous_none');
        }

        $percent = (int) round(($now - $before) / $before * 100);

        return __('admin.dashboard.vs_previous', ['change' => ($percent > 0 ? '+' : '').$percent.'%']);
    }
}
