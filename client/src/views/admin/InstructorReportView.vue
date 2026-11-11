<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../../services/api.js'
import { duration } from '../../services/format.js'
import EmptyState from '../../components/EmptyState.vue'
import ErrorNotice from '../../components/ErrorNotice.vue'
import FilterBar from '../../components/FilterBar.vue'
import LoadingState from '../../components/LoadingState.vue'
import PageHeading from '../../components/PageHeading.vue'

const filters = reactive({ from: '', to: '' })
const fields = [{ key: 'from', label: 'Дата с', type: 'date' }, { key: 'to', label: 'Дата по', type: 'date' }]
const rows = ref([])
const loading = ref(true)
const error = ref(null)
const total = computed(() => rows.value.reduce((sum, row) => ({
  completed: sum.completed + row.completed, minutes: sum.minutes + row.minutes,
}), { completed: 0, minutes: 0 }))

async function load() {
  loading.value = true
  error.value = null
  try {
    rows.value = (await api('/reports/instructors', { params: filters })).data
  } catch (problem) {
    error.value = problem
    rows.value = []
  } finally {
    loading.value = false
  }
}

function reset() {
  Object.assign(filters, { from: '', to: '' })
  load()
}
onMounted(load)
</script>

<template>
  <PageHeading title="Отчёт по инструкторам" text="Завершённые вождения и проведённое время за выбранный период." />
  <section class="surface">
    <FilterBar v-model="filters" :fields="fields" :busy="loading" @apply="load" @reset="reset" />
    <ErrorNotice :error="error" class="m-3" />
    <LoadingState v-if="loading" />
    <EmptyState v-else-if="!rows.length && !error" title="Инструкторы пока не добавлены" icon="person-badge" />
    <div v-else-if="!error" class="table-responsive">
      <table class="table data-table">
        <thead><tr><th scope="col">Инструктор</th><th scope="col">Завершено занятий</th><th scope="col">Проведено времени</th></tr></thead>
        <tbody><tr v-for="row in rows" :key="row.id"><td>{{ row.name }}</td><td>{{ row.completed }}</td><td>{{ duration(row.minutes) }}</td></tr></tbody>
        <tfoot class="fw-bold"><tr><th scope="row">Всего</th><td>{{ total.completed }}</td><td>{{ duration(total.minutes) }}</td></tr></tfoot>
      </table>
    </div>
  </section>
</template>
