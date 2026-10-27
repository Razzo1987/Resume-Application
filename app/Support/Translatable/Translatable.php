<?php

declare(strict_types=1);

namespace App\Support\Translatable;

/**
 * Model with translated fields (implemented through the HasTranslations trait).
 */
interface Translatable
{
    /**
     * @return list<string>
     */
    public function getTranslatableAttributes(): array;

    public function t(string $field, ?string $locale = null, bool $fallback = true): ?string;

    /**
     * Fields filled in at least one language but not in the given one.
     *
     * @return list<string>
     */
    public function missingTranslations(string $locale): array;
}
