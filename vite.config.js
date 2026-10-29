import { readFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Assets of a theme, compiled into public/themes/{theme} (manifest included).
// "base" theme by default; another theme (copied to themes/{theme}) with THEME=name, e.g. scripts/build-theme.sh.
const theme = process.env.THEME ?? 'base';

if (!/^[a-z0-9][a-z0-9-]*$/.test(theme)) {
    throw new Error(`Invalid theme name: ${theme}`);
}

const manifest = JSON.parse(readFileSync(`themes/${theme}/theme.json`, 'utf8'));

// Entry points of the theme and of all its variants (palettes/fonts chosen in the admin)
const input = [
    ...manifest.entrypoints,
    ...Object.values(manifest.variants ?? {}).flatMap((variant) => variant.entrypoints ?? []),
].filter((entry, index, all) => all.indexOf(entry) === index);

export default defineConfig({
    plugins: [
        laravel({
            input,
            buildDirectory: `themes/${theme}`,
            refresh: [`themes/${theme}/views/**`, 'themes/base/views/**'],
        }),
    ],
    build: {
        // The icon sprite must stay a file (used with <svg><use href="...#name">), never inlined
        assetsInlineLimit: 0,
    },
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 still uses @import: silence the Dart Sass deprecation warnings
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
                quietDeps: true,
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
