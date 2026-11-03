<?php

namespace App\Filament\Pages;

use App\Support\Cron\Cron;
use App\Support\Settings\Settings;
use App\Support\Update\AutoUpdate;
use App\Support\Update\GitHub;
use App\Support\Update\GitHubKey;
use App\Support\Update\GitHubKeys;
use App\Support\Update\PackageInspector;
use App\Support\Update\ReleaseChecker;
use App\Support\Update\ReleaseDownloader;
use App\Support\Update\UpdateRunner;
use App\Support\Update\UpdateSource;
use App\Support\Update\UpdateSources;
use App\Support\Update\UpdateStorage;
use App\Themes\ThemeException;
use App\Themes\ThemeInstaller;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Updates: one place for everything that can be updated (the app and the installed themes), with the state of each
 * one, where it comes from (public or private repository) and which GitHub key it uses; the keys themselves;
 * the update of the app (download or upload of the package, checks, installation, automatic updates).
 */
class ManageUpdates extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?int $navigationSort = 95;

    protected static ?string $slug = 'updates';

    protected string $view = 'filament.pages.updates';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.site');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.updates.title');
    }

    /**
     * Number of updates available (and things to fix) next to the menu item.
     */
    public static function getNavigationBadge(): ?string
    {
        $counts = self::counts();
        $count = $counts['available'] + $counts['keys'];

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::counts()['keys'] > 0 ? 'danger' : 'warning';
    }

    /**
     * Counts for the menu badge, once per request (the menu is drawn on every admin page).
     *
     * @return array{available: int, problems: int, keys: int}
     */
    private static function counts(): array
    {
        return once(function (): array {
            try {
                return app(UpdateSources::class)->counts();
            } catch (Throwable) {
                return ['available' => 0, 'problems' => 0, 'keys' => 0];
            }
        });
    }

    public function getTitle(): string
    {
        return __('admin.pages.updates.title');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $sources = app(UpdateSources::class);
        $releases = app(ReleaseChecker::class);
        $available = $releases->available();

        return [
            'sources' => $sources->all(),
            'checkedAt' => $sources->checkedAt(),
            'keys' => app(GitHubKeys::class)->all(),
            'keyUse' => $this->keyUse($sources->all()),
            'appRelease' => $available,
            'appNotes' => $available !== null && $available->notes !== ''
                ? new HtmlString(Str::markdown($available->notes, ['html_input' => 'strip', 'allow_unsafe_links' => false]))
                : null,
            'packages' => app(UpdateStorage::class)->packages(),
            'packagedInstall' => app(PackageInspector::class)->isPackagedInstall(),
            'autoLevel' => app(AutoUpdate::class)->level(),
            'autoEvent' => app(AutoUpdate::class)->lastEvent(),
            'cronActive' => app(Cron::class)->isActive(),
        ];
    }

    /**
     * Which sources use each key: id => titles.
     *
     * @param  list<UpdateSource>  $sources
     * @return array<string, list<string>>
     */
    private function keyUse(array $sources): array
    {
        $use = [];

        foreach ($sources as $source) {
            if ($source->key !== null) {
                $use[$source->key->id][] = $source->title;
            }
        }

        return $use;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkAll')
                ->label(__('admin.updates.check_now'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (): void {
                    $sources = app(UpdateSources::class);
                    $sources->check();
                    $counts = $sources->counts();

                    $notification = match (true) {
                        $counts['available'] > 0 => Notification::make()->info()->title(trans_choice('admin.updates.found', $counts['available'], ['count' => $counts['available']])),
                        $counts['problems'] > 0 => Notification::make()->warning()->title(trans_choice('admin.updates.found_problems', $counts['problems'], ['count' => $counts['problems']])),
                        default => Notification::make()->success()->title(__('admin.updates.all_up_to_date')),
                    };

                    $notification->send();
                }),
            Action::make('upload')
                ->label(__('admin.updates.upload_title'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalHeading(__('admin.updates.upload_title'))
                ->modalDescription(__('admin.updates.upload_help'))
                ->modalContent(fn () => view('filament.pages.updates-upload'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('admin.themes.close')),
        ];
    }

    /**
     * After the upload from "Upload a package": an app package goes to the checks before the installation, a package
     * with themes is installed right away. Returns an error message for the upload area, or null.
     */
    public function packageUploaded(string $file): ?string
    {
        $path = app(UpdateStorage::class)->package($file);

        if ($path === null) {
            return (string) __('admin.updates.problems.not_zip');
        }

        if (self::isAppPackage($path)) {
            $this->replaceMountedAction('install', ['package' => $file]);

            return null;
        }

        try {
            $results = app(ThemeInstaller::class)->installAll($path);
            $lines = array_map(fn (array $result): string => ManageThemes::describe($result), $results);

            Notification::make()->success()
                ->title(count($lines) === 1 ? $lines[0] : __('admin.themes.installed_many', ['count' => count($lines)]))
                ->body(count($lines) > 1 ? new HtmlString(implode('<br>', array_map('e', $lines))) : null)
                ->send();
            $this->unmountAction();

            return null;
        } catch (ThemeException $exception) {
            return $exception->getMessage();
        } finally {
            File::delete($path);
        }
    }

    /**
     * The app's packages have core/VERSION; anything else is treated as a package of themes.
     */
    public static function isAppPackage(string $path): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return false;
        }

        $found = $zip->locateName('core/VERSION') !== false;
        $zip->close();

        return $found;
    }

    /**
     * Downloads the next chunk of the new version's package (called repeatedly by the page).
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

    /**
     * Checks the package and, if everything is fine, starts the update.
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

                    // Back to the System page, which completes the update and shows what was done
                    $url = app(UpdateRunner::class)->start($path, ManageSystem::getUrl(['update' => 'finish']));
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

    public function updateThemeAction(): Action
    {
        return ManageThemes::themeUpdateAction('updateTheme');
    }

    public function autoUpdatesAction(): Action
    {
        return Action::make('autoUpdates')
            ->label(__('admin.updates.auto.change'))
            ->link()
            ->size('sm')
            ->fillForm(fn (): array => ['level' => app(AutoUpdate::class)->level()])
            ->modalHeading(__('admin.updates.auto.title'))
            ->modalDescription(__('admin.updates.auto.help'))
            ->schema([
                Radio::make('level')
                    ->hiddenLabel()
                    ->options([
                        AutoUpdate::OFF => __('admin.updates.auto.levels.off'),
                        AutoUpdate::DOWNLOAD => __('admin.updates.auto.levels.download'),
                        AutoUpdate::PATCH => __('admin.updates.auto.levels.patch'),
                    ])
                    ->descriptions([
                        AutoUpdate::OFF => __('admin.updates.auto.descriptions.off'),
                        AutoUpdate::DOWNLOAD => __('admin.updates.auto.descriptions.download'),
                        AutoUpdate::PATCH => __('admin.updates.auto.descriptions.patch'),
                    ])
                    ->required(),
            ])
            ->action(function (array $data): void {
                app(Settings::class)->set(AutoUpdate::SETTING, (string) $data['level']);
                Notification::make()->success()->title(__('admin.messages.saved'))->send();
            });
    }

    /**
     * Adds a key (arguments: scope, suggested from a repository that needs it) or changes one (arguments: key).
     */
    public function keyAction(): Action
    {
        return Action::make('key')
            ->label(fn (array $arguments): string => isset($arguments['key']) ? __('admin.updates.keys.edit') : __('admin.updates.keys.add'))
            ->icon(Heroicon::OutlinedKey)
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => isset($arguments['key']) ? __('admin.updates.keys.edit') : __('admin.updates.keys.add'))
            ->modalDescription(new HtmlString(__('admin.updates.keys.help')))
            ->modalWidth('2xl')
            ->fillForm(function (array $arguments): array {
                $key = isset($arguments['key']) ? app(GitHubKeys::class)->find((string) $arguments['key']) : null;

                return $key !== null
                    ? ['label' => $key->label, 'scopes' => $key->scopes]
                    : ['label' => '', 'scopes' => isset($arguments['scope']) ? [(string) $arguments['scope']] : []];
            })
            ->schema(fn (array $arguments): array => [
                TextInput::make('label')
                    ->label(__('admin.updates.keys.label'))
                    ->placeholder(__('admin.updates.keys.label_placeholder'))
                    ->maxLength(60),
                TagsInput::make('scopes')
                    ->label(__('admin.updates.keys.scopes'))
                    ->placeholder(__('admin.updates.keys.scopes_placeholder'))
                    ->helperText(__('admin.updates.keys.scopes_help'))
                    ->suggestions(fn (): array => $this->scopeSuggestions())
                    ->nestedRecursiveRules(['regex:'.GitHubKey::SCOPE_PATTERN])
                    ->required(),
                TextInput::make('token')
                    ->label(__('admin.updates.keys.token'))
                    ->password()
                    ->revealable()
                    ->autocomplete('off')
                    ->placeholder(isset($arguments['key']) ? __('admin.updates.keys.token_keep') : 'github_pat_...')
                    ->required(! isset($arguments['key'])),
            ])
            ->modalSubmitActionLabel(__('admin.updates.keys.save'))
            ->action(function (array $data, array $arguments, Action $action): void {
                try {
                    $key = app(GitHubKeys::class)->save(
                        isset($arguments['key']) ? (string) $arguments['key'] : null,
                        (string) ($data['label'] ?? ''),
                        array_values(array_map('strval', (array) ($data['scopes'] ?? []))),
                        filled($data['token'] ?? null) ? (string) $data['token'] : null,
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                    $action->halt();

                    return;
                }

                $this->verifyAndReport($key);
            });
    }

    public function verifyKeyAction(): Action
    {
        return Action::make('verifyKey')
            ->label(__('admin.updates.keys.verify'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('gray')
            ->size('sm')
            ->action(function (array $arguments): void {
                $key = app(GitHubKeys::class)->find((string) ($arguments['key'] ?? ''));

                if ($key !== null) {
                    $this->verifyAndReport($key);
                }
            });
    }

    public function deleteKeyAction(): Action
    {
        return Action::make('deleteKey')
            ->label(__('admin.updates.keys.delete'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->iconButton()
            ->size('sm')
            ->requiresConfirmation()
            ->modalDescription(__('admin.updates.keys.delete_help'))
            ->action(function (array $arguments): void {
                app(GitHubKeys::class)->delete((string) ($arguments['key'] ?? ''));
                Notification::make()->success()->title(__('admin.updates.keys.deleted'))->send();
            });
    }

    /**
     * Checks a key on GitHub (valid, expiration, repositories it can read), then checks the updates again so the
     * states of the page follow, and says what was found.
     */
    private function verifyAndReport(GitHubKey $key): void
    {
        $sources = app(UpdateSources::class);
        $result = app(GitHub::class)->verify($key, $sources->repositories());

        // Validity and expiration are saved by GitHub with the answer; the account name only here
        if ($result['login'] !== null) {
            app(GitHubKeys::class)->remember($key->id, ['login' => $result['login']]);
        }

        if (! $result['valid']) {
            Notification::make()->danger()->title(__('admin.updates.keys.not_valid', ['key' => $key->label]))->body($result['error'])->persistent()->send();

            return;
        }

        try {
            $sources->check();
        } catch (Throwable) {
            // the result of the key is shown anyway
        }

        $lines = [];

        foreach ($result['repositories'] as $repository => $readable) {
            $lines[] = e($repository).': '.($readable ? __('admin.updates.keys.can_read') : '<strong>'.e(__('admin.updates.keys.cannot_read')).'</strong>');
        }

        if ($lines === []) {
            $lines[] = e(__('admin.updates.keys.no_repositories'));
        }

        $title = $result['expires_at'] !== null
            ? __('admin.updates.keys.valid_until', ['key' => $key->label, 'date' => $result['expires_at']->translatedFormat('j F Y')])
            : __('admin.updates.keys.valid', ['key' => $key->label]);

        $notification = Notification::make()->title($title)->body(new HtmlString(implode('<br>', $lines)))->persistent();
        (in_array(false, $result['repositories'], true) ? $notification->warning() : $notification->success())->send();
    }

    /**
     * "owner/*" and every repository of the update sources.
     *
     * @return list<string>
     */
    private function scopeSuggestions(): array
    {
        $repositories = app(UpdateSources::class)->repositories();
        $owners = array_map(fn (string $repository): string => strtok($repository, '/').'/*', $repositories);

        return array_values(array_unique([...$owners, ...$repositories]));
    }
}
