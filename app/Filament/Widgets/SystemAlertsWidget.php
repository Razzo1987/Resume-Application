<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
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
        return self::alerts() !== [];
    }

    /**
     * Alerts of the site, plus the two-step verification of the signed-in administrator when it is off: the admin
     * controls the whole server (themes and updates contain code), so a stolen password alone is enough without it.
     *
     * @return array<string, string>
     */
    public static function alerts(): array
    {
        $alerts = app(SystemStatus::class)->alerts();
        $user = auth()->user();

        if ($user instanceof User && blank($user->getAttributes()['app_authentication_secret'] ?? null)) {
            $alerts['mfa_off'] = 'warning';
        }

        return $alerts;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $event = app(AutoUpdate::class)->lastEvent();

        return [
            'alerts' => self::alerts(),
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
