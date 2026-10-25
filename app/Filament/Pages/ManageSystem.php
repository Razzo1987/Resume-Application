<?php

namespace App\Filament\Pages;

use App\Support\System\Backup;
use App\Support\System\CoreProbe;
use App\Support\System\Htaccess;
use App\Support\System\Maintenance;
use App\Support\System\OrphanFiles;
use App\Support\System\SystemStatus;
use App\Support\System\Updater;
use App\Themes\ThemeManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Sistema: versione, completamento degli aggiornamenti, manutenzione, cache, .htaccess,
 * protezione di core/, file orfani, esportazione dei contenuti.
 */
class ManageSystem extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?int $navigationSort = 95;

    protected static ?string $slug = 'system';

    protected string $view = 'filament.pages.system';

    /** @var list<string> */
    public array $log = [];

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.site');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.system.title');
    }

    public function getTitle(): string
    {
        return __('admin.pages.system.title');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $updater = app(Updater::class);
        $probe = app(CoreProbe::class);
        $orphans = app(OrphanFiles::class);

        try {
            $database = (string) DB::selectOne('select version() as version')?->version;
        } catch (Throwable) {
            $database = '—';
        }

        return [
            'codeVersion' => $updater->codeVersion(),
            'installedVersion' => $updater->installedVersion(),
            'updatePending' => $updater->isPending(),
            'maintenance' => app(Maintenance::class)->isOn(),
            'protection' => app(SystemStatus::class)->coreProtection(),
            'releaseLayout' => $probe->isReleaseLayout(),
            'htaccess' => app(Htaccess::class)->options(),
            'orphansAvailable' => $orphans->isAvailable(),
            'orphanCount' => count($orphans->find()),
            'theme' => app(ThemeManager::class)->active()->title,
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'database' => $database,
            'paths' => ['core' => base_path(), 'webroot' => public_path()],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('completeUpdate')
                ->label(__('admin.system.complete_update'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (): bool => app(Updater::class)->isPending())
                ->requiresConfirmation()
                ->modalDescription(__('admin.system.complete_update_help'))
                ->action(function (): void {
                    $this->log = app(Updater::class)->complete();
                    Notification::make()->success()->title(__('admin.system.update_completed'))->send();
                }),

            Action::make('maintenance')
                ->label(fn (): string => app(Maintenance::class)->isOn() ? __('admin.system.maintenance_off') : __('admin.system.maintenance_on'))
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->color(fn (): string => app(Maintenance::class)->isOn() ? 'warning' : 'gray')
                ->requiresConfirmation()
                ->modalDescription(__('admin.system.maintenance_help'))
                ->action(function (): void {
                    $maintenance = app(Maintenance::class);
                    $maintenance->isOn() ? $maintenance->off() : $maintenance->on();
                    Notification::make()->success()->title(__('admin.messages.saved'))->send();
                }),

            ActionGroup::make([
                Action::make('clearCache')
                    ->label(__('admin.system.clear_cache'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->action(function (): void {
                        $this->log = app(Updater::class)->clearCaches();
                        Notification::make()->success()->title(__('admin.system.cache_cleared'))->send();
                    }),

                Action::make('probe')
                    ->label(__('admin.system.check_protection'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->action(function (): void {
                        $result = app(SystemStatus::class)->coreProtection(refresh: true);
                        $notification = Notification::make()->title(__('admin.system.protection.'.$result));
                        ($result === CoreProbe::EXPOSED ? $notification->danger() : $notification->success())->send();
                    }),

                Action::make('htaccess')
                    ->label(__('admin.system.htaccess'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->visible(fn (): bool => app(CoreProbe::class)->isReleaseLayout())
                    ->fillForm(fn (): array => app(Htaccess::class)->options())
                    ->schema([
                        Toggle::make('force_https')->label(__('install.fields.force_https')),
                        Toggle::make('strip_www')->label(__('install.fields.strip_www')),
                    ])
                    ->modalDescription(__('install.help.addresses'))
                    ->action(function (array $data): void {
                        app(Htaccess::class)->write((bool) ($data['force_https'] ?? false), (bool) ($data['strip_www'] ?? false));
                        Notification::make()->success()->title(__('admin.messages.saved'))->send();
                    }),

                Action::make('orphans')
                    ->label(__('admin.system.orphans'))
                    ->icon(Heroicon::OutlinedDocumentMinus)
                    ->visible(fn (): bool => app(OrphanFiles::class)->isAvailable())
                    ->modalContent(fn () => view('filament.pages.system-orphans', ['files' => app(OrphanFiles::class)->find()]))
                    ->modalSubmitActionLabel(__('admin.system.delete_orphans'))
                    ->color('danger')
                    ->action(function (): void {
                        $orphans = app(OrphanFiles::class);
                        $deleted = $orphans->delete($orphans->find());
                        Notification::make()->success()->title(__('admin.system.orphans_deleted', ['count' => $deleted]))->send();
                    }),

                Action::make('backup')
                    ->label(__('admin.system.backup'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (): BinaryFileResponse => response()->download(app(Backup::class)->create())->deleteFileAfterSend()),

                Action::make('restore')
                    ->label(__('admin.system.restore'))
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('danger')
                    ->modalDescription(__('admin.system.restore_help'))
                    ->schema([
                        FileUpload::make('package')
                            ->label(__('admin.system.restore_file'))
                            ->disk('local')
                            ->directory('restore-uploads')
                            ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                            ->maxSize(50 * 1024)
                            ->required(),
                    ])
                    ->modalSubmitActionLabel(__('admin.system.restore_submit'))
                    ->action(function (array $data): void {
                        $path = (string) $data['package'];
                        $disk = Storage::disk('local');

                        try {
                            $result = app(Backup::class)->restore($disk->path($path));
                            $this->log = [__('admin.system.restore_done', $result)];
                            Notification::make()->success()->title(__('admin.system.restore_done', $result))->persistent()->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()->danger()->title(__('admin.system.restore_failed'))->body($exception->getMessage())->persistent()->send();
                        } finally {
                            $disk->delete($path);
                        }
                    }),
            ])
                ->label(__('admin.system.tools'))
                ->button()
                ->color('gray'),
        ];
    }
}
