<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Support\System\SystemStatus;
use Filament\Widgets\Widget;

/**
 * Avvisi in cima alla dashboard: cartella core raggiungibile, aggiornamento da completare, manutenzione attiva.
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
        return app(SystemStatus::class)->alerts() !== [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['alerts' => app(SystemStatus::class)->alerts()];
    }
}
