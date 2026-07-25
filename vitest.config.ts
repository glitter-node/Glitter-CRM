import { defineConfig } from 'vitest/config';
import path from 'path';
import fs from 'fs';

function findProjectRoot(startDir: string): string {
  let directory = startDir;

  while (directory !== path.dirname(directory)) {
    if (fs.existsSync(path.join(directory, 'artisan'))) {
      return directory;
    }
    directory = path.dirname(directory);
  }

  return path.resolve(startDir, '../../');
}

const projectRoot = findProjectRoot(__dirname);

export default defineConfig({
  test: {
    globals: true,
    environment: 'jsdom',
    include: ['resources/js/**/*.{test,spec}.{ts,tsx}'],
    exclude: ['node_modules/', 'dist/'],
    setupFiles: [path.resolve(projectRoot, 'resources/js/tests/setup.ts')],
  },
  resolve: {
    alias: {
      '@': path.resolve(projectRoot, 'resources/js'),
      '@core': path.resolve(projectRoot, 'resources/js/core'),
    },
  },
});
