<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ErrorNotice from '../components/ErrorNotice.vue'
import FormField from '../components/FormField.vue'
import { auth, signIn } from '../services/auth.js'

const route = useRoute()
const router = useRouter()
const register = computed(() => route.name === 'register')
const form = reactive({ name: '', email: '', phone: '', password: '', password_confirmation: '' })
const busy = ref(false)
const error = ref(null)
watch(register, () => {
  error.value = null
  form.password = ''
  form.password_confirmation = ''
})

async function submit() {
  error.value = null
  if (register.value && form.password !== form.password_confirmation) {
    error.value = { message: 'Проверьте введённые данные.', errors: { password_confirmation: ['Пароли должны совпадать.'] } }
    return
  }
  busy.value = true
  try {
    const credentials = { email: form.email, password: form.password }
    await signIn(register.value ? 'register' : 'login', register.value ? form : credentials)
    const redirect = route.query.redirect
    if (typeof redirect === 'string' && redirect.startsWith('/cabinet/') && router.resolve(redirect).matched.some(record => record.meta.cabinet)) {
      await router.replace(redirect)
    } else if (register.value && auth.user.role === 'student' && ['A', 'B', 'C'].includes(route.query.category)) {
      await router.replace({ name: 'groups', query: { category: route.query.category } })
    } else {
      await router.replace({ name: 'dashboard' })
    }
  } catch (problem) {
    error.value = problem
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <section class="auth-page">
    <div class="auth-visual">
      <p>AutoStart</p>
      <h2>Ваше обучение<br>в одном месте</h2>
      <span>Расписание, вождение и подготовка к экзамену ПДД.</span>
    </div>
    <div class="auth-panel">
      <h1>{{ register ? 'Регистрация' : 'Вход в кабинет' }}</h1>
      <p>{{ register ? 'Создайте аккаунт, чтобы подать заявку на обучение.' : 'Войдите с адресом электронной почты и паролем.' }}</p>
      <p v-if="route.query.expired" class="alert alert-warning" role="status">Срок сеанса истёк. Войдите снова.</p>
      <p v-else-if="route.query.offline" class="alert alert-warning" role="status">Не удалось восстановить сеанс. Проверьте подключение и войдите снова.</p>
      <ErrorNotice :error="error" />
      <form class="form-stack" @submit.prevent="submit">
        <FormField v-if="register" v-model="form.name" label="Имя и фамилия" autocomplete="name" :maxlength="100" required :error="error?.errors?.name?.[0]" />
        <FormField v-model="form.email" label="Электронная почта" type="email" autocomplete="email" :maxlength="255" required :error="error?.errors?.email?.[0]" />
        <FormField v-if="register" v-model="form.phone" label="Телефон" type="tel" autocomplete="tel" :maxlength="32" :error="error?.errors?.phone?.[0]" />
        <FormField v-model="form.password" label="Пароль" type="password" :autocomplete="register ? 'new-password' : 'current-password'"
          :minlength="register ? 8 : undefined" :maxlength="72" :hint="register ? 'Минимум 8 символов.' : ''" required :error="error?.errors?.password?.[0]" />
        <FormField v-if="register" v-model="form.password_confirmation" label="Подтверждение пароля" type="password" autocomplete="new-password"
          :maxlength="72" required :error="error?.errors?.password_confirmation?.[0]" />
        <button class="btn btn-primary btn-lg" type="submit" :disabled="busy">
          {{ busy ? 'Подождите…' : register ? 'Создать аккаунт' : 'Войти' }} <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </button>
      </form>
      <p class="auth-switch">
        {{ register ? 'Уже есть аккаунт?' : 'Впервые в AutoStart?' }}
        <RouterLink :to="{ name: register ? 'login' : 'register', query: { redirect: route.query.redirect, category: route.query.category } }">
          {{ register ? 'Войти' : 'Зарегистрироваться' }}
        </RouterLink>
      </p>
    </div>
  </section>
</template>
