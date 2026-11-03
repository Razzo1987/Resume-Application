<?php

declare(strict_types=1);

namespace App\Support\Update;

use Illuminate\Support\Carbon;

/**
 * Something that can be updated (the app or an installed theme) with its state, for the Updates page.
 *
 * States: "available", "up_to_date", "needs_key" (private repository, no key), "key_denied" (the key cannot read
 * it), "key_invalid" (expired or revoked key), "rate_limited", "unreachable", "error", "no_release" (nothing
 * published yet), "unchecked", "manual" (the theme does not declare where it is published), "dev" (development copy).
 */
final readonly class UpdateSource
{
    public const array KEY_STATES = ['needs_key', 'key_denied', 'key_invalid'];

    public const array PROBLEM_STATES = ['needs_key', 'key_denied', 'key_invalid', 'rate_limited', 'unreachable', 'error'];

    /**
     * @param  string  $id  "app" or the name of the theme
     * @param  string  $type  "app" or "theme"
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public string $installed,
        public ?Release $latest,
        public ?string $repository,
        public ?bool $private,
        public ?GitHubKey $key,
        public string $state,
        public ?string $message,
        public ?Carbon $checkedAt,
        public bool $active = false,
        public bool $signed = false,
    ) {}

    public function isApp(): bool
    {
        return $this->type === 'app';
    }

    public function hasUpdate(): bool
    {
        return $this->state === 'available';
    }

    public function hasProblem(): bool
    {
        return in_array($this->state, self::PROBLEM_STATES, true);
    }

    /**
     * The problem is solved by adding or changing a GitHub key.
     */
    public function needsKey(): bool
    {
        return in_array($this->state, self::KEY_STATES, true);
    }

    /**
     * "public", "private" or "unknown"; null for themes without an update source.
     */
    public function access(): ?string
    {
        return match (true) {
            $this->repository === null => null,
            $this->private === true => 'private',
            $this->private === false => 'public',
            default => 'unknown',
        };
    }

    /**
     * Colour of the state badge.
     */
    public function color(): string
    {
        return match ($this->state) {
            'available' => 'warning',
            'up_to_date' => 'success',
            'needs_key', 'key_denied', 'key_invalid', 'error' => 'danger',
            default => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this->state) {
            'available' => 'heroicon-o-arrow-down-circle',
            'up_to_date' => 'heroicon-o-check-circle',
            'needs_key', 'key_denied', 'key_invalid' => 'heroicon-o-key',
            'rate_limited', 'unreachable', 'error' => 'heroicon-o-exclamation-triangle',
            default => 'heroicon-o-minus-circle',
        };
    }
}
