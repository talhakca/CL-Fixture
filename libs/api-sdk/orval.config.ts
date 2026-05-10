import { defineConfig } from 'orval';

export default defineConfig({
  api: {
    input: {
      target: '../../apps/api/storage/api-docs/openapi.json',
    },
    output: {
      target: 'src/generated/api.ts',
      schemas: 'src/generated/schemas',
      mode: 'split',
      client: 'axios-functions',
      clean: ['src/generated/**'],
      prettier: true,
      indexFiles: true,
      override: {
        mutator: {
          path: './src/http.ts',
          name: 'customInstance',
        },
      },
    },
  },
});
