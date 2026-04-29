<template>
  <AuthCard :title="t('auth.signup')">
    <template #default>
      <AlertBox v-if="generalError" type="error">{{ generalError }}</AlertBox>

      <form @submit.prevent="submit" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <FormField :label="t('auth.firstName')" :error="errors.firstName?.[0]">
            <input v-model="form.firstName" type="text" required class="auth-input" :class="err('firstName')" />
          </FormField>
          <FormField :label="t('auth.lastName')" :error="errors.lastName?.[0]">
            <input v-model="form.lastName" type="text" required class="auth-input" :class="err('lastName')" />
          </FormField>
        </div>

        <FormField :label="t('auth.userName')" :error="errors.userName?.[0]">
          <input v-model="form.userName" type="text" required autocomplete="username"
                 class="auth-input" :class="err('userName')" />
        </FormField>

        <FormField :label="t('auth.email')" :error="errors.email?.[0]">
          <input v-model="form.email" type="email" required autocomplete="email"
                 class="auth-input" :class="err('email')" />
        </FormField>

        <FormField :label="t('auth.password')" :error="errors.password?.[0]">
          <div class="relative">
            <input v-model="form.password" :type="showPass ? 'text' : 'password'" required
                   autocomplete="new-password" class="auth-input pr-10" :class="err('password')" />
            <button type="button" @click="showPass = !showPass"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--text-tertiary)] hover:text-[var(--primary)]">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path v-if="showPass" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
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
          {{ loading ? t('auth.submit') + '…' : t('auth.signup') }}
        </button>
      </form>
    </template>

    <template #footer>
      <p>{{ t('auth.hasAccount') }} <RouterLink :to="`/${lang}/auth`" class="text-[var(--primary)] hover:underline font-medium">{{ t('auth.signin') }}</RouterLink></p>
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

const lang = window.__LANG__ || 'en'
const captchaEnabled = (window.__DATA__?.captchaOnRegister ?? true)

const loading      = ref(false)
const generalError = ref('')
const errors       = ref({})
const showPass     = ref(false)
const form         = ref({ firstName: '', lastName: '', userName: '', email: '', password: '' })

const { question, ans, correct, refresh } = useCaptcha()
const captchaError = ref('')

const err = (f) => errors.value[f] ? 'border-red-400 focus:border-red-400' : ''

async function submit() {
  if (captchaEnabled && !correct.value) {
    captchaError.value = 'Wrong answer — try again'
    refresh()
    return
  }
  captchaError.value = ''
  loading.value      = true
  generalError.value = ''
  errors.value       = {}
  try {
    const data = await api.auth.register(form.value)
    if (data.token) document.cookie = `token=${data.token}; path=/; SameSite=Strict`
    window.location.href = `/${lang}/`
  } catch (e) {
    if (e.data && typeof e.data === 'object' && !e.data.error) {
      errors.value = e.data
    } else {
      generalError.value = e.message || 'Registration failed'
    }
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
