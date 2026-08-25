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
					<div class="report-field report-field--period" role="group" :aria-label="t('empleados', 'Period')">
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

					<div class="report-field">
						<span class="report-field__label">{{ t('empleados', 'Customer') }}</span>
						<NcSelect
							v-model="clienteSeleccionado"
							:options="clientesOptions"
							:clearable="true"
							:placeholder="t('empleados', 'Customer')"
							class="report-field__control" />
					</div>

					<div class="report-field">
						<span class="report-field__label">{{ t('empleados', 'Status') }}</span>
						<NcSelect
							v-model="estadoSeleccionado"
							:options="estadoOptions"
							:clearable="false"
							class="report-field__control" />
					</div>

					<div class="report-toolbar__actions">
						<NcButton type="tertiary" :aria-expanded="moreFiltersOpen ? 'true' : 'false'" @click="moreFiltersOpen = !moreFiltersOpen">
							{{ moreFiltersOpen ? t('empleados', 'Fewer filters') : t('empleados', 'More filters') }}
							<template #icon>
								<ChevronUp v-if="moreFiltersOpen" :size="16" />
								<ChevronDown v-else :size="16" />
							</template>
						</NcButton>
						<NcButton type="primary" :disabled="loading" @click="applyFilters">
							{{ t('empleados', 'Apply filters') }}
						</NcButton>
					</div>
				</div>

				<div v-if="moreFiltersOpen" class="report-toolbar__row report-toolbar__row--advanced">
					<div class="report-field">
						<span class="report-field__label">{{ t('empleados', 'Parent group') }}</span>
						<NcSelect
							v-model="grupoSeleccionado"
							:options="grupoOptions"
							:clearable="true"
							:placeholder="t('empleados', 'Parent group')"
							class="report-field__control" />
					</div>

					<div class="report-field">
						<span class="report-field__label">{{ t('empleados', 'Project Manager') }}</span>
						<NcSelect
							v-model="liderSeleccionado"
							:options="lideresOptions"
							:clearable="true"
							:placeholder="t('empleados', 'Project Manager')"
							class="report-field__control" />
					</div>

					<div class="report-field">
						<span class="report-field__label">{{ t('empleados', 'Fee type') }}</span>
						<NcSelect
							v-model="tipoSeleccionado"
							:options="tipoOptions"
							:clearable="true"
							:placeholder="t('empleados', 'Fee type')"
							class="report-field__control" />
					</div>

					<div class="report-field report-field--check">
						<NcCheckboxRadioSwitch v-model="soloPendientes">
							{{ t('empleados', 'Outstanding fees only') }}
						</NcCheckboxRadioSwitch>
					</div>

					<div class="report-toolbar__actions report-toolbar__actions--secondary">
						<NcButton type="tertiary" :disabled="loading" @click="clearFilters">
							{{ t('empleados', 'Clear') }}
						</NcButton>
					</div>
				</div>
			</div>
		</header>

		<ClientesAnalyticsDashboard
			:resumen="resumen"
			:loading="loading"
			:error="error"
			@select-client="$emit('select-client', $event)" />
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

const FILTER_DEFAULTS = {
	fechaInicio: null,
	fechaFin: null,
	idCliente: null,
	clientePadre: null,
	liderProyecto: null,
	estado: 1,
	tipoHonorario: null,
	soloPendientes: false,
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
			fechaInicio: null,
			fechaFin: null,
			clienteSeleccionado: null,
			grupoSeleccionado: null,
			liderSeleccionado: null,
			estadoSeleccionado: { value: 1, label: '' },
			tipoSeleccionado: null,
			soloPendientes: false,
			moreFiltersOpen: false,
		}
	},

	computed: {
		clientesOptions() {
			return this.rawClients.map((item) => ({
				id: Number(item.id),
				value: Number(item.id),
				label: item.nombre,
			}))
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

		estadoOptions() {
			return [
				{ value: 1, label: t('empleados', 'Active') },
				{ value: 0, label: t('empleados', 'Inactive') },
				{ value: null, label: t('empleados', 'All') },
			]
		},

		tipoOptions() {
			return [
				{ value: 'parcial', label: t('empleados', 'Installments') },
				{ value: 'iguala', label: t('empleados', 'Retainer') },
				{ value: 'eventual', label: t('empleados', 'One-time') },
			]
		},

		periodLabel() {
			if (this.fechaInicio && this.fechaFin) {
				return `${this.formatDate(this.fechaInicio)} — ${this.formatDate(this.fechaFin)}`
			}
			return t('empleados', 'All periods')
		},

		catalogLabel() {
			const total = Number(this.resumen?.catalogo?.total || 0)
			return t('empleados', '{count} customers', { count: total })
		},
	},

	watch: {
		fechaInicio() { this.scheduleSave() },
		fechaFin() { this.scheduleSave() },
		clienteSeleccionado() { this.scheduleSave() },
		grupoSeleccionado() { this.scheduleSave() },
		liderSeleccionado() { this.scheduleSave() },
		estadoSeleccionado() { this.scheduleSave() },
		tipoSeleccionado() { this.scheduleSave() },
		soloPendientes() { this.scheduleSave() },
		moreFiltersOpen() { this.scheduleSave() },
	},

	created() {
		this._prefsReady = false
		this._debouncedSave = debounce(() => this.persistPreferences(), 300)
		this.estadoSeleccionado = this.estadoOptions[0]
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
	},

	methods: {
		t,

		payloadFilters() {
			return {
				id_cliente: this.clienteSeleccionado?.value ?? null,
				cliente_padre: this.grupoSeleccionado?.value ?? null,
				lider_proyecto: this.liderSeleccionado?.value ?? null,
				estado: this.estadoSeleccionado?.value,
				tipo_honorario: this.tipoSeleccionado?.value ?? null,
				solo_pendientes: this.soloPendientes ? 1 : 0,
				fecha_inicio: this.formatDate(this.fechaInicio),
				fecha_fin: this.formatDate(this.fechaFin),
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

		applyFilters() {
			return this.loadSummary()
		},

		clearFilters() {
			this.fechaInicio = null
			this.fechaFin = null
			this.clienteSeleccionado = null
			this.grupoSeleccionado = null
			this.liderSeleccionado = null
			this.estadoSeleccionado = this.estadoOptions[0]
			this.tipoSeleccionado = null
			this.soloPendientes = false
			this.moreFiltersOpen = false
			return this.applyFilters()
		},

		formatDate(value) {
			if (!value) {
				return null
			}
			if (value instanceof Date && !Number.isNaN(value.getTime())) {
				return value.toISOString().slice(0, 10)
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
			this.fechaInicio = filters.fechaInicio || null
			this.fechaFin = filters.fechaFin || null
			this.soloPendientes = Boolean(filters.soloPendientes)
			this.moreFiltersOpen = Boolean(filters.moreFiltersOpen)
			this._storedFilters = filters
		},

		restoreCatalogFilters() {
			const filters = this._storedFilters || {}
			this.clienteSeleccionado = this.clientesOptions.find((item) => item.value === Number(filters.idCliente)) || null
			this.grupoSeleccionado = this.grupoOptions.find((item) => item.value === Number(filters.clientePadre)) || null
			this.liderSeleccionado = this.lideresOptions.find((item) => item.value === Number(filters.liderProyecto)) || null
			this.estadoSeleccionado = this.estadoOptions.find((item) => item.value === filters.estado) || this.estadoOptions[0]
			this.tipoSeleccionado = this.tipoOptions.find((item) => item.value === filters.tipoHonorario) || null
		},

		persistPreferences() {
			if (!this._prefsReady) {
				return
			}
			const prefs = loadPreference(PREFERENCE_KEYS.CLIENTS_DASHBOARD, this.preferenceDefaults())
			savePreference(PREFERENCE_KEYS.CLIENTS_DASHBOARD, {
				filters: {
					fechaInicio: this.formatDate(this.fechaInicio),
					fechaFin: this.formatDate(this.fechaFin),
					idCliente: this.clienteSeleccionado?.value ?? null,
					clientePadre: this.grupoSeleccionado?.value ?? null,
					liderProyecto: this.liderSeleccionado?.value ?? null,
					estado: this.estadoSeleccionado?.value,
					tipoHonorario: this.tipoSeleccionado?.value ?? null,
					soloPendientes: this.soloPendientes,
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

.report-field {
	display: flex;
	flex-direction: column;
	gap: 0.3rem;
	min-width: 11rem;
	flex: 1 1 11rem;
}

.report-field__label {
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	font-weight: 700;
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.report-period {
	display: flex;
	align-items: center;
	gap: 0.4rem;
}

.report-toolbar__actions {
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
	margin-left: auto;
}

@media (max-width: 720px) {
	.clientes-dashboard {
		padding: 0.75rem;
	}

	.clientes-dashboard--embedded {
		padding: 0 0 1rem;
	}

	.report-toolbar__actions {
		margin-left: 0;
		width: 100%;
	}
}
</style>
