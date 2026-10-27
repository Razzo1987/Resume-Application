<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\ManageSystem;
use App\Support\Cron\Cron;
use App\Support\System\Backup;
use App\Support\System\Updater;
use App\Support\Update\AutoUpdate;
use App\Support\Update\ReleaseChecker;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard, gruppo Sistema: versione e aggiornamenti, cron, aggiornamenti automatici, ultima copia dei contenuti.
 */
class SystemOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return __('admin.dashboard.system');
    }

    protected function getStats(): array
    {
        $updater = app(Updater::class);
        $available = app(ReleaseChecker::class)->available();
        $cron = app(Cron::class);
        $auto = app(AutoUpdate::class);
        $event = $auto->lastEvent();
        $backup = app(Backup::class)->lastCreatedAt();
        $system = ManageSystem::getUrl();

        return [
            Stat::make(__('admin.widgets.version'), $updater->codeVersion())
                ->description($available !== null ? __('admin.dashboard.update_available', ['version' => $available->version]) : 'Laravel '.app()->version().' · PHP '.PHP_VERSION)
                ->descriptionIcon($available !== null ? 'heroicon-m-arrow-down-circle' : null)
                ->color($available !== null ? 'info' : 'gray')
                ->url($system),
            Stat::make(__('admin.dashboard.cron'), $cron->isActive() ? __('admin.dashboard.cron_active') : __('admin.dashboard.cron_missing'))
                ->description($cron->isActive() && $cron->lastCron() !== null
                    ? __('admin.cron.last', ['when' => $cron->lastCron()->diffForHumans()])
                    : __('admin.dashboard.cron_missing_help'))
                ->color($cron->isActive() ? 'success' : 'warning')
                ->url($system.'#cron'),
            Stat::make(__('admin.updates.auto.title'), __('admin.updates.auto.short.'.$auto->level()))
                ->description($event !== null
                    ? __('admin.updates.auto.last_event.'.$event['type'], ['version' => $event['version'], 'when' => $event['at']->diffForHumans()])
                    : __('admin.updates.auto.descriptions.'.$auto->level()))
                ->color($event !== null && $event['type'] === 'failed' ? 'danger' : 'gray')
                ->url($system),
            Stat::make(__('admin.dashboard.backup'), $backup !== null ? $backup->translatedFormat('j M Y') : __('admin.dashboard.never'))
                ->description($backup === null || $backup->lt(now()->subDays(30)) ? __('admin.dashboard.backup_old') : __('admin.dashboard.backup_help'))
                ->color($backup === null || $backup->lt(now()->subDays(30)) ? 'warning' : 'gray')
                ->url($system),
        ];
    }
}
