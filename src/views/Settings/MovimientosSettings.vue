<template>
	<div v-if="loading" class="app-settings-loading" role="status">
		<NcLoadingIcon :size="40" :name="t('empleados', 'Loading...')" />
	</div>
	<div v-else class="app-settings-page app-settings-page--reading movements-page">
		<header class="app-settings-header">
			<p class="app-settings-eyebrow">
				{{ t('empleados', 'Movimientos') }}
			</p>
			<h2 class="app-settings-title">
				{{ t('empleados', 'Historial de actividades') }}
			</h2>
			<p class="app-settings-description">
				{{ t('empleados', 'Consulta las actividades realizadas dentro del módulo.') }}
			</p>
		</header>

		<section class="app-settings-panel movements-panel" aria-labelledby="movements-history-title">
			<div class="app-settings-panel__header movements-panel__header">
				<div>
					<h3 id="movements-history-title">
						{{ t('empleados', 'Historial de actividades') }}
					</h3>
				</div>

				<NcButton
					class="movements-filter-button"
					:class="{ 'movements-filter-button--active': hayFiltrosActivos }"
					type="secondary"
					:aria-label="etiquetaAriaFiltros"
					:aria-expanded="modalAbierto ? 'true' : 'false'"
					aria-haspopup="dialog"
					aria-controls="movimientos-filtros-modal"
					@click="abrirModal">
					<template #icon>
						<FilterVariantIcon :size="20" aria-hidden="true" />
					</template>
					<span>{{ t('empleados', 'Filtros') }}</span>
					<span
						v-if="hayFiltrosActivos"
						class="movements-filter-count"
						aria-hidden="true">
						{{ cantidadFiltrosActivos }}
					</span>
				</NcButton>
			</div>

			<div class="app-settings-panel__body movements-panel__body">
				<div
					class="movements-scroll"
					role="region"
					aria-labelledby="movements-history-title"
					tabindex="0">
					<div v-if="loadingFiltro" class="movements-loading" role="status">
						<NcLoadingIcon :size="32" :name="t('empleados', 'Loading...')" />
					</div>

					<div v-else-if="movimientos.length === 0" class="app-settings-empty movements-empty">
						<BellOutlineIcon :size="40" aria-hidden="true" />
						<p>{{ t('empleados', 'No movements yet') }}</p>
					</div>

					<ul v-else class="timeline">
						<li v-for="mov in movimientos" :key="mov.id" class="timeline-item">
							<div class="timeline-icon" :class="`bg-${getTipo(mov).color}`" aria-hidden="true">
								<component :is="getTipo(mov).icon" :size="18" />
							</div>
							<article class="timeline-content">
								<p class="timeline-actor">
									{{ nombreActorMovimiento(mov) }}
								</p>
								<p v-if="descripcionMovimiento(mov)" class="timeline-description">
									{{ descripcionMovimiento(mov) }}
								</p>
								<div class="timeline-date">
									<CalendarBlankOutlineIcon :size="14" aria-hidden="true" />
									<time :datetime="fechaIso(mov.fecha)">{{ formatFecha(mov.fecha) }}</time>
								</div>
							</article>
						</li>
					</ul>
				</div>
			</div>
		</section>

		<!-- Modal de filtros -->
		<NcModal
			v-if="modalAbierto"
			label-id="movimientos-filtros-title"
			size="normal"
			@close="modalAbierto = false">
			<div id="movimientos-filtros-modal" class="modal-filtros">
				<h3
					id="movimientos-filtros-title"
					ref="modalTitle"
					tabindex="-1"
					class="modal-titulo">
					{{ t('empleados', 'Filtros') }}
				</h3>

				<div class="filtro-campo">
					<label for="movements-filter-module">{{ t('empleados', 'Módulo') }}</label>
					<NcSelect
						v-model="borrador.modulo"
						input-id="movements-filter-module"
						label-outside
						:options="opcionesModulo"
						label="nombre"
						:reduce="o => o.id"
						:placeholder="t('empleados', 'Todos')"
						:clearable="true" />
				</div>

				<div class="filtro-campo">
					<label for="movements-filter-employee">{{ t('empleados', 'Empleado') }}</label>
					<NcSelect
						v-model="borrador.idEmpleado"
						input-id="movements-filter-employee"
						label-outside
						:options="opcionesEmpleado"
						label="nombre"
						:reduce="o => o.id_empleado"
						:placeholder="t('empleados', 'Todos')"
						:clearable="true" />
				</div>

				<div v-if="borrador.modulo" class="filtro-campo">
					<label for="movements-filter-type">{{ t('empleados', 'Tipo de movimiento') }}</label>
					<NcSelect
						v-model="borrador.tipo"
						input-id="movements-filter-type"
						label-outside
						:options="opcionesTipoActual"
						label="nombre"
						:reduce="o => o.id"
						:placeholder="t('empleados', 'Todos')"
						:clearable="true" />
				</div>

				<div class="filtro-fechas">
					<div class="filtro-campo">
						<label for="movements-filter-from">{{ t('empleados', 'Desde') }}</label>
						<input id="movements-filter-from"
							v-model="borrador.desde"
							type="date"
							class="input-fecha">
					</div>
					<div class="filtro-campo">
						<label for="movements-filter-to">{{ t('empleados', 'Hasta') }}</label>
						<input id="movements-filter-to"
							v-model="borrador.hasta"
							type="date"
							class="input-fecha">
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
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

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
		cantidadFiltrosActivos() {
			const f = this.filtrosAplicados
			return [f.modulo, f.idEmpleado, f.tipo, f.desde, f.hasta]
				.filter(valor => valor !== null && valor !== '').length
		},
		hayFiltrosActivos() {
			return this.cantidadFiltrosActivos > 0
		},
		etiquetaAriaFiltros() {
			const etiqueta = t('empleados', 'Filtros')
			return this.hayFiltrosActivos
				? `${etiqueta}: ${this.cantidadFiltrosActivos}`
				: etiqueta
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
		fechaIso(fecha) {
			return fecha ? `${String(fecha).replace(' ', 'T')}Z` : ''
		},
		formatFecha(fecha) {
			const d = new Date(this.fechaIso(fecha))

			return `${d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })} · ${d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}`
		},
		nombreActorMovimiento(mov) {
			const actor = String(mov?.nombre_actor || '').trim()
			return actor || t('empleados', 'Sistema')
		},
		descripcionMovimiento(mov) {
			const mensaje = String(mov?.mensaje || '')
				.replace(/\*\*(.*?)\*\*/g, '$1')
				.trim()

			if (!mensaje) return ''

			const actor = String(mov?.nombre_actor || '').trim()
			const prefijo = actor || (/^Sistema(?=\s|[-–—:])/i.test(mensaje) ? 'Sistema' : '')
			if (!prefijo) return mensaje

			const escapado = prefijo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
			const sinActor = mensaje
				.replace(new RegExp(`^${escapado}(?=\\s|[-–—:])\\s*(?:[-–—:]\\s*)?`, 'i'), '')
				.trim()

			if (!sinActor) return mensaje
			return sinActor.charAt(0).toLocaleUpperCase() + sinActor.slice(1)
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
			this.$refs.modalTitle?.focus()
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
.movements-panel__header {
	align-items: center;
}

.movements-panel__body {
	padding: 0;
}

.movements-filter-button {
	flex: 0 0 auto;
}

.movements-filter-button--active {
	color: var(--color-primary-element);
}

.movements-filter-count {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
	min-width: 20px;
	height: 20px;
	margin-inline-start: 2px;
	padding-inline: 6px;
	border-radius: 999px;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-size: 0.75rem;
	font-variant-numeric: tabular-nums;
	font-weight: 700;
	line-height: 1;
}

/* ---------- Modal de filtros ---------- */
.modal-filtros {
	display: flex;
	box-sizing: border-box;
	width: min(480px, calc(100vw - 32px));
	min-width: 0;
	padding: 24px;
	flex-direction: column;
	gap: 16px;
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

.modal-titulo:focus {
	border-radius: var(--border-radius);
	outline: 2px solid var(--color-primary-element);
	outline-offset: 3px;
}

.filtro-fechas {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
	margin-top: 4px;
}

.filtro-fechas .filtro-campo {
	gap: 8px;
	min-width: 0;
}

.input-fecha {
	box-sizing: border-box;
	width: 100%;
	min-height: 42px;
	padding: 0 12px;
	border: 1px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 0.95rem;
}

.input-fecha:focus-visible {
	border-color: var(--color-primary-element);
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.modal-acciones {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 8px;
}

/* ---------- Historial con scroll sólo cuando el contenido lo requiere ---------- */
.movements-scroll {
	max-height: min(68vh, 720px);
	overflow-y: auto;
	padding: 4px 12px 8px;
	scrollbar-width: thin;
}

.movements-scroll:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.movements-loading {
	display: grid;
	place-items: center;
	min-height: 180px;
	color: var(--color-text-maxcontrast);
}

.movements-empty {
	gap: 8px;
}

.movements-empty p {
	margin: 0;
}

/* ---------- Timeline ---------- */
.timeline {
	position: relative;
	min-width: 0;
	list-style: none;
	margin: 0;
	padding: 0;
}

.timeline-item {
	position: relative;
	display: grid;
	grid-template-columns: 36px minmax(0, 1fr);
	gap: 12px;
	padding: 14px 8px;
	border-radius: var(--border-radius-large);
	transition: background-color 0.15s ease;
}

.timeline-item:hover {
	background-color: var(--color-background-hover);
}

.timeline-item:not(:last-child)::before {
	content: '';
	position: absolute;
	top: 50px;
	bottom: -14px;
	left: 25px;
	width: 1px;
	background: var(--color-border);
}

.timeline-icon {
	position: relative;
	z-index: 1;
	display: flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	width: 34px;
	height: 34px;
	border-radius: 50%;
	box-shadow: 0 0 0 2px var(--color-main-background);
}

.timeline-content {
	min-width: 0;
	padding-top: 2px;
}

.timeline-actor,
.timeline-description {
	margin: 0;
}

.timeline-actor {
	color: var(--color-main-text);
	font-size: 0.9rem;
	font-weight: 700;
	line-height: 1.35;
	overflow-wrap: anywhere;
}

.timeline-description {
	margin-top: 2px;
	color: var(--color-text-maxcontrast);
	font-size: 0.92rem;
	line-height: 1.45;
	overflow-wrap: anywhere;
}

.timeline-date {
	display: flex;
	align-items: center;
	gap: 5px;
	margin-top: 5px;
	font-size: 0.78rem;
	color: var(--color-text-maxcontrast);
}

/* ---------- Colores de los íconos según tipo de movimiento ---------- */
.bg-green {
	background: var(--color-success-hover);
	color: var(--color-success-text);
}

.bg-red {
	background: var(--color-error-hover);
	color: var(--color-error-text);
}

.bg-orange {
	background: var(--color-warning-hover);
	color: var(--color-warning-text);
}

.bg-blue {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
}

.bg-purple {
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
}

.bg-gray {
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}

@media (max-width: 767px) {
	.movements-panel__header {
		align-items: flex-start;
	}

	.movements-filter-button {
		align-self: flex-start;
	}

	.movements-scroll {
		max-height: none;
		padding-inline: 10px;
	}

	.timeline-item {
		grid-template-columns: 32px minmax(0, 1fr);
		gap: 10px;
		padding: 12px 4px;
	}

	.timeline-item:not(:last-child)::before {
		top: 44px;
		bottom: -12px;
		left: 19px;
	}

	.timeline-icon {
		width: 30px;
		height: 30px;
	}

	.modal-filtros {
		padding: 18px;
	}

	.filtro-fechas {
		grid-template-columns: 1fr;
	}
}
</style>
