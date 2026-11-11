<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import Brand from './Brand.vue'
import { auth } from '../services/auth.js'

const open = ref(false)
const route = useRoute()
watch(() => route.fullPath, () => { open.value = false })

const links = [
  ['#programs', 'Программы'], ['#how', 'Как начать'], ['#instructors', 'Инструкторы'], ['#contacts', 'Контакты'],
]
</script>

<template>
  <header class="site-header">
    <div class="site-container header-inner">
      <Brand />
      <button class="btn menu-toggle" type="button" aria-label="Меню сайта" aria-controls="site-navigation" :aria-expanded="open" @click="open = !open">
        <i :class="open ? 'bi bi-x-lg' : 'bi bi-list'" aria-hidden="true"></i>
      </button>
      <nav id="site-navigation" class="public-nav" :class="{ 'is-open': open }" aria-label="Главное меню">
        <RouterLink v-for="[hash, label] in links" :key="hash" :to="{ name: 'home', hash }" @click="open = false">{{ label }}</RouterLink>
        <RouterLink class="btn btn-outline-primary account-link" :to="{ name: auth.user ? 'dashboard' : 'login' }">
          <i class="bi bi-person-circle" aria-hidden="true"></i> Личный кабинет
        </RouterLink>
      </nav>
    </div>
  </header>
</template>
