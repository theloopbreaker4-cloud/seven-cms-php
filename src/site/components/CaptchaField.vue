<template>
  <div class="rounded-[4px] border border-[var(--border-color)] bg-[var(--bg-primary)] p-3.5 flex items-center gap-3">
    <div class="flex-1">
      <p class="text-xs font-semibold text-[var(--text-tertiary)] uppercase tracking-wider mb-1.5">Security check</p>
      <p class="text-base font-mono font-bold text-[var(--primary)] select-none">{{ question }}</p>
    </div>
    <div class="w-20">
      <input :value="modelValue" @input="$emit('update:modelValue', $event.target.value)"
             type="number" inputmode="numeric" placeholder="?"
             class="w-full px-2.5 py-2 text-center rounded-[4px] border text-sm font-bold
                    border-[var(--border-color)] bg-[var(--bg-secondary)] text-[var(--text-primary)]
                    focus:outline-none focus:border-[var(--primary)] focus:ring-2 focus:ring-[var(--primary)]/20" />
    </div>
  </div>
  <p v-if="error" class="text-xs text-red-400 -mt-1">{{ error }}</p>
</template>

<script setup>
defineProps({
  question:   { type: String, required: true },
  modelValue: { type: String, default: '' },
  error:      { type: String, default: '' },
})
defineEmits(['update:modelValue'])
</script>
