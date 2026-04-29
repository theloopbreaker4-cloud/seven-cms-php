import { ref, computed } from 'vue'

const OPS = ['+', '+', '+', '-', '*']

export function useCaptcha() {
  const a   = ref(0)
  const b   = ref(0)
  const op  = ref('+')
  const ans = ref('')

  function refresh() {
    op.value  = OPS[Math.floor(Math.random() * OPS.length)]
    a.value   = Math.floor(Math.random() * (op.value === '*' ? 9 : 15)) + 1
    b.value   = Math.floor(Math.random() * (op.value === '*' ? 9 : 12)) + 1
    if (op.value === '-' && b.value > a.value) [a.value, b.value] = [b.value, a.value]
    ans.value = ''
  }

  const question = computed(() => `${a.value} ${op.value} ${b.value} = ?`)

  const correct = computed(() => {
    const expected = op.value === '+' ? a.value + b.value
                   : op.value === '-' ? a.value - b.value
                   : a.value * b.value
    return parseInt(ans.value, 10) === expected
  })

  refresh()
  return { question, ans, correct, refresh }
}
