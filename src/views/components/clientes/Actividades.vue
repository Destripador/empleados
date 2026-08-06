<template id="content">
	<NcAppContent :name="t('empleados', 'Employees - Activities')">
		<List
			:loading="loading"
			:listas="filteredListas"
			:select="select"
			:show-options="canAdminCustomers"
			:defaultbuttons="canAdminCustomers">
			<template #custombuttons>
				<div class="filter-wrap">
					<NcButton
						class="filter-icon-button"
						type="tertiary"
						:title="t('empleados', 'Filters')"
						@click.stop="toggleFilters">
						<template #icon>
							<FilterVariant :size="23" />
						</template>

						{{ t('empleados') }}

						<span
							v-if="activeFilterCount > 0"
							class="filter-badge">
							{{ activeFilterCount }}
						</span>
					</NcButton>

					<div v-if="showFilters"
						class="filter-dropdown"
						@click.stop>
						<div class="filter-section">
							<p class="filter-section-label">
								{{ t('empleados', 'Sort') }}
							</p>
							<select v-model="sortOrder">
								<option value="az">
									A to Z
								</option>
								<option value="za">
									Z to A
								</option>
							</select>
						</div>
						<hr class="filter-divider">
						<div class="filter-section">
							<label>
								<input v-model="onlyBillable" type="checkbox">
								{{ t('empleados', 'Only Billable') }}
							</label>
						</div>
					</div>
				</div>
			</template>
			<template #details>
				<ActividadesDetalles :select="select" />
			</template>
		</List>

		<NcModal
			v-if="modal && canAdminCustomers"
			ref="modalRef"
			size="normal"
			:name="editing ? t('empleados', 'Edit activity') : t('empleados', 'New activity')"
			@close="closeModal">
			<div class="modal__content">
				<NcTextField
					:value.sync="name_activity"
					:label="t('empleados', 'Activity name')"
					:placeholder="t('empleados', 'e.g. Legal consulting')"
					required />

				<NcTextArea
					:value.sync="description_activity"
					:label="t('empleados', 'Description')"
					:placeholder="t('empleados', 'Optional description...')"
					resize="vertical" />

				<div class="time-row">
					<div class="time-type">
						<span class="field-label">{{ t('empleados', 'Unit') }}</span>
						<div class="radios">
							<NcCheckboxRadioSwitch
								v-model="type_time"
								:button-variant="true"
								value="minutos"
								:name="t('empleados', 'Minutes')"
								type="radio"
								button-variant-grouped="horizontal">
								{{ t('empleados', 'Min') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch
								v-model="type_time"
								:button-variant="true"
								value="horas"
								:name="t('empleados', 'Hours')"
								type="radio"
								button-variant-grouped="horizontal">
								{{ t('empleados', 'Hrs') }}
							</NcCheckboxRadioSwitch>
						</div>
					</div>

					<div class="time-input">
						<NcTextField
							:value.sync="time_activity"
							:label="t('empleados', 'Estimated time')"
							type="number"
							:placeholder="'0'" />
					</div>
				</div>
				<div class="activity-type-section">
					<span class="field-label">{{ t('empleados', 'Activity type') }}</span>
					<div class="radios">
						<NcCheckboxRadioSwitch v-model="activity_type" value="cliente" type="radio">
							{{ t('empleados', 'Client activity') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="activity_type" value="interno" type="radio">
							{{ t('empleados', 'Internal activity') }}
						</NcCheckboxRadioSwitch>
					</div>
				</div>

				<div v-if="activity_type === 'interno'" class="scope-section">
					<span class="field-label">{{ t('empleados', 'Available to') }}</span>
					<div class="radios">
						<NcCheckboxRadioSwitch v-model="activity_scope" value="global" type="radio">
							{{ t('empleados', 'Entire company') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="activity_scope" value="areas" type="radio">
							{{ t('empleados', 'Specific areas') }}
						</NcCheckboxRadioSwitch>
					</div>
					<NcSelect v-if="activity_scope === 'areas'"
						v-model="selected_areas"
						:options="area_options"
						:multiple="true"
						:input-label="t('empleados', 'Select areas')" />
					<p class="cargable-hint">
						{{ t('empleados', 'Internal activities are non-billable') }}
					</p>
				</div>

				<div class="cargable-row">
					<NcCheckboxRadioSwitch
						v-model="cargable_activity"
						:disabled="activity_type === 'interno'"
						:checked.sync="cargable_activity"
						type="checkbox">
						{{ t('empleados', 'Billable activity') }}
					</NcCheckboxRadioSwitch>
					<span class="cargable-hint">{{ t('empleados', 'Mark if this activity can be billed to a client') }}</span>
				</div>

				<div class="modal-actions">
					<NcButton
						type="tertiary"
						@click="closeModal">
						{{ t('empleados', 'Cancel') }}
					</NcButton>
					<NcButton
						v-if="editing"
						type="primary"
						:aria-label="t('empleados', 'Save changes')"
						@click="modify()">
						{{ t('empleados', 'Save changes') }}
					</NcButton>
					<NcButton
						v-else
						type="primary"
						:aria-label="t('empleados', 'Create activity')"
						@click="create()">
						{{ t('empleados', 'Create activity') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<input
			v-if="canAdminCustomers"
			ref="file"
			type="file"
			class="file-input"
			accept=".xlsx"
			@change="importar()">
	</NcAppContent>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import List from '../Helpers/Lists/List.vue'
import ActividadesDetalles from './ActividadesDetalles.vue'
import permissionsMixin from '../../../mixins/permissions.js'
import FilterVariant from 'vue-material-design-icons/FilterVariant.vue'

import {
	NcAppContent,
	NcModal,
	NcTextField,
	NcButton,
	NcTextArea,
	NcCheckboxRadioSwitch,
	NcSelect,
} from '@nextcloud/vue'

export default {
	name: 'Actividades',
	components: {
		NcAppContent,
		List,
		NcModal,
		NcTextField,
		FilterVariant,
		NcButton,
		NcTextArea,
		NcCheckboxRadioSwitch,
		NcSelect,
		ActividadesDetalles,
	},
	mixins: [permissionsMixin],
	data() {
		return {
			editing: false,
			loading: true,
			listas: [],
			select: [],
			modal: false,
			name_activity: '',
			description_activity: '',
			type_time: 'minutos',
			time_activity: 0,
			cargable_activity: false,
			activity_type: 'cliente',
			activity_scope: 'global',
			area_options: [],
			selected_areas: [],
			sortOrder: 'az',
			onlyBillable: false,
			showFilters: false,
		}
	},

	computed: {
		canAdminCustomers() {
			return this.canSee('clientes.admin')
		},

		activeFilterCount() {
			return (this.onlyBillable ? 1 : 0)
		},

		filteredListas() {
			let data = [...this.listas]

			if (this.onlyBillable) {
				data = data.filter(item => Number(item.cargable || 0) === 1)
			}

			data.sort((a, b) => {
				const nameA = (a.name || a.nombre || '').toLowerCase()
				const nameB = (b.name || b.nombre || '').toLowerCase()
				return this.sortOrder === 'za'
					? nameB.localeCompare(nameA)
					: nameA.localeCompare(nameB)
			})

			return data
		},
	},
	watch: {
		activity_type(value) {
			if (value === 'interno') this.cargable_activity = false
			else {
				this.activity_scope = 'global'
				this.selected_areas = []
			}
		},
	},

	async mounted() {
		this._onDetails = (id) => this.GetActividad(id)
		this._onNew = () => this.openModal()
		this._onDelete = () => this.delete()
		this._onEdit = () => this.edit()
		this._onExport = () => this.Exportar()
		this._onImport = () => this.$refs.file.click()
		window.addEventListener('keydown', this.onKeyDown)

		this.$root.$on('details', this._onDetails)
		if (this.canAdminCustomers) {
			this.$root.$on('new', this._onNew)
			this.$root.$on('delete', this._onDelete)
			this.$root.$on('edit', this._onEdit)
			this.$root.$on('exportlist', this._onExport)
			this.$root.$on('importlist', this._onImport)
		}
		this.GetActividades()
		this.GetAreasList()
		this._onClickOutside = (e) => {
			const wrap = this.$el.querySelector('.filter-wrap')
			if (wrap && !wrap.contains(e.target)) {
				this.showFilters = false
			}
		}
		document.addEventListener('click', this._onClickOutside)
	},

	beforeUnmount() {
		window.removeEventListener('keydown', this.onKeyDown)
	},

	beforeDestroy() {
		this.$root.$off('details', this._onDetails)
		this.$root.$off('new', this._onNew)
		this.$root.$off('delete', this._onDelete)
		this.$root.$off('edit', this._onEdit)
		this.$root.$off('exportlist', this._onExport)
		this.$root.$off('importlist', this._onImport)
		document.removeEventListener('click', this._onClickOutside)
	},

	methods: {
		t,

		onKeyDown(e) {
			if (e.key === 'Escape') this.onEsc()
		},

		toggleFilters() {
			this.showFilters = !this.showFilters
		},

		onEsc() {
			this.select = []
		},

		openModal() {
			this.editing = false
			this.name_activity = ''
			this.description_activity = ''
			this.type_time = 'minutos'
			this.time_activity = 0
			this.cargable_activity = false
			this.activity_type = 'cliente'
			this.activity_scope = 'global'
			this.selected_areas = []
			this.modal = true
		},

		closeModal() {
			this.modal = false
		},

		async GetActividad(id) {
			try {
				const response = await axios.post(generateUrl('/apps/empleados/GetActividad'), { id })
				this.select = response?.data?.ocs?.data
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		async GetActividades() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetActividades'))
				if (response?.data?.ocs?.meta?.status !== 'ok') {
					showError(response?.data?.ocs?.meta?.message)
					this.loading = false
					window.location.href = '/apps/empleados/#/'
					return
				}
				const keyMap = {
					id_actividad: 'id',
					nombre: 'name',
					tiempo_real: 'count',
				}
				const renameKeys = (obj, map) =>
					Object.fromEntries(Object.entries(obj).map(([k, v]) => [map[k] ?? k, v]))
				const arr = Array.isArray(response?.data?.ocs?.data) ? response.data.ocs.data : []
				this.listas = arr.map((row) => {
					const item = renameKeys(row, keyMap)
					const typeLabel = item.clave_sistema
						? t('empleados', 'System activity')
						: item.tipo_actividad === 'interno'
							? t('empleados', 'Internal activity')
							: t('empleados', 'Client activity')
					const scope = item.tipo_actividad === 'interno'
						? (item.alcance === 'areas'
							? (item.areas || []).map(area => area.nombre).filter(Boolean).join(', ')
							: t('empleados', 'Entire company'))
						: ''
					return { ...item, subname: scope ? `${typeLabel} · ${scope}` : typeLabel }
				})
				this.loading = false
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		async GetAreasList() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetAreasList'))
				const rows = response?.data?.ocs?.data || []
				this.area_options = rows.map(area => ({
					id: Number(area.Id_departamento ?? area.id_departamento),
					label: area.Nombre ?? area.nombre,
				}))
			} catch (err) {
				showError(t('empleados', 'Unable to load areas: {error}', { error: String(err) }))
			}
		},

		async create() {
			try {
				await axios.post(generateUrl('/apps/empleados/crearActividad'), {
					nombre: this.name_activity,
					detalles: this.description_activity,
					tiempoestimado: this.time_activity != null ? Number(this.time_activity) : 0,
					tipo: this.type_time,
					cargable: this.cargable_activity,
					tipo_actividad: this.activity_type,
					alcance: this.activity_scope,
					area_ids: this.selected_areas.map(area => Number(area.id)),
				})
				showSuccess(t('empleados', 'Actividad creada exitosamente'))
				this.GetActividades()
				this.closeModal()
			} catch (err) {
				showError(this.backendError(err))
			}
		},

		async delete() {
			try {
				if (this.select[0]?.clave_sistema || Number(this.select[0]?.id_actividad) === 99999) {
					showError(t('empleados', 'System activities cannot be deleted'))
					return
				}
				await axios.post(generateUrl('/apps/empleados/DeleteActividad'), {
					id: this.select[0].id_actividad,
				})
				showSuccess(t('empleados', 'Actividad eliminada exitosamente'))
				this.GetActividades()
				this.closeModal()
				this.select = []
			} catch (err) {
				showError(this.backendError(err))
			}
		},

		async modify() {
			try {
				await axios.post(generateUrl('/apps/empleados/ModificarActividad'), {
					id_actividad: this.select[0].id_actividad,
					nombre: this.name_activity,
					detalles: this.description_activity,
					tiempoestimado: Number(this.time_activity),
					tipo: this.type_time,
					cargable: this.cargable_activity,
					tipo_actividad: this.activity_type,
					alcance: this.activity_scope,
					area_ids: this.selected_areas.map(area => Number(area.id)),
				})
				showSuccess(t('empleados', 'Modificación exitosa'))
				// Actualizar select con valores frescos (en minutos, ya convertidos)
				const minutos = this.type_time === 'horas'
					? Number(this.time_activity) * 60
					: Number(this.time_activity)
				this.select = [{
					...this.select[0],
					nombre: this.name_activity,
					detalles: this.description_activity,
					tiempo_estimado: minutos,
					cargable: this.cargable_activity,
					tipo_actividad: this.activity_type,
					alcance: this.activity_scope,
					area_ids: this.selected_areas.map(area => Number(area.id)),
					areas: this.selected_areas.map(area => ({ id_departamento: Number(area.id), nombre: area.label })),
				}]
				this.GetActividades()
				this.closeModal()
			} catch (err) {
				showError(this.backendError(err))
			}
		},

		edit() {
			this.editing = true
			this.name_activity = this.select[0].nombre
			this.description_activity = this.select[0].detalles
			this.type_time = 'minutos'
			this.time_activity = this.select[0].tiempo_estimado
			this.cargable_activity = Boolean(this.select[0].cargable)
			this.activity_type = this.select[0].tipo_actividad || 'cliente'
			this.activity_scope = this.select[0].alcance || 'global'
			const selectedIds = new Set((this.select[0].area_ids || []).map(Number))
			this.selected_areas = this.area_options.filter(area => selectedIds.has(Number(area.id)))
			this.modal = true
		},

		async importar() {
			const formData = new FormData()
			formData.append('ActividadesfileXLSX', this.$refs.file.files[0])
			try {
				await axios.post(generateUrl('/apps/empleados/ImportarActividades'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				})
				this.GetActividades()
				showSuccess(t('empleados', 'Base de datos actualizada exitosamente'))
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},

		Exportar() {
			axios.get(generateUrl('/apps/empleados/ExportarActividades'), { responseType: 'blob' })
				.then((response) => {
					const url = URL.createObjectURL(new Blob([response.data], {
						type: 'application/vnd.ms-excel',
					}))
					const link = document.createElement('a')
					link.href = url
					link.setAttribute('download', 'actividades.xlsx')
					document.body.appendChild(link)
					link.click()
				})
				.catch((err) => {
					showError(t('empleados', 'Error al exportar: {error}', { error: String(err) }))
				})
		},
		backendError(error) {
			return error?.response?.data?.ocs?.data?.message
				|| error?.response?.data?.message
				|| error?.message
				|| String(error)
		},
	},
}
</script>

<style scoped lang="scss">
/* ── Modal content ── */
.modal__content {
	display: flex;
	flex-direction: column;
	gap: 12px;
	width: 100%;
	max-width: 560px;
	margin: 0 auto;
	padding: 24px 28px 28px;
	box-sizing: border-box;
}

/* ── Fila tiempo: unidad + valor ── */
.time-row {
	display: flex;
	align-items: flex-end;
	gap: 10px;
}

.time-type {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.field-label {
	display: block;
	padding-inline-start: 2px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 600;
	letter-spacing: .03em;
	text-transform: uppercase;
}

.radios {
	display: flex;
}

.time-input {
	flex: 1;
	min-width: 0;
}

/* ── Cargable ── */
.cargable-row {
	display: flex;
	flex-direction: column;
	gap: 2px;
	padding: 10px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.cargable-hint {
	padding-inline-start: 30px; /* alinea bajo el label del checkbox */
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.4;
}

/* ── Botones ── */
.modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 4px;
}

/* ── Input file oculto ── */
.file-input {
	display: none;
}

.filter-wrap {
	position: relative;
	display: flex;
	align-items: center;
	gap: 8px;
	z-index: 999999;
}

.filter-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: var(--color-primary-element);
  color: var(--color-primary-element-text);
  font-size: 0.7rem;
  font-weight: 700;
  margin-left: 4px;
}

.filter-dropdown {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  z-index: 9999;
  width: 190px;
  box-sizing: border-box;
  padding: 6px 0;
  border: 1px solid rgba(0, 0, 0, 0.28);
  border-radius: var(--border-radius);
  background: var(--color-main-background);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}

.filter-section {
  display: flex;
  flex-direction: column;
  gap: 2px;
  box-sizing: border-box;
  width: 100%;
  padding: 3px 10px;
}

.filter-section-label {
  margin: 0 0 4px;
  color: var(--color-text-maxcontrast);
  font-size: 10px;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.filter-section label {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  min-height: 26px;
  color: var(--color-text-maxcontrast);
  font-size: 12px;
  line-height: 1;
}

.filter-section input[type='checkbox'] {
  width: 13px;
  height: 13px;
  margin: 0;
}

.filter-divider {
  margin: 1px 0;
  border: none;
  border-top: 1px solid var(--color-border);
}

.filter-section select {
  width: 100%;
  height: 28px;
  box-sizing: border-box;
  padding: 1px 22px 1px 7px;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  background-color: var(--color-main-background);
  color: var(--color-main-text);
  font-size: 12px;
}

.filter-icon-button {
	min-width: unset !important;
	padding-left: 4px !important;
	padding-right: 4px !important;
}
</style>
