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
									<strong>{{ quincenaHorasTexto }}</strong>
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
								<NcSelect
									v-model="filter_tipo_trabajo"
									:input-label="t('empleados', 'Work type')"
									:options="workTypeFilterOptions"
									class="filter-control" />
								<NcSelect
									v-model="filter_origen"
									:input-label="t('empleados', 'Origin')"
									:options="originFilterOptions"
									class="filter-control" />
								<NcSelect
									v-model="filter_cargable"
									:input-label="t('empleados', 'Billable classification')"
									:options="billableFilterOptions"
									class="filter-control" />
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
							{{ quincenaHorasTexto }}
						</div>

						<div class="semaforo-meta">
							<span>{{ t('empleados', 'Goal') }}: {{ quincenaMetaTexto }}</span>
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
		<ReportTimeModal v-if="modal" @created="gethistorial" @close="closeModal" />
	</NcAppContent>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import Check from 'vue-material-design-icons/Check.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import VirtualList from 'vue-virtual-scroll-list'
import ReportRow from '../Helpers/Lists/ReportRow.vue'
import mitt from 'mitt'

import ReportTimeModal from './ReportTimeModal.vue'

import {
	NcLoadingIcon,
	NcAppContent,
	NcButton,
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
		NcTextField,
		NcDateTimePicker,
		NcSelect,
		VirtualList,
		ReportTimeModal,
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
			listas: [],
			actividades: [],
			temp_listas: [],
			filter_fecha_inicio: null,
			filter_fecha_fin: null,
			filter_cliente: null,
			filter_actividad: null,
			filter_tipo_trabajo: null,
			filter_origen: null,
			filter_cargable: null,
			filter_busqueda: '',
		}
	},
	computed: {
		workTypeFilterOptions() {
			return [
				{ id: 'todos', label: t('empleados', 'All') },
				{ id: 'cliente', label: t('empleados', 'Client work') },
				{ id: 'interno', label: t('empleados', 'Internal work') },
				{ id: 'ausencia', label: t('empleados', 'Absences') },
			]
		},
		originFilterOptions() {
			const origins = new Set(this.historial.map(report => report.origen || 'legado'))
			return Array.from(origins).map(origin => ({
				id: origin,
				label: origin === 'manual_interno'
					? t('empleados', 'Manual internal report')
					: origin === 'soporte_ti' ? t('empleados', 'Support TI') : origin,
			}))
		},
		billableFilterOptions() {
			return [
				{ id: '1', label: t('empleados', 'Billable') },
				{ id: '0', label: t('empleados', 'Non-billable') },
			]
		},
		historialFiltrado() {
			const fechaInicio = this.normalizeDateOnly(this.filter_fecha_inicio)
			const fechaFin = this.normalizeDateOnly(this.filter_fecha_fin)

			const clienteId = this.getOptionId(this.filter_cliente)
			const actividadId = this.getOptionId(this.filter_actividad)
			const selectedWorkType = this.getOptionId(this.filter_tipo_trabajo)
			const workType = selectedWorkType === 'todos' ? null : selectedWorkType
			const origin = this.getOptionId(this.filter_origen)
			const billable = this.getOptionId(this.filter_cargable)
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

				if (workType !== null && reporte.tipo_trabajo !== workType) return false
				if (origin !== null && (reporte.origen || 'legado') !== origin) return false
				if (billable !== null && Number(reporte.cargable || 0) !== Number(billable)) return false

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
				this.getOptionId(this.filter_tipo_trabajo) === 'todos' ? null : this.filter_tipo_trabajo,
				this.filter_origen,
				this.filter_cargable,
				String(this.filter_busqueda || '').trim(),
			].filter(Boolean).length
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

		totalMinutosFiltrados() {
			return this.historialFiltrado.reduce((total, reporte) => {
				const minutos = Number(reporte.tiempo_registrado)

				return total + (
					Number.isFinite(minutos) && minutos > 0
						? minutos
						: 0
				)
			}, 0)
		},

		totalHorasFiltradas() {
			return this.formatDuration(this.totalMinutosFiltrados)
		},

		quincenaActual() {
			const today = new Date()

			today.setHours(12, 0, 0, 0)

			const start = new Date(
				today.getFullYear(),
				today.getMonth(),
				today.getDate() <= 15 ? 1 : 16,
				12,
			)

			const end = today.getDate() <= 15
				? new Date(today.getFullYear(), today.getMonth(), 15, 12)
				: new Date(today.getFullYear(), today.getMonth() + 1, 0, 12)

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
			const { startKey, todayKey } = this.quincenaActual

			return this.historial.reduce((total, reporte) => {
				const fechaReporte = this.normalizeDateOnly(reporte.fecha_registro)

				// Para cumplimiento solo contamos desde el inicio
				// de la quincena hasta el día actual.
				if (
					!fechaReporte
					|| fechaReporte < startKey
					|| fechaReporte > todayKey
				) {
					return total
				}

				const minutos = Number(reporte.tiempo_registrado)

				return total + (
					Number.isFinite(minutos) && minutos > 0
						? minutos
						: 0
				)
			}, 0)
		},

		quincenaHoras() {
			return this.quincenaMinutos / 60
		},

		quincenaHorasTexto() {
			return this.formatDuration(this.quincenaMinutos)
		},

		horasMinimasDiarias() {
			const configured = Number(
				this.configuraciones?.Reportes?.horas_minimas
				?? this.configuraciones?.reportes_horas_minimas
				?? 0,
			)

			return Number.isFinite(configured) && configured > 0
				? configured
				: 8
		},

		quincenaMetaMinutos() {
			return Math.round(
				this.horasMinimasDiarias
				* this.diasHabilesQuincena
				* 60,
			)
		},

		diasHabilesQuincena() {
			const { start, end } = this.quincenaActual

			const cursor = new Date(start)
			const lastDay = new Date(end)

			cursor.setHours(12, 0, 0, 0)
			lastDay.setHours(12, 0, 0, 0)

			let count = 0

			// eslint-disable-next-line no-unmodified-loop-condition
			while (cursor <= lastDay) {
				const day = cursor.getDay()

				if (day !== 0 && day !== 6) {
					count++
				}

				cursor.setDate(cursor.getDate() + 1)
			}

			return count
		},

		quincenaMetaHoras() {
			return this.horasMinimasDiarias * this.diasHabilesQuincena
		},

		quincenaMetaTexto() {
			return this.formatDuration(this.quincenaMetaMinutos)
		},

		quincenaPorcentaje() {
			if (this.quincenaMetaMinutos <= 0) {
				return this.quincenaMinutos > 0 ? 100 : 0
			}

			return Math.min(
				(this.quincenaMinutos / this.quincenaMetaMinutos) * 100,
				100,
			)
		},

		quincenaPorcentajeTexto() {
			return Math.round(this.quincenaPorcentaje)
		},

		quincenaProgressWidth() {
			const porcentaje = Math.min(
				Math.max(this.quincenaPorcentaje, 0),
				100,
			)

			return `${porcentaje}%`
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
		},

		closeModal() {
			this.modal = false
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

						const tipoTrabajo = r.tipo_trabajo || (Number(idCliente) === 99999 ? 'ausencia' : (idCliente == null ? 'interno' : 'cliente'))
						const esAusencia = tipoTrabajo === 'ausencia'
						const esInterno = tipoTrabajo === 'interno'
						const esSoporte = r.origen === 'soporte_ti'
						const tipoAusenciaTexto = String(r.descripcion || '').trim()
						const clienteNombre = esInterno
							? t('empleados', 'Internal work')
							: esAusencia
								? `${t('empleados', 'Absence -')} ${tipoAusenciaTexto || t('empleados', 'Vacation')}`
								: (clientesMap.get(Number(idCliente)) || `Cliente ${idCliente ?? ''}`.trim())
						const actividadNombre = esSoporte
							? (r.actividad_nombre || t('empleados', 'Support TI'))
							: esAusencia
								? t('empleados', 'No Cargable')
								: (actividadesMap.get(Number(idActividad)) || `Actividad ${idActividad ?? ''}`.trim())
						return {
							...r,
							id,
							idCliente,
							idActividad,
							clienteNombre,
							actividadNombre,
							esAusencia,
							esSoporte,
							esInterno,
							tipo_trabajo: tipoTrabajo,
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
				await axios.get(generateUrl('/apps/empleados/GetActividades'), { params: { manual: 1 } })
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
							showError(this.backendError(err))
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
							showError(this.backendError(err))
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
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

		getOptionId(option) {
			if (option === null || option === undefined || option === '') {
				return null
			}

			if (typeof option === 'object') {
				return option.id ?? option.value ?? null
			}

			return option
		},
		backendError(error) {
			return error?.response?.data?.ocs?.data?.message
				|| error?.response?.data?.message
				|| error?.message
				|| String(error)
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
			this.filter_tipo_trabajo = null
			this.filter_origen = null
			this.filter_cargable = null
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
		formatDuration(value) {
			const totalMinutes = Math.max(
				0,
				Math.round(Number(value) || 0),
			)

			const hours = Math.floor(totalMinutes / 60)
			const minutes = totalMinutes % 60

			if (minutes === 0) {
				return `${hours} h`
			}

			return `${hours} h ${String(minutes).padStart(2, '0')} min`
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
