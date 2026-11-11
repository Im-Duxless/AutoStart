<script setup>
import Brand from './Brand.vue'
import { auth } from '../services/auth.js'

const year = new Date().getFullYear()
</script>

<template>
  <footer class="site-footer">
    <div class="site-container footer-inner">
      <Brand />
      <nav aria-label="Навигация в подвале">
        <RouterLink :to="{ name: 'home', hash: '#programs' }">Программы</RouterLink>
        <RouterLink :to="{ name: 'home', hash: '#contacts' }">Контакты</RouterLink>
        <RouterLink :to="{ name: auth.user ? 'dashboard' : 'login' }">Личный кабинет</RouterLink>
      </nav>
      <span>© {{ year }} AutoStart</span>
    </div>
  </footer>
</template>
