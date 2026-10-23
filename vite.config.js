import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [laravel({
        input: ['resources/css/app.css', 'resources/css/vite-demo.css', 'resources/js/vite-demo.js'],
        refresh: ['resources/views/**/*.blade.php'],
    }), tailwindcss()],
});
