<template>
  <div class="max-w-3xl mx-auto px-4 py-16">
    <h1 class="text-4xl font-bold mb-4 text-[var(--text-primary)]">{{ t('about.title') }}</h1>
    <p class="text-[var(--text-tertiary)] text-sm mb-10">{{ t('about.version') }} {{ version }}</p>

    <div class="space-y-6 text-[var(--text-secondary)] leading-relaxed">
      <p>{{ t('about.description') }}</p>
      <p>{{ t('about.description2') }}</p>
    </div>

    <div class="mt-12 grid grid-cols-2 gap-4 sm:grid-cols-3">
      <div v-for="item in stack" :key="item.label"
           class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-lg p-4">
        <div class="text-xs text-[var(--text-tertiary)] mb-1">{{ item.label }}</div>
        <div class="font-semibold text-[var(--primary)]">{{ item.value }}</div>
      </div>
    </div>

    <div class="mt-10">
      <RouterLink :to="`/${lang}/`" class="text-sm text-[var(--primary)] hover:underline">
        {{ t('about.backHome') }}
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { RouterLink } from 'vue-router'
import { t } from '../composables/useI18n.js'

const lang    = window.__LANG__ || 'en'
const version = window.__DATA__?.version || '1.0.0'

const stack = [
  { label: 'PHP',        value: '8.4' },
  { label: 'Vue',        value: '3.5' },
  { label: 'Vite',       value: '6'   },
  { label: 'Tailwind',   value: 'v3'  },
  { label: 'RedBeanPHP', value: '5.7' },
  { label: 'Version',    value: version },
]
</script>
