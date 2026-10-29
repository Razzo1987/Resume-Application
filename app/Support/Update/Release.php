<?php

declare(strict_types=1);

namespace App\Support\Update;

/**
 * A release published on GitHub (tag vX.Y.Z) with the full package and its SHA-256.
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
     * From the GitHub API response; null for drafts, pre-releases and tags that are not a version.
     * The app uses tags "vX.Y.Z" and the package "resume-app-X.Y.Z.zip"; a theme can use its own tag prefix
     * ("folio-v") and package name ("folio-X.Y.Z.zip").
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromGitHub(array $data, string $tagPrefix = 'v', string $package = 'resume-app'): ?self
    {
        $tag = is_string($data['tag_name'] ?? null) ? $data['tag_name'] : '';
        $version = match (true) {
            $tagPrefix === 'v' => ltrim($tag, 'vV'),
            str_starts_with($tag, $tagPrefix) => substr($tag, strlen($tagPrefix)),
            default => '',
        };

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
            package: $assets["{$package}-{$version}.zip"] ?? null,
            checksum: $assets["{$package}-{$version}.zip.sha256"] ?? null,
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
