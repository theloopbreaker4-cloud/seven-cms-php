<template>
  <div class="max-w-3xl mx-auto px-4 py-12">
    <div v-if="loading" class="animate-pulse space-y-4">
      <div class="h-8 bg-[var(--bg-tertiary)] rounded w-1/2" />
      <div class="h-4 bg-[var(--bg-tertiary)] rounded w-full" />
    </div>
    <div v-else-if="!page" class="text-[var(--text-secondary)]">Page not found.</div>
    <template v-else>
      <h1 class="text-3xl font-bold mb-8 text-[var(--text-primary)]">{{ title }}</h1>
      <div class="text-[var(--text-secondary)] leading-relaxed" v-html="content" />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { apiFetch } from '../composables/useApi.js'

const lang    = window.__LANG__ || 'en'
const route   = useRoute()
const page    = ref(null)
const loading = ref(true)

function localise(json) {
  try {
    const obj = typeof json === 'string' ? JSON.parse(json) : json
    return obj?.[lang] || obj?.en || ''
  } catch { return '' }
}

const title   = computed(() => localise(page.value?.title))
const content = computed(() => localise(page.value?.content))

async function load(slug) {
  loading.value = true
  try {
    const list = await apiFetch('/api/page/index')
    page.value = list.find(p => (p.slugs?.[lang] || p.slug) === slug) || null
  } catch (e) {
    console.error('Failed to load page', e)
  } finally {
    loading.value = false
  }
}

onMounted(() => load(route.params.slug))
watch(() => route.params.slug, slug => load(slug))
</script>
