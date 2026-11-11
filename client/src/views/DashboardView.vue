<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../services/api.js'
import { auth } from '../services/auth.js'
import { duration, money } from '../services/format.js'
import ErrorNotice from '../components/ErrorNotice.vue'
import LessonTable from '../components/LessonTable.vue'
import LoadingState from '../components/LoadingState.vue'
import MetricCard from '../components/MetricCard.vue'
import PageHeading from '../components/PageHeading.vue'
import StatusBadge from '../components/StatusBadge.vue'

const role = auth.user.role
const student = role === 'student'
const instructor = role === 'instructor'
const stats = ref(null)
const lessons = ref([])
const application = ref(null)
const balance = ref(null)
const busy = ref(true)
const error = ref(null)

const adminLinks = [
  ['enrollments', 'Рассмотреть заявки', 'inbox'],
  ['lessons', 'Открыть расписание', 'calendar-week'],
  ['instructor-report', 'Отчёт по инструкторам', 'bar-chart'],
]
const metrics = computed(() => {
  const value = stats.value
  if (!value) return []
  if (role === 'admin') return [
    ['Курсанты', value.students, 'people'],
    ['Инструкторы', value.instructors, 'person-badge'],
    ['Учебные группы', value.groups, 'mortarboard'],
    ['Предстоящие занятия', value.planned_lessons, 'calendar-week'],
    ['Учтённые платежи', money(value.received_payments), 'wallet2'],
    ['Заявки на рассмотрении', value.pending_enrollments, 'inbox'],
  ]
  return [
    [instructor ? 'Проведено времени' : 'Время за рулём', duration(value.minutes), 'clock'],
    ['Завершено занятий', value.completed, 'check2-circle'],
    student ? ['Осталось оплатить', money(balance.value?.due), 'wallet2'] : ['Предстоит вождений', value.upcoming, 'calendar-week'],
  ]
})

async function load() {
  busy.value = true
  error.value = null
  try {
    if (role === 'admin') {
      stats.value = (await api('/reports/summary')).data
    } else {
      const [summary, upcoming, enrollments, payments] = await Promise.all([
        api('/lessons/summary'),
        api('/lessons', { params: { mode: 'mine', upcoming: true, per_page: 5 } }),
        student ? api('/enrollments') : null,
        student ? api('/payments/balance') : null,
      ])
      stats.value = summary.data
      lessons.value = upcoming.data
      application.value = enrollments?.data.find(row => row.status === 'approved') || enrollments?.data[0] || null
      balance.value = payments?.data
    }
  } catch (problem) {
    error.value = problem
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <PageHeading :title="role === 'admin' ? 'Управление автошколой' : 'Обзор кабинета'"
    :text="role === 'admin' ? 'Заявки, расписание и показатели AutoStart.' : 'Здравствуйте, ' + auth.user.name.split(' ')[0] + '!'"/>
  <LoadingState v-if="busy" />
  <template v-else-if="error">
    <ErrorNotice :error="error" />
    <button class="btn btn-outline-primary" type="button" @click="load">Повторить</button>
  </template>
  <template v-else>
    <div v-if="student" class="cabinet-hero">
      <div v-if="application"><h2>{{ application.group_name }}</h2><StatusBadge :value="application.status" /></div>
      <div v-else><h2>Выберите программу обучения</h2><p>Подайте заявку в подходящую группу.</p></div>
      <RouterLink class="btn btn-accent" :to="{ name: 'groups' }">{{ application ? 'Моё обучение' : 'Выбрать группу' }} <i class="bi bi-arrow-right" aria-hidden="true"></i></RouterLink>
    </div>
    <div class="metrics-grid">
      <MetricCard v-for="[label, value, icon] in metrics" :key="label" :label="label" :value="value" :icon="icon" />
    </div>
    <section v-if="role === 'admin'" class="surface surface-pad">
      <h2 class="h5 mb-3">Быстрые действия</h2>
      <div class="inline-form-actions">
        <RouterLink v-for="[section, label, icon] in adminLinks" :key="section" class="btn btn-outline-primary" :to="{ name: 'admin-section', params: { section } }">
          <i :class="'bi bi-' + icon" aria-hidden="true"></i>{{ label }}
        </RouterLink>
      </div>
    </section>
    <section v-else class="surface">
      <div class="surface-heading">
        <h2>Ближайшие занятия</h2>
        <RouterLink class="btn btn-outline-primary btn-sm" :to="{ name: instructor ? 'instructor-lessons' : 'theory' }">Расписание <i class="bi bi-arrow-right" aria-hidden="true"></i></RouterLink>
      </div>
      <LessonTable :lessons="lessons" :staff="instructor" empty-title="Предстоящих занятий нет" />
    </section>
  </template>
</template>
