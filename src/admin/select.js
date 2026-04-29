// Custom select dropdown — wraps native <select> to give it a styled,
// theme-aware menu. The native element is kept in the DOM (visually hidden)
// so form submission, validation, and accessibility tools keep working.
//
// Opt out per-element with `data-native-select` on the <select>.

let openInstance = null

function init(select) {
  if (select.dataset.selectBound) return
  if (select.hasAttribute('data-native-select')) return
  if (select.multiple) return // not handling multi-select for now
  if (select.size && select.size > 1) return
  select.dataset.selectBound = '1'

  // Wrapper
  const wrap = document.createElement('div')
  wrap.className = 'seven-select'
  if (select.disabled) wrap.classList.add('is-disabled')
  select.parentNode.insertBefore(wrap, select)
  wrap.appendChild(select)

  // Trigger button (the visible "select" the user sees)
  const trigger = document.createElement('button')
  trigger.type = 'button'
  trigger.className = 'seven-select__trigger'
  trigger.setAttribute('aria-haspopup', 'listbox')
  trigger.setAttribute('aria-expanded', 'false')
  trigger.disabled = select.disabled
  trigger.innerHTML = `
    <span class="seven-select__label"></span>
    <svg class="seven-select__caret" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M5.5 7.5L10 12l4.5-4.5"/>
    </svg>`
  wrap.appendChild(trigger)

  // Menu
  const menu = document.createElement('div')
  menu.className = 'seven-select__menu is-hidden'
  menu.setAttribute('role', 'listbox')
  wrap.appendChild(menu)

  function rebuildOptions() {
    menu.innerHTML = ''
    Array.from(select.options).forEach((opt, i) => {
      const item = document.createElement('div')
      item.className = 'seven-select__option'
      item.setAttribute('role', 'option')
      item.dataset.index = i
      if (opt.disabled) item.classList.add('is-disabled')
      if (opt.selected) item.classList.add('is-selected')
      item.textContent = opt.textContent
      if (opt.value === '') item.classList.add('seven-select__option--placeholder')
      menu.appendChild(item)
    })
  }

  function syncLabel() {
    const opt = select.options[select.selectedIndex]
    const label = trigger.querySelector('.seven-select__label')
    label.textContent = opt ? opt.textContent : ''
    label.classList.toggle('seven-select__label--placeholder', !opt || opt.value === '')
    menu.querySelectorAll('.seven-select__option').forEach((el, i) => {
      el.classList.toggle('is-selected', i === select.selectedIndex)
    })
  }

  function open() {
    if (select.disabled) return
    if (openInstance && openInstance !== close) openInstance()
    menu.classList.remove('is-hidden')
    trigger.setAttribute('aria-expanded', 'true')
    wrap.classList.add('is-open')
    openInstance = close
    document.addEventListener('click', outsideClick, { capture: true })
    document.addEventListener('keydown', keyNav)
    // Bring selected option into view
    const sel = menu.querySelector('.is-selected')
    if (sel) sel.scrollIntoView({ block: 'nearest' })
  }
  function close() {
    menu.classList.add('is-hidden')
    trigger.setAttribute('aria-expanded', 'false')
    wrap.classList.remove('is-open')
    if (openInstance === close) openInstance = null
    document.removeEventListener('click', outsideClick, { capture: true })
    document.removeEventListener('keydown', keyNav)
  }
  function outsideClick(e) {
    if (!wrap.contains(e.target)) close()
  }
  function keyNav(e) {
    if (e.key === 'Escape') { e.preventDefault(); close(); trigger.focus(); return }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault()
      const items = Array.from(menu.querySelectorAll('.seven-select__option:not(.is-disabled)'))
      const cur = items.findIndex(el => el.classList.contains('is-active') || el.classList.contains('is-selected'))
      let next = cur + (e.key === 'ArrowDown' ? 1 : -1)
      if (next < 0) next = items.length - 1
      if (next >= items.length) next = 0
      items.forEach(el => el.classList.remove('is-active'))
      items[next]?.classList.add('is-active')
      items[next]?.scrollIntoView({ block: 'nearest' })
    }
    if (e.key === 'Enter') {
      e.preventDefault()
      const active = menu.querySelector('.is-active') || menu.querySelector('.is-selected')
      if (active) selectIndex(parseInt(active.dataset.index, 10))
    }
  }

  function selectIndex(i) {
    if (select.options[i]?.disabled) return
    select.selectedIndex = i
    select.dispatchEvent(new Event('input', { bubbles: true }))
    select.dispatchEvent(new Event('change', { bubbles: true }))
    syncLabel()
    close()
    trigger.focus()
  }

  trigger.addEventListener('click', e => {
    e.preventDefault()
    if (wrap.classList.contains('is-open')) close(); else open()
  })

  menu.addEventListener('click', e => {
    const opt = e.target.closest('.seven-select__option')
    if (!opt || opt.classList.contains('is-disabled')) return
    selectIndex(parseInt(opt.dataset.index, 10))
  })

  // Mirror programmatic changes
  select.addEventListener('change', syncLabel)

  // Reset support: when the form resets, options get reset by the browser; sync label.
  select.form?.addEventListener('reset', () => setTimeout(syncLabel, 0))

  // If options change later (e.g. dynamic), expose a refresh hook
  select.refreshSelect = () => { rebuildOptions(); syncLabel() }

  rebuildOptions()
  syncLabel()
}

export function initSelects(root = document) {
  root.querySelectorAll('select').forEach(init)
}
