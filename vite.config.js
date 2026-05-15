import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    resolve: {
        alias: {
            // Ziggy lives in vendor/ (installed by Composer, not npm).
            // This alias lets app.js import from 'ziggy-js' without a
            // fragile relative path, while making the Composer dependency explicit here.
            'ziggy-js': fileURLToPath(new URL('vendor/tightenco/ziggy', import.meta.url)),
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
