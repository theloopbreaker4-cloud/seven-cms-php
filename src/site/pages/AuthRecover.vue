<template>
  <AuthCard :title="t('auth.resetPassword')" :subtitle="t('auth.recoverPassword')">
    <template #default>
      <AlertBox v-if="error" type="error">{{ error }}</AlertBox>

      <form @submit.prevent="submit" class="space-y-4">
        <FormField :label="t('auth.newPassword')">
          <div class="relative">
            <input v-model="form.password" :type="showPass ? 'text' : 'password'" required
                   autocomplete="new-password" class="auth-input pr-10" />
            <button type="button" @click="showPass = !showPass"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--text-tertiary)] hover:text-[var(--primary)]">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>

          <div class="mt-2 flex gap-1">
            <div v-for="i in 4" :key="i"
                 class="h-1 flex-1 rounded-full transition-colors"
                 :class="strength >= i ? strengthColor : 'bg-[var(--border-color)]'" />
          </div>
          <p class="mt-1 text-xs" :class="strength > 0 ? strengthTextColor : 'text-[var(--text-tertiary)]'">
            {{ strengthLabel }}
          </p>
        </FormField>

        <FormField :label="t('auth.confirmPassword')">
          <input v-model="form.confirmPassword" :type="showPass ? 'text' : 'password'" required
                 autocomplete="new-password" class="auth-input"
                 :class="form.confirmPassword && form.confirmPassword !== form.password ? 'border-red-400' : ''" />
          <p v-if="form.confirmPassword && form.confirmPassword !== form.password"
             class="mt-1 text-xs text-red-400">{{ t('validation.passwordsMismatch') }}</p>
        </FormField>

        <button type="submit" :disabled="loading || form.password !== form.confirmPassword"
                class="w-full py-2.5 rounded-[4px] bg-[var(--primary)] text-white font-semibold
                       hover:bg-[var(--primary-hover)] transition-colors disabled:opacity-60
                       flex items-center justify-center gap-2">
          <svg v-if="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          {{ loading ? t('auth.submit') + '…' : t('auth.resetPassword') }}
        </button>
      </form>
    </template>

    <template #footer>
      <RouterLink :to="`/${lang}/auth`" class="hover:text-[var(--primary)] transition-colors">
        ← {{ t('auth.backToSignin') }}
      </RouterLink>
    </template>
  </AuthCard>
</template>

<script setup>
import { ref, computed } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AuthCard  from '../components/AuthCard.vue'
import AlertBox  from '../components/AlertBox.vue'
import FormField from '../components/FormField.vue'
import { api }   from '../composables/useApi.js'
import { t }     from '../composables/useI18n.js'

const lang    = window.__LANG__ || 'en'
const route   = useRoute()
const router  = useRouter()
const loading = ref(false)
const error   = ref('')
const showPass = ref(false)
const form    = ref({ password: '', confirmPassword: '' })

const strength = computed(() => {
  const p = form.value.password
  if (!p) return 0
  let s = 0
  if (p.length >= 8)          s++
  if (/[A-Z]/.test(p))        s++
  if (/[0-9]/.test(p))        s++
  if (/[^A-Za-z0-9]/.test(p)) s++
  return s
})

const strengthColor = computed(() => {
  if (strength.value <= 1) return 'bg-red-400'
  if (strength.value === 2) return 'bg-yellow-400'
  if (strength.value === 3) return 'bg-blue-400'
  return 'bg-green-400'
})

const strengthTextColor = computed(() => {
  if (strength.value <= 1) return 'text-red-400'
  if (strength.value === 2) return 'text-yellow-400'
  if (strength.value === 3) return 'text-blue-400'
  return 'text-green-400'
})

const strengthLabel = computed(() => {
  if (!form.value.password) return ''
  return ['', 'Weak', 'Fair', 'Good', 'Strong'][strength.value]
})

async function submit() {
  if (form.value.password !== form.value.confirmPassword) {
    error.value = t('validation.passwordsMismatch')
    return
  }
  loading.value = true
  error.value   = ''
  try {
    await api.auth.reset({ token: route.query.token, password: form.value.password })
    router.push(`/${lang}/auth`)
  } catch (e) {
    error.value = e.message || t('auth.resetPassword', 'Reset failed. Try again.')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.auth-input {
  @apply w-full px-3.5 py-2.5 rounded-[4px] border border-[var(--border-color)]
         bg-[var(--bg-primary)] text-[var(--text-primary)] text-sm
         focus:outline-none focus:border-[var(--primary)] transition-colors;
  &:focus { box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 20%, transparent); }
}
</style>
