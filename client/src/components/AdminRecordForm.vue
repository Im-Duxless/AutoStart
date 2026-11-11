<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../services/api.js'
import { loadOptions } from '../admin/lookups.js'
import ErrorNotice from './ErrorNotice.vue'
import FormField from './FormField.vue'
import LoadingState from './LoadingState.vue'
import QuestionOptions from './QuestionOptions.vue'

const props = defineProps({ config: Object, recordId: Number })
const emit = defineEmits(['close', 'saved'])
const form = reactive({})
const lookups = reactive({})
const loading = ref(true)
const loadError = ref(null)
const error = ref(null)
const busy = ref(false)
const editing = computed(() => props.recordId !== null)
const fields = computed(() => props.config.fields.filter(field => !field.forType || field.forType === form.type))
const optionChoices = computed(() => (form.options || []).map((option, index) => ({ value: index, label: (index + 1) + '. ' + (option || 'Вариант') })))

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const names = [...new Set(props.config.fields.map(field => field.lookup).filter(Boolean))]
    const [record, lists] = await Promise.all([
      editing.value ? api(props.config.path + '/' + props.recordId).then(result => result.data) : null,
      Promise.all(names.map(async name => [name, await loadOptions(name)])),
    ])
    Object.assign(lookups, Object.fromEntries(lists))
    for (const field of props.config.fields) {
      const fallback = typeof field.default === 'function' ? field.default() : field.default
      let value = record?.[field.key] ?? fallback ?? ''
      if (field.type === 'datetime-local' && value) value = value.slice(0, 16)
      form[field.key] = field.key === 'options' && !Array.isArray(value) ? ['', '', ''] : value
    }
  } catch (problem) {
    loadError.value = problem
  } finally {
    loading.value = false
  }
}

function required(field) {
  if (field.createRequired && editing.value) return field.key === 'password_confirmation' && !!form.password
  return field.required
}

function fieldOptions(field) {
  if (field.key === 'correct_option') return optionChoices.value
  if (field.lookup) return lookups[field.lookup] || []
  return field.options
}

function removeAnswer(index) {
  if (form.correct_option === index) form.correct_option = ''
  else if (typeof form.correct_option === 'number' && form.correct_option > index) form.correct_option -= 1
}

async function save() {
  busy.value = true
  error.value = null
  const body = {}
  for (const field of fields.value) {
    if (field.createRequired && editing.value && !form.password) continue
    const value = form[field.key]
    body[field.key] = value === '' ? null : field.step === '0.01' ? String(value) : value
  }
  const url = props.config.path + (editing.value ? '/' + props.recordId : '')
  try {
    await api(url, { method: editing.value ? 'PATCH' : 'POST', body })
    emit('saved', editing.value ? 'Изменения сохранены.' : 'Запись добавлена.')
  } catch (problem) {
    error.value = problem
  } finally {
    busy.value = false
  }
}
onMounted(load)
</script>

<template>
  <section class="surface admin-form">
    <h2>{{ editing ? 'Редактировать запись' : config.create }}</h2>
    <LoadingState v-if="loading" />
    <div v-else-if="loadError">
      <ErrorNotice :error="loadError" />
      <div class="inline-form-actions">
        <button class="btn btn-primary" type="button" @click="load">Повторить</button>
        <button class="btn btn-outline-primary" type="button" @click="emit('close')">Закрыть</button>
      </div>
    </div>
    <form v-else class="form-stack" @submit.prevent="save">
      <ErrorNotice :error="error" />
      <div class="field-grid">
        <template v-for="field in fields" :key="field.key">
          <QuestionOptions v-if="field.key === 'options'" v-model="form.options" class="field-wide" :errors="error?.errors" @removed="removeAnswer" />
          <FormField v-else v-model="form[field.key]" :class="{ 'field-wide': field.wide }" :label="field.label" :type="field.type"
            :options="fieldOptions(field)" :required="required(field)" :disabled="busy || (field.lockedOnEdit && editing)"
            :min="field.min" :max="typeof field.max === 'function' ? field.max() : field.max" :step="field.step"
            :maxlength="field.maxlength" :minlength="field.minlength" :autocomplete="field.autocomplete"
            :hint="field.key === 'password' && editing ? 'Заполните, чтобы сменить пароль.' : undefined"
            :error="error?.errors?.[field.key]?.[0]" />
        </template>
      </div>
      <div class="inline-form-actions">
        <button class="btn btn-primary" type="submit" :disabled="busy">{{ busy ? 'Сохраняем…' : 'Сохранить' }}</button>
        <button class="btn btn-outline-primary" type="button" :disabled="busy" @click="emit('close')">Закрыть</button>
      </div>
    </form>
  </section>
</template>
