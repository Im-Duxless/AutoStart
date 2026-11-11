const baseUrl = (import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '')
const storageKey = 'autostart_token'

export function getToken() {
  return localStorage.getItem(storageKey)
}

export function setToken(token) {
  if (token) localStorage.setItem(storageKey, token)
  else localStorage.removeItem(storageKey)
}

export class ApiError extends Error {
  constructor(message, status = 0, errors = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

export async function api(path, { method = 'GET', params = {}, body, authorized = true } = {}) {
  const url = new URL(baseUrl + path)
  for (const [key, value] of Object.entries(params)) {
    if (value !== '' && value !== null && value !== undefined) {
      url.searchParams.set(key, typeof value === 'boolean' ? Number(value) : value)
    }
  }

  const token = authorized ? getToken() : null
  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (token) headers.Authorization = 'Bearer ' + token

  let response
  try {
    response = await fetch(url, { method, headers, body: body === undefined ? undefined : JSON.stringify(body) })
  } catch {
    throw new ApiError('Не удалось связаться с сервером. Проверьте подключение и повторите попытку.')
  }

  if (response.status === 204) return null
  const result = await response.json().catch(() => ({}))
  if (response.ok) return result

  if (response.status === 401 && token && token === getToken()) {
    setToken(null)
    window.dispatchEvent(new Event('autostart:expired'))
  }
  const message = response.status >= 500
    ? 'На сервере произошла ошибка. Повторите попытку позже.'
    : result.message || 'Запрос не удалось выполнить.'
  throw new ApiError(message, response.status, result.errors)
}

export async function getAll(path, params = {}) {
  const rows = []
  let page = 1
  let last = 1
  do {
    const result = await api(path, { params: { ...params, per_page: 100, page } })
    rows.push(...result.data)
    last = result.meta.last_page
    page += 1
  } while (page <= last)
  return rows
}
