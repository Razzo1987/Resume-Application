<?php

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test configuration
|--------------------------------------------------------------------------
| Feature: full application with a MySQL test database (reset for every test).
| Unit: isolated classes, without the application.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Bilingual demo CV (it-IT by default, en-GB) with the PDFs on a fake disk.
 */
function seedDemo(): void
{
    Storage::fake('local');
    Storage::fake('uploads');
    test()->seed(DemoSeeder::class);
}

/**
 * Hosts of the resources loaded by the page (scripts, styles, images, fonts), excluding normal links.
 *
 * @return list<string>
 */
function resourceHosts(string $html): array
{
    preg_match_all('#<(?:script|img|source|iframe|video|audio)\b[^>]*\ssrc="([^"]+)"#i', $html, $sources);
    preg_match_all('#<link\b[^>]*rel="(?:stylesheet|preload|modulepreload|icon|preconnect|dns-prefetch)"[^>]*href="([^"]+)"#i', $html, $links);
    preg_match_all('#<use\b[^>]*href="([^"]+)"#i', $html, $sprites);

    $hosts = [];

    foreach ([...$sources[1], ...$links[1], ...$sprites[1]] as $url) {
        $host = parse_url($url, PHP_URL_HOST);
        $hosts[] = is_string($host) ? $host : 'localhost';
    }

    return array_values(array_unique($hosts));
}

/**
 * JSON-LD structured data of the page (all nodes of all blocks).
 *
 * @return list<array<string, mixed>>
 */
function jsonLdNodes(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);

    $nodes = [];

    foreach ($blocks[1] as $json) {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $nodes = [...$nodes, ...($data['@graph'] ?? [$data])];
    }

    return $nodes;
}
