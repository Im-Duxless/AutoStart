<script setup>
import { computed } from 'vue'
import hero from '../assets/driving-course.webp'
import ErrorNotice from '../components/ErrorNotice.vue'
import LoadingState from '../components/LoadingState.vue'
import { auth } from '../services/auth.js'
import { date, initials, money } from '../services/format.js'
import { loadSchool, school } from '../services/school.js'

const categories = {
  A: { icon: 'speedometer2', text: 'Управление мотоциклом, маневрирование на площадке и подготовка к выезду на дорогу.' },
  B: { icon: 'car-front-fill', text: 'От первых упражнений на площадке до самостоятельных решений в городском потоке.' },
  C: { icon: 'truck', text: 'Габариты, манёвры и безопасное управление грузовым транспортом.' },
}
const benefits = [
  { icon: 'calendar2-week', title: 'Теория по расписанию', text: 'Даты занятий всегда под рукой' },
  { icon: 'car-front', title: 'Вождение в удобное время', text: 'Выбор инструктора и свободного слота' },
  { icon: 'journal-check', title: 'Практика с билетами ПДД', text: 'Тренажёр с разбором ответов' },
]
const steps = [
  ['Выберите программу', 'Определите категорию прав и найдите группу с подходящей датой начала.'],
  ['Подайте заявку', 'Зарегистрируйтесь и отправьте заявку. После зачисления откроется расписание группы.'],
  ['Приходите на занятия', 'Посещайте теорию, записывайтесь на вождение и решайте билеты ПДД.'],
]
const contacts = computed(() => school.data?.contacts || {})

function enrollmentLink(category) {
  if (auth.user?.role === 'student') return { name: 'groups', query: category ? { category } : {} }
  if (auth.user) return { name: 'dashboard' }
  return { name: 'register', query: category ? { category } : {} }
}
</script>

<template>
  <section class="hero" aria-labelledby="hero-title">
    <img class="hero-image" :src="hero" alt="" fetchpriority="high">
    <div class="hero-shade"></div>
    <div class="site-container hero-inner">
      <p class="hero-kicker"><span></span> Автошкола для будущих водителей</p>
      <h1 id="hero-title">Уверенность<br class="hero-break"> за рулём<br class="hero-break"> <em>начинается здесь</em></h1>
      <p class="hero-copy">Выберите категорию, освоите теорию и наберитесь опыта на дороге с инструктором.</p>
      <div class="hero-actions">
        <RouterLink class="btn btn-accent btn-lg" :to="enrollmentLink()">
          Записаться на обучение <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </RouterLink>
        <RouterLink class="hero-secondary" :to="{ name: 'home', hash: '#programs' }">
          Программы <i class="bi bi-arrow-down" aria-hidden="true"></i>
        </RouterLink>
      </div>
    </div>
  </section>

  <section class="benefits-band" aria-label="Преимущества обучения">
    <div class="site-container benefits-grid">
      <article v-for="benefit in benefits" :key="benefit.title" class="benefit">
        <span class="benefit-icon"><i :class="'bi bi-' + benefit.icon" aria-hidden="true"></i></span>
        <div><h2>{{ benefit.title }}</h2><p>{{ benefit.text }}</p></div>
      </article>
    </div>
  </section>

  <section id="programs" class="site-section programs">
    <div class="site-container">
      <div class="site-heading">
        <h2>Программы обучения</h2>
        <p>Выберите категорию водительских прав.</p>
      </div>
      <LoadingState v-if="school.loading" text="Загружаем программы…" />
      <template v-else-if="school.error">
        <ErrorNotice :error="school.error" />
        <button type="button" class="btn btn-outline-primary" @click="loadSchool">Повторить</button>
      </template>
      <div v-else class="program-grid">
        <article v-for="category in school.data.categories" :key="category.code" class="program-card">
          <div class="program-top">
            <span class="category-tile">{{ category.code }}</span>
            <i :class="'bi bi-' + categories[category.code]?.icon" aria-hidden="true"></i>
          </div>
          <h3>{{ category.label }}</h3>
          <p>{{ categories[category.code]?.text }}</p>
          <div class="program-start" v-if="category.next_start">
            <span><i class="bi bi-calendar3" aria-hidden="true"></i> Старт {{ date(category.next_start, true) }}</span>
            <strong v-if="category.tuition !== null">{{ money(category.tuition) }}</strong>
          </div>
          <div class="program-start" v-else>Открытых групп пока нет</div>
          <RouterLink class="program-link" :to="enrollmentLink(category.code)">
            Смотреть группы <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
          </RouterLink>
        </article>
      </div>
    </div>
  </section>

  <section id="how" class="site-section how-section">
    <div class="site-container how-grid">
      <div class="site-heading">
        <h2>Как начать обучение</h2>
        <p>От выбора группы до первого занятия.</p>
        <RouterLink class="btn btn-accent" :to="enrollmentLink()">Записаться на обучение <i class="bi bi-arrow-right" aria-hidden="true"></i></RouterLink>
      </div>
      <ol class="step-list">
        <li v-for="([title, text], index) in steps" :key="title">
          <span class="step-number">{{ index + 1 }}</span>
          <div><h3>{{ title }}</h3><p>{{ text }}</p></div>
        </li>
      </ol>
    </div>
  </section>

  <section id="instructors" class="site-section instructors-section">
    <div class="site-container">
      <div class="site-heading"><h2>Инструкторы</h2><p>Выберите инструктора при записи на вождение.</p></div>
      <LoadingState v-if="school.loading" />
      <ErrorNotice v-else-if="school.error" :error="school.error" />
      <p v-else-if="!school.data.instructors.length">Инструкторы появятся здесь после добавления в расписание школы.</p>
      <div v-else class="instructor-grid">
        <article v-for="instructor in school.data.instructors" :key="instructor.id" class="instructor-card">
          <span class="avatar">{{ initials(instructor.name) }}</span>
          <div><h3>{{ instructor.name }}</h3><p>Инструктор по вождению</p></div>
        </article>
      </div>
    </div>
  </section>

  <section id="contacts" class="contacts-section">
    <div class="site-container contacts-inner">
      <div><h2>Контакты</h2><p>Заявки на обучение принимаются через личный кабинет.</p></div>
      <ul v-if="contacts.phone || contacts.email || contacts.address || contacts.hours" class="contact-list">
        <li v-if="contacts.phone"><i class="bi bi-telephone" aria-hidden="true"></i> <a :href="'tel:' + contacts.phone.replace(/[^0-9+]/g, '')">{{ contacts.phone }}</a></li>
        <li v-if="contacts.email"><i class="bi bi-envelope" aria-hidden="true"></i> <a :href="'mailto:' + contacts.email">{{ contacts.email }}</a></li>
        <li v-if="contacts.address"><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ contacts.address }}</li>
        <li v-if="contacts.hours"><i class="bi bi-clock" aria-hidden="true"></i> {{ contacts.hours }}</li>
      </ul>
      <RouterLink class="btn btn-accent" :to="enrollmentLink()">Подать заявку <i class="bi bi-arrow-up-right" aria-hidden="true"></i></RouterLink>
    </div>
  </section>
</template>
