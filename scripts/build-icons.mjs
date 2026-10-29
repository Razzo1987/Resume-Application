#!/usr/bin/env node
/**
 * Generates the SVG icon sprite of a theme with only the icons mapped in its icons.php
 * (plus those of the themes it inherits from). Each symbol has the semantic name as id ("briefcase").
 *
 *   node scripts/build-icons.mjs [theme] [icon source]
 *
 * Theme: "base" if not given.
 * Source: a Font Awesome folder with one file per icon ({style}/{icon}.svg, e.g. "svgs") or
 * with one sprite per style ({style}.svg, e.g. the Font Awesome Pro "sprites"). Defaults to the one of
 * @fortawesome/fontawesome-free. Icons the theme inherits without redefining them are taken from
 * Font Awesome Free when they are missing from the theme's source.
 *
 * Classes accepted in icons.php: "fa-solid fa-briefcase" (FA 6/7) or "fas fa-briefcase" (FA 5;
 * also far, fal, fad, fab). Duotone icons keep the second layer with opacity 0.4.
 * Tabler Icons (MIT): "ti ti-briefcase" (outline) or "ti ti-briefcase-filled", with the folder icons/ of the
 * @tabler/icons package as source ({outline,filled}/{icon}.svg). Outline icons are drawn with the stroke
 * (currentColor, width 2), filled ones with the fill.
 * Output: themes/{theme}/resources/icons/sprite.svg (then compiled by Vite with a hash in the name).
 */
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const themeName = process.argv[2] ?? 'base';
const freeDir = join(root, 'node_modules/@fortawesome/fontawesome-free/svgs');
const sourceDir = resolve(process.argv[3] ?? freeDir);

const STYLES = ['solid', 'regular', 'light', 'thin', 'duotone', 'brands'];
const PREFIXES = { fas: 'solid', far: 'regular', fal: 'light', fat: 'thin', fad: 'duotone', fab: 'brands' };

function themeDir(name) {
    const dir = join(root, 'themes', name);
    if (!existsSync(join(dir, 'theme.json'))) {
        throw new Error(`Theme not found: ${dir}`);
    }
    return dir;
}

/** Map name → { classes, own }, from the base theme to the requested one. */
function iconMap(name, seen = new Set()) {
    if (seen.has(name)) return {};
    seen.add(name);

    const dir = themeDir(name);
    const manifest = JSON.parse(readFileSync(join(dir, 'theme.json'), 'utf8'));
    const parent = manifest.extends ?? (name === 'base' ? null : 'base');
    const inherited = Object.fromEntries(
        Object.entries(parent ? iconMap(parent, seen) : {}).map(([key, icon]) => [key, { ...icon, own: false }]),
    );

    const file = join(dir, 'icons.php');
    const own = {};
    if (existsSync(file)) {
        const source = readFileSync(file, 'utf8');
        for (const [, key, classes] of source.matchAll(/'([a-z0-9-]+)'\s*=>\s*'([^']+)'/g)) {
            own[key] = { classes, own: true };
        }
    }

    return { ...inherited, ...own };
}

/** "fas fa-briefcase" / "fa-solid fa-briefcase" → { style: "solid", icon: "briefcase" }; "ti ti-x" → Tabler */
function parse(classes) {
    const tokens = classes.split(/\s+/).filter(Boolean);
    let style = 'solid';
    let icon = null;

    if (tokens.includes('ti')) {
        const name = tokens.find((token) => token.startsWith('ti-'))?.slice(3) ?? null;
        if (name?.endsWith('-filled')) {
            return { set: 'tabler', style: 'filled', icon: name.slice(0, -'-filled'.length) };
        }
        return { set: 'tabler', style: 'outline', icon: name };
    }

    for (const token of tokens) {
        if (token in PREFIXES) {
            style = PREFIXES[token];
        } else if (STYLES.includes(token.replace(/^fa-/, ''))) {
            style = token.replace(/^fa-/, '');
        } else if (token.startsWith('fa-') && icon === null) {
            icon = token.slice(3);
        }
    }

    return { set: 'fa', style, icon };
}

const spriteCache = new Map();

/** Symbols of a per-style sprite ({style}.svg), indexed by id. */
function spriteSymbols(dir, style) {
    const key = `${dir}/${style}`;
    if (!spriteCache.has(key)) {
        const file = join(dir, `${style}.svg`);
        const symbols = new Map();
        if (existsSync(file)) {
            const source = readFileSync(file, 'utf8');
            const license = source.match(/<!--([\s\S]*?)-->/)?.[1]?.trim() ?? '';
            for (const [, id, viewBox, body] of source.matchAll(/<symbol id="([^"]+)" viewBox="([^"]+)">([\s\S]*?)<\/symbol>/g)) {
                symbols.set(id, { viewBox, body: body.trim(), license });
            }
        }
        spriteCache.set(key, symbols);
    }
    return spriteCache.get(key);
}

/** Icon from a folder: one file per icon or one sprite per style. */
function load(dir, style, icon) {
    const file = join(dir, style, `${icon}.svg`);
    if (existsSync(file)) {
        const svg = readFileSync(file, 'utf8');
        return {
            viewBox: svg.match(/viewBox="([^"]+)"/)?.[1],
            body: svg.replace(/<!--[\s\S]*?-->/g, '').match(/<svg[^>]*>([\s\S]*)<\/svg>/)?.[1]?.trim(),
            license: svg.match(/<!--([\s\S]*?)-->/)?.[1]?.replace(/^!\s*/, '').trim() ?? '',
        };
    }

    return spriteSymbols(dir, style).get(icon) ?? null;
}

const TABLER_LICENSE = 'Tabler Icons by tabler.io - MIT License - https://tabler.io/icons';

/** Tabler icon: drawing without the transparent 24x24 frame, with the stroke or fill of the original <svg> */
function loadTabler(dir, style, icon) {
    const file = join(dir, style, `${icon}.svg`);
    if (!existsSync(file)) return null;

    const svg = readFileSync(file, 'utf8').replace(/<!--[\s\S]*?-->/g, '');
    const body = svg.match(/<svg[^>]*>([\s\S]*)<\/svg>/)?.[1]
        ?.replace(/<path stroke="none" d="M0 0h24v24H0z" fill="none"\s*\/>/g, '')
        .trim();
    const paint = style === 'filled'
        ? '<g fill="currentColor">'
        : '<g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';

    return body ? { viewBox: svg.match(/viewBox="([^"]+)"/)?.[1] ?? '0 0 24 24', body: `${paint}${body}</g>`, license: TABLER_LICENSE } : null;
}

function clean(body) {
    return body
        .replace(/<defs>[\s\S]*?<\/defs>/g, '')
        .replace(/\s*class="fa-secondary"/g, ' opacity=".4"')
        .replace(/\s*class="[^"]*"/g, '')
        .replace(/>\s+</g, '><');
}

const symbols = [];
const licenses = new Set();

for (const [name, { classes, own }] of Object.entries(iconMap(themeName))) {
    const { set, style, icon } = parse(classes);

    if (!icon) {
        throw new Error(`Icon "${name}": invalid classes "${classes}"`);
    }

    let found = set === 'tabler' ? loadTabler(sourceDir, style, icon) : load(sourceDir, style, icon);

    // Icon inherited from a "parent" theme and missing from the theme's source: Font Awesome Free is used
    if (!found && !own && sourceDir !== freeDir) {
        found = load(freeDir, style, icon);
    }

    if (!found?.viewBox || !found?.body) {
        throw new Error(`Icon "${name}" (${classes}): not found in ${sourceDir}`);
    }

    if (found.license) licenses.add(found.license.replace(/\s+/g, ' '));
    symbols.push(`<symbol id="${name}" viewBox="${found.viewBox}">${clean(found.body)}</symbol>`);
}

const output = join(themeDir(themeName), 'resources/icons/sprite.svg');
mkdirSync(dirname(output), { recursive: true });
const header = [...licenses].map((license) => `<!-- ${license.replace(/--/g, '-')} -->`).join('');
writeFileSync(output, `<svg xmlns="http://www.w3.org/2000/svg">${header}${symbols.join('')}</svg>\n`);

console.log(`Sprite ${themeName}: ${symbols.length} icons → ${output.replace(root + '/', '')}`);
