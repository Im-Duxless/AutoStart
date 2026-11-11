<script setup>
defineProps({ error: { type: [Object, String], default: null } })
</script>

<template>
  <div v-if="error" class="alert alert-danger error-notice" role="alert">
    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
    <span>{{ typeof error === 'string' ? error : error.message }}</span>
  </div>
</template>
