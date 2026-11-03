<?php

declare(strict_types=1);

namespace App\Support\Update;

use App\Support\System\Updater;
use App\Themes\ThemeManager;
use App\Themes\ThemeUpdates;
use Illuminate\Support\Carbon;

/**
 * The app and the installed themes, each with its update state, repository, access (public or private) and key:
 * what the Updates page, the Themes page and the dashboard show. Reads only the saved results, never GitHub,
 * except check().
 */
class UpdateSources
{
    public function __construct(
        private readonly ReleaseChecker $releases,
        private readonly ThemeUpdates $themeUpdates,
        private readonly ThemeManager $themes,
        private readonly GitHub $github,
        private readonly GitHubKeys $keys,
        private readonly Updater $updater,
    ) {}

    /**
     * The app first, then the installed themes (the active one first, base excluded: it comes with the app).
     *
     * @return list<UpdateSource>
     */
    public function all(): array
    {
        $sources = [$this->app()];
        $status = $this->themeUpdates->status();
        $active = $this->themes->active()->name;

        $names = array_filter(array_keys($this->themes->installed()), fn (string $name): bool => $name !== $this->themes->base()->name);
        usort($names, fn (string $a, string $b): int => [$a !== $active, $this->themes->get($a)->title] <=> [$b !== $active, $this->themes->get($b)->title]);

        foreach ($names as $name) {
            $theme = $this->themes->get($name);
            $repository = $theme->updateRepository;
            $latest = $repository !== null ? ($status['releases'][$name] ?? null) : null;

            $sources[] = $this->make(
                id: $name,
                type: 'theme',
                title: $theme->title,
                installed: $theme->version,
                latest: $latest,
                repository: $repository,
                newer: $latest !== null && version_compare($latest->version, $theme->version, '>'),
                problem: $status['problems'][$name] ?? (isset($status['errors'][$name]) ? 'error' : null),
                message: $status['errors'][$name] ?? null,
                checkedAt: $repository !== null ? $status['checked_at'] : null,
                active: $name === $active,
            );
        }

        return $sources;
    }

    public function find(string $id): ?UpdateSource
    {
        foreach ($this->all() as $source) {
            if ($source->id === $id) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Asks GitHub about the app and every theme with an update source.
     */
    public function check(): void
    {
        $this->releases->check();

        if ($this->themeUpdates->updatable() !== []) {
            $this->themeUpdates->check();
        }
    }

    /**
     * When the oldest of the last checks was made (null: never).
     */
    public function checkedAt(): ?Carbon
    {
        $dates = array_map(fn (UpdateSource $source): ?Carbon => $source->checkedAt, array_filter($this->all(), fn (UpdateSource $source): bool => $source->repository !== null));

        return in_array(null, $dates, true) || $dates === [] ? null : min($dates);
    }

    /**
     * Repositories of the update sources, for the scopes of the keys.
     *
     * @return list<string>
     */
    public function repositories(): array
    {
        $repositories = array_filter(array_map(fn (UpdateSource $source): ?string => $source->repository, $this->all()));

        return array_values(array_unique($repositories));
    }

    /**
     * Sources with an update, with a problem, needing a key.
     *
     * @return array{available: int, problems: int, keys: int}
     */
    public function counts(): array
    {
        $sources = $this->all();

        return [
            'available' => count(array_filter($sources, fn (UpdateSource $source): bool => $source->hasUpdate())),
            'problems' => count(array_filter($sources, fn (UpdateSource $source): bool => $source->hasProblem())),
            'keys' => count(array_filter($sources, fn (UpdateSource $source): bool => $source->needsKey())),
        ];
    }

    private function app(): UpdateSource
    {
        $status = $this->releases->status();
        $version = $this->updater->codeVersion();
        $latest = $status['release'];

        if (preg_match(Release::VERSION_PATTERN, $version) !== 1) {
            return new UpdateSource('app', 'app', 'Resume App', $version, $latest, $this->releases->repository(), false, null, 'dev', null, $status['checked_at']);
        }

        return $this->make(
            id: 'app',
            type: 'app',
            title: 'Resume App',
            installed: $version,
            latest: $latest,
            repository: $this->releases->repository(),
            newer: $this->releases->available() !== null,
            problem: $status['problem'],
            message: $status['error'],
            checkedAt: $status['checked_at'],
        );
    }

    private function make(string $id, string $type, string $title, string $installed, ?Release $latest, ?string $repository, bool $newer, ?string $problem, ?string $message, ?Carbon $checkedAt, bool $active = false): UpdateSource
    {
        $known = $repository !== null ? $this->github->repository($repository) : null;
        $private = $known['private'] ?? null;
        $key = $repository !== null && $private !== false ? $this->keys->forRepository($repository) : null;

        $state = match (true) {
            $repository === null => 'manual',
            $newer => 'available',
            $problem !== null => in_array($problem, ['needs_key', 'key_denied', 'key_invalid', 'rate_limited', 'unreachable'], true) ? $problem : 'error',
            $checkedAt === null => 'unchecked',
            $latest === null => 'no_release',
            default => 'up_to_date',
        };

        return new UpdateSource($id, $type, $title, $installed, $latest, $repository, $private, $key, $state, $problem !== null ? $message : null, $checkedAt, $active);
    }
}
