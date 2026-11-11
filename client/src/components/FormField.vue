<script setup>
import { computed, useId } from 'vue'

const value = defineModel()
const props = defineProps({
  label: String, type: { type: String, default: 'text' }, options: Array,
  required: Boolean, disabled: Boolean, error: String, hint: String,
  min: [String, Number], max: [String, Number], step: [String, Number], autocomplete: String, minlength: Number, maxlength: Number,
})
const id = useId()
const describedBy = computed(() => props.error ? id + '-error' : props.hint ? id + '-hint' : undefined)
</script>

<template>
  <div class="field-wrap">
    <label :for="id" class="form-label">{{ label }}</label>
    <select v-if="options" :id="id" v-model="value" class="form-select" :class="{ 'is-invalid': error }"
      :required="required" :disabled="disabled" :aria-describedby="describedBy" :aria-invalid="!!error">
      <option v-if="!options.some(option => option.value === '')" value="">Выберите</option>
      <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
    </select>
    <textarea v-else-if="type === 'textarea'" :id="id" v-model="value" class="form-control" :class="{ 'is-invalid': error }" rows="3"
      :required="required" :disabled="disabled" :maxlength="maxlength" :aria-describedby="describedBy" :aria-invalid="!!error"></textarea>
    <input v-else :id="id" v-model="value" class="form-control" :class="{ 'is-invalid': error }" :type="type" :required="required"
      :disabled="disabled" :min="min" :max="max" :step="step" :minlength="minlength" :maxlength="maxlength" :autocomplete="autocomplete"
      :aria-describedby="describedBy" :aria-invalid="!!error">
    <div v-if="error" :id="id + '-error'" class="invalid-feedback">{{ error }}</div>
    <div v-else-if="hint" :id="id + '-hint'" class="form-text">{{ hint }}</div>
  </div>
</template>
