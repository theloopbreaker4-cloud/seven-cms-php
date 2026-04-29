// SevenCMS UI helpers — Toast, Dropdown, Spinner overlay
// Import in main.js: import { toast, SevenUI } from './ui.js'

// ─── Toast ────────────────────────────────────────────────────────────────────
function getContainer() {
  let c = document.getElementById('seven-toast-container')
  if (!c) {
    c = document.createElement('div')
    c.id = 'seven-toast-container'
    document.body.appendChild(c)
  }
  return c
}

const ICONS = {
  success: `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 8l3 3 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
  danger:  `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4l8 8M12 4l-8 8" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
  warning: `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 6v4M8 11.5v.5" stroke-linecap="round"/><path d="M8 2L1 14h14L8 2z" stroke-linejoin="round"/></svg>`,
  info:    `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6"/><path d="M8 7v4M8 5.5v.5" stroke-linecap="round"/></svg>`,
}

export function toast(message, type = 'info', duration = 4000) {
  const container = getContainer()
  const el = document.createElement('div')
  el.className = `seven-toast seven-toast--${type}`
  el.innerHTML = `
    <span class="seven-toast__icon">${ICONS[type] ?? ICONS.info}</span>
    <span class="seven-toast__msg">${message}</span>
    <button class="seven-toast__close" aria-label="Close">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 2l10 10M12 2L2 12" stroke-linecap="round"/></svg>
    </button>`

  container.prepend(el)

  const dismiss = () => {
    el.classList.add('is-leaving')
    el.addEventListener('animationend', () => el.remove(), { once: true })
  }
  el.querySelector('.seven-toast__close').addEventListener('click', dismiss)
  if (duration > 0) setTimeout(dismiss, duration)
  return dismiss
}

// ─── Spinner overlay ──────────────────────────────────────────────────────────
let overlayEl = null

export const overlay = {
  show() {
    if (!overlayEl) {
      overlayEl = document.createElement('div')
      overlayEl.className = 'loading-overlay'
      overlayEl.innerHTML = '<div class="spinner spinner--lg spinner--white"></div>'
      document.body.appendChild(overlayEl)
    }
    overlayEl.classList.remove('hidden')
  },
  hide() {
    overlayEl?.classList.add('hidden')
  }
}

// ─── Dropdown ────────────────────────────────────────────────────────────────
function initDropdowns() {
  document.addEventListener('click', e => {
    const trigger = e.target.closest('[data-dropdown]')
    if (trigger) {
      e.stopPropagation()
      const menuId = trigger.dataset.dropdown
      const menu = document.getElementById(menuId)
      if (!menu) return
      const isOpen = !menu.classList.contains('is-hidden')
      document.querySelectorAll('.seven-dropdown__menu').forEach(m => m.classList.add('is-hidden'))
      if (!isOpen) menu.classList.remove('is-hidden')
      return
    }
    document.querySelectorAll('.seven-dropdown__menu').forEach(m => m.classList.add('is-hidden'))
  })
}

// ─── Init ─────────────────────────────────────────────────────────────────────
export function initUI() {
  initDropdowns()
}

export const SevenUI = { toast, overlay, initUI }
export default SevenUI
