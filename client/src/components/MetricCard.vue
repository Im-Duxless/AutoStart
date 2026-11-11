<script setup>
defineProps({ label: String, value: [String, Number], icon: String })
</script>

<template>
  <article class="metric-card">
    <span v-if="icon" class="metric-icon"><i :class="'bi bi-' + icon" aria-hidden="true"></i></span>
    <p>{{ label }}</p>
    <strong>{{ value }}</strong>
  </article>
</template>
