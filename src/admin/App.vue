<template>
  <div class="seven-admin-app">
    <slot />
  </div>
</template>

<script setup>
defineProps({
  data: {
    type: Object,
    default: () => ({}),
  },
})
</script>
