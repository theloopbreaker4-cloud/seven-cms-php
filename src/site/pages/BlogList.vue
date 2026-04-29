<template>
  <div class="max-w-3xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-10 text-[var(--text-primary)]">{{ t('blog.title') }}</h1>

    <div v-if="loading" class="space-y-8">
      <div v-for="i in 3" :key="i" class="animate-pulse border-b border-[var(--border-color)] pb-8">
        <div class="h-6 bg-[var(--bg-tertiary)] rounded w-3/4 mb-3" />
        <div class="h-4 bg-[var(--bg-tertiary)] rounded w-full mb-2" />
        <div class="h-4 bg-[var(--bg-tertiary)] rounded w-2/3" />
      </div>
    </div>

    <div v-else-if="posts.length === 0" class="text-[var(--text-secondary)]">{{ t('blog.noPostsYet') }}</div>

    <div v-else class="space-y-8">
      <article v-for="post in posts" :key="post.id" class="border-b border-[var(--border-color)] pb-8">
        <img v-if="post.cover_image" :src="post.cover_image" :alt="localise(post.title)"
             class="w-full h-48 object-cover rounded-lg mb-4" />
        <h2 class="text-xl font-semibold mb-2">
          <RouterLink :to="`/${lang}/blog/${post.slug}`" class="hover:text-[var(--primary)]">
            {{ localise(post.title) }}
          </RouterLink>
        </h2>
        <p v-if="localise(post.excerpt)" class="text-[var(--text-secondary)] text-sm mb-3">
          {{ localise(post.excerpt) }}
        </p>
        <RouterLink :to="`/${lang}/blog/${post.slug}`" class="text-sm text-[var(--primary)] hover:underline">
          {{ t('blog.readMore') }} →
        </RouterLink>
      </article>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { apiFetch } from '../composables/useApi.js'

const lang  = window.__LANG__ || 'en'
const i18n  = window.__DATA__?.i18n || {}
const posts = ref([])
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

onMounted(async () => {
  try {
    posts.value = await apiFetch('/api/post/index')
  } catch (e) {
    console.error('Failed to load posts', e)
  } finally {
    loading.value = false
  }
})
</script>
