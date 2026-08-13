const STATUS_KEYS = ['pending', 'scheduled', 'in_progress', 'completed', 'rescheduled', 'cancelled', 'not_applicable', 'partial', 'overdue']
const TYPE_KEYS = ['preventive', 'corrective', 'special']

export const isPositiveId = value => /^\d+$/.test(String(value ?? '')) && Number(value) > 0

export function maintenanceStatusLabel(status, t) {
	const labels = {
		pending: 'Pending',
		scheduled: 'Scheduled',
		in_progress: 'In progress',
		completed: 'Completed',
		rescheduled: 'Rescheduled',
		cancelled: 'Cancelled',
		not_applicable: 'Not applicable',
		partial: 'Partial',
		overdue: 'Overdue',
	}
	const key = STATUS_KEYS.includes(status) ? status : 'pending'
	return t('empleados', labels[key])
}

export function maintenanceTypeLabel(type, t) {
	const labels = { preventive: 'Preventive', corrective: 'Corrective', special: 'Special' }
	return t('empleados', labels[TYPE_KEYS.includes(type) ? type : 'special'])
}

export function formatCalendarDate(value, locale) {
	if (!/^\d{4}-\d{2}-\d{2}$/.test(String(value || ''))) return '—'
	const [year, month, day] = value.split('-').map(Number)
	return new Intl.DateTimeFormat(locale || undefined).format(new Date(year, month - 1, day))
}

export function formatDateRange(start, end, locale) {
	const formattedStart = formatCalendarDate(start, locale)
	const formattedEnd = formatCalendarDate(end, locale)
	if (formattedStart === '—' && formattedEnd === '—') return '—'
	if (formattedStart === formattedEnd || formattedEnd === '—') return formattedStart
	if (formattedStart === '—') return formattedEnd
	return `${formattedStart} – ${formattedEnd}`
}

export function formatOptionalTimeRange(start, end, fallback = '—') {
	const normalize = value => /^\d{2}:\d{2}/.test(String(value || '')) ? String(value).slice(0, 5) : ''
	const from = normalize(start)
	const to = normalize(end)
	return from && to ? `${from} – ${to}` : fallback
}

export function addOneCalendarDay(value) {
	if (!/^\d{4}-\d{2}-\d{2}$/.test(String(value || ''))) return ''
	const [year, month, day] = value.split('-').map(Number)
	const date = new Date(year, month - 1, day + 1, 12)
	return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

export function toApiDate(value) {
	if (!value) return ''
	const year = value.getFullYear()
	const month = String(value.getMonth() + 1).padStart(2, '0')
	const day = String(value.getDate()).padStart(2, '0')
	return `${year}-${month}-${day}`
}

export function maintenanceCapabilities(permissions = {}, configurations = {}) {
	const truthy = value => value === true || value === 'true' || value === 1 || value === '1'
	const inventory = permissions?.modules?.inventario || {}
	const isAdmin = truthy(permissions?.is_admin) || truthy(inventory.admin) || truthy(inventory.permissions?.admin)
	const isTechnician = isAdmin || truthy(inventory.technician) || truthy(inventory.permissions?.technician)
	const canView = isTechnician || truthy(inventory.view) || truthy(inventory.permissions?.view)
	return {
		moduleEnabled: truthy(configurations?.modulo_inventario),
		canAdministerMaintenance: isAdmin,
		canWorkMaintenance: isTechnician,
		canViewMaintenance: canView,
		isTechnician: isTechnician && !isAdmin,
	}
}

export function maintenanceRecordCapabilities(maintenance = {}, capabilities = {}, currentUid = '') {
	const status = String(maintenance.estado || '')
	const active = ['pending', 'scheduled', 'in_progress', 'rescheduled'].includes(status)
	const assignedTechnician = capabilities.isTechnician === true
		&& String(currentUid || '') !== ''
		&& String(currentUid) === String(maintenance.tecnico_uid || '')
	const canOperate = capabilities.canAdministerMaintenance === true || assignedTechnician
	return {
		canOperate,
		canSchedule: canOperate && ['pending', 'rescheduled'].includes(status),
		canStart: canOperate && ['pending', 'scheduled', 'rescheduled'].includes(status),
		canEditChecklist: canOperate && status === 'in_progress',
		canSaveWork: canOperate && status === 'in_progress',
		canComplete: canOperate && status === 'in_progress',
		canReschedule: canOperate && ['pending', 'scheduled', 'in_progress'].includes(status) && Boolean(maintenance.fecha_programada),
		canMarkNotApplicable: canOperate && ['pending', 'scheduled', 'rescheduled'].includes(status),
		canCancel: capabilities.canAdministerMaintenance === true && active,
		canAssignTechnician: capabilities.canAdministerMaintenance === true && active,
		readOnly: !canOperate || !active,
	}
}

export function toggleSelectedEquipment(selectedIds, rows, checked) {
	const next = new Set((selectedIds || []).map(Number).filter(Number.isInteger))
	for (const row of rows || []) {
		const id = Number(row.id)
		if (!Number.isInteger(id) || id <= 0) continue
		if (checked) next.add(id)
		else next.delete(id)
	}
	return [...next]
}
