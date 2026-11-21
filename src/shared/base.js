// Sub-path installs (BASE_PATH=/cms): the server puts the prefix in <meta name="seven-base">.
// withBase('/en/blog') → '/cms/en/blog'; at the domain root BASE is '' and nothing changes.
export const BASE = document.querySelector('meta[name="seven-base"]')?.getAttribute('content') || ''

export function withBase(path) {
  if (!BASE || typeof path !== 'string' || !path.startsWith('/') || path.startsWith('//')) return path
  if (path === BASE || path.startsWith(BASE + '/') || path.startsWith(BASE + '?')) return path
  return BASE + path
}

/** location.pathname without the prefix, for code that reads URL segments. */
export function appPath(pathname = window.location.pathname) {
  return BASE && (pathname === BASE || pathname.startsWith(BASE + '/')) ? pathname.slice(BASE.length) || '/' : pathname
}
