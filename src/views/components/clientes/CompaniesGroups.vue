<template id="content">
	<NcAppContent name="Empleados – Companies & Groups">
		<List
			:loading="loading"
			:listas="listas"
			:select="select">
			<template #buttons />
			<template #details>
				<div class="client-details">
					<div class="details-header">
						<div class="details-icon">
							<HexagonMultipleOutline :size="30" />
						</div>
						<div>
							<p class="eyebrow">
								{{ t('empleados', 'Companie or group') }}
							</p>
							<h2>{{ selectedClient.nombre || t('empleados', 'Without name') }}</h2>
						</div>
					</div>

					<div class="details-grid">
						<div class="detail-field detail-field-wide">
							<span>{{ t('empleados', 'Description') }}</span>
							<p>{{ selectedClient.detalles || t('empleados', 'No description available.') }}</p>
						</div>
						<div class="detail-field">
							<span>{{ t('empleados', 'Parent group') }}</span>
							<strong>{{ parentName }}</strong>
						</div>
						<div class="detail-field">
							<span>{{ t('empleados', 'Subgroups') }}</span>
							<strong>{{ selectedClient.child_count || 0 }}</strong>
						</div>
					</div>
				</div>
				<!--CompaniesGroupsDetalles :select="select" /-->
			</template>
		</List>

		<NcModal
			v-if="modal"
			ref="modalRef"
			:name="t('empleados', 'Add new companie or group')"
			@close="closeModal">
			<div class="modal__content">
				<div class="form-group center">
					<NcTextField
						required
						class="form-control"
						:value.sync="name_cliente"
						:label="t('empleados', 'Companie or group name')" />
					<NcTextArea
						required
						class="form-control"
						resize="vertical"
						:value.sync="description_client"
						:label="t('empleados', 'Description companie or group')" />
					<NcSelect
						v-model="padre"
						class="form-control"
						:input-label="t('empleados', 'part of group')"
						:options="options" />
					<div class="save">
						<NcButton
							v-if="editing"
							class="center"
							:aria-label="t('empleados', 'Edit companie or group')"
							type="primary"
							@click="modify()">
							{{ t('empleados', 'Edit') }}
						</NcButton>
						<NcButton
							v-else
							class="center"
							:aria-label="t('empleados', 'Create Activity')"
							type="primary"
							@click="create()">
							{{ t('empleados', 'Create') }}
						</NcButton>
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
import HexagonMultipleOutline from 'vue-material-design-icons/HexagonMultipleOutline.vue'

import {
	NcAppContent,
	NcModal,
	NcTextField,
	NcButton,
	NcTextArea,
	NcSelect,
	// CompaniesGroupsDetalles,
} from '@nextcloud/vue'

export default {
	name: 'CompaniesGroups',
	components: {
		NcAppContent,
		List,
		HexagonMultipleOutline,
		NcModal,
		NcTextField,
		NcButton,
		NcTextArea,
		NcSelect,
		// CompaniesGroupsDetalles,
	},
	data() {
		return {
			editing: false,
			loading: true,
			listas: [],
			select: [],
			options: [],
			modal: false,
			name_cliente: '',
			description_client: '',
			padre: [],
		}
	},
	computed: {
		selectedClient() {
			return this.select?.[0] || {}
		},

		parentName() {
			const parentId = this.selectedClient?.cliente_padre

			if (!parentId || Number(parentId) === 0) {
				return this.t('empleados', 'Main group')
			}

			return this.options.find(option => Number(option.id) === Number(parentId))?.label
				|| this.t('empleados', 'Not found')
		},
	},
	mounted() {
		this._onDetails = (id) => this.GetCompanieGroup(id)
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

		this.GetCompaniesGroups()
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
			this.name_cliente = null
			this.description_client = null
			this.padre = null
			this.modal = true
		},

		closeModal() {
			this.modal = false
		},

		async GetCompanieGroup(id) {
			try {
				await axios.post(generateUrl('/apps/empleados/GetCompanieGroup'), {
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

							this.listas = arr.map(o => renameKeys(o, keyMap))

							const data = Array.isArray(response?.data?.ocs?.data) ? response.data.ocs.data : []

							// Lista para tu <List>
							this.listas = data.map(o => ({
								id: o.id_cliente,
								name: o.nombre,
								count: o.child_count,
							}))

							// Opciones para <NcSelect>
							this.options = data.map(o => ({
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
			try {
				await axios.post(generateUrl('/apps/empleados/crearCliente'), {
					nombre: this.name_cliente,
					detalles: this.description_client,
					cliente_padre: (this.padre && this.padre.id != null) ? Number(this.padre.id) : 0,
				}).then(
					() => {
						showSuccess(t('empleados', 'Área creada exitosamente'))
						this.GetCompaniesGroups()
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
				await axios.post(generateUrl('/apps/empleados/deleteCliente'), {
					id: this.select[0].id_cliente,
				}).then(
					() => {
						showSuccess(t('empleados', 'Se ha eliminadon exitosamente'))
						this.GetCompaniesGroups()
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
				await axios.post(generateUrl('/apps/empleados/modificarCliente'), {
					id_clientes: this.select[0].id_cliente,
					nombre: this.name_cliente,
					detalles: this.description_client,
					cliente_padre: this.padre.id,
				}).then(
					() => {
						showSuccess(t('empleados', 'Modificacion exitosa'))
						const id = this.select?.[0]?.id_cliente ?? null
						this.select = [{
							id_cliente: id,
							nombre: this.name_cliente,
							detalles: this.description_client,
							tipo: 'minutos',
						}]
						this.GetCompaniesGroups()
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
			this.name_cliente = this.select[0].nombre
			this.description_client = this.select[0].detalles
			const pid = this.select?.[0]?.cliente_padre ?? null
			this.padre = (pid == null)
				? null
				: this.options.find(o => Number(o.id) === Number(pid)) || null
			this.modal = true
		},

		async importar() {
			const formData = new FormData()
			formData.append('clientesfileXLSX', this.$refs.file.files[0])
			try {
				await axios.post(generateUrl('/apps/empleados/importarClientes'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				}).then(
					() => {
						this.GetCompaniesGroups()
						showSuccess(t('empleados', 'Se actualizó la base de datos exitosamente'))
					},
					(err) => { showError(err) },
				)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},

		Exportar() {
			axios.get(generateUrl('/apps/empleados/Exportarclientes'), { responseType: 'blob' })
				.then(
					(response) => {
						const url = URL.createObjectURL(new Blob([response.data], {
							type: 'application/vnd.ms-excel',
						}))
						const link = document.createElement('a')
						link.href = url
						link.setAttribute('download', 'clientes.xlsx')
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
.client-details {
	max-width: 900px;
	margin: 28px auto 0;
	padding: 22px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
}

.details-header {
	display: flex;
	align-items: center;
	gap: 14px;
	margin-bottom: 18px;
}

.details-icon {
	display: inline-flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	width: 54px;
	height: 54px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-primary-element);
}

.eyebrow {
	margin: 0 0 4px;
	color: var(--color-primary-element);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.details-header h2 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 24px;
	font-weight: 700;
	line-height: 1.2;
}

.details-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
}

.detail-field {
	min-width: 0;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.detail-field-wide {
	grid-column: 1 / -1;
}

.detail-field span {
	display: block;
	margin-bottom: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.detail-field strong,
.detail-field p {
	margin: 0;
	color: var(--color-main-text);
	font-size: 14px;
	line-height: 1.5;
	overflow-wrap: anywhere;
}

.modal__content {
	width: min(560px, calc(100vw - 48px));
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

.save {
	display: flex;
	justify-content: center;
	margin-top: 4px;
}

.file-input {
	display: none;
}

@media (max-width: 768px) {
	.client-details {
		margin-top: 18px;
		padding: 14px;
	}

	.details-grid {
		grid-template-columns: 1fr;
	}

	.detail-field-wide {
		grid-column: auto;
	}

	.details-header h2 {
		font-size: 20px;
	}
}
</style>
