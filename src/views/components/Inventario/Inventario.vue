<template>
	<NcAppContent
		class="inventario-page"
		:name="t('empleados', 'IT Inventory')">
		<div class="inventario-header">
			<div class="inventario-heading">
				<h2>{{ t('empleados', 'IT Inventory') }}</h2>
				<p>{{ t('empleados', 'Computer equipment, device models and support history.') }}</p>
			</div>

			<NcButton
				type="primary"
				:disabled="tab === 'soporte' && !selectedEquipo"
				@click="openCreateModal">
				<template #icon>
					<Plus :size="20" />
				</template>
				{{ primaryButtonText }}
			</NcButton>
		</div>

		<div class="inventario-summary" aria-hidden="true">
			<div
				v-for="item in summaryItems"
				:key="item.id"
				class="summary-item">
				<component :is="item.icon" :size="20" />
				<span>{{ item.label }}</span>
				<strong>{{ item.value }}</strong>
			</div>
		</div>

		<div
			class="inventario-tabs"
			role="tablist"
			:aria-label="t('empleados', 'Inventory sections')">
			<button
				v-for="item in tabs"
				:key="item.id"
				type="button"
				role="tab"
				:aria-selected="tab === item.id"
				:class="{ active: tab === item.id }"
				@click="setTab(item.id)">
				<component :is="item.icon" :size="20" />
				<span>{{ item.name }}</span>
			</button>
		</div>

		<div class="inventario-toolbar">
			<div class="search-field">
				<NcTextField
					:value.sync="search"
					:label="t('empleados', 'Search')"
					@keyup.enter="reload">
					<template #icon>
						<Magnify :size="20" />
					</template>
				</NcTextField>
			</div>

			<NcButton
				:disabled="loading"
				:aria-label="t('empleados', 'Refresh inventory')"
				@click="reload">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<Refresh v-else :size="20" />
				</template>
				{{ t('empleados', 'Refresh') }}
			</NcButton>
		</div>

		<div class="inventario-card">
			<div v-if="loading" class="loading-state">
				<NcLoadingIcon :size="48" />
			</div>

			<template v-else>
				<!-- MODELOS -->
				<div v-if="tab === 'modelos'" class="table-wrap">
					<table v-if="modelos.length > 0" class="inventario-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Brand') }}</th>
								<th>{{ t('empleados', 'Model') }}</th>
								<th>{{ t('empleados', 'CPU') }}</th>
								<th>{{ t('empleados', 'RAM') }}</th>
								<th>{{ t('empleados', 'Storage') }}</th>
								<th>{{ t('empleados', 'Type') }}</th>
								<th>{{ t('empleados', 'Touch') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="modelo in modelos" :key="modelo.id_modelo">
								<td class="strong-cell">
									{{ displayValue(modelo.marca) }}
								</td>
								<td>{{ displayValue(modelo.modelo) }}</td>
								<td>{{ displayValue(modelo.procesador) }}</td>
								<td>{{ displayValue(modelo.ram) }}</td>
								<td>{{ displayValue(modelo.disco_duro) }}</td>
								<td>{{ displayValue(modelo.tipo) }}</td>
								<td>
									<span class="boolean-pill" :class="{ active: isTruthy(modelo.touch) }">
										<Check v-if="isTruthy(modelo.touch)" :size="16" />
										<Close v-else :size="16" />
										{{ isTruthy(modelo.touch) ? t('empleados', 'Yes') : t('empleados', 'No') }}
									</span>
								</td>
							</tr>
						</tbody>
					</table>

					<NcEmptyContent
						v-else
						:name="t('empleados', 'No models found')" />
				</div>

				<!-- EQUIPOS -->
				<div v-if="tab === 'equipos'" class="table-wrap">
					<table v-if="equipos.length > 0" class="inventario-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Device name') }}</th>
								<th>{{ t('empleados', 'System name') }}</th>
								<th>{{ t('empleados', 'Serial number') }}</th>
								<th>{{ t('empleados', 'Model') }}</th>
								<th>{{ t('empleados', 'Status') }}</th>
								<th>{{ t('empleados', 'Employee ID') }}</th>
								<th>{{ t('empleados', 'Support') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="equipo in equipos" :key="equipo.id_equipo">
								<td class="strong-cell">
									{{ displayValue(equipo.nombre_dispositivo) }}
								</td>
								<td>{{ displayValue(equipo.nombre_sistema) }}</td>
								<td>{{ displayValue(equipo.numero_serie) }}</td>
								<td>{{ modeloName(equipo) }}</td>
								<td>
									<span class="status-pill" :class="statusClass(equipo.estado)">
										{{ displayValue(equipo.estado) }}
									</span>
								</td>
								<td>{{ displayValue(equipo.id_empleado) }}</td>
								<td class="actions-cell">
									<NcButton
										size="small"
										:aria-label="t('empleados', 'View support history')"
										@click="selectEquipoSoporte(equipo)">
										<template #icon>
											<Eye :size="18" />
										</template>
										{{ t('empleados', 'View') }}
									</NcButton>
								</td>
							</tr>
						</tbody>
					</table>

					<NcEmptyContent
						v-else
						:name="t('empleados', 'No devices found')" />
				</div>

				<!-- SOPORTE -->
				<div v-if="tab === 'soporte'" class="table-wrap">
					<div v-if="selectedEquipo" class="selected-equipo">
						<div>
							<span>{{ t('empleados', 'Selected device') }}</span>
							<strong>{{ displayValue(selectedEquipo.nombre_dispositivo) }}</strong>
						</div>
						<span>{{ displayValue(selectedEquipo.numero_serie) }}</span>
					</div>

					<NcEmptyContent
						v-if="!selectedEquipo"
						:name="t('empleados', 'Select a device to view support history')" />

					<table v-else-if="soporte.length > 0" class="inventario-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Date') }}</th>
								<th>{{ t('empleados', 'Action') }}</th>
								<th>{{ t('empleados', 'Current user') }}</th>
								<th>{{ t('empleados', 'Support user') }}</th>
								<th>{{ t('empleados', 'Details') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="item in soporte" :key="item.id_soporte">
								<td>{{ displayValue(item.fecha) }}</td>
								<td class="strong-cell">
									{{ displayValue(item.accion) }}
								</td>
								<td>{{ displayValue(item.usuario_actual) }}</td>
								<td>{{ displayValue(item.usuario_soporte) }}</td>
								<td>{{ displayValue(item.detalles) }}</td>
							</tr>
						</tbody>
					</table>

					<NcEmptyContent
						v-else
						:name="t('empleados', 'No support records found')" />
				</div>
			</template>
		</div>

		<NcModal v-if="showModal" :name="modalTitle" @close="closeModal">
			<div class="inventario-modal">
				<!-- Crear modelo -->
				<div v-if="tab === 'modelos'" class="form-grid">
					<NcTextField :value.sync="formModelo.marca" :label="t('empleados', 'Brand')" />
					<NcTextField :value.sync="formModelo.modelo" :label="t('empleados', 'Model')" />
					<NcTextField :value.sync="formModelo.procesador" :label="t('empleados', 'CPU')" />
					<NcTextField :value.sync="formModelo.ram" :label="t('empleados', 'RAM')" />
					<NcTextField :value.sync="formModelo.disco_duro" :label="t('empleados', 'Storage')" />
					<NcTextField :value.sync="formModelo.tipo" :label="t('empleados', 'Type')" />
					<NcCheckboxRadioSwitch v-model="formModelo.touch">
						{{ t('empleados', 'Touch screen') }}
					</NcCheckboxRadioSwitch>
				</div>

				<!-- Crear equipo -->
				<div v-if="tab === 'equipos'" class="form-grid">
					<NcTextField :value.sync="formEquipo.id_empleado" :label="t('empleados', 'Employee ID')" />
					<NcTextField :value.sync="formEquipo.id_modelo" :label="t('empleados', 'Model ID')" />
					<NcTextField :value.sync="formEquipo.nombre_dispositivo" :label="t('empleados', 'Device name')" />
					<NcTextField :value.sync="formEquipo.nombre_sistema" :label="t('empleados', 'System name')" />
					<NcTextField :value.sync="formEquipo.numero_serie" :label="t('empleados', 'Serial number')" />
					<NcTextField :value.sync="formEquipo.estado" :label="t('empleados', 'Status')" />
					<NcTextArea :value.sync="formEquipo.info" :label="t('empleados', 'Information')" />
				</div>

				<!-- Crear soporte -->
				<div v-if="tab === 'soporte'" class="form-grid form-grid--single">
					<p v-if="selectedEquipo" class="modal-context">
						{{ displayValue(selectedEquipo.nombre_dispositivo) }} - {{ displayValue(selectedEquipo.numero_serie) }}
					</p>
					<NcTextField :value.sync="formSoporte.accion" :label="t('empleados', 'Action')" />
					<NcTextField :value.sync="formSoporte.usuario_actual" :label="t('empleados', 'Current user')" />
					<NcTextField :value.sync="formSoporte.usuario_soporte" :label="t('empleados', 'Support user')" />
					<NcTextArea :value.sync="formSoporte.detalles" :label="t('empleados', 'Details')" />
				</div>

				<div class="inventario-modal-actions">
					<NcButton @click="closeModal">
						{{ t('empleados', 'Cancel') }}
					</NcButton>

					<NcButton
						type="primary"
						:disabled="loading"
						@click="saveModal">
						{{ t('empleados', 'Save') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</NcAppContent>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'

import {
	NcAppContent,
	NcButton,
	NcTextField,
	NcTextArea,
	NcModal,
	NcEmptyContent,
	NcLoadingIcon,
	NcCheckboxRadioSwitch,
} from '@nextcloud/vue'

import Check from 'vue-material-design-icons/Check.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Database from 'vue-material-design-icons/Database.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import History from 'vue-material-design-icons/History.vue'
import Laptop from 'vue-material-design-icons/Laptop.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Wrench from 'vue-material-design-icons/Wrench.vue'

import inventarioService from '../../../services/inventarioService.js'

export default {
	name: 'Inventario',

	components: {
		NcAppContent,
		NcButton,
		NcTextField,
		NcTextArea,
		NcModal,
		NcEmptyContent,
		NcLoadingIcon,
		NcCheckboxRadioSwitch,
		Check,
		Close,
		Database,
		Eye,
		History,
		Laptop,
		Magnify,
		Plus,
		Refresh,
		Wrench,
	},

	data() {
		return {
			tab: 'modelos',
			search: '',
			loading: false,
			showModal: false,

			modelos: [],
			equipos: [],
			soporte: [],

			selectedEquipo: null,

			formModelo: {
				marca: '',
				modelo: '',
				procesador: '',
				ram: '',
				disco_duro: '',
				tipo: '',
				touch: false,
			},

			formEquipo: {
				id_empleado: '',
				id_modelo: '',
				nombre_dispositivo: '',
				nombre_sistema: '',
				numero_serie: '',
				estado: 'activo',
				info: '',
			},

			formSoporte: {
				accion: '',
				detalles: '',
				usuario_actual: '',
				usuario_soporte: '',
			},
		}
	},

	computed: {
		tabs() {
			return [
				{
					id: 'modelos',
					name: t('empleados', 'Models'),
					icon: 'Database',
				},
				{
					id: 'equipos',
					name: t('empleados', 'Devices'),
					icon: 'Laptop',
				},
				{
					id: 'soporte',
					name: t('empleados', 'Support'),
					icon: 'Wrench',
				},
			]
		},

		summaryItems() {
			return [
				{
					id: 'modelos',
					label: t('empleados', 'Models'),
					value: this.modelos.length,
					icon: 'Database',
				},
				{
					id: 'equipos',
					label: t('empleados', 'Devices'),
					value: this.equipos.length,
					icon: 'Laptop',
				},
				{
					id: 'soporte',
					label: t('empleados', 'Support'),
					value: this.soporte.length,
					icon: 'History',
				},
			]
		},

		primaryButtonText() {
			if (this.tab === 'modelos') return t('empleados', 'New model')
			if (this.tab === 'equipos') return t('empleados', 'New device')
			return t('empleados', 'New support record')
		},

		modalTitle() {
			return this.primaryButtonText
		},
	},

	watch: {
		tab() {
			this.reload()
		},
	},

	mounted() {
		this.reload()
	},

	methods: {
		t,

		setTab(tab) {
			this.tab = tab
		},

		async reload() {
			try {
				this.loading = true

				if (this.tab === 'modelos') {
					const res = await inventarioService.getModelos({ search: this.search })
					this.modelos = this.normalizeCollection(res)
				}

				if (this.tab === 'equipos') {
					const res = await inventarioService.getEquipos({ search: this.search })
					this.equipos = this.normalizeCollection(res)
				}

				if (this.tab === 'soporte' && this.selectedEquipo) {
					const res = await inventarioService.getSoporteEquipo(this.selectedEquipo.id_equipo)
					this.soporte = this.normalizeCollection(res)
				}
			} catch (error) {
				showError(t('empleados', 'Error loading inventory: {error}', {
					error: String(error),
				}))
			} finally {
				this.loading = false
			}
		},

		normalizeCollection(response) {
			if (Array.isArray(response)) {
				return response
			}

			if (Array.isArray(response?.data)) {
				return response.data
			}

			return []
		},

		displayValue(value) {
			return value === null || value === undefined || value === '' ? '-' : value
		},

		isTruthy(value) {
			return value === true
				|| value === 'true'
				|| value === 1
				|| value === '1'
		},

		modeloName(equipo) {
			const model = [equipo.marca, equipo.modelo].filter(Boolean).join(' ')

			return model || this.displayValue(equipo.id_modelo)
		},

		statusClass(status) {
			const normalized = String(status || '').toLowerCase()

			return {
				'status-pill--success': ['activo', 'active', 'asignado'].includes(normalized),
				'status-pill--warning': ['mantenimiento', 'support', 'soporte'].includes(normalized),
				'status-pill--muted': !normalized || ['inactivo', 'inactive', 'baja'].includes(normalized),
			}
		},

		openCreateModal() {
			if (this.tab === 'soporte' && !this.selectedEquipo) {
				showError(t('empleados', 'Select a device first.'))
				return
			}

			this.showModal = true
		},

		closeModal() {
			this.showModal = false
		},

		async saveModal() {
			try {
				if (this.tab === 'modelos') {
					await inventarioService.crearModelo(this.formModelo)
					showSuccess(t('empleados', 'Model created successfully.'))
					this.resetModelo()
				}

				if (this.tab === 'equipos') {
					await inventarioService.crearEquipo({
						...this.formEquipo,
						id_empleado: this.formEquipo.id_empleado ? Number(this.formEquipo.id_empleado) : null,
						id_modelo: this.formEquipo.id_modelo ? Number(this.formEquipo.id_modelo) : null,
					})
					showSuccess(t('empleados', 'Device created successfully.'))
					this.resetEquipo()
				}

				if (this.tab === 'soporte') {
					await inventarioService.crearSoporte({
						...this.formSoporte,
						id_equipo: this.selectedEquipo.id_equipo,
					})
					showSuccess(t('empleados', 'Support record created successfully.'))
					this.resetSoporte()
				}

				this.closeModal()
				await this.reload()
			} catch (error) {
				showError(t('empleados', 'Error saving inventory data: {error}', {
					error: String(error),
				}))
			}
		},

		async selectEquipoSoporte(equipo) {
			this.selectedEquipo = equipo
			this.tab = 'soporte'
			await this.reload()
		},

		resetModelo() {
			this.formModelo = {
				marca: '',
				modelo: '',
				procesador: '',
				ram: '',
				disco_duro: '',
				tipo: '',
				touch: false,
			}
		},

		resetEquipo() {
			this.formEquipo = {
				id_empleado: '',
				id_modelo: '',
				nombre_dispositivo: '',
				nombre_sistema: '',
				numero_serie: '',
				estado: 'activo',
				info: '',
			}
		},

		resetSoporte() {
			this.formSoporte = {
				accion: '',
				detalles: '',
				usuario_actual: '',
				usuario_soporte: '',
			}
		},
	},
}
</script>

<style scoped>
.inventario-page {
	width: 100%;
	overflow: auto;
}

.inventario-header {
	display: flex;
	gap: 16px;
	justify-content: space-between;
	align-items: flex-start;
	padding: 24px 24px 12px;
}

.inventario-heading {
	min-width: 0;
}

.inventario-heading h2 {
	margin: 0 0 4px;
	font-size: 24px;
	font-weight: 700;
	line-height: 1.25;
}

.inventario-heading p {
	margin: 0;
	color: var(--color-text-maxcontrast);
	line-height: 1.4;
}

.inventario-summary {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 8px;
	padding: 0 24px 16px;
}

.summary-item {
	display: grid;
	grid-template-columns: 24px minmax(0, 1fr) auto;
	gap: 8px;
	align-items: center;
	min-height: 44px;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-main-background);
	color: var(--color-text-maxcontrast);
}

.summary-item strong {
	color: var(--color-main-text);
	font-size: 18px;
}

.inventario-tabs {
	display: flex;
	gap: 4px;
	padding: 0 24px;
	border-bottom: 1px solid var(--color-border);
}

.inventario-tabs button {
	display: inline-flex;
	gap: 8px;
	align-items: center;
	min-height: 44px;
	margin: 0;
	border: none;
	border-bottom: 2px solid transparent;
	background: transparent;
	color: var(--color-main-text);
	cursor: pointer;
	font-weight: 600;
	padding: 0 14px;
}

.inventario-tabs button:hover,
.inventario-tabs button:focus-visible {
	background-color: var(--color-background-hover);
}

.inventario-tabs button.active {
	border-bottom-color: var(--color-primary-element);
	color: var(--color-primary-element);
}

.inventario-toolbar {
	display: flex;
	gap: 12px;
	align-items: center;
	padding: 16px 24px;
}

.search-field {
	width: min(420px, 100%);
}

.inventario-card {
	margin: 0 24px 24px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	overflow: hidden;
}

.table-wrap {
	overflow-x: auto;
}

.loading-state {
	display: grid;
	place-items: center;
	min-height: 240px;
}

.inventario-table {
	width: 100%;
	border-collapse: collapse;
	table-layout: fixed;
}

.inventario-table th,
.inventario-table td {
	padding: 12px;
	border-bottom: 1px solid var(--color-border);
	text-align: left;
	vertical-align: top;
	overflow-wrap: anywhere;
}

.inventario-table th {
	font-weight: 700;
	color: var(--color-text-maxcontrast);
	background-color: var(--color-background-hover);
}

.inventario-table tbody tr:hover {
	background-color: var(--color-background-hover);
}

.inventario-table tr:last-child td {
	border-bottom: none;
}

.strong-cell {
	font-weight: 700;
}

.actions-cell {
	width: 1%;
	white-space: nowrap;
}

.status-pill,
.boolean-pill {
	display: inline-flex;
	gap: 4px;
	align-items: center;
	min-height: 24px;
	padding: 2px 8px;
	border-radius: 999px;
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.status-pill--success,
.boolean-pill.active {
	background-color: var(--color-success);
	color: var(--color-primary-element-text);
}

.status-pill--warning {
	background-color: var(--color-warning);
	color: var(--color-main-text);
}

.status-pill--muted {
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}

.selected-equipo {
	display: flex;
	gap: 12px;
	justify-content: space-between;
	align-items: center;
	padding: 12px 16px;
	border-bottom: 1px solid var(--color-border);
	background-color: var(--color-background-hover);
}

.selected-equipo div {
	display: grid;
	gap: 2px;
}

.selected-equipo span {
	color: var(--color-text-maxcontrast);
}

.inventario-modal {
	padding: 24px;
	width: min(720px, calc(100vw - 48px));
	display: flex;
	flex-direction: column;
	gap: 18px;
}

.form-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 14px;
}

.form-grid--single {
	grid-template-columns: 1fr;
}

.modal-context {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.inventario-modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 20px;
}

@media (max-width: 900px) {
	.inventario-summary {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 700px) {
	.inventario-header,
	.inventario-toolbar,
	.selected-equipo {
		align-items: stretch;
		flex-direction: column;
	}

	.inventario-tabs {
		overflow-x: auto;
	}

	.form-grid {
		grid-template-columns: 1fr;
	}
}
</style>
