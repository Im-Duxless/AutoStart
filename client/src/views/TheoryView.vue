<script setup>
import { onMounted, ref, computed } from 'vue'
import { getAll } from '../services/api.js'
import { useList } from '../services/useList.js'
import EmptyState from '../components/EmptyState.vue'
import ErrorNotice from '../components/ErrorNotice.vue'
import FilterBar from '../components/FilterBar.vue'
import LessonTable from '../components/LessonTable.vue'
import LoadingState from '../components/LoadingState.vue'
import PageHeading from '../components/PageHeading.vue'
import PaginationBar from '../components/PaginationBar.vue'

const { filters, rows, meta, error, loading, load } = useList('/lessons', { type: 'theory', mode: 'mine', study_group_id: '', from: '', to: '' })
const groups = ref([])
const groupError = ref(null)
const groupBusy = ref(true)
const fields = computed(() => [
  { key: 'study_group_id', label: 'Группа', options: groups.value.map(group => ({ value: group.study_group_id, label: group.group_name })) },
  { key: 'from', label: 'Дата с', type: 'date' },
  { key: 'to', label: 'Дата по', type: 'date' },
])

async function start() {
  groupBusy.value = true
  groupError.value = null
  try {
    groups.value = await getAll('/enrollments', { status: 'approved' })
    if (groups.value.length) await load()
  } catch (problem) {
    groupError.value = problem
  } finally {
    groupBusy.value = false
  }
}

function reset() {
  Object.assign(filters, { study_group_id: '', from: '', to: '' })
  load()
}

onMounted(start)
</script>

<template>
  <PageHeading title="Расписание" text="Теоретические занятия ваших учебных групп." />
  <section class="surface">
    <LoadingState v-if="groupBusy" />
    <div v-else-if="groupError" class="surface-pad">
      <ErrorNotice :error="groupError" />
      <button class="btn btn-outline-primary" type="button" @click="start">Повторить</button>
    </div>
    <EmptyState v-else-if="!groups.length" title="Расписание откроется после зачисления" text="Подайте заявку и дождитесь подтверждения администратора." icon="calendar-week">
      <RouterLink class="btn btn-primary" :to="{ name: 'groups' }">Учебные группы</RouterLink>
    </EmptyState>
    <template v-else>
      <FilterBar v-model="filters" :fields="fields" :busy="loading" @apply="load()" @reset="reset" />
      <ErrorNotice :error="error" class="m-3" />
      <LessonTable :lessons="rows" :loading="loading" empty-title="Теории по выбранным датам нет" />
      <PaginationBar :meta="meta" :disabled="loading" @change="load" />
    </template>
  </section>
</template>
