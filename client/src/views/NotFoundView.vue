<script setup>
import EmptyState from '../components/EmptyState.vue'
</script>

<template>
  <div class="site-container site-section">
    <h1 class="text-center">Страница не найдена</h1>
    <EmptyState title="Проверьте адрес страницы" icon="sign-turn-right">
      <RouterLink class="btn btn-primary" :to="{ name: 'home' }">На главную</RouterLink>
    </EmptyState>
  </div>
</template>
