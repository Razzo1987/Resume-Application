<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Support\System\SystemStatus;
use App\Support\Update\PackageInspector;
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

    public static function canView(): bool
    {
        $releases = app(ReleaseChecker::class);

        // Nuove versioni su GitHub: al massimo ogni 12 ore, dopo aver risposto (la dashboard non aspetta GitHub).
        // Solo per le installazioni da pacchetto: una copia di sviluppo non si aggiorna da qui.
        if ($releases->isStale() && app(PackageInspector::class)->isPackagedInstall()) {
            app()->terminating(fn () => rescue(fn () => $releases->check(), report: false));
        }

        return app(SystemStatus::class)->alerts() !== [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'alerts' => app(SystemStatus::class)->alerts(),
            'params' => ['update_available' => ['version' => app(ReleaseChecker::class)->available()->version ?? '']],
        ];
    }
}
