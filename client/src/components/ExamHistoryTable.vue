<script setup>
import { date } from '../services/format.js'
import EmptyState from './EmptyState.vue'
import LoadingState from './LoadingState.vue'
import StatusBadge from './StatusBadge.vue'

defineProps({ attempts: Array, loading: Boolean, admin: Boolean })
</script>

<template>
  <LoadingState v-if="loading" />
  <EmptyState v-else-if="!attempts?.length" title="Попыток пока нет" icon="journal-check" />
  <div v-else class="table-responsive">
    <table class="table table-hover data-table">
      <thead><tr><th scope="col">Дата</th><th v-if="admin" scope="col">Курсант</th><th scope="col">Билет</th><th scope="col">Ответы</th><th scope="col">Результат</th><th scope="col">Разбор</th></tr></thead>
      <tbody>
        <tr v-for="row in attempts" :key="row.id">
          <td>{{ date(row.created_at) }}</td>
          <td v-if="admin">{{ row.student?.name }}</td>
          <td>№ {{ row.ticket_number }} <span class="table-subtitle">Категория {{ row.category }}</span></td>
          <td>{{ row.correct_answers }} из {{ row.total_questions }} · {{ row.percent }}%</td>
          <td><StatusBadge :value="row.passed ? 'passed' : 'failed'" :label="row.passed ? 'Сдан' : 'Не сдан'" /></td>
          <td><RouterLink class="btn btn-outline-primary btn-sm" :to="{ name: 'exam-result', params: { id: row.id } }">Открыть</RouterLink></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
