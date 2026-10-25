#!/usr/bin/env node
/**
 * Genera lo sprite SVG delle icone di un tema con le sole icone mappate nel suo icons.php
 * (più quelle dei temi da cui eredita). Ogni simbolo ha come id il nome semantico ("briefcase").
 *
 *   node scripts/build-icons.mjs [tema] [sorgente icone]
 *
 * Tema: "base" se non indicato.
 * Sorgente: cartella di Font Awesome con un file per icona ({stile}/{icona}.svg, es. "svgs") oppure
 * con uno sprite per stile ({stile}.svg, es. "sprites" di Font Awesome Pro). Di default quella di
 * @fortawesome/fontawesome-free. Le icone che il tema eredita senza ridefinirle, se mancano nella
 * sorgente del tema, vengono prese da Font Awesome Free.
 *
 * Classi accettate in icons.php: "fa-solid fa-briefcase" (FA 6/7) oppure "fas fa-briefcase" (FA 5;
 * anche far, fal, fad, fab). Le icone duotone mantengono il secondo livello con opacità 0,4.
 * Uscita: themes/{tema}/resources/icons/sprite.svg (compilato poi da Vite con hash nel nome).
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
        throw new Error(`Tema non trovato: ${dir}`);
    }
    return dir;
}

/** Mappa nome → { classes, own }, dal tema base al tema richiesto. */
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

/** "fas fa-briefcase" / "fa-solid fa-briefcase" → { style: "solid", icon: "briefcase" } */
function parse(classes) {
    const tokens = classes.split(/\s+/).filter(Boolean);
    let style = 'solid';
    let icon = null;

    for (const token of tokens) {
        if (token in PREFIXES) {
            style = PREFIXES[token];
        } else if (STYLES.includes(token.replace(/^fa-/, ''))) {
            style = token.replace(/^fa-/, '');
        } else if (token.startsWith('fa-') && icon === null) {
            icon = token.slice(3);
        }
    }

    return { style, icon };
}

const spriteCache = new Map();

/** Simboli di uno sprite per stile ({stile}.svg), indicizzati per id. */
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

/** Icona da una cartella: un file per icona oppure uno sprite per stile. */
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
    const { style, icon } = parse(classes);

    if (!icon) {
        throw new Error(`Icona "${name}": classi non valide "${classes}"`);
    }

    let found = load(sourceDir, style, icon);

    // Icona ereditata da un tema "padre" e assente nella sorgente del tema: si usa Font Awesome Free
    if (!found && !own && sourceDir !== freeDir) {
        found = load(freeDir, style, icon);
    }

    if (!found?.viewBox || !found?.body) {
        throw new Error(`Icona "${name}" (${classes}): non trovata in ${sourceDir}`);
    }

    if (found.license) licenses.add(found.license.replace(/\s+/g, ' '));
    symbols.push(`<symbol id="${name}" viewBox="${found.viewBox}">${clean(found.body)}</symbol>`);
}

const output = join(themeDir(themeName), 'resources/icons/sprite.svg');
mkdirSync(dirname(output), { recursive: true });
const header = [...licenses].map((license) => `<!-- ${license.replace(/--/g, '-')} -->`).join('');
writeFileSync(output, `<svg xmlns="http://www.w3.org/2000/svg">${header}${symbols.join('')}</svg>\n`);

console.log(`Sprite ${themeName}: ${symbols.length} icone → ${output.replace(root + '/', '')}`);
