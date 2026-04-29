<template>
  <div class="max-w-3xl mx-auto px-4 py-12">
    <RouterLink :to="`/${lang}/blog`" class="text-sm text-[var(--text-tertiary)] hover:text-[var(--primary)] mb-8 inline-block">
      ← {{ t('blog.backToList') }}
    </RouterLink>

    <div v-if="loading" class="animate-pulse space-y-4 mt-4">
      <div class="h-8 bg-[var(--bg-tertiary)] rounded w-3/4" />
      <div class="h-4 bg-[var(--bg-tertiary)] rounded w-full" />
      <div class="h-4 bg-[var(--bg-tertiary)] rounded w-5/6" />
    </div>

    <div v-else-if="!post" class="text-[var(--text-secondary)] mt-8">{{ t('page.notFound') }}</div>

    <template v-else>
      <img v-if="post.coverImage" :src="post.coverImage" :alt="title"
           class="w-full h-64 object-cover rounded-[4px] mb-8" />
      <h1 class="text-3xl font-bold mb-4 text-[var(--text-primary)]">{{ title }}</h1>
      <p v-if="excerpt" class="text-lg text-[var(--text-secondary)] mb-8 italic">{{ excerpt }}</p>
      <div class="text-[var(--text-secondary)] leading-relaxed" v-html="content" />
      <div class="mt-12 pt-6 border-t border-[var(--border-color)] text-xs text-[var(--text-tertiary)]">
        {{ post.createdAt }}
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { apiFetch } from '../composables/useApi.js'

const lang  = window.__LANG__ || 'en'
const i18n  = window.__DATA__?.i18n || {}
const route = useRoute()
const post  = ref(null)
const loading = ref(true)

function t(key) {
  return i18n[key] || i18n[key.split('.').pop()] || key.split('.').pop()
}

function localise(json) {
  try {
    const obj = typeof json === 'string' ? JSON.parse(json) : json
    return obj?.[lang] || obj?.en || ''
  } catch { return '' }
}

const title   = computed(() => localise(post.value?.title))
const excerpt = computed(() => localise(post.value?.excerpt))
const content = computed(() => localise(post.value?.content))

async function load(slug) {
  loading.value = true
  try {
    const list = await apiFetch('/api/post/index')
    post.value = list.find(p => (p.slugs?.[lang] || p.slug) === slug) || null
  } catch (e) {
    console.error('Failed to load post', e)
  } finally {
    loading.value = false
  }
}

onMounted(() => load(route.params.slug))
watch(() => route.params.slug, slug => load(slug))
</script>
