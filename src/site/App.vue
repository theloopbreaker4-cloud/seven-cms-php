<template>
  <!-- Animated background -->
  <div class="site-bg" aria-hidden="true">
    <div class="site-bg__dots"></div>
    <div class="site-bg__orb site-bg__orb--a"></div>
    <div class="site-bg__orb site-bg__orb--b"></div>
    <div class="site-bg__orb site-bg__orb--c"></div>
    <svg class="site-bg__svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice">
      <polygon class="site-bg__shape site-bg__shape--spin"
        points="720,120 820,175 820,285 720,340 620,285 620,175"
        stroke-width="1.5" transform-origin="720 230" />
      <circle class="site-bg__shape site-bg__shape--spin-rev"
        cx="180" cy="700" r="120" stroke-width="1" transform-origin="180 700" />
      <polygon class="site-bg__shape site-bg__shape--pulse"
        points="1300,100 1380,240 1220,240" stroke-width="1.2" />
      <line x1="0" y1="450" x2="350" y2="450" stroke="var(--primary)" stroke-opacity="0.05" stroke-width="1"/>
      <line x1="1090" y1="450" x2="1440" y2="450" stroke="var(--primary)" stroke-opacity="0.05" stroke-width="1"/>
      <circle class="site-bg__shape site-bg__shape--spin"
        cx="1200" cy="650" r="60" stroke-width="1" transform-origin="1200 650" />
      <polygon class="site-bg__shape site-bg__shape--pulse"
        points="120,200 160,260 120,320 80,260" stroke-width="1" />
    </svg>
  </div>

  <header class="border-b border-[var(--border-color)] bg-[var(--bg-secondary)]/80 backdrop-blur-sm sticky top-0 z-50">
    <div class="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between">
      <RouterLink :to="`/${lang}/`" class="flex items-center gap-2">
        <img :src="`/brand.svg`" alt="Seven CMS" class="w-8 h-8 rounded-lg" />
        <span class="font-bold text-lg text-[var(--primary)]">Seven <span class="text-[var(--text-secondary)]">CMS</span></span>
      </RouterLink>

      <nav class="flex items-center gap-5 text-sm font-medium">
        <RouterLink :to="`/${lang}/`"     class="hover:text-[var(--primary)] transition-colors">{{ t('nav.home') }}</RouterLink>
        <RouterLink :to="`/${lang}/blog`" class="hover:text-[var(--primary)] transition-colors">{{ t('nav.blog') }}</RouterLink>
        <template v-if="isLogin">
          <a :href="`/${lang}/admin`"       class="hover:text-[var(--primary)] transition-colors">{{ t('nav.dashboard') }}</a>
          <a :href="`/${lang}/auth/logout`" class="hover:text-[var(--primary)] transition-colors">{{ t('nav.signout') }}</a>
        </template>
        <template v-else>
          <RouterLink :to="`/${lang}/auth`" class="hover:text-[var(--primary)] transition-colors">{{ t('nav.signin') }}</RouterLink>
        </template>

        <!-- Language dropdown -->
        <div class="relative" @mouseenter="openLang" @mouseleave="closeLang">
          <button class="flex items-center gap-1.5 px-2 py-1 rounded-md hover:bg-[var(--bg-tertiary)] transition-colors normal-case tracking-normal text-sm">
            <img :src="flagUrl(currentLang.code)" :alt="currentLang.code" class="w-5 h-3.5 rounded-[3px] object-cover" />
            <span class="text-[var(--text-secondary)]">{{ currentLang.code?.toUpperCase() }}</span>
            <svg class="w-3 h-3 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <div v-show="langOpen"
               class="absolute right-0 top-full pt-1 min-w-44 z-50">
            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-lg shadow-lg overflow-hidden">
            <a v-for="l in languages" :key="l.code"
               :href="langUrl(l.code)"
               class="flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-[var(--bg-tertiary)] transition-colors"
               :class="l.code === lang ? 'text-[var(--primary)] font-semibold bg-[var(--bg-tertiary)]' : 'text-[var(--text-secondary)]'">
              <img :src="flagUrl(l.code)" :alt="l.code" class="w-6 h-4 rounded-[3px] object-cover flex-shrink-0" />
              <span>{{ l.nativeName }}</span>
              <span class="ml-auto text-xs text-[var(--text-tertiary)] font-mono">{{ l.code?.toUpperCase() }}</span>
            </a>
            </div>
          </div>
        </div>

        <!-- Theme toggle -->
        <button @click="toggleTheme"
                class="p-1.5 rounded-md text-[var(--text-secondary)] hover:text-[var(--primary)] hover:bg-[var(--bg-tertiary)] transition-colors normal-case tracking-normal"
                aria-label="Toggle theme">
          <svg v-if="theme === 'dark'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 3v1m0 16v1m8.66-9h-1M4.34 12h-1m15.07-6.07-.7.7M6.34 17.66l-.7.7M17.66 17.66l.7.7M6.34 6.34l.7.7M12 8a4 4 0 100 8 4 4 0 000-8z"/>
          </svg>
          <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
          </svg>
        </button>
      </nav>
    </div>
  </header>

  <main class="flex-1">
    <RouterView />
  </main>

  <SiteFooter :lang="lang" :is-login="isLogin" />
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import SiteFooter from './components/SiteFooter.vue'

const data      = window.__DATA__  || {}
const lang      = window.__LANG__  || 'en'
const i18n      = data.i18n        || {}
// languages is now [{code, name, nativeName, flag, isDefault}]
const languages = data.languages   || [{ code: 'en', name: 'English', nativeName: 'English', flag: '🇬🇧', isDefault: true }]
const isLogin   = data.isLogin     || false

const theme        = ref(localStorage.getItem('theme') || 'light')
const langOpen     = ref(false)
let   langCloseTimer = null

function openLang()  {
  clearTimeout(langCloseTimer)
  langOpen.value = true
}
function closeLang() {
  langCloseTimer = setTimeout(() => { langOpen.value = false }, 500)
}

const currentLang = computed(() =>
  languages.find(l => l.code === lang) || { code: lang, flag: '🌐', nativeName: lang }
)

onMounted(() => {
  document.documentElement.setAttribute('data-theme', theme.value)
})

function toggleTheme() {
  theme.value = theme.value === 'light' ? 'dark' : 'light'
  document.documentElement.setAttribute('data-theme', theme.value)
  localStorage.setItem('theme', theme.value)
}

function t(key) {
  return i18n[key] || i18n[key.split('.').pop()] || key.split('.').pop()
}

function langUrl(code) {
  // Replace first path segment (the lang prefix) with new code
  const parts = window.location.pathname.split('/')
  parts[1] = code
  return parts.join('/') + window.location.search
}

// Map lang code → ISO 3166-1 alpha-2 country code for flag CDN
const LANG_TO_COUNTRY = {
  en: 'gb', ru: 'ru', ka: 'ge', uk: 'ua', az: 'az', hy: 'am',
  be: 'by', de: 'de', fr: 'fr', es: 'es', tr: 'tr', ar: 'sa',
  zh: 'cn', ja: 'jp', pt: 'pt', it: 'it', pl: 'pl', nl: 'nl',
  sv: 'se', ko: 'kr',
}

function flagUrl(code) {
  const cc = LANG_TO_COUNTRY[code] || code
  return `https://flagcdn.com/w40/${cc}.png`
}
</script>
