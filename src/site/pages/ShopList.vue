<template>
  <div class="max-w-5xl mx-auto px-4 py-12">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
      <div>
        <h1 class="text-3xl font-bold text-[var(--text-primary)]">{{ t('shop') }}</h1>
        <p class="text-[var(--text-secondary)] mt-1">{{ t('subtitle') }}</p>
      </div>
      <RouterLink :to="`/${lang}/shop/cart`" class="text-sm px-4 py-2 rounded-lg border border-[var(--border-color)] hover:border-[var(--primary)] transition-colors">
        {{ t('cart') }}<span v-if="cartCount"> ({{ cartCount }})</span>
      </RouterLink>
    </div>

    <div v-if="categories.length" class="flex flex-wrap gap-2 mb-8">
      <button v-for="c in [{ id: null, name: t('all') }, ...categories]" :key="c.id ?? 'all'" type="button"
              class="text-sm px-3 py-1.5 rounded-full border transition-colors"
              :class="category === c.id ? 'border-[var(--primary)] text-[var(--primary)]' : 'border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)]'"
              @click="select(c.id)">
        {{ c.id === null ? c.name : localise(c.name) }}
      </button>
    </div>

    <div v-if="loading" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <div v-for="i in 3" :key="i" class="animate-pulse h-56 rounded-xl bg-[var(--bg-tertiary)]" />
    </div>
    <p v-else-if="!products.length" class="text-[var(--text-secondary)]">{{ t('empty') }}</p>
    <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <RouterLink v-for="p in products" :key="p.id" :to="`/${lang}/shop/product/${p.slug}`"
                  class="group flex flex-col rounded-xl border border-[var(--border-color)] bg-[var(--bg-secondary)] overflow-hidden hover:border-[var(--primary)] transition-colors">
        <div class="aspect-[4/3] bg-[var(--bg-tertiary)] flex items-center justify-center text-5xl">
          <img v-if="p.images?.length" :src="p.images[0]" :alt="localise(p.name)" class="w-full h-full object-cover" />
          <span v-else aria-hidden="true">{{ icon(p) }}</span>
        </div>
        <div class="p-4 flex flex-col flex-1">
          <div class="flex items-center gap-2 mb-1">
            <h2 class="font-semibold group-hover:text-[var(--primary)]">{{ localise(p.name) }}</h2>
            <span v-if="p.kind !== 'physical'" class="text-[10px] uppercase tracking-wider text-[var(--text-tertiary)]">{{ kindLabel(p) }}</span>
          </div>
          <p class="text-sm text-[var(--text-secondary)] mb-3 flex-1">{{ localise(p.shortDescription) }}</p>
          <div class="flex items-baseline gap-2">
            <span class="font-semibold">{{ money(p.basePrice, currency) }}</span>
            <span v-if="p.compareAtPrice" class="text-sm line-through text-[var(--text-tertiary)]">{{ money(p.compareAtPrice, currency) }}</span>
            <span v-if="p.isSubscription" class="text-xs text-[var(--text-tertiary)]">{{ period(p) }}</span>
          </div>
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { shop, lang, localise, money, t } from '../composables/useShop.js'

const products = ref([])
const categories = ref([])
const category = ref(null)
const loading = ref(true)
const currency = ref('USD')
const cartCount = ref(0)

const icon = (p) => (p.kind === 'digital' ? '⬇️' : p.kind === 'service' ? '🛠️' : '📦')
const kindLabel = (p) => (p.isSubscription ? t('subscription') : t(p.kind))
const period = (p) => t('per' + (p.billingPeriod || 'month').replace(/^./, (c) => c.toUpperCase()))

async function load() {
  loading.value = true
  try {
    const data = await shop.products(category.value ? { category: category.value } : {})
    products.value = data.items || []
  } finally {
    loading.value = false
  }
}

function select(id) {
  category.value = id
  load()
}

onMounted(async () => {
  const [cats, cart] = await Promise.all([shop.categories().catch(() => ({})), shop.cart().catch(() => null)])
  categories.value = cats.items || []
  if (cart) {
    currency.value = cart.currency || 'USD'
    cartCount.value = (cart.items || []).reduce((n, i) => n + Number(i.quantity || 0), 0)
  }
  await load()
})
</script>
