import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  base: '/',
  server: {
    host: '127.0.0.1',
    port: 5173,
    strictPort: true,
    hmr: {
      host: '127.0.0.1',
      clientPort: 5173,
      protocol: 'http',
    },
  },
  plugins: [
    laravel({
      input: ['resources/js/app.js'],
      ssr: 'resources/js/ssr.js',
      refresh: true,
      hotFile: 'public/hot', // Default Laravel path
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false
        }
      },
    }),
  ],
  build: {
    outDir: 'public/build',
    manifest: true,
    rollupOptions: {
      input: 'resources/js/app.js',
    },
  },
  resolve: {
    alias: {
      '@': '/resources/js',
    },
  },
})
