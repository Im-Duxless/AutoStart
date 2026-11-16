<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../services/api.js'
import { date, money, options, paymentMethods, paymentStatuses } from '../services/format.js'
import { useList } from '../services/useList.js'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import DemoPaymentForm from '../components/DemoPaymentForm.vue'
import EmptyState from '../components/EmptyState.vue'
import ErrorNotice from '../components/ErrorNotice.vue'
import FilterBar from '../components/FilterBar.vue'
import LoadingState from '../components/LoadingState.vue'
import MetricCard from '../components/MetricCard.vue'
import PageHeading from '../components/PageHeading.vue'
import PaginationBar from '../components/PaginationBar.vue'
import StatusBadge from '../components/StatusBadge.vue'

const { filters, rows, meta, loading, error, load } = useList('/payments', { from: '', to: '', status: '' })
const balance = ref(null)
const balanceError = ref(null)
const cancellation = ref(null)
const actionError = ref(null)
const success = ref('')
const busy = ref(false)
const fields = [
  { key: 'from', label: 'Дата с', type: 'date' },
  { key: 'to', label: 'Дата по', type: 'date' },
  { key: 'status', label: 'Статус', options: options(paymentStatuses) },
]
async function loadBalance() {
  balanceError.value = null
  try {
    balance.value = (await api('/payments/balance')).data
  } catch (problem) {
    balanceError.value = problem
  }
}
function reset() {
  Object.assign(filters, { from: '', to: '', status: '' })
  load()
}
function refresh() {
  return Promise.all([load(), loadBalance()])
}
function paymentCreated(payment) {
  actionError.value = null
  if (payment.status === 'pending') {
    success.value = `Заявка на ${money(payment.amount)} создана. Остаток уменьшится после подтверждения.`
  } else if (payment.status === 'void') {
    success.value = 'Эта заявка уже отменена. Сумма снова доступна к оплате.'
  } else {
    success.value = `Демо-оплата на ${money(payment.amount)} зачислена.`
  }
  refresh()
}
async function cancelPayment() {
  if (!cancellation.value || busy.value) return
  busy.value = true
  actionError.value = null
  success.value = ''
  try {
    await api('/payments/' + cancellation.value.id + '/cancel', { method: 'POST' })
    success.value = 'Заявка отменена. Сумма снова доступна к оплате.'
  } catch (problem) {
    actionError.value = problem
  } finally {
    await refresh()
    busy.value = false
    cancellation.value = null
  }
}
onMounted(refresh)
</script>

<template>
  <PageHeading title="Оплата" text="Взносы за обучение, остаток и история платежей." />
  <ErrorNotice :error="balanceError" />
  <button v-if="balanceError" class="btn btn-outline-primary mb-3" type="button" @click="loadBalance">Обновить баланс</button>
  <div v-if="balance" class="metrics-grid">
    <MetricCard label="Стоимость обучения" :value="money(balance.tuition)" icon="receipt" />
    <MetricCard label="Оплачено" :value="money(balance.paid)" icon="check2-circle" />
    <MetricCard label="Осталось оплатить" :value="money(balance.due)" icon="wallet2" />
  </div>
  <ErrorNotice :error="actionError" />
  <p v-if="success" class="alert alert-success" role="status">{{ success }}</p>
  <section v-if="balance && !balanceError" class="surface surface-pad" aria-labelledby="demo-payment-heading">
    <h2 id="demo-payment-heading" class="h5">Демо-оплата</h2>
    <p v-if="balance.due === null" class="mb-0">Оплата станет доступна после зачисления в группу с указанной стоимостью обучения.</p>
    <p v-else-if="Number(balance.due) === 0" class="mb-0">Обучение оплачено.</p>
    <template v-else>
      <p class="text-secondary small mb-3">Учебные операции отражаются в журнале платежей.</p>
      <p v-if="Number(balance.pending)" class="alert alert-warning" role="status">
        Заявки на наличные ожидают подтверждения. Зарезервировано {{ money(balance.pending) }}.
      </p>
      <DemoPaymentForm v-if="Number(balance.available) > 0" :available="balance.available"
        @payment-created="paymentCreated" @refresh="refresh" />
      <p v-else class="mb-0">Весь остаток зарезервирован. Заявку можно отменить в истории платежей.</p>
    </template>
  </section>
  <section class="surface">
    <div class="surface-heading"><h2>История платежей</h2></div>
    <FilterBar v-model="filters" :fields="fields" :busy="loading" @apply="load()" @reset="reset" />
    <ErrorNotice :error="error" class="m-3" />
    <LoadingState v-if="loading" />
    <EmptyState v-else-if="!rows.length" title="Платежей по выбранным условиям нет" icon="wallet2" />
    <div v-else class="table-responsive">
      <table class="table table-hover data-table payment-history-table">
        <thead><tr><th scope="col">Дата</th><th scope="col">Сумма</th><th scope="col">Способ</th><th scope="col">Статус</th></tr></thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td>{{ date(row.payment_date) }}</td><td><strong>{{ money(row.amount) }}</strong></td>
            <td>
              {{ paymentMethods[row.payment_method] }}
              <small v-if="row.comment" class="table-subtitle">{{ row.comment }}</small>
            </td>
            <td>
              <StatusBadge :value="row.status" :label="paymentStatuses[row.status]" />
              <button v-if="row.is_demo && row.status === 'pending'" type="button" class="btn btn-outline-danger btn-sm d-block mt-2"
                :disabled="busy" @click="cancellation = row">Отменить заявку</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <PaginationBar :meta="meta" :disabled="loading" @change="load" />
  </section>
  <ConfirmDialog :open="!!cancellation" title="Отменить заявку на наличные?"
    :text="cancellation ? money(cancellation.amount) + ' снова станет доступно к оплате.' : ''"
    label="Отменить заявку" danger :busy="busy" @confirm="cancelPayment" @close="cancellation = null" />
</template>

<style scoped>
@media (max-width: 620px) {
  .payment-history-table th, .payment-history-table td { padding: .65rem .5rem; }
  .payment-history-table :deep(.status-badge) { white-space: normal; }
}
</style>
