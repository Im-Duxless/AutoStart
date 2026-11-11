<script setup>
defineProps({ meta: Object, disabled: Boolean })
defineEmits(['change'])
</script>

<template>
  <nav v-if="meta && meta.total > 0" class="pager" aria-label="Страницы списка">
    <span>Записи {{ meta.from }}–{{ meta.to }} из {{ meta.total }}</span>
    <div v-if="meta.last_page > 1" class="pager-controls">
      <button class="btn btn-sm btn-outline-primary" type="button" :disabled="disabled || meta.current_page <= 1" @click="$emit('change', meta.current_page - 1)" aria-label="Предыдущая страница">
        <i class="bi bi-chevron-left" aria-hidden="true"></i>
      </button>
      <span>{{ meta.current_page }} / {{ meta.last_page }}</span>
      <button class="btn btn-sm btn-outline-primary" type="button" :disabled="disabled || meta.current_page >= meta.last_page" @click="$emit('change', meta.current_page + 1)" aria-label="Следующая страница">
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
      </button>
    </div>
  </nav>
</template>
