<template>
	<div class="clientes-dashboard" :class="{ 'clientes-dashboard--embedded': embedded }">
		<header class="report-context">
			<p v-if="!embedded" class="report-context__meta">
				<span>{{ periodLabel }}</span>
				<span aria-hidden="true"> · </span>
				<span>{{ catalogLabel }}</span>
			</p>

			<div class="report-toolbar" :class="{ 'report-toolbar--expanded': moreFiltersOpen }">
				<div class="report-toolbar__row report-toolbar__row--main">
					<div class="report-field report-field--period">
						<span id="clientes-period-label" class="report-field__label">
							{{ t('empleados', 'Period') }}
						</span>
						<NcSelect
							v-model="periodoSeleccionado"
							:options="periodoOptions"
							:clearable="false"
							:searchable="false"
							:aria-labelledby="'clientes-period-label'"
							class="report-field__control" />
					</div>

					<div class="report-field report-field--check">
						<NcCheckboxRadioSwitch v-model="soloPendientes" type="switch">
							{{ t('empleados', 'Outstanding fees only') }}
						</NcCheckboxRadioSwitch>
					</div>

					<div class="report-toolbar__actions">
						<NcButton v-if="hasActiveFilters"
							type="tertiary"
							:disabled="loading"
							@click="clearFilters">
							{{ t('empleados', 'Clear') }}
						</NcButton>
						<NcButton type="tertiary"
							:aria-expanded="moreFiltersOpen ? 'true' : 'false'"
							@click="moreFiltersOpen = !moreFiltersOpen">
							{{ moreFiltersOpen ? t('empleados', 'Fewer filters') : t('empleados', 'More filters') }}
							<template #icon>
								<ChevronUp v-if="moreFiltersOpen" :size="16" />
								<ChevronDown v-else :size="16" />
							</template>
						</NcButton>
						<span v-if="!moreFiltersOpen && advancedFilterCount > 0" class="filter-badge">
							{{ advancedFilterCount }}
						</span>
					</div>
				</div>

				<div v-if="isCustomPeriod"
					class="report-toolbar__row report-toolbar__row--range">
					<div class="report-field report-field--range"
						role="group"
						:aria-label="t('empleados', 'Period')">
						<span class="report-field__label">{{ t('empleados', 'Custom period') }}</span>
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
				</div>

				<div v-if="moreFiltersOpen" class="report-toolbar__row report-toolbar__row--advanced">
					<div class="report-field">
						<span id="clientes-group-label" class="report-field__label">
							{{ t('empleados', 'Parent group') }}
						</span>
						<NcSelect
							v-model="grupoSeleccionado"
							:options="grupoOptions"
							:clearable="true"
							:aria-labelledby="'clientes-group-label'"
							:placeholder="t('empleados', 'Parent group')"
							class="report-field__control" />
					</div>

					<div class="report-field">
						<span id="clientes-manager-label" class="report-field__label">
							{{ t('empleados', 'Project Manager') }}
						</span>
						<NcSelect
							v-model="liderSeleccionado"
							:options="lideresOptions"
							:clearable="true"
							:aria-labelledby="'clientes-manager-label'"
							:placeholder="t('empleados', 'Project Manager')"
							class="report-field__control" />
					</div>

					<div class="report-field">
						<span id="clientes-fee-type-label" class="report-field__label">
							{{ t('empleados', 'Fee type') }}
						</span>
						<NcSelect
							v-model="tipoSeleccionado"
							:options="tipoOptions"
							:clearable="true"
							:aria-labelledby="'clientes-fee-type-label'"
							:placeholder="t('empleados', 'Fee type')"
							class="report-field__control" />
					</div>

					<div class="report-field report-field--check">
						<NcCheckboxRadioSwitch v-model="incluirInactivos">
							{{ t('empleados', 'Include inactive customers') }}
						</NcCheckboxRadioSwitch>
					</div>
				</div>
			</div>
		</header>

		<ClientesAnalyticsDashboard
			:resumen="resumen"
			:loading="loading"
			:error="error"
			@select-client="forwardSelectClient" />
	</div>
</template>

<script>
import debounce from 'debounce'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import {
	NcButton,
	NcCheckboxRadioSwitch,
	NcDateTimePicker,
	NcSelect,
} from '@nextcloud/vue'
import clientesService from '../../../services/clientesService.js'
import { loadPreference, savePreference, PREFERENCE_KEYS } from '../../../utils/userPreferences.js'
import ClientesAnalyticsDashboard from './ClientesAnalyticsDashboard.vue'

const PERIOD_ALL = 'all'
const PERIOD_CUSTOM = 'custom'

const FILTER_DEFAULTS = {
	periodo: PERIOD_ALL,
	fechaInicio: null,
	fechaFin: null,
	clientePadre: null,
	liderProyecto: null,
	tipoHonorario: null,
	soloPendientes: false,
	incluirInactivos: false,
	moreFiltersOpen: false,
}

export default {
	name: 'ClientesDashboard',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDateTimePicker,
		NcSelect,
		ChevronDown,
		ChevronUp,
		ClientesAnalyticsDashboard,
	},

	props: {
		embedded: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			loading: true,
			error: '',
			resumen: {},
			rawClients: [],
			lideres: [],
			periodoSeleccionado: null,
			fechaInicio: null,
			fechaFin: null,
			grupoSeleccionado: null,
			liderSeleccionado: null,
			tipoSeleccionado: null,
			soloPendientes: false,
			incluirInactivos: false,
			moreFiltersOpen: false,
		}
	},

	computed: {
		periodoOptions() {
			return [
				{ value: PERIOD_ALL, label: t('empleados', 'All periods') },
				{ value: 'month', label: t('empleados', 'This month') },
				{ value: 'year', label: t('empleados', 'This year') },
				{ value: 'last12', label: t('empleados', 'Last 12 months') },
				{ value: PERIOD_CUSTOM, label: t('empleados', 'Custom period') },
			]
		},

		isCustomPeriod() {
			return this.periodoSeleccionado?.value === PERIOD_CUSTOM
		},

		grupoOptions() {
			return this.rawClients
				.filter((item) => Number(item.cliente_padre || 0) === 0)
				.map((item) => ({
					id: Number(item.id),
					value: Number(item.id),
					label: item.nombre,
				}))
		},

		lideresOptions() {
			return this.lideres.map((item) => ({
				value: Number(item.id_empleado),
				label: item.displayname || item.uid,
			}))
		},

		tipoOptions() {
			return [
				{ value: 'parcial', label: t('empleados', 'Installments') },
				{ value: 'iguala', label: t('empleados', 'Retainer') },
				{ value: 'eventual', label: t('empleados', 'One-time') },
			]
		},

		/** Rango efectivo según el preset o las fechas manuales. */
		periodRange() {
			const preset = this.periodoSeleccionado?.value || PERIOD_ALL

			if (preset === PERIOD_CUSTOM) {
				return { inicio: this.fechaInicio, fin: this.fechaFin }
			}

			if (preset === PERIOD_ALL) {
				return { inicio: null, fin: null }
			}

			const now = new Date()

			if (preset === 'month') {
				return {
					inicio: new Date(now.getFullYear(), now.getMonth(), 1),
					fin: new Date(now.getFullYear(), now.getMonth() + 1, 0),
				}
			}

			if (preset === 'year') {
				return {
					inicio: new Date(now.getFullYear(), 0, 1),
					fin: new Date(now.getFullYear(), 11, 31),
				}
			}

			return {
				inicio: new Date(now.getFullYear(), now.getMonth() - 11, 1),
				fin: new Date(now.getFullYear(), now.getMonth() + 1, 0),
			}
		},

		advancedFilterCount() {
			return [
				this.grupoSeleccionado,
				this.liderSeleccionado,
				this.tipoSeleccionado,
				this.incluirInactivos || null,
			].filter(Boolean).length
		},

		hasActiveFilters() {
			return this.advancedFilterCount > 0
				|| this.soloPendientes
				|| (this.periodoSeleccionado?.value || PERIOD_ALL) !== PERIOD_ALL
		},

		periodLabel() {
			const { inicio, fin } = this.periodRange
			if (inicio && fin) {
				return `${this.formatDate(inicio)} — ${this.formatDate(fin)}`
			}
			return t('empleados', 'All periods')
		},

		catalogLabel() {
			const total = Number(this.resumen?.catalogo?.total || 0)
			return t('empleados', '{count} customers', { count: total })
		},
	},

	watch: {
		periodoSeleccionado() { this.onFilterChange() },
		fechaInicio() { this.onFilterChange() },
		fechaFin() { this.onFilterChange() },
		grupoSeleccionado() { this.onFilterChange() },
		liderSeleccionado() { this.onFilterChange() },
		tipoSeleccionado() { this.onFilterChange() },
		soloPendientes() { this.onFilterChange() },
		incluirInactivos() { this.onFilterChange() },
		moreFiltersOpen() { this.scheduleSave() },
	},

	created() {
		this._prefsReady = false
		this._debouncedSave = debounce(() => this.persistPreferences(), 300)
		this._debouncedReload = debounce(() => this.loadSummary(), 400)
		this.periodoSeleccionado = this.periodoOptions[0]
	},

	async mounted() {
		this.restorePreferences()
		try {
			const [clients, employees] = await Promise.all([
				clientesService.getCompanies(),
				clientesService.getEmployeesLookup(),
			])
			this.rawClients = Array.isArray(clients) ? clients : []
			this.lideres = Array.isArray(employees) ? employees : []
			this.restoreCatalogFilters()
			await this.loadSummary()
		} catch (error) {
			this.error = String(error)
		} finally {
			this._prefsReady = true
			this.loading = false
		}
	},

	beforeDestroy() {
		this._debouncedSave?.flush?.()
		this._debouncedSave?.clear?.()
		this._debouncedReload?.clear?.()
	},

	methods: {
		t,

		forwardSelectClient(id) {
			const clientId = Number(id)
			if (!Number.isFinite(clientId) || clientId <= 0) {
				return
			}
			this.$emit('select-client', clientId)
		},

		payloadFilters() {
			const { inicio, fin } = this.periodRange

			return {
				cliente_padre: this.grupoSeleccionado?.value ?? null,
				lider_proyecto: this.liderSeleccionado?.value ?? null,
				estado: this.incluirInactivos ? null : 1,
				tipo_honorario: this.tipoSeleccionado?.value ?? null,
				solo_pendientes: this.soloPendientes ? 1 : 0,
				fecha_inicio: this.formatDate(inicio),
				fecha_fin: this.formatDate(fin),
			}
		},

		async loadSummary() {
			this.loading = true
			this.error = ''
			try {
				this.resumen = await clientesService.getDashboardSummary(this.payloadFilters())
			} catch (error) {
				this.resumen = {}
				this.error = t('empleados', 'The customers dashboard could not be loaded.')
				showError(this.error)
			} finally {
				this.loading = false
			}
		},

		/** Los filtros se aplican solos: no hay botón "Aplicar". */
		onFilterChange() {
			this.scheduleSave()
			if (!this._prefsReady) {
				return
			}
			this._debouncedReload?.()
		},

		clearFilters() {
			this.periodoSeleccionado = this.periodoOptions[0]
			this.fechaInicio = null
			this.fechaFin = null
			this.grupoSeleccionado = null
			this.liderSeleccionado = null
			this.tipoSeleccionado = null
			this.soloPendientes = false
			this.incluirInactivos = false
		},

		formatDate(value) {
			if (!value) {
				return null
			}
			if (value instanceof Date && !Number.isNaN(value.getTime())) {
				const month = String(value.getMonth() + 1).padStart(2, '0')
				const day = String(value.getDate()).padStart(2, '0')
				return `${value.getFullYear()}-${month}-${day}`
			}
			const text = String(value)
			return /^\d{4}-\d{2}-\d{2}/.test(text) ? text.slice(0, 10) : null
		},

		preferenceDefaults() {
			return { filters: { ...FILTER_DEFAULTS }, view: {} }
		},

		restorePreferences() {
			const prefs = loadPreference(PREFERENCE_KEYS.CLIENTS_DASHBOARD, this.preferenceDefaults())
			const filters = prefs.filters || {}

			this.periodoSeleccionado = this.periodoOptions.find((item) => item.value === filters.periodo)
				|| (filters.fechaInicio || filters.fechaFin
					? this.periodoOptions.find((item) => item.value === PERIOD_CUSTOM)
					: this.periodoOptions[0])
			this.fechaInicio = filters.fechaInicio || null
			this.fechaFin = filters.fechaFin || null
			this.soloPendientes = Boolean(filters.soloPendientes)
			// Preferencias previas guardaban estado (1 activo / null todos).
			this.incluirInactivos = filters.incluirInactivos ?? (filters.estado === null)
			this.moreFiltersOpen = Boolean(filters.moreFiltersOpen)
			this._storedFilters = filters
		},

		restoreCatalogFilters() {
			const filters = this._storedFilters || {}
			this.grupoSeleccionado = this.grupoOptions.find((item) => item.value === Number(filters.clientePadre)) || null
			this.liderSeleccionado = this.lideresOptions.find((item) => item.value === Number(filters.liderProyecto)) || null
			this.tipoSeleccionado = this.tipoOptions.find((item) => item.value === filters.tipoHonorario) || null
		},

		persistPreferences() {
			if (!this._prefsReady) {
				return
			}
			const prefs = loadPreference(PREFERENCE_KEYS.CLIENTS_DASHBOARD, this.preferenceDefaults())
			savePreference(PREFERENCE_KEYS.CLIENTS_DASHBOARD, {
				filters: {
					periodo: this.periodoSeleccionado?.value || PERIOD_ALL,
					fechaInicio: this.formatDate(this.fechaInicio),
					fechaFin: this.formatDate(this.fechaFin),
					clientePadre: this.grupoSeleccionado?.value ?? null,
					liderProyecto: this.liderSeleccionado?.value ?? null,
					tipoHonorario: this.tipoSeleccionado?.value ?? null,
					soloPendientes: this.soloPendientes,
					incluirInactivos: this.incluirInactivos,
					moreFiltersOpen: this.moreFiltersOpen,
				},
				view: { ...(prefs.view || {}) },
			})
		},

		scheduleSave() {
			if (!this._prefsReady) {
				return
			}
			this._debouncedSave?.()
		},
	},
}
</script>

<style scoped lang="scss">
.clientes-dashboard {
	display: flex;
	flex-direction: column;
	gap: 1rem;
	width: 100%;
	padding: 1rem 1.25rem 2rem;
	box-sizing: border-box;
	color: var(--color-main-text);
}

.clientes-dashboard--embedded {
	padding: 0 0 1.5rem;
}

.report-context {
	display: flex;
	flex-direction: column;
	gap: 0.85rem;
}

.report-context__meta {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
}

.report-toolbar {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
}

.report-toolbar__row {
	display: flex;
	flex-wrap: wrap;
	gap: 0.75rem 1rem;
	align-items: flex-end;
}

.report-toolbar__row--main {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
}

.report-field {
	display: flex;
	flex-direction: column;
	gap: 0.3rem;
	min-width: 11rem;
	flex: 1 1 11rem;
}

.report-field--period {
	min-width: 0;
	max-width: 13rem;
	flex: 0 0 13rem;
}

.report-field--range {
	width: min(100%, 28rem);
	min-width: 0;
	flex: 0 1 28rem;
}

.report-field--check {
	flex: 0 1 auto;
	justify-content: center;
	min-width: 0;
	min-height: 44px;
}

.report-field__control {
	width: 100%;
	min-width: 0;
}

.report-field__label {
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	font-weight: 700;
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.report-period {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
	align-items: center;
	gap: 0.4rem;
	width: 100%;
}

.report-period__picker {
	width: 100%;
	min-width: 0;
}

.report-toolbar__actions {
	display: flex;
	flex-wrap: nowrap;
	align-items: center;
	gap: 0.5rem;
	margin-left: auto;
	justify-self: end;
}

.filter-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 1.15rem;
	height: 1.15rem;
	padding: 0 0.3rem;
	border-radius: 999px;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-size: 0.7rem;
	font-weight: 700;
}

@media (max-width: 720px) {
	.clientes-dashboard {
		padding: 0.75rem;
	}

	.clientes-dashboard--embedded {
		padding: 0 0 1rem;
	}

	.report-toolbar__row--main {
		display: flex;
		align-items: stretch;
		flex-direction: column;
	}

	.report-field--period,
	.report-field--range {
		width: 100%;
		max-width: none;
	}

	.report-field--period,
	.report-field--range,
	.report-field--check,
	.report-field {
		flex: 1 1 auto;
	}

	.report-field--check {
		align-items: flex-start;
		min-height: 0;
	}

	.report-period {
		grid-template-columns: 1fr;
	}

	.report-period__sep {
		display: none;
	}

	.report-toolbar__actions {
		flex-wrap: wrap;
		margin-left: 0;
		width: 100%;
		justify-content: flex-start;
		justify-self: start;
	}
}
</style>
