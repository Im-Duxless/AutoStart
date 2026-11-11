<script setup>
defineProps({ title: String, text: String })
</script>

<template>
  <div class="page-heading">
    <div><h1>{{ title }}</h1><p v-if="text">{{ text }}</p></div>
    <div v-if="$slots.default" class="page-heading-actions"><slot /></div>
  </div>
</template>
