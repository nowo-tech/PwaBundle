import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['src/Resources/assets/**/*.test.ts'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'text-summary', 'html'],
            reportsDirectory: './coverage-ts',
            include: [
                'src/Resources/assets/src/pwa-client.ts',
                'src/Resources/assets/src/push-checks.ts',
                'src/Resources/assets/src/browser-detect.ts',
                'src/Resources/assets/stimulus-peers/*.ts',
            ],
            exclude: ['**/*.test.ts', '**/node_modules/**'],
            thresholds: {
                statements: 100,
                branches: 100,
                functions: 100,
                lines: 100,
            },
        },
    },
});
