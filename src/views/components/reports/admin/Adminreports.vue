<template id="content">
	<NcAppContent :name="t('empleados', 'Employees - Activities')">
		<List
			:loading="loading"
			:listas="sidebarEmployees"
			:select="select"
			:details-active="selectedEmployeeId !== null"
			:defaultbuttons="false"
			:custom="true">
			<template #custombuttons>
				<div class="button-container">
					<NcActions>
						<template #icon>
							<DatabaseExport :size="20" />
						</template>
						<NcActionButton :disabled="exporting" @click="Exportar()">
							<template #icon>
								<DatabaseExport :size="20" />
							</template>
							{{ t('empleados', 'Export period report') }}
						</NcActionButton>
					</NcActions>
				</div>
			</template>
			<template #custom>
				<div class="periodo-details">
					<header class="report-context">
						<div class="report-context__heading">
							<h2 class="report-context__title">
								{{ t('empleados', 'Time reports') }}
							</h2>
							<p class="report-context__meta">
								<span>{{ selectedPeriodLabel }}</span>
								<span aria-hidden="true"> · </span>
								<span v-if="areaLabel">{{ areaLabel }}</span>
								<span v-else>{{ t('empleados', 'All visible areas') }}</span>
								<span aria-hidden="true"> · </span>
								<span>{{ viewModeLabel }}</span>
							</p>
						</div>

						<div class="report-toolbar" :class="{ 'report-toolbar--expanded': moreFiltersOpen }">
							<div class="report-toolbar__row report-toolbar__row--main">
								<div
									class="report-field report-field--period"
									role="group"
									:aria-label="t('empleados', 'Period')">
									<span class="report-field__label">{{ t('empleados', 'Period') }}</span>
									<div class="report-period">
										<NcDateTimePicker
											v-model="fechaInicio"
											type="date"
											:placeholder="t('empleados', 'From date')"
											class="report-period__picker" />
										<span class="report-period__sep" aria-hidden="true">—</span>
										<NcDateTimePicker
											v-model="fechaFin"
											type="date"
											:placeholder="t('empleados', 'To date')"
											class="report-period__picker" />
									</div>
								</div>

								<div class="report-field report-field--area">
									<span id="admin-report-area-label" class="report-field__label">
										{{ t('empleados', 'Area') }}
									</span>
									<NcSelect
										v-model="areaSeleccionada"
										:options="areasOptions"
										:clearable="true"
										:aria-labelledby="'admin-report-area-label'"
										:placeholder="t('empleados', 'Area')"
										class="report-field__control" />
								</div>

								<div class="report-field report-field--employee">
									<span id="admin-report-employee-label" class="report-field__label">
										{{ t('empleados', 'Employee') }}
									</span>
									<NcSelect
										v-model="empleadoSeleccionado"
										:options="empleadosOptions"
										:clearable="true"
										:aria-labelledby="'admin-report-employee-label'"
										:placeholder="t('empleados', 'Employee')"
										class="report-field__control" />
								</div>

								<div class="report-field report-field--type">
									<span id="admin-report-type-label" class="report-field__label">
										{{ t('empleados', 'Work type') }}
									</span>
									<NcSelect
										v-model="tipoTrabajoSeleccionado"
										:options="workTypeOptions"
										:clearable="false"
										:aria-labelledby="'admin-report-type-label'"
										:placeholder="t('empleados', 'Work type')"
										class="report-field__control" />
								</div>

								<div class="report-toolbar__actions">
									<NcButton
										type="tertiary"
										:aria-expanded="moreFiltersOpen ? 'true' : 'false'"
										:title="moreFiltersButtonLabel"
										@click="toggleMoreFilters">
										{{ moreFiltersButtonLabel }}
										<template #icon>
											<ChevronUp v-if="moreFiltersOpen" :size="16" />
											<ChevronDown v-else :size="16" />
										</template>
									</NcButton>
									<NcButton type="primary" :disabled="loadingResumen" @click="applyFilters">
										{{ t('empleados', 'Apply filters') }}
									</NcButton>
								</div>
							</div>

							<div v-if="moreFiltersOpen" class="report-toolbar__row report-toolbar__row--advanced">
								<div
									v-if="showClientFilter"
									class="report-field report-field--client">
									<span id="admin-report-client-label" class="report-field__label">
										{{ t('empleados', 'Client') }}
									</span>
									<NcSelect
										v-model="clienteSeleccionado"
										:options="clientesOptions"
										:clearable="true"
										:aria-labelledby="'admin-report-client-label'"
										:placeholder="t('empleados', 'Client')"
										class="report-field__control" />
								</div>

								<div class="report-field report-field--activity">
									<span id="admin-report-activity-label" class="report-field__label">
										{{ t('empleados', 'Activity') }}
									</span>
									<NcSelect
										v-model="actividadSeleccionada"
										:options="activityFilterOptions"
										:clearable="true"
										:aria-labelledby="'admin-report-activity-label'"
										:placeholder="t('empleados', 'Activity')"
										class="report-field__control" />
								</div>

								<div class="report-toolbar__actions report-toolbar__actions--secondary">
									<NcButton
										type="tertiary"
										:disabled="loadingResumen"
										@click="clearReportFilters">
										{{ t('empleados', 'Clear') }}
									</NcButton>
								</div>
							</div>
						</div>
					</header>

					<AdminAnalyticsDashboard
						:resumen="resumenGeneral"
						:loading="loadingResumen"
						:mostrar-clientes="mostrarClientes"
						:mostrar-ausencias="mostrarAusencias"
						@select-employee="openEmployeeDetails" />
				</div>
			</template>
			<template #details>
				<div class="details-toolbar">
					<NcButton type="tertiary" @click="closeEmployeeDetails">
						{{ t('empleados', 'Back to administrative report') }}
					</NcButton>
				</div>
				<AdminEmpleadoResumen
					:empleado="selectedEmployeeSummary"
					:mostrar-clientes="mostrarClientes"
					:mostrar-ausencias="mostrarAusencias" />
				<AdminDetalles :select="select"
					:sueldo="sueldo"
					:actividades-list="actividades"
					:proyectos-list="temp_listas"
					:mostrar-clientes="mostrarClientes"
					:mostrar-ausencias="mostrarAusencias" />
			</template>
		</List>
	</NcAppContent>
</template>

<script>
// Icons
import DatabaseExport from 'vue-material-design-icons/DatabaseExport.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'

// public imports
import { showError /*, showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import List from '../../Helpers/Lists/List.vue'
import AdminDetalles from './AdminDetalles.vue'
import AdminAnalyticsDashboard from './AdminAnalyticsDashboard.vue'
import AdminEmpleadoResumen from './AdminEmpleadoResumen.vue'
import debounce from 'debounce'
import {
	PREFERENCE_KEYS,
	loadPreference,
	savePreference,
	resetPreference,
	hasPreference,
} from '../../../../utils/userPreferences.js'

import {
	NcAppContent,
	NcButton,
	NcActions,
	NcActionButton,
	NcDateTimePicker,
	NcSelect,
} from '@nextcloud/vue'

const ADMIN_REPORT_DEFAULTS = Object.freeze({
	filters: {
		areaId: null,
		employeeId: null,
		workType: 'todos',
		clientId: null,
		activityId: null,
		fechaInicio: null,
		fechaFin: null,
	},
	view: {
		moreFiltersOpen: false,
	},
})

/** Claves legacy previas a la utilidad namespaced (migración suave). */
const LEGACY_ADMIN_KEYS = Object.freeze({
	area: 'nextcloud_empleados_admin_area',
	fechaInicio: 'nextcloud_empleados_admin_fecha_inicio',
	fechaFin: 'nextcloud_empleados_admin_fecha_fin',
	tipoTrabajo: 'nextcloud_empleados_admin_tipo_trabajo',
})

export default {
	name: 'Adminreports',
	components: {
		NcAppContent,
		List,
		NcButton,
		AdminDetalles,
		AdminEmpleadoResumen,
		NcActions,
		NcActionButton,
		DatabaseExport,
		ChevronDown,
		ChevronUp,
		NcDateTimePicker,
		NcSelect,
		AdminAnalyticsDashboard,
	},
	data() {
		return {
			loading: true,
			listas: [],
			select: [],
			periodo_inicio: null,
			periodo_fin: null,
			anioSeleccionado: null,
			actividades: [],
			meses: [
				{ label: t('empleados', 'January'), value: 1 },
				{ label: t('empleados', 'February'), value: 2 },
				{ label: t('empleados', 'March'), value: 3 },
				{ label: t('empleados', 'April'), value: 4 },
				{ label: t('empleados', 'May'), value: 5 },
				{ label: t('empleados', 'June'), value: 6 },
				{ label: t('empleados', 'July'), value: 7 },
				{ label: t('empleados', 'August'), value: 8 },
				{ label: t('empleados', 'September'), value: 9 },
				{ label: t('empleados', 'October'), value: 10 },
				{ label: t('empleados', 'November'), value: 11 },
				{ label: t('empleados', 'December'), value: 12 },
			],
			anios: Array.from({ length: Math.max(0, new Date().getFullYear() - 2025 + 1) }, (_, i) => 2025 + i),
			resumenGeneral: null,
			loadingResumen: false,
			sueldo: 0,
			temp_listas: [],
			areasOptions: [],
			areaSeleccionada: null,
			fechaInicio: null,
			fechaFin: null,
			tipoTrabajoSeleccionado: null,
			empleadoSeleccionado: null,
			clienteSeleccionado: null,
			actividadSeleccionada: null,
			selectedEmployeeId: null,
			exporting: false,
			reportRequestId: 0,
			detailRequestId: 0,
			moreFiltersOpen: false,
		}
	},

	computed: {
		normalizedPeriod() {
			let fechaInicio = this.formatDateKey(this.fechaInicio)
			let fechaFin = this.formatDateKey(this.fechaFin)
			if (fechaInicio && fechaFin && fechaInicio > fechaFin) {
				[fechaInicio, fechaFin] = [fechaFin, fechaInicio]
			}
			const idDepartamento = this.normalizeSelectNumber(
				this.areaSeleccionada?.value ?? this.areaSeleccionada,
			)
			const workType = this.optionId(this.tipoTrabajoSeleccionado)
			const payload = {
				fecha_inicio: fechaInicio,
				fecha_fin: fechaFin,
				tipo_trabajo: workType === 'todos' ? null : workType,
				id_empleado: this.optionId(this.empleadoSeleccionado),
				id_cliente: this.showClientFilter ? this.optionId(this.clienteSeleccionado) : null,
				id_actividad: this.optionId(this.actividadSeleccionada),
			}

			if (idDepartamento !== null) {
				payload.id_departamento = idDepartamento
			}

			return payload
		},

		workTypeOptions() {
			const options = [
				{ id: 'todos', label: t('empleados', 'All') },
				{ id: 'interno', label: t('empleados', 'Internal work') },
			]
			if (this.mostrarClientes) {
				options.splice(1, 0, { id: 'cliente', label: t('empleados', 'Client work') })
			}
			return options
		},

		showClientFilter() {
			return this.mostrarClientes && this.optionId(this.tipoTrabajoSeleccionado) !== 'interno'
		},

		clientesOptions() {
			return (this.temp_listas || []).map(client => ({
				id: Number(client.id),
				label: client.name || client.label || client.nombre || `#${client.id}`,
			}))
		},

		activityFilterOptions() {
			const type = this.mostrarClientes
				? this.optionId(this.tipoTrabajoSeleccionado)
				: 'interno'
			const departmentId = this.normalizeSelectNumber(
				this.areaSeleccionada?.value ?? this.areaSeleccionada,
			)
			return (this.actividades || []).filter((activity) => {
				const activityType = activity.tipo_actividad || activity.tipoActividad || 'cliente'
				if (type && type !== 'todos' && activityType !== type) {
					return false
				}
				if (activityType !== 'interno' || activity.alcance !== 'areas' || departmentId === null) {
					return true
				}
				const areaIds = Array.isArray(activity.area_ids) ? activity.area_ids : []
				return areaIds.some(id => Number(id) === departmentId)
			})
		},

		empleadosOptions() {
			return (this.listas || []).map(employee => ({
				id: employee.id,
				label: employee.name,
			}))
		},

		sidebarEmployees() {
			const employeeId = this.optionId(this.empleadoSeleccionado)
			if (employeeId === null) {
				return this.listas
			}
			return this.listas.filter(employee => String(employee.id) === String(employeeId))
		},

		selectedPeriodLabel() {
			const start = this.formatDateDisplay(this.normalizedPeriod.fecha_inicio)
			const end = this.formatDateDisplay(this.normalizedPeriod.fecha_fin)
			return start && end ? `${start} – ${end}` : t('empleados', 'Select a period')
		},

		selectedEmployeeSummary() {
			const employees = Array.isArray(this.resumenGeneral?.empleados)
				? this.resumenGeneral.empleados
				: []
			return employees.find(employee => Number(employee.id_empleado) === Number(this.selectedEmployeeId)) || null
		},

		areaLabel() {
			return this.areaSeleccionada?.label
				|| this.resumenGeneral?.area?.nombre
				|| ''
		},

		mostrarClientes() {
			return this.resolveAreaFlag('mostrar_clientes', true)
		},

		mostrarAusencias() {
			return this.resolveAreaFlag('mostrar_ausencias', true)
		},

		viewModeLabel() {
			return this.mostrarClientes
				? t('empleados', 'Client-oriented view')
				: t('empleados', 'Administrative view')
		},

		activeSecondaryFilterCount() {
			let count = 0
			if (this.showClientFilter && this.optionId(this.clienteSeleccionado) !== null) {
				count++
			}
			if (this.optionId(this.actividadSeleccionada) !== null) {
				count++
			}
			return count
		},

		moreFiltersButtonLabel() {
			const base = t('empleados', 'More filters')
			const count = this.activeSecondaryFilterCount
			return count > 0 ? `${base} (${count})` : base
		},

	},

	watch: {
		areaSeleccionada() {
			this.onAreaFilterChange()
		},
		tipoTrabajoSeleccionado() {
			this.enforceFilterDependencies()
			this.scheduleSaveReportPreferences()
		},
		empleadoSeleccionado() {
			this.scheduleSaveReportPreferences()
		},
		clienteSeleccionado() {
			this.scheduleSaveReportPreferences()
		},
		actividadSeleccionada() {
			this.scheduleSaveReportPreferences()
		},
		fechaInicio() {
			this.scheduleSaveReportPreferences()
		},
		fechaFin() {
			this.scheduleSaveReportPreferences()
		},
	},

	created() {
		this._prefsReady = false
		this._pendingEmployeeId = null
		this._debouncedSavePrefs = debounce(() => {
			this.persistReportPreferences()
		}, 300)
	},

	async mounted() {
		this._onDetails = (id) => this.gethistorial(id)
		this._onExport = () => this.Exportar()
		window.addEventListener('keydown', this.onKeyDown)

		this.$root.$on('details', this._onDetails)
		this.$root.$on('exportlist', this._onExport)
		try {
			const prefs = this.loadReportPreferences()
			this.applyDateFiltersFromPrefs(prefs.filters)
			this.applyViewPreferences(prefs.view)

			await Promise.all([
				this.GetAreasOptions(),
				this.GetCompaniesGroups(),
				this.GetActividades(),
			])

			this.applyCatalogFiltersFromPrefs(prefs.filters)
			await this.reloadReportFilters()
			const employeeFilterInvalid = this.validateEmployeeFilterAfterLoad()
			this._prefsReady = true
			this.persistReportPreferences()
			if (employeeFilterInvalid) {
				await this.reloadReportFilters()
			}
		} finally {
			this.loading = false
		}
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.onKeyDown)
		this._debouncedSavePrefs?.flush?.()
		this._debouncedSavePrefs?.clear?.()

		this.$root.$off('details', this._onDetails)
		this.$root.$off('exportlist', this._onExport)
	},

	methods: {
		t,

		 onKeyDown(e) {
			if (e.key === 'Escape') this.onEsc()
		},

		onEsc() {
			this.closeEmployeeDetails()
		},

		async GetAreasOptions() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetAreasFix'))
				const arr = Array.isArray(response?.data?.ocs?.data) ? response.data.ocs.data : []
				this.areasOptions = arr.map(area => ({
					value: Number(area.value),
					label: area.label,
					mostrar_clientes: this.isAreaFlagEnabled(area.mostrar_clientes, true),
					mostrar_ausencias: this.isAreaFlagEnabled(area.mostrar_ausencias, true),
				}))
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [Areas] [{error}]', { error: String(err) }))
			}
		},

		async GetActividades() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetActividades'))
					.then(
						(response) => {
							const keyMap = {
								id_actividad: 'id',
								nombre: 'label',
								tiempo_real: 'count',
							}

							const renameKeys = (obj, map) =>
								Object.fromEntries(Object.entries(obj).map(([k, v]) => [map[k] ?? k, v]))

							const arr = Array.isArray(response?.data?.ocs?.data) ? response.data.ocs.data : []

							this.actividades = arr.map(o => renameKeys(o, keyMap))
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
							this.empresasOptions = data.map(o => ({
								id: o.id_cliente,
								label: o.nombre,
							}))

						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		async GetEmpleadosReports(requestId = this.reportRequestId) {
			try {
				const payload = { ...this.normalizedPeriod, id_empleado: null }
				const response = await axios.post(generateUrl('/apps/empleados/GetEmpleadosReports'), payload)
				if (requestId !== this.reportRequestId) return
				if (response?.data?.ocs?.meta?.status !== 'ok') {
					this.listas = []
					showError(response?.data?.ocs?.meta?.message)
					return
				}
				const keyMap = {
					Id_empleados: 'id',
					id_empleado: 'id',
					displayname: 'name',
					nombre: 'name',
					Id_user: 'image',
					id_user: 'image',
					total_tiempo_registrado: 'count',
					Sueldo: 'Sueldo',
				}

				const renameKeys = (obj, map) =>
					Object.fromEntries(
						Object.entries(obj).map(([k, v]) => {
							if (k === 'displayname') {
								return ['name', v ?? obj.Id_user]
							}
							return [map[k] ?? k, v]
						}),
					)

				const arr = Array.isArray(response?.data?.ocs?.data) ? response.data.ocs.data : []
				this.listas = arr.map(o => renameKeys(o, keyMap))
			} catch (err) {
				if (requestId === this.reportRequestId) {
					this.listas = []
					showError(t('empleados', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
				}
			}
		},

		onAreaFilterChange() {
			if (this._skipAreaWatch) {
				return
			}
			this.empleadoSeleccionado = null
			this._pendingEmployeeId = null
			this.enforceFilterDependencies()
			this.scheduleSaveReportPreferences()
			if (this._prefsReady) {
				this.reloadReportFilters()
			}
		},

		enforceFilterDependencies() {
			if (!this.showClientFilter) {
				this.clienteSeleccionado = null
			}
			if (!this.mostrarClientes && this.optionId(this.tipoTrabajoSeleccionado) === 'cliente') {
				this.tipoTrabajoSeleccionado = this.workTypeOptions.find(option => option.id === 'interno')
					|| this.workTypeOptions[0]
			}
			const activity = this.actividadSeleccionada
			if (activity && !this.activityFilterOptions.some(option => String(option.id) === String(activity.id))) {
				this.actividadSeleccionada = null
			}
		},

		async reloadReportFilters() {
			const requestId = ++this.reportRequestId
			this.closeEmployeeDetails()
			this.loadingResumen = true
			try {
				await Promise.all([
					this.GetEmpleadosReports(requestId),
					this.GetAdminReportsSummary(requestId),
				])
			} finally {
				if (requestId === this.reportRequestId) {
					this.loadingResumen = false
				}
			}
		},

		/**
		 * Normaliza flags de área que pueden llegar como bool, 0/1 o "0"/"1".
		 * @param {*} value Valor recibido del API.
		 * @param {boolean} defaultValue Valor usado cuando no existe la bandera.
		 * @return {boolean}
		 */
		isAreaFlagEnabled(value, defaultValue = true) {
			if (value === null || value === undefined || value === '') {
				return defaultValue
			}
			if (typeof value === 'boolean') {
				return value
			}
			if (typeof value === 'number') {
				return value === 1
			}
			const normalized = String(value).trim().toLowerCase()
			if (['0', 'false', 'no', 'off'].includes(normalized)) {
				return false
			}
			if (['1', 'true', 'yes', 'on'].includes(normalized)) {
				return true
			}
			return defaultValue
		},

		resolveAreaFlag(key, defaultValue = true) {
			const fromSelect = this.areaSeleccionada
			if (fromSelect && Object.prototype.hasOwnProperty.call(fromSelect, key)) {
				return this.isAreaFlagEnabled(fromSelect[key], defaultValue)
			}
			const fromSummary = this.resumenGeneral?.area
			if (fromSummary && Object.prototype.hasOwnProperty.call(fromSummary, key)) {
				return this.isAreaFlagEnabled(fromSummary[key], defaultValue)
			}
			return defaultValue
		},

		async gethistorial(id) {
			const requestId = ++this.detailRequestId
			this.selectedEmployeeId = Number(id)
			this.select = []
			this.sueldo = 0
			try {
				const payload = { ...this.normalizedPeriod }
				delete payload.id_empleado
				const response = await axios.post(generateUrl('/apps/empleados/GetReportesById'), {
					id,
					...payload,
				})
				if (requestId !== this.detailRequestId || Number(this.selectedEmployeeId) !== Number(id)) return
				const reports = response?.data?.ocs?.data
				this.select = Array.isArray(reports) ? reports : []
				this.sueldo = Number(this.listas.find(e => Number(e.id) === Number(id))?.Sueldo || 0)
			} catch (e) {
				if (requestId === this.detailRequestId) {
					showError(t('empleados', 'Could not fetch employee reports.'))
				}
			}
		},

		openEmployeeDetails(id) {
			return this.gethistorial(id)
		},

		closeEmployeeDetails() {
			this.detailRequestId++
			this.selectedEmployeeId = null
			this.select = []
			this.sueldo = 0
		},

		async GetAdminReportsSummary(requestId = this.reportRequestId) {
			try {
				const response = await axios.post(generateUrl('/apps/empleados/GetAdminReportsSummary'), this.normalizedPeriod)
				if (requestId !== this.reportRequestId) return

				if (response?.data?.ocs?.meta?.status !== 'ok') {
					this.resumenGeneral = null
					showError(response?.data?.ocs?.meta?.message)
					return
				}

				this.resumenGeneral = response?.data?.ocs?.data ?? null
			} catch (err) {
				if (requestId === this.reportRequestId) {
					this.resumenGeneral = null
					showError(t('empleados', 'Se ha producido una excepcion [Resumen] [{error}]', { error: String(err) }))
				}
			}
		},

		async Exportar() {
			if (this.exporting) return
			if (!this.normalizedPeriod.fecha_inicio || !this.normalizedPeriod.fecha_fin) {
				showError(t('empleados', 'Select a valid start and end date.'))
				return
			}
			this.exporting = true
			try {
				const response = await axios.post(
					generateUrl('/apps/empleados/ExportarReportes'),
					this.normalizedPeriod,
					{
						responseType: 'blob',
					},
				)
				const blob = new Blob([response.data], {
					type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				})

				const url = URL.createObjectURL(blob)
				const link = document.createElement('a')
				link.href = url
				link.download = this.exportFilename(response)
				document.body.appendChild(link)
				link.click()
				link.remove()
				URL.revokeObjectURL(url)
			} catch (err) {
				showError(t('empleados', 'Se ha producido un error {error}, reporte al administrador', { error: String(err) }))
			} finally {
				this.exporting = false
			}
		},

		exportFilename(response) {
			const disposition = String(response?.headers?.['content-disposition'] || '')
			const match = disposition.match(/filename="?([^";]+)"?/i)
			const filename = String(match?.[1] || '').trim()
			return filename
				? filename.replace(/[\\/:*?"<>|\r\n]/g, '_')
				: 'reportetiempo.xlsx'
		},

		async applyFilters() {
			if (!this.normalizedPeriod.fecha_inicio || !this.normalizedPeriod.fecha_fin) {
				showError(t('empleados', 'Select a valid start and end date.'))
				return
			}
			this.enforceFilterDependencies()
			this.persistReportPreferences()
			await this.reloadReportFilters()
			this.validateEmployeeFilterAfterLoad()
		},

		async clearReportFilters() {
			this._prefsReady = false
			this._skipAreaWatch = true
			this.areaSeleccionada = null
			this.empleadoSeleccionado = null
			this.clienteSeleccionado = null
			this.actividadSeleccionada = null
			this._pendingEmployeeId = null
			this.tipoTrabajoSeleccionado = this.workTypeOptions.find(option => option.id === 'todos') || this.workTypeOptions[0]
			this.setCurrentFortnight()
			resetPreference(PREFERENCE_KEYS.ADMIN_REPORTS, ADMIN_REPORT_DEFAULTS)
			this.clearLegacyAdminPreferenceKeys()
			await this.$nextTick()
			this._skipAreaWatch = false
			this._prefsReady = true
			this.persistReportPreferences()
			await this.reloadReportFilters()
		},

		loadReportPreferences() {
			if (!hasPreference(PREFERENCE_KEYS.ADMIN_REPORTS)) {
				return this.migrateLegacyAdminPreferences({
					filters: { ...ADMIN_REPORT_DEFAULTS.filters },
					view: { ...ADMIN_REPORT_DEFAULTS.view },
				})
			}
			return loadPreference(PREFERENCE_KEYS.ADMIN_REPORTS, ADMIN_REPORT_DEFAULTS)
		},

		migrateLegacyAdminPreferences(prefs) {
			const next = {
				filters: { ...ADMIN_REPORT_DEFAULTS.filters, ...(prefs.filters || {}) },
				view: { ...(prefs.view || {}) },
			}

			try {
				const legacyArea = Number(window.localStorage?.getItem(LEGACY_ADMIN_KEYS.area))
				if (Number.isFinite(legacyArea) && legacyArea > 0) {
					next.filters.areaId = legacyArea
				}
				const legacyStart = window.localStorage?.getItem(LEGACY_ADMIN_KEYS.fechaInicio)
				const legacyEnd = window.localStorage?.getItem(LEGACY_ADMIN_KEYS.fechaFin)
				if (legacyStart && legacyEnd) {
					next.filters.fechaInicio = legacyStart
					next.filters.fechaFin = legacyEnd
				}
				const legacyType = window.localStorage?.getItem(LEGACY_ADMIN_KEYS.tipoTrabajo)
				if (legacyType) {
					next.filters.workType = legacyType
				}
			} catch (error) {
				// Ignorar migración fallida.
			}

			return next
		},

		clearLegacyAdminPreferenceKeys() {
			try {
				Object.values(LEGACY_ADMIN_KEYS).forEach((key) => {
					window.localStorage?.removeItem(key)
				})
			} catch (error) {
				// Ignorar.
			}
		},

		applyDateFiltersFromPrefs(filters = {}) {
			const start = this.parseDateKey(filters.fechaInicio)
			const end = this.parseDateKey(filters.fechaFin)
			if (start && end) {
				this.fechaInicio = start
				this.fechaFin = end
				return
			}
			this.setCurrentFortnight()
		},

		applyViewPreferences(view = {}) {
			this.moreFiltersOpen = Boolean(view.moreFiltersOpen)
		},

		toggleMoreFilters() {
			this.moreFiltersOpen = !this.moreFiltersOpen
			this.scheduleSaveReportPreferences()
		},

		applyCatalogFiltersFromPrefs(filters = {}) {
			this._skipAreaWatch = true

			const areaId = this.normalizeSelectNumber(filters.areaId)
			this.areaSeleccionada = areaId !== null
				? (this.areasOptions.find(area => Number(area.value) === areaId) || null)
				: null

			const workType = filters.workType || 'todos'
			this.tipoTrabajoSeleccionado = this.workTypeOptions.find(option => option.id === workType)
				|| this.workTypeOptions[0]

			this.enforceFilterDependencies()

			const clientId = this.normalizeSelectNumber(filters.clientId)
			if (this.showClientFilter && clientId !== null) {
				this.clienteSeleccionado = this.clientesOptions.find(client => Number(client.id) === clientId) || null
			} else {
				this.clienteSeleccionado = null
			}

			const activityId = this.normalizeSelectNumber(filters.activityId)
			if (activityId !== null) {
				this.actividadSeleccionada = this.activityFilterOptions.find(activity => Number(activity.id) === activityId) || null
			} else {
				this.actividadSeleccionada = null
			}

			const employeeId = this.normalizeSelectNumber(filters.employeeId)
			this._pendingEmployeeId = employeeId
			this.empleadoSeleccionado = employeeId !== null
				? { id: employeeId, label: `#${employeeId}` }
				: null

			this.$nextTick(() => {
				this._skipAreaWatch = false
			})
		},

		validateEmployeeFilterAfterLoad() {
			const employeeId = this._pendingEmployeeId ?? this.normalizeSelectNumber(this.optionId(this.empleadoSeleccionado))
			this._pendingEmployeeId = null
			if (employeeId === null) {
				this.empleadoSeleccionado = null
				return false
			}

			const matched = this.empleadosOptions.find(employee => Number(employee.id) === Number(employeeId))
			if (matched) {
				this.empleadoSeleccionado = matched
				return false
			}

			this.empleadoSeleccionado = null
			return true
		},

		buildReportPreferencePayload() {
			return {
				filters: {
					areaId: this.normalizeSelectNumber(
						this.areaSeleccionada?.value ?? this.areaSeleccionada,
					),
					employeeId: this.normalizeSelectNumber(this.optionId(this.empleadoSeleccionado)),
					workType: this.optionId(this.tipoTrabajoSeleccionado) || 'todos',
					clientId: this.showClientFilter
						? this.normalizeSelectNumber(this.optionId(this.clienteSeleccionado))
						: null,
					activityId: this.normalizeSelectNumber(this.optionId(this.actividadSeleccionada)),
					fechaInicio: this.normalizedPeriod.fecha_inicio,
					fechaFin: this.normalizedPeriod.fecha_fin,
				},
				view: {
					moreFiltersOpen: Boolean(this.moreFiltersOpen),
				},
			}
		},

		persistReportPreferences() {
			savePreference(PREFERENCE_KEYS.ADMIN_REPORTS, this.buildReportPreferencePayload())
			this.clearLegacyAdminPreferenceKeys()
		},

		scheduleSaveReportPreferences() {
			if (!this._prefsReady) {
				return
			}
			this._debouncedSavePrefs?.()
		},

		setCurrentFortnight() {
			const today = new Date()
			today.setHours(12, 0, 0, 0)
			this.fechaInicio = new Date(today.getFullYear(), today.getMonth(), today.getDate() <= 15 ? 1 : 16, 12)
			this.fechaFin = today.getDate() <= 15
				? new Date(today.getFullYear(), today.getMonth(), 15, 12)
				: new Date(today.getFullYear(), today.getMonth() + 1, 0, 12)
		},

		parseDateKey(value) {
			const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/)
			if (!match) return null
			const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), 12)
			return Number.isNaN(date.getTime()) ? null : date
		},

		formatDateKey(value) {
			if (!value) return null
			if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) return value
			const date = value instanceof Date ? value : new Date(value)
			if (Number.isNaN(date.getTime())) return null
			const year = date.getFullYear()
			const month = String(date.getMonth() + 1).padStart(2, '0')
			const day = String(date.getDate()).padStart(2, '0')
			return `${year}-${month}-${day}`
		},

		formatDateDisplay(value) {
			const date = this.parseDateKey(value)
			return date
				? new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date)
				: ''
		},

		optionId(option) {
			if (option === null || option === undefined || option === '') return null
			return option && typeof option === 'object'
				? option.id ?? option.value ?? null
				: option
		},

		normalizeSelectNumber(value) {
			const raw = value && typeof value === 'object'
				? value.value ?? value.id ?? null
				: value

			if (raw === null || raw === undefined || raw === '') {
				return null
			}

			const number = Number(raw)

			return Number.isFinite(number) ? number : null
		},

		monthLabel(value) {
			const month = this.normalizeSelectNumber(value)

			return this.meses.find(m => m.value === month)?.label || '-'
		},
	},
}
</script>

<style scoped lang="scss">
.periodo-details {
	margin-bottom: 8px;
	text-align: left;
}

.report-context {
	display: flex;
	flex-direction: column;
	gap: 6px;
	margin: 4px 0 10px;
	padding: 8px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
}

.report-context__heading {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}

.report-context__title {
	margin: 0;
	font-size: 1.05rem;
	line-height: 1.25;
	font-weight: 600;
}

.report-context__meta {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 0 4px;
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.8rem;
	line-height: 1.3;
}

.report-toolbar {
	display: flex;
	flex-direction: column;
	gap: 8px;
	min-width: 0;
}

.report-toolbar__row {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 8px;
	min-width: 0;
}

.report-toolbar__row--advanced {
	padding-top: 8px;
	border-top: 1px solid var(--color-border);
}

.report-field {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}

.report-field__label {
	display: block;
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.68rem;
	font-weight: 600;
	letter-spacing: 0.03em;
	line-height: 1.2;
	text-transform: uppercase;
}

.report-field__control {
	min-width: 0;
	width: 100%;
}

.report-field--period {
	flex: 1 1 230px;
	max-width: 280px;
}

.report-field--area,
.report-field--employee,
.report-field--client,
.report-field--activity {
	flex: 1 1 140px;
	max-width: 200px;
}

.report-field--type {
	flex: 0 1 130px;
	max-width: 160px;
}

.report-period {
	display: flex;
	align-items: center;
	gap: 4px;
	min-width: 0;
	padding: 0 4px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
}

.report-period__picker {
	flex: 1 1 0;
	min-width: 0;
}

.report-period__sep {
	flex: 0 0 auto;
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
	line-height: 1;
	user-select: none;
}

.report-toolbar__actions {
	display: flex;
	flex: 0 0 auto;
	align-items: center;
	gap: 6px;
	margin-left: auto;
}

.report-toolbar__actions--secondary {
	margin-left: auto;
}

.report-toolbar :deep(.mx-datepicker),
.report-toolbar :deep(.mx-input-wrapper),
.report-toolbar :deep(input.mx-input) {
	width: 100%;
	min-width: 0;
}

.report-toolbar :deep(input.mx-input) {
	min-height: 34px;
	height: 34px;
	padding-top: 0;
	padding-bottom: 0;
	border: none;
	background: transparent;
	box-shadow: none;
}

.report-toolbar :deep(.v-select),
.report-toolbar :deep(.vs__dropdown-toggle) {
	min-height: 34px;
}

.report-toolbar :deep(.vs__dropdown-toggle) {
	padding-top: 0;
	padding-bottom: 0;
}

.report-toolbar :deep(.input-field),
.report-toolbar :deep(.select) {
	margin: 0;
}

.details-toolbar {
	display: flex;
	justify-content: flex-start;
	margin: 8px 0 14px;
}

.area-period-label {
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.summary-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 20px;
	margin: 24px 0;
}

.summary-card {
	background: #fff;
	border-radius: 10px;
	padding: 22px 20px;
	text-align: center;
	box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
	border: 1px solid rgba(0, 0, 0, 0.06);
}

.summary-value {
	font-family: "Cormorant Garamond", serif;
	font-size: 2.2rem;
	font-weight: 600;
	color: #555352;
	line-height: 1.1;
}

.summary-label {
	margin-top: 6px;
	font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
	font-size: 0.75rem;
	letter-spacing: 1.5px;
	text-transform: uppercase;
	color: #555352;
}

@media (max-width: 720px) {
	.report-field--period,
	.report-field--area,
	.report-field--employee,
	.report-field--type,
	.report-field--client,
	.report-field--activity {
		flex: 1 1 calc(50% - 8px);
		max-width: none;
	}

	.report-toolbar__actions {
		width: 100%;
		margin-left: 0;
		justify-content: flex-end;
	}
}

@media (max-width: 480px) {
	.report-field--period,
	.report-field--area,
	.report-field--employee,
	.report-field--type,
	.report-field--client,
	.report-field--activity {
		flex: 1 1 100%;
	}

	.report-toolbar__actions {
		flex-direction: column;
		align-items: stretch;
	}

	.summary-grid {
		grid-template-columns: 1fr;
	}
}
</style>
