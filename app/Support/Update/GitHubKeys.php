<?php

declare(strict_types=1);

namespace App\Support\Update;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * GitHub access keys used to read private repositories (app or themes).
 *
 * Saved in core/storage (updates/github-keys.json, never in the content exports), each token encrypted with the
 * APP_KEY. The key of RESUME_UPDATE_TOKEN in .env is listed too, read-only, for every repository. A request uses the
 * most specific key for its repository (exact repository, then owner, then "every repository"); with the same
 * specificity the .env key wins, as before 2.4. Public repositories never get a key (see GitHub).
 */
class GitHubKeys
{
    public function __construct(private readonly UpdateStorage $storage) {}

    public function file(): string
    {
        return $this->storage->directory().'/github-keys.json';
    }

    /**
     * Every key, the .env one first.
     *
     * @return list<GitHubKey>
     */
    public function all(): array
    {
        $this->migrateLegacy();
        $keys = [];
        $env = config('resume.updates.token');
        $data = $this->read();

        if (is_string($env) && $env !== '') {
            $meta = is_array($data['env'] ?? null) ? $data['env'] : [];
            $keys[] = $this->make(GitHubKey::ENV, ['label' => '.env (RESUME_UPDATE_TOKEN)', 'scopes' => ['*'], ...$meta], $env, 'env');
        }

        foreach ($data['keys'] as $id => $key) {
            try {
                $token = Crypt::decryptString((string) ($key['token'] ?? ''));
            } catch (Throwable) {
                continue; // APP_KEY changed: the key must be entered again
            }

            $keys[] = $this->make((string) $id, $key, $token, 'admin');
        }

        return $keys;
    }

    public function find(string $id): ?GitHubKey
    {
        foreach ($this->all() as $key) {
            if ($key->id === $id) {
                return $key;
            }
        }

        return null;
    }

    /**
     * The key to use for a repository, if any.
     */
    public function forRepository(string $repository): ?GitHubKey
    {
        $best = null;
        $score = 0;

        foreach ($this->all() as $key) {
            $specificity = $key->specificity($repository);

            if ($specificity > $score) {
                $best = $key;
                $score = $specificity;
            }
        }

        return $best;
    }

    public function any(): bool
    {
        return $this->all() !== [];
    }

    /**
     * Adds a key (no id) or changes one; a blank token keeps the saved one.
     *
     * @param  list<string>  $scopes
     */
    public function save(?string $id, string $label, array $scopes, #[\SensitiveParameter] ?string $token): GitHubKey
    {
        $scopes = array_values(array_unique(array_filter(array_map('trim', $scopes), fn (string $scope): bool => $scope !== '')));

        foreach ($scopes as $scope) {
            if (preg_match(GitHubKey::SCOPE_PATTERN, $scope) !== 1) {
                throw new InvalidArgumentException((string) __('admin.updates.keys.errors.scope', ['scope' => $scope]));
            }
        }

        if ($scopes === []) {
            throw new InvalidArgumentException((string) __('admin.updates.keys.errors.no_scope'));
        }

        $data = $this->read();
        $existing = $id !== null ? ($data['keys'][$id] ?? null) : null;
        $token = $token !== null ? trim($token) : null;

        if ($existing === null && ($token === null || $token === '')) {
            throw new InvalidArgumentException((string) __('admin.updates.keys.errors.no_token'));
        }

        $id = $existing !== null ? (string) $id : Str::lower(Str::random(10));
        $key = [
            ...($existing ?? ['added_at' => now()->toAtomString()]),
            'label' => trim($label) !== '' ? trim($label) : 'GitHub',
            'scopes' => $scopes,
        ];

        if ($token !== null && $token !== '') {
            // A new token: what was known about the old one no longer applies
            $key = ['added_at' => $key['added_at'], 'label' => $key['label'], 'scopes' => $scopes, 'token' => Crypt::encryptString($token)];
        }

        $data['keys'][$id] = $key;
        $this->write($data);

        return $this->find($id) ?? throw new InvalidArgumentException('Key not saved');
    }

    public function delete(string $id): void
    {
        $data = $this->read();
        unset($data['keys'][$id]);
        $this->write($data);
    }

    /**
     * What a response from GitHub says about the key: expiration date (header of the fine-grained tokens), valid or not.
     *
     * @param  array<string, mixed>  $facts  status, expires_at, login
     */
    public function remember(string $id, array $facts): void
    {
        $data = $this->read();
        $facts = ['checked_at' => now()->toAtomString(), ...$facts];

        if ($id === GitHubKey::ENV) {
            $data['env'] = [...(is_array($data['env'] ?? null) ? $data['env'] : []), ...$facts];
        } elseif (isset($data['keys'][$id])) {
            $data['keys'][$id] = [...$data['keys'][$id], ...$facts];
        } else {
            return;
        }

        $this->write($data);
    }

    /**
     * Until 2.3 there was a single token for every repository (updates/github-token): it becomes a key for "*".
     */
    private function migrateLegacy(): void
    {
        $legacy = $this->storage->tokenFile();

        if (! is_file($legacy)) {
            return;
        }

        $data = $this->read();

        try {
            $token = Crypt::decryptString((string) file_get_contents($legacy));
        } catch (Throwable) {
            $token = '';
        }

        if ($token !== '') {
            $data['keys'][Str::lower(Str::random(10))] = [
                'added_at' => date(DATE_ATOM, (int) filemtime($legacy)),
                'label' => 'GitHub',
                'scopes' => ['*'],
                'token' => Crypt::encryptString($token),
            ];
            $this->write($data);
        }

        File::delete($legacy);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function make(string $id, array $data, #[\SensitiveParameter] string $token, string $source): GitHubKey
    {
        $date = fn (mixed $value): ?Carbon => is_string($value) && $value !== '' ? Carbon::parse($value) : null;

        return new GitHubKey(
            id: $id,
            label: is_string($data['label'] ?? null) ? $data['label'] : 'GitHub',
            scopes: array_values(array_filter(is_array($data['scopes'] ?? null) ? $data['scopes'] : ['*'], 'is_string')),
            token: $token,
            source: $source,
            expiresAt: $date($data['expires_at'] ?? null),
            status: is_string($data['status'] ?? null) ? $data['status'] : null,
            checkedAt: $date($data['checked_at'] ?? null),
            login: is_string($data['login'] ?? null) ? $data['login'] : null,
        );
    }

    /**
     * @return array{keys: array<string, array<string, mixed>>, env?: array<string, mixed>}
     */
    private function read(): array
    {
        $data = $this->storage->readJson($this->file()) ?? [];
        $keys = [];

        foreach (is_array($data['keys'] ?? null) ? $data['keys'] : [] as $id => $key) {
            if (is_array($key)) {
                $keys[(string) $id] = $key;
            }
        }

        return [...$data, 'keys' => $keys];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function write(array $data): void
    {
        $this->storage->writeJson($this->file(), $data);
        @chmod($this->file(), 0600);
    }
}
