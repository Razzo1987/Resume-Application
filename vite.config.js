import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath } from 'node:url';

// Asset del tema base: compilati in public/themes/base (manifest incluso).
// I temi PRO hanno una build propria nel loro repository.
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'themes/base/resources/scss/app.scss',
                'themes/base/resources/js/app.js',
            ],
            buildDirectory: 'themes/base',
            refresh: ['themes/base/views/**'],
        }),
    ],
    resolve: {
        alias: {
            '@fa-webfonts': fileURLToPath(new URL('./node_modules/@fortawesome/fontawesome-free/webfonts', import.meta.url)),
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 usa ancora @import: silenziamo gli avvisi di deprecazione di Dart Sass
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'mixed-decls', 'if-function'],
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
