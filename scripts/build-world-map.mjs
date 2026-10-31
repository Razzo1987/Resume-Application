// World map for the Statistics page, to rebuild only when the graphics change:
// - resources/stats/world-map.json: borders at 1:110m for the whole world, plus for each country the box of its main
//   part (France without the overseas territories, the United States without Alaska and Hawaii) used to zoom in;
// - resources/stats/world-map-detail.json: borders at 1:50m with the box of each shape, drawn only when zoomed in
//   on a country (only the countries in view).
// Natural Earth (public domain) from the world-atlas package; Equal Earth projection (d3-geo), the same for both.
// The PHP side (App\Support\Stats\WorldMap) draws the SVG and projects the cities with the same formula and parameters.
//
//   node scripts/build-world-map.mjs
import { readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { geoEqualEarth, geoPath } from 'd3-geo';
import { feature } from 'topojson-client';
import countries from 'i18n-iso-countries';

const require = createRequire(import.meta.url);
const load = (scale) => {
    const topology = JSON.parse(readFileSync(require.resolve(`world-atlas/countries-${scale}.json`), 'utf8'));
    const collection = feature(topology, topology.objects.countries);
    // Without Antarctica: no visits from there, and the map stays more compact
    collection.features = collection.features.filter((country) => country.id !== '010');

    return collection;
};
const world = load('110m');
const detail = load('50m');

const width = 1000;
const projection = geoEqualEarth().fitWidth(width, world);
const path = geoPath(projection).digits(1);
const [[, top], [, bottom]] = path.bounds(world);
const height = Math.ceil(bottom - top + 2);
projection.translate([projection.translate()[0], projection.translate()[1] - top + 1]);

// Countries without a numeric ISO code in the Natural Earth data
const byName = { Kosovo: 'XK', 'N. Cyprus': 'CY', Somaliland: 'SO' };
const codeOf = (country) => byName[country.properties.name] ?? countries.numericToAlpha2(country.id);
const round = (box) => box.map((point) => point.map((value) => +value.toFixed(2)));
const shapes = {};

for (const country of world.features) {
    const code = codeOf(country);

    if (!code) {
        console.warn('No code:', country.properties.name);
        continue;
    }

    shapes[code] = (shapes[code] ?? '') + path(country);
}

// Detailed shapes (more digits: they are seen enlarged) and the box of the main part of each country: the largest
// polygon plus the polygons close to it (Corsica and Sicily yes, French Guiana and Alaska no)
const detailPath = geoPath(projection).digits(2);

// A polygon cut by the antimeridian (eastern Russia) is drawn at both edges of the map: its box would cover the whole
// width, so only the eastern hemisphere counts
const mainBounds = (polygon) => {
    const box = path.bounds(polygon);

    if (box[1][0] - box[0][0] < width / 2) {
        return box;
    }

    const east = { type: 'MultiPoint', coordinates: polygon.coordinates.flat().filter(([longitude]) => longitude >= 0) };

    return path.bounds(east);
};
const detailed = {};
const bounds = {};

for (const country of detail.features) {
    const code = codeOf(country);

    if (!code) {
        continue;
    }

    const polygons = country.geometry.type === 'MultiPolygon'
        ? country.geometry.coordinates.map((coordinates) => ({ type: 'Polygon', coordinates }))
        : [country.geometry];
    const parts = polygons.map((polygon) => ({ box: mainBounds(polygon), area: path.area(polygon) })).sort((a, b) => b.area - a.area);
    const [main] = parts;
    const size = Math.hypot(main.box[1][0] - main.box[0][0], main.box[1][1] - main.box[0][1]);
    const center = (box) => [(box[0][0] + box[1][0]) / 2, (box[0][1] + box[1][1]) / 2];
    const [cx, cy] = center(main.box);
    let box = main.box.map((point) => [...point]);

    for (const part of parts.slice(1)) {
        const [px, py] = center(part.box);

        if (Math.hypot(px - cx, py - cy) <= Math.max(size * 0.6, 8)) {
            box = [[Math.min(box[0][0], part.box[0][0]), Math.min(box[0][1], part.box[0][1])], [Math.max(box[1][0], part.box[1][0]), Math.max(box[1][1], part.box[1][1])]];
        }
    }

    const previous = bounds[code];
    bounds[code] = round(previous ? [[Math.min(previous[0][0], box[0][0]), Math.min(previous[0][1], box[0][1])], [Math.max(previous[1][0], box[1][0]), Math.max(previous[1][1], box[1][1])]] : box);
    const shape = detailPath(country);
    const full = round(path.bounds(country));
    detailed[code] = detailed[code] ? { d: detailed[code].d + shape, box: [[Math.min(detailed[code].box[0][0], full[0][0]), Math.min(detailed[code].box[0][1], full[0][1])], [Math.max(detailed[code].box[1][0], full[1][0]), Math.max(detailed[code].box[1][1], full[1][1])]] } : { d: shape, box: full };
}

const [scale, [x, y]] = [projection.scale(), projection.translate()];
const output = { width, height, projection: { type: 'equalEarth', scale: +scale.toFixed(6), translate: [+x.toFixed(6), +y.toFixed(6)] }, countries: shapes, bounds };

writeFileSync(new URL('../resources/stats/world-map.json', import.meta.url), JSON.stringify(output));
writeFileSync(new URL('../resources/stats/world-map-detail.json', import.meta.url), JSON.stringify(detailed));
console.log(`resources/stats/world-map.json: ${Object.keys(shapes).length} countries, ${width}x${height}; world-map-detail.json: ${Object.keys(detailed).length} countries`);
