<?php

declare(strict_types=1);

namespace App\Support\Translatable;

/**
 * Modello con campi tradotti (implementato tramite il trait HasTranslations).
 */
interface Translatable
{
    /**
     * @return list<string>
     */
    public function getTranslatableAttributes(): array;

    public function t(string $field, ?string $locale = null, bool $fallback = true): ?string;

    /**
     * Campi compilati in almeno una lingua ma non in quella indicata.
     *
     * @return list<string>
     */
    public function missingTranslations(string $locale): array;
}
