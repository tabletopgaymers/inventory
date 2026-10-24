import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [laravel({
        input: [
            'resources/css/app.css',
            'resources/css/style-guide.css',
            'resources/css/purchase.css',
            'resources/css/relocation.css',
            'resources/css/print.css',
            'resources/js/print.js',
            'resources/js/catalog.js',
            'resources/js/catalog-progress.js',
            'resources/js/catalog-status-filter.js',
            'resources/js/user-actions.js',
            'resources/js/catalog-form.js',
            'resources/js/inventory.js',
            'resources/js/inventory-browse.js',
            'resources/js/location-counts.js',
            'resources/js/requests.js',
            'resources/css/event-report.css',
            'resources/css/vite-demo.css',
            'resources/js/vite-demo.js',
            'resources/js/relocation.js',
            'resources/js/purchase-request.js',
            'resources/js/purchase-work-invoice.js',
            'resources/js/workflow-demo.js',
        ],
        refresh: ['resources/views/**/*.blade.php'],
    }), tailwindcss()],
});
