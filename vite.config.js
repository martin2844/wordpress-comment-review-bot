import { defineConfig } from 'vite';
import { phpPlugin } from '@vitejs/plugin-php';
import { resolve } from 'path';

export default defineConfig({
  plugins: [phpPlugin()],
  build: {
    outDir: 'plugin/assets/js',
    rollupOptions: {
      input: {
        admin: resolve(__dirname, 'src/js/admin.js'),
        frontend: resolve(__dirname, 'src/js/frontend.js'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: '[name].js',
        assetFileNames: '[name].[ext]'
      }
    },
    watch: {
      include: 'src/**',
      exclude: 'node_modules/**'
    }
  },
  server: {
    hmr: {
      port: 3000,
    }
  }
});