import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [laravel({
        input: ['resources/css/app.css', 'resources/css/vite-demo.css', 'resources/js/vite-demo.js', 'resources/js/relocation.js', 'resources/js/purchase-request.js', 'resources/js/purchase-work-invoice.js', 'resources/js/workflow-demo.js'],
        refresh: ['resources/views/**/*.blade.php'],
    }), tailwindcss()],
});
