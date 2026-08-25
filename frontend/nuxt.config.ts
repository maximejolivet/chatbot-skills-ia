import fs from 'node:fs';

// `process.dev` isn't set in this top-level config-evaluation context (only
// inside Nuxt runtime code) -- NODE_ENV is the reliable check here.
const isDev = 'production' !== process.env.NODE_ENV;

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  devtools: { enabled: true },
  app: {
    head: {
      // title/description are translated (i18n/locales/fr.json meta.*),
      // set via useHead() in app.vue instead of statically here -- this
      // config is evaluated before the i18n module's runtime is available.
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap',
        },
      ],
    },
  },
  modules: ['@nuxtjs/tailwindcss', '@nuxtjs/i18n', '@nuxt/image'],
  css: ['~/assets/css/main.css'],
  // Security headers on every response. No CSP nonce infrastructure here
  // (would need per-request head injection wired through Nuxt's SSR
  // context) -- 'unsafe-inline' on script/style is the pragmatic call for
  // an SSR Vue app without one: Nuxt's own hydration payload is an inline
  // <script>, and Vue SFC scoped styles/critical CSS can land inline too.
  // 'unsafe-eval' only in dev -- Vite's HMR module runner needs it; the
  // production build never does. connect-src allows dev's HMR websocket
  // (ws:) for the same reason.
  routeRules: {
    '/**': {
      headers: {
        'Strict-Transport-Security': 'max-age=31536000; includeSubDomains',
        'X-Content-Type-Options': 'nosniff',
        'X-Frame-Options': 'SAMEORIGIN',
        'Referrer-Policy': 'strict-origin-when-cross-origin',
        'Content-Security-Policy': [
          "default-src 'self'",
          `script-src 'self' 'unsafe-inline'${isDev ? " 'unsafe-eval'" : ''}`,
          "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
          "font-src 'self' https://fonts.gstatic.com",
          "img-src 'self' data:",
          `connect-src 'self'${isDev ? ' ws:' : ''}`,
          "frame-ancestors 'self'",
          "object-src 'none'",
          "base-uri 'self'",
          "form-action 'self'",
        ].join('; '),
      },
    },
  },
  // Infrastructure only for now: every user-facing string lives in
  // i18n/locales/fr.json instead of hardcoded in components, so adding a
  // second language later is "translate the file", not "hunt down strings
  // across every .vue file". `no_prefix` (no /en, /fr in the URL) since
  // there's only one locale -- revisit the strategy once a second one
  // actually exists.
  i18n: {
    locales: [{ code: 'fr', language: 'fr-FR', file: 'fr.json' }],
    defaultLocale: 'fr',
    langDir: 'locales',
    strategy: 'no_prefix',
  },
  runtimeConfig: {
    // Server-only: used by server/api/[...path].ts to authenticate to the
    // Symfony backend via HTTP Basic on behalf of the public chat widget.
    adminUsername: process.env.ADMIN_USERNAME || '',
    adminPassword: process.env.ADMIN_PASSWORD || '',
    public: {
      apiUrl: process.env.API_URL || 'http://chatbot-symfony:8000',
    },
  },
  vite: {
    vue: {
      script: {
        // TypeScript 7 breaks @vue/compiler-sfc's Node-environment detection
        // used to resolve type-only prop imports (defineProps<X>()), causing
        // "No fs option provided to compileScript in non-Node environment".
        // Passing Node's fs explicitly works around it.
        fs: {
          fileExists: (file: string) => fs.existsSync(file),
          readFile: (file: string) => fs.readFileSync(file, 'utf-8'),
        },
      },
    },
  },
});
