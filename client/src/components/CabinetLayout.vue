<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Brand from './Brand.vue'
import ErrorNotice from './ErrorNotice.vue'
import { auth, signOut } from '../services/auth.js'
import { initials, roles } from '../services/format.js'

const route = useRoute()
const router = useRouter()
const open = ref(false)
const leaving = ref(false)
const error = ref(null)
watch(() => route.fullPath, () => { open.value = false })

const studentLinks = [
  ['groups', 'mortarboard', 'Обучение'], ['theory', 'calendar-week', 'Расписание'],
  ['driving', 'car-front', 'Вождение'], ['exams', 'journal-check', 'Экзамены ПДД'], ['payments', 'wallet2', 'Оплата'],
]
const adminLinks = [
  ['enrollments', 'inbox', 'Заявки'], ['users', 'people', 'Пользователи'],
  ['study-groups', 'mortarboard', 'Учебные группы'], ['cars', 'car-front', 'Транспорт'],
  ['lessons', 'calendar-week', 'Занятия'], ['questions', 'patch-question', 'Вопросы ПДД'],
  ['exam-results', 'journal-check', 'Результаты ПДД'], ['payments', 'wallet2', 'Платежи'],
  ['instructor-report', 'bar-chart-line', 'Отчёт по инструкторам'],
]
const links = computed(() => {
  const role = auth.user?.role
  const items = [{ to: { name: 'dashboard' }, icon: 'grid-1x2', label: 'Обзор' }]
  const links = role === 'admin' ? adminLinks : role === 'instructor'
    ? [['instructor-lessons', 'calendar-week', 'Мои занятия']] : studentLinks
  for (const [name, icon, label] of links) {
    const to = role === 'admin' ? { name: 'admin-section', params: { section: name } } : { name }
    items.push({ to, icon, label })
  }
  items.push({ to: { name: 'profile' }, icon: 'person-circle', label: 'Профиль' })
  return items
})

async function logout() {
  leaving.value = true
  error.value = null
  try {
    await signOut()
    await router.push({ name: 'login' })
  } catch (problem) {
    if (problem.status !== 401) error.value = problem
  } finally {
    leaving.value = false
  }
}
</script>

<template>
  <div class="cabinet-layout">
    <aside id="cabinet-nav" class="cabinet-sidebar" :class="{ 'is-open': open }">
      <Brand />
      <nav aria-label="Разделы кабинета">
        <RouterLink v-for="item in links" :key="item.label" :to="item.to" exact-active-class="active" @click="open = false">
          <i :class="'bi bi-' + item.icon" aria-hidden="true"></i>{{ item.label }}
        </RouterLink>
      </nav>
      <RouterLink class="sidebar-public" :to="{ name: 'home' }"><i class="bi bi-arrow-up-right" aria-hidden="true"></i> На сайт</RouterLink>
    </aside>
    <div class="cabinet-workspace">
      <header class="cabinet-header">
        <button type="button" class="btn cabinet-menu" aria-label="Меню кабинета" aria-controls="cabinet-nav" :aria-expanded="open" @click="open = !open">
          <i :class="open ? 'bi bi-x-lg' : 'bi bi-list'" aria-hidden="true"></i>
        </button>
        <span class="cabinet-header-title">Личный кабинет</span>
        <div v-if="auth.user" class="cabinet-user">
          <span class="avatar">{{ initials(auth.user.name) }}</span>
          <span class="cabinet-identity"><strong>{{ auth.user.name }}</strong><small>{{ roles[auth.user.role] }}</small></span>
        </div>
        <button class="btn btn-outline-primary logout" type="button" :disabled="leaving" @click="logout">
          <i class="bi bi-box-arrow-right" aria-hidden="true"></i> {{ leaving ? 'Выходим…' : 'Выйти' }}
        </button>
      </header>
      <main id="content" class="cabinet-content">
        <ErrorNotice :error="error" />
        <slot />
      </main>
    </div>
  </div>
</template>
