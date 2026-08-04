import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const appUrl = path => generateUrl(`/apps/empleados${path}`)

function responseData(response) {
	const body = response?.data?.ocs?.data ?? response?.data
	if (body?.success === false) {
		throw normalizeMaintenanceError({ response: { status: response.status, data: body } })
	}
	return body?.success === true ? body.data : body
}

export function normalizeMaintenanceError(error) {
	if (error?.isMaintenanceError) return error
	const status = Number(error?.response?.status || 0)
	const body = error?.response?.data?.ocs?.data ?? error?.response?.data ?? {}
	const detail = body?.error ?? {}
	const normalized = new Error(detail.message || error?.message || 'Maintenance request failed')
	normalized.name = 'MaintenanceApiError'
	normalized.isMaintenanceError = true
	normalized.status = status
	normalized.code = detail.code || ({
		400: 'maintenance_validation_error',
		401: 'maintenance_session_expired',
		403: 'maintenance_access_denied',
		404: 'maintenance_not_found',
		409: 'maintenance_conflict',
		500: 'maintenance_internal_error',
	}[status] || 'maintenance_request_error')
	normalized.conflicts = Array.isArray(detail.conflicts) ? detail.conflicts : []
	normalized.cancelled = error?.code === 'ERR_CANCELED' || axios.isCancel?.(error) === true
	return normalized
}

async function request(method, path, { params, data, signal } = {}) {
	try {
		return responseData(await axios({ method, url: appUrl(path), params, data, signal }))
	} catch (error) {
		throw normalizeMaintenanceError(error)
	}
}

let technicianCache = null
let technicianRequest = null

export function normalizeTechnicians(items) {
	if (!Array.isArray(items)) return []
	const normalized = new Map()
	for (const item of items) {
		const uid = String(item?.uid ?? item?.value ?? item?.Id_user ?? item?.id_user ?? '').trim()
		if (!uid) continue
		const displayName = String(item?.displayName ?? item?.displayname ?? item?.label ?? item?.Nombre ?? item?.name ?? uid).trim() || uid
		normalized.set(uid, { uid, displayName })
	}
	return [...normalized.values()].sort((left, right) => left.displayName.localeCompare(right.displayName, undefined, { sensitivity: 'base' }) || left.uid.localeCompare(right.uid))
}

export default {
	getGroups(filters = {}, signal) { return request('get', '/inventario/mantenimientos/grupos', { params: filters, signal }) },
	createGroup(payload) {
		const { scheduledDate, ...data } = payload
		return request('post', '/inventario/mantenimientos/grupos', { data })
	},
	getGroup(id, signal) { return request('get', `/inventario/mantenimientos/grupos/${id}`, { signal }) },
	getGroupMaintenances(id, filters = {}, signal) { return request('get', `/inventario/mantenimientos/grupos/${id}/equipos`, { params: filters, signal }) },
	cancelGroup(id, reason) { return request('post', `/inventario/mantenimientos/grupos/${id}/cancelar`, { data: { reason } }) },
	assignGroupTechnician(id, technicianUid) { return request('post', `/inventario/mantenimientos/grupos/${id}/tecnico`, { data: { technicianUid } }) },
	getEligibleEquipment(departmentId, filters = {}, signal) { return request('get', `/inventario/mantenimientos/departamentos/${departmentId}/equipos`, { params: filters, signal }) },
	getMaintenance(id, signal) { return request('get', `/inventario/mantenimientos/${id}`, { signal }) },
	getOverdue(filters = {}, signal) { return request('get', '/inventario/mantenimientos/atrasados', { params: filters, signal }) },
	getDuplicates(params = {}, signal) {
		const { scheduledDate, ...periodParams } = params
		return request('get', '/inventario/mantenimientos/duplicados', { params: periodParams, signal })
	},
	startMaintenance(id) { return request('post', `/inventario/mantenimientos/${id}/iniciar`) },
	scheduleMaintenance(id, payload) { return request('post', `/inventario/mantenimientos/${id}/programar`, { data: payload }) },
	completeMaintenance(id, payload) { return request('post', `/inventario/mantenimientos/${id}/completar`, { data: payload }) },
	rescheduleMaintenance(id, payload) { return request('post', `/inventario/mantenimientos/${id}/reprogramar`, { data: payload }) },
	cancelMaintenance(id, reason) { return request('post', `/inventario/mantenimientos/${id}/cancelar`, { data: { reason } }) },
	markNotApplicable(id, reason) { return request('post', `/inventario/mantenimientos/${id}/no-aplica`, { data: { reason } }) },
	assignTechnician(id, technicianUid) { return request('post', `/inventario/mantenimientos/${id}/tecnico`, { data: { technicianUid } }) },
	updateChecklist(id, items) { return request('patch', `/inventario/mantenimientos/${id}/checklist`, { data: { items } }) },
	updateWork(id, payload) { return request('patch', `/inventario/mantenimientos/${id}/trabajo`, { data: payload }) },
	getEquipmentHistory(id, filters = {}, signal) { return request('get', `/inventario/mantenimientos/equipos/${id}/historial`, { params: filters, signal }) },
	async getDepartments(signal) {
		const response = await axios.get(appUrl('/GetAreasFix'), { signal })
		return response?.data?.ocs?.data ?? response?.data ?? []
	},
	async getTechnicians(signal) {
		if (technicianCache !== null) return technicianCache
		if (technicianRequest !== null) return technicianRequest
		technicianRequest = request('get', '/inventario/mantenimientos/tecnicos', { signal })
			.then(data => {
				technicianCache = normalizeTechnicians(data?.items ?? data)
				return technicianCache
			})
			.finally(() => { technicianRequest = null })
		return technicianRequest
	},
}
