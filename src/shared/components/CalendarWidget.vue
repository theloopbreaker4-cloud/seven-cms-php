<template>
  <div class="bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg p-4 select-none">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <button @click="prev" class="p-1 hover:text-[var(--primary)] transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <span class="font-medium text-sm">{{ monthLabel }}</span>
      <button @click="next" class="p-1 hover:text-[var(--primary)] transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </button>
    </div>

    <!-- Day labels -->
    <div class="grid grid-cols-7 mb-1">
      <div v-for="d in dayNames" :key="d" class="text-center text-xs text-[var(--text-tertiary)] py-1">{{ d }}</div>
    </div>

    <!-- Days grid -->
    <div class="grid grid-cols-7 gap-px">
      <div v-for="(day, i) in calDays" :key="i"
           class="aspect-square flex flex-col items-center justify-start pt-1 rounded cursor-pointer text-xs transition-colors relative"
           :class="dayClass(day)"
           @click="day.date && selectDay(day.date)">
        <span v-if="day.date">{{ day.date.getDate() }}</span>
        <span v-if="day.date && hasEvents(day.date)"
              class="mt-0.5 w-1 h-1 rounded-full bg-[var(--primary)]"></span>
      </div>
    </div>

    <!-- Selected day events -->
    <div v-if="selected && selectedEvents.length" class="mt-4 border-t border-[var(--border-color)] pt-3">
      <div class="text-xs font-medium text-[var(--text-secondary)] mb-2">{{ selectedLabel }}</div>
      <ul class="space-y-1.5">
        <li v-for="ev in selectedEvents" :key="ev.id"
            class="flex items-start gap-2 text-xs">
          <span class="mt-0.5 w-2 h-2 rounded-full flex-shrink-0"
                :style="{ background: ev.color || 'var(--primary)' }"></span>
          <span>{{ ev.title }}</span>
        </li>
      </ul>
    </div>

    <div v-else-if="selected" class="mt-4 border-t border-[var(--border-color)] pt-3 text-xs text-[var(--text-tertiary)]">
      No events on {{ selectedLabel }}.
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  events: { type: Array, default: () => [] },
  // events format: [{ id, date: 'YYYY-MM-DD', title, color? }]
})

const today    = new Date()
const current  = ref(new Date(today.getFullYear(), today.getMonth(), 1))
const selected = ref(null)

const dayNames = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']

const monthLabel = computed(() => {
  return current.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
})

const calDays = computed(() => {
  const year  = current.value.getFullYear()
  const month = current.value.getMonth()
  const first = new Date(year, month, 1)
  const last  = new Date(year, month + 1, 0)

  // Monday-first offset
  const offset = (first.getDay() + 6) % 7
  const total  = offset + last.getDate()
  const cells  = Math.ceil(total / 7) * 7

  return Array.from({ length: cells }, (_, i) => {
    const dayNum = i - offset + 1
    return dayNum >= 1 && dayNum <= last.getDate()
      ? { date: new Date(year, month, dayNum) }
      : { date: null }
  })
})

const toKey = (d) => d && `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`

const hasEvents = (d) => props.events.some(e => e.date === toKey(d))

const selectedEvents = computed(() =>
  selected.value ? props.events.filter(e => e.date === toKey(selected.value)) : []
)

const selectedLabel = computed(() =>
  selected.value ? selected.value.toLocaleDateString(undefined, { weekday:'short', month:'short', day:'numeric' }) : ''
)

const dayClass = (day) => {
  if (!day.date) return 'cursor-default'
  const key = toKey(day.date)
  const isToday    = key === toKey(today)
  const isSel      = selected.value && key === toKey(selected.value)
  const isWeekend  = day.date.getDay() === 0 || day.date.getDay() === 6

  if (isSel)   return 'bg-[var(--primary)] text-white hover:bg-[var(--primary-hover)]'
  if (isToday) return 'ring-1 ring-[var(--primary)] text-[var(--primary)] font-semibold hover:bg-[var(--bg-tertiary)]'
  return (isWeekend ? 'text-[var(--text-tertiary)] ' : '') + 'hover:bg-[var(--bg-tertiary)]'
}

const selectDay  = (d) => { selected.value = (selected.value && toKey(selected.value) === toKey(d)) ? null : d }
const prev = () => { current.value = new Date(current.value.getFullYear(), current.value.getMonth() - 1, 1) }
const next = () => { current.value = new Date(current.value.getFullYear(), current.value.getMonth() + 1, 1) }
</script>
