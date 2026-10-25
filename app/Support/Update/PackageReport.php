<?php

declare(strict_types=1);

namespace App\Support\Update;

/**
 * Esito dei controlli su un pacchetto di aggiornamento.
 */
final readonly class PackageReport
{
    /**
     * @param  list<string>  $problems  impediscono l'aggiornamento
     * @param  list<string>  $notes  solo informazioni
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
