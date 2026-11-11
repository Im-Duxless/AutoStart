<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../services/api.js'
import { auth } from '../services/auth.js'
import { date } from '../services/format.js'
import ErrorNotice from '../components/ErrorNotice.vue'
import LoadingState from '../components/LoadingState.vue'
import MetricCard from '../components/MetricCard.vue'
import PageHeading from '../components/PageHeading.vue'
import StatusBadge from '../components/StatusBadge.vue'

const route = useRoute()
const attempt = ref(null)
const loading = ref(true)
const error = ref(null)
const admin = auth.user.role === 'admin'
const back = admin ? { name: 'admin-section', params: { section: 'exam-results' } } : { name: 'exams' }
const title = computed(() => attempt.value ? 'Результат билета № ' + attempt.value.ticket_number : 'Результат экзамена')

async function load() {
  loading.value = true
  error.value = null
  attempt.value = null
  try {
    attempt.value = (await api('/exams/history/' + route.params.id)).data
  } catch (problem) {
    error.value = problem
  } finally {
    loading.value = false
  }
}
watch(() => route.params.id, load, { immediate: true })
</script>

<template>
  <PageHeading :title="title" :text="attempt ? 'Категория ' + attempt.category + ' · ' + date(attempt.created_at) : undefined">
    <RouterLink class="btn btn-outline-primary" :to="back"><i class="bi bi-arrow-left" aria-hidden="true"></i> {{ admin ? 'К результатам' : 'К экзаменам' }}</RouterLink>
  </PageHeading>
  <LoadingState v-if="loading" />
  <template v-else-if="error">
    <ErrorNotice :error="error" />
    <button class="btn btn-outline-primary" type="button" @click="load">Повторить</button>
  </template>
  <template v-else-if="attempt">
    <div class="metrics-grid">
      <MetricCard label="Правильные ответы" :value="attempt.correct_answers + ' из ' + attempt.total_questions" icon="check2-square" />
      <MetricCard label="Итоговый балл" :value="attempt.percent + '%'" icon="percent" />
      <MetricCard label="Результат" :value="attempt.passed ? 'Сдан' : 'Не сдан'" icon="journal-check" />
    </div>
    <section class="surface">
      <div class="surface-heading">
        <h2>{{ admin ? 'Разбор · ' + attempt.student?.name : 'Разбор ответов' }}</h2>
        <RouterLink v-if="!admin" class="btn btn-primary btn-sm" :to="{ name: 'ticket', params: { category: attempt.category, number: attempt.ticket_number } }">Пройти ещё раз</RouterLink>
      </div>
      <article v-for="(answer, index) in attempt.answers" :key="answer.question_id" class="answer-review">
        <StatusBadge :value="answer.selected_option === answer.correct_option ? 'passed' : 'failed'" :label="answer.selected_option === answer.correct_option ? 'Верно' : 'Ошибка'" />
        <h3>{{ index + 1 }}. {{ answer.text }}</h3>
        <p><strong>Выбранный ответ</strong> · {{ answer.options[answer.selected_option] }}</p>
        <p v-if="answer.selected_option !== answer.correct_option"><strong>Правильный ответ</strong> · {{ answer.options[answer.correct_option] }}</p>
      </article>
    </section>
  </template>
</template>
