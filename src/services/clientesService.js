/* eslint-disable camelcase */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const appUrl = (path) => generateUrl(`/apps/empleados${path}`)

const unwrap = (response) => response.data?.ocs?.data ?? response.data

export function clienteLogoUrl(id, bust = null) {
	if (!id) {
		return null
	}

	const url = appUrl(`/clientes/${id}/logo`)
	if (bust === null || bust === undefined || bust === '') {
		return url
	}

	return `${url}?t=${encodeURIComponent(String(bust))}`
}

export default {
	async getCompanies() {
		const response = await axios.get(appUrl('/GetCompaniesGroups'))
		return unwrap(response) || []
	},

	async getEmployeesLookup() {
		const response = await axios.get(appUrl('/GetClientesEmpleadosLookup'))
		return unwrap(response) || []
	},

	async getDashboardSummary(filters = {}) {
		const response = await axios.post(appUrl('/GetClientesDashboard'), filters)
		return unwrap(response) || {}
	},

	async getDashboardCliente(id, filters = {}) {
		const response = await axios.post(appUrl('/GetClientesDashboardCliente'), {
			id,
			...filters,
		})
		return unwrap(response) || {}
	},

	async uploadLogo(id, file) {
		const form = new FormData()
		form.append('logo', file)
		const response = await axios.post(appUrl(`/clientes/${id}/logo`), form)
		return unwrap(response)
	},

	async deleteLogo(id) {
		const response = await axios.delete(appUrl(`/clientes/${id}/logo`))
		return unwrap(response)
	},
}
