<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api, getAll } from '../services/api.js'
import { carName, duration, options, statuses } from '../services/format.js'
import { useList } from '../services/useList.js'
import ErrorNotice from '../components/ErrorNotice.vue'
import FilterBar from '../components/FilterBar.vue'
import FormField from '../components/FormField.vue'
import LessonTable from '../components/LessonTable.vue'
import LoadingState from '../components/LoadingState.vue'
import MetricCard from '../components/MetricCard.vue'
import PageHeading from '../components/PageHeading.vue'
import PaginationBar from '../components/PaginationBar.vue'

const defaults = { from: '', to: '', status: '', order: 'desc' }
const { rows, filters, meta, error, loading, load, reload } = useList('/lessons', defaults)
const stats = ref(null)
const statsError = ref(null)
const form = reactive({ car_id: '', start_at: '', end_at: '' })
const cars = ref([])
const showForm = ref(false)
const carsLoading = ref(false)
const busy = ref(false)
const actionError = ref(null)
const success = ref('')

const fields = [
  { key: 'status', label: 'Статус', options: options({ free: statuses.free, booked: statuses.booked, completed: statuses.completed, cancelled: statuses.cancelled }) },
  { key: 'from', label: 'Дата с', type: 'date' }, { key: 'to', label: 'Дата по', type: 'date' },
  { key: 'order', label: 'Порядок дат', options: options({ desc: 'Сначала поздние', asc: 'Сначала ранние' }) },
]

async function loadStats() {
  statsError.value = null
  try {
    stats.value = (await api('/lessons/summary')).data
  } catch (problem) {
    statsError.value = problem
  }
}

async function openForm() {
  showForm.value = true
  actionError.value = null
  success.value = ''
  carsLoading.value = true
  try {
    cars.value = (await getAll('/cars', { sort: 'brand', order: 'asc' }))
      .map(car => ({ value: car.id, label: carName(car) + ' · ' + car.plate_number + ' · ' + car.category }))
  } catch (problem) {
    actionError.value = problem
  } finally {
    carsLoading.value = false
  }
}

async function save() {
  actionError.value = null
  busy.value = true
  try {
    await api('/lessons', { method: 'POST', body: { ...form, type: 'driving' } })
    success.value = 'Слот добавлен. Курсанты этой категории могут записаться.'
    showForm.value = false
    Object.assign(form, { car_id: '', start_at: '', end_at: '' })
    await Promise.all([reload(), loadStats()])
  } catch (problem) {
    actionError.value = problem
  } finally {
    busy.value = false
  }
}
function reset() {
  Object.assign(filters, defaults)
  load()
}
onMounted(() => Promise.all([load(), loadStats()]))
</script>

<template>
  <PageHeading title="Мои занятия" text="Открывайте время для записи и отмечайте проведённые вождения.">
    <button class="btn btn-accent" type="button" :disabled="showForm" @click="openForm"><i class="bi bi-plus-lg" aria-hidden="true"></i> Добавить слот</button>
  </PageHeading>
  <ErrorNotice :error="statsError" />
  <div v-if="stats" class="metrics-grid">
    <MetricCard label="Проведено времени" :value="duration(stats.minutes)" icon="clock" />
    <MetricCard label="Завершено занятий" :value="stats.completed" icon="check2-circle" />
    <MetricCard label="Предстоит вождений" :value="stats.upcoming" icon="calendar-week" />
  </div>
  <p v-if="success" class="alert alert-success" role="status">{{ success }}</p>
  <section v-if="showForm" class="surface surface-pad lesson-form">
    <h2 class="h5">Новый слот вождения</h2>
    <ErrorNotice :error="actionError" />
    <LoadingState v-if="carsLoading" />
    <form v-else class="form-stack" @submit.prevent="save">
      <FormField v-model="form.car_id" label="Транспорт" :options="cars" required :error="actionError?.errors?.car_id?.[0]" />
      <div class="field-grid">
        <FormField v-model="form.start_at" label="Начало" type="datetime-local" required :error="actionError?.errors?.start_at?.[0]" />
        <FormField v-model="form.end_at" label="Окончание" type="datetime-local" required :error="actionError?.errors?.end_at?.[0]" />
      </div>
      <div class="inline-form-actions">
        <button class="btn btn-primary" type="submit" :disabled="busy || !cars.length">{{ busy ? 'Сохраняем…' : 'Сохранить слот' }}</button>
        <button class="btn btn-outline-primary" type="button" :disabled="busy" @click="showForm = false">Закрыть</button>
        <button v-if="!cars.length && actionError" class="btn btn-outline-primary" type="button" @click="openForm">Загрузить транспорт</button>
      </div>
      <p v-if="!cars.length && !actionError" class="mb-0">Активный транспорт пока не добавлен.</p>
    </form>
  </section>
  <section class="surface">
    <FilterBar v-model="filters" :fields="fields" :busy="loading" @apply="load()" @reset="reset" />
    <ErrorNotice :error="error" class="m-3" />
    <LessonTable :lessons="rows" :loading="loading" staff />
    <PaginationBar :meta="meta" :disabled="loading" @change="load" />
  </section>
</template>
