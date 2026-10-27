// Cartina del mondo per la pagina Statistiche (resources/stats/world-map.json), da rifare solo se cambia la grafica.
// Confini: Natural Earth 1:110m (pubblico dominio) dal pacchetto world-atlas; proiezione Equal Earth (d3-geo).
// Il PHP (App\Support\Stats\WorldMap) disegna l'SVG e proietta le città con la stessa formula e gli stessi parametri.
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

// Senza Antartide: nessuna visita da lì, e la cartina resta più compatta
world.features = world.features.filter((country) => country.id !== '010');

const width = 1000;
const projection = geoEqualEarth().fitWidth(width, world);
const path = geoPath(projection).digits(1);
const [[, top], [, bottom]] = path.bounds(world);
const height = Math.ceil(bottom - top + 2);
projection.translate([projection.translate()[0], projection.translate()[1] - top + 1]);

// Paesi senza codice numerico ISO nei dati di Natural Earth
const byName = { Kosovo: 'XK', 'N. Cyprus': 'CY', Somaliland: 'SO' };
const shapes = {};

for (const country of world.features) {
    const code = byName[country.properties.name] ?? countries.numericToAlpha2(country.id);

    if (!code) {
        console.warn('Senza codice:', country.properties.name);
        continue;
    }

    shapes[code] = (shapes[code] ?? '') + path(country);
}

const [scale, [x, y]] = [projection.scale(), projection.translate()];
const output = { width, height, projection: { type: 'equalEarth', scale: +scale.toFixed(6), translate: [+x.toFixed(6), +y.toFixed(6)] }, countries: shapes };

writeFileSync(new URL('../resources/stats/world-map.json', import.meta.url), JSON.stringify(output));
console.log(`resources/stats/world-map.json: ${Object.keys(shapes).length} paesi, ${width}x${height}`);
