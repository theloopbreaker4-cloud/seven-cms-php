<template>
  <div class="min-h-[88vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">

      <!-- Logo + title -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-[5px] bg-[var(--bg-secondary)] border border-[var(--border-color)] shadow-sm mb-4">
          <img :src="`${base}brand.svg`" alt="Seven CMS" class="w-8 h-8 rounded-[4px]" />
        </div>
        <h1 class="text-2xl font-bold text-[var(--text-primary)]">{{ title }}</h1>
        <p v-if="subtitle" class="mt-1.5 text-sm text-[var(--text-tertiary)]">{{ subtitle }}</p>
      </div>

      <!-- Card -->
      <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-[5px] shadow-lg p-7">
        <slot />
      </div>

      <!-- Footer links -->
      <div v-if="$slots.footer" class="mt-5 text-center text-sm text-[var(--text-tertiary)] space-y-1.5">
        <slot name="footer" />
      </div>

    </div>
  </div>
</template>

<script setup>
const base = import.meta.env.BASE_URL

defineProps({
  title:    { type: String, required: true },
  subtitle: { type: String, default: '' },
})
</script>
