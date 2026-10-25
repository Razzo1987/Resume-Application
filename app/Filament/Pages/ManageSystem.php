<?php

namespace App\Filament\Pages;

use App\Support\System\Backup;
use App\Support\System\CoreProbe;
use App\Support\System\Htaccess;
use App\Support\System\Maintenance;
use App\Support\System\OrphanFiles;
use App\Support\System\SystemStatus;
use App\Support\System\Updater;
use App\Support\Update\PackageInspector;
use App\Support\Update\ReleaseChecker;
use App\Support\Update\ReleaseDownloader;
use App\Support\Update\UpdateRunner;
use App\Support\Update\UpdateStorage;
use App\Themes\ThemeManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Sistema: versione, aggiornamenti (controllo su GitHub, download o caricamento del pacchetto, installazione),
 * completamento degli aggiornamenti, manutenzione, cache, .htaccess, protezione di core/, file orfani,
 * esportazione e importazione dei contenuti.
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
     * Ritorno dalla pagina di estrazione (?update=finish): si completa subito l'aggiornamento.
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
            ...$this->updateViewData(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateViewData(): array
    {
        $releases = app(ReleaseChecker::class);
        $storage = app(UpdateStorage::class);
        $status = $releases->status();
        $available = $releases->available();

        return [
            'updateCheck' => $status,
            'updateAvailable' => $available,
            'updateNotes' => $available !== null && $available->notes !== ''
                ? new HtmlString(Str::markdown($available->notes, ['html_input' => 'strip', 'allow_unsafe_links' => false]))
                : null,
            'updateRepository' => (string) config('resume.updates.repository'),
            'updateTokenSource' => $storage->tokenSource(),
            'updatePackages' => $storage->packages(),
            'updateSupported' => app(PackageInspector::class)->isPackagedInstall(),
            'updateUploadUrl' => route('filament.admin.update-upload'),
        ];
    }

    /**
     * Scarica il blocco successivo del pacchetto della nuova versione (chiamato a ripetizione dalla pagina).
     *
     * @return array{received?: int, total?: int, done?: bool, file?: ?string, error?: string}
     */
    public function downloadChunk(): array
    {
        $release = app(ReleaseChecker::class)->available();

        if ($release === null) {
            return ['error' => (string) __('admin.updates.errors.no_release')];
        }

        try {
            return app(ReleaseDownloader::class)->next($release);
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    public function checkUpdatesAction(): Action
    {
        return Action::make('checkUpdates')
            ->label(__('admin.updates.check_now'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->size('sm')
            ->action(function (): void {
                $status = app(ReleaseChecker::class)->check();
                $available = app(ReleaseChecker::class)->available();

                $notification = match (true) {
                    $status['error'] !== null => Notification::make()->danger()->title(__('admin.updates.check_failed'))->body($status['error']),
                    $available !== null => Notification::make()->info()->title(__('admin.updates.available', ['version' => $available->version])),
                    default => Notification::make()->success()->title(__('admin.updates.up_to_date')),
                };

                $notification->send();
            });
    }

    public function githubTokenAction(): Action
    {
        return Action::make('githubToken')
            ->label(__('admin.updates.token'))
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->size('sm')
            ->modalDescription(new HtmlString(__('admin.updates.token_help', ['repository' => e((string) config('resume.updates.repository'))])))
            ->schema([
                TextInput::make('token')
                    ->label(__('admin.updates.token'))
                    ->password()
                    ->revealable()
                    ->placeholder(fn (): string => app(UpdateStorage::class)->tokenSource() !== null ? __('admin.updates.token_saved') : '')
                    ->helperText(fn (): ?string => app(UpdateStorage::class)->tokenSource() === 'env' ? __('admin.updates.token_env') : null),
                Toggle::make('remove')
                    ->label(__('admin.updates.token_remove'))
                    ->visible(fn (): bool => app(UpdateStorage::class)->tokenSource() === 'admin'),
            ])
            ->action(function (array $data): void {
                $storage = app(UpdateStorage::class);

                if ($data['remove'] ?? false) {
                    $storage->setToken(null);
                } elseif (filled($data['token'] ?? null)) {
                    $storage->setToken((string) $data['token']);
                }

                Notification::make()->success()->title(__('admin.messages.saved'))->send();
            });
    }

    /**
     * Controlli sul pacchetto e, se va tutto bene, avvio dell'aggiornamento.
     */
    public function installAction(): Action
    {
        return Action::make('install')
            ->label(__('admin.updates.install'))
            ->icon(Heroicon::OutlinedArrowDownCircle)
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => __('admin.updates.install_heading', ['file' => (string) ($arguments['package'] ?? '')]))
            ->modalContent(function (array $arguments) {
                $path = app(UpdateStorage::class)->package((string) ($arguments['package'] ?? ''));

                return view('filament.pages.system-update-check', [
                    'report' => $path !== null ? app(PackageInspector::class)->inspect($path) : null,
                ]);
            })
            ->modalSubmitActionLabel(__('admin.updates.install_submit'))
            ->action(function (array $arguments): void {
                $path = app(UpdateStorage::class)->package((string) ($arguments['package'] ?? ''));

                try {
                    if ($path === null) {
                        throw new RuntimeException((string) __('admin.updates.problems.not_zip'));
                    }

                    $url = app(UpdateRunner::class)->start($path, static::getUrl(['update' => 'finish']));
                    $this->redirect($url);
                } catch (RuntimeException $exception) {
                    Notification::make()->danger()->title(__('admin.updates.install_failed'))->body($exception->getMessage())->persistent()->send();
                }
            });
    }

    public function deletePackageAction(): Action
    {
        return Action::make('deletePackage')
            ->label(__('admin.updates.delete_package'))
            ->icon(Heroicon::OutlinedTrash)
            ->iconButton()
            ->color('gray')
            ->size('sm')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                app(UpdateStorage::class)->deletePackage((string) ($arguments['package'] ?? ''));
            });
    }

    /**
     * Completa l'aggiornamento (migrazioni, cache, .htaccess, versione) e, se l'ha avviato l'admin, rimette la
     * manutenzione com'era e cancella il pacchetto.
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
                            ->helperText(__('admin.system.upload_limit', ['size' => ini_get('upload_max_filesize') ?: '?', 'path' => 'core/storage/app/private/backups']))
                            ->disk('local')
                            ->directory('restore-uploads')
                            ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                            ->maxSize(50 * 1024)
                            ->required(fn (Get $get): bool => blank($get('existing'))),
                        Select::make('existing')
                            ->label(__('admin.system.restore_existing'))
                            ->options(fn (): array => app(Backup::class)->available())
                            ->placeholder(__('admin.system.restore_existing_none')),
                    ])
                    ->modalSubmitActionLabel(__('admin.system.restore_submit'))
                    ->action(function (array $data): void {
                        $backup = app(Backup::class);
                        $disk = Storage::disk('local');
                        $upload = filled($data['package'] ?? null) ? (string) $data['package'] : null;
                        $path = $upload !== null ? $disk->path($upload) : $backup->find((string) ($data['existing'] ?? ''));

                        try {
                            if ($path === null) {
                                throw new RuntimeException((string) __('admin.system.restore_errors.not_zip'));
                            }

                            $result = $backup->restore($path);
                            $this->log = [__('admin.system.restore_done', $result)];
                            Notification::make()->success()->title(__('admin.system.restore_done', $result))->persistent()->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()->danger()->title(__('admin.system.restore_failed'))->body($exception->getMessage())->persistent()->send();
                        } finally {
                            if ($upload !== null) {
                                $disk->delete($upload);
                            }
                        }
                    }),
            ])
                ->label(__('admin.system.tools'))
                ->button()
                ->color('gray'),
        ];
    }
}
