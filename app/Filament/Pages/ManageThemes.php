<?php

namespace App\Filament\Pages;

use App\Support\Settings\Settings;
use App\Support\System\Updater;
use App\Themes\Compatibility;
use App\Themes\Theme;
use App\Themes\ThemeException;
use App\Themes\ThemeInstaller;
use App\Themes\ThemeManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

/**
 * Temi: elenco dei temi installati, attivazione, installazione o aggiornamento da zip, rimozione.
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

        return [
            'active' => $active,
            'configured' => $themes->activeName(),
            'themes' => collect(array_keys($themes->installed()))
                ->map(fn (string $name): Theme => $themes->get($name))
                ->sortBy(fn (Theme $theme): string => ($theme->name === $active ? '0' : '1').$theme->title)
                ->map(fn (Theme $theme): array => [
                    'theme' => $theme,
                    'assets' => $theme->name === $themes->base()->name || $theme->hasAssets($themes->publicPath()),
                    'compatible' => $theme->requires === null || Compatibility::satisfies($version, $theme->requires),
                    'base' => $theme->name === $themes->base()->name,
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
                        $result = app(ThemeInstaller::class)->install($disk->path($path));

                        Notification::make()->success()
                            ->title(match (true) {
                                ! $result['updated'] => __('admin.themes.installed', ['theme' => $result['theme']->title, 'version' => $result['theme']->version]),
                                $result['previous'] === $result['theme']->version => __('admin.themes.reinstalled', ['theme' => $result['theme']->title, 'version' => $result['theme']->version]),
                                default => __('admin.themes.updated', ['theme' => $result['theme']->title, 'from' => $result['previous'], 'to' => $result['theme']->version]),
                            })
                            ->send();
                    } catch (ThemeException $exception) {
                        Notification::make()->danger()->title(__('admin.themes.errors.title'))->body($exception->getMessage())->persistent()->send();
                    } finally {
                        $disk->delete($path);
                    }
                }),
        ];
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

                app(Settings::class)->set('site.theme', $name);
                $themes->forget();

                Notification::make()->success()->title(__('admin.themes.activated', ['theme' => $themes->get($name)->title]))->send();
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
