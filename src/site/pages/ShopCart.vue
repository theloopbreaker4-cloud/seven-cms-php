<template>
  <div class="max-w-4xl mx-auto px-4 py-12">
    <RouterLink :to="`/${lang}/shop`" class="text-sm text-[var(--text-tertiary)] hover:text-[var(--primary)]">← {{ t('back') }}</RouterLink>
    <h1 class="text-3xl font-bold mt-4 mb-8">{{ t('cart') }}</h1>

    <div v-if="order" class="rounded-xl border border-[var(--primary)] p-6">
      <p class="text-lg font-semibold">{{ t('ordered') }} {{ order.number }}</p>
      <p class="text-sm text-[var(--text-secondary)] mt-1">{{ money(order.total, order.currency) }} · {{ t('orderNote') }}</p>
    </div>

    <div v-else-if="loading" class="animate-pulse h-40 rounded-xl bg-[var(--bg-tertiary)]" />
    <p v-else-if="!items.length" class="text-[var(--text-secondary)]">{{ t('cartEmpty') }}</p>

    <div v-else class="grid md:grid-cols-[1fr_320px] gap-8">
      <div class="space-y-3">
        <div v-for="i in items" :key="i.id" class="flex items-center gap-4 rounded-xl border border-[var(--border-color)] p-4">
          <div class="flex-1">
            <p class="font-medium">{{ localise(i.product_name) }}</p>
            <p class="text-sm text-[var(--text-tertiary)]">{{ money(i.unit_price, currency) }}</p>
          </div>
          <label class="text-sm text-[var(--text-tertiary)]">
            {{ t('quantity') }}
            <input type="number" min="1" max="99" :value="i.quantity" class="w-16 ml-1 px-2 py-1 rounded border border-[var(--border-color)] bg-[var(--bg-primary)]"
                   @change="setQty(i, $event.target.value)" />
          </label>
          <button type="button" class="text-sm text-red-500 hover:underline" @click="remove(i)">{{ t('remove') }}</button>
        </div>
      </div>

      <aside class="space-y-4">
        <div class="rounded-xl border border-[var(--border-color)] p-4 text-sm space-y-1.5">
          <div class="flex justify-between"><span>{{ t('subtotal') }}</span><span>{{ money(totals.subtotal, currency) }}</span></div>
          <div v-if="totals.discount" class="flex justify-between"><span>{{ t('discount') }}</span><span>−{{ money(totals.discount, currency) }}</span></div>
          <div class="flex justify-between"><span>{{ t('shipping') }}</span><span>{{ money(totals.shipping, currency) }}</span></div>
          <div class="flex justify-between"><span>{{ t('tax') }}</span><span>{{ money(totals.tax, currency) }}</span></div>
          <div class="flex justify-between font-semibold pt-2 border-t border-[var(--border-color)]"><span>{{ t('total') }}</span><span>{{ money(totals.total, currency) }}</span></div>
          <form class="flex gap-2 pt-3" @submit.prevent="applyCode">
            <input v-model="code" :placeholder="t('discountCode')" class="flex-1 min-w-0 px-2 py-1.5 rounded border border-[var(--border-color)] bg-[var(--bg-primary)]" />
            <button class="px-3 rounded border border-[var(--border-color)] hover:border-[var(--primary)]">{{ t('apply') }}</button>
          </form>
        </div>

        <form class="rounded-xl border border-[var(--border-color)] p-4 space-y-2 text-sm" @submit.prevent="checkout">
          <p class="font-semibold mb-1">{{ t('checkout') }}</p>
          <input v-model="form.email" type="email" required :placeholder="t('email')" class="w-full px-2 py-1.5 rounded border border-[var(--border-color)] bg-[var(--bg-primary)]" />
          <div class="flex gap-2">
            <input v-model="form.first_name" required :placeholder="t('firstName')" class="w-1/2 px-2 py-1.5 rounded border border-[var(--border-color)] bg-[var(--bg-primary)]" />
            <input v-model="form.last_name" required :placeholder="t('lastName')" class="w-1/2 px-2 py-1.5 rounded border border-[var(--border-color)] bg-[var(--bg-primary)]" />
          </div>
          <template v-if="needsShipping">
            <input v-model="form.line1" required :placeholder="t('address')" class="w-full px-2 py-1.5 rounded border border-[var(--border-color)] bg-[var(--bg-primary)]" />
            <div class="flex gap-2">
              <input v-model="form.city" required :placeholder="t('city')" class="w-1/2 px-2 py-1.5 rounded border border-[var(--border-color)] bg-[var(--bg-primary)]" />
              <input v-model="form.zip" :placeholder="t('zip')" class="w-1/4 px-2 py-1.5 rounded border border-[var(--border-color)] bg-[var(--bg-primary)]" />
              <input v-model="form.country" required maxlength="2" :placeholder="t('country')" class="w-1/4 px-2 py-1.5 rounded border border-[var(--border-color)] bg-[var(--bg-primary)] uppercase" />
            </div>
          </template>
          <button :disabled="busy" class="w-full mt-2 px-4 py-2.5 rounded-lg bg-[var(--primary)] text-white hover:bg-[var(--primary-hover)] font-medium disabled:opacity-50">
            {{ t('placeOrder') }}
          </button>
          <p class="text-xs text-[var(--text-tertiary)]">{{ t('orderNote') }}</p>
        </form>
        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { shop, lang, localise, money, t } from '../composables/useShop.js'

const items = ref([])
const totals = ref({})
const currency = ref('USD')
const loading = ref(true)
const busy = ref(false)
const error = ref('')
const code = ref('')
const order = ref(null)
const form = ref({ email: '', first_name: '', last_name: '', line1: '', city: '', zip: '', country: 'GE' })

const needsShipping = computed(() => items.value.some((i) => i.kind === 'physical'))

function apply(cart) {
  items.value = cart?.items || []
  totals.value = cart?.totals || {}
  currency.value = cart?.currency || 'USD'
}

async function run(fn) {
  busy.value = true
  error.value = ''
  try {
    apply(await fn())
  } catch (e) {
    error.value = e.data?.error || e.message
  } finally {
    busy.value = false
  }
}

const setQty = (i, q) => run(() => shop.update(i.id, Math.max(1, Math.min(99, Number(q) || 1))))
const remove = (i) => run(() => shop.remove(i.id))
const applyCode = () => code.value && run(() => shop.discount(code.value))

async function checkout() {
  busy.value = true
  error.value = ''
  const f = form.value
  try {
    const res = await shop.checkout({
      customer: { email: f.email, first_name: f.first_name, last_name: f.last_name },
      gateway: 'manual',
      ...(needsShipping.value ? { shipping_address: { line1: f.line1, city: f.city, zip: f.zip, country: f.country.toUpperCase() } } : {}),
    })
    order.value = res.order || res
  } catch (e) {
    error.value = e.data?.error || e.message
  } finally {
    busy.value = false
  }
}

onMounted(async () => {
  try {
    apply(await shop.cart())
  } finally {
    loading.value = false
  }
})
</script>
