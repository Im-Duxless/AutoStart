<script setup>
import { nextTick, ref, watch } from 'vue'

const props = defineProps({ open: Boolean, title: String, text: String, label: String, danger: Boolean, busy: Boolean })
const emit = defineEmits(['close', 'confirm'])
const dialog = ref(null)

watch(() => props.open, async show => {
  await nextTick()
  if (show && !dialog.value.open) dialog.value.showModal()
  else if (!show && dialog.value.open) dialog.value.close()
})
</script>

<template>
  <dialog ref="dialog" class="confirm-dialog" aria-labelledby="confirm-title" @cancel.prevent="!busy && emit('close')">
    <h2 id="confirm-title">{{ title }}</h2>
    <p v-if="text">{{ text }}</p>
    <div class="dialog-actions">
      <button autofocus type="button" class="btn btn-outline-primary" :disabled="busy" @click="emit('close')">Назад</button>
      <button type="button" class="btn" :class="danger ? 'btn-danger' : 'btn-primary'" :disabled="busy" @click="emit('confirm')">
        {{ busy ? 'Подождите…' : label || 'Подтвердить' }}
      </button>
    </div>
  </dialog>
</template>
