<!-- eslint-disable vue/require-v-for-key -->
<template>
	<div>
		<div class="container">
			<div class="flex">
				<!-- Anniversaries table -->
				<div class="mitad">
					<div>
						<div class="table_component"
							role="region"
							tabindex="0"
							style="max-height:  calc(90vh - 4rem); overflow-y: auto;">
							<table>
								<caption>
									<span class="caption-title">{{ t('empleados', 'Anniversaries table') }}</span>
									<span class="caption-buttons">
										<NcActions>
											<NcActionButton :close-after-click="true" @click="showAddAniversario">
												<template #icon><Plus :size="20" /></template>
												{{ t('empleados', 'Create new anniversary') }}
											</NcActionButton>
											<NcActionButton :close-after-click="true" @click="$refs.file.click()">
												<template #icon><Import :size="20" /></template>
												{{ t('empleados', 'Import list') }}
											</NcActionButton>
											<NcActionButton :close-after-click="true" @click="Exportar()">
												<template #icon><Export :size="20" /></template>
												{{ t('empleados', 'Export list / template') }}
											</NcActionButton>
											<NcActionButton :close-after-click="true" @click="vaciar()">
												<template #icon><Delete :size="20" /></template>
												{{ t('empleados', 'Empty anniversaries table') }}
											</NcActionButton>
										</NcActions>
									</span>
								</caption>
								<thead>
									<tr>
										<th>{{ t('empleados', 'Anniversary number') }}</th>
										<th>{{ t('empleados', 'Days off') }}</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="(item) in Aniversarios" v-bind="$attrs">
										<td>{{ item.numero_aniversario }}</td>
										<td>{{ item.dias }}</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- Absence types table -->
				<div class="mitad">
					<div>
						<div
							class="table_component"
							style="max-height:  calc(80vh - 4rem); overflow-y: auto;"
							role="region"
							tabindex="0">
							<table>
								<caption>
									<span class="caption-title">{{ t('empleados', 'Absence types') }}</span>
									<span class="caption-buttons">
										<NcActions>
											<NcActionButton :close-after-click="true" @click="showAddTipo">
												<template #icon><Plus :size="20" /></template>
												{{ t('empleados', 'Create new absence type') }}
											</NcActionButton>
											<NcActionButton :close-after-click="true" @click="$refs.fileTipo.click()">
												<template #icon><Import :size="20" /></template>
												{{ t('empleados', 'Import list') }}
											</NcActionButton>
											<NcActionButton :close-after-click="true" @click="ExportarTipo()">
												<template #icon><Export :size="20" /></template>
												{{ t('empleados', 'Export list / template') }}
											</NcActionButton>
											<NcActionButton :close-after-click="true" @click="vaciarTipo()">
												<template #icon><Delete :size="20" /></template>
												{{ t('empleados', 'Empty absence types table') }}
											</NcActionButton>
										</NcActions>
									</span>
								</caption>
								<thead>
									<tr>
										<th>{{ t('empleados', 'Name') }}</th>
										<th>{{ t('empleados', 'Description') }}</th>
										<th>{{ t('empleados', 'Request file') }}</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="(item) in TipoAusencias" v-bind="$attrs">
										<td>{{ item.nombre }}</td>
										<td>{{ item.descripcion }}</td>
										<td>{{ item.solicitar_archivo == 1 ? t('empleados', 'yes') : t('empleados', 'no') }}</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal: Add anniversary -->
		<NcModal
			v-if="modalAddAniversario"
			ref="modalRef"
			name="add"
			@close="closeModalAniversario">
			<div class="modal__content">
				<h2>{{ t('empleados', 'Add new anniversary information') }}</h2>
				<div class="form-group">
					<NcTextField :label="t('empleados', 'Anniversary number')" :value.sync="NumeroAniversario" />
				</div>
				<div class="form-group">
					<NcTextField :label="t('empleados', 'Days')" :value.sync="DiasAniversario" />
				</div>

				<NcButton :disabled="!NumeroAniversario || !DiasAniversario" @click="AgregarNuevoAniversario">
					{{ t('empleados', 'Submit') }}
				</NcButton>
			</div>
		</NcModal>

		<!-- Modal: Add absence type -->
		<NcModal
			v-if="modalAddTipo"
			ref="modalRef"
			name="add"
			@close="closeModalTipo">
			<div class="modal__content">
				<h2>{{ t('empleados', 'Add new absence type information') }}</h2>
				<div class="form-group">
					<NcTextField :label="t('empleados', 'Name')" :value.sync="NombreTipo" />
				</div>
				<div class="form-group">
					<NcTextField :label="t('empleados', 'Description')" :value.sync="DescripcionTipo" />
				</div>
				<div class="form-group">
					<NcCheckboxRadioSwitch v-model="SolicitarArchivoTipo">
						{{ t('empleados', 'Request file') }}
					</NcCheckboxRadioSwitch>
				</div>
				<div class="form-group">
					<NcCheckboxRadioSwitch v-model="solicitar_prima_vacacional">
						{{ t('empleados', 'Vacation Bonus') }}
					</NcCheckboxRadioSwitch>
				</div>

				<NcButton :disabled="!NombreTipo || !DescripcionTipo" @click="AgregarNuevoTipo">
					{{ t('empleados', 'Submit') }}
				</NcButton>
			</div>
		</NcModal>

		<input ref="file"
			type="file"
			style="display:none"
			accept=".xlsx"
			@change="importar()">
		<input ref="fileTipo"
			type="file"
			style="display:none"
			accept=".xlsx"
			@change="importarTipo()">
	</div>
</template>

<script>
// icons
import Delete from 'vue-material-design-icons/Delete.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Import from 'vue-material-design-icons/Import.vue'
import Export from 'vue-material-design-icons/Export.vue'

// nextcloud/vue
import {
	NcActions,
	NcActionButton,
	NcModal,
	NcTextField,
	NcButton,
	NcCheckboxRadioSwitch,
} from '@nextcloud/vue'
import { ref } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TiempoLaboralSettings',
	components: {
		NcActions,
		NcActionButton,
		NcModal,
		NcTextField,
		NcButton,
		NcCheckboxRadioSwitch,
		Plus,
		Import,
		Export,
		Delete,
	},

	data() {
		return {
			modalAddAniversario: false,
			modalAddTipo: false,
			modalRef: ref(null),

			Aniversarios: [],
			TipoAusencias: [],

			NumeroAniversario: null,
			DiasAniversario: null,

			NombreTipo: null,
			DescripcionTipo: null,
			SolicitarArchivoTipo: false,
			solicitar_prima_vacacional: false,
		}
	},

	mounted() {
		this.getAniversarios()
		this.getTipo()
	},

	methods: {
		// expose t into template
		t,

		showAddAniversario() { this.modalAddAniversario = true },
		showAddTipo() { this.modalAddTipo = true },
		closeModalAniversario() { this.modalAddAniversario = false },
		closeModalTipo() { this.modalAddTipo = false },

		async getAniversarios() {
			this.closeModalAniversario()
			this.DiasAniversario = null
			this.NumeroAniversario = null
			try {
				await axios.get(generateUrl('/apps/empleados/Getaniversarios'))
					.then(
						(response) => { this.Aniversarios = response.data },
						(err) => { showError(err) },
					)
			} catch (err) {
				showError(t('empleados', 'An exception occurred [01] [{error}]', { error: String(err) }))
			}
		},

		async getTipo() {
			this.closeModalTipo()
			this.NombreTipo = null
			this.DescripcionTipo = null
			this.SolicitarArchivoTipo = false
			try {
				await axios.get(generateUrl('/apps/empleados/getTipo'))
					.then(
						(response) => { this.TipoAusencias = response.data },
						(err) => { showError(err) },
					)
			} catch (err) {
				showError(t('empleados', 'An exception occurred [01] [{error}]', { error: String(err) }))
			}
		},

		async AgregarNuevoAniversario() {
			try {
				await axios.post(generateUrl('/apps/empleados/AgregarNuevoAniversario'), {
					numero_aniversario: this.NumeroAniversario,
					dias: this.DiasAniversario,
				})
				showSuccess(t('empleados', 'Note has been updated'))
				this.getAniversarios()
			} catch (err) {
				showError(t('empleados', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async AgregarNuevoTipo() {
			try {
				await axios.post(generateUrl('/apps/empleados/AgregarNuevoTipo'), {
					nombre: this.NombreTipo,
					descripcion: this.DescripcionTipo,
					solicitar_archivo: this.SolicitarArchivoTipo,
					solicitar_prima_vacacional: this.solicitar_prima_vacacional,
				})
				showSuccess(t('empleados', 'Note has been updated'))
				this.getTipo()
			} catch (err) {
				showError(t('empleados', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async vaciar() {
			this.closeModalAniversario()
			try {
				await axios.get(generateUrl('/apps/empleados/VaciarAniversarios'))
					.then(
						() => {
							this.getAniversarios()
							showSuccess(t('empleados', 'Anniversaries table emptied'))
						},
						(err) => { showError(err) },
					)
			} catch (err) {
				showError(t('empleados', 'An exception occurred [01] [{error}]', { error: String(err) }))
			}
		},

		async vaciarTipo() {
			this.closeModalTipo()
			try {
				await axios.get(generateUrl('/apps/empleados/VaciarTipo'))
					.then(
						() => {
							this.getTipo()
							showSuccess(t('empleados', 'Absence types table emptied'))
						},
						(err) => { showError(err) },
					)
			} catch (err) {
				showError(t('empleados', 'An exception occurred [01] [{error}]', { error: String(err) }))
			}
		},

		Exportar() {
			axios.get(generateUrl('/apps/empleados/ExportListAniversarios'), { responseType: 'blob' }).then(
				(response) => {
					const url = URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.ms-excel' }))
					const link = document.createElement('a')
					link.href = url
					link.setAttribute('download', 'aniversarios.xlsx')
					document.body.appendChild(link)
					link.click()
				},
				(err) => {
					showError(t('empleados', 'An error occurred {error}, report to the administrator', { error: String(err) }))
				},
			)
		},

		ExportarTipo() {
			axios.get(generateUrl('/apps/empleados/ExportarTipo'), { responseType: 'blob' }).then(
				(response) => {
					const url = URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.ms-excel' }))
					const link = document.createElement('a')
					link.href = url
					link.setAttribute('download', 'tipos_ausencias.xlsx')
					document.body.appendChild(link)
					link.click()
				},
				(err) => {
					showError(t('empleados', 'An error occurred {error}, report to the administrator', { error: String(err) }))
				},
			)
		},

		async importar() {
			const formData = new FormData()
			formData.append('fileXLSX', this.$refs.file.files[0])
			try {
				await axios.post(generateUrl('/apps/empleados/ImportListAniversarios'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				})
				this.getAniversarios()
				showSuccess(t('empleados', 'Database updated successfully'))
			} catch (err) {
				showError(t('empleados', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async importarTipo() {
			const formData = new FormData()
			formData.append('fileXLSX', this.$refs.fileTipo.files[0])
			try {
				await axios.post(generateUrl('/apps/empleados/importarTipo'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				})
				this.getTipo()
				showSuccess(t('empleados', 'Database updated successfully'))
			} catch (err) {
				showError(t('empleados', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},
	},
}
</script>

<style>
.flex { display: flex; justify-content: space-between; }
.mitad { flex: 1; padding: 20px; border: 1px solid #000; }

.table_component { overflow: auto; width: 100%; }
.table_component table {
	border: 1px solid #dededf;
	height: 100%;
	width: 100%;
	table-layout: fixed;
	border-collapse: collapse;
	border-spacing: 1px;
	text-align: left;
}
.table_component th {
	border: 1px solid #dededf;
	background-color: #eceff1;
	color: #000000;
	padding: 5px;
}
.table_component td {
	border: 1px solid #dededf;
	background-color: #ffffff;
	color: #000000;
	padding: 5px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.caption-title { font-weight: bold; }
.caption-buttons { float: right; padding-bottom: 6px; }

.modal__content { margin: 50px; }
.modal__content h2 { text-align: center; }

.form-group {
	margin: calc(var(--default-grid-baseline) * 4) 0;
	display: flex;
	flex-direction: column;
	align-items: flex-start;
}
</style>
