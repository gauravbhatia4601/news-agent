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
      Inter: [400, 500, 600, 700],
      'JetBrains Mono': [400, 500, 600, 700],
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
      titleTemplate: '%s — The AI Journal',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: 'AI-powered news engine delivering curated, fact-driven journalism with an Indian perspective. Covering politics, business, technology, sports, and culture.' },
        { name: 'theme-color', content: '#1a2233' },
        { property: 'og:site_name', content: 'The AI Journal' },
        { property: 'og:type', content: 'website' },
        { property: 'og:locale', content: 'en_IN' },
        { name: 'twitter:card', content: 'summary_large_image' },
        { name: 'twitter:site', content: '@theaijournal' },
      ],
      link: [
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
      ],
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
