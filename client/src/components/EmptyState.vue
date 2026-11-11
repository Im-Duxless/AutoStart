<script setup>
defineProps({
  title: { type: String, default: 'Записей пока нет' },
  text: { type: String, default: '' },
  icon: { type: String, default: 'inbox' },
})
</script>

<template>
  <div class="empty-state">
    <span class="empty-icon"><i :class="'bi bi-' + icon" aria-hidden="true"></i></span>
    <h2>{{ title }}</h2>
    <p v-if="text">{{ text }}</p>
    <slot />
  </div>
</template>
