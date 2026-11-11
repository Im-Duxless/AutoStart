<script setup>
import { computed, onMounted, ref } from 'vue'
import { api, getAll } from '../services/api.js'
import { carName, date, duration, statuses, options, time, today } from '../services/format.js'
import { school } from '../services/school.js'
import { useList } from '../services/useList.js'
import EmptyState from '../components/EmptyState.vue'
import ErrorNotice from '../components/ErrorNotice.vue'
import FilterBar from '../components/FilterBar.vue'
import LessonTable from '../components/LessonTable.vue'
import LoadingState from '../components/LoadingState.vue'
import MetricCard from '../components/MetricCard.vue'
import PageHeading from '../components/PageHeading.vue'
import PaginationBar from '../components/PaginationBar.vue'
import StatusBadge from '../components/StatusBadge.vue'

const openSlots = useList('/lessons', { type: 'driving', mode: 'available', instructor_id: '', from: '', to: '' })
const mySlots = useList('/lessons', { type: 'driving', mode: 'mine', status: '', from: '', to: '', order: 'desc' })
const { rows, filters, meta, loading, error, load } = openSlots
const { rows: myRows, filters: myFilters, meta: myMeta, loading: myLoading, error: myError, load: loadMine } = mySlots
const tab = ref('free')
const summary = ref(null)
const groups = ref([])
const busy = ref(true)
const profileError = ref(null)

const freeFields = computed(() => [
  { key: 'instructor_id', label: 'Инструктор', options: (school.data?.instructors || []).map(row => ({ value: row.id, label: row.name })) },
  { key: 'from', label: 'Дата с', type: 'date', min: today() },
  { key: 'to', label: 'Дата по', type: 'date', min: today() },
])
const myFields = [
  { key: 'status', label: 'Статус', options: options({ booked: statuses.booked, completed: statuses.completed, cancelled: statuses.cancelled }) },
  { key: 'from', label: 'Дата с', type: 'date' },
  { key: 'to', label: 'Дата по', type: 'date' },
]

async function start() {
  busy.value = true
  profileError.value = null
  try {
    const [result, approved] = await Promise.all([api('/lessons/summary'), getAll('/enrollments', { status: 'approved' })])
    summary.value = result.data
    groups.value = approved
    if (groups.value.length) await load()
  } catch (problem) {
    profileError.value = problem
  } finally {
    busy.value = false
  }
}

function selectTab(value) {
  tab.value = value
  if (value === 'mine') loadMine()
}

function reset() {
  Object.assign(filters, { instructor_id: '', from: '', to: '' })
  load()
}
function resetMine() {
  Object.assign(myFilters, { status: '', from: '', to: '' })
  loadMine()
}
onMounted(start)
</script>

<template>
  <PageHeading title="Вождение" text="Выберите время и инструктора или откройте свои записи." />
  <LoadingState v-if="busy" />
  <template v-else>
    <ErrorNotice :error="profileError" />
    <button v-if="profileError" type="button" class="btn btn-outline-primary" @click="start">Повторить</button>
    <div v-if="summary" class="metrics-grid">
      <MetricCard label="Время за рулём" :value="duration(summary.minutes)" icon="clock" />
      <MetricCard label="Завершено занятий" :value="summary.completed" icon="check2-circle" />
      <MetricCard label="Предстоит вождений" :value="summary.upcoming" icon="calendar-week" />
    </div>
    <div v-if="!profileError" class="tab-list" aria-label="Разделы вождения">
      <button type="button" :aria-pressed="tab === 'free'" @click="selectTab('free')">Свободные занятия</button>
      <button type="button" :aria-pressed="tab === 'mine'" @click="selectTab('mine')">Мои занятия</button>
    </div>
    <section v-if="!profileError && tab === 'free'" class="surface">
      <EmptyState v-if="!groups.length" title="Нужно зачисление в группу" text="После подтверждения заявки здесь появятся доступные занятия вашей категории." icon="car-front">
        <RouterLink :to="{ name: 'groups' }" class="btn btn-primary">Учебные группы</RouterLink>
      </EmptyState>
      <template v-else>
        <FilterBar v-model="filters" :fields="freeFields" :busy="loading" @apply="load()" @reset="reset" />
        <ErrorNotice :error="error" class="m-3" />
        <LoadingState v-if="loading" />
        <EmptyState v-else-if="!rows.length" title="Свободных занятий по этим условиям нет" text="Попробуйте выбрать другие даты или инструктора." icon="calendar-week" />
        <div v-else class="slot-grid">
          <article v-for="lesson in rows" :key="lesson.id" class="slot-card">
            <div class="card-top-line"><StatusBadge :value="lesson.status" /><span class="table-subtitle">Категория {{ lesson.car?.category }}</span></div>
            <h2>{{ date(lesson.start_at, true) }} · {{ time(lesson.start_at) }}–{{ time(lesson.end_at) }}</h2>
            <p><i class="bi bi-person" aria-hidden="true"></i> {{ lesson.instructor?.name }}</p>
            <p><i class="bi bi-car-front" aria-hidden="true"></i> {{ carName(lesson.car) }} · {{ duration(lesson.minutes) }}</p>
            <RouterLink class="btn btn-primary btn-sm" :to="{ name: 'lesson', params: { id: lesson.id } }">Выбрать время</RouterLink>
          </article>
        </div>
        <PaginationBar :meta="meta" :disabled="loading" @change="load" />
      </template>
    </section>
    <section v-else-if="!profileError" class="surface">
      <FilterBar v-model="myFilters" :fields="myFields" :busy="myLoading" @apply="loadMine()" @reset="resetMine" />
      <ErrorNotice :error="myError" class="m-3" />
      <LessonTable :lessons="myRows" :loading="myLoading" />
      <PaginationBar :meta="myMeta" :disabled="myLoading" @change="loadMine" />
    </section>
  </template>
</template>
