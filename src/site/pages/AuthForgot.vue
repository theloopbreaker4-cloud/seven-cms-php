<template>
  <AuthCard :title="t('auth.resetPassword')" :subtitle="t('auth.forgotPassword')">
    <template #default>
      <AlertBox v-if="success" type="success">
        {{ t('auth.sendResetLink') }} ✓
      </AlertBox>
      <AlertBox v-else-if="error" type="error">{{ error }}</AlertBox>

      <form v-if="!success" @submit.prevent="submit" class="space-y-4">
        <FormField :label="t('auth.email')">
          <input v-model="email" type="email" required autocomplete="email" class="auth-input" />
        </FormField>

        <CaptchaField v-if="captchaEnabled" :question="question" v-model="ans" :error="captchaError" />

        <button type="submit" :disabled="loading"
                class="w-full py-2.5 rounded-[4px] bg-[var(--primary)] text-white font-semibold
                       hover:bg-[var(--primary-hover)] transition-colors disabled:opacity-60
                       flex items-center justify-center gap-2">
          <svg v-if="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          {{ loading ? t('auth.submit') + '…' : t('auth.sendResetLink') }}
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
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import AuthCard     from '../components/AuthCard.vue'
import AlertBox     from '../components/AlertBox.vue'
import FormField    from '../components/FormField.vue'
import CaptchaField from '../components/CaptchaField.vue'
import { api }      from '../composables/useApi.js'
import { useCaptcha } from '../composables/useCaptcha.js'
import { t }        from '../composables/useI18n.js'

const lang           = window.__LANG__ || 'en'
const captchaEnabled = (window.__DATA__?.captchaOnForgot ?? true)

const loading = ref(false)
const success = ref(false)
const error   = ref('')
const email   = ref('')

const { question, ans, correct, refresh } = useCaptcha()
const captchaError = ref('')

async function submit() {
  if (captchaEnabled && !correct.value) {
    captchaError.value = t('validation.wrongPass', 'Wrong answer — try again')
    refresh()
    return
  }
  captchaError.value = ''
  loading.value = true
  error.value   = ''
  try {
    await api.auth.forgot(email.value)
    success.value = true
  } catch (e) {
    error.value = e.message || t('auth.sendResetLink', 'Failed to send. Try again.')
    if (captchaEnabled) refresh()
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
