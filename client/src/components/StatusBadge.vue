<script setup>
import { computed } from 'vue'
import { statuses } from '../services/format.js'

const props = defineProps({ value: String, label: String })
const tone = computed(() => {
  if (['open', 'free', 'approved', 'completed', 'received', 'passed'].includes(props.value)) return 'good'
  if (['pending', 'booked', 'in_progress'].includes(props.value)) return 'warm'
  if (['cancelled', 'rejected', 'failed', 'void'].includes(props.value)) return 'bad'
  return 'neutral'
})
</script>

<template>
  <span class="status-badge" :class="'tone-' + tone">{{ label || statuses[value] || value }}</span>
</template>
