// Lightweight fetch wrapper for PHP API endpoints
const BASE = '/api'

function getToken() {
  const m = document.cookie.match(/(?:^|;\s*)token=([^;]+)/)
  return m ? m[1] : null
}

export async function apiFetch(path, options = {}) {
  const token = getToken()
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
    ...options.headers,
  }
  const res = await fetch(BASE + path, { ...options, headers })
  const json = await res.json().catch(() => ({}))
  if (!res.ok) throw Object.assign(new Error(json.error || `HTTP ${res.status}`), { status: res.status, data: json })
  return json
}

export const api = {
  auth: {
    login:    (data)  => apiFetch('/auth/login',    { method: 'POST', body: JSON.stringify(data) }),
    logout:   ()      => apiFetch('/auth/logout',   { method: 'POST' }),
    me:       ()      => apiFetch('/auth/me'),
    register: (data)  => apiFetch('/auth/register', { method: 'POST', body: JSON.stringify(data) }),
    forgot:   (email) => apiFetch('/auth/forgot',   { method: 'POST', body: JSON.stringify({ email }) }),
    reset:    (data)  => apiFetch('/auth/reset',    { method: 'POST', body: JSON.stringify(data) }),
  },
  blog: {
    list:   (params = {}) => apiFetch('/post/index?' + new URLSearchParams(params)),
    show:   (id)          => apiFetch('/post/show/' + id),
    create: (data)        => apiFetch('/post/store',      { method: 'POST', body: JSON.stringify(data) }),
    update: (id, data)    => apiFetch('/post/update/' + id, { method: 'PUT',  body: JSON.stringify(data) }),
    remove: (id)          => apiFetch('/post/delete/' + id, { method: 'DELETE' }),
  },
  pages: {
    list:   ()         => apiFetch('/page/index'),
    show:   (id)       => apiFetch('/page/show/' + id),
    create: (data)     => apiFetch('/page/store',       { method: 'POST', body: JSON.stringify(data) }),
    update: (id, data) => apiFetch('/page/update/' + id, { method: 'PUT',  body: JSON.stringify(data) }),
    remove: (id)       => apiFetch('/page/delete/' + id, { method: 'DELETE' }),
  },
  users: {
    list:   ()         => apiFetch('/user/index'),
    show:   (id)       => apiFetch('/user/show/' + id),
    update: (id, data) => apiFetch('/user/update/' + id, { method: 'PUT',  body: JSON.stringify(data) }),
    remove: (id)       => apiFetch('/user/delete/' + id, { method: 'DELETE' }),
  },
  calendar: {
    events: (month) => apiFetch('/calendar/events?month=' + month),
  },
}
