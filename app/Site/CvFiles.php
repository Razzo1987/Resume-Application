<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\CvDocument;
use App\Models\Language;
use App\Support\Languages\Locales;
use App\Support\Settings\Settings;

/**
 * CV in PDF da offrire in una lingua: la versione corrente di quella lingua oppure,
 * se l'admin lo ha attivato, quella della lingua di default.
 */
final class CvFiles
{
    /** @var array<string, CvDocument|null> */
    private array $resolved = [];

    public function __construct(
        private readonly Locales $locales,
        private readonly Settings $settings,
    ) {}

    public function for(Language $language): ?CvDocument
    {
        if (array_key_exists($language->code, $this->resolved)) {
            return $this->resolved[$language->code];
        }

        $document = $this->current($language->id);

        if ($document === null && $this->settings->bool('cv.fallback_to_default') && $language->code !== $this->locales->default()) {
            $default = $this->locales->find($this->locales->default());
            $document = $default !== null ? $this->current($default->id) : null;
        }

        return $this->resolved[$language->code] = $document;
    }

    private function current(?int $languageId): ?CvDocument
    {
        if ($languageId === null) {
            return null;
        }

        return CvDocument::query()
            ->where('language_id', $languageId)
            ->where('is_current', true)
            ->with('language')
            ->latest('id')
            ->first();
    }
}
