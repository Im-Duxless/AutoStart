<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api, getAll } from '../services/api.js'
import { date, money } from '../services/format.js'
import { useList } from '../services/useList.js'
import { school } from '../services/school.js'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import EmptyState from '../components/EmptyState.vue'
import ErrorNotice from '../components/ErrorNotice.vue'
import FilterBar from '../components/FilterBar.vue'
import LoadingState from '../components/LoadingState.vue'
import PageHeading from '../components/PageHeading.vue'
import PaginationBar from '../components/PaginationBar.vue'
import StatusBadge from '../components/StatusBadge.vue'

const route = useRoute()
const selectedCategory = () => ['A', 'B', 'C'].includes(route.query.category) ? route.query.category : ''
const { rows, filters, meta, loading, error, load, reload } = useList('/study-groups', { category: selectedCategory(), status: 'open', search: '' })
const tab = ref('groups')
const applications = ref([])
const applicationError = ref(null)
const applicationLoading = ref(false)
const actionError = ref(null)
const success = ref('')
const selection = ref(null)
const busy = ref(false)

const fields = computed(() => [
  { key: 'category', label: 'Категория', options: (school.data?.categories || []).map(category => ({ value: category.code, label: category.code + ' · ' + category.label })) },
  { key: 'search', label: 'Поиск группы', placeholder: 'Название группы' },
])
const byGroup = computed(() => Object.fromEntries(applications.value.map(item => [item.study_group_id, item.status])))

async function loadApplications() {
  applicationLoading.value = true
  applicationError.value = null
  try {
    applications.value = await getAll('/enrollments')
  } catch (problem) {
    applicationError.value = problem
  } finally {
    applicationLoading.value = false
  }
}

function reset() {
  filters.category = ''
  filters.search = ''
  load()
}

async function apply() {
  busy.value = true
  actionError.value = null
  success.value = ''
  try {
    await api('/study-groups/' + selection.value.id + '/enroll', { method: 'POST' })
    success.value = 'Заявка отправлена. Статус доступен во вкладке «Мои заявки».'
    await Promise.all([loadApplications(), reload()])
  } catch (problem) {
    actionError.value = problem
  } finally {
    selection.value = null
    busy.value = false
  }
}

watch(() => route.query.category, () => { filters.category = selectedCategory(); load() })
onMounted(() => Promise.all([load(), loadApplications()]))
</script>

<template>
  <PageHeading title="Обучение" text="Выберите группу по категории и дате начала." />
  <div class="tab-list" aria-label="Разделы обучения">
    <button type="button" :aria-pressed="tab === 'groups'" @click="tab = 'groups'">Открытые группы</button>
    <button type="button" :aria-pressed="tab === 'applications'" @click="tab = 'applications'; loadApplications()">Мои заявки</button>
  </div>
  <ErrorNotice :error="actionError" />
  <p v-if="success" class="alert alert-success" role="status">{{ success }}</p>

  <section v-if="tab === 'groups'" class="surface">
    <FilterBar v-model="filters" :fields="fields" :busy="loading" @apply="load()" @reset="reset" />
    <ErrorNotice :error="error" class="m-3" />
    <LoadingState v-if="loading" />
    <EmptyState v-else-if="!rows.length" title="Группы по выбранным условиям не найдены" icon="mortarboard" />
    <div v-else class="group-grid">
      <article v-for="group in rows" :key="group.id" class="group-card">
        <div class="card-top-line"><span class="category-tile">{{ group.category }}</span><StatusBadge :value="byGroup[group.id] || group.status" /></div>
        <h2>{{ group.name }}</h2>
        <p><i class="bi bi-calendar3" aria-hidden="true"></i> Начало {{ date(group.start_date) }}</p>
        <p>Свободных мест: {{ Math.max(0, group.capacity - group.approved_count) }}</p>
        <p v-if="group.tuition !== null">Стоимость: <strong>{{ money(group.tuition) }}</strong></p>
        <button v-if="!['pending', 'approved'].includes(byGroup[group.id]) && group.approved_count < group.capacity"
          type="button" class="btn btn-primary btn-sm" :disabled="busy || applicationLoading" @click="selection = group">Подать заявку</button>
        <RouterLink v-else-if="byGroup[group.id] === 'approved'" :to="{ name: 'theory' }" class="btn btn-outline-primary btn-sm">Расписание</RouterLink>
      </article>
    </div>
    <PaginationBar :meta="meta" :disabled="loading" @change="load" />
  </section>

  <section v-else class="surface">
    <ErrorNotice :error="applicationError" class="m-3" />
    <LoadingState v-if="applicationLoading" />
    <EmptyState v-else-if="!applications.length" title="Вы ещё не подавали заявку" text="Откройте список групп и выберите подходящую." icon="inbox" />
    <div v-else class="table-responsive">
      <table class="table data-table">
        <thead><tr><th scope="col">Группа</th><th scope="col">Категория</th><th scope="col">Обновлена</th><th scope="col">Статус</th></tr></thead>
        <tbody><tr v-for="row in applications" :key="row.id"><td><strong>{{ row.group_name }}</strong></td><td>{{ row.category }}</td><td>{{ date(row.updated_at) }}</td><td><StatusBadge :value="row.status" /></td></tr></tbody>
      </table>
    </div>
  </section>

  <ConfirmDialog :open="!!selection" title="Подать заявку?" :text="selection?.name" label="Отправить" :busy="busy" @confirm="apply" @close="selection = null" />
</template>
