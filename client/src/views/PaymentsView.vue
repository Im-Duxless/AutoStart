<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../services/api.js'
import { date, money, options, paymentMethods, statuses } from '../services/format.js'
import { useList } from '../services/useList.js'
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
const fields = [
  { key: 'from', label: 'Дата с', type: 'date' },
  { key: 'to', label: 'Дата по', type: 'date' },
  { key: 'status', label: 'Статус', options: options({ received: statuses.received, void: statuses.void }) },
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
onMounted(() => Promise.all([load(), loadBalance()]))
</script>

<template>
  <PageHeading title="Оплата" text="Платежи, которые учла администрация, и остаток за обучение." />
  <ErrorNotice :error="balanceError" />
  <button v-if="balanceError" class="btn btn-outline-primary mb-3" type="button" @click="loadBalance">Обновить баланс</button>
  <div v-if="balance" class="metrics-grid">
    <MetricCard label="Стоимость обучения" :value="money(balance.tuition)" icon="receipt" />
    <MetricCard label="Оплачено" :value="money(balance.paid)" icon="check2-circle" />
    <MetricCard label="Осталось оплатить" :value="money(balance.due)" icon="wallet2" />
  </div>
  <section class="surface">
    <div class="surface-heading"><h2>История платежей</h2></div>
    <FilterBar v-model="filters" :fields="fields" :busy="loading" @apply="load()" @reset="reset" />
    <ErrorNotice :error="error" class="m-3" />
    <LoadingState v-if="loading" />
    <EmptyState v-else-if="!rows.length" title="Платежей по выбранным условиям нет" icon="wallet2" />
    <div v-else class="table-responsive">
      <table class="table table-hover data-table">
        <thead><tr><th scope="col">Дата</th><th scope="col">Сумма</th><th scope="col">Способ</th><th scope="col">Статус</th><th scope="col">Комментарий</th></tr></thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td>{{ date(row.payment_date) }}</td><td><strong>{{ money(row.amount) }}</strong></td>
            <td>{{ paymentMethods[row.payment_method] }}</td><td><StatusBadge :value="row.status" /></td><td>{{ row.comment || '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <PaginationBar :meta="meta" :disabled="loading" @change="load" />
  </section>
</template>
