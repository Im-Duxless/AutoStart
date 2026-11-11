<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../services/api.js'
import ErrorNotice from '../components/ErrorNotice.vue'
import LoadingState from '../components/LoadingState.vue'
import PageHeading from '../components/PageHeading.vue'

const route = useRoute()
const router = useRouter()
const ticket = ref(null)
const selected = reactive({})
const loading = ref(true)
const busy = ref(false)
const error = ref(null)
const answered = computed(() => Object.keys(selected).length)

async function load() {
  loading.value = true
  error.value = null
  ticket.value = null
  Object.keys(selected).forEach(key => { delete selected[key] })
  try {
    ticket.value = await api('/tickets/' + route.params.number, { params: { category: route.params.category } })
  } catch (problem) {
    error.value = problem
  } finally {
    loading.value = false
  }
}

async function submit() {
  if (answered.value !== ticket.value.data.length) {
    error.value = 'Выберите ответ на каждый вопрос.'
    return
  }
  busy.value = true
  error.value = null
  try {
    const result = await api('/exams/submit', {
      method: 'POST',
      body: {
        category: ticket.value.category, ticket_number: ticket.value.ticket_number, version: ticket.value.version,
        answers: ticket.value.data.map(question => ({ question_id: question.id, option: selected[question.id] })),
      },
    })
    await router.push({ name: 'exam-result', params: { id: result.data.id } })
  } catch (problem) {
    error.value = problem
  } finally {
    busy.value = false
  }
}

watch(() => [route.params.category, route.params.number], load, { immediate: true })
</script>

<template>
  <PageHeading :title="'Билет № ' + route.params.number" :text="'Категория ' + route.params.category + '. Выберите один ответ на каждый вопрос.'">
    <RouterLink class="btn btn-outline-primary" :to="{ name: 'exams' }"><i class="bi bi-arrow-left" aria-hidden="true"></i> К билетам</RouterLink>
  </PageHeading>
  <LoadingState v-if="loading" />
  <div v-else-if="!ticket">
    <ErrorNotice :error="error" />
    <button class="btn btn-outline-primary" type="button" @click="load">Повторить</button>
  </div>
  <form v-else class="form-stack" @submit.prevent="submit">
    <div class="surface surface-pad d-flex justify-content-between gap-3 align-items-center" role="status">
      <strong>Отвечено {{ answered }} из {{ ticket.data.length }}</strong>
      <progress class="exam-progress" :max="ticket.data.length" :value="answered" aria-label="Отвечено вопросов"></progress>
    </div>
    <fieldset v-for="(question, index) in ticket.data" :key="question.id" class="exam-question" :disabled="busy">
      <legend>Вопрос {{ index + 1 }} из {{ ticket.data.length }}</legend>
      <h2>{{ question.text }}</h2>
      <label v-for="(option, optionIndex) in question.options" :key="optionIndex" class="exam-choice">
        <input v-model="selected[question.id]" type="radio" :name="'answer-' + question.id" :value="optionIndex">
        <span>{{ option }}</span>
      </label>
    </fieldset>
    <ErrorNotice :error="error" />
    <div class="inline-form-actions">
      <button class="btn btn-primary" type="submit" :disabled="busy || error?.status === 409">{{ busy ? 'Проверяем…' : 'Проверить ответы' }}</button>
      <button v-if="error?.status === 409" class="btn btn-outline-primary" type="button" @click="load">Загрузить билет заново</button>
    </div>
  </form>
</template>

<style scoped>
.exam-progress { width: min(35%, 190px); accent-color: #294e79; }
</style>
