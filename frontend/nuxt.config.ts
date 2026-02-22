// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  srcDir: '.',
  compatibilityDate: '2024-11-01',
  devtools: { enabled: true },

  runtimeConfig: {
    // Server-only: used by Nitro proxy routes to reach backend
    backendApiBase: process.env.BACKEND_API_BASE || 'http://127.0.0.1:8001',
  },

  modules: [
    '@nuxtjs/tailwindcss',
    '@nuxtjs/google-fonts',
  ],

  googleFonts: {
    families: {
      Inter: [400, 500, 600, 700],
      Merriweather: [400, 700],
    },
  },

  css: ['~/assets/css/tailwind.css'],
})
