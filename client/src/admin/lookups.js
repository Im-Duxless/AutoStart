import { getAll } from '../services/api.js'
import { carName } from '../services/format.js'

const lookupSources = {
  students: ['/users', { role: 'student', sort: 'name', order: 'asc' }, row => row.name + ' · ' + row.email],
  instructors: ['/users', { role: 'instructor', sort: 'name', order: 'asc' }, row => row.name],
  groups: ['/study-groups', { sort: 'name', order: 'asc' }, row => row.name + ' · ' + row.category],
  openGroups: ['/study-groups', { status: 'open', sort: 'name', order: 'asc' }, row => row.name + ' · ' + row.category],
  cars: ['/cars', { is_active: '1', sort: 'brand', order: 'asc' }, row => carName(row) + ' · ' + row.plate_number + ' · ' + row.category],
}

export async function loadOptions(key) {
  const [path, params, label] = lookupSources[key]
  return (await getAll(path, params)).map(row => ({ value: row.id, label: label(row) }))
}
