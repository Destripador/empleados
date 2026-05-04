<template id="content">
	<NcAppContent name="Empleados – Actividades">
		<div v-if="loading">
			<div class="center">
				<NcLoadingIcon :size="64" appearance="dark" name="Loading on light background" />
			</div>
		</div>

		<div v-else>
			<div class="container">
				<div class="main-content-card">
					<div class="pack_card">
						<p class="description">
							{{ t('empleados', 'El empleado debe generar el reporte cada día · registrar cada procedimiento por separado · si requiere más registros, usar un nuevo formulario.') }}
						</p>
						<div class="bottom">
							<NcButton
								aria-label="center (default)"
								type="primary"
								wide
								@click="openModal()">
								<template #icon>
									<Check :size="20" />
								</template>
								{{ t('empleados', 'Create report') }}
							</NcButton>
						</div>
					</div>
				</div>
			</div>

			<div class="filters-card">
				<div class="filters-header">
					<div>
						<h3>{{ t('empleados', 'Review my reports') }}</h3>
						<p>
							{{ t('empleados', 'Filter your reports by date, project, activity or description.') }}
						</p>
					</div>

					<NcButton
						:aria-label="t('empleados', 'Clear filters')"
						@click="clearFilters">
						{{ t('empleados', 'Clear filters') }}
					</NcButton>
				</div>

				<div class="filters-grid">
					<NcDateTimePicker
						v-model="filter_fecha_inicio"
						class="filter-control"
						type="date"
						:placeholder="t('empleados', 'From date')" />

					<NcDateTimePicker
						v-model="filter_fecha_fin"
						class="filter-control"
						type="date"
						:placeholder="t('empleados', 'To date')" />

					<NcSelect
						v-model="filter_cliente"
						:input-label="t('empleados', 'Project')"
						:options="actividades"
						class="filter-control" />

					<NcSelect
						v-model="filter_actividad"
						:input-label="t('empleados', 'Activity')"
						:options="listas"
						class="filter-control" />

					<NcTextField
						class="filter-control filter-search"
						:value.sync="filter_busqueda"
						:label="t('empleados', 'Search description, project or activity')" />
				</div>

				<div class="filters-summary">
					<span>
						{{ t('empleados', 'Reports') }}:
						<strong>{{ historialFiltrado.length }}</strong>
					</span>

					<span>
						{{ t('empleados', 'Total hours') }}:
						<strong>{{ totalHorasFiltradas }}</strong>
					</span>
				</div>
			</div>

			<VirtualList
				v-if="historialFiltrado.length > 0"
				class="list"
				:data-sources="historialFiltrado"
				:data-key="'id'"
				:data-component="rowComponent"
				:keeps="24"
				:estimate-size="52"
				:extra-props="{ listas, actividades }" />

			<div v-else id="emptycontent" />
		</div>
		<NcModal
			v-if="modal"
			ref="modalRef"
			:name="t('empleados', 'Add new activity')"
			@close="closeModal">
			<div class="modal__content">
				<div class="form-group">
					<input
						ref="trapFocus"
						type="text"
						style="position:absolute;opacity:0;height:0;width:0;pointer-events:none;">
					<NcSelect
						v-model="activity_selected"
						:input-label="t('empleados', 'Proyect')"
						:options="actividades"
						class="fit" />
					<div class="time-selector">
						<div class="wrapper">
							<NcDateTimePicker
								v-model="time"
								class="date-picker"
								type="date" />
						</div>
						<div class="estimatetime">
							<NcTextField
								required
								:value.sync="time_activity"
								type="number"
								min="1"
								:label="t('empleados', 'Estimate time')" />
						</div>
						<div class="radios">
							<NcCheckboxRadioSwitch
								v-model="type_time"
								:button-variant="true"
								value="minutos"
								name="Minutos"
								type="radio"
								button-variant-grouped="horizontal">
								Minutos
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch
								v-model="type_time"
								:button-variant="true"
								value="horas"
								name="Horas"
								type="radio"
								button-variant-grouped="horizontal">
								Horas
							</NcCheckboxRadioSwitch>
						</div>
					</div>
					<NcSelect
						v-model="listas_selected"
						:input-label="t('empleados', 'Activity')"
						:options="listas"
						class="fit" />
					<br>
					<NcTextArea
						required
						resize="vertical"
						:value.sync="description_activity"
						class="top"
						:label="t('empleados', 'Description activity')" />
					<div class="save top">
						<NcButton
							class=""
							:aria-label="t('empleados', 'Create Activity')"
							type="primary"
							:disabled="!isFormValid"
							@click="create()">
							{{ t('empleados', 'Create Activity') }}
						</NcButton>
					</div>
				</div>
			</div>
		</NcModal>
	</NcAppContent>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import Check from 'vue-material-design-icons/Check.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import VirtualList from 'vue-virtual-scroll-list'
import ReportRow from '../Helpers/Lists/ReportRow.vue'
import mitt from 'mitt'

import {
	NcLoadingIcon,
	NcAppContent,
	NcButton,
	NcTextArea,
	NcCheckboxRadioSwitch,
	NcModal,
	NcTextField,
	NcDateTimePicker,
	NcSelect,
} from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'Reports',
	components: {
		NcLoadingIcon,
		NcAppContent,
		NcButton,
		Check,
		NcTextArea,
		NcCheckboxRadioSwitch,
		NcModal,
		NcTextField,
		NcDateTimePicker,
		NcSelect,
		VirtualList,
	},
	data() {
		return {
			rowComponent: ReportRow,
			reloadBus: mitt(),
			loading: true,
			historial: [],
			modal: false,
			description_activity: '',
			type_time: 'minutos',
			time_activity: 0,
			time: new Date(),
			listas: [],
			actividades: [],
			activity_selected: null,
			listas_selected: null,
			temp_listas: [],
			filter_fecha_inicio: null,
			filter_fecha_fin: null,
			filter_cliente: null,
			filter_actividad: null,
			filter_busqueda: '',
		}
	},
	computed: {
		isFormValid() {
			const clienteId = this.activity_selected?.id
			const actividadId = this.listas_selected?.id
			const tiempo = Number(this.time_activity)
			const descripcion = String(this.description_activity || '').trim()
			const fecha = this.time instanceof Date ? this.time : new Date(this.time)

			return Boolean(
				clienteId !== null
				&& clienteId !== undefined
				&& actividadId !== null
				&& actividadId !== undefined
				&& Number.isFinite(tiempo)
				&& tiempo > 0
				&& descripcion.length > 0
				&& !isNaN(fecha.getTime()),
			)
		},
		historialFiltrado() {
			const fechaInicio = this.normalizeDateOnly(this.filter_fecha_inicio)
			const fechaFin = this.normalizeDateOnly(this.filter_fecha_fin)

			const clienteId = this.getOptionId(this.filter_cliente)
			const actividadId = this.getOptionId(this.filter_actividad)
			const busqueda = String(this.filter_busqueda || '').trim().toLowerCase()

			return this.historial.filter((reporte) => {
				const fechaReporte = this.normalizeDateOnly(reporte.fecha_registro)

				if (fechaInicio && fechaReporte && fechaReporte < fechaInicio) {
					return false
				}

				if (fechaFin && fechaReporte && fechaReporte > fechaFin) {
					return false
				}

				if (clienteId !== null && Number(this.getReportClientId(reporte)) !== Number(clienteId)) {
					return false
				}

				if (actividadId !== null && Number(this.getReportActivityId(reporte)) !== Number(actividadId)) {
					return false
				}

				if (busqueda) {
					const texto = [
						reporte.descripcion,
						reporte.clienteNombre,
						reporte.actividadNombre,
						reporte.fecha_registro,
						reporte.tiempo_registrado,
					].join(' ').toLowerCase()

					if (!texto.includes(busqueda)) {
						return false
					}
				}

				return true
			})
		},

		totalHorasFiltradas() {
			const totalMinutos = this.historialFiltrado.reduce((total, reporte) => {
				return total + Number(reporte.tiempo_registrado || 0)
			}, 0)

			return new Intl.NumberFormat('es-MX', {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			}).format(totalMinutos / 60)
		},
	},
	async mounted() {
		this.loading = true
		try {
			await Promise.all([
				await this.GetCompaniesGroups(),
				await this.GetActividades(),
			])
			await this.gethistorial()
		} finally {
			this.loading = false
		}
		this.$bus.on('gethistorial', () => {
			this.gethistorial()
		})
	},
	methods: {
		t, // expone t al template

		openModal() {
			this.modal = true
			this.GetActividades()
			this.GetCompaniesGroups()
		},

		closeModal() {
			this.modal = false
			this.resetForm()
		},

		async gethistorial() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetReportesAll'))
				const data = response?.data?.ocs?.data
				const arr = Array.isArray(data) ? data : []

				const actividadesMap = new Map(
					(this.listas || []).map(c => [Number(c.id), c.name || c.nombre || c.label]),
				)

				const clientesMap = new Map(
					(this.temp_listas || []).map(a => [Number(a.id), a.label || a.nombre || a.name]),
				)

				this.historial = arr
					.filter(r => r && typeof r === 'object')
					.map((r, i) => {
						// fuerza PK real (id_reporte) y siempre string
						const rawId = r.id_reporte ?? r.idReporte ?? r.Id_reporte ?? r.id ?? i
						const id = String(rawId)

						const idCliente = r.id_cliente ?? r.idCliente ?? r.Id_cliente ?? null
						const idActividad = r.id_actividad ?? r.idActividad ?? r.Id_actividad ?? null

						const clienteNombre = clientesMap.get(Number(idCliente)) || `Cliente ${idCliente ?? ''}`.trim()
						const actividadNombre = actividadesMap.get(Number(idActividad)) || `Actividad ${idActividad ?? ''}`.trim()

						return {
							...r,
							id,
							idCliente,
							idActividad,
							clienteNombre,
							actividadNombre,
						}
					})

			} catch (e) {
				showError(t('ahorrosgossler', e.message))
			} finally {
				this.loading = false
			}
		},

		async GetActividades() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetActividades'))
					.then(
						(response) => {
							if (response?.data?.ocs?.meta?.status !== 'ok') {
								showError(response?.data?.ocs?.meta?.message)
								this.loading = false
								window.location.href = '/apps/empleados/#/'
								return
							}
							const keyMap = {
								id_actividad: 'id',
								nombre: 'label',
								tiempo_real: 'count',
							}

							const renameKeys = (obj, map) =>
								Object.fromEntries(Object.entries(obj).map(([k, v]) => [map[k] ?? k, v]))

							const arr = Array.isArray(response?.data?.ocs?.data) ? response.data.ocs.data : []

							this.listas = arr.map(o => renameKeys(o, keyMap))

							this.loading = false
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		async GetCompaniesGroups() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetCompaniesGroups'))
					.then(
						(response) => {
							if (response?.data?.ocs?.meta?.status !== 'ok') {
								showError(response?.data?.ocs?.meta?.message)
								this.loading = false
								window.location.href = '/apps/empleados/#/'
								return
							}
							const keyMap = {
								id_cliente: 'id',
								nombre: 'name',
								cliente_padre: 'count',
							}

							const renameKeys = (obj, map) =>
								Object.fromEntries(Object.entries(obj).map(([k, v]) => [map[k] ?? k, v]))

							const arr = Array.isArray(response?.data?.ocs?.data) ? response.data.ocs.data : []

							this.temp_listas = arr.map(o => renameKeys(o, keyMap))

							const data = Array.isArray(response?.data?.ocs?.data) ? response.data.ocs.data : []

							// Lista para tu <List>
							this.temp_listas = data.map(o => ({
								id: o.id_cliente,
								name: o.nombre,
								count: o.child_count,
							}))

							// Opciones para <NcSelect>
							this.actividades = data.map(o => ({
								id: o.id_cliente,
								label: o.nombre,
							}))

							this.loading = false
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		async create() {
			if (!this.isFormValid) {
				showError(t('empleados', 'Completa todos los campos obligatorios con valores válidos.'))
				return
			}

			const fecha = this.time instanceof Date ? this.time : new Date(this.time)
			const payload = {
				id_cliente: this.activity_selected.id,
				id_actividad: this.listas_selected.id,
				tiemporegistrado: Number(this.time_activity),
				descripcion: String(this.description_activity || '').trim(),
				tipo: this.type_time,
				time: fecha.toISOString().slice(0, 10),
			}

			try {
				await axios.post(generateUrl('/apps/empleados/crearReporte'), payload).then(
					() => {
						showSuccess(t('empleados', 'Área creada exitosamente'))
						this.gethistorial()
						this.closeModal()
					},
					(err) => { showError(err) },
				)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},

		formatDate(val) {
			// Si viene ya formateada, la mostramos; si es ISO, la convertimos.
			if (!val) return ''
			// intenta parsear fecha conocida
			const d = new Date(val)
			if (!isNaN(d.getTime())) {
				// Muestra fecha y hora locales (MX)
				return new Intl.DateTimeFormat('es-MX', {
					year: 'numeric',
					month: '2-digit',
					day: '2-digit',
					hour: '2-digit',
					minute: '2-digit',
				}).format(d)
			}
			// si no fue parseable, regresa como viene
			return val
		},

		resetForm() {
			this.description_activity = ''
			this.type_time = 'minutos'
			this.time_activity = 0
			this.time = new Date()
			this.activity_selected = null
			this.listas_selected = null
		},
		getOptionId(option) {
			if (option === null || option === undefined || option === '') {
				return null
			}

			if (typeof option === 'object') {
				return option.id ?? option.value ?? null
			}

			return option
		},

		getReportClientId(reporte) {
			return reporte.idCliente
				?? reporte.id_cliente
				?? reporte.Id_cliente
				?? reporte.IdCliente
				?? null
		},

		getReportActivityId(reporte) {
			return reporte.idActividad
				?? reporte.id_actividad
				?? reporte.Id_actividad
				?? reporte.IdActividad
				?? null
		},

		normalizeDateOnly(value) {
			if (!value) {
				return null
			}

			if (value instanceof Date && !isNaN(value.getTime())) {
				return value.toISOString().slice(0, 10)
			}

			const text = String(value)

			if (/^\d{4}-\d{2}-\d{2}/.test(text)) {
				return text.slice(0, 10)
			}

			const date = new Date(value)

			if (isNaN(date.getTime())) {
				return null
			}

			return date.toISOString().slice(0, 10)
		},

		clearFilters() {
			this.filter_fecha_inicio = null
			this.filter_fecha_fin = null
			this.filter_cliente = null
			this.filter_actividad = null
			this.filter_busqueda = ''
		},
	},
}
</script>

<style scoped>
#emptycontent, .emptycontent { margin-top: 1vh; }
.center-screen {
	display: flex;
	justify-content: center;
	align-items: center;
	text-align: center;
	min-height: 100vh;
}
.center { margin: auto; width: 50%; padding: 10px; }
.container { padding-left: 20px; }
.board-title {
	margin-right: 10px;
	font-size: 25px;
	display: flex;
	align-items: center;
	font-weight: bold;
	margin-left: 20px;
}
.board-title .icon { margin-right: 8px; }
.main-content-card {
	margin-top: 10px;
	margin-left: 20px;
	margin-right: 20px;
}
.time-selector {
	display: flex;
	margin: .5rem 0;          /* margen arriba y abajo */
	align-self: center;
}

.radios {
	display: flex;
	margin-left: 7px;
	height: 35px;
	margin-top: 4px;
}

.estimatetime {
	display: flex;
}

.save {
	display: flex;
	margin-left: 10px;
	align-self: center;
}

.wrapper {
	display: flex;
	flex-direction: column;
}

.type-select {
	display: flex;
	flex-direction: row;
	flex-wrap: wrap;
}
.date-picker {
	margin-top: 3px;
	margin-right: 6px;
}
.fit {
	width: 100%;
}
.list {
	height: clamp(280px, calc(100vh - 390px), 560px);
	max-height: 560px;
	min-height: 280px;
	overflow-y: auto;
	overflow-x: hidden;
	border: 1px solid var(--color-border);
	border-radius: 12px;
	margin: 20px;
	background: var(--color-main-background);
	overscroll-behavior: contain;
}
.filters-card {
	margin: 20px;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
}

.filters-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 14px;
	margin-bottom: 16px;
}

.filters-header h3 {
	margin: 0;
	font-size: 18px;
	font-weight: 700;
}

.filters-header p {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.filters-grid {
	display: grid;
	grid-template-columns: repeat(4, minmax(180px, 1fr));
	gap: 12px;
	align-items: end;
}

.filter-control {
	width: 100%;
	min-width: 0;
}

.filter-search {
	grid-column: span 2;
}

.filters-summary {
	display: flex;
	flex-wrap: wrap;
	gap: 16px;
	margin-top: 14px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.filters-summary strong {
	color: var(--color-main-text);
}

@media (max-width: 1100px) {
	.filters-grid {
		grid-template-columns: repeat(2, minmax(180px, 1fr));
	}

	.filter-search {
		grid-column: span 2;
	}
}

@media (max-width: 700px) {
	.filters-header {
		flex-direction: column;
	}

	.filters-grid {
		grid-template-columns: 1fr;
	}

	.filter-search {
		grid-column: auto;
	}
}

@media (max-width: 900px) {
	.list {
		height: clamp(260px, 48vh, 480px);
		max-height: 480px;
	}
}

@media (max-width: 600px) {
	.list {
		height: 45vh;
		min-height: 240px;
		margin: 12px;
	}
}
</style>
