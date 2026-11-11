import { reactive } from 'vue'
import { api } from './api.js'

export const school = reactive({ data: null, loading: true, error: null })

export async function loadSchool() {
  if (school.data) return
  school.loading = true
  school.error = null
  try {
    school.data = (await api('/school', { authorized: false })).data
  } catch (error) {
    school.error = error
  } finally {
    school.loading = false
  }
}
