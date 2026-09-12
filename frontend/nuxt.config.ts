export default defineNuxtConfig({
  srcDir: '.',
  compatibilityDate: '2024-11-01',
  devtools: { enabled: true },

  runtimeConfig: {
    backendApiBase: process.env.NUXT_BACKEND_API_BASE || process.env.BACKEND_API_BASE || '',
    public: {
      adsenseClient: process.env.ADSENSE_CLIENT || '',
      adsenseSlot: process.env.ADSENSE_SLOT || '',
      umamiWebsiteId: process.env.NUXT_PUBLIC_UMAMI_WEBSITE_ID || '',
      siteUrl: process.env.NUXT_PUBLIC_SITE_URL || 'https://news.technioz.com',
      newBadgeHours: Number(process.env.NUXT_PUBLIC_NEW_BADGE_HOURS || 6),
    },
  },

  modules: [
    '@nuxtjs/tailwindcss',
    '@nuxtjs/google-fonts',
  ],

  googleFonts: {
    display: 'swap',
    preconnect: true,
    families: {
      'Playfair Display': [700],
      'Source Serif 4': [400, 600],
      Inter: [400, 500, 600, 700],
    },
  },

  tailwindcss: {
    config: {
      theme: {
        extend: {
          fontFamily: {
            display: ['"Playfair Display"', 'sans-serif'],
            serif: ['"Source Serif 4"', 'sans-serif'],
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
      htmlAttrs: { lang: 'en' },
      titleTemplate: '%s — The Neural Journal',
      script: [
        {
          src: 'https://analytics.ahrefs.com/analytics.js',
          'data-key': 'ORT1RC2upv80EsIo9fWuWg',
          async: true,
        },
      ],
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: 'AI-powered news engine delivering curated, fact-driven journalism with an Indian perspective. Covering politics, business, technology, sports, and culture.' },
        { name: 'theme-color', content: '#1a2233' },
        { name: 'ahrefs-site-verification', content: 'd4c7e273ac3f8b0907551ea6ba488dfd92d567b16bf0f066efb76b32718712a6' },
        { property: 'og:site_name', content: 'The Neural Journal' },
        { property: 'og:type', content: 'website' },
        { property: 'og:locale', content: 'en_IN' },
        { property: 'og:image', content: 'https://news.technioz.com/og-default.jpg' },
        { property: 'og:image:alt', content: 'The Neural Journal — AI-Powered News' },
        { property: 'og:image:width', content: '1200' },
        { property: 'og:image:height', content: '630' },
        { name: 'twitter:card', content: 'summary_large_image' },
        { name: 'twitter:site', content: '@theneuraljournal' },
        { name: 'twitter:image', content: 'https://news.technioz.com/og-default.jpg' },
      ],
      link: [
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
      ],
    },
  },

  nitro: {
    routeRules: {
      '/': { swr: 120 },
      '/article/**': { swr: 300 },
      '/category/**': { swr: 600 },
      '/stories': { swr: 300 },
      '/story/**': { swr: 120 },
      '/trending': { swr: 300 },
    },
  },
})
