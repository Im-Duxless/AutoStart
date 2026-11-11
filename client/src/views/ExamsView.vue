<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../services/api.js'
import { useList } from '../services/useList.js'
import EmptyState from '../components/EmptyState.vue'
import ErrorNotice from '../components/ErrorNotice.vue'
import ExamHistoryTable from '../components/ExamHistoryTable.vue'
import LoadingState from '../components/LoadingState.vue'
import PageHeading from '../components/PageHeading.vue'
import PaginationBar from '../components/PaginationBar.vue'

const tickets = ref([])
const passPercent = ref(null)
const busy = ref(false)
const error = ref(null)
const tab = ref('tickets')
const category = ref('')
const history = useList('/exams/history')
const { rows, meta, loading, error: historyError, load } = history

async function loadTickets() {
  busy.value = true
  error.value = null
  try {
    const response = await api('/tickets', { params: { category: category.value } })
    tickets.value = response.data
    passPercent.value = response.pass_percent
  } catch (problem) {
    error.value = problem
  } finally {
    busy.value = false
  }
}

function select(value) {
  tab.value = value
  if (value === 'history') load()
}

onMounted(loadTickets)
</script>

<template>
  <PageHeading title="Экзамены ПДД" :text="passPercent ? 'Решайте билеты и разбирайте ошибки. Для зачёта нужно набрать ' + passPercent + '% правильных ответов.' : 'Решайте билеты и разбирайте ошибки.'" />
  <div class="tab-list" aria-label="Разделы экзаменов">
    <button type="button" :aria-pressed="tab === 'tickets'" @click="select('tickets')">Билеты</button>
    <button type="button" :aria-pressed="tab === 'history'" @click="select('history')">История попыток</button>
  </div>
  <section v-if="tab === 'tickets'" class="surface">
    <div class="filter-bar">
      <div class="filter-field"><label for="exam-category" class="form-label">Категория</label>
        <select id="exam-category" v-model="category" class="form-select" :disabled="busy" @change="loadTickets">
          <option value="">Все</option><option value="A">A</option><option value="B">B</option><option value="C">C</option>
        </select>
      </div>
    </div>
    <LoadingState v-if="busy" />
    <div v-else-if="error" class="surface-pad">
      <ErrorNotice :error="error" />
      <button class="btn btn-outline-primary" type="button" @click="loadTickets">Повторить</button>
    </div>
    <EmptyState v-else-if="!tickets.length" title="Билеты для этой категории ещё не добавлены" icon="journal-check" />
    <div v-else class="ticket-grid">
      <article v-for="ticket in tickets" :key="ticket.category + '-' + ticket.ticket_number" class="ticket-card">
        <div class="card-top-line"><span class="category-tile">{{ ticket.category }}</span><i class="bi bi-journal-text" aria-hidden="true"></i></div>
        <h2>Билет № {{ ticket.ticket_number }}</h2>
        <p>Вопросов в билете: {{ ticket.question_count }}</p>
        <RouterLink :to="{ name: 'ticket', params: { category: ticket.category, number: ticket.ticket_number } }" class="btn btn-primary btn-sm">Начать билет <i class="bi bi-arrow-right" aria-hidden="true"></i></RouterLink>
      </article>
    </div>
  </section>
  <section v-else class="surface">
    <ErrorNotice :error="historyError" class="m-3" />
    <ExamHistoryTable :attempts="rows" :loading="loading" />
    <PaginationBar :meta="meta" :disabled="loading" @change="load" />
  </section>
</template>
