import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export async function getSimulacionEmpleados(params = {}) {
	const response = await axios.get(
		generateUrl('/apps/empleados/simulacion-oficina/empleados'),
		{ params },
	)

	// BaseController extends OCSController → payload en ocs.data
	const payload = response?.data?.ocs?.data ?? response?.data ?? {}
	return {
		periodo: payload.periodo ?? null,
		eventosHoy: payload.eventosHoy ?? null,
		empleados: Array.isArray(payload.empleados) ? payload.empleados : [],
	}
}

export async function getSimulacionStatuses(config = {}) {
	const response = await axios.get(
		generateUrl('/apps/empleados/simulacion-oficina/statuses'),
		config,
	)
	const payload = response?.data?.ocs?.data ?? response?.data ?? {}
	return Array.isArray(payload.statuses) ? payload.statuses : []
}
