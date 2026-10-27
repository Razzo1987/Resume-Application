<?php

declare(strict_types=1);

namespace App\Support\Stats;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Locale;

/**
 * SVG world map (no library, no tiles from external servers): countries colored by visits and one circle per
 * city. Shapes and Equal Earth projection parameters are in resources/stats/world-map.json
 * (scripts/build-world-map.mjs); here, the same formula as d3-geo for the city coordinates.
 */
class WorldMap
{
    /** @var array{width: int, height: int, projection: array{scale: float, translate: array{0: float, 1: float}}, countries: array<string, string>}|null */
    private ?array $map = null;

    /**
     * @param  array<string, int>  $countries  ISO code => visits
     * @param  list<array{label: string, latitude: float, longitude: float, views: int}>  $cities
     */
    public function render(array $countries, array $cities, string $locale): HtmlString
    {
        $map = $this->map();
        $max = max([1, ...array_values($countries)]);
        $maxCity = max([1, ...array_column($cities, 'views')]);
        $svg = sprintf('<svg class="resume-map" viewBox="0 0 %d %d" role="img" aria-label="%s">', $map['width'], $map['height'], e(__('admin.stats.map')));

        foreach ($map['countries'] as $code => $path) {
            $views = $countries[$code] ?? 0;
            $name = Locale::getDisplayRegion('-'.$code, $locale) ?: $code;
            $opacity = $views > 0 ? round(0.25 + 0.75 * sqrt($views / $max), 2) : null;

            $svg .= sprintf(
                '<path d="%s" class="%s"%s><title>%s</title></path>',
                $path,
                $views > 0 ? 'resume-map-visited' : 'resume-map-land',
                $opacity !== null ? ' fill-opacity="'.$opacity.'"' : '',
                e($name.($views > 0 ? ': '.Number::format($views, locale: $locale) : '')),
            );
        }

        foreach ($cities as $city) {
            [$x, $y] = $this->project($city['longitude'], $city['latitude']);
            $radius = round(2.5 + 6 * sqrt($city['views'] / $maxCity), 1);

            $svg .= sprintf(
                '<circle cx="%.1f" cy="%.1f" r="%.1f" class="resume-map-city"><title>%s</title></circle>',
                $x,
                $y,
                $radius,
                e($city['label'].': '.Number::format($city['views'], locale: $locale)),
            );
        }

        return new HtmlString($svg.'</svg>');
    }

    /**
     * Longitude and latitude (degrees) → map coordinates, like geoEqualEarth() in d3-geo.
     *
     * @return array{0: float, 1: float}
     */
    public function project(float $longitude, float $latitude): array
    {
        [$a1, $a2, $a3, $a4] = [1.340264, -0.081106, 0.000893, 0.003796];
        $m = sqrt(3) / 2;
        $lambda = deg2rad($longitude);
        $l = asin($m * sin(deg2rad($latitude)));
        $l2 = $l * $l;
        $l6 = $l2 * $l2 * $l2;
        $x = $lambda * cos($l) / ($m * ($a1 + 3 * $a2 * $l2 + $l6 * (7 * $a3 + 9 * $a4 * $l2)));
        $y = $l * ($a1 + $a2 * $l2 + $l6 * ($a3 + $a4 * $l2));
        $projection = $this->map()['projection'];

        return [
            $projection['translate'][0] + $projection['scale'] * $x,
            $projection['translate'][1] - $projection['scale'] * $y,
        ];
    }

    /**
     * @return array{width: int, height: int, projection: array{scale: float, translate: array{0: float, 1: float}}, countries: array<string, string>}
     */
    private function map(): array
    {
        /** @var array{width: int, height: int, projection: array{scale: float, translate: array{0: float, 1: float}}, countries: array<string, string>} $map */
        $map = $this->map ??= json_decode((string) file_get_contents(resource_path('stats/world-map.json')), true);

        return $map;
    }
}
