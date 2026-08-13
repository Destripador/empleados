/* eslint-disable camelcase */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const appUrl = (path) => generateUrl(`/apps/empleados${path}`)

export default {
	// =========================
	// Modelos
	// =========================

	async getModelos(params = {}) {
		const response = await axios.get(appUrl('/GetInventarioModelos'), { params })
		return response.data?.ocs?.data ?? response.data
	},

	async getModelo(id_modelo) {
		const response = await axios.post(appUrl('/GetInventarioModelo'), { id_modelo })
		return response.data?.ocs?.data ?? response.data
	},

	async crearModelo(data) {
		const response = await axios.post(appUrl('/CrearInventarioModelo'), data)
		return response.data?.ocs?.data ?? response.data
	},

	async actualizarModelo(data) {
		const response = await axios.post(appUrl('/ActualizarInventarioModelo'), data)
		return response.data?.ocs?.data ?? response.data
	},

	async eliminarModelo(id_modelo) {
		const response = await axios.post(appUrl('/EliminarInventarioModelo'), { id_modelo })
		return response.data?.ocs?.data ?? response.data
	},

	// =========================
	// Equipos
	// =========================

	async getEquipos(params = {}) {
		const response = await axios.get(appUrl('/GetInventarioComputo'), { params })
		return response.data?.ocs?.data ?? response.data
	},

	async getEquipo(id_equipo) {
		const response = await axios.post(appUrl('/GetInventarioEquipo'), { id_equipo })
		return response.data?.ocs?.data ?? response.data
	},

	async getHistorialEquipo(id_equipo, params = {}) {
		const response = await axios.get(appUrl(`/inventario/equipos/${id_equipo}/historial`), { params })
		return response.data?.ocs?.data ?? response.data
	},

	async getEquiposEmpleado(id_empleado) {
		const response = await axios.post(appUrl('/GetInventarioEmpleado'), { id_empleado })
		return response.data?.ocs?.data ?? response.data
	},

	async crearEquipo(data) {
		const response = await axios.post(appUrl('/CrearInventarioEquipo'), data)
		return response.data?.ocs?.data ?? response.data
	},

	async actualizarEquipo(data) {
		const response = await axios.post(appUrl('/ActualizarInventarioEquipo'), data)
		return response.data?.ocs?.data ?? response.data
	},

	async eliminarEquipo(id_equipo) {
		const response = await axios.post(appUrl('/EliminarInventarioEquipo'), { id_equipo })
		return response.data?.ocs?.data ?? response.data
	},

	// =========================
	// Soporte
	// =========================

	async getSoporteEquipo(id_equipo) {
		const response = await axios.post(appUrl('/GetSoporteEquipo'), { id_equipo })
		return response.data?.ocs?.data ?? response.data
	},

	async crearSoporte(data) {
		const response = await axios.post(appUrl('/CrearSoporteEquipo'), data)
		return response.data?.ocs?.data ?? response.data
	},

	async actualizarSoporte(data) {
		const response = await axios.post(appUrl('/ActualizarSoporteEquipo'), data)
		return response.data?.ocs?.data ?? response.data
	},

	async eliminarSoporte(id_soporte) {
		const response = await axios.post(appUrl('/EliminarSoporteEquipo'), { id_soporte })
		return response.data?.ocs?.data ?? response.data
	},
}
