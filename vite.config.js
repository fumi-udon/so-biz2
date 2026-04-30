import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        // Filament 管理画面テーマは Tailwind v3（Filament 公式プリセット）で別ビルド: npm run build:filament
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // KDS V2: POS (Livewire/Alpine) と完全分離した Vue 3 + Pinia エントリ
                'resources/js/kds2/main.js',
                // POS2 V2 shell: Livewire/Alpine と完全分離した Inertia + Vue 3 + Pinia
                'resources/js/pos2/main.js',
            ],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
        
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
