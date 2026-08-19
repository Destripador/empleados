import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const unwrapPayload = (response) => response?.data?.ocs?.data ?? response?.data ?? {}

export async function getSimulacionEmpleados(params = {}) {
	const response = await axios.get(
		generateUrl('/apps/empleados/simulacion-oficina/empleados'),
		{ params },
	)

	// BaseController extends OCSController → payload en ocs.data
	const payload = unwrapPayload(response)
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
	const payload = unwrapPayload(response)
	return Array.isArray(payload.statuses) ? payload.statuses : []
}

export async function getOfficeSimulationOnboardingStatus() {
	const response = await axios.get(
		generateUrl('/apps/empleados/simulacion-oficina/onboarding'),
	)
	const payload = unwrapPayload(response)

	return {
		completed: payload.completed === true,
		completedVersion: Number(payload.completedVersion) || 0,
		requiredVersion: Number(payload.requiredVersion) || 0,
	}
}

export async function completeOfficeSimulationOnboarding(version) {
	const response = await axios.post(
		generateUrl('/apps/empleados/simulacion-oficina/onboarding/complete'),
		{ version },
	)
	return unwrapPayload(response)
}
