<?php

namespace App\Filament\Pages;

use App\Support\Cron\Cron;
use App\Support\System\Backup;
use App\Support\System\CoreProbe;
use App\Support\System\Htaccess;
use App\Support\System\Maintenance;
use App\Support\System\OrphanFiles;
use App\Support\System\SystemStatus;
use App\Support\System\Updater;
use App\Support\Update\UpdateRunner;
use App\Support\Update\UpdateSources;
use App\Themes\ThemeManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * System: state of the server, summary of the updates (managed in ManageUpdates), completion of updates,
 * maintenance, cache, .htaccess, protection of core/, orphan files, content export and import.
 */
class ManageSystem extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?int $navigationSort = 95;

    protected static ?string $slug = 'system';

    protected string $view = 'filament.pages.system';

    /** @var list<string> */
    public array $log = [];

    /**
     * Return from the extraction page (?update=finish): the update is completed right away.
     */
    public function mount(): void
    {
        $runner = app(UpdateRunner::class);

        if (request()->query('update') === 'finish' && $runner->isExtracted()) {
            $this->finishUpdate();
        }
    }

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
            'sources' => app(UpdateSources::class)->all(),
            ...$this->cronViewData(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cronViewData(): array
    {
        $cron = app(Cron::class);

        return [
            'cronActive' => $cron->isActive(),
            'cronLast' => $cron->lastCron(),
            'cronPhp' => $cron->cronPhpVersion(),
            'cronCommand' => $cron->command(),
            'cronJobs' => $cron->jobs(),
            'phpRequired' => (string) (json_decode((string) @file_get_contents(base_path('manifest.json')), true)['requires']['php'] ?? '8.4.1'),
        ];
    }

    /**
     * After the upload in "Import the content": the new export is chosen in the list of the form.
     */
    public function backupUploaded(string $file): ?string
    {
        if (app(Backup::class)->find($file) === null) {
            return (string) __('admin.system.restore_errors.not_zip');
        }

        $index = array_key_last($this->mountedActions);

        if ($index !== null) {
            $this->mountedActions[$index]['data']['existing'] = $file;
        }

        return null;
    }

    /**
     * Completes the update (migrations, cache, .htaccess, version) and, if the admin started it, restores the
     * maintenance mode to how it was and deletes the package.
     */
    private function finishUpdate(): void
    {
        $this->log = app(UpdateRunner::class)->finish();
        $orphans = app(OrphanFiles::class);
        $count = $orphans->isAvailable() ? count($orphans->find()) : 0;

        Notification::make()
            ->success()
            ->title(__('admin.system.update_completed'))
            ->body($count > 0 ? __('admin.updates.orphans_hint', ['count' => $count]) : null)
            ->persistent()
            ->send();
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
                ->action(fn () => $this->finishUpdate()),

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

                Action::make('testMail')
                    ->label(__('admin.system.test_mail'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->requiresConfirmation()
                    ->modalIcon(Heroicon::OutlinedEnvelope)
                    ->modalDescription(fn (): string => __('admin.system.test_mail_help', [
                        'email' => (string) auth()->user()?->email,
                        'mailer' => (string) config('mail.default'),
                        'from' => (string) config('mail.from.address'),
                    ]))
                    ->action(function (): void {
                        $email = (string) auth()->user()?->email;

                        try {
                            Mail::raw((string) __('admin.system.test_mail_body', ['site' => url('/')]), fn ($message) => $message
                                ->to($email)
                                ->subject('['.config('app.name').'] '.__('admin.system.test_mail_subject')));
                        } catch (Throwable $exception) {
                            Notification::make()->danger()->title(__('admin.system.test_mail_failed'))->body($exception->getMessage())->persistent()->send();

                            return;
                        }

                        Notification::make()->success()->title(__('admin.system.test_mail_sent', ['email' => $email]))->persistent()->send();
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
                    // Chunked upload: the export lands among those on the server and is chosen in the list below
                    ->modalContent(fn () => view('filament.pages.system-restore-upload'))
                    ->schema([
                        Select::make('existing')
                            ->label(__('admin.system.restore_existing'))
                            ->options(fn (): array => app(Backup::class)->available())
                            ->placeholder(__('admin.system.restore_existing_none'))
                            ->helperText(__('admin.system.restore_server', ['path' => 'core/storage/app/private/backups']))
                            ->required(),
                    ])
                    ->modalSubmitActionLabel(__('admin.system.restore_submit'))
                    ->action(function (array $data): void {
                        $backup = app(Backup::class);
                        $path = $backup->find((string) ($data['existing'] ?? ''));

                        try {
                            if ($path === null) {
                                throw new RuntimeException((string) __('admin.system.restore_errors.not_zip'));
                            }

                            $result = $backup->restore($path);
                            $this->log = [__('admin.system.restore_done', $result)];
                            Notification::make()->success()->title(__('admin.system.restore_done', $result))->persistent()->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()->danger()->title(__('admin.system.restore_failed'))->body($exception->getMessage())->persistent()->send();
                        }
                    }),
            ])
                ->label(__('admin.system.tools'))
                ->button()
                ->color('gray'),
        ];
    }
}
