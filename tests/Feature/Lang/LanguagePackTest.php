<?php

/*
 * Checks on the language packs in lang/{code}/.
 */

// The dataset is evaluated before the app boots: the path is built by hand
dataset('language packs', function (): array {
    $codes = array_map('basename', glob(dirname(__DIR__, 3).'/lang/*-*', GLOB_ONLYDIR) ?: []);

    return array_combine($codes, array_map(fn (string $code): array => [$code], $codes));
});

/**
 * @return list<string>
 */
function flattenKeys(array $array, string $prefix = ''): array
{
    $keys = [];

    foreach ($array as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
        $keys = is_array($value) ? [...$keys, ...flattenKeys($value, $path)] : [...$keys, $path];
    }

    return $keys;
}

it('ships the main languages', function () {
    $codes = array_map('basename', glob(lang_path('*-*'), GLOB_ONLYDIR) ?: []);

    expect($codes)->toContain('it-IT', 'en-GB', 'en-US', 'de-DE', 'fr-FR', 'es-ES', 'pt-PT', 'pt-BR', 'nl-NL');
});

it('defines valid, unique URL segments', function (string $code) {
    $routes = require lang_path("{$code}/routes.php");

    expect($routes)->toHaveKeys(['projects', 'experience', 'blog', 'cv']);

    foreach ($routes as $segment) {
        expect($segment)->toMatch('/^[a-z0-9]+(?:-[a-z0-9]+)*$/');
    }

    expect(array_unique($routes))->toHaveCount(count($routes));
})->with('language packs');

it('keeps the same site strings in every language that has them', function (string $code) {
    $file = lang_path("{$code}/site.php");

    if (! is_file($file)) {
        $this->markTestSkipped("{$code}: site.php non ancora tradotto (fallback sulla lingua di default)");
    }

    $reference = flattenKeys(require lang_path('it-IT/site.php'));
    $keys = flattenKeys(require $file);

    expect(array_values(array_diff($reference, $keys)))->toBe([])
        ->and(array_values(array_diff($keys, $reference)))->toBe([]);
})->with('language packs');
