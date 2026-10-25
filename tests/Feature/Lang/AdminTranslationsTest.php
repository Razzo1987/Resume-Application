<?php

use App\Enums\HighlightSource;
use App\Enums\PageSectionKey;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Arr;

/*
 * Testi dell'admin (lang/it/admin.php e lang/en/admin.php) e testi del sito usati dal codice.
 */

const ADMIN_LOCALES = ['it', 'en'];

it('has the same admin strings in Italian and English', function () {
    $italian = array_keys(Arr::dot(require lang_path('it/admin.php')));
    $english = array_keys(Arr::dot(require lang_path('en/admin.php')));

    expect(array_values(array_diff($italian, $english)))->toBe([])
        ->and(array_values(array_diff($english, $italian)))->toBe([]);
});

it('defines every admin string used in the code', function () {
    $missing = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        // Solo chiavi scritte per intero: quelle composte a runtime sono verificate dai test sugli enum
        preg_match_all("/__\\('(admin\\.[a-z0-9_.]+)'/", (string) file_get_contents($file->getPathname()), $matches);

        // Le chiavi che finiscono con "." sono prefissi completati a runtime
        foreach (array_unique(array_filter($matches[1], fn (string $key): bool => ! str_ends_with($key, '.'))) as $key) {
            foreach (ADMIN_LOCALES as $locale) {
                if (! trans()->hasForLocale($key, $locale)) {
                    $missing[] = "{$locale}: {$key} (".str_replace(app_path().'/', '', $file->getPathname()).')';
                }
            }
        }
    }

    expect($missing)->toBe([]);
});

it('labels every enum value in the admin', function (string $locale) {
    app()->setLocale($locale);
    $missing = [];

    foreach (glob(app_path('Enums/*.php')) ?: [] as $file) {
        $enum = 'App\\Enums\\'.basename($file, '.php');

        if (! enum_exists($enum) || ! is_subclass_of($enum, HasLabel::class)) {
            continue;
        }

        foreach ($enum::cases() as $case) {
            $label = (string) $case->getLabel();

            if ($label === '' || str_starts_with($label, 'admin.')) {
                $missing[] = $enum.'::'.$case->name;
            }
        }
    }

    expect($missing)->toBe([]);
})->with(ADMIN_LOCALES);

it('labels every resource and its navigation group', function (string $locale) {
    app()->setLocale($locale);
    $missing = [];

    foreach (glob(app_path('Filament/Resources/*/*Resource.php')) ?: [] as $file) {
        $resource = 'App\\Filament\\Resources\\'.basename(dirname($file)).'\\'.basename($file, '.php');

        foreach ([$resource::getModelLabel(), $resource::getPluralModelLabel(), (string) $resource::getNavigationGroup()] as $label) {
            if ($label === '' || str_starts_with($label, 'admin.')) {
                $missing[] = "{$resource}: {$label}";
            }
        }
    }

    expect($missing)->toBe([]);
})->with(ADMIN_LOCALES);

it('has a site heading for every page section and a label for every automatic key number', function (string $code) {
    $file = lang_path("{$code}/site.php");

    if (! is_file($file)) {
        $this->markTestSkipped("{$code}: site.php non ancora tradotto");
    }

    $strings = Arr::dot(['site' => require $file]);

    foreach (PageSectionKey::cases() as $section) {
        expect($strings)->toHaveKey($section->siteStringKey());
    }

    foreach (HighlightSource::cases() as $source) {
        if ($source->isAutomatic()) {
            expect($strings)->toHaveKey('site.highlights.'.$source->value);
        }
    }
})->with(fn (): array => array_map('basename', glob(dirname(__DIR__, 3).'/lang/*-*', GLOB_ONLYDIR) ?: []));
