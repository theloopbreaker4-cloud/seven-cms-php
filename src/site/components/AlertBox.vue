<template>
  <div :class="classes" class="flex items-start gap-2.5 px-4 py-3 rounded-xl text-sm border">
    <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path v-if="type==='error'"   stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      <path v-else-if="type==='success'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
      <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <span><slot /></span>
  </div>
</template>

<script setup>
import { computed } from 'vue'
const props = defineProps({ type: { type: String, default: 'error' } })
const classes = computed(() => ({
  'bg-red-500/10 border-red-500/30 text-red-400':     props.type === 'error',
  'bg-green-500/10 border-green-500/30 text-green-400': props.type === 'success',
  'bg-blue-500/10 border-blue-500/30 text-blue-400':  props.type === 'info',
}))
</script>
