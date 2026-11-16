<script setup>
import { computed, onMounted, ref } from 'vue'
import { loadOptions } from '../admin/lookups.js'
import { api } from '../services/api.js'
import { money } from '../services/format.js'
import { useList } from '../services/useList.js'
import AdminRecordForm from './AdminRecordForm.vue'
import ConfirmDialog from './ConfirmDialog.vue'
import EmptyState from './EmptyState.vue'
import ErrorNotice from './ErrorNotice.vue'
import FilterBar from './FilterBar.vue'
import LoadingState from './LoadingState.vue'
import PageHeading from './PageHeading.vue'
import PaginationBar from './PaginationBar.vue'
import StatusBadge from './StatusBadge.vue'

const props = defineProps({ config: { type: Object, required: true } })
const defaults = Object.fromEntries(props.config.filters.map(field => [field.key, '']))
const { rows, filters, meta, result, loading, error, load, reload } = useList(props.config.path, { ...defaults, sort: '', order: '' })
const lookups = ref({})
const lookupError = ref(null)
const selection = ref(null)
const editor = ref(null)
const busy = ref(false)
const actionError = ref(null)
const success = ref('')
const fields = computed(() => props.config.filters.map(field => field.lookup ? { ...field, options: lookups.value[field.lookup] || [] } : field))

async function loadFilterOptions() {
  lookupError.value = null
  try {
    const names = props.config.filters.map(field => field.lookup).filter(Boolean)
    lookups.value = Object.fromEntries(await Promise.all(names.map(async name => [name, await loadOptions(name)])))
  } catch (problem) {
    lookupError.value = problem
  }
}

function sort(column) {
  filters.order = filters.sort === column.key && filters.order === 'asc' ? 'desc' : 'asc'
  filters.sort = column.key
  load()
}
function reset() {
  Object.assign(filters, defaults, { sort: '', order: '' })
  load()
}
function open(id = null) {
  actionError.value = null
  success.value = ''
  editor.value = { id }
}
function saved(message) {
  editor.value = null
  success.value = message
  reload()
}
async function remove() {
  busy.value = true
  actionError.value = null
  success.value = ''
  try {
    await api(props.config.path + '/' + selection.value.id, { method: 'DELETE' })
    success.value = 'Запись удалена.'
    await reload()
  } catch (problem) {
    actionError.value = problem
  } finally {
    busy.value = false
    selection.value = null
  }
}
onMounted(() => Promise.all([load(), loadFilterOptions()]))
</script>

<template>
  <PageHeading :title="config.title" :text="config.description">
    <button class="btn btn-accent" type="button" :disabled="!!editor" @click="open()"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ config.create }}</button>
  </PageHeading>
  <ErrorNotice :error="actionError" />
  <ErrorNotice :error="lookupError" />
  <button v-if="lookupError" type="button" class="btn btn-outline-primary mb-3" @click="loadFilterOptions">Загрузить фильтры</button>
  <p v-if="success" class="alert alert-success" role="status">{{ success }}</p>
  <slot name="notices" />
  <AdminRecordForm v-if="editor" :key="editor.id || 'new'" :config="config" :record-id="editor.id" @close="editor = null" @saved="saved" />
  <section class="surface">
    <FilterBar v-model="filters" :fields="fields" :busy="loading" @apply="load()" @reset="reset" />
    <ErrorNotice :error="error" class="m-3" />
    <p v-if="result?.received_total !== undefined" class="px-3 pt-3 mb-2">Учтено по фильтрам <strong>{{ money(result.received_total) }}</strong></p>
    <LoadingState v-if="loading" />
    <EmptyState v-else-if="!rows.length" title="Записи по выбранным условиям не найдены" />
    <div v-else class="table-responsive">
      <table class="table table-hover data-table">
        <thead>
          <tr>
            <th v-for="column in config.columns" :key="column.key" scope="col"
              :aria-sort="filters.sort === column.key ? (filters.order === 'asc' ? 'ascending' : 'descending') : undefined">
              <button v-if="column.sort" type="button" class="table-sort" :disabled="loading" @click="sort(column)">
                {{ column.label }} <i :class="filters.sort === column.key ? (filters.order === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down') : 'bi bi-arrow-down-up'" aria-hidden="true"></i>
              </button>
              <template v-else>{{ column.label }}</template>
            </th>
            <th scope="col">Действия</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td v-for="column in config.columns" :key="column.key">
              <StatusBadge v-if="column.badge" :value="row[column.key]" :label="column.value?.(row)" />
              <template v-else>{{ (column.value ? column.value(row) : row[column.key]) ?? '—' }}</template>
            </td>
            <td>
              <div class="row-actions">
                <slot name="row-actions" :row="row" :reload="reload" />
                <RouterLink v-if="config.path === '/lessons'" class="btn btn-outline-primary btn-sm" :to="{ name: 'lesson', params: { id: row.id } }">Открыть</RouterLink>
                <button v-if="!config.canEdit || config.canEdit(row)" type="button" class="btn btn-outline-primary btn-sm"
                  :aria-label="'Редактировать ' + config.itemName(row)" @click="open(row.id)"><i class="bi bi-pencil" aria-hidden="true"></i></button>
                <button v-if="!config.canDelete || config.canDelete(row)" type="button" class="btn btn-outline-danger btn-sm"
                  :aria-label="'Удалить ' + config.itemName(row)" @click="selection = row"><i class="bi bi-trash" aria-hidden="true"></i></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <PaginationBar :meta="meta" :disabled="loading" @change="load" />
  </section>
  <ConfirmDialog :open="!!selection" title="Удалить запись?" :text="selection ? config.itemName(selection) : ''" label="Удалить" danger :busy="busy" @confirm="remove" @close="selection = null" />
</template>
