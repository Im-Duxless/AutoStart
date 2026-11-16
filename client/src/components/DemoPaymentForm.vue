<script setup>
import { computed, ref } from 'vue'
import { api } from '../services/api.js'
import { money } from '../services/format.js'
import ConfirmDialog from './ConfirmDialog.vue'
import ErrorNotice from './ErrorNotice.vue'
import FormField from './FormField.vue'

const props = defineProps({ available: { type: String, required: true } })
const emit = defineEmits(['payment-created', 'refresh'])
const method = ref('card')
const amount = ref('')
const checkout = ref(null)
const busy = ref(false)
const error = ref(null)
const paymentLimit = computed(() => Math.min(Number(props.available), 99_999_999.99))
let lastAttempt = null

function paymentReference() {
  if (crypto.randomUUID) return crypto.randomUUID()

  // OSPanel runs on HTTP. getRandomValues is available there as well.
  const bytes = crypto.getRandomValues(new Uint8Array(16))
  bytes[6] = (bytes[6] & 0x0f) | 0x40
  bytes[8] = (bytes[8] & 0x3f) | 0x80
  const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('')
  return [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-')
}

function reviewPayment() {
  error.value = null
  const value = Number(amount.value).toFixed(2)
  if (lastAttempt?.amount !== value || lastAttempt?.payment_method !== method.value) {
    lastAttempt = { amount: value, payment_method: method.value, reference: paymentReference() }
  }
  checkout.value = lastAttempt
}

async function submitPayment() {
  if (!checkout.value || busy.value) return
  busy.value = true
  error.value = null
  try {
    const { data } = await api('/payments/demo', { method: 'POST', body: checkout.value })
    amount.value = ''
    lastAttempt = null
    emit('payment-created', data)
  } catch (problem) {
    error.value = problem
    if (problem.status === 409) emit('refresh')
  } finally {
    busy.value = false
    checkout.value = null
  }
}
</script>

<template>
  <form class="form-stack" @submit.prevent="reviewPayment">
    <fieldset :disabled="busy">
      <legend class="form-label">Способ оплаты</legend>
      <div class="payment-methods">
        <label class="payment-method">
          <input v-model="method" type="radio" name="payment-method" value="card">
          <i class="bi bi-credit-card" aria-hidden="true"></i>
          <span><strong>Картой</strong><small>Зачисление тестовой картой •••• 4242</small></span>
        </label>
        <label class="payment-method">
          <input v-model="method" type="radio" name="payment-method" value="cash">
          <i class="bi bi-cash-stack" aria-hidden="true"></i>
          <span><strong>Наличными</strong><small>Взнос подтверждает администратор</small></span>
        </label>
      </div>
    </fieldset>
    <ErrorNotice v-if="!error?.errors?.amount" :error="error" />
    <div class="payment-amount">
      <FormField v-model="amount" type="number" label="Сумма взноса" min="0.01" :max="paymentLimit" step="0.01"
        :hint="'Лимит взноса ' + money(paymentLimit)" :error="error?.errors?.amount?.[0]" :disabled="busy" required />
      <button v-if="Number(available) === paymentLimit" class="btn btn-outline-primary" type="button" :disabled="busy"
        @click="amount = available">Весь остаток</button>
    </div>
    <div>
      <button class="btn btn-primary" type="submit" :disabled="busy">{{ method === 'card' ? 'Оплатить картой' : 'Оформить взнос' }}</button>
    </div>
  </form>
  <ConfirmDialog :open="!!checkout" :busy="busy"
    :title="checkout?.payment_method === 'card' ? 'Подтвердить демо-оплату?' : 'Создать заявку на наличные?'"
    :text="checkout ? money(checkout.amount) + (checkout.payment_method === 'card' ? ' · Тестовая карта •••• 4242' : ' · Наличными') : ''"
    :label="checkout?.payment_method === 'card' ? 'Оплатить' : 'Создать заявку'"
    @close="checkout = null" @confirm="submitPayment" />
</template>

<style scoped>
.payment-methods {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr));
  gap: .7rem;
}
.payment-method {
  display: flex;
  align-items: center;
  gap: .7rem;
  min-width: 0;
  padding: .85rem 1rem;
  border: 1px solid var(--line);
  border-radius: .7rem;
  cursor: pointer;
}
.payment-method:has(input:checked) { border-color: #617fa6; background: #edf3fb; }
.payment-method input { accent-color: var(--navy-light); }
.payment-method i { color: var(--navy-light); font-size: 1.25rem; }
.payment-method small { display: block; color: var(--muted); }
.payment-amount { display: flex; align-items: flex-start; flex-wrap: wrap; gap: .6rem; }
.payment-amount .field-wrap { flex: 1 1 12rem; max-width: 20rem; }
.payment-amount > button { margin-top: 1.8rem; }
@media (max-width: 480px) {
  .payment-amount > button { margin-top: 0; }
}
</style>
