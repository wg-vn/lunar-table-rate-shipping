import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import lunarPanelPlugin from '@lunarphp/panel-vite-plugin';

// One IIFE bundle (build/) that runs on the panel's own Vue, Inertia and component instances.
export default defineConfig({
    plugins: [vue(), lunarPanelPlugin({ name: 'LunarTableRateShipping' })],
    build: {
        outDir: 'build',
        rollupOptions: {
            input: 'resources/js/addon.ts',
        },
    },
});
