import { readFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Asset di un tema, compilati in public/themes/{tema} (manifest incluso).
// Tema "base" di default; un altro tema (copiato in themes/{tema}) con THEME=nome, es. scripts/build-theme.sh.
const theme = process.env.THEME ?? 'base';

if (!/^[a-z0-9][a-z0-9-]*$/.test(theme)) {
    throw new Error(`Nome di tema non valido: ${theme}`);
}

const manifest = JSON.parse(readFileSync(`themes/${theme}/theme.json`, 'utf8'));

export default defineConfig({
    plugins: [
        laravel({
            input: manifest.entrypoints,
            buildDirectory: `themes/${theme}`,
            refresh: [`themes/${theme}/views/**`, 'themes/base/views/**'],
        }),
    ],
    build: {
        // Lo sprite delle icone deve restare un file (usato con <svg><use href="...#nome">), mai inline
        assetsInlineLimit: 0,
    },
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 usa ancora @import: silenziamo gli avvisi di deprecazione di Dart Sass
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
