<?php

declare(strict_types=1);

namespace App\Support\Update;

/**
 * Una release pubblicata su GitHub (tag vX.Y.Z) con il pacchetto completo e il suo SHA-256.
 */
final readonly class Release
{
    public const string VERSION_PATTERN = '/^\d+\.\d+\.\d+$/';

    /**
     * @param  array{id: int, name: string, size: int, api_url: string, download_url: string}|null  $package
     * @param  array{id: int, name: string, size: int, api_url: string, download_url: string}|null  $checksum
     */
    public function __construct(
        public string $version,
        public string $tag,
        public string $name,
        public string $notes,
        public string $url,
        public ?string $publishedAt,
        public ?array $package,
        public ?array $checksum,
    ) {}

    /**
     * Dalla risposta dell'API di GitHub; null per bozze, pre-release e tag che non sono una versione.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromGitHub(array $data): ?self
    {
        $tag = is_string($data['tag_name'] ?? null) ? $data['tag_name'] : '';
        $version = ltrim($tag, 'vV');

        if (($data['draft'] ?? false) || ($data['prerelease'] ?? false) || preg_match(self::VERSION_PATTERN, $version) !== 1) {
            return null;
        }

        $assets = [];

        foreach (is_array($data['assets'] ?? null) ? $data['assets'] : [] as $asset) {
            if (is_array($asset) && is_string($asset['name'] ?? null)) {
                $assets[$asset['name']] = [
                    'id' => (int) ($asset['id'] ?? 0),
                    'name' => $asset['name'],
                    'size' => (int) ($asset['size'] ?? 0),
                    'api_url' => (string) ($asset['url'] ?? ''),
                    'download_url' => (string) ($asset['browser_download_url'] ?? ''),
                ];
            }
        }

        return new self(
            version: $version,
            tag: $tag,
            name: is_string($data['name'] ?? null) && $data['name'] !== '' ? $data['name'] : $tag,
            notes: is_string($data['body'] ?? null) ? $data['body'] : '',
            url: (string) ($data['html_url'] ?? ''),
            publishedAt: is_string($data['published_at'] ?? null) ? $data['published_at'] : null,
            package: $assets["resume-app-{$version}.zip"] ?? null,
            checksum: $assets["resume-app-{$version}.zip.sha256"] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{id: int, name: string, size: int, api_url: string, download_url: string}|null $package */
        $package = is_array($data['package'] ?? null) ? $data['package'] : null;
        /** @var array{id: int, name: string, size: int, api_url: string, download_url: string}|null $checksum */
        $checksum = is_array($data['checksum'] ?? null) ? $data['checksum'] : null;

        return new self(
            version: (string) ($data['version'] ?? ''),
            tag: (string) ($data['tag'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            notes: (string) ($data['notes'] ?? ''),
            url: (string) ($data['url'] ?? ''),
            publishedAt: is_string($data['published_at'] ?? null) ? $data['published_at'] : null,
            package: $package,
            checksum: $checksum,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'tag' => $this->tag,
            'name' => $this->name,
            'notes' => $this->notes,
            'url' => $this->url,
            'published_at' => $this->publishedAt,
            'package' => $this->package,
            'checksum' => $this->checksum,
        ];
    }

    public function packageName(): string
    {
        return "resume-app-{$this->version}.zip";
    }
}
