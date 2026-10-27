// World map for the Statistics page (resources/stats/world-map.json), to rebuild only when the graphics change.
// Borders: Natural Earth 1:110m (public domain) from the world-atlas package; Equal Earth projection (d3-geo).
// The PHP side (App\Support\Stats\WorldMap) draws the SVG and projects the cities with the same formula and parameters.
//
//   node scripts/build-world-map.mjs
import { readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { geoEqualEarth, geoPath } from 'd3-geo';
import { feature } from 'topojson-client';
import countries from 'i18n-iso-countries';

const require = createRequire(import.meta.url);
const topology = JSON.parse(readFileSync(require.resolve('world-atlas/countries-110m.json'), 'utf8'));
const world = feature(topology, topology.objects.countries);

// Without Antarctica: no visits from there, and the map stays more compact
world.features = world.features.filter((country) => country.id !== '010');

const width = 1000;
const projection = geoEqualEarth().fitWidth(width, world);
const path = geoPath(projection).digits(1);
const [[, top], [, bottom]] = path.bounds(world);
const height = Math.ceil(bottom - top + 2);
projection.translate([projection.translate()[0], projection.translate()[1] - top + 1]);

// Countries without a numeric ISO code in the Natural Earth data
const byName = { Kosovo: 'XK', 'N. Cyprus': 'CY', Somaliland: 'SO' };
const shapes = {};

for (const country of world.features) {
    const code = byName[country.properties.name] ?? countries.numericToAlpha2(country.id);

    if (!code) {
        console.warn('No code:', country.properties.name);
        continue;
    }

    shapes[code] = (shapes[code] ?? '') + path(country);
}

const [scale, [x, y]] = [projection.scale(), projection.translate()];
const output = { width, height, projection: { type: 'equalEarth', scale: +scale.toFixed(6), translate: [+x.toFixed(6), +y.toFixed(6)] }, countries: shapes };

writeFileSync(new URL('../resources/stats/world-map.json', import.meta.url), JSON.stringify(output));
console.log(`resources/stats/world-map.json: ${Object.keys(shapes).length} countries, ${width}x${height}`);
