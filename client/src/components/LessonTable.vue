<script setup>
import { date, time, carName, lessonTypes } from '../services/format.js'
import EmptyState from './EmptyState.vue'
import LoadingState from './LoadingState.vue'
import StatusBadge from './StatusBadge.vue'

defineProps({
  lessons: { type: Array, default: () => [] }, loading: Boolean, staff: Boolean,
  emptyTitle: { type: String, default: 'Занятий пока нет' }, emptyText: String,
})
</script>

<template>
  <LoadingState v-if="loading" text="Загружаем занятия…" />
  <EmptyState v-else-if="!lessons.length" :title="emptyTitle" :text="emptyText" icon="calendar-week"><slot name="empty" /></EmptyState>
  <div v-else class="table-responsive">
    <table class="table table-hover data-table">
      <thead><tr><th scope="col">Дата и время</th><th scope="col">Занятие</th><th scope="col">{{ staff ? 'Курсант' : 'Инструктор' }}</th><th scope="col">Статус</th><th scope="col">Действия</th></tr></thead>
      <tbody>
        <tr v-for="lesson in lessons" :key="lesson.id">
          <td><strong>{{ date(lesson.start_at, true) }}</strong><span class="table-subtitle">{{ time(lesson.start_at) }}–{{ time(lesson.end_at) }}</span></td>
          <td>{{ lessonTypes[lesson.type] }}<span class="table-subtitle">{{ lesson.study_group?.name || carName(lesson.car) }}</span></td>
          <td>{{ (staff ? lesson.student?.name : lesson.instructor?.name) || '—' }}</td>
          <td><StatusBadge :value="lesson.status" /></td>
          <td><RouterLink class="btn btn-outline-primary btn-sm" :to="{ name: 'lesson', params: { id: lesson.id } }">Открыть</RouterLink></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
