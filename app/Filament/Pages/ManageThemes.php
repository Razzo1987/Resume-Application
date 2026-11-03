<?php

namespace App\Filament\Pages;

use App\Support\Settings\Settings;
use App\Support\System\Updater;
use App\Support\Update\UpdateSources;
use App\Support\Update\UpdateStorage;
use App\Themes\Compatibility;
use App\Themes\Theme;
use App\Themes\ThemeException;
use App\Themes\ThemeInstaller;
use App\Themes\ThemeManager;
use App\Themes\ThemeUpdates;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\File;
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
        $sources = collect(app(UpdateSources::class)->all())->keyBy('id');

        return [
            'active' => $active,
            'activeVariant' => $themes->active()->variant,
            'configured' => $themes->activeName(),
            'themes' => collect(array_keys($themes->installed()))
                ->map(fn (string $name): Theme => $themes->get($name))
                ->sortBy(fn (Theme $theme): string => ($theme->name === $active ? '0' : '1').$theme->title)
                ->map(fn (Theme $theme): array => [
                    'theme' => $theme,
                    'assets' => $theme->name === $themes->base()->name || $theme->hasAssets($themes->publicPath()),
                    'compatible' => $theme->requires === null || Compatibility::satisfies($version, $theme->requires),
                    'base' => $theme->name === $themes->base()->name,
                    'source' => $sources->get($theme->name),
                    'preview' => self::previewUrl($theme, $theme->name === $active ? $themes->active()->variant : $theme->defaultVariant),
                    'previewMobile' => self::previewUrl($theme, $theme->name === $active ? $themes->active()->variant : $theme->defaultVariant, mobile: true),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Address of the preview image of a theme (of the variant in use, or of the default one), on a computer or on a
     * phone, or null.
     */
    private static function previewUrl(Theme $theme, ?string $variant, bool $mobile = false): ?string
    {
        if ($theme->previewFile($variant, $mobile) === null) {
            return null;
        }

        $own = $variant !== null ? ($theme->variants[$variant] ?? null) : null;
        $parameters = ['theme' => $theme->name, 'variant' => $own?->preview !== null || $own?->previewMobile !== null ? $variant : null];

        return route('filament.admin.theme-preview', array_filter($parameters)).'?'.http_build_query(array_filter(['device' => $mobile ? 'mobile' : null, 'v' => $theme->version]));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('install')
                ->label(__('admin.themes.install'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->modalDescription(__('admin.themes.install_help'))
                // Chunked upload (no upload_max_filesize limit), then installUploaded()
                ->modalContent(fn () => view('filament.pages.themes-upload'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('admin.themes.close')),
            Action::make('updates')
                ->label(__('admin.pages.updates.title'))
                ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
                ->color('gray')
                ->url(fn (): string => ManageUpdates::getUrl()),
        ];
    }

    /**
     * Installs the package uploaded in chunks from the Themes modal (one theme or several), then deletes it.
     */
    public function installUploaded(string $package): ?string
    {
        $storage = app(UpdateStorage::class);
        $path = $storage->themeUploadsDirectory().'/'.$package;

        if (preg_match('/^[A-Za-z0-9._-]{1,120}\.zip$/', $package) !== 1 || ! is_file($path)) {
            return (string) __('admin.themes.errors.not_zip');
        }

        try {
            $results = app(ThemeInstaller::class)->installAll($path);
            $lines = array_map(fn (array $result): string => self::describe($result), $results);

            Notification::make()->success()
                ->title(count($lines) === 1 ? $lines[0] : __('admin.themes.installed_many', ['count' => count($lines)]))
                ->body(count($lines) > 1 ? new HtmlString(implode('<br>', array_map('e', $lines))) : null)
                ->send();

            $this->unmountAction();

            return null;
        } catch (ThemeException $exception) {
            // Shown in the upload area, where the admin can drop another file
            return $exception->getMessage();
        } finally {
            File::delete($path);
        }
    }

    /**
     * Notification text for an installed theme.
     *
     * @param  array{theme: Theme, updated: bool, previous: string|null}  $result
     */
    public static function describe(array $result): string
    {
        return match (true) {
            ! $result['updated'] => __('admin.themes.installed', ['theme' => $result['theme']->title, 'version' => $result['theme']->version]),
            $result['previous'] === $result['theme']->version => __('admin.themes.reinstalled', ['theme' => $result['theme']->title, 'version' => $result['theme']->version]),
            default => __('admin.themes.updated', ['theme' => $result['theme']->title, 'from' => $result['previous'], 'to' => $result['theme']->version]),
        };
    }

    public function updateAction(): Action
    {
        return self::themeUpdateAction('update');
    }

    /**
     * Download and installation of a theme's new version (Themes and Updates pages).
     */
    public static function themeUpdateAction(string $name): Action
    {
        return Action::make($name)
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
