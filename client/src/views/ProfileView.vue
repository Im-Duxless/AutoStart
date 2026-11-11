<script setup>
import { reactive, ref } from 'vue'
import { api } from '../services/api.js'
import { auth } from '../services/auth.js'
import { roles } from '../services/format.js'
import ErrorNotice from '../components/ErrorNotice.vue'
import FormField from '../components/FormField.vue'
import PageHeading from '../components/PageHeading.vue'

const form = reactive({ name: auth.user.name, phone: auth.user.phone || '' })
const error = ref(null)
const busy = ref(false)
const success = ref(false)

async function save() {
  busy.value = true
  error.value = null
  success.value = false
  try {
    const response = await api('/me', { method: 'PATCH', body: form })
    auth.user = response.data
    form.name = auth.user.name
    form.phone = auth.user.phone || ''
    success.value = true
  } catch (problem) {
    error.value = problem
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <PageHeading title="Профиль" text="Обновите имя или номер телефона." />
  <section class="surface surface-pad profile-form">
    <dl class="detail-grid">
      <div><dt>Электронная почта</dt><dd>{{ auth.user.email }}</dd></div>
      <div><dt>Роль</dt><dd>{{ roles[auth.user.role] }}</dd></div>
    </dl>
    <ErrorNotice :error="error" />
    <p v-if="success" class="alert alert-success" role="status">Профиль сохранён.</p>
    <form class="form-stack" @submit.prevent="save">
      <FormField v-model="form.name" label="Имя и фамилия" autocomplete="name" :maxlength="100" required :error="error?.errors?.name?.[0]" />
      <FormField v-model="form.phone" label="Телефон" type="tel" autocomplete="tel" :maxlength="32" :error="error?.errors?.phone?.[0]" />
      <button class="btn btn-primary" type="submit" :disabled="busy">{{ busy ? 'Сохраняем…' : 'Сохранить' }}</button>
    </form>
  </section>
</template>
