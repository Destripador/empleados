<template>
	<div v-if="loading">
		<div class="center-screen">
			<NcLoadingIcon :size="64" appearance="dark" :name="t('empleados', 'Loading...')" />
		</div>
	</div>
	<div v-else class="card">
		<div class="card-header">
			<h3 class="card-title">
				{{ t('empleados', 'Historial de actividades') }}
			</h3>

			<button class="filtros-btn" :class="{ activo: hayFiltrosActivos }" @click="abrirModal">
				<FilterVariantIcon :size="18" />
				<span>{{ t('empleados', 'Filtros') }}</span>
				<span v-if="hayFiltrosActivos" class="filtros-dot" />
			</button>
		</div>

		<div class="scroll-container">
			<div v-if="loadingFiltro" class="center-screen small">
				<NcLoadingIcon :size="32" appearance="dark" :name="t('empleados', 'Loading...')" />
			</div>

			<p v-else-if="movimientos.length === 0" class="empty">
				<BellOutlineIcon :size="40" fill-color="#c4c4c4" />
				<span>{{ t('empleados', 'No movements yet') }}</span>
			</p>

			<ul v-else class="timeline">
				<li v-for="mov in movimientos" :key="mov.id" class="timeline-item">
					<div class="timeline-icon" :class="`bg-${getTipo(mov).color}`">
						<component :is="getTipo(mov).icon" :size="18" fill-color="#ffffff" />
					</div>
					<div class="timeline-content">
						<div class="timeline-header">
							<span class="timeline-texto" v-html="formatMensaje(mov)" />
						</div>
						<div class="timeline-fecha">
							<CalendarBlankOutlineIcon :size="14" fill-color="#8a8a8a" />
							<span>{{ formatFecha(mov.fecha) }}</span>
						</div>
					</div>
				</li>
			</ul>
		</div>

		<!-- Modal de filtros -->
		<NcModal v-if="modalAbierto" size="normal" @close="modalAbierto = false">
			<div class="modal-filtros">
				<!-- Atrapa el autofocus de NcModal para que no caiga en el primer NcSelect
                    y lo abra solo. No es visible ni interactivo para el usuario. -->
				<div ref="focusTrap" tabindex="-1" class="focus-trap-inicial" />

				<h3 class="modal-titulo">
					{{ t('empleados', 'Filtros') }}
				</h3>

				<div class="filtro-campo">
					<label>{{ t('empleados', 'Módulo') }}</label>
					<NcSelect
						v-model="borrador.modulo"
						:options="opcionesModulo"
						label="nombre"
						:reduce="o => o.id"
						:placeholder="t('empleados', 'Todos')"
						:clearable="true" />
				</div>

				<div class="filtro-campo">
					<label>{{ t('empleados', 'Empleado') }}</label>
					<NcSelect
						v-model="borrador.idEmpleado"
						:options="opcionesEmpleado"
						label="nombre"
						:reduce="o => o.id_empleado"
						:placeholder="t('empleados', 'Todos')"
						:clearable="true" />
				</div>

				<div v-if="borrador.modulo" class="filtro-campo">
					<label>{{ t('empleados', 'Tipo de movimiento') }}</label>
					<NcSelect
						v-model="borrador.tipo"
						:options="opcionesTipoActual"
						label="nombre"
						:reduce="o => o.id"
						:placeholder="t('empleados', 'Todos')"
						:clearable="true" />
				</div>

				<div class="filtro-fechas">
					<div class="filtro-campo">
						<label>{{ t('empleados', 'Desde') }}</label>
						<input v-model="borrador.desde" type="date" class="input-fecha">
					</div>
					<div class="filtro-campo">
						<label>{{ t('empleados', 'Hasta') }}</label>
						<input v-model="borrador.hasta" type="date" class="input-fecha">
					</div>
				</div>

				<div class="modal-acciones">
					<NcButton type="tertiary" @click="limpiarFiltros">
						{{ t('empleados', 'Limpiar') }}
					</NcButton>
					<NcButton type="primary" @click="aplicarFiltros">
						{{ t('empleados', 'Aplicar') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import { NcLoadingIcon, NcSelect, NcModal, NcButton } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import CheckCircleOutlineIcon from 'vue-material-design-icons/CheckCircleOutline.vue'
import CloseCircleOutlineIcon from 'vue-material-design-icons/CloseCircleOutline.vue'
import ClockOutlineIcon from 'vue-material-design-icons/ClockOutline.vue'
import PlusCircleOutlineIcon from 'vue-material-design-icons/PlusCircleOutline.vue'
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'
import CancelIcon from 'vue-material-design-icons/Cancel.vue'
import BellOutlineIcon from 'vue-material-design-icons/BellOutline.vue'
import CalendarBlankOutlineIcon from 'vue-material-design-icons/CalendarBlankOutline.vue'
import FilterVariantIcon from 'vue-material-design-icons/FilterVariant.vue'

// Estado "vacío" de los filtros: todos por default (sin filtrar nada).
const filtrosVacios = () => ({
	modulo: null,
	idEmpleado: null,
	tipo: null,
	desde: null,
	hasta: null,
})

export default {
	name: 'MovimientosSettings',
	components: {
		NcLoadingIcon,
		NcSelect,
		NcModal,
		NcButton,
		CheckCircleOutlineIcon,
		CloseCircleOutlineIcon,
		ClockOutlineIcon,
		PlusCircleOutlineIcon,
		PencilOutlineIcon,
		CancelIcon,
		BellOutlineIcon,
		CalendarBlankOutlineIcon,
		FilterVariantIcon,
	},
	data() {
		return {
			loading: true,
			loadingFiltro: false,
			movimientos: [],

			modalAbierto: false,

			// Filtros que ya están aplicados (los que se usan para pedir datos al backend)
			filtrosAplicados: filtrosVacios(),
			// Filtros que se están editando dentro del modal, no aplican hasta dar "Aplicar"
			borrador: filtrosVacios(),

			opcionesModulo: [],
			opcionesEmpleado: [],

			// Tipos de movimiento disponibles, agrupados por módulo.
			tiposPorModulo: {
				vacaciones: [
					{ id: 'creacion', nombre: t('empleados', 'Creaciones') },
					{ id: 'edicion', nombre: t('empleados', 'Ediciones') },
					{ id: 'cancelacion', nombre: t('empleados', 'Cancelaciones') },
					{ id: 'aprobacion_parcial', nombre: t('empleados', 'Aprobaciones parciales') },
					{ id: 'aprobacion_completa', nombre: t('empleados', 'Aprobaciones completas') },
					{ id: 'rechazo', nombre: t('empleados', 'Rechazos') },
					{ id: 'recordatorio', nombre: t('empleados', 'Recordatorios') },
					{ id: 'recordatorio_sistema', nombre: t('empleados', 'Recordatorios automáticos') },
					{ id: 'edicion_manual_dias', nombre: t('empleados', 'Ajustes manuales de días') },
					{ id: 'edicion_manual_acumulado', nombre: t('empleados', 'Ajustes manuales de acumulado') },
					{ id: 'aniversario_actualizado', nombre: t('empleados', 'Aniversarios actualizados') },
					{ id: 'acumulado_actualizado', nombre: t('empleados', 'Acumulados actualizados') },
					{ id: 'dias_actualizados', nombre: t('empleados', 'Días actualizados') },
				],
				actividades: [
					{ id: 'creacion', nombre: t('empleados', 'Creaciones') },
					{ id: 'edicion', nombre: t('empleados', 'Ediciones') },
					{ id: 'eliminacion', nombre: t('empleados', 'Eliminaciones') },
					{ id: 'importacion', nombre: t('empleados', 'Importaciones') },
				],
				equipos: [
					{ id: 'creacion', nombre: t('empleados', 'Creaciones') },
					{ id: 'edicion', nombre: t('empleados', 'Ediciones') },
					{ id: 'eliminacion', nombre: t('empleados', 'Eliminaciones') },
					{ id: 'importacion', nombre: t('empleados', 'Importaciones') },
				],
				puestos: [
					{ id: 'creacion', nombre: t('empleados', 'Creaciones') },
					{ id: 'edicion', nombre: t('empleados', 'Ediciones') },
					{ id: 'eliminacion', nombre: t('empleados', 'Eliminaciones') },
					{ id: 'importacion', nombre: t('empleados', 'Importaciones') },
				],
				areas: [
					{ id: 'creacion', nombre: t('empleados', 'Creaciones') },
					{ id: 'edicion', nombre: t('empleados', 'Ediciones') },
					{ id: 'eliminacion', nombre: t('empleados', 'Eliminaciones') },
					{ id: 'importacion', nombre: t('empleados', 'Importaciones') },
				],
				clientes: [
					{ id: 'creacion', nombre: t('empleados', 'Creaciones') },
					{ id: 'edicion', nombre: t('empleados', 'Ediciones') },
					{ id: 'eliminacion', nombre: t('empleados', 'Eliminaciones') },
					{ id: 'importacion', nombre: t('empleados', 'Importaciones') },
				],
				honorarios: [
					{ id: 'creacion', nombre: t('empleados', 'Creaciones') },
					{ id: 'edicion', nombre: t('empleados', 'Ediciones') },
					{ id: 'eliminacion', nombre: t('empleados', 'Eliminaciones') },
					{ id: 'finalizacion', nombre: t('empleados', 'Finalizaciones') },
					{ id: 'reactivacion', nombre: t('empleados', 'Reactivaciones') },
					{ id: 'envio_solicitud', nombre: t('empleados', 'Envíos de solicitud') },
					{ id: 'descarga_masiva', nombre: t('empleados', 'Descargas masivas') },
					{ id: 'notificacion_pendientes', nombre: t('empleados', 'Notificaciones de pendientes') },
				],
				honorarios_parcialidades: [
					{ id: 'creacion', nombre: t('empleados', 'Creaciones') },
					{ id: 'pago', nombre: t('empleados', 'Pagos registrados') },
					{ id: 'facturacion', nombre: t('empleados', 'Facturaciones') },
					{ id: 'cancelacion_pago', nombre: t('empleados', 'Cancelaciones de pago') },
					{ id: 'cancelacion_factura', nombre: t('empleados', 'Cancelaciones de factura') },
					{ id: 'honorario_completado', nombre: t('empleados', 'Honorarios completados') },
					{ id: 'honorario_reactivado', nombre: t('empleados', 'Honorarios reactivados') },
				],
			},
		}
	},
	computed: {
		hayFiltrosActivos() {
			const f = this.filtrosAplicados
			return !!(f.modulo || f.idEmpleado || f.tipo || f.desde || f.hasta)
		},
		// Catálogo de tipos correspondiente al módulo seleccionado en el modal
		// (borrador, no filtrosAplicados, porque se usa mientras se edita el filtro).
		opcionesTipoActual() {
			return this.tiposPorModulo[this.borrador.modulo] ?? []
		},
	},
	watch: {
		// Si el usuario cambia de módulo dentro del modal, limpiamos el tipo
		// seleccionado para no dejar un filtro inválido (p. ej. "cancelacion"
		// aplicado sobre el módulo "actividades", que no tiene ese tipo).
		'borrador.modulo'(nuevoModulo, moduloAnterior) {
			if (nuevoModulo !== moduloAnterior) {
				this.borrador.tipo = null
			}
		},
	},
	async mounted() {
		await Promise.all([
			this.fetchOpcionesFiltro(),
			this.fetchMovimientos(),
		])
		this.loading = false
	},
	methods: {
		t,
		formatFecha(fecha) {
			const d = new Date(fecha.replace(' ', 'T') + 'Z')

			return `${d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })} · ${d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}`
		},
		formatMensaje(mov) {
			let mensaje = mov.mensaje || ''

			if (!mensaje) return ''

			const nombres = [mov.nombre_actor, mov.nombre_afectado]
				.filter(n => n && String(n).trim() !== '')
				.map(n => String(n))

			if (!mov.nombre_actor && mensaje.startsWith('Sistema')) {
				nombres.push('Sistema')
			}

			const unicos = [...new Set(nombres)].sort((a, b) => b.length - a.length)

			unicos.forEach((nombre) => {
				const escapado = nombre.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
				const regex = new RegExp(escapado, 'g')

				mensaje = mensaje.replace(
					regex,
					`<strong>${nombre}</strong>`,
				)
			})

			// Convertir **texto** a negritas
			mensaje = mensaje.replace(
				/\*\*(.*?)\*\*/g,
				'<strong>$1</strong>',
			)

			return mensaje
		},
		getTipo(mov) {
			const mapa = {
				creacion: { icon: 'PlusCircleOutlineIcon', color: 'blue' },
				edicion: { icon: 'PencilOutlineIcon', color: 'purple' },
				eliminacion: { icon: 'CloseCircleOutlineIcon', color: 'red' },
				importacion: { icon: 'PlusCircleOutlineIcon', color: 'blue' },
				cancelacion: { icon: 'CancelIcon', color: 'gray' },
				rechazo: { icon: 'CloseCircleOutlineIcon', color: 'red' },
				aprobacion_parcial: { icon: 'CheckCircleOutlineIcon', color: 'orange' },
				aprobacion_completa: { icon: 'CheckCircleOutlineIcon', color: 'green' },
				recordatorio: { icon: 'ClockOutlineIcon', color: 'orange' },
				recordatorio_sistema: { icon: 'ClockOutlineIcon', color: 'orange' },
				edicion_manual_dias: { icon: 'PencilOutlineIcon', color: 'purple' },
				edicion_manual_acumulado: { icon: 'PencilOutlineIcon', color: 'purple' },
				aniversario_actualizado: { icon: 'CheckCircleOutlineIcon', color: 'green' },
				acumulado_actualizado: { icon: 'CheckCircleOutlineIcon', color: 'green' },
				dias_actualizados: { icon: 'CheckCircleOutlineIcon', color: 'green' },
			}

			const tipo = (mov.tipo_movimiento || '').toLowerCase()
			if (mapa[tipo]) return mapa[tipo]

			const texto = (mov.mensaje || '').toLowerCase()
			if (texto.includes('aprob')) return { icon: 'CheckCircleOutlineIcon', color: 'green' }
			if (texto.includes('rechaz')) return { icon: 'CloseCircleOutlineIcon', color: 'red' }
			if (texto.includes('cancel')) return { icon: 'CancelIcon', color: 'gray' }
			if (texto.includes('eliminado') || texto.includes('elimin')) return { icon: 'CloseCircleOutlineIcon', color: 'red' }
			if (texto.includes('solicit') || texto.includes('pendiente')) return { icon: 'ClockOutlineIcon', color: 'orange' }
			if (texto.includes('cre') || texto.includes('agreg') || texto.includes('nuev') || texto.includes('import')) return { icon: 'PlusCircleOutlineIcon', color: 'blue' }
			if (texto.includes('edit') || texto.includes('actualiz') || texto.includes('modific')) return { icon: 'PencilOutlineIcon', color: 'purple' }
			return { icon: 'BellOutlineIcon', color: 'blue' }
		},
		async abrirModal() {
			this.borrador = { ...this.filtrosAplicados }
			this.modalAbierto = true
			await this.$nextTick()
			this.$refs.focusTrap?.focus()
		},
		async aplicarFiltros() {
			this.filtrosAplicados = { ...this.borrador }
			this.modalAbierto = false
			this.loadingFiltro = true
			await this.fetchMovimientos()
			this.loadingFiltro = false
		},
		async limpiarFiltros() {
			this.borrador = filtrosVacios()
			this.filtrosAplicados = filtrosVacios()
			this.modalAbierto = false
			this.loadingFiltro = true
			await this.fetchMovimientos()
			this.loadingFiltro = false
		},
		async fetchOpcionesFiltro() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetOpcionesFiltro'))
				const data = response?.data?.ocs?.data ?? { modulos: [], empleados: [] }
				this.opcionesModulo = data.modulos ?? []
				this.opcionesEmpleado = data.empleados ?? []
			} catch (err) {
			}
		},
		async fetchMovimientos() {
			try {
				const f = this.filtrosAplicados
				const params = {}
				if (f.modulo) params.modulo = f.modulo
				if (f.tipo) params.tipo = f.tipo
				if (f.idEmpleado) params.id_empleado = f.idEmpleado
				if (f.desde) params.desde = f.desde
				if (f.hasta) params.hasta = f.hasta

				const response = await axios.get(generateUrl('/apps/empleados/GetMovimientos'), { params })
				this.movimientos = response?.data?.ocs?.data ?? []
			} catch (err) {
				showError(t('empleados', 'Error al cargar movimientos'))
			}
		},
	},
}
</script>

<style scoped>
.card {
	max-width: 700px;
	margin: 0 auto;
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-main-background);
	overflow: hidden;
}

.card-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 14px 16px;
	border-bottom: 1px solid var(--color-border);
}

.card-title {
	margin: 0;
	font-size: 1rem;
	font-weight: 700;
	color: var(--color-main-text);
}

/* ---------- Botón de filtros ---------- */
.filtros-btn {
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 6px 12px;
	border-radius: 20px;
	border: 1px solid var(--color-border);
	background: var(--color-background-hover);
	color: var(--color-main-text);
	font-size: 0.85rem;
	font-weight: 600;
	cursor: pointer;
	position: relative;
	transition: background-color 0.15s ease, border-color 0.15s ease;
}

.filtros-btn:hover {
	background: var(--color-background-dark);
}

.filtros-btn.activo {
	border-color: var(--color-primary-element);
	color: var(--color-primary-element);
}

.filtros-dot {
	width: 7px;
	height: 7px;
	border-radius: 50%;
	background: var(--color-primary-element);
	margin-left: 2px;
}

/* ---------- Modal de filtros ---------- */
.modal-filtros {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 16px;
	min-width: 320px;
}

.modal-titulo {
	margin: 0;
	font-size: 1.1rem;
	font-weight: 700;
	color: var(--color-main-text);
}

.filtro-campo {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.filtro-campo label {
	font-size: 0.8rem;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.focus-trap-inicial {
	position: absolute;
	width: 1px;
	height: 1px;
	overflow: hidden;
	outline: none;
}

.filtro-fechas {
	display: flex;
	gap: 24px;
	margin-top: 4px;
}

.filtro-fechas .filtro-campo {
	flex: 1;
	gap: 8px;
}

.input-fecha {
	height: 42px;
	border-radius: var(--border-radius, 6px);
	border: 1px solid var(--color-border-maxcontrast);
	background: var(--color-main-background);
	color: var(--color-main-text);
	padding: 0 14px;
	font-size: 0.95rem;
	width: 100%;
	box-sizing: border-box;
}

.input-fecha:focus {
	border-color: var(--color-primary-element);
	outline: none;
}

.modal-acciones {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 8px;
}

/* ---------- Contenedor con scroll (la "tablita") ---------- */
.scroll-container {
	max-height: 420px;
	overflow-y: auto;
	padding: 6px 10px;
}

/* ---------- Estado vacío ---------- */
.empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 10px;
	text-align: center;
	color: var(--color-text-maxcontrast);
	padding: 50px 20px;
	font-size: 0.95rem;
}

.center-screen {
	display: flex;
	justify-content: center;
	align-items: center;
	min-height: 40vh;
}

.center-screen.small {
	min-height: 120px;
}

/* ---------- Timeline ---------- */
.timeline {
	list-style: none;
	margin: 0;
	padding: 0;
	position: relative;
}

.timeline-item {
	position: relative;
	display: flex;
	gap: 16px;
	padding: 14px 8px;
	border-radius: 12px;
	transition: background-color 0.15s ease;
}

.timeline-item:hover {
	background-color: var(--color-background-hover);
}

.timeline-item:not(:last-child)::before {
	content: '';
	position: absolute;
	left: 25px;
	top: 46px;
	bottom: -14px;
	width: 2px;
	background: linear-gradient(to bottom, var(--color-border), transparent);
}

.timeline-icon {
	flex: 0 0 auto;
	width: 34px;
	height: 34px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
	z-index: 1;
}

.timeline-content {
	flex: 1;
	min-width: 0;
	padding-top: 2px;
}

.timeline-header {
	margin-bottom: 4px;
}

.timeline-texto {
	font-size: 0.92rem;
	line-height: 1.5;
	color: var(--color-main-text);
}

.timeline-texto :deep(strong) {
	color: var(--color-main-text);
	font-weight: 700;
}

.timeline-fecha {
	display: flex;
	align-items: center;
	gap: 5px;
	font-size: 0.78rem;
	color: var(--color-text-maxcontrast);
}

/* ---------- Colores de los íconos según tipo de movimiento ---------- */
.bg-green {
	background: linear-gradient(135deg, #4caf50, #388e3c);
}
.bg-red {
	background: linear-gradient(135deg, #ef5350, #c62828);
}
.bg-orange {
	background: linear-gradient(135deg, #ffa726, #ef6c00);
}
.bg-blue {
	background: linear-gradient(135deg, #42a5f5, #1565c0);
}
.bg-purple {
	background: linear-gradient(135deg, #ab47bc, #6a1b9a);
}
.bg-gray {
	background: linear-gradient(135deg, #9e9e9e, #616161);
}
</style>
