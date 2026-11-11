import { reactive } from 'vue'
import { api, getToken, setToken } from './api.js'

export const auth = reactive({ user: null })

export async function restoreUser() {
  if (auth.user || !getToken()) return
  const result = await api('/me')
  auth.user = result.data
}

export async function signIn(mode, body) {
  const result = await api('/' + mode, { method: 'POST', body, authorized: false })
  setToken(result.token)
  auth.user = result.data
}

export async function signOut() {
  await api('/logout', { method: 'POST' })
  clearSession()
}

export function clearSession() {
  setToken(null)
  auth.user = null
}
