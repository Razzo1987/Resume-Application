<?php

declare(strict_types=1);

namespace App\Support\Update;

/**
 * Result of the checks on an update package.
 */
final readonly class PackageReport
{
    /**
     * @param  list<string>  $problems  block the update
     * @param  list<string>  $notes  information only
     */
    public function __construct(
        public string $file,
        public ?string $version,
        public string $currentVersion,
        public int $entries,
        public int $size,
        public array $problems,
        public array $notes,
    ) {}

    public function ok(): bool
    {
        return $this->problems === [];
    }
}
