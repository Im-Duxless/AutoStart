<script setup>
import { ref } from 'vue'
import { adminTables } from '../../admin/tables.js'
import AdminList from '../../components/AdminList.vue'
import ConfirmDialog from '../../components/ConfirmDialog.vue'
import ErrorNotice from '../../components/ErrorNotice.vue'
import { api } from '../../services/api.js'
import { money } from '../../services/format.js'

const decision = ref(null)
const busy = ref(false)
const error = ref(null)
const success = ref('')

function review(row, action, reload) {
  error.value = null
  success.value = ''
  decision.value = { row, action, reload }
}

async function decide() {
  if (!decision.value || busy.value) return
  const { row, action, reload } = decision.value
  busy.value = true
  try {
    await api('/payments/' + row.id + '/' + action, { method: 'POST' })
    success.value = action === 'confirm' ? 'Взнос наличными подтверждён и учтён в остатке.' : 'Заявка на наличные отменена.'
  } catch (problem) {
    error.value = problem
  } finally {
    await reload()
    busy.value = false
    decision.value = null
  }
}
</script>

<template>
  <AdminList :config="adminTables.payments">
    <template #notices>
      <ErrorNotice :error="error" />
      <p v-if="success" class="alert alert-success" role="status">{{ success }}</p>
    </template>
    <template #row-actions="{ row, reload }">
      <template v-if="row.is_demo && row.status === 'pending'">
        <button class="btn btn-primary btn-sm text-nowrap" type="button" :disabled="busy"
          @click="review(row, 'confirm', reload)">Подтвердить наличные</button>
        <button class="btn btn-outline-danger btn-sm text-nowrap" type="button" :disabled="busy"
          @click="review(row, 'cancel', reload)">Отменить заявку</button>
      </template>
    </template>
  </AdminList>
  <ConfirmDialog :open="!!decision" :busy="busy" :danger="decision?.action === 'cancel'"
    :title="decision?.action === 'confirm' ? 'Подтвердить взнос наличными?' : 'Отменить заявку на наличные?'"
    :text="decision ? (decision.row.student?.name || '') + ' · ' + money(decision.row.amount) : ''"
    :label="decision?.action === 'confirm' ? 'Подтвердить взнос' : 'Отменить заявку'"
    @close="decision = null" @confirm="decide" />
</template>
