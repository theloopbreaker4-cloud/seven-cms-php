// Fetch wrapper for the site API (/api/v1). The session lives in an httpOnly cookie
// set by the server, so nothing here reads or writes tokens.
const BASE = '/api/v1'

export async function apiFetch(path, options = {}) {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...options.headers,
  }
  const res = await fetch(BASE + path, { credentials: 'same-origin', ...options, headers })
  const json = await res.json().catch(() => ({}))
  if (!res.ok) throw Object.assign(new Error(json.error || `HTTP ${res.status}`), { status: res.status, data: json })
  return json
}

const post = (path, data) => apiFetch(path, { method: 'POST', body: JSON.stringify(data) })

export const api = {
  auth: {
    login:    (data)        => post('/session', data),
    logout:   ()            => apiFetch('/session', { method: 'DELETE' }),
    me:       ()            => apiFetch('/session'),
    register: (data)        => post('/account/register', data),
    forgot:   (email, lang) => post('/account/password/forgot', { email, lang }),
    reset:    (data)        => post('/account/password/reset', data),
  },
  posts: {
    list:     (params = {}) => apiFetch('/posts?' + new URLSearchParams(params)),
    bySlug:   (slug, lang)  => apiFetch(`/posts/${encodeURIComponent(slug)}?lang=${encodeURIComponent(lang)}`),
    comments: (postId)      => apiFetch(`/posts/${postId}/comments`),
    comment:  (postId, data) => post(`/posts/${postId}/comments`, data),
  },
  pages: {
    bySlug:   (slug, lang)  => apiFetch(`/pages/${encodeURIComponent(slug)}?lang=${encodeURIComponent(lang)}`),
  },
}
