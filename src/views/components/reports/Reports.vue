<template id="content">
	<NcAppContent :name="t('empleados', 'Employees - Activities')">
		<div v-if="loading">
			<div class="center">
				<NcLoadingIcon :size="64" appearance="dark" name="Loading on light background" />
			</div>
		</div>

		<div v-else class="reports-page">
			<div class="reports-layout">
				<section class="reports-main">
					<div class="filters-card">
						<div class="filters-header">
							<div class="filters-title">
								<p class="section-label">
									{{ t('empleados', 'Time reports') }}
								</p>
								<h2>{{ t('empleados', 'My reports') }}</h2>
								<p>{{ t('empleados', 'Review my reports') }}</p>
							</div>

							<div class="filters-stats">
								<div class="filters-stat">
									<span>{{ t('empleados', 'Reports') }}</span>
									<strong>{{ historialFiltrado.length }}</strong>
								</div>

								<div class="filters-stat">
									<span>{{ t('empleados', 'Total hours') }}</span>
									<strong>{{ totalHorasFiltradas }}</strong>
								</div>

								<div class="filters-stat">
									<span>{{ t('empleados', 'Fortnight') }}</span>
									<strong>{{ quincenaHorasTexto }} h</strong>
								</div>
							</div>
						</div>

						<div class="filters-panel">
							<div class="filters-toolbar">
								<div>
									<strong>{{ t('empleados', 'Filters') }}</strong>
									<span>
										{{ activeFiltersCount > 0
											? t('empleados', '{count} active', { count: activeFiltersCount })
											: t('empleados', 'No active filters') }}
									</span>
								</div>

								<NcButton
									:aria-label="t('empleados', 'Clear filters')"
									:disabled="activeFiltersCount === 0"
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

					<div v-else class="empty-state">
						{{ t('empleados', 'No reports found.') }}
					</div>
				</section>

				<aside class="reports-side">
					<section class="quick-card">
						<div>
							<h3>{{ t('empleados', 'New report') }}</h3>
							<p>{{ t('empleados', 'Create report') }}</p>
						</div>

						<NcButton
							:aria-label="t('empleados', 'Create report')"
							type="primary"
							wide
							@click="openModal()">
							<template #icon>
								<Check :size="20" />
							</template>
							{{ t('empleados', 'Create report') }}
						</NcButton>
					</section>

					<section class="compliance-card" :class="semaforoClass">
						<div class="semaforo-header">
							<div>
								<h3>{{ t('empleados', 'Fortnight compliance') }}</h3>
								<p>{{ quincenaPeriodoTexto }}</p>
							</div>
							<span class="semaforo-light" />
						</div>

						<div class="semaforo-value">
							{{ quincenaHorasTexto }} h
						</div>

						<div class="semaforo-meta">
							<span>{{ t('empleados', 'Goal') }}: {{ quincenaMetaTexto }} h</span>
							<strong>{{ quincenaPorcentajeTexto }}%</strong>
						</div>

						<div class="progress-track">
							<div
								class="progress-value"
								:style="{ width: quincenaProgressWidth }" />
						</div>

						<div class="semaforo-status">
							{{ semaforoLabel }}
						</div>
					</section>
				</aside>
			</div>
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
								:name="t('empleados', 'Minutes')"
								type="radio"
								button-variant-grouped="horizontal">
								{{ t('empleados', 'Minutes') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch
								v-model="type_time"
								:button-variant="true"
								value="horas"
								:name="t('empleados', 'Hours')"
								type="radio"
								button-variant-grouped="horizontal">
								{{ t('empleados', 'Hours') }}
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
	inject: {
		configuraciones: {
			default: () => ({}),
		},
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

		activeFiltersCount() {
			return [
				this.filter_fecha_inicio,
				this.filter_fecha_fin,
				this.filter_cliente,
				this.filter_actividad,
				String(this.filter_busqueda || '').trim(),
			].filter(Boolean).length
		},

		totalHorasFiltradas() {
			const totalMinutos = this.historialFiltrado.reduce((total, reporte) => {
				return total + Number(reporte.tiempo_registrado || 0)
			}, 0)

			return this.formatHours(totalMinutos / 60)
		},

		quincenaActual() {
			const today = new Date()
			const start = new Date(today.getFullYear(), today.getMonth(), today.getDate() <= 15 ? 1 : 16)
			const end = today.getDate() <= 15
				? new Date(today.getFullYear(), today.getMonth(), 15)
				: new Date(today.getFullYear(), today.getMonth() + 1, 0)

			return {
				start,
				end,
				today,
				startKey: this.formatLocalDateKey(start),
				endKey: this.formatLocalDateKey(end),
				todayKey: this.formatLocalDateKey(today),
			}
		},

		quincenaMinutos() {
			const { startKey, endKey } = this.quincenaActual

			return this.historial.reduce((total, reporte) => {
				const fechaReporte = this.normalizeDateOnly(reporte.fecha_registro)

				if (!fechaReporte || fechaReporte < startKey || fechaReporte > endKey) {
					return total
				}

				return total + Number(reporte.tiempo_registrado || 0)
			}, 0)
		},

		quincenaHoras() {
			return this.quincenaMinutos / 60
		},

		quincenaHorasTexto() {
			return this.formatHours(this.quincenaHoras)
		},

		horasMinimasDiarias() {
			const configured = Number(
				this.configuraciones?.Reportes?.horas_minimas
				?? this.configuraciones?.reportes_horas_minimas
				?? 0,
			)

			return Number.isFinite(configured) && configured > 0 ? configured : 8
		},

		diasHabilesQuincenaTranscurridos() {
			const { start, today, end } = this.quincenaActual
			const limit = today < end ? today : end
			let count = 0

			for (
				let cursorTime = start.getTime();
				cursorTime <= limit.getTime();
				cursorTime += 24 * 60 * 60 * 1000
			) {
				const cursor = new Date(cursorTime)
				const day = cursor.getDay()

				if (day !== 0 && day !== 6) {
					count++
				}
			}

			return Math.max(count, 1)
		},

		quincenaMetaHoras() {
			return this.horasMinimasDiarias * this.diasHabilesQuincenaTranscurridos
		},

		quincenaMetaTexto() {
			return this.formatHours(this.quincenaMetaHoras)
		},

		quincenaPorcentaje() {
			if (this.quincenaMetaHoras <= 0) {
				return this.quincenaHoras > 0 ? 100 : 0
			}

			return Math.min((this.quincenaHoras / this.quincenaMetaHoras) * 100, 100)
		},

		quincenaPorcentajeTexto() {
			return Math.round(this.quincenaPorcentaje)
		},

		quincenaProgressWidth() {
			return `${this.quincenaPorcentaje}%`
		},

		semaforoClass() {
			if (this.quincenaPorcentaje >= 100) {
				return 'status-ok'
			}

			if (this.quincenaPorcentaje >= 70) {
				return 'status-warning'
			}

			return 'status-danger'
		},

		semaforoLabel() {
			if (this.quincenaPorcentaje >= 100) {
				return t('empleados', 'On track')
			}

			if (this.quincenaPorcentaje >= 70) {
				return t('empleados', 'Close to goal')
			}

			return t('empleados', 'Needs attention')
		},

		quincenaPeriodoTexto() {
			const formatter = new Intl.DateTimeFormat('es-MX', {
				day: '2-digit',
				month: 'short',
			})

			return `${formatter.format(this.quincenaActual.start)} - ${formatter.format(this.quincenaActual.end)}`
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

						const esAusencia = Number(idCliente) === 99999
						const clienteNombre = esAusencia
							? t('empleados', 'Absence')
							: (clientesMap.get(Number(idCliente)) || `Cliente ${idCliente ?? ''}`.trim())
						const actividadNombre = esAusencia
							? t('empleados', 'Cargable')
							: (actividadesMap.get(Number(idActividad)) || `Actividad ${idActividad ?? ''}`.trim())
						return {
							...r,
							id,
							idCliente,
							idActividad,
							clienteNombre,
							actividadNombre,
							esAusencia,
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
								id: 'id',
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
								id: o.id,
								name: o.nombre,
								count: o.child_count,
							}))

							// Opciones para <NcSelect>
							this.actividades = data.map(o => ({
								id: o.id,
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
				return this.formatLocalDateKey(value)
			}

			const text = String(value)

			if (/^\d{4}-\d{2}-\d{2}/.test(text)) {
				return text.slice(0, 10)
			}

			const date = new Date(value)

			if (isNaN(date.getTime())) {
				return null
			}

			return this.formatLocalDateKey(date)
		},

		clearFilters() {
			this.filter_fecha_inicio = null
			this.filter_fecha_fin = null
			this.filter_cliente = null
			this.filter_actividad = null
			this.filter_busqueda = ''
		},

		formatLocalDateKey(date) {
			const year = date.getFullYear()
			const month = String(date.getMonth() + 1).padStart(2, '0')
			const day = String(date.getDate()).padStart(2, '0')

			return `${year}-${month}-${day}`
		},

		formatHours(value) {
			return new Intl.NumberFormat('es-MX', {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			}).format(Number(value) || 0)
		},
	},
}
</script>

<style scoped>
.center { margin: auto; width: 50%; padding: 10px; }

.reports-page {
	width: 100%;
	padding: 20px;
	box-sizing: border-box;
}

.reports-layout {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 320px;
	gap: 18px;
	align-items: start;
}

.reports-main,
.reports-side {
	min-width: 0;
}

.reports-side {
	position: sticky;
	top: 20px;
	display: flex;
	flex-direction: column;
	gap: 14px;
}

.time-selector {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin: .5rem 0;
	align-self: center;
}

.radios {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	height: 35px;
	margin-top: 4px;
}

.estimatetime {
	display: flex;
}

.save {
	display: flex;
	justify-content: flex-end;
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
	height: clamp(320px, calc(100vh - 315px), 660px);
	max-height: 660px;
	min-height: 280px;
	overflow-y: auto;
	overflow-x: hidden;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	background: var(--color-main-background);
	overscroll-behavior: contain;
}

.filters-card,
.quick-card,
.compliance-card,
.empty-state {
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
}

.filters-card {
	margin-bottom: 14px;
	padding: 0;
	overflow: hidden;
}

.filters-header {
	display: flex;
	align-items: stretch;
	justify-content: space-between;
	gap: 18px;
	padding: 18px;
	border-bottom: 1px solid var(--color-border);
	background: linear-gradient(180deg, var(--color-main-background) 0%, var(--color-background-hover) 100%);
}

.filters-title {
	display: flex;
	flex-direction: column;
	justify-content: center;
	min-width: 220px;
}

.section-label {
	margin: 0 0 4px;
	color: var(--color-primary-element);
	font-size: 12px;
	font-weight: 700;
	letter-spacing: .04em;
	text-transform: uppercase;
}

.filters-header h2,
.quick-card h3,
.compliance-card h3 {
	margin: 0;
	font-size: 18px;
	font-weight: 700;
	line-height: 1.25;
}

.quick-card h3,
.compliance-card h3 {
	font-size: 16px;
}

.filters-header p,
.quick-card p,
.semaforo-header p {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.filters-stats {
	display: grid;
	grid-template-columns: repeat(3, minmax(118px, 1fr));
	gap: 10px;
	width: min(100%, 520px);
}

.filters-stat {
	display: flex;
	flex-direction: column;
	justify-content: center;
	min-width: 0;
	min-height: 76px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	background: var(--color-main-background);
}

.filters-stat span {
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 600;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.filters-stat strong {
	margin-top: 6px;
	color: var(--color-main-text);
	font-size: 22px;
	font-weight: 800;
	line-height: 1;
}

.filters-panel {
	padding: 16px 18px 18px;
}

.filters-toolbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 14px;
}

.filters-toolbar strong {
	display: block;
	color: var(--color-main-text);
	font-size: 14px;
}

.filters-toolbar span {
	display: block;
	margin-top: 2px;
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

.quick-card {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.compliance-card {
	--semaforo-color: #d94f00;
	--semaforo-bg: rgba(217, 79, 0, 0.12);
	display: flex;
	flex-direction: column;
	gap: 14px;
}

.compliance-card.status-ok {
	--semaforo-color: #108548;
	--semaforo-bg: rgba(16, 133, 72, 0.12);
}

.compliance-card.status-warning {
	--semaforo-color: #c78200;
	--semaforo-bg: rgba(199, 130, 0, 0.14);
}

.compliance-card.status-danger {
	--semaforo-color: #d94f00;
	--semaforo-bg: rgba(217, 79, 0, 0.12);
}

.semaforo-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
}

.semaforo-light {
	display: block;
	flex: 0 0 18px;
	width: 18px;
	height: 18px;
	margin-top: 2px;
	border-radius: 50%;
	background: var(--semaforo-color);
	box-shadow: 0 0 0 6px var(--semaforo-bg);
}

.semaforo-value {
	font-size: 34px;
	font-weight: 800;
	line-height: 1;
	color: var(--semaforo-color);
}

.semaforo-meta,
.semaforo-status {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.semaforo-meta strong {
	color: var(--color-main-text);
	font-size: 20px;
}

.progress-track {
	width: 100%;
	height: 10px;
	overflow: hidden;
	border-radius: 999px;
	background: var(--color-background-darker);
}

.progress-value {
	height: 100%;
	border-radius: inherit;
	background: var(--semaforo-color);
	transition: width 180ms ease;
}

.semaforo-status {
	justify-content: flex-start;
	min-height: 30px;
	padding: 6px 10px;
	border-radius: 999px;
	background: var(--semaforo-bg);
	color: var(--semaforo-color);
	font-weight: 700;
}

.empty-state {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 220px;
	color: var(--color-text-maxcontrast);
}

@media (max-width: 1100px) {
	.reports-layout {
		grid-template-columns: 1fr;
	}

	.reports-side {
		position: static;
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		order: -1;
	}

	.filters-grid {
		grid-template-columns: repeat(2, minmax(180px, 1fr));
	}

	.filters-stats {
		width: min(100%, 460px);
	}

	.filter-search {
		grid-column: span 2;
	}
}

@media (max-width: 700px) {
	.reports-page {
		padding: 12px;
	}

	.reports-side {
		grid-template-columns: 1fr;
	}

	.filters-header {
		flex-direction: column;
	}

	.filters-stats {
		grid-template-columns: 1fr;
		width: 100%;
	}

	.filters-stat {
		min-height: 64px;
	}

	.filters-toolbar {
		align-items: flex-start;
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
	}
}
</style>
