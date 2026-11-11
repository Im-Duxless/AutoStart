<script setup>
import FormField from './FormField.vue'

const options = defineModel({ type: Array, required: true })
defineProps({ errors: Object })
const emit = defineEmits(['removed'])

function remove(index) {
  options.value.splice(index, 1)
  emit('removed', index)
}
</script>

<template>
  <fieldset class="option-editor">
    <legend class="form-label">Варианты ответа</legend>
    <div v-for="(_, index) in options" :key="index" class="option-line">
      <FormField v-model="options[index]" :label="'Вариант ' + (index + 1)" required :maxlength="300" :error="errors?.['options.' + index]?.[0]" />
      <button v-if="options.length > 2" class="btn btn-outline-danger" type="button" :aria-label="'Удалить вариант ' + (index + 1)" @click="remove(index)">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
      </button>
    </div>
    <p v-if="errors?.options?.[0]" class="text-danger mb-0">{{ errors.options[0] }}</p>
    <button v-if="options.length < 6" class="btn btn-outline-primary btn-sm" type="button" @click="options.push('')"><i class="bi bi-plus-lg" aria-hidden="true"></i> Добавить вариант</button>
  </fieldset>
</template>
