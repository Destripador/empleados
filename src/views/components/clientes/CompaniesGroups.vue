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

			<div class="filter-wrap">
				<NcButton
					type="tertiary"
					@click.stop="toggleFilters"
					:title="t('empleados', 'Filters')">
					<template #icon>
						<FilterVariant :size="20" />
					</template>
					{{ t('empleados', 'Filters') }}
					<span
						v-if="(onlyParents ? 1 : 0) + (onlySpecial ? 1 : 0) > 0"
						class="filter-badge">
						{{ (onlyParents ? 1 : 0) + (onlySpecial ? 1 : 0) }}
					</span>
				</NcButton>

				<div v-if="showFilters" class="filter-dropdown" @click.stop>
					<div class="filter-section">
						<p class="filter-section-label">{{ t('empleados', 'Sort') }}</p>
						<NcSelect
							v-model="sortOrderObj"
							:options="sortOptions"
							:clearable="false"
							:searchable="false" />
					</div>
					<hr class="filter-divider" />
					<div class="filter-section">
						<NcCheckboxRadioSwitch v-model="onlyParents" type="switch">
							{{ t('empleados', 'Only Main Groups') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="onlySpecial" type="switch">
							{{ t('empleados', 'Only Special Clients') }}
						</NcCheckboxRadioSwitch>
					</div>
				</div>
			</div>

			<List
				:loading="loading"
				:listas="filteredListas"
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

							<div class="info-section">
								<div class="section-head">
									<div>
										<p class="section-label">
											{{ t('empleados', 'Company Information') }}
										</p>
										<h3>{{ t('empleados', 'General Information') }}</h3>
									</div>
								</div>

								<div class="info-grid">
									<div class="detail-card">
										<span>{{ t('empleados', 'Legal Business Name') }}</span>
										<strong>{{ selectedClient.razon_social || '-' }}</strong>
									</div>

									<div class="detail-card">
										<span>{{ t('empleados', 'Company Type') }}</span>
										<strong>{{ selectedClient.tipo_cliente || '-' }}</strong>
									</div>

									<div class="detail-card">
										<span>{{ t('empleados', 'Service Type') }}</span>
										<strong>{{ selectedClient.tipo_servicio || '-' }}</strong>
									</div>

									<div class="detail-card">
										<span>{{ t('empleados', 'Project Manager') }}</span>
										<strong>{{ selectedClient.lider_proyecto || '-' }}</strong>
									</div>

									<div class="detail-card">
										<span>{{ t('empleados', 'Primary Contact') }}</span>
										<strong>{{ selectedClient.nombre_contacto || '-' }}</strong>
									</div>

									<div class="detail-card">
										<span>{{ t('empleados', 'Phone Number') }}</span>
										<strong>{{ selectedClient.telefono || '-' }}</strong>
									</div>

									<div class="detail-card">
										<span>{{ t('empleados', 'Email Address') }}</span>
										<strong>{{ selectedClient.correo || '-' }}</strong>
									</div>

									<div class="detail-card">
										<span>{{ t('empleados', 'Status') }}</span>
										<strong>{{ selectedClient.status || '-' }}</strong>
									</div>

									<div class="detail-card">
										<span>{{ t('empleados', 'Location') }}</span>
										<strong>{{ selectedClient.ubicacion || '-' }}</strong>
									</div>

									<div class="detail-card">
										<span>{{ t('empleados', 'Service Fees') }}</span>
										<strong>
											{{ selectedClient.honorarios || '-' }}
											{{ selectedClient.tipo_moneda || '' }}
										</strong>
									</div>

									<div class="detail-card">
										<span>{{ t('empleados', 'Special Client') }}</span>
										<strong>
											{{ Number(selectedClient.especial) ? 'Yes' : 'No' }}
										</strong>
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
					<NcTextField
						required
						class="span-2"
						:value.sync="razon_social"
						:label="t('empleados', 'Legal Business Name')" />
					<NcTextField
						required
						:value.sync="tipo_cliente"
						:label="t('empleados', 'Company Type')" />

					<NcTextField
						required
						:value.sync="tipo_servicio"
						:label="t('empleados', 'Service Type')" />

					<NcSelect
						v-model="lider_proyecto"
						:options="projectManagers"
						:placeholder="t('empleados', 'Project Manager')"
						:clearable="true"
						class="aligned-select" />

					<NcTextField
						required
						:value.sync="nombre_contacto"
						:label="t('empleados', 'Primary Contact')" />

					<NcTextField
						required
						:value.sync="telefono"
						:label="t('empleados', 'Phone Number')" />

					<NcTextField
						required
						:value.sync="correo"
						:label="t('empleados', 'Email Address')" />

					<NcTextField
						required
						:value.sync="status"
						:label="t('empleados', 'Status')" />

					<NcTextField
						required
						:value.sync="ubicacion"
						:label="t('empleados', 'Location')" />

					<NcTextField
						required
						:value.sync="honorarios"
						:label="t('empleados', 'Service Fees')" />

					<NcTextField
						required
						:value.sync="tipo_moneda"
						:label="t('empleados', 'Currency')" />

					<NcTextArea
						class="span-2"
						resize="vertical"
						:value.sync="description_client"
						:label="t('empleados', 'Description')" />

					<div class="special-client-card span-2">
						<NcCheckboxRadioSwitch
							v-model="especial"
							type="switch" />

						<div class="special-client-info">
							<h3>{{ t('empleados', 'Special Client') }}</h3>
							<p>
								{{ t('empleados', 'Enable this option for special handling clients.') }}
							</p>
						</div>
					</div>

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
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import FilterVariant from 'vue-material-design-icons/FilterVariant.vue'

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
		NcCheckboxRadioSwitch,
		HexagonMultipleOutline,
		OfficeBuilding,
		AccountGroup,
		NcModal,
		NcTextField,
		NcButton,
		NcTextArea,
		NcSelect,
		FilterVariant,
		NcEmptyContent,
		NcNoteCard,
	},

	data() {
		return {
			projectManagers: [],
			editing: false,
			saving: false,
			loading: true,
			listas: [],
			rawClients: [],
			select: [],
			options: [],
			modal: false,
			name_cliente: '',
			razon_social: '',
			tipo_cliente: '',
			tipo_servicio: '',
			lider_proyecto: '',
			nombre_contacto: '',
			telefono: '',
			correo: '',
			status: '',
			ubicacion: '',
			honorarios: '',
			tipo_moneda: '',
			description_client: '',
			especial: false,
			padre: null,
			sortOrder: 'az',
			sortOptions: [
				{
					value: 'az',
					label: t('empleados', 'A to Z'),
				},
				{
					value: 'za',
					label: t('empleados', 'Z to A'),
				},
			],
			onlyParents: false,
			onlySpecial: false,
			showFilters: false,
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

		filteredListas() {
			let data = [...this.listas]

			if (this.onlyParents) {
				data = data.filter(item =>
					Number(item.cliente_padre || 0) === 0
				)
			}

			if (this.onlySpecial) {
				data = data.filter(item =>
					Number(item.especial || 0) === 1
				)
			}

			data.sort((a, b) => {
				const nameA = (a.nombre || a.name || '').toLowerCase()
				const nameB = (b.nombre || b.name || '').toLowerCase()

				return this.sortOrder === 'za'
					? nameB.localeCompare(nameA)
					: nameA.localeCompare(nameB)
			})

			return data
		},

		sortOrderObj: {
			get() {
				return this.sortOptions.find(o => o.value === this.sortOrder) || this.sortOptions[0]
			},
			set(val) {
				this.sortOrder = val.value
			},
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
		this.GetEmpleadosList()
		this._onClickOutside = (e) => {
			const wrap = this.$el.querySelector('.filter-wrap')
			if (wrap && !wrap.contains(e.target)) {
				this.showFilters = false
			}
		}
		document.addEventListener('click', this._onClickOutside)
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.onKeyDown)

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
			if (e.key === 'Escape') {
				this.onEsc()
			}
		},

		onEsc() {
			if (this.modal) {
				this.closeModal()
				return
			}

			if (this.showFilters) {
				this.showFilters = false
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
			this.razon_social = ''
			this.tipo_cliente = ''
			this.tipo_servicio = ''
			this.lider_proyecto = ''
			this.nombre_contacto = ''
			this.telefono = ''
			this.correo = ''
			this.status = ''
			this.ubicacion = ''
			this.honorarios = ''
			this.tipo_moneda = ''
			this.description_client = ''
			this.especial = false
			this.padre = null
		},

		triggerImport() {
			this.$refs.file?.click()
		},

		getOcsData(response) {
			return response?.data?.ocs?.data ?? response?.data ?? null
		},

		toggleFilters() {
			this.showFilters = !this.showFilters
		},

		async GetEmpleadosList() {
			try {
				const response = await axios.get(
					generateUrl('/apps/empleados/GetEmpleadosList')
				)

				const empleados = response?.data?.ocs?.data?.Empleados || []

				this.projectManagers = empleados.map((emp) => ({
					value: emp.uid,
					label: emp.displayname,
				}))
			} catch (err) {
				showError(
					t('empleados', 'Error loading employees: {error}', {
						error: String(err),
					})
				)
			}
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
				razon_social: String(this.razon_social || '').trim(),
				tipo_cliente: String(this.tipo_cliente || '').trim(),
				tipo_servicio: String(this.tipo_servicio || '').trim(),
				lider_proyecto: this.lider_proyecto?.label || '',
				nombre_contacto: String(this.nombre_contacto || '').trim(),
				telefono: String(this.telefono || '').trim(),
				correo: String(this.correo || '').trim(),
				status: String(this.status || '').trim(),
				ubicacion: String(this.ubicacion || '').trim(),
				honorarios: String(this.honorarios || '').trim(),
				tipo_moneda: String(this.tipo_moneda || '').trim(),
				detalles: String(this.description_client || '').trim(),
				especial: Boolean(this.especial),
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
					id_cliente: this.selectedClient.id_cliente,
					...payload,
				})

				showSuccess(t('empleados', 'Company or group updated successfully'))

				this.select = [{
					...this.selectedClient,
					nombre: payload.nombre,
					razon_social: payload.razon_social,
					tipo_cliente: payload.tipo_cliente,
					tipo_servicio: payload.tipo_servicio,
					lider_proyecto: payload.lider_proyecto,
					nombre_contacto: payload.nombre_contacto,
					telefono: payload.telefono,
					correo: payload.correo,
					status: payload.status,
					ubicacion: payload.ubicacion,
					honorarios: payload.honorarios,
					tipo_moneda: payload.tipo_moneda,
					detalles: payload.detalles,
					especial: payload.especial,
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
			this.razon_social = this.selectedClient.razon_social || ''
			this.tipo_cliente = this.selectedClient.tipo_cliente || ''
			this.tipo_servicio = this.selectedClient.tipo_servicio || ''
			this.lider_proyecto = this.projectManagers.find((emp) => emp.value === this.selectedClient.lider_proyecto) || null
			this.nombre_contacto = this.selectedClient.nombre_contacto || ''
			this.telefono = this.selectedClient.telefono || ''
			this.correo = this.selectedClient.correo || ''
			this.status = this.selectedClient.status || ''
			this.ubicacion = this.selectedClient.ubicacion || ''
			this.honorarios = this.selectedClient.honorarios || ''
			this.tipo_moneda = this.selectedClient.tipo_moneda || ''
			this.description_client = this.selectedClient.detalles || ''
			this.especial = Boolean(Number(this.selectedClient.especial))

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
.info-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
	gap: 12px;
	margin-top: 12px;
}

.info-section {
	margin-top: 24px;
}

.special-client-card {
	display: flex;
	align-items: center;
	gap: 16px;

	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-background-hover);
}

.special-client-info {
	display: flex;
	flex-direction: column;
}

.special-client-info h3 {
	margin: 0;
	font-size: 15px;
	font-weight: 600;
}

.special-client-info p {
	margin: 4px 0 0;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

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
	align-items: center;
	gap: 18px;
	padding: 20px;
	margin-bottom: 20px;

	border: 1px solid var(--color-border);
	border-radius: 16px;

	background: linear-gradient(
		135deg,
		var(--color-background-hover),
		var(--color-main-background)
	);
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
	font-size: 28px;
	font-weight: 800;
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
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: 14px;

	background: var(--color-main-background);

	box-shadow: 0 2px 8px rgba(0,0,0,.04);

	transition: all .2s ease;
}

.detail-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 6px 16px rgba(0,0,0,.08);
}

.detail-card-wide {
	grid-column: 1 / -1;
}

.detail-card span {
	display: block;
	margin-bottom: 8px;

	font-size: 11px;
	font-weight: 700;
	letter-spacing: .08em;

	color: var(--color-text-maxcontrast);

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
	padding: 14px;

	border: 1px solid var(--color-border);
	border-radius: 14px;

	background: var(--color-main-background);

	transition: all .2s ease;
}

.child-card:hover {
	transform: translateY(-3px);
	box-shadow: 0 8px 20px rgba(0,0,0,.08);
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
	width: 100%;
	max-width: 100%;
	box-sizing: border-box;
	padding: 24px;
	margin: 0 auto;
}

:deep(.modal-container) {
	max-width: 1100px !important;
	width: min(20vw, 80rem);
}

:deep(.modal-wrapper) {
	overflow-x: hidden !important;
}

.modal-header {
	margin-bottom: 18px;
}

.form-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 16px;
	width: 100%;
}

.form-grid > * {
	min-width: 0;
	width: 100%;
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

	.modal-content {
		width: 100%;
		box-sizing: border-box;
		padding: 24px;
	}

	.client-details {
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

.filter-wrap {
	position: relative;
	display: inline-flex;
	align-self: flex-start;
	overflow: visible;
}

.companies-page :deep(.search-contacts-field) {
	padding-left: 12px;
}

.companies-page :deep(.container-search) {
	align-items: center;
	gap: 6px;
}

.companies-page :deep(.input-container) {
	min-width: 0;
	margin-right: 0;
}

.filter-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background-color: var(--color-primary);
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  margin-left: 4px;
}

.filter-dropdown {
	position: absolute;
	top: calc(100% + 6px);
	left: 0;
	width: 190px;
	box-sizing: border-box;
	background: var(--color-main-background);
	border: 1px solid rgba(0, 0, 0, 0.28);
	border-radius: var(--border-radius);
	box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
	z-index: 100000;
	padding: 6px 0;
	overflow: hidden;
}

.filter-section {
	display: flex;
	flex-direction: column;
	gap: 4px;
	box-sizing: border-box;
	width: 100%;
	padding: 6px 10px;
}

.filter-section-label {
	margin: 0 0 4px;
	color: var(--color-text-maxcontrast);
	font-size: 10px;
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.filter-section :deep(.v-select),
.filter-section :deep(.multiselect) {
	width: 100% !important;
	min-width: 0 !important;
	max-width: 100% !important;
}

.filter-section :deep(.vs__dropdown-toggle),
.filter-section :deep(.multiselect__tags) {
	width: 100% !important;
	min-width: 0 !important;
	box-sizing: border-box;
}

.filter-section :deep(.vs__dropdown-menu),
.filter-section :deep(.multiselect__content-wrapper) {
	width: 100% !important;
	min-width: 0 !important;
	max-width: 100% !important;
	box-sizing: border-box;
}

.filter-section :deep(.multiselect) {
	min-height: 32px;
	width: 100%;
	font-size: 12px;
}

.filter-section :deep(.multiselect__tags) {
	min-height: 32px;
	padding: 5px 28px 0 8px;
}

.filter-section :deep(.multiselect__single),
.filter-section :deep(.multiselect__placeholder) {
	margin-bottom: 0;
	font-size: 12px;
	line-height: 20px;
}

.filter-section :deep(.checkbox-radio-switch) {
	min-height: 28px;
	font-size: 12px;
}

.filter-divider {
	margin: 2px 0;
	border: none;
	border-top: 1px solid var(--color-border);
}

.filter-section :deep(input) {
	display: none !important;
}

.sr-only {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0,0,0,0);
	white-space: nowrap;
	border: 0;
}
</style>
