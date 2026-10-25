#!/usr/bin/env node
/**
 * Genera lo sprite SVG delle icone di un tema con le sole icone mappate nel suo icons.php
 * (più quelle dei temi da cui eredita). Ogni simbolo ha come id il nome semantico ("briefcase").
 *
 *   node scripts/build-icons.mjs [tema] [cartella svgs]
 *
 * Tema: "base" se non indicato. Cartella svgs: quella di @fortawesome/fontawesome-free
 * (un tema PRO indica la propria, es. node_modules/@fortawesome/fontawesome-pro/svgs).
 * Uscita: themes/{tema}/resources/icons/sprite.svg (compilato poi da Vite con hash nel nome).
 */
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const themeName = process.argv[2] ?? 'base';
const svgsDir = resolve(process.argv[3] ?? join(root, 'node_modules/@fortawesome/fontawesome-free/svgs'));

function themeDir(name) {
    const dir = join(root, 'themes', name);
    if (!existsSync(join(dir, 'theme.json'))) {
        throw new Error(`Tema non trovato: ${dir}`);
    }
    return dir;
}

/** Mappa nome → "fa-solid fa-briefcase", dal tema base al tema richiesto. */
function iconMap(name, seen = new Set()) {
    if (seen.has(name)) return {};
    seen.add(name);

    const dir = themeDir(name);
    const manifest = JSON.parse(readFileSync(join(dir, 'theme.json'), 'utf8'));
    const parent = manifest.extends ?? (name === 'base' ? null : 'base');
    const inherited = parent ? iconMap(parent, seen) : {};

    const file = join(dir, 'icons.php');
    const own = {};
    if (existsSync(file)) {
        const source = readFileSync(file, 'utf8');
        for (const [, key, classes] of source.matchAll(/'([a-z0-9-]+)'\s*=>\s*'([^']+)'/g)) {
            own[key] = classes;
        }
    }

    return { ...inherited, ...own };
}

const styles = { solid: 'solid', regular: 'regular', brands: 'brands', light: 'light', thin: 'thin', duotone: 'duotone' };
const symbols = [];
let license = '';

for (const [name, classes] of Object.entries(iconMap(themeName))) {
    const style = classes.match(/\bfa-(solid|regular|brands|light|thin|duotone)\b/)?.[1] ?? 'solid';
    const icon = classes.split(/\s+/).map((c) => c.replace(/^fa-/, '')).find((c) => !(c in styles));
    const file = join(svgsDir, styles[style], `${icon}.svg`);

    if (!existsSync(file)) {
        throw new Error(`Icona "${name}": file non trovato ${file}`);
    }

    const svg = readFileSync(file, 'utf8');
    license ||= svg.match(/<!--([\s\S]*?)-->/)?.[1]?.replace(/^!\s*/, '').trim() ?? '';
    const viewBox = svg.match(/viewBox="([^"]+)"/)?.[1];
    const body = svg.replace(/<!--[\s\S]*?-->/g, '').match(/<svg[^>]*>([\s\S]*)<\/svg>/)?.[1]?.trim();

    if (!viewBox || !body) {
        throw new Error(`Icona "${name}": SVG non valido (${file})`);
    }

    symbols.push(`<symbol id="${name}" viewBox="${viewBox}">${body.replace(/\s*class="[^"]*"/g, '')}</symbol>`);
}

const output = join(themeDir(themeName), 'resources/icons/sprite.svg');
mkdirSync(dirname(output), { recursive: true });
writeFileSync(
    output,
    `<svg xmlns="http://www.w3.org/2000/svg">${license ? `<!-- ${license} -->` : ''}${symbols.join('')}</svg>\n`,
);

console.log(`Sprite ${themeName}: ${symbols.length} icone → ${output.replace(root + '/', '')}`);
