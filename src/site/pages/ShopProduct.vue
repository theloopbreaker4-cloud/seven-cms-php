<template>
  <div class="max-w-5xl mx-auto px-4 py-12">
    <RouterLink :to="`/${lang}/shop`" class="text-sm text-[var(--text-tertiary)] hover:text-[var(--primary)]">← {{ t('back') }}</RouterLink>

    <div v-if="loading" class="mt-6 animate-pulse h-72 rounded-xl bg-[var(--bg-tertiary)]" />
    <p v-else-if="!product" class="mt-6 text-[var(--text-secondary)]">{{ t('notFound') }}</p>

    <div v-else class="mt-6 grid md:grid-cols-2 gap-10">
      <div class="aspect-square rounded-xl bg-[var(--bg-tertiary)] flex items-center justify-center text-7xl overflow-hidden">
        <img v-if="product.images?.length" :src="product.images[0]" :alt="localise(product.name)" class="w-full h-full object-cover" />
        <span v-else aria-hidden="true">{{ product.kind === 'digital' ? '⬇️' : product.kind === 'service' ? '🛠️' : '📦' }}</span>
      </div>

      <div>
        <h1 class="text-3xl font-bold">{{ localise(product.name) }}</h1>
        <p v-if="product.kind !== 'physical'" class="text-xs uppercase tracking-wider text-[var(--text-tertiary)] mt-1">
          {{ product.isSubscription ? t('subscription') : t(product.kind) }}
        </p>

        <div class="flex items-baseline gap-3 mt-4">
          <span class="text-2xl font-semibold">{{ money(product.basePrice, currency) }}</span>
          <span v-if="product.compareAtPrice" class="line-through text-[var(--text-tertiary)]">{{ money(product.compareAtPrice, currency) }}</span>
          <span v-if="product.isSubscription" class="text-sm text-[var(--text-tertiary)]">{{ t('perMonth') }}</span>
        </div>
        <p v-if="product.trackInventory" class="text-sm mt-1" :class="product.stock > 0 ? 'text-[var(--text-secondary)]' : 'text-red-500'">
          {{ product.stock > 0 ? `${product.stock} ${t('inStock')}` : t('outOfStock') }}
        </p>

        <!-- Sanitized server-side (HtmlSanitizer) before it is stored. -->
        <div class="mt-6 text-[var(--text-secondary)] leading-relaxed" v-html="localise(product.description)" />

        <div class="mt-8 flex flex-wrap items-center gap-3">
          <button type="button" :disabled="busy || (product.trackInventory && product.stock <= 0)"
                  class="px-6 py-3 rounded-lg bg-[var(--primary)] text-white hover:bg-[var(--primary-hover)] font-medium disabled:opacity-50"
                  @click="add">
            {{ t('addToCart') }}
          </button>
          <RouterLink v-if="added" :to="`/${lang}/shop/cart`" class="text-sm text-[var(--primary)] hover:underline">
            {{ t('added') }} — {{ t('viewCart') }} →
          </RouterLink>
          <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { shop, lang, localise, money, t } from '../composables/useShop.js'

const route = useRoute()
const product = ref(null)
const loading = ref(true)
const busy = ref(false)
const added = ref(false)
const error = ref('')
const currency = ref('USD')

async function add() {
  busy.value = true
  error.value = ''
  try {
    await shop.add(product.value.id, 1)
    added.value = true
  } catch (e) {
    error.value = e.data?.error || e.message
  } finally {
    busy.value = false
  }
}

onMounted(async () => {
  try {
    const [p, cart] = await Promise.all([shop.product(route.params.slug), shop.cart().catch(() => null)])
    product.value = p
    if (cart?.currency) currency.value = cart.currency
    if (p) document.title = `${localise(p.name)} — ${document.title.split(' — ').pop()}`
  } catch {
    product.value = null
  } finally {
    loading.value = false
  }
})
</script>
