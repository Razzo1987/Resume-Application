<?php

namespace App\Filament\Pages;

use App\Support\Settings\Settings;
use App\Support\System\Updater;
use App\Themes\Compatibility;
use App\Themes\Theme;
use App\Themes\ThemeException;
use App\Themes\ThemeInstaller;
use App\Themes\ThemeManager;
use App\Themes\ThemeUpdates;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Themes: list of the installed themes, activation, installation or update from a zip, removal.
 */
class ManageThemes extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 94;

    protected static ?string $slug = 'themes';

    protected string $view = 'filament.pages.themes';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.site');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.themes.title');
    }

    public function getTitle(): string
    {
        return __('admin.pages.themes.title');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $themes = app(ThemeManager::class);
        $version = app(Updater::class)->codeVersion();
        $active = $themes->active()->name;
        $updates = app(ThemeUpdates::class);
        $status = $updates->status();
        $available = $updates->available();

        return [
            'active' => $active,
            'activeVariant' => $themes->active()->variant,
            'configured' => $themes->activeName(),
            'updatesChecked' => $status['checked_at'],
            'hasUpdatable' => $updates->updatable() !== [],
            'themes' => collect(array_keys($themes->installed()))
                ->map(fn (string $name): Theme => $themes->get($name))
                ->sortBy(fn (Theme $theme): string => ($theme->name === $active ? '0' : '1').$theme->title)
                ->map(fn (Theme $theme): array => [
                    'theme' => $theme,
                    'assets' => $theme->name === $themes->base()->name || $theme->hasAssets($themes->publicPath()),
                    'compatible' => $theme->requires === null || Compatibility::satisfies($version, $theme->requires),
                    'base' => $theme->name === $themes->base()->name,
                    'update' => $available[$theme->name] ?? null,
                    'updateError' => $status['errors'][$theme->name] ?? null,
                ])
                ->values()
                ->all(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('install')
                ->label(__('admin.themes.install'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->modalDescription(__('admin.themes.install_help'))
                ->schema([
                    FileUpload::make('package')
                        ->label(__('admin.themes.package'))
                        ->helperText(__('admin.themes.upload_limit', ['size' => ini_get('upload_max_filesize') ?: '?']))
                        ->disk('local')
                        ->directory('theme-uploads')
                        ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                        ->maxSize(50 * 1024)
                        ->required(),
                ])
                ->modalSubmitActionLabel(__('admin.themes.install_submit'))
                ->action(function (array $data): void {
                    $path = (string) $data['package'];
                    $disk = Storage::disk('local');

                    try {
                        $results = app(ThemeInstaller::class)->installAll($disk->path($path));
                        $lines = array_map(fn (array $result): string => self::describe($result), $results);

                        Notification::make()->success()
                            ->title(count($lines) === 1 ? $lines[0] : __('admin.themes.installed_many', ['count' => count($lines)]))
                            ->body(count($lines) > 1 ? new HtmlString(implode('<br>', array_map('e', $lines))) : null)
                            ->send();
                    } catch (ThemeException $exception) {
                        Notification::make()->danger()->title(__('admin.themes.errors.title'))->body($exception->getMessage())->persistent()->send();
                    } finally {
                        $disk->delete($path);
                    }
                }),
            Action::make('checkUpdates')
                ->label(__('admin.themes.updates.check'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => app(ThemeUpdates::class)->updatable() !== [])
                ->action(function (): void {
                    $updates = app(ThemeUpdates::class);
                    $status = $updates->check();
                    $available = $updates->available();

                    $notification = Notification::make()->title($available === []
                        ? __('admin.themes.updates.none')
                        : trans_choice('admin.themes.updates.found', count($available), ['count' => count($available)]));

                    ($status['errors'] !== [] ? $notification->warning()->body(new HtmlString(implode('<br>', array_map('e', array_unique($status['errors']))))) : $notification->success())->send();
                }),
        ];
    }

    /**
     * Notification text for an installed theme.
     *
     * @param  array{theme: Theme, updated: bool, previous: string|null}  $result
     */
    private static function describe(array $result): string
    {
        return match (true) {
            ! $result['updated'] => __('admin.themes.installed', ['theme' => $result['theme']->title, 'version' => $result['theme']->version]),
            $result['previous'] === $result['theme']->version => __('admin.themes.reinstalled', ['theme' => $result['theme']->title, 'version' => $result['theme']->version]),
            default => __('admin.themes.updated', ['theme' => $result['theme']->title, 'from' => $result['previous'], 'to' => $result['theme']->version]),
        };
    }

    public function updateAction(): Action
    {
        return Action::make('update')
            ->label(fn (array $arguments): string => __('admin.themes.updates.install', ['version' => (string) ($arguments['version'] ?? '')]))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->size('sm')
            ->action(function (array $arguments): void {
                try {
                    $result = app(ThemeUpdates::class)->update((string) ($arguments['theme'] ?? ''));
                    Notification::make()->success()->title(self::describe($result))->send();
                } catch (ThemeException $exception) {
                    Notification::make()->danger()->title(__('admin.themes.errors.title'))->body($exception->getMessage())->persistent()->send();
                }
            });
    }

    public function activateAction(): Action
    {
        return Action::make('activate')
            ->label(__('admin.themes.activate'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->size('sm')
            ->action(function (array $arguments): void {
                $themes = app(ThemeManager::class);
                $name = (string) ($arguments['theme'] ?? '');

                if (! $themes->isInstalled($name)) {
                    return;
                }

                $theme = $themes->get($name);
                $variant = $theme->variants[(string) ($arguments['variant'] ?? '')] ?? null;

                app(Settings::class)->setMany(['site.theme' => $name, 'site.theme_variant' => $variant?->name]);
                $themes->forget();

                Notification::make()->success()->title($variant !== null
                    ? __('admin.themes.activated_variant', ['theme' => $theme->title, 'variant' => $variant->title])
                    : __('admin.themes.activated', ['theme' => $theme->title]))->send();
                $this->redirect(static::getUrl());
            });
    }

    public function removeAction(): Action
    {
        return Action::make('remove')
            ->label(__('admin.themes.remove'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalDescription(__('admin.themes.remove_help'))
            ->action(function (array $arguments): void {
                try {
                    app(ThemeInstaller::class)->remove((string) ($arguments['theme'] ?? ''));
                    Notification::make()->success()->title(__('admin.themes.removed'))->send();
                } catch (ThemeException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                }
            });
    }
}
