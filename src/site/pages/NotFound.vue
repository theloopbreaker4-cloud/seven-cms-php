<template>
  <div class="min-h-[60vh] flex items-center justify-center px-4 text-center">
    <div>
      <h1 class="text-6xl font-bold text-[var(--primary)] mb-4">404</h1>
      <p class="text-xl font-semibold text-[var(--text-primary)] mb-2">{{ t('page.notFound') }}</p>
      <p class="text-[var(--text-secondary)] mb-8">{{ t('page.notFoundDesc') }}</p>
      <RouterLink :to="`/${lang}/`"
        class="px-5 py-2.5 rounded-[4px] bg-[var(--primary)] text-white font-medium hover:bg-[var(--primary-hover)]">
        {{ t('nav.home') }}
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { RouterLink } from 'vue-router'
import { t } from '../composables/useI18n.js'
const lang = window.__LANG__ || 'en'
</script>
