import { createRouter, createWebHistory } from 'vue-router'
import { auth, clearSession, restoreUser } from '../services/auth.js'

const student = { cabinet: true, roles: ['student'] }
const instructor = { cabinet: true, roles: ['instructor'] }
const admin = { cabinet: true, roles: ['admin'] }

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: () => import('../views/HomeView.vue'), meta: { title: 'Автошкола' } },
    { path: '/login', name: 'login', component: () => import('../views/AuthView.vue'), meta: { guest: true, title: 'Вход' } },
    { path: '/register', name: 'register', component: () => import('../views/AuthView.vue'), meta: { guest: true, title: 'Регистрация' } },
    { path: '/cabinet', name: 'dashboard', component: () => import('../views/DashboardView.vue'), meta: { cabinet: true, title: 'Личный кабинет' } },
    { path: '/cabinet/learning', name: 'groups', component: () => import('../views/GroupsView.vue'), meta: { ...student, title: 'Обучение' } },
    { path: '/cabinet/theory', name: 'theory', component: () => import('../views/TheoryView.vue'), meta: { ...student, title: 'Расписание' } },
    { path: '/cabinet/driving', name: 'driving', component: () => import('../views/DrivingView.vue'), meta: { ...student, title: 'Вождение' } },
    { path: '/cabinet/exams', name: 'exams', component: () => import('../views/ExamsView.vue'), meta: { ...student, title: 'Экзамены ПДД' } },
    { path: '/cabinet/exams/:category([ABC])/:number(\\d+)', name: 'ticket', component: () => import('../views/TicketView.vue'), meta: { ...student, title: 'Билет ПДД' } },
    { path: '/cabinet/exams/result/:id(\\d+)', name: 'exam-result', component: () => import('../views/ExamResultView.vue'), meta: { cabinet: true, roles: ['admin', 'student'], title: 'Результат экзамена' } },
    { path: '/cabinet/payments', name: 'payments', component: () => import('../views/PaymentsView.vue'), meta: { ...student, title: 'Оплата' } },
    { path: '/cabinet/lessons/:id(\\d+)', name: 'lesson', component: () => import('../views/LessonDetailView.vue'), meta: { cabinet: true, title: 'Занятие' } },
    { path: '/cabinet/instructor/lessons', name: 'instructor-lessons', component: () => import('../views/InstructorLessonsView.vue'), meta: { ...instructor, title: 'Мои занятия' } },
    { path: '/cabinet/admin/:section(enrollments|users|study-groups|cars|lessons|questions|exam-results|payments|instructor-report)', name: 'admin-section', component: () => import('../views/AdminView.vue'), meta: { ...admin, title: 'Управление' } },
    { path: '/cabinet/profile', name: 'profile', component: () => import('../views/ProfileView.vue'), meta: { cabinet: true, title: 'Профиль' } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../views/NotFoundView.vue'), meta: { title: 'Страница не найдена' } },
  ],
  scrollBehavior(to, from, saved) {
    if (saved) return saved
    if (to.hash) return { el: to.hash, top: 18, behavior: 'smooth' }
    return { top: 0 }
  },
})

router.beforeEach(async to => {
  let offline = false
  try {
    await restoreUser()
  } catch (error) {
    offline = error.status !== 401
  }
  if (to.meta.cabinet && !auth.user) {
    return { name: 'login', query: { redirect: to.fullPath, ...(offline ? { offline: '1' } : {}) } }
  }
  if (to.meta.roles && !to.meta.roles.includes(auth.user?.role)) return { name: 'dashboard' }
  if (to.meta.guest && auth.user) return { name: 'dashboard' }
})

router.afterEach(to => {
  document.title = 'AutoStart — ' + to.meta.title
})

window.addEventListener('autostart:expired', () => {
  clearSession()
  const current = router.currentRoute.value
  if (current.meta.cabinet) {
    router.replace({ name: 'login', query: { redirect: current.fullPath, expired: '1' } })
  }
})

export default router
