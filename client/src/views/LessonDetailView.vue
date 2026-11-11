<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../services/api.js'
import { auth } from '../services/auth.js'
import { carName, date, duration, time } from '../services/format.js'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import ErrorNotice from '../components/ErrorNotice.vue'
import LoadingState from '../components/LoadingState.vue'
import PageHeading from '../components/PageHeading.vue'
import StatusBadge from '../components/StatusBadge.vue'

const route = useRoute()
const lesson = ref(null)
const loading = ref(true)
const error = ref(null)
const busy = ref(false)
const success = ref('')
const action = ref(null)
const student = auth.user.role === 'student'

const actions = {
  book: { label: 'Записаться', title: 'Записаться на вождение?', text: 'Занятие появится в разделе «Мои занятия».', success: 'Вы записаны на вождение.' },
  cancel: {
    label: student ? 'Отменить запись' : 'Отменить занятие', title: student ? 'Отменить запись?' : 'Отменить занятие?',
    text: student ? 'Время снова станет доступно для записи.' : 'Занятие исчезнет из предстоящего расписания.',
    success: student ? 'Запись отменена.' : 'Занятие отменено.',
  },
  complete: { label: 'Отметить проведённым', title: 'Завершить занятие?', text: 'Время занятия будет учтено в статистике инструктора и курсанта.', success: 'Занятие завершено. Время учтено.' },
}
const back = computed(() => {
  if (auth.user.role === 'admin') return { name: 'admin-section', params: { section: 'lessons' } }
  if (!student) return { name: 'instructor-lessons' }
  return { name: lesson.value?.type === 'theory' ? 'theory' : 'driving' }
})

async function load() {
  loading.value = true
  error.value = null
  success.value = ''
  lesson.value = null
  try {
    lesson.value = (await api('/lessons/' + route.params.id)).data
  } catch (problem) {
    error.value = problem
  } finally {
    loading.value = false
  }
}

async function submit() {
  const operation = action.value
  busy.value = true
  error.value = null
  success.value = ''
  try {
    lesson.value = (await api('/lessons/' + lesson.value.id + '/' + operation, { method: 'POST' })).data
    success.value = actions[operation].success
  } catch (problem) {
    error.value = problem
  } finally {
    action.value = null
    busy.value = false
  }
}
watch(() => route.params.id, load, { immediate: true })
</script>

<template>
  <PageHeading :title="lesson?.type === 'theory' ? 'Теоретическое занятие' : 'Занятие по вождению'">
    <RouterLink class="btn btn-outline-primary" :to="back"><i class="bi bi-arrow-left" aria-hidden="true"></i> К списку</RouterLink>
  </PageHeading>
  <ErrorNotice :error="error" />
  <p v-if="success" class="alert alert-success" role="status">{{ success }}</p>
  <LoadingState v-if="loading" />
  <article v-else-if="lesson" class="surface surface-pad">
    <dl class="detail-grid">
      <div><dt>Дата</dt><dd>{{ date(lesson.start_at) }}</dd></div>
      <div><dt>Время</dt><dd>{{ time(lesson.start_at) }}–{{ time(lesson.end_at) }}</dd></div>
      <div><dt>Длительность</dt><dd>{{ duration(lesson.minutes) }}</dd></div>
      <div><dt>Статус</dt><dd><StatusBadge :value="lesson.status" /></dd></div>
      <div v-if="lesson.study_group"><dt>Группа</dt><dd>{{ lesson.study_group.name }} · {{ lesson.study_group.category }}</dd></div>
      <div v-if="lesson.instructor"><dt>Инструктор</dt><dd>{{ lesson.instructor.name }}</dd></div>
      <div v-if="lesson.car"><dt>Транспорт</dt><dd>{{ carName(lesson.car) }} · {{ lesson.car.plate_number }}</dd></div>
      <div v-if="lesson.student && !student"><dt>Курсант</dt><dd>{{ lesson.student.name }}</dd></div>
    </dl>
    <div class="inline-form-actions">
      <button v-if="lesson.can_book" class="btn btn-accent" type="button" @click="action = 'book'">{{ actions.book.label }}</button>
      <button v-if="lesson.can_complete" class="btn btn-primary" type="button" @click="action = 'complete'">{{ actions.complete.label }}</button>
      <button v-if="lesson.can_cancel" class="btn btn-outline-danger" type="button" @click="action = 'cancel'">{{ actions.cancel.label }}</button>
    </div>
  </article>
  <button v-else class="btn btn-outline-primary" type="button" @click="load">Повторить</button>
  <ConfirmDialog :open="!!action" :title="actions[action]?.title" :text="actions[action]?.text"
    :label="actions[action]?.label" :busy="busy" :danger="action === 'cancel'" @confirm="submit" @close="action = null" />
</template>
