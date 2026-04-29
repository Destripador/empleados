<template id="content">
	<NcAppContent name="Empleados – Actividades">
		<List
			:loading="loading"
			:listas="listas"
			:select="select"
			:show-options="true">
			<template #buttons />
			<template #details>
				<ActividadesDetalles :select="select" />
			</template>
		</List>

		<NcModal
			v-if="modal"
			ref="modalRef"
			:name="t('empleados', 'Add new activity')"
			@close="closeModal">
			<div class="modal__content">
				<div class="form-group center">
					<NcTextField
						required
						class="form-control"
						:value.sync="name_activity"
						:label="t('empleados', 'Activity name')" />
					<NcTextArea
						required
						class="form-control"
						resize="vertical"
						:value.sync="description_activity"
						:label="t('empleados', 'Description activity')" />
					<div class="time-selector">
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
						<div class="estimatetime">
							<NcTextField
								required
								class="form-control"
								:value.sync="time_activity"
								type="number"
								:label="t('empleados', 'Estimate time')" />
						</div>
						<div class="save">
							<NcButton
								v-if="editing"
								class="center"
								:aria-label="t('empleados', 'Edit Activity')"
								type="primary"
								@click="modify()">
								{{ t('empleados', 'Edit Activity') }}
							</NcButton>
							<NcButton
								v-else
								class="center"
								:aria-label="t('empleados', 'Create Activity')"
								type="primary"
								@click="create()">
								{{ t('empleados', 'Create Activity') }}
							</NcButton>
						</div>
					</div>
				</div>
			</div>
		</NcModal>
		<input
			ref="file"
			type="file"
			class="file-input"
			accept=".xlsx"
			@change="importar()">
	</NcAppContent>
</template>

<script>
// public imports
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import List from '../Helpers/Lists/List.vue'
import ActividadesDetalles from './ActividadesDetalles.vue'

import {
	NcAppContent,
	NcModal,
	NcTextField,
	NcButton,
	NcTextArea,
	NcCheckboxRadioSwitch,
} from '@nextcloud/vue'

export default {
	name: 'Actividades',
	components: {
		NcAppContent,
		List,
		NcModal,
		NcTextField,
		NcButton,
		NcTextArea,
		NcCheckboxRadioSwitch,
		ActividadesDetalles,
	},
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
		}
	},

	async mounted() {
		this._onDetails = (id) => this.GetActividad(id)
		this._onNew = () => this.openModal()
		this._onDelete = () => this.delete()
		this._onEdit = () => this.edit()
		this._onExport = () => this.Exportar()
		this._onImport = () => this.$refs.file.click()
		// deteccion de esc
		window.addEventListener('keydown', this.onKeyDown)

		this.$root.$on('details', this._onDetails)
		this.$root.$on('new', this._onNew)
		this.$root.$on('delete', this._onDelete)
		this.$root.$on('edit', this._onEdit)
		this.$root.$on('exportlist', this._onExport)
		this.$root.$on('importlist', this._onImport)
		this.GetActividades()
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
	},

	methods: {
		t,

		 onKeyDown(e) {
			if (e.key === 'Escape') this.onEsc()
		},

		onEsc() {
			this.select = []
		},

		openModal() {
			this.editing = false
			this.name_activity = null
			this.description_activity = null
			this.type_time = 'minutos'
			this.time_activity = null

			this.modal = true
		},

		closeModal() {
			this.modal = false
		},

		async GetActividad(id) {
			try {
				await axios.post(generateUrl('/apps/empleados/GetActividad'), {
					id,
				}).then(
					(response) => {
						this.select = response?.data?.ocs?.data
					},
					(err) => { showError(err) },
				)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
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
								nombre: 'name',
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

		async create() {
			try {
				await axios.post(generateUrl('/apps/empleados/crearActividad'), {
					nombre: this.name_activity,
					detalles: this.description_activity,
					tiempoestimado: this.time_activity != null ? this.time_activity : 0,
					tipo: this.type_time,
				}).then(
					() => {
						showSuccess(t('empleados', 'Área creada exitosamente'))
						this.GetActividades()
						this.closeModal()
					},
					(err) => { showError(err) },
				)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},

		async delete() {
			try {
				await axios.post(generateUrl('/apps/empleados/DeleteActividad'), {
					id: this.select[0].id_actividad,
				}).then(
					() => {
						showSuccess(t('empleados', 'Se ha eliminadon exitosamente'))
						this.GetActividades()
						this.closeModal()
						this.select = []
					},
					(err) => { showError(err) },
				)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},

		async modify() {
			try {
				await axios.post(generateUrl('/apps/empleados/ModificarActividad'), {
					id_actividad: this.select[0].id_actividad,
					nombre: this.name_activity,
					detalles: this.description_activity,
					tiempoestimado: this.time_activity,
					tipo: this.type_time,
				}).then(
					() => {
						showSuccess(t('empleados', 'Modificacion exitosa'))
						const id = this.select?.[0]?.id_actividad ?? null
						this.select = [{
							id_actividad: id,
							nombre: this.name_activity,
							detalles: this.description_activity,
							tiempo_estimado: this.time_activity,
							tipo: 'minutos',
						}]
						this.GetActividades()
						this.closeModal()
					},
					(err) => { showError(err) },
				)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},

		edit() {
			this.editing = true
			this.name_activity = this.select[0].nombre
			this.description_activity = this.select[0].detalles
			this.type_time = 'minutos'
			this.time_activity = this.select[0].tiempo_estimado
			this.modal = true
		},

		async importar() {
			const formData = new FormData()
			formData.append('ActividadesfileXLSX', this.$refs.file.files[0])
			try {
				await axios.post(generateUrl('/apps/empleados/ImportarActividades'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				}).then(
					() => {
						this.GetActividades()
						showSuccess(t('empleados', 'Se actualizó la base de datos exitosamente'))
					},
					(err) => { showError(err) },
				)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},

		Exportar() {
			axios.get(generateUrl('/apps/empleados/ExportarActividades'), { responseType: 'blob' })
				.then(
					(response) => {
						const url = URL.createObjectURL(new Blob([response.data], {
							type: 'application/vnd.ms-excel',
						}))
						const link = document.createElement('a')
						link.href = url
						link.setAttribute('download', 'actividades.xlsx')
						document.body.appendChild(link)
						link.click()
					},
					(err) => {
						showError(t('empleados', 'Se ha producido un error {error}, reporte al administrador', { error: String(err) }))
					},
				)
		},
	},
}
</script>

<style scoped lang="scss">
.modal__content {
	width: min(620px, calc(100vw - 48px));
	padding: 24px;
}

.form-group {
	display: flex;
	flex-direction: column;
	gap: 14px;
	margin: 0;
}

.form-control {
	width: 100%;
}

.time-selector {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 14px;
	margin: 4px 0 0;
}

.radios {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.estimatetime {
	display: flex;
	flex: 1 1 180px;
	min-width: 180px;
}

.save {
	display: flex;
	justify-content: center;
	width: 100%;
	margin-top: 4px;
}

.file-input {
	display: none;
}

@media (max-width: 768px) {
	.modal__content {
		padding: 18px;
	}

	.time-selector {
		align-items: stretch;
	}

	.estimatetime {
		flex-basis: 100%;
	}
}
</style>
