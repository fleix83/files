import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  base: '/files/',
  server: {
    proxy: {
      '/files/api': 'http://localhost:80',
    },
  },
  build: {
    outDir: '../../',
    emptyOutDir: false,
  },
});
