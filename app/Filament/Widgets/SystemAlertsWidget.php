<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Support\System\SystemStatus;
use App\Support\Update\AutoUpdate;
use App\Support\Update\ReleaseChecker;
use Filament\Widgets\Widget;

/**
 * Avvisi in cima alla dashboard: cartella core raggiungibile, aggiornamento da completare, manutenzione attiva,
 * nuova versione disponibile.
 */
class SystemAlertsWidget extends Widget
{
    protected static ?int $sort = -10;

    // Gli avvisi si vedono subito, senza caricamento differito
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.system-alerts';

    // Il controllo delle nuove versioni su GitHub è un lavoro programmato (App\Support\Cron\CronJobs): col cron
    // dell'hosting, oppure dall'admin dopo aver risposto quando il cron non c'è.
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
