import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/manifest-editor.css', 'resources/js/manifest-editor.js', 'resources/css/occupancy-live.css', 'resources/js/occupancy-live.js', 'resources/js/service-orders-live.js', 'resources/css/service-orders.css'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
