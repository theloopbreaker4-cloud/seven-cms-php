<template>
  <div>
    <label class="block text-xs font-semibold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">
      {{ label }}
    </label>
    <slot />
    <div v-if="error" class="form-error--inline">{{ error }}</div>
  </div>
</template>

<script setup>
defineProps({
  label: { type: String, required: true },
  error: { type: String, default: '' },
})
</script>
