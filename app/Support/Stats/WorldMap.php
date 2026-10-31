<?php

declare(strict_types=1);

namespace App\Support\Stats;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Locale;

/**
 * SVG world map (no library, no tiles from external servers): countries colored by visits and one circle per
 * city. Shapes and Equal Earth projection parameters are in resources/stats/world-map.json and, for the zoom on a
 * country, world-map-detail.json (scripts/build-world-map.mjs); here, the same formula as d3-geo for the city
 * coordinates. Countries with visits and cities are buttons that set the filters of the Statistics page.
 */
class WorldMap
{
    /** @var array{width: int, height: int, projection: array{scale: float, translate: array{0: float, 1: float}}, countries: array<string, string>, bounds: array<string, array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}}>}|null */
    private ?array $map = null;

    /** @var array<string, array{d: string, box: array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}}}>|null */
    private ?array $detail = null;

    /**
     * @param  array<string, int>  $countries  ISO code => visits
     * @param  list<array{label: string, latitude: float, longitude: float, views: int, key?: string}>  $cities
     * @param  string|null  $focus  country to zoom in on (ISO code)
     * @param  string|null  $activeCity  key of the selected city ("IT|Milan")
     */
    public function render(array $countries, array $cities, string $locale, ?string $focus = null, ?string $activeCity = null): HtmlString
    {
        $map = $this->map();
        $max = max([1, ...array_values($countries)]);
        $maxCity = max([1, ...array_column($cities, 'views')]);
        $view = $focus !== null ? $this->viewBox($focus) : null;
        $box = $view ?? [0.0, 0.0, (float) $map['width'], (float) $map['height']];
        // circles keep their size on screen when zoomed in
        $zoom = $box[2] / $map['width'];

        $svg = sprintf(
            '<svg class="resume-map%s" viewBox="%s" role="group" aria-label="%s">',
            $view !== null ? ' resume-map-zoomed' : '',
            implode(' ', array_map(fn (float $value): string => (string) round($value, 2), $box)),
            e(__('admin.stats.map')),
        );

        foreach ($this->shapes($view) as $code => $path) {
            $views = $countries[$code] ?? 0;
            $name = Locale::getDisplayRegion('-'.$code, $locale) ?: $code;
            $opacity = $views > 0 ? round(0.25 + 0.75 * sqrt($views / $max), 2) : null;
            $shape = sprintf(
                '<path d="%s" class="%s"%s vector-effect="non-scaling-stroke"><title>%s</title></path>',
                $path,
                $views > 0 ? 'resume-map-visited' : 'resume-map-land',
                $opacity !== null ? ' fill-opacity="'.$opacity.'"' : '',
                e($name.($views > 0 ? ': '.Number::format($views, locale: $locale) : '')),
            );

            // countries with visits: a click shows only their visits and zooms in (not the one already shown)
            $svg .= $views > 0 && $code !== $focus
                ? sprintf('<a href="#" role="button" class="resume-map-link" wire:click.prevent="addFilter(\'country\', \'%s\')" aria-label="%s">%s</a>', e($code), e(__('admin.stats.map_filter', ['place' => $name, 'views' => Number::format($views, locale: $locale)])), $shape)
                : $shape;
        }

        foreach ($cities as $city) {
            [$x, $y] = $this->project($city['longitude'], $city['latitude']);
            $radius = round((2.5 + 6 * sqrt($city['views'] / $maxCity)) * $zoom, 2);
            $key = $city['key'] ?? null;
            $circle = sprintf(
                '<circle cx="%.2f" cy="%.2f" r="%s" class="resume-map-city%s" vector-effect="non-scaling-stroke"><title>%s</title></circle>',
                $x,
                $y,
                $radius,
                $key !== null && $key === $activeCity ? ' resume-map-city-active' : '',
                e($city['label'].': '.Number::format($city['views'], locale: $locale)),
            );

            $svg .= $key !== null && $key !== $activeCity
                ? sprintf('<a href="#" role="button" class="resume-map-link" wire:click.prevent="addFilter(\'city\', %s)" aria-label="%s">%s</a>', e(json_encode($key, JSON_UNESCAPED_UNICODE) ?: '""'), e(__('admin.stats.map_filter', ['place' => $city['label'], 'views' => Number::format($city['views'], locale: $locale)])), $circle)
                : $circle;
        }

        return new HtmlString($svg.'</svg>');
    }

    /**
     * Visible area when zoomed in on a country: the box of its main part with a margin, at least 1.3 times wider
     * than tall and not narrower than 1/25 of the world (small countries such as Malta).
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null x, y, width, height
     */
    public function viewBox(string $country): ?array
    {
        $map = $this->map();
        $bounds = $map['bounds'][$country] ?? null;

        if ($bounds === null) {
            return null;
        }

        [[$x0, $y0], [$x1, $y1]] = $bounds;
        $width = max($x1 - $x0, $map['width'] / 25) * 1.15;
        $height = max($y1 - $y0, $map['width'] / 25 / 1.3) * 1.15;
        $width = max($width, $height * 1.3);
        $height = max($height, $width / 2.2);
        $cx = ($x0 + $x1) / 2;
        $cy = ($y0 + $y1) / 2;

        return [$cx - $width / 2, $cy - $height / 2, $width, $height];
    }

    /**
     * Shapes to draw: the whole world, or the detailed countries that fall in the visible area.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $view
     * @return array<string, string>
     */
    private function shapes(?array $view): array
    {
        if ($view === null) {
            return $this->map()['countries'];
        }

        /** @var array<string, array{d: string, box: array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}}}> $detail */
        $detail = $this->detail ??= json_decode((string) file_get_contents(resource_path('stats/world-map-detail.json')), true);
        [$x, $y, $width, $height] = $view;
        $shapes = [];

        foreach ($detail as $code => $country) {
            [[$x0, $y0], [$x1, $y1]] = $country['box'];

            if ($x1 >= $x && $x0 <= $x + $width && $y1 >= $y && $y0 <= $y + $height) {
                $shapes[$code] = $country['d'];
            }
        }

        return $shapes;
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
     * @return array{width: int, height: int, projection: array{scale: float, translate: array{0: float, 1: float}}, countries: array<string, string>, bounds: array<string, array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}}>}
     */
    private function map(): array
    {
        /** @var array{width: int, height: int, projection: array{scale: float, translate: array{0: float, 1: float}}, countries: array<string, string>, bounds: array<string, array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}}>} $map */
        $map = $this->map ??= json_decode((string) file_get_contents(resource_path('stats/world-map.json')), true);

        return $map;
    }
}
