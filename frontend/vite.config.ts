import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig(() => ({
  plugins: [
    vue(),
    tailwindcss(),
    VitePWA({
      registerType: 'autoUpdate',
      devOptions: { enabled: false },
      workbox: {
        skipWaiting: true,
        clientsClaim: true,
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api/],
        runtimeCaching: [
          // Fonts (Google etc.) — cache forever
          {
            urlPattern: /^https:\/\/fonts\./,
            handler: 'CacheFirst',
            options: {
              cacheName: 'fonts',
              expiration: { maxEntries: 10, maxAgeSeconds: 60 * 60 * 24 * 365 },
            },
          },

          // GET /api/v1/days/{date} — main day view
          {
            urlPattern: ({ url, request }) =>
              request.method === 'GET' &&
              url.pathname.startsWith('/api/v1/days/'),
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api-days',
              networkTimeoutSeconds: 3,
              expiration: { maxEntries: 50, maxAgeSeconds: 60 * 60 * 24 * 30 },
              cacheableResponse: { statuses: [0, 200] },
            },
          },

          // GET /api/v1/stats/*
          {
            urlPattern: ({ url, request }) =>
              request.method === 'GET' &&
              url.pathname.startsWith('/api/v1/stats/'),
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api-stats',
              networkTimeoutSeconds: 3,
              expiration: { maxEntries: 20, maxAgeSeconds: 60 * 60 * 24 },
              cacheableResponse: { statuses: [0, 200] },
            },
          },

          // GET /api/v1/dishes, /goals, /profile, /auth/me — slow-changing
          {
            urlPattern: ({ url, request }) =>
              request.method === 'GET' &&
              /^\/api\/v1\/(dishes|goals|profile|auth\/me)/.test(url.pathname),
            handler: 'StaleWhileRevalidate',
            options: {
              cacheName: 'api-static',
              expiration: { maxEntries: 30, maxAgeSeconds: 60 * 60 * 24 * 7 },
              cacheableResponse: { statuses: [0, 200] },
            },
          },
        ],
      },
      manifest: {
        name: 'Кал Трекер',
        short_name: 'Кал Трекер',
        start_url: '/?source=pwa',
        display: 'standalone',
        theme_color: '#FAFAF7',
        background_color: '#FAFAF7',
        lang: 'ru',
        icons: [
          { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
          { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
          { src: '/icons/icon-512-maskable.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
      },
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
}))
