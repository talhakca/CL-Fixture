/// <reference types='vitest' />
import { defineConfig } from 'vitest/config';

export default defineConfig({
  root: import.meta.dirname,
  cacheDir: '../../node_modules/.vite/libs/api-sdk',
  test: {
    name: '@champions-league-fixture/api-sdk',
    watch: false,
    globals: true,
    environment: 'node',
    include: ['src/**/*.{test,spec}.ts'],
    exclude: ['src/generated/**'],
    reporters: ['default'],
    coverage: {
      reportsDirectory: './test-output/vitest/coverage',
      provider: 'v8',
      exclude: ['src/generated/**', 'orval.config.ts'],
    },
  },
});
