// Custom form validation — replaces native browser tooltips with inline,
// styled error messages. Auto-applies to every <form> in the admin panel.
//
// Usage: just write standard HTML constraints (`required`, `pattern`,
// `min`, `max`, `type="email"`, …). Override messages per-field with
// `data-msg-required`, `data-msg-pattern`, `data-msg-email`, etc.
//
// Forms can opt out with `data-no-validate`.

import { toast } from './ui.js'

const MSG = {
  required:  'This field is required.',
  email:     'Enter a valid email address.',
  url:       'Enter a valid URL.',
  number:    'Enter a number.',
  tel:       'Enter a valid phone number.',
  pattern:   'Value does not match the expected format.',
  min:       'Value is too small.',
  max:       'Value is too large.',
  minlength: 'Value is too short.',
  maxlength: 'Value is too long.',
  step:      'Value does not fit the allowed step.',
  mismatch:  'Value is invalid.',
}

function fieldsOf(form) {
  return Array.from(form.querySelectorAll('input, textarea, select'))
    .filter(el => !el.disabled
                  && el.type !== 'hidden'
                  && el.type !== 'submit'
                  && el.type !== 'button'
                  && el.type !== 'reset'
                  && !el.hasAttribute('data-no-validate'))
}

function messageFor(field) {
  const v = field.validity
  const data = field.dataset

  if (v.valueMissing)    return data.msgRequired  || MSG.required
  if (v.typeMismatch) {
    if (field.type === 'email') return data.msgEmail || MSG.email
    if (field.type === 'url')   return data.msgUrl   || MSG.url
    return data.msgMismatch || MSG.mismatch
  }
  if (v.patternMismatch) return data.msgPattern   || MSG.pattern
  if (v.tooShort)        return data.msgMinlength || MSG.minlength
  if (v.tooLong)         return data.msgMaxlength || MSG.maxlength
  if (v.rangeUnderflow)  return data.msgMin       || MSG.min
  if (v.rangeOverflow)   return data.msgMax       || MSG.max
  if (v.stepMismatch)    return data.msgStep      || MSG.step
  if (v.badInput)        return data.msgMismatch  || MSG.mismatch
  return ''
}

function clearError(field) {
  field.removeAttribute('aria-invalid')
  const id = field.dataset.errorId
  if (id) {
    document.getElementById(id)?.remove()
    delete field.dataset.errorId
  }
}

function showError(field, message) {
  field.setAttribute('aria-invalid', 'true')

  let errEl
  if (field.dataset.errorId) {
    errEl = document.getElementById(field.dataset.errorId)
  }
  if (!errEl) {
    errEl = document.createElement('div')
    errEl.className = 'form-error--inline'
    errEl.id = 'fe-' + Math.random().toString(36).slice(2, 9)
    field.dataset.errorId = errEl.id
    const parent = field.parentElement
    parent.insertBefore(errEl, field.nextSibling)
  }
  errEl.textContent = message
}

function validateField(field) {
  if (field.checkValidity()) {
    clearError(field)
    return true
  }
  showError(field, messageFor(field))
  return false
}

function attachLiveValidation(field) {
  if (field.dataset.liveBound) return
  field.dataset.liveBound = '1'
  const evt = (field.tagName === 'SELECT' || field.type === 'checkbox' || field.type === 'radio')
    ? 'change' : 'blur'
  field.addEventListener(evt, () => {
    if (field.hasAttribute('aria-invalid')) validateField(field)
  })
  field.addEventListener('input', () => {
    if (field.hasAttribute('aria-invalid') && field.checkValidity()) clearError(field)
  })
}

function shake(form) {
  form.classList.remove('form-shake')
  // Reflow so the animation restarts on repeated submits
  void form.offsetWidth
  form.classList.add('form-shake')
}

function clearAllErrors(form) {
  fieldsOf(form).forEach(clearError)
}

// Live "47 / 200" counter for any textarea/input with maxlength.
function attachCounter(field) {
  if (field.dataset.counterBound) return
  const max = parseInt(field.getAttribute('maxlength') || '0', 10)
  if (!max || max <= 0) return
  field.dataset.counterBound = '1'

  const counter = document.createElement('span')
  counter.className = 'form-counter'
  field.parentElement.insertBefore(counter, field.nextSibling)

  const update = () => {
    const len = (field.value || '').length
    counter.textContent = `${len} / ${max}`
    counter.classList.toggle('is-near', len >= max * 0.9)
    counter.classList.toggle('is-full', len >= max)
  }
  field.addEventListener('input', update)
  update()
}

export function initForms(root = document) {
  root.querySelectorAll('form').forEach(form => {
    if (form.hasAttribute('data-no-validate')) return
    if (form.dataset.formBound) return
    form.dataset.formBound = '1'

    form.setAttribute('novalidate', '')

    form.addEventListener('submit', e => {
      const fields = fieldsOf(form)
      let firstBad = null
      let badCount = 0
      fields.forEach(f => {
        const ok = validateField(f)
        if (!ok) { badCount++; if (!firstBad) firstBad = f }
        attachLiveValidation(f)
      })
      if (firstBad) {
        e.preventDefault()
        e.stopPropagation()
        shake(form)
        firstBad.focus({ preventScroll: false })
        firstBad.scrollIntoView({ block: 'center', behavior: 'smooth' })
        toast(
          badCount === 1 ? 'Please fix the highlighted field.' : `Please fix ${badCount} highlighted fields.`,
          'danger',
          3500
        )
      }
    })

    form.addEventListener('reset', () => {
      // Native reset clears values but leaves our error elements in place.
      // Defer to next tick so reset has actually applied.
      setTimeout(() => clearAllErrors(form), 0)
    })

    fieldsOf(form).forEach(f => {
      attachLiveValidation(f)
      if (f.tagName === 'TEXTAREA' || (f.tagName === 'INPUT' && f.hasAttribute('maxlength'))) {
        attachCounter(f)
      }
    })
  })
}
