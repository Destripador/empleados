<template>
	<div class="group-settings">
		<div class="settings-header">
			<div class="settings-header__icon">
				<AccountGroup :size="28" />
			</div>
			<div class="settings-header__content">
				<p class="section-label">
					{{ t('empleados', 'Access control') }}
				</p>
				<h2 class="board-title">
					{{ t('empleados', 'Groups and permissions') }}
				</h2>
				<p class="settings-description">
					{{ t('empleados', 'Manage the Nextcloud groups that can be assigned from the employees app. User membership is still stored in Nextcloud groups  this catalog only controls which groups are manageable from this module.') }}
				</p>
			</div>
		</div>

		<NcNoteCard type="info" class="group-settings__note">
			{{ t('empleados', 'Restricted permissions should only be assigned or removed by a Nextcloud administrator.')
			}}
		</NcNoteCard>

		<div class="group-settings__toolbar">
			<NcTextField class="group-settings__search"
				:value.sync="search"
				:label="t('empleados', 'Search groups or modules')" />

			<NcButton @click="checkStructure">
				<template #icon>
					<ShieldCheck :size="20" />
				</template>
				{{ t('empleados', 'Check group structure') }}
			</NcButton>

			<NcButton type="primary" @click="openCreateDialog">
				<template #icon>
					<Plus :size="20" />
				</template>
				{{ t('empleados', 'Add permission group') }}
			</NcButton>
		</div>

		<div v-if="loading" class="group-settings__loading">
			<NcLoadingIcon :size="64" />
		</div>

		<NcEmptyContent v-else-if="filteredGroups.length === 0"
			:name="search ? t('empleados', 'No permission groups match the search') : t('empleados', 'No permission groups configured')"
			:description="t('empleados', 'Create the first permission group to start managing module access dynamically.')">
			<template #icon>
				<AccountGroup :size="28" />
			</template>
		</NcEmptyContent>

		<div v-else class="group-settings__list">
			<div v-for="item in filteredGroups"
				:key="item.id"
				class="permission-card"
				:class="{
					'permission-card--disabled': !item.enabled,
					'permission-card--restricted': item.restricted,
				}">
				<div class="permission-card__main">
					<div class="permission-card__icon">
						<AccountGroup :size="22" />
					</div>

					<div class="permission-card__content">
						<div class="permission-card__title-row">
							<h3>{{ item.label }}</h3>

							<span v-if="item.enabled" class="status-badge status-badge--enabled">
								{{ t('empleados', 'Enabled') }}
							</span>
							<span v-else class="status-badge status-badge--disabled">
								{{ t('empleados', 'Disabled') }}
							</span>

							<span v-if="item.restricted" class="status-badge status-badge--restricted">
								{{ t('empleados', 'Restricted') }}
							</span>

							<span v-if="!item.exists" class="status-badge status-badge--missing">
								{{ t('empleados', 'Group does not exist') }}
							</span>
						</div>

						<div class="permission-card__meta">
							<code>{{ item.group_id }}</code>
							<span>·</span>
							<span>{{ item.module }}</span>
							<span>·</span>
							<span>{{ item.permission }}</span>
						</div>

						<p v-if="item.description" class="permission-card__description">
							{{ item.description }}
						</p>
						<p v-else class="permission-card__description permission-card__description--empty">
							{{ t('empleados', 'No description provided.') }}
						</p>
					</div>
				</div>

				<NcActions>
					<NcActionButton close-after-click @click="openEditDialog(item)">
						<template #icon>
							<Pencil :size="20" />
						</template>
						{{ t('empleados', 'Edit') }}
					</NcActionButton>

					<NcActionButton v-if="item.enabled" close-after-click @click="disableGroup(item)">
						<template #icon>
							<EyeOff :size="20" />
						</template>
						{{ t('empleados', 'Disable') }}
					</NcActionButton>

					<NcActionButton v-else close-after-click @click="enableGroup(item)">
						<template #icon>
							<Eye :size="20" />
						</template>
						{{ t('empleados', 'Enable') }}
					</NcActionButton>
				</NcActions>
			</div>
		</div>

		<NcModal v-if="showDialog"
			size="large"
			:name="dialogTitle"
			@close="closeDialog">
			<div class="permission-form">
				<NcNoteCard v-if="formError" type="error" class="permission-form__error">
					{{ formError }}
				</NcNoteCard>

				<div class="permission-form__section">
					<h3>{{ t('empleados', 'Permission setup') }}</h3>
					<p>
						{{ t('empleados', 'Choose the module, permission level and the Nextcloud group that will grant access.') }}
					</p>
				</div>

				<div class="permission-form__grid">
					<NcSelect :value="selectedModule"
						:options="modulesOptions"
						:input-label="t('empleados', 'Module')"
						label="label"
						:clearable="false"
						@input="onModuleSelected" />

					<NcSelect :value="selectedPermission"
						:options="permissionOptions"
						:input-label="t('empleados', 'Permission level')"
						label="label"
						:clearable="false"
						@input="onPermissionSelected" />

					<NcSelect v-if="!allowManualGroup"
						:value="selectedGroup"
						:options="nextcloudGroups"
						:input-label="t('empleados', 'Nextcloud group')"
						label="label"
						:clearable="false"
						@input="onGroupSelected"
						@search="loadNextcloudGroups" />

					<NcTextField v-else
						:value.sync="form.group_id"
						:label="t('empleados', 'New group ID')"
						:placeholder="t('empleados', 'Example: empleados_admin')" />

					<NcTextField :value.sync="form.sort_order"
						type="number"
						:label="t('empleados', 'Sort order')"
						:placeholder="t('empleados', 'Example: 10')" />
				</div>

				<div class="permission-form__manual-toggle">
					<NcCheckboxRadioSwitch :checked="allowManualGroup"
						type="switch"
						@update:checked="allowManualGroup = Boolean($event)">
						<span>
							<strong>{{ t('empleados', 'Use a new group ID') }}</strong>
							<small>{{ t('empleados', 'Enable this if the group does not exist yet. The structure repair tool can create it later.') }}</small>
						</span>
					</NcCheckboxRadioSwitch>
				</div>

				<div class="permission-form__section">
					<h3>{{ t('empleados', 'Display information') }}</h3>
				</div>

				<div class="permission-form__grid">
					<NcTextField :value.sync="form.label"
						:label="t('empleados', 'Display name')"
						:placeholder="t('empleados', 'Example: Employees - Administrator')" />
				</div>

				<NcTextField class="permission-form__description"
					:value.sync="form.description"
					:label="t('empleados', 'Description')"
					:placeholder="t('empleados', 'Describe what this permission allows.')" />

				<div class="permission-form__switches">
					<NcCheckboxRadioSwitch :checked="form.enabled"
						type="switch"
						@update:checked="form.enabled = Boolean($event)">
						<span>
							<strong>{{ t('empleados', 'Enabled') }}</strong>
							<small>{{ t('empleados', 'Disabled permissions are hidden from user assignment.') }}</small>
						</span>
					</NcCheckboxRadioSwitch>

					<NcCheckboxRadioSwitch :checked="form.restricted"
						type="switch"
						@update:checked="form.restricted = Boolean($event)">
						<span>
							<strong>{{ t('empleados', 'Restricted') }}</strong>
							<small>{{ t('empleados', 'Only Nextcloud administrators should assign or remove this permission.')
							}}</small>
						</span>
					</NcCheckboxRadioSwitch>
				</div>

				<NcNoteCard v-if="form.group_id && !isValidGroupId(form.group_id)"
					type="warning"
					class="permission-form__warning">
					{{ t('empleados', 'The group ID can only contain letters, numbers, underscore, dot, at sign or dash.') }}
				</NcNoteCard>

				<div class="permission-form__actions">
					<NcButton @click="closeDialog">
						{{ t('empleados', 'Cancel') }}
					</NcButton>

					<NcButton type="primary" :disabled="saving || !isFormValid" @click="saveGroup">
						<template #icon>
							<ContentSave :size="20" />
						</template>
						{{ saving ? t('empleados', 'Saving') : t('empleados', 'Save') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
		<NcModal v-if="structureDialog"
			size="large"
			:name="t('empleados', 'Group structure check')"
			@close="closeStructureDialog">
			<div class="structure-check">
				<NcNoteCard v-if="structure.summary.missing_total === 0" type="success">
					{{ t('empleados', 'Group structure looks good. No missing groups were found.') }}
				</NcNoteCard>

				<NcNoteCard v-else type="warning">
					{{ t('empleados', 'Some required groups or catalog entries are missing. You can repair the structure to recreate them.') }}
				</NcNoteCard>

				<div class="structure-summary">
					<div class="structure-summary__item">
						<strong>{{ structure.summary.base_missing }}</strong>
						<span>{{ t('empleados', 'Missing base groups') }}</span>
					</div>
					<div class="structure-summary__item">
						<strong>{{ structure.summary.catalog_missing }}</strong>
						<span>{{ t('empleados', 'Missing permission groups') }}</span>
					</div>
					<div class="structure-summary__item">
						<strong>{{ structure.summary.catalog_entries_missing }}</strong>
						<span>{{ t('empleados', 'Missing catalog entries') }}</span>
					</div>
					<div class="structure-summary__item">
						<strong>{{ structure.summary.missing_total }}</strong>
						<span>{{ t('empleados', 'Total missing') }}</span>
					</div>
				</div>

				<h3>{{ t('empleados', 'Base groups') }}</h3>
				<div class="structure-list">
					<div v-for="group in structure.base_groups" :key="`base-${group.id}`" class="structure-row">
						<div>
							<strong>{{ group.label }}</strong>
							<code>{{ group.id }}</code>
							<p>{{ group.description }}</p>
						</div>

						<span v-if="group.exists" class="status-badge status-badge--enabled">
							{{ t('empleados', 'Exists') }}
						</span>
						<span v-else class="status-badge status-badge--missing">
							{{ t('empleados', 'Missing') }}
						</span>
					</div>
				</div>
				<h3>{{ t('empleados', 'Required catalog entries') }}</h3>
				<div class="structure-list">
					<div v-for="entry in structure.required_catalog_entries"
						:key="`required-catalog-${entry.module}-${entry.permission}-${entry.group_id}`"
						class="structure-row"
						:class="{ 'structure-row--disabled': !entry.enabled }">
						<div>
							<strong>{{ entry.label }}</strong>
							<code>{{ entry.group_id }}</code>
							<p>
								{{ entry.module }} · {{ entry.permission }}
								<span v-if="entry.restricted">· {{ t('empleados', 'Restricted') }}</span>
							</p>
							<p v-if="entry.description">
								{{ entry.description }}
							</p>
						</div>

						<span v-if="entry.exists" class="status-badge status-badge--enabled">
							{{ t('empleados', 'Exists in catalog') }}
						</span>
						<span v-else class="status-badge status-badge--missing">
							{{ t('empleados', 'Missing in catalog') }}
						</span>
					</div>

					<NcEmptyContent v-if="structure.required_catalog_entries.length === 0"
						:name="t('empleados', 'No required catalog entries')"
						:description="t('empleados', 'There are no required permission definitions configured for this check.')">
						<template #icon>
							<AccountGroup :size="28" />
						</template>
					</NcEmptyContent>
				</div>
				<h3>{{ t('empleados', 'Permission groups') }}</h3>
				<div class="structure-list">
					<div v-for="group in structure.catalog_groups"
						:key="`catalog-${group.catalog_id}`"
						class="structure-row"
						:class="{ 'structure-row--disabled': !group.enabled }">
						<div>
							<strong>{{ group.label }}</strong>
							<code>{{ group.id }}</code>
							<p>
								{{ group.module }} · {{ group.permission }}
								<span v-if="!group.enabled">· {{ t('empleados', 'Disabled') }}</span>
							</p>
						</div>

						<span v-if="group.exists" class="status-badge status-badge--enabled">
							{{ t('empleados', 'Exists') }}
						</span>
						<span v-else-if="group.enabled" class="status-badge status-badge--missing">
							{{ t('empleados', 'Missing') }}
						</span>
						<span v-else class="status-badge status-badge--disabled">
							{{ t('empleados', 'Disabled') }}
						</span>
					</div>
				</div>

				<div class="structure-actions">
					<NcButton @click="closeStructureDialog">
						{{ t('empleados', 'Close') }}
					</NcButton>

					<NcButton type="primary"
						:disabled="repairingStructure || structure.summary.missing_total === 0"
						@click="repairStructure">
						<template #icon>
							<ShieldCheck :size="20" />
						</template>
						{{ repairingStructure ? t('empleados', 'Repairing') : t('empleados', 'Repair structure') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import EyeOff from 'vue-material-design-icons/EyeOff.vue'
import ContentSave from 'vue-material-design-icons/ContentSave.vue'

import ShieldCheck from 'vue-material-design-icons/ShieldCheck.vue'

import {
	NcActions,
	NcActionButton,
	NcButton,
	NcCheckboxRadioSwitch,
	NcEmptyContent,
	NcLoadingIcon,
	NcModal,
	NcNoteCard,
	NcTextField,
	NcSelect,
} from '@nextcloud/vue'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

const EMPTY_FORM = {
	id: null,
	module: '',
	permission: '',
	group_id: '',
	label: '',
	description: '',
	restricted: false,
	enabled: true,
	sort_order: 0,
}

export default {
	name: 'GroupSettings',

	components: {
		AccountGroup,
		Plus,
		Pencil,
		Eye,
		EyeOff,
		ContentSave,
		NcActions,
		NcActionButton,
		NcButton,
		NcCheckboxRadioSwitch,
		NcEmptyContent,
		NcLoadingIcon,
		NcModal,
		NcNoteCard,
		NcTextField,
		ShieldCheck,
		NcSelect,
	},

	data() {
		return {
			loading: true,
			saving: false,
			search: '',
			groups: [],
			showDialog: false,
			formMode: 'create',
			form: { ...EMPTY_FORM },
			formError: '',
			checkingStructure: false,
			repairingStructure: false,
			structureDialog: false,
			structure: {
				summary: {
					base_total: 0,
					base_missing: 0,
					catalog_total: 0,
					catalog_missing: 0,
					catalog_entries_required: 0,
					catalog_entries_missing: 0,
					missing_total: 0,
				},
				base_groups: [],
				catalog_groups: [],
				required_catalog_entries: [],
				missing_base_groups: [],
				missing_catalog_groups: [],
				missing_catalog_entries: [],
			},
			modulesOptions: [
				{ id: 'empleados', label: t('empleados', 'Employees / HR') },
				{ id: 'compras', label: t('empleados', 'Purchases') },
				{ id: 'clientes', label: t('empleados', 'Customers') },
				{ id: 'inventario', label: t('empleados', 'IT inventory') },
				{ id: 'soporte', label: t('empleados', 'Support') },
				{ id: 'reporte_tiempos', label: t('empleados', 'Time reports') },
				{ id: 'ahorro', label: t('empleados', 'Savings') },
				{ id: 'ausencias', label: t('empleados', 'Working time') },
			],
			permissionOptions: [
				{ id: 'admin', label: t('empleados', 'Administrator') },
				{ id: 'view', label: t('empleados', 'View only') },
				{ id: 'request', label: t('empleados', 'Requester') },
				{ id: 'approve', label: t('empleados', 'Approver') },
				{ id: 'accounting', label: t('empleados', 'Accounting') },
				{ id: 'hr', label: t('empleados', 'Human resources') },
			],
			nextcloudGroups: [],
			selectedModule: null,
			selectedPermission: null,
			selectedGroup: null,
			allowManualGroup: false,
		}
	},

	computed: {
		filteredGroups() {
			const query = String(this.search || '').trim().toLowerCase()

			if (!query) {
				return this.groups
			}

			return this.groups.filter((item) => {
				return [
					item.label,
					item.group_id,
					item.module,
					item.permission,
					item.description,
				].join(' ').toLowerCase().includes(query)
			})
		},

		dialogTitle() {
			return this.formMode === 'edit'
				? t('empleados', 'Edit permission group')
				: t('empleados', 'Add permission group')
		},

		isFormValid() {
			return String(this.form.module || '').trim() !== ''
                && String(this.form.permission || '').trim() !== ''
                && String(this.form.group_id || '').trim() !== ''
                && String(this.form.label || '').trim() !== ''
                && this.isValidGroupId(this.form.group_id)
		},
	},

	mounted() {
		this.loadGroups()
	},

	methods: {
		t,

		async loadGroups() {
			this.loading = true

			try {
				const response = await axios.get(generateUrl('/apps/empleados/permisos/catalogo'))
				const payload = this.getPayload(response)

				if (payload.status !== 'ok') {
					throw new Error(payload.message || t('empleados', 'Could not load permission groups.'))
				}

				this.groups = payload.data || []
			} catch (err) {
				showError(t('empleados', 'Error loading permission groups: {error}', { error: String(err) }))
				console.error(err)
			} finally {
				this.loading = false
			}
		},

		openCreateDialog() {
			this.formMode = 'create'
			this.form = { ...EMPTY_FORM }
			this.selectedModule = null
			this.selectedPermission = null
			this.selectedGroup = null
			this.allowManualGroup = false
			this.formError = ''
			this.showDialog = true
			this.loadNextcloudGroups()
		},

		openEditDialog(item) {
			this.formMode = 'edit'
			this.form = {
				id: item.id,
				module: item.module || '',
				permission: item.permission || '',
				group_id: item.group_id || '',
				label: item.label || '',
				description: item.description || '',
				restricted: Boolean(item.restricted),
				enabled: Boolean(item.enabled),
				sort_order: Number(item.sort_order || 0),
			}

			this.selectedModule = this.modulesOptions.find(option => option.id === this.form.module) || {
				id: this.form.module,
				label: this.form.module,
			}

			this.selectedPermission = this.permissionOptions.find(option => option.id === this.form.permission) || {
				id: this.form.permission,
				label: this.form.permission,
			}

			this.selectedGroup = {
				id: this.form.group_id,
				label: this.form.group_id,
			}

			this.allowManualGroup = false
			this.formError = ''
			this.showDialog = true
			this.loadNextcloudGroups()
		},

		closeDialog() {
			if (this.saving) {
				return
			}

			this.showDialog = false
			this.form = { ...EMPTY_FORM }
			this.formError = ''
		},

		async saveGroup() {
			this.formError = ''

			if (!this.isFormValid) {
				this.formError = t('empleados', 'Complete the required fields before saving.')
				return
			}

			this.saving = true

			try {
				const payload = {
					module: String(this.form.module).trim(),
					permission: String(this.form.permission).trim(),
					group_id: String(this.form.group_id).trim(),
					label: String(this.form.label).trim(),
					description: String(this.form.description || '').trim(),
					restricted: Boolean(this.form.restricted),
					enabled: Boolean(this.form.enabled),
					sort_order: Number(this.form.sort_order || 0),
				}

				const url = this.formMode === 'edit'
					? generateUrl('/apps/empleados/permisos/catalogo/{id}', { id: this.form.id })
					: generateUrl('/apps/empleados/permisos/catalogo')

				const response = await axios.post(url, payload)
				const data = this.getPayload(response)

				if (data.status !== 'ok') {
					throw new Error(data.message || t('empleados', 'Could not save permission group.'))
				}

				showSuccess(data.message || t('empleados', 'Permission group saved.'))
				this.closeDialog()
				await this.loadGroups()
			} catch (err) {
				this.formError = String(err?.response?.data?.message || err?.response?.data?.ocs?.data?.message || err.message || err)
				showError(t('empleados', 'Error saving permission group: {error}', { error: this.formError }))
				console.error(err)
			} finally {
				this.saving = false
			}
		},

		async enableGroup(item) {
			await this.toggleGroup(item, true)
		},

		async disableGroup(item) {
			await this.toggleGroup(item, false)
		},

		async toggleGroup(item, enabled) {
			try {
				const endpoint = enabled ? 'enable' : 'disable'
				const response = await axios.post(
					generateUrl('/apps/empleados/permisos/catalogo/{id}/{endpoint}', {
						id: item.id,
						endpoint,
					}),
				)

				const payload = this.getPayload(response)

				if (payload.status !== 'ok') {
					throw new Error(payload.message || t('empleados', 'Could not update permission group.'))
				}

				showSuccess(payload.message || t('empleados', 'Permission group updated.'))
				await this.loadGroups()
			} catch (err) {
				showError(t('empleados', 'Error updating permission group: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		getPayload(response) {
			return response?.data?.ocs?.data || response?.data || {}
		},

		isValidGroupId(groupId) {
			return /^[a-zA-Z0-9_.@-]+$/.test(String(groupId || '').trim())
		},
		async checkStructure() {
			this.checkingStructure = true

			try {
				const response = await axios.get(generateUrl('/apps/empleados/permisos/catalogo/estructura'), {
					headers: {
						Accept: 'application/json',
						'OCS-APIRequest': true,
					},
				})

				const payload = this.getPayload(response)

				if (payload.status !== 'ok') {
					throw new Error(payload.message || t('empleados', 'Could not check group structure.'))
				}

				this.structure = this.normalizeStructure(payload.data || {})
				this.structureDialog = true
			} catch (err) {
				showError(t('empleados', 'Error checking group structure: {error}', { error: String(err) }))
				console.error(err)
			} finally {
				this.checkingStructure = false
			}
		},

		async repairStructure() {
			this.repairingStructure = true

			try {
				const response = await axios.post(generateUrl('/apps/empleados/permisos/catalogo/estructura/reparar'), {}, {
					headers: {
						Accept: 'application/json',
						'OCS-APIRequest': true,
					},
				})

				const payload = this.getPayload(response)

				if (!['ok', 'partial'].includes(payload.status)) {
					throw new Error(payload.message || t('empleados', 'Could not repair group structure.'))
				}

				showSuccess(payload.message || t('empleados', 'Group structure repaired.'))

				await this.checkStructure()
				await this.loadGroups()
			} catch (err) {
				showError(t('empleados', 'Error repairing group structure: {error}', { error: String(err) }))
				console.error(err)
			} finally {
				this.repairingStructure = false
			}
		},

		closeStructureDialog() {
			if (this.repairingStructure) {
				return
			}

			this.structureDialog = false
		},

		async loadNextcloudGroups(search = '') {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/permisos/catalogo/grupos-nextcloud'), {
					params: { search },
					headers: {
						Accept: 'application/json',
						'OCS-APIRequest': true,
					},
				})

				const payload = this.getPayload(response)

				if (payload.status !== 'ok') {
					throw new Error(payload.message || t('empleados', 'Could not load Nextcloud groups.'))
				}

				this.nextcloudGroups = payload.data || []
			} catch (err) {
				showError(t('empleados', 'Error loading Nextcloud groups: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		onModuleSelected(option) {
			this.selectedModule = option
			this.form.module = option?.id || ''

			this.autofillPermissionFields()
		},

		onPermissionSelected(option) {
			this.selectedPermission = option
			this.form.permission = option?.id || ''

			this.autofillPermissionFields()
		},

		onGroupSelected(option) {
			this.selectedGroup = option
			this.form.group_id = option?.id || ''
		},

		autofillPermissionFields() {
			if (!this.form.module || !this.form.permission) {
				return
			}

			const moduleLabel = this.selectedModule?.label || this.form.module
			const permissionLabel = this.selectedPermission?.label || this.form.permission

			if (!this.form.label || this.formMode === 'create') {
				this.form.label = `${moduleLabel} - ${permissionLabel}`
			}

			if (!this.form.description || this.formMode === 'create') {
				this.form.description = t(
					'empleados',
					'Allows {permission} access to the {module} module.',
					{
						permission: permissionLabel.toLowerCase(),
						module: moduleLabel.toLowerCase(),
					},
				)
			}

			if (!this.form.group_id || this.formMode === 'create') {
				this.form.group_id = `${this.form.module}_${this.form.permission}`
				this.selectedGroup = {
					id: this.form.group_id,
					label: `${this.form.group_id} (${t('empleados', 'new group')})`,
				}
			}
		},
		normalizeStructure(data = {}) {
			return {
				summary: {
					base_total: Number(data?.summary?.base_total || 0),
					base_missing: Number(data?.summary?.base_missing || 0),
					catalog_total: Number(data?.summary?.catalog_total || 0),
					catalog_missing: Number(data?.summary?.catalog_missing || 0),
					catalog_entries_required: Number(data?.summary?.catalog_entries_required || 0),
					catalog_entries_missing: Number(data?.summary?.catalog_entries_missing || 0),
					missing_total: Number(data?.summary?.missing_total || 0),
				},
				base_groups: data?.base_groups || [],
				catalog_groups: data?.catalog_groups || [],
				required_catalog_entries: data?.required_catalog_entries || [],
				missing_base_groups: data?.missing_base_groups || [],
				missing_catalog_groups: data?.missing_catalog_groups || [],
				missing_catalog_entries: data?.missing_catalog_entries || [],
			}
		},
	},
}
</script>

<style scoped lang="scss">
.group-settings {
    color: var(--color-main-text);
}

.settings-header {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin: 0 0 16px;
    padding: 18px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    background: var(--color-main-background);
}

.settings-header__icon {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: var(--border-radius-large);
    background: var(--color-background-hover);
    color: var(--color-primary-element);
}

.settings-header__content {
    min-width: 0;
}

.section-label {
    margin: 0 0 4px;
    color: var(--color-primary-element);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.board-title {
    margin: 0;
    color: var(--color-main-text);
    font-size: 24px;
    font-weight: bold;
}

.settings-description {
    max-width: 900px;
    margin: 8px 0 0;
    color: var(--color-text-maxcontrast);
    font-size: 14px;
    line-height: 1.4;
}

.group-settings__note {
    margin-bottom: 16px;
}

.group-settings__toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
}

.group-settings__search {
    flex: 1 1 320px;
    max-width: 520px;
}

.group-settings__loading {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 320px;
}

.group-settings__list {
    display: grid;
    gap: 12px;
}

.permission-card {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 16px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    background: var(--color-main-background);
}

.permission-card--disabled {
    opacity: .72;
}

.permission-card--restricted {
    border-color: var(--color-warning);
}

.permission-card__main {
    display: flex;
    min-width: 0;
    gap: 12px;
}

.permission-card__icon {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: var(--border-radius-large);
    background: var(--color-background-hover);
    color: var(--color-primary-element);
}

.permission-card__content {
    min-width: 0;
}

.permission-card__title-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.permission-card__title-row h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
}

.permission-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 4px;
    color: var(--color-text-maxcontrast);
    font-size: 13px;
}

.permission-card__meta code {
    padding: 1px 6px;
    border-radius: var(--border-radius);
    background: var(--color-background-dark);
    color: var(--color-main-text);
}

.permission-card__description {
    margin: 8px 0 0;
    color: var(--color-main-text);
    font-size: 14px;
    line-height: 1.4;
}

.permission-card__description--empty {
    color: var(--color-text-maxcontrast);
    font-style: italic;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    min-height: 22px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.status-badge--enabled {
    background: var(--color-success);
    color: var(--color-primary-text);
}

.status-badge--disabled {
    background: var(--color-background-dark);
    color: var(--color-text-maxcontrast);
}

.status-badge--restricted,
.status-badge--missing {
    background: var(--color-warning);
    color: var(--color-primary-text);
}

.permission-form {
	padding: 24px;
}

.permission-form__section {
	margin-bottom: 12px;
}

.permission-form__section h3 {
	margin: 0;
	font-size: 18px;
	font-weight: 700;
}

.permission-form__section p {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.permission-form__manual-toggle {
	margin-top: 14px;
}

.permission-form__error,
.permission-form__warning {
    margin-bottom: 16px;
}

.permission-form__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
}

.permission-form__description {
    margin-top: 14px;
}

.permission-form__switches {
    display: grid;
    gap: 12px;
    margin-top: 18px;
}

.permission-form__switches span {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.permission-form__switches small {
    color: var(--color-text-maxcontrast);
    font-size: 12px;
}

.permission-form__actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 24px;
}

@media (max-width: 720px) {

    .settings-header,
    .permission-card {
        flex-direction: column;
    }

    .group-settings__toolbar {
        align-items: stretch;
    }

    .group-settings__search {
        max-width: none;
    }

    .permission-form__actions {
        flex-direction: column-reverse;
    }
}.structure-check {
	padding: 20px;
}

.structure-summary {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
	gap: 12px;
	margin: 16px 0 24px;
}

.structure-summary__item {
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.structure-summary__item strong {
	display: block;
	font-size: 24px;
}

.structure-summary__item span {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.structure-list {
	display: grid;
	gap: 8px;
	margin-bottom: 24px;
}

.structure-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.structure-row--disabled {
	opacity: .65;
}

.structure-row code {
	display: inline-block;
	margin-left: 8px;
	padding: 1px 6px;
	border-radius: var(--border-radius);
	background: var(--color-background-dark);
}

.structure-row p {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.structure-actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 20px;
}
</style>
