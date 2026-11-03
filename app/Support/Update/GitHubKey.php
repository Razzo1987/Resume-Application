<?php

declare(strict_types=1);

namespace App\Support\Update;

use Illuminate\Support\Carbon;

/**
 * A GitHub access key (fine-grained token) and the repositories it is used for.
 *
 * Scopes: "*" (every repository), "owner/*" (every repository of an owner) or "owner/name".
 * The secret never leaves the server: views and logs only get the label, the scopes and the status.
 */
final readonly class GitHubKey
{
    public const string SCOPE_PATTERN = '#^(\*|[A-Za-z0-9_.-]+/(\*|[A-Za-z0-9_.-]+))$#';

    public const string ENV = 'env';

    /**
     * @param  list<string>  $scopes
     * @param  string  $source  "admin" (entered in the admin, encrypted on the server) or "env" (RESUME_UPDATE_TOKEN)
     * @param  string|null  $status  "ok", "invalid" or null (not verified yet)
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $scopes,
        #[\SensitiveParameter] public string $token,
        public string $source = 'admin',
        public ?Carbon $expiresAt = null,
        public ?string $status = null,
        public ?Carbon $checkedAt = null,
        public ?string $login = null,
    ) {}

    /**
     * How well the key matches a repository: 3 exact, 2 owner, 1 every repository, 0 not at all.
     */
    public function specificity(string $repository): int
    {
        $repository = strtolower($repository);
        $owner = strtolower(strtok($repository, '/') ?: '');
        $best = 0;

        foreach ($this->scopes as $scope) {
            $scope = strtolower($scope);
            $best = max($best, match (true) {
                $scope === $repository => 3,
                $scope === "{$owner}/*" => 2,
                $scope === '*' => 1,
                default => 0,
            });
        }

        return $best;
    }

    public function covers(string $repository): bool
    {
        return $this->specificity($repository) > 0;
    }

    public function isEditable(): bool
    {
        return $this->source === 'admin';
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt->isPast();
    }

    /**
     * Expires within the given days (and is not expired yet).
     */
    public function expiresSoon(int $days = 14): bool
    {
        return $this->expiresAt !== null && ! $this->isExpired() && $this->expiresAt->lt(now()->addDays($days));
    }

    /**
     * Overall state for the admin: "expired", "invalid", "expiring", "ok" or "unchecked".
     */
    public function state(): string
    {
        return match (true) {
            $this->isExpired() => 'expired',
            $this->status === 'invalid' => 'invalid',
            $this->expiresSoon() => 'expiring',
            $this->status === 'ok' => 'ok',
            default => 'unchecked',
        };
    }

    /**
     * The last characters of the token, enough to recognise it on GitHub.
     */
    public function hint(): string
    {
        return '...'.substr($this->token, -4);
    }
}
