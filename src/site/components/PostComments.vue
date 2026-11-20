<template>
  <section class="mt-12 pt-8 border-t border-[var(--border-color)]">
    <h2 class="text-xl font-semibold mb-6 text-[var(--text-primary)]">
      {{ tr('title') }} <span v-if="comments.length" class="text-[var(--text-tertiary)] font-normal">({{ comments.length }})</span>
    </h2>

    <ul v-if="comments.length" class="space-y-5 mb-10">
      <li v-for="c in comments" :key="c.id">
        <div class="text-sm font-medium text-[var(--text-primary)]">{{ c.authorName }}</div>
        <div class="text-xs text-[var(--text-tertiary)] mb-1">{{ c.createdAt.slice(0, 16) }}</div>
        <p class="text-[var(--text-secondary)] whitespace-pre-line break-words">{{ c.content }}</p>
      </li>
    </ul>
    <p v-else class="text-sm text-[var(--text-tertiary)] mb-8">{{ tr('empty') }}</p>

    <p v-if="sent" class="px-4 py-3 rounded border border-[var(--border-color)] text-sm text-[var(--text-secondary)]">{{ tr('sent') }}</p>
    <form v-else class="space-y-3" @submit.prevent="submit">
      <div class="grid sm:grid-cols-2 gap-3">
        <input v-model="form.name" required maxlength="80" :placeholder="tr('name')" class="comment-input" />
        <input v-model="form.email" required type="email" :placeholder="tr('email')" class="comment-input" />
      </div>
      <!-- honeypot: hidden from people, bots fill it in -->
      <input v-model="form.website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true" />
      <textarea v-model="form.content" required minlength="2" maxlength="2000" rows="4" :placeholder="tr('comment')" class="comment-input w-full" />
      <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
      <button type="submit" :disabled="sending"
              class="px-5 py-2 rounded bg-[var(--primary)] text-white text-sm disabled:opacity-50">
        {{ sending ? '…' : tr('submit') }}
      </button>
      <p class="text-xs text-[var(--text-tertiary)]">{{ tr('moderated') }}</p>
    </form>
  </section>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { apiFetch } from '../composables/useApi.js'

const props = defineProps({ postId: { type: Number, required: true } })
const lang = window.__LANG__ || 'en'

const STRINGS = {
  en: { title: 'Comments', empty: 'No comments yet — be the first.', name: 'Name', email: 'Email (not published)', comment: 'Your comment', submit: 'Post comment', moderated: 'Comments appear after moderation.', sent: 'Thanks! Your comment will appear after moderation.' },
  ru: { title: 'Комментарии', empty: 'Комментариев пока нет — будьте первым.', name: 'Имя', email: 'Email (не публикуется)', comment: 'Ваш комментарий', submit: 'Отправить', moderated: 'Комментарии появляются после модерации.', sent: 'Спасибо! Комментарий появится после модерации.' },
}
const tr = key => (STRINGS[lang] || STRINGS.en)[key] || STRINGS.en[key]

const comments = ref([])
const form = ref({ name: '', email: '', content: '', website: '' })
const sending = ref(false)
const sent = ref(false)
const error = ref('')

async function load() {
  try { comments.value = await apiFetch(`/posts/${props.postId}/comments`) } catch { comments.value = [] }
}

async function submit() {
  sending.value = true
  error.value = ''
  try {
    await apiFetch(`/posts/${props.postId}/comments`, {
      method: 'POST',
      body: JSON.stringify({ lang, ...form.value }),
    })
    sent.value = true
  } catch (e) {
    error.value = e.status === 429 ? 'Too many comments — try again later.' : (e.message || 'Could not send.')
  } finally {
    sending.value = false
  }
}

onMounted(load)
watch(() => props.postId, load)
</script>

<style scoped>
.comment-input {
  padding: .5rem .75rem;
  border-radius: 4px;
  border: 1px solid var(--border-color);
  background: var(--bg-primary);
  color: var(--text-primary);
  font-size: .875rem;
}
</style>
