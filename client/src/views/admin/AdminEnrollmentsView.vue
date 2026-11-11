<script setup>
import { onMounted, reactive, ref } from 'vue'
import { categories } from '../../admin/tables.js'
import { loadOptions } from '../../admin/lookups.js'
import { api } from '../../services/api.js'
import { date, options, statuses } from '../../services/format.js'
import { useList } from '../../services/useList.js'
import ConfirmDialog from '../../components/ConfirmDialog.vue'
import EmptyState from '../../components/EmptyState.vue'
import ErrorNotice from '../../components/ErrorNotice.vue'
import FilterBar from '../../components/FilterBar.vue'
import FormField from '../../components/FormField.vue'
import LoadingState from '../../components/LoadingState.vue'
import PageHeading from '../../components/PageHeading.vue'
import PaginationBar from '../../components/PaginationBar.vue'
import StatusBadge from '../../components/StatusBadge.vue'

const defaults = { search: '', category: '', status: '' }
const { rows, filters, meta, loading, error, load, reload } = useList('/enrollments', defaults)
const fieldOptions = [
  { key: 'search', label: 'Поиск', placeholder: 'Курсант или группа' },
  { key: 'category', label: 'Категория', options: categories },
  { key: 'status', label: 'Статус', options: options({ pending: statuses.pending, approved: statuses.approved, rejected: statuses.rejected }) },
]
const decision = ref(null)
const actionError = ref(null)
const success = ref('')
const busy = ref(false)
const showForm = ref(false)
const lookupBusy = ref(false)
const students = ref([])
const groups = ref([])
const form = reactive({ user_id: '', study_group_id: '' })

async function openForm() {
  showForm.value = true
  lookupBusy.value = true
  actionError.value = null
  success.value = ''
  try {
    const [studentOptions, groupOptions] = await Promise.all([loadOptions('students'), loadOptions('openGroups')])
    students.value = studentOptions
    groups.value = groupOptions
  } catch (problem) {
    actionError.value = problem
  } finally {
    lookupBusy.value = false
  }
}

async function add() {
  busy.value = true
  actionError.value = null
  try {
    await api('/enrollments', { method: 'POST', body: form })
    Object.assign(form, { user_id: '', study_group_id: '' })
    showForm.value = false
    success.value = 'Заявка добавлена. Теперь можно принять решение о зачислении.'
    await load()
  } catch (problem) {
    actionError.value = problem
  } finally {
    busy.value = false
  }
}

async function decide() {
  const { row, status } = decision.value
  busy.value = true
  actionError.value = null
  success.value = ''
  try {
    await api('/enrollments/' + row.id, { method: 'PATCH', body: { status } })
    success.value = status === 'approved' ? 'Курсант зачислен в группу.' : 'Заявка отклонена.'
    await reload()
  } catch (problem) {
    actionError.value = problem
  } finally {
    busy.value = false
    decision.value = null
  }
}

function reset() {
  Object.assign(filters, defaults)
  load()
}

onMounted(() => load())
</script>

<template>
  <PageHeading title="Заявки на обучение" text="Рассмотрите заявки и зачислите курсантов в подходящие группы.">
    <button class="btn btn-accent" type="button" :disabled="showForm" @click="openForm"><i class="bi bi-plus-lg" aria-hidden="true"></i> Добавить заявку</button>
  </PageHeading>
  <ErrorNotice v-if="!showForm" :error="actionError" />
  <p v-if="success" class="alert alert-success" role="status">{{ success }}</p>
  <section v-if="showForm" class="surface surface-pad">
    <h2 class="h5">Новая заявка</h2>
    <ErrorNotice :error="actionError" />
    <LoadingState v-if="lookupBusy" />
    <form v-else class="form-stack" @submit.prevent="add">
      <div class="field-grid">
        <FormField v-model="form.user_id" label="Курсант" :options="students" required :error="actionError?.errors?.user_id?.[0]" />
        <FormField v-model="form.study_group_id" label="Группа" :options="groups" required :error="actionError?.errors?.study_group_id?.[0]" />
      </div>
      <div class="inline-form-actions">
        <button class="btn btn-primary" type="submit" :disabled="busy || !students.length || !groups.length">{{ busy ? 'Сохраняем…' : 'Создать заявку' }}</button>
        <button class="btn btn-outline-primary" type="button" :disabled="busy" @click="showForm = false">Закрыть</button>
        <button v-if="actionError && (!students.length || !groups.length)" class="btn btn-outline-primary" type="button" @click="openForm">Повторить загрузку</button>
      </div>
      <p v-if="!actionError && (!students.length || !groups.length)" class="mb-0">Для заявки нужны курсант и открытая учебная группа.</p>
    </form>
  </section>

  <section class="surface">
    <FilterBar v-model="filters" :fields="fieldOptions" :busy="loading" @apply="load()" @reset="reset" />
    <ErrorNotice :error="error" class="m-3" />
    <LoadingState v-if="loading" />
    <EmptyState v-else-if="!rows.length" title="Заявки по выбранным условиям не найдены" />
    <div v-else class="table-responsive">
      <table class="table table-hover data-table">
        <thead><tr><th scope="col">Курсант</th><th scope="col">Группа</th><th scope="col">Категория</th><th scope="col">Дата подачи</th><th scope="col">Статус</th><th scope="col">Решение</th></tr></thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td><strong>{{ row.student_name }}</strong></td><td>{{ row.group_name }}</td><td>{{ row.category }}</td>
            <td>{{ date(row.created_at) }}</td><td><StatusBadge :value="row.status" /></td>
            <td>
              <div v-if="row.status === 'pending'" class="row-actions">
                <button class="btn btn-primary btn-sm" type="button" :disabled="showForm" @click="decision = { row, status: 'approved' }">Зачислить</button>
                <button class="btn btn-outline-danger btn-sm" type="button" :disabled="showForm" @click="decision = { row, status: 'rejected' }">Отклонить</button>
              </div>
              <span v-else>—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <PaginationBar :meta="meta" :disabled="loading" @change="load" />
  </section>
  <ConfirmDialog :open="!!decision" :title="decision?.status === 'approved' ? 'Зачислить курсанта?' : 'Отклонить заявку?'"
    :text="decision ? decision.row.student_name + ' · ' + decision.row.group_name : ''" :label="decision?.status === 'approved' ? 'Зачислить' : 'Отклонить'"
    :danger="decision?.status === 'rejected'" :busy="busy" @close="decision = null" @confirm="decide" />
</template>
