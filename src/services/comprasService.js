import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const BASE_URL = '/apps/empleados/compras/solicitudes'

export async function listarSolicitudes(params = {}) {
	const response = await axios.get(generateUrl(BASE_URL), { params })
	return response.data
}

export async function listarPendientes(params = {}) {
	const response = await axios.get(generateUrl(`${BASE_URL}/pendientes`), { params })
	return response.data
}

export async function obtenerSolicitud(id) {
	const response = await axios.get(generateUrl(`${BASE_URL}/${id}`))
	return response.data
}

export async function crearSolicitud(payload) {
	const response = await axios.post(generateUrl(BASE_URL), payload)
	return response.data
}

export async function actualizarSolicitud(id, payload) {
	const response = await axios.put(generateUrl(`${BASE_URL}/${id}`), payload)
	return response.data
}

export async function enviarAutorizacion(id) {
	const response = await axios.post(generateUrl(`${BASE_URL}/${id}/enviar-autorizacion`), {})
	return response.data
}

export async function autorizarSolicitud(id, comentario = '') {
	const response = await axios.post(generateUrl(`${BASE_URL}/${id}/autorizar`), {
		comentario,
	})
	return response.data
}

export async function rechazarSolicitud(id, comentario = '') {
	const response = await axios.post(generateUrl(`${BASE_URL}/${id}/rechazar`), {
		comentario,
	})
	return response.data
}

export async function cancelarSolicitud(id, comentario = '') {
	const response = await axios.post(generateUrl(`${BASE_URL}/${id}/cancelar`), {
		comentario,
	})
	return response.data
}

export async function obtenerContextoCompras() {
	const response = await axios.get(generateUrl('/apps/empleados/compras/contexto'))
	return response.data
}

export const guardarDocumentoSolicitud = async (id) => {
	const response = await axios.post(
		generateUrl('/apps/empleados/compras/solicitudes/{id}/documento/guardar', { id }),
	)

	return response.data
}
export const subirDocumentoFirmadoSolicitud = async (id, file) => {
	const formData = new FormData()
	formData.append('archivo', file)

	const response = await axios.post(
		generateUrl('/apps/empleados/compras/solicitudes/{id}/documento/firmado', { id }),
		formData,
		{
			headers: {
				'Content-Type': 'multipart/form-data',
			},
		},
	)

	return response.data
}
