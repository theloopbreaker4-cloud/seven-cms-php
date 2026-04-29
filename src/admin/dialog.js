// Custom modal dialogs — replaces native confirm/alert/prompt

let _overlay = null

function getOverlay() {
  if (_overlay) return _overlay
  _overlay = document.createElement('div')
  _overlay.id = 'seven-dialog-overlay'
  document.body.appendChild(_overlay)
  return _overlay
}

function show(html) {
  const overlay = getOverlay()
  overlay.innerHTML = html
  overlay.offsetHeight // force reflow for enter animation
  overlay.classList.add('is-open')
  return overlay
}

function hide(overlay) {
  overlay.classList.remove('is-open')
  overlay.classList.add('is-closing')
  overlay.addEventListener('animationend', () => {
    overlay.classList.remove('is-closing')
    overlay.innerHTML = ''
  }, { once: true })
}

// type: 'danger' | 'warning' | 'info'
// okLabel: text on the confirm button (defaults per type)
export function sevenConfirm(message, { title, type = 'danger', okLabel } = {}) {
  return new Promise(resolve => {
    const t  = title   || 'Confirm'
    const ok = okLabel || (type === 'warning' ? 'Archive' : type === 'info' ? 'OK' : 'Delete')
    const btnClass = type === 'warning' ? 'seven-dialog__btn--warning' : 'seven-dialog__btn--danger'

    const overlay = show(`
      <div class="seven-dialog" role="dialog" aria-modal="true">
        <div class="seven-dialog__icon seven-dialog__icon--${escHtml(type)}">
          ${iconFor(type)}
        </div>
        <h3 class="seven-dialog__title">${escHtml(t)}</h3>
        <p class="seven-dialog__message">${escHtml(message)}</p>
        <div class="seven-dialog__actions">
          <button class="seven-dialog__btn seven-dialog__btn--cancel" data-action="cancel">Cancel</button>
          <button class="seven-dialog__btn ${escHtml(btnClass)}" data-action="ok">${escHtml(ok)}</button>
        </div>
      </div>
    `)

    overlay.addEventListener('click', e => {
      const action = e.target.closest('[data-action]')?.dataset.action
      if (!action && !e.target.closest('.seven-dialog')) {
        hide(overlay); resolve(false); return
      }
      if (!action) return
      hide(overlay)
      resolve(action === 'ok')
    }, { once: true })

    overlay.querySelector('[data-action="ok"]')?.focus()
  })
}

export function sevenAlert(message, title, type = 'info') {
  return new Promise(resolve => {
    const overlay = show(`
      <div class="seven-dialog" role="dialog" aria-modal="true">
        <div class="seven-dialog__icon seven-dialog__icon--${escHtml(type)}">
          ${iconFor(type)}
        </div>
        <h3 class="seven-dialog__title">${escHtml(title || 'Notice')}</h3>
        <p class="seven-dialog__message">${escHtml(message)}</p>
        <div class="seven-dialog__actions">
          <button class="seven-dialog__btn seven-dialog__btn--primary" data-action="ok">OK</button>
        </div>
      </div>
    `)

    overlay.addEventListener('click', e => {
      const action = e.target.closest('[data-action]')?.dataset.action
      if (action === 'ok' || (!action && !e.target.closest('.seven-dialog'))) {
        hide(overlay); resolve()
      }
    }, { once: true })

    overlay.querySelector('[data-action="ok"]')?.focus()
  })
}

function escHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

function iconFor(type) {
  if (type === 'success') return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`
  if (type === 'warning') return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 18 0 9 9 0 0 0-18 0"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>`
  if (type === 'danger')  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>`
  // info
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`
}

// Wire up all [data-confirm] links and submit buttons.
// Optional extra attributes:
//   data-confirm-type="warning|danger|info"  — dialog style
//   data-confirm-ok="Archive"                — ok button label
//   data-confirm-title="..."                 — dialog title
export function initConfirmLinks() {
  document.addEventListener('click', async e => {
    // <a data-confirm>
    const link = e.target.closest('a[data-confirm]')
    if (link) {
      e.preventDefault()
      const ok = await sevenConfirm(link.dataset.confirm || 'Are you sure?', {
        title:   link.dataset.confirmTitle,
        type:    link.dataset.confirmType  || 'danger',
        okLabel: link.dataset.confirmOk,
      })
      if (ok) window.location.href = link.href
      return
    }

    // <button type="submit" data-confirm> inside a <form>
    const btn = e.target.closest('button[type="submit"][data-confirm]')
    if (btn) {
      e.preventDefault()
      const ok = await sevenConfirm(btn.dataset.confirm || 'Are you sure?', {
        title:   btn.dataset.confirmTitle,
        type:    btn.dataset.confirmType  || 'danger',
        okLabel: btn.dataset.confirmOk,
      })
      if (ok) btn.closest('form')?.submit()
    }
  })
}
