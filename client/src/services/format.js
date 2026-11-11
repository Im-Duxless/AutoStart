import { school } from './school.js'

export const roles = { student: 'Курсант', instructor: 'Инструктор', admin: 'Администратор' }
export const statuses = {
  open: 'Идёт набор', in_progress: 'Обучается', closed: 'Набор закрыт',
  scheduled: 'По расписанию', free: 'Свободно', booked: 'Записан', completed: 'Проведено', cancelled: 'Отменено',
  pending: 'На рассмотрении', approved: 'Зачислен', rejected: 'Отклонена',
  received: 'Учтён', void: 'Аннулирован',
}
export const lessonTypes = { theory: 'Теория', driving: 'Вождение' }
export const paymentMethods = { cash: 'Наличные', transfer: 'Перевод', card: 'Карта' }

export function options(labels) {
  return Object.entries(labels).map(([value, label]) => ({ value, label }))
}

export function date(value, short = false) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('ru-RU', {
    day: 'numeric', month: short ? 'short' : 'long', ...(short ? {} : { year: 'numeric' }), timeZone: 'UTC',
  }).format(new Date(value.slice(0, 10) + 'T12:00:00Z')).replace(/\sг\.$/, '')
}

export function time(value) {
  return value?.slice(11, 16) || '—'
}

export function today() {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: school.data?.timezone || 'Asia/Qyzylorda', year: 'numeric', month: '2-digit', day: '2-digit',
  }).formatToParts(new Date())
  const part = name => parts.find(item => item.type === name).value
  return [part('year'), part('month'), part('day')].join('-')
}

export function money(value) {
  if (value === null || value === undefined) return '—'
  const currency = school.data?.currency || 'KZT'
  const amount = Number(value)
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency', currency, currencyDisplay: 'narrowSymbol', maximumFractionDigits: 2,
    minimumFractionDigits: Number.isInteger(amount) ? 0 : 2,
  }).format(amount)
}

export function duration(minutes = 0) {
  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60
  return [hours || !rest ? hours + ' ч' : '', rest ? rest + ' мин' : ''].filter(Boolean).join(' ')
}

export function initials(name = '') {
  return name.trim().split(/\s+/).slice(0, 2).map(word => word[0]).join('').toUpperCase()
}

export function carName(car) {
  return car ? car.brand + ' ' + car.model : '—'
}
