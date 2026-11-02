import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // Single Sass entry (Tailwind runs through PostCSS after Sass) + single JS entry.
            input: ['resources/scss/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Don't warn about deprecations inside node_modules (e.g. the Inter font's own Sass sources).
                quietDeps: true,
            },
        },
    },
    build: {
        // Chart.js is lazy-loaded as its own chunk (only pages with a chart download it); keep the warning quiet.
        chunkSizeWarningLimit: 600,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
