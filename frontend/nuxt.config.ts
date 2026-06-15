export default defineNuxtConfig({
  srcDir: '.',
  compatibilityDate: '2024-11-01',
  devtools: { enabled: true },

  runtimeConfig: {
    backendApiBase: process.env.BACKEND_API_BASE || 'http://127.0.0.1:8001',
  },

  modules: [
    '@nuxtjs/tailwindcss',
    '@nuxtjs/google-fonts',
  ],

  googleFonts: {
    families: {
      'Playfair Display': [400, 700],
      'Source Serif 4': [400, 600, 700],
      Inter: [400, 500, 700],
    },
  },

  tailwindcss: {
    config: {
      theme: {
        extend: {
          fontFamily: {
            display: ['"Playfair Display"', 'serif'],
            serif: ['"Source Serif 4"', 'serif'],
            sans: ['Inter', 'sans-serif'],
            label: ['Inter', 'sans-serif'],
          },
        },
      },
    },
  },

  css: ['~/assets/css/tailwind.css'],

  app: {
    head: {
      script: [
        {
          src: 'https://umami.technioz.com/script.js',
          defer: true,
          'data-website-id': '3a676a6d-3b90-4a60-acb1-6cd8f75d0601',
        },
      ],
    },
  },
})
