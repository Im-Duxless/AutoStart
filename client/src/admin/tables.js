import { carName, date, lessonTypes, money, options, paymentMethods, paymentStatuses, roles, statuses, time, today } from '../services/format.js'

export const categories = options({ A: 'A · Мотоциклы', B: 'B · Легковые', C: 'C · Грузовые' })
const groupStatuses = options({ open: statuses.open, in_progress: statuses.in_progress, closed: statuses.closed })
const lessonStatuses = options({ scheduled: statuses.scheduled, free: statuses.free, booked: statuses.booked, completed: statuses.completed, cancelled: statuses.cancelled })
const manualPaymentStatuses = options({ received: statuses.received, void: statuses.void })
const categoryFilter = { key: 'category', label: 'Категория', options: categories }
const dateFilters = [{ key: 'from', label: 'Дата с', type: 'date' }, { key: 'to', label: 'Дата по', type: 'date' }]
const categoryField = { key: 'category', label: 'Категория', options: categories, required: true }

export const adminTables = {
  users: {
    title: 'Пользователи', description: 'Учётные записи курсантов, инструкторов и администраторов.',
    path: '/users', create: 'Добавить пользователя', itemName: row => row.name,
    filters: [
      { key: 'search', label: 'Поиск', placeholder: 'Имя, почта или телефон' },
      { key: 'role', label: 'Роль', options: options(roles) },
      { key: 'email', label: 'Электронная почта', placeholder: 'Часть адреса' },
    ],
    columns: [
      { key: 'name', label: 'Имя', sort: true }, { key: 'email', label: 'Почта', sort: true },
      { key: 'role', label: 'Роль', sort: true, value: row => roles[row.role] }, { key: 'phone', label: 'Телефон' },
    ],
    fields: [
      { key: 'name', label: 'Имя и фамилия', required: true, maxlength: 100 },
      { key: 'email', label: 'Электронная почта', type: 'email', required: true, maxlength: 255 },
      { key: 'role', label: 'Роль', options: options(roles), required: true, default: 'student' },
      { key: 'phone', label: 'Телефон', type: 'tel', maxlength: 32 },
      { key: 'password', label: 'Пароль', type: 'password', required: true, createRequired: true, minlength: 8, maxlength: 72, autocomplete: 'new-password' },
      { key: 'password_confirmation', label: 'Подтверждение пароля', type: 'password', required: true, createRequired: true, minlength: 8, maxlength: 72, autocomplete: 'new-password' },
    ],
  },
  'study-groups': {
    title: 'Учебные группы', description: 'Набор, вместимость, даты начала и стоимость обучения.',
    path: '/study-groups', create: 'Добавить группу', itemName: row => row.name,
    filters: [{ key: 'search', label: 'Поиск группы', placeholder: 'Название' }, categoryFilter, { key: 'status', label: 'Статус', options: groupStatuses }],
    columns: [
      { key: 'name', label: 'Группа', sort: true }, { key: 'category', label: 'Категория', sort: true },
      { key: 'start_date', label: 'Дата начала', sort: true, value: row => date(row.start_date) },
      { key: 'capacity', label: 'Зачислено', sort: true, value: row => row.approved_count + ' / ' + row.capacity },
      { key: 'tuition', label: 'Стоимость', sort: true, value: row => money(row.tuition) },
      { key: 'status', label: 'Статус', badge: true },
    ],
    fields: [
      { key: 'name', label: 'Название группы', required: true, maxlength: 100 },
      categoryField,
      { key: 'capacity', label: 'Вместимость', type: 'number', required: true, min: 1, max: 500, default: 12 },
      { key: 'status', label: 'Статус', options: groupStatuses, required: true, default: 'open' },
      { key: 'start_date', label: 'Дата начала', type: 'date', required: true },
      { key: 'tuition', label: 'Стоимость обучения', type: 'number', min: 0, step: '0.01', max: 99999999.99 },
    ],
  },
  cars: {
    title: 'Транспорт', description: 'Учебные автомобили и мотоциклы по категориям.',
    path: '/cars', create: 'Добавить транспорт', itemName: row => carName(row) + ' · ' + row.plate_number,
    filters: [
      { key: 'search', label: 'Поиск транспорта', placeholder: 'Марка, модель или госномер' }, categoryFilter,
      { key: 'is_active', label: 'Доступность', options: [{ value: '1', label: 'Активен' }, { value: '0', label: 'Отключён' }] },
    ],
    columns: [
      { key: 'brand', label: 'Марка', sort: true }, { key: 'model', label: 'Модель', sort: true },
      { key: 'plate_number', label: 'Госномер', sort: true }, { key: 'category', label: 'Категория', sort: true },
      { key: 'is_active', label: 'Доступность', sort: true, value: row => row.is_active ? 'Активен' : 'Отключён' },
    ],
    fields: [
      { key: 'brand', label: 'Марка', required: true, maxlength: 60 },
      { key: 'model', label: 'Модель', required: true, maxlength: 60 },
      { key: 'plate_number', label: 'Госномер', required: true, maxlength: 20 },
      categoryField,
      { key: 'is_active', label: 'Доступность', required: true, default: true, options: [{ value: true, label: 'Активен' }, { value: false, label: 'Отключён' }] },
    ],
  },
  lessons: {
    title: 'Занятия', description: 'Теория для учебных групп и расписание вождения.',
    path: '/lessons', create: 'Добавить занятие', itemName: row => lessonTypes[row.type] + ' · ' + date(row.start_at),
    filters: [
      { key: 'search', label: 'Поиск занятия', placeholder: 'Группа, инструктор или госномер' },
      { key: 'type', label: 'Тип', options: options(lessonTypes) },
      { key: 'status', label: 'Статус', options: lessonStatuses }, ...dateFilters,
    ],
    columns: [
      { key: 'start_at', label: 'Начало', sort: true, value: row => date(row.start_at, true) + ' · ' + time(row.start_at) },
      { key: 'end_at', label: 'Окончание', sort: true, value: row => date(row.end_at, true) + ' · ' + time(row.end_at) },
      { key: 'type', label: 'Тип', sort: true, value: row => lessonTypes[row.type] },
      { key: 'participants', label: 'Группа или инструктор', value: row => row.study_group?.name || row.instructor?.name },
      { key: 'status', label: 'Статус', sort: true, badge: true },
    ],
    fields: [
      { key: 'type', label: 'Тип занятия', options: options(lessonTypes), default: 'theory', required: true, lockedOnEdit: true },
      { key: 'study_group_id', label: 'Группа', lookup: 'groups', forType: 'theory', required: true },
      { key: 'instructor_id', label: 'Инструктор', lookup: 'instructors', forType: 'driving', required: true },
      { key: 'car_id', label: 'Транспорт', lookup: 'cars', forType: 'driving', required: true },
      { key: 'start_at', label: 'Начало', type: 'datetime-local', required: true },
      { key: 'end_at', label: 'Окончание', type: 'datetime-local', required: true },
    ],
    canEdit: row => ['scheduled', 'free'].includes(row.status) && new Date(row.start_at) > new Date(),
    canDelete: row => !row.student_id && (row.status === 'cancelled' || (['scheduled', 'free'].includes(row.status) && new Date(row.start_at) > new Date())),
  },
  questions: {
    title: 'Вопросы ПДД', description: 'Вопросы объединяются в билеты по категории и номеру.',
    path: '/questions', create: 'Добавить вопрос', itemName: row => row.text,
    filters: [{ key: 'search', label: 'Поиск вопроса', placeholder: 'Часть вопроса' }, categoryFilter, { key: 'ticket_number', label: 'Номер билета', type: 'number', min: 1 }],
    columns: [
      { key: 'ticket_number', label: 'Билет', sort: true }, { key: 'category', label: 'Категория', sort: true },
      { key: 'text', label: 'Вопрос', sort: true, value: row => row.text.length > 105 ? row.text.slice(0, 102) + '…' : row.text },
    ],
    fields: [
      categoryField,
      { key: 'ticket_number', label: 'Номер билета', type: 'number', required: true, min: 1, max: 999, default: 1 },
      { key: 'text', label: 'Текст вопроса', type: 'textarea', required: true, maxlength: 2000, wide: true },
      { key: 'options', label: 'Варианты ответа', wide: true },
      { key: 'correct_option', label: 'Правильный ответ', required: true, wide: true },
    ],
  },
  payments: {
    title: 'Платежи', description: 'Взносы курсантов и заявки на оплату наличными.',
    path: '/payments', create: 'Внести платёж', itemName: row => row.student?.name + ' · ' + money(row.amount),
    filters: [
      { key: 'search', label: 'Поиск платежа', placeholder: 'Курсант или комментарий' },
      { key: 'user_id', label: 'Курсант', lookup: 'students' },
      { key: 'payment_method', label: 'Способ', options: options(paymentMethods) },
      { key: 'status', label: 'Статус', options: options(paymentStatuses) }, ...dateFilters,
    ],
    columns: [
      { key: 'payment_date', label: 'Дата', sort: true, value: row => date(row.payment_date) },
      { key: 'student', label: 'Курсант', value: row => row.student?.name },
      { key: 'amount', label: 'Сумма', sort: true, value: row => money(row.amount) },
      { key: 'payment_method', label: 'Способ', sort: true, value: row => paymentMethods[row.payment_method] + (row.is_demo ? ' · Демо' : '') },
      { key: 'status', label: 'Статус', sort: true, badge: true, value: row => paymentStatuses[row.status] },
    ],
    fields: [
      { key: 'user_id', label: 'Курсант', lookup: 'students', required: true },
      { key: 'amount', label: 'Сумма', type: 'number', min: 0.01, max: 99999999.99, step: '0.01', required: true },
      { key: 'payment_date', label: 'Дата платежа', type: 'date', required: true, default: today, max: today },
      { key: 'payment_method', label: 'Способ оплаты', options: options(paymentMethods), required: true },
      { key: 'status', label: 'Статус', options: manualPaymentStatuses, required: true, default: 'received' },
      { key: 'comment', label: 'Комментарий', type: 'textarea', wide: true, maxlength: 500 },
    ],
    canEdit: row => !row.is_demo,
    canDelete: row => !row.is_demo,
  },
}
