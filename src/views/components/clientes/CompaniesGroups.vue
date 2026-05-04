<template>
	<NcAppContent :name="t('empleados', 'Companies and groups')">
		<div class="companies-page">
			<div class="companies-header">
				<div class="header-title">
					<p class="section-label">
						{{ t('empleados', 'Customers module') }}
					</p>
					<h2>{{ t('empleados', 'Companies and groups') }}</h2>
					<p class="section-description">
						{{ t('empleados', 'Manage customer groups, companies and sub-companies used by the time reports module.') }}
					</p>
				</div>

				<div class="header-actions">
					<NcButton @click="Exportar">
						{{ t('empleados', 'Export') }}
					</NcButton>

					<NcButton @click="triggerImport">
						{{ t('empleados', 'Import') }}
					</NcButton>

					<NcButton type="primary" @click="openModal">
						{{ t('empleados', 'New company') }}
					</NcButton>
				</div>
			</div>

			<div class="stats-grid">
				<div class="stat-card">
					<div class="stat-icon">
						<OfficeBuilding :size="22" />
					</div>
					<div>
						<span>{{ t('empleados', 'Total records') }}</span>
						<strong>{{ rawClients.length }}</strong>
					</div>
				</div>

				<div class="stat-card">
					<div class="stat-icon">
						<HexagonMultipleOutline :size="22" />
					</div>
					<div>
						<span>{{ t('empleados', 'Main groups') }}</span>
						<strong>{{ mainGroups.length }}</strong>
					</div>
				</div>

				<div class="stat-card">
					<div class="stat-icon">
						<AccountGroup :size="22" />
					</div>
					<div>
						<span>{{ t('empleados', 'Sub-companies') }}</span>
						<strong>{{ subCompanies.length }}</strong>
					</div>
				</div>
			</div>

			<List
				:loading="loading"
				:listas="listas"
				:select="select"
				:show-options="true">
				<template #buttons />

				<template #details>
					<div class="client-details">
						<NcEmptyContent
							v-if="!hasSelectedClient"
							:name="t('empleados', 'No company selected')"
							:description="t('empleados', 'Select a company or group from the list to view its details.')">
							<template #icon>
								<HexagonMultipleOutline />
							</template>
						</NcEmptyContent>

						<template v-else>
							<div class="details-header">
								<div class="details-icon">
									<HexagonMultipleOutline :size="30" />
								</div>

								<div class="details-title">
									<p class="eyebrow">
										{{ selectedClientType }}
									</p>
									<h2>{{ selectedClient.nombre || t('empleados', 'Without name') }}</h2>
									<p>{{ selectedClient.detalles || t('empleados', 'No description available.') }}</p>
								</div>
							</div>

							<div class="details-grid">
								<div class="detail-card">
									<span>{{ t('empleados', 'Parent group') }}</span>
									<strong>{{ parentName }}</strong>
								</div>

								<div class="detail-card">
									<span>{{ t('empleados', 'Sub-companies') }}</span>
									<strong>{{ childCompanies.length }}</strong>
								</div>

								<div class="detail-card detail-card-wide">
									<span>{{ t('empleados', 'Hierarchy') }}</span>
									<div class="breadcrumb">
										<span>{{ parentName }}</span>
										<span class="separator">/</span>
										<strong>{{ selectedClient.nombre }}</strong>
									</div>
								</div>
							</div>

							<div class="children-section">
								<div class="section-head">
									<div>
										<p class="section-label">
											{{ t('empleados', 'Sub-companies') }}
										</p>
										<h3>{{ t('empleados', 'Companies inside this group') }}</h3>
									</div>
								</div>

								<div v-if="childCompanies.length > 0" class="children-grid">
									<button
										v-for="child in childCompanies"
										:key="child.id_cliente"
										type="button"
										class="child-card"
										@click="GetCompanieGroup(child.id_cliente)">
										<div class="child-icon">
											<OfficeBuilding :size="20" />
										</div>

										<div class="child-info">
											<strong>{{ child.nombre }}</strong>
											<span>{{ child.detalles || t('empleados', 'No description available.') }}</span>
										</div>

										<div class="child-count">
											{{ child.child_count || 0 }}
										</div>
									</button>
								</div>

								<NcEmptyContent
									v-else
									:name="t('empleados', 'No sub-companies')"
									:description="t('empleados', 'This company or group does not have registered sub-companies.')">
									<template #icon>
										<OfficeBuilding />
									</template>
								</NcEmptyContent>
							</div>
						</template>
					</div>
				</template>
			</List>
		</div>

		<NcModal
			v-if="modal"
			ref="modalRef"
			:name="modalTitle"
			@close="closeModal">
			<div class="modal-content">
				<div class="modal-header">
					<p class="section-label">
						{{ editing ? t('empleados', 'Edit') : t('empleados', 'Create') }}
					</p>
					<h2>{{ modalTitle }}</h2>
					<p>
						{{ t('empleados', 'Register a main group or link the company to an existing parent group.') }}
					</p>
				</div>

				<div class="form-grid">
					<NcTextField
						required
						class="span-2"
						:value.sync="name_cliente"
						:label="t('empleados', 'Company or group name')" />

					<NcTextArea
						class="span-2"
						resize="vertical"
						:value.sync="description_client"
						:label="t('empleados', 'Description')" />

					<NcSelect
						v-model="padre"
						class="span-2"
						:input-label="t('empleados', 'Parent group')"
						:options="parentOptions"
						:clearable="true" />

					<NcNoteCard
						type="info"
						class="span-2">
						{{ t('empleados', 'Leave parent group empty to create a main group. Select a parent to create a sub-company.') }}
					</NcNoteCard>
				</div>

				<div class="modal-actions">
					<NcButton @click="closeModal">
						{{ t('empleados', 'Cancel') }}
					</NcButton>

					<NcButton
						type="primary"
						:disabled="!isFormValid || saving"
						@click="save">
						{{ saving ? t('empleados', 'Saving...') : saveLabel }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<input
			ref="file"
			type="file"
			class="file-input"
			accept=".xlsx"
			@change="importar">
	</NcAppContent>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import List from '../Helpers/Lists/List.vue'

import HexagonMultipleOutline from 'vue-material-design-icons/HexagonMultipleOutline.vue'
import OfficeBuilding from 'vue-material-design-icons/OfficeBuilding.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'

import {
	NcAppContent,
	NcModal,
	NcTextField,
	NcButton,
	NcTextArea,
	NcSelect,
	NcEmptyContent,
	NcNoteCard,
} from '@nextcloud/vue'

export default {
	name: 'CompaniesGroups',

	components: {
		NcAppContent,
		List,
		HexagonMultipleOutline,
		OfficeBuilding,
		AccountGroup,
		NcModal,
		NcTextField,
		NcButton,
		NcTextArea,
		NcSelect,
		NcEmptyContent,
		NcNoteCard,
	},

	data() {
		return {
			editing: false,
			saving: false,
			loading: true,
			listas: [],
			rawClients: [],
			select: [],
			options: [],
			modal: false,
			name_cliente: '',
			description_client: '',
			padre: null,
		}
	},

	computed: {
		selectedClient() {
			return this.select?.[0] || {}
		},

		hasSelectedClient() {
			return Boolean(this.selectedClient?.id_cliente)
		},

		selectedClientType() {
			return Number(this.selectedClient?.cliente_padre || 0) === 0
				? t('empleados', 'Main group')
				: t('empleados', 'Sub-company')
		},

		parentName() {
			const parentId = this.selectedClient?.cliente_padre

			if (!parentId || Number(parentId) === 0) {
				return t('empleados', 'Main group')
			}

			return this.rawClients.find((client) => Number(client.id_cliente) === Number(parentId))?.nombre
				|| t('empleados', 'Not found')
		},

		childCompanies() {
			if (!this.selectedClient?.id_cliente) {
				return []
			}

			return this.rawClients.filter((client) => {
				return Number(client.cliente_padre || 0) === Number(this.selectedClient.id_cliente)
			})
		},

		mainGroups() {
			return this.rawClients.filter((client) => Number(client.cliente_padre || 0) === 0)
		},

		subCompanies() {
			return this.rawClients.filter((client) => Number(client.cliente_padre || 0) !== 0)
		},

		parentOptions() {
			const currentId = this.selectedClient?.id_cliente

			return this.options.filter((option) => {
				return !currentId || Number(option.id) !== Number(currentId)
			})
		},

		isFormValid() {
			return String(this.name_cliente || '').trim().length > 0
		},

		modalTitle() {
			return this.editing
				? t('empleados', 'Edit company or group')
				: t('empleados', 'New company or group')
		},

		saveLabel() {
			return this.editing
				? t('empleados', 'Save changes')
				: t('empleados', 'Create')
		},
	},

	mounted() {
		this._onDetails = (id) => this.GetCompanieGroup(id)
		this._onNew = () => this.openModal()
		this._onDelete = () => this.delete()
		this._onEdit = () => this.edit()
		this._onExport = () => this.Exportar()
		this._onImport = () => this.triggerImport()

		window.addEventListener('keydown', this.onKeyDown)

		this.$root.$on('details', this._onDetails)
		this.$root.$on('new', this._onNew)
		this.$root.$on('delete', this._onDelete)
		this.$root.$on('edit', this._onEdit)
		this.$root.$on('exportlist', this._onExport)
		this.$root.$on('importlist', this._onImport)

		this.GetCompaniesGroups()
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.onKeyDown)

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
			if (e.key === 'Escape') {
				this.onEsc()
			}
		},

		onEsc() {
			if (this.modal) {
				this.closeModal()
				return
			}

			this.select = []
		},

		openModal() {
			this.editing = false
			this.resetForm()
			this.modal = true
		},

		closeModal() {
			this.modal = false
			this.saving = false
		},

		resetForm() {
			this.name_cliente = ''
			this.description_client = ''
			this.padre = null
		},

		triggerImport() {
			this.$refs.file?.click()
		},

		getOcsData(response) {
			return response?.data?.ocs?.data ?? response?.data ?? null
		},

		async GetCompanieGroup(id) {
			try {
				const response = await axios.post(generateUrl('/apps/empleados/GetCompanieGroup'), {
					id,
				})

				const data = this.getOcsData(response)
				this.select = Array.isArray(data) ? data : []
			} catch (err) {
				showError(t('empleados', 'Error loading company: {error}', { error: String(err) }))
			}
		},

		async GetCompaniesGroups() {
			this.loading = true

			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetCompaniesGroups'))

				if (response?.data?.ocs?.meta?.status !== 'ok') {
					showError(response?.data?.ocs?.meta?.message || t('empleados', 'Could not load companies.'))
					window.location.href = generateUrl('/apps/empleados/#/')
					return
				}

				const data = Array.isArray(response?.data?.ocs?.data)
					? response.data.ocs.data
					: []

				this.rawClients = data

				this.listas = data.map((item) => ({
					id: item.id_cliente,
					name: item.nombre,
					count: item.child_count || 0,
					...item,
				}))

				this.options = data.map((item) => ({
					id: item.id_cliente,
					label: item.nombre,
				}))
			} catch (err) {
				showError(t('empleados', 'Error loading companies: {error}', { error: String(err) }))
				this.rawClients = []
				this.listas = []
				this.options = []
			} finally {
				this.loading = false
			}
		},

		save() {
			if (this.editing) {
				return this.modify()
			}

			return this.create()
		},

		getPayload() {
			return {
				nombre: String(this.name_cliente || '').trim(),
				detalles: String(this.description_client || '').trim(),
				cliente_padre: this.padre?.id != null ? Number(this.padre.id) : 0,
			}
		},

		async create() {
			if (!this.isFormValid) {
				showError(t('empleados', 'Company or group name is required.'))
				return
			}

			this.saving = true

			try {
				await axios.post(generateUrl('/apps/empleados/crearCliente'), this.getPayload())

				showSuccess(t('empleados', 'Company or group created successfully'))
				await this.GetCompaniesGroups()
				this.closeModal()
			} catch (err) {
				showError(t('empleados', 'Error creating company: {error}', { error: String(err) }))
			} finally {
				this.saving = false
			}
		},

		async delete() {
			if (!this.selectedClient?.id_cliente) {
				showError(t('empleados', 'Select a company or group first.'))
				return
			}

			this.loading = true

			try {
				await axios.post(generateUrl('/apps/empleados/deleteCliente'), {
					id: this.selectedClient.id_cliente,
				})

				showSuccess(t('empleados', 'Company or group deleted successfully'))
				this.select = []
				await this.GetCompaniesGroups()
			} catch (err) {
				showError(t('empleados', 'Error deleting company: {error}', { error: String(err) }))
			} finally {
				this.loading = false
			}
		},

		async modify() {
			if (!this.selectedClient?.id_cliente) {
				showError(t('empleados', 'Select a company or group first.'))
				return
			}

			if (!this.isFormValid) {
				showError(t('empleados', 'Company or group name is required.'))
				return
			}

			const payload = this.getPayload()
			this.saving = true

			try {
				await axios.post(generateUrl('/apps/empleados/modificarCliente'), {
					id_clientes: this.selectedClient.id_cliente,
					...payload,
				})

				showSuccess(t('empleados', 'Company or group updated successfully'))

				this.select = [{
					...this.selectedClient,
					nombre: payload.nombre,
					detalles: payload.detalles,
					cliente_padre: payload.cliente_padre,
				}]

				await this.GetCompaniesGroups()
				this.closeModal()
			} catch (err) {
				showError(t('empleados', 'Error updating company: {error}', { error: String(err) }))
			} finally {
				this.saving = false
			}
		},

		edit() {
			if (!this.selectedClient?.id_cliente) {
				showError(t('empleados', 'Select a company or group first.'))
				return
			}

			this.editing = true
			this.name_cliente = this.selectedClient.nombre || ''
			this.description_client = this.selectedClient.detalles || ''

			const parentId = this.selectedClient.cliente_padre || 0

			this.padre = Number(parentId) === 0
				? null
				: this.options.find((option) => Number(option.id) === Number(parentId)) || null

			this.modal = true
		},

		async importar() {
			const file = this.$refs.file?.files?.[0]

			if (!file) {
				return
			}

			const formData = new FormData()
			formData.append('clientesfileXLSX', file)

			this.loading = true

			try {
				await axios.post(generateUrl('/apps/empleados/importarClientes'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				})

				showSuccess(t('empleados', 'Companies imported successfully'))
				await this.GetCompaniesGroups()
			} catch (err) {
				showError(t('empleados', 'Error importing companies: {error}', { error: String(err) }))
			} finally {
				this.loading = false

				if (this.$refs.file) {
					this.$refs.file.value = ''
				}
			}
		},

		async Exportar() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/Exportarclientes'), {
					responseType: 'blob',
				})

				const url = URL.createObjectURL(new Blob([response.data], {
					type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				}))

				const link = document.createElement('a')
				link.href = url
				link.setAttribute('download', 'clientes.xlsx')
				document.body.appendChild(link)
				link.click()
				link.remove()
				URL.revokeObjectURL(url)
			} catch (err) {
				showError(t('empleados', 'Error exporting companies: {error}', { error: String(err) }))
			}
		},
	},
}
</script>

<style scoped lang="scss">
.companies-page {
	display: flex;
	flex-direction: column;
	gap: 16px;
	width: 100%;
	padding: 24px;
}

.companies-header {
	display: flex;
	align-items: flex-end;
	justify-content: space-between;
	gap: 16px;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.header-title {
	min-width: 0;
}

.section-label,
.eyebrow {
	margin: 0 0 4px;
	color: var(--color-primary-element);
	font-size: 12px;
	font-weight: 700;
	letter-spacing: .04em;
	text-transform: uppercase;
}

.companies-header h2,
.modal-header h2,
.section-head h3 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 24px;
	font-weight: 700;
}

.section-description,
.modal-header p,
.details-title p {
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	line-height: 1.4;
}

.header-actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 8px;
}

.stats-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
}

.stat-card {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.stat-icon,
.details-icon,
.child-icon {
	display: inline-flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-primary-element);
}

.stat-icon {
	width: 42px;
	height: 42px;
}

.stat-card span {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
}

.stat-card strong {
	display: block;
	margin-top: 2px;
	color: var(--color-main-text);
	font-size: 22px;
	font-weight: 700;
}

.client-details {
	width: min(960px, 100%);
	box-sizing: border-box;
	margin: 20px auto 0;
	padding: 22px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
	overflow: hidden;
}

.details-header {
	display: flex;
	align-items: flex-start;
	gap: 14px;
	margin-bottom: 18px;
	min-width: 0;
}

.details-icon {
	width: 56px;
	height: 56px;
}

.details-title {
	min-width: 0;
}

.details-title h2 {
	max-width: 100%;
	margin: 0;
	color: var(--color-main-text);
	font-size: 24px;
	font-weight: 700;
	line-height: 1.2;
	overflow-wrap: anywhere;
}

.details-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
	width: 100%;
	min-width: 0;
	box-sizing: border-box;
	margin-bottom: 18px;
}

.detail-card {
	min-width: 0;
	max-width: 100%;
	box-sizing: border-box;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	overflow: hidden;
}

.detail-card-wide {
	grid-column: 1 / -1;
}

.detail-card span {
	display: block;
	margin-bottom: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.detail-card strong,
.detail-card p {
	max-width: 100%;
	margin: 0;
	color: var(--color-main-text);
	font-size: 14px;
	line-height: 1.5;
	overflow-wrap: anywhere;
	word-break: break-word;
	white-space: normal;
}

.breadcrumb {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}

.breadcrumb .separator {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.children-section {
	margin-top: 6px;
}

.section-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 12px;
}

.section-head h3 {
	font-size: 18px;
}

.children-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
	gap: 10px;
}

.child-card {
	display: flex;
	align-items: flex-start;
	gap: 10px;
	min-width: 0;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-main-text);
	text-align: left;
	cursor: pointer;
}

.child-card:hover,
.child-card:focus {
	border-color: var(--color-primary-element-light);
	background: var(--color-main-background);
	outline: none;
}

.child-icon {
	width: 36px;
	height: 36px;
}

.child-info {
	flex: 1 1 auto;
	min-width: 0;
}

.child-info strong {
	display: block;
	overflow: hidden;
	color: var(--color-main-text);
	font-size: 14px;
	font-weight: 700;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.child-info span {
	display: -webkit-box;
	margin-top: 4px;
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.35;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
}

.child-count {
	display: flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	min-width: 28px;
	height: 28px;
	border-radius: 999px;
	background: var(--color-main-background);
	color: var(--color-primary-element);
	font-size: 12px;
	font-weight: 700;
}

.modal-content {
	width: min(620px, calc(100vw - 48px));
	padding: 24px;
}

.modal-header {
	margin-bottom: 18px;
}

.form-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 14px;
}

.span-2 {
	grid-column: 1 / -1;
}

.modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 22px;
}

.file-input {
	display: none;
}

@media (max-width: 768px) {
	.companies-page {
		padding: 14px;
	}

	.companies-header {
		align-items: stretch;
		flex-direction: column;
		padding: 16px;
	}

	.header-actions {
		justify-content: flex-start;
	}

	.stats-grid,
	.details-grid,
	.form-grid {
		grid-template-columns: 1fr;
	}

	.detail-card-wide,
	.span-2 {
		grid-column: auto;
	}

	.client-details,
	.modal-content {
		width: min(94vw, 620px);
		padding: 16px;
	}

	.details-icon {
		width: 46px;
		height: 46px;
	}

	.details-title h2 {
		font-size: 20px;
	}
}
</style>
