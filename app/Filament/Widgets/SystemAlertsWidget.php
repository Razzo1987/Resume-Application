<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Support\System\SystemStatus;
use App\Support\Update\AutoUpdate;
use App\Support\Update\ReleaseChecker;
use Filament\Widgets\Widget;

/**
 * Alerts at the top of the dashboard: core folder reachable, update to complete, maintenance on,
 * new version available.
 */
class SystemAlertsWidget extends Widget
{
    protected static ?int $sort = -10;

    // Alerts are shown right away, without lazy loading
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.system-alerts';

    // The check for new versions on GitHub is a scheduled job (App\Support\Cron\CronJobs): run by the hosting's
    // cron or, when there is no cron, by the admin after the response is sent.
    public static function canView(): bool
    {
        return app(SystemStatus::class)->alerts() !== [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $event = app(AutoUpdate::class)->lastEvent();

        return [
            'alerts' => app(SystemStatus::class)->alerts(),
            'params' => [
                'update_available' => ['version' => app(ReleaseChecker::class)->available()->version ?? ''],
                'update_downloaded' => ['version' => app(ReleaseChecker::class)->available()->version ?? ''],
                'auto_update_installed' => ['version' => $event['version'] ?? ''],
                'auto_update_failed' => ['version' => $event['version'] ?? ''],
            ],
            'event' => $event,
        ];
    }
}
