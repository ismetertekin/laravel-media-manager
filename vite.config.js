import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [
        vue(),
    ],
    build: {
        outDir: resolve(__dirname, 'dist'),
        emptyOutDir: true,
        lib: {
            entry: resolve(__dirname, 'resources/js/media-manager.js'),
            name: 'MediaManager',
            fileName: () => 'media-manager.js',
            cssFileName: 'media-manager',
            formats: ['iife'], // IIFE format creates a single standalone JS file without exports/imports
        },
        rollupOptions: {
            // We do not externalize Vue or Axios so they are bundled into the file.
            // This makes the package truly plug-and-play.
            external: [],
        },
    },
    define: {
        'process.env.NODE_ENV': JSON.stringify('production'),
    }
});
