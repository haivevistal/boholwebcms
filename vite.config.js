import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/admin.jsx', 'resources/js/front.jsx'],
            refresh: ['resources/views/**', 'routes/**', 'app/**', 'content/**/*.php', 'content/**/dist/**'],
        }),
        react(),
        tailwindcss(),
    ],
    build: {
        chunkSizeWarningLimit: 1200,
    },
});
