const i18n = window.__DATA__?.i18n || {}

export function t(key, fallback = '') {
  return i18n[key] || i18n[key.split('.').pop()] || fallback || key.split('.').pop()
}
