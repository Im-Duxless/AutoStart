<script setup>
defineProps({ text: { type: String, default: 'Загрузка…' } })
</script>

<template>
  <div class="loading-state" role="status">
    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span> {{ text }}
  </div>
</template>
