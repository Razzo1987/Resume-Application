// Icons of the Statistics page (operating systems, browsers, devices, channels, bots) in resources/stats/icons.json,
// to rebuild only when the list changes. Source: the @tabler/icons package (MIT License, https://tabler.io/icons),
// outline style; only the inner elements of each SVG are kept (App\Support\Stats\Icons draws the <svg>).
//
//   node scripts/build-stats-icons.mjs path/to/@tabler/icons
import { readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const source = process.argv[2];

if (!source) {
    console.error('Usage: node scripts/build-stats-icons.mjs path/to/@tabler/icons');
    process.exit(1);
}

const ICONS = [
    // operating systems and browsers
    'brand-windows', 'brand-apple', 'brand-android', 'brand-ubuntu', 'brand-debian', 'brand-redhat', 'terminal-2',
    'brand-chrome', 'brand-firefox', 'brand-safari', 'brand-edge', 'brand-opera', 'brand-vivaldi', 'brand-arc', 'brand-yandex',
    // apps and sites
    'brand-facebook', 'brand-instagram', 'brand-linkedin', 'brand-google', 'brand-bing', 'brand-github', 'brand-x', 'brand-reddit',
    'brand-youtube', 'brand-openai', 'brand-bluesky', 'brand-mastodon', 'brand-telegram', 'brand-whatsapp', 'brand-threads',
    'brand-tiktok', 'brand-pinterest',
    // devices
    'device-desktop', 'device-mobile', 'device-tablet', 'device-tv', 'device-gamepad-2', 'car', 'device-watch',
    // channels, bots, networks, generic
    'world', 'world-www', 'search', 'users', 'sparkles', 'mail', 'pointer', 'speakerphone', 'spider', 'heartbeat', 'share',
    'rss', 'robot', 'building', 'router', 'server-2',
];

const icons = {};

for (const name of ICONS) {
    const svg = readFileSync(join(source, 'icons', 'outline', `${name}.svg`), 'utf8');
    icons[name] = svg
        .replace(/^[\s\S]*?<svg[^>]*>/, '')
        .replace(/<\/svg>\s*$/, '')
        .replace(/<path stroke="none" d="M0 0h24v24H0z" fill="none"\s*\/>/, '')
        .replace(/\s*\n\s*/g, '')
        .trim();
}

const output = { license: 'Tabler Icons by tabler.io - MIT License - https://tabler.io/icons', icons };
writeFileSync(new URL('../resources/stats/icons.json', import.meta.url), JSON.stringify(output));
console.log(`resources/stats/icons.json: ${Object.keys(icons).length} icons`);
