<script setup>
import { useId } from 'vue'

const model = defineModel({ required: true })
const id = useId()
defineProps({ fields: { type: Array, required: true }, busy: Boolean })
defineEmits(['apply', 'reset'])
</script>

<template>
  <form class="filter-bar" @submit.prevent="$emit('apply')">
    <div v-for="field in fields" :key="field.key" class="filter-field" :class="{ 'filter-search': field.key === 'search' }">
      <label :for="id + '-' + field.key" class="form-label">{{ field.label }}</label>
      <select v-if="field.options" :id="id + '-' + field.key" v-model="model[field.key]" class="form-select">
        <option value="">{{ field.placeholder || 'Все' }}</option>
        <option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
      <input v-else :id="id + '-' + field.key" v-model="model[field.key]" class="form-control" :type="field.type || 'search'" :min="field.min" :placeholder="field.placeholder">
    </div>
    <div class="filter-actions">
      <button class="btn btn-primary" :disabled="busy" type="submit">Применить</button>
      <button class="btn btn-outline-primary" :disabled="busy" type="button" @click="$emit('reset')">Сбросить</button>
    </div>
  </form>
</template>
