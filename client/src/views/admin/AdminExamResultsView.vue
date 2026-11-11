<script setup>
import { onMounted } from 'vue'
import { categories } from '../../admin/tables.js'
import { useList } from '../../services/useList.js'
import ErrorNotice from '../../components/ErrorNotice.vue'
import ExamHistoryTable from '../../components/ExamHistoryTable.vue'
import FilterBar from '../../components/FilterBar.vue'
import PageHeading from '../../components/PageHeading.vue'
import PaginationBar from '../../components/PaginationBar.vue'

const defaults = { search: '', category: '', passed: '', ticket_number: '' }
const { rows, filters, meta, loading, error, load } = useList('/exams/history', defaults)
const fields = [
  { key: 'search', label: 'Поиск курсанта', placeholder: 'Имя или фамилия' },
  { key: 'category', label: 'Категория', options: categories },
  { key: 'passed', label: 'Результат', options: [{ value: '1', label: 'Сдан' }, { value: '0', label: 'Не сдан' }] },
  { key: 'ticket_number', label: 'Номер билета', type: 'number', min: 1 },
]
function reset() {
  Object.assign(filters, defaults)
  load()
}
onMounted(() => load())
</script>

<template>
  <PageHeading title="Результаты ПДД" text="Попытки курсантов и разбор каждого билета." />
  <section class="surface">
    <FilterBar v-model="filters" :fields="fields" :busy="loading" @apply="load()" @reset="reset" />
    <ErrorNotice :error="error" class="m-3" />
    <ExamHistoryTable :attempts="rows" :loading="loading" admin />
    <PaginationBar :meta="meta" :disabled="loading" @change="load" />
  </section>
</template>
