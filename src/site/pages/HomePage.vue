<template>
  <div class="max-w-5xl mx-auto px-4 py-16">
    <template v-if="page">
      <h1 class="text-4xl font-bold mb-6 text-[var(--text-primary)]">{{ localise(page.title) }}</h1>
      <div class="text-[var(--text-secondary)] leading-relaxed" v-html="localise(page.content)" />
    </template>
    <template v-else>
      <h1 class="text-4xl font-bold mb-4">{{ t('home.welcome', 'Welcome to Seven CMS') }}</h1>
      <p class="text-[var(--text-secondary)] text-lg">{{ t('home.tagline', 'A modern, multilingual content management system.') }}</p>
      <div class="mt-8 flex gap-4">
        <RouterLink :to="`/${lang}/blog`"
          class="px-5 py-2.5 rounded-[4px] bg-[var(--primary)] text-white font-medium hover:bg-[var(--primary-hover)]">
          {{ t('nav.blog') }}
        </RouterLink>
        <RouterLink v-if="!isLogin" :to="`/${lang}/auth`"
          class="px-5 py-2.5 rounded-[4px] border border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--primary)]">
          {{ t('nav.signin') }}
        </RouterLink>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

const data    = window.__DATA__ || {}
const lang    = window.__LANG__ || 'en'
const i18n    = data.i18n       || {}
const isLogin = data.isLogin    || false
const page    = computed(() => data.homePage ?? null)

function t(key, fallback = '') {
  return i18n[key] || i18n[key.split('.').pop()] || fallback || key.split('.').pop()
}

function localise(json) {
  try {
    const obj = typeof json === 'string' ? JSON.parse(json) : json
    return obj?.[lang] || obj?.en || ''
  } catch { return '' }
}
</script>
