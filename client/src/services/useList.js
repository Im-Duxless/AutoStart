import { reactive, ref } from 'vue'
import { api } from './api.js'

export function useList(path, defaults = {}, perPage = 12) {
  const filters = reactive({ ...defaults })
  const rows = ref([])
  const meta = ref(null)
  const result = ref(null)
  const loading = ref(false)
  const error = ref(null)
  let requestNumber = 0

  async function load(page = 1) {
    const request = ++requestNumber
    loading.value = true
    error.value = null
    try {
      const response = await api(path, { params: { ...filters, page, per_page: perPage } })
      if (request !== requestNumber) return
      if (page > response.meta.last_page) return load(response.meta.last_page)
      result.value = response
      rows.value = response.data
      meta.value = response.meta
    } catch (problem) {
      if (request === requestNumber) error.value = problem
    } finally {
      if (request === requestNumber) loading.value = false
    }
  }

  return { filters, rows, meta, result, loading, error, load, reload: () => load(meta.value?.current_page || 1) }
}
