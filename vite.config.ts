import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        outDir: 'src/Resources/public',
        emptyOutDir: false,
        rollupOptions: {
            input: 'src/Resources/assets/src/pwa.ts',
            output: {
                format: 'es',
                entryFileNames: 'pwa.js',
                assetFileNames: 'pwa.[ext]',
            },
        },
        minify: true,
        sourcemap: false,
    },
});
