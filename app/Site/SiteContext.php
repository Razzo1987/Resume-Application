<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\Language;
use App\Support\Languages\Locales;
use Carbon\Carbon;
use Illuminate\Translation\Translator;

/**
 * State of the public request: the language the site is being shown in.
 */
final class SiteContext
{
    private ?Language $language = null;

    public function __construct(
        private readonly Locales $locales,
        private readonly Translator $translator,
    ) {}

    /**
     * Sets the language of the request: app language, Carbon language and texts customized in the admin.
     */
    public function enter(Language $language): void
    {
        $this->language = $language;

        app()->setLocale($language->code);
        Carbon::setLocale(str_replace('-', '_', $language->code));

        $overrides = [];

        foreach ($language->string_overrides ?? [] as $key => $value) {
            if (trim($value) !== '') {
                $overrides['site.'.$key] = $value;
            }
        }

        if ($overrides !== []) {
            // Load site.php first, otherwise addLines would mark the group as already loaded
            $this->translator->load('*', 'site', $language->code);
            $this->translator->addLines($overrides, $language->code);
        }
    }

    public function language(): Language
    {
        if ($this->language === null) {
            $default = $this->locales->default();
            $this->language = $this->locales->find($default) ?? new Language(['code' => $default, 'name' => $default]);
        }

        return $this->language;
    }

    public function locale(): string
    {
        return $this->language()->code;
    }
}
