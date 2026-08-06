<!-- eslint-disable object-curly-newline -->
<template>
	<div class="contacts-list__item-wrapper">
		<div v-if="Object.keys(data).length == 0">
			<div class="empty">
				<div class="areas-empty-state areas-empty-state--network">
					<EntityCountNetwork
						:items="items"
						entity-type="area"
						:show-hierarchy="true"
						@select="onNetworkSelect" />
				</div>
			</div>
		</div>
		<div v-else>
			<div class="area-details">
				<div class="area-hero">
					<div class="area-hero__content">
						<span class="area-hero__eyebrow">
							{{ t('empleados', 'Area details') }}
						</span>
						<div class="area-hero__title-row">
							<h2 class="area-hero__title">
								{{ data.Nombre }}
							</h2>
							<span class="area-hero__count">
								{{ employeeCount }} {{ t('empleados', 'employees') }}
							</span>
						</div>
						<p class="area-hero__description">
							{{ t('empleados', 'Review the assigned employees, update the structure of the area and switch between different display modes.') }}
						</p>
						<div class="area-hero__meta">
							<div class="area-meta-card">
								<span class="area-meta-card__label">{{ t('empleados', 'Department / area') }}</span>
								<strong class="area-meta-card__value">{{ data.Nombre }}</strong>
							</div>
							<div class="area-meta-card">
								<span class="area-meta-card__label">{{ t('empleados', 'Parent area') }}</span>
								<strong class="area-meta-card__value">
									{{ data.Id_padre || t('empleados', 'No parent area') }}
								</strong>
							</div>
							<div class="area-meta-card">
								<span class="area-meta-card__label">{{ t('empleados', 'Display mode') }}</span>
								<strong class="area-meta-card__value">
									{{ preferencias_areas ? t('empleados', 'Cards') : t('empleados', 'List') }}
								</strong>
							</div>
						</div>
					</div>
					<div class="area-hero__actions">
						<NcActions>
							<template #icon>
								<AccountCog :size="20" />
							</template>
							<NcActionButton
								:close-after-click="true"
								@click="showEdit()">
								<template #icon>
									<AccountEdit :size="20" />
								</template>
								{{ t('empleados', 'Enable editing') }}
							</NcActionButton>
							<NcActionButton
								:close-after-click="true"
								@click="ChangeView()">
								<template #icon>
									<AccountEdit :size="20" />
								</template>
								{{ t('empleados', 'Change view type') }}
							</NcActionButton>
							<NcActionSeparator />
							<NcActionButton
								:close-after-click="true"
								@click="showDialog = true">
								<template #icon>
									<DeleteAlert :size="20" />
								</template>
								{{ t('empleados', 'Delete department') }}
							</NcActionButton>
							<NcDialog
								:open.sync="showDialog"
								:name="t('empleados', 'Confirm')"
								:message="t('empleados', 'Do you want to delete {departamento}?', { departamento: data.Nombre })"
								:buttons="buttons" />
						</NcActions>
					</div>
				</div>
				<div class="employees-panel">
					<div class="employees-panel__header">
						<div>
							<h3 class="employees-panel__title">
								{{ t('empleados', 'Employees in department / Area') }}
							</h3>
							<p class="employees-panel__subtitle">
								{{ employeeCount }} {{ t('empleados', 'people assigned to this area') }}
							</p>
						</div>
						<span class="employees-panel__view-badge">
							{{ preferencias_areas ? t('empleados', 'Card view') : t('empleados', 'List view') }}
						</span>
					</div>
					<div v-if="preferencias_areas" class="employees-grid-panel">
						<ul class="employees-grid">
							<li v-for="(item) in peopleArea.area"
								:key="item.Id_empleados"
								class="employees-grid__item">
								<div class="employee-card">
									<NcAvatar :user="item.Id_user" :display-name="item.Id_user" :size="60" />
									<div class="employee-card__body">
										<div class="employee-card__name">
											{{ item.displayname ? item.displayname : item.Id_user }}
										</div>
										<div class="employee-card__user">
											{{ item.Id_user }}
										</div>
									</div>
								</div>
							</li>
						</ul>
					</div>
					<div v-else class="employees-list-panel">
						<ul class="employees-list">
							<NcListItem v-for="(item) in peopleArea.area"
								:key="item.Id_empleados"
								bold
								:name="item.displayname ? item.displayname : item.Id_user"
								@click.prevent>
								<template #icon>
									<NcAvatar
										:size="44"
										:user="item.Id_user"
										:display-name="item.displayname ? item.displayname : item.Id_user" />
								</template>
								<template v-if="!item.displayname" #subname>
									{{ item.Id_user }}
								</template>
							</NcListItem>
						</ul>
					</div>
				</div>
			</div>
		</div>

		<NcModal
			v-if="show"
			ref="modalRef"
			:name="t('empleados', 'Edit')"
			@close="closeModal">
			<div class="modal__content">
				<div class="modal-form">
					<div class="form-group">
						<NcTextField
							:value.sync="area"
							:v-model="area"
							:label="t('empleados', 'Department/area name')" />
					</div>
					<div class="form-group">
						<NcSelect
							v-model="padre"
							:input-label="t('empleados', 'Parent area')"
							:options="options" />
					</div>
					<div class="form-group">
						<NcButton
							class="center"
							:aria-label="t('empleados', 'Save changes')"
							type="primary"
							@click="guardarcambioarea()">
							{{ t('empleados', 'Save changes') }}
						</NcButton>
					</div>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
// ICONOS
import DeleteAlert from 'vue-material-design-icons/DeleteAlert.vue'
import AccountEdit from 'vue-material-design-icons/AccountEdit.vue'
import AccountCog from 'vue-material-design-icons/AccountCog.vue'

import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

import {
	NcAvatar,
	NcActions,
	NcActionButton,
	NcActionSeparator,
	NcDialog,
	NcTextField,
	NcSelect,
	NcButton,
	NcListItem,
	NcModal,
} from '@nextcloud/vue'
import EntityCountNetwork from '../../../../components/EntityCountNetwork.vue'

export default {
	name: 'AreasDetails',

	components: {
		NcAvatar,
		NcActionSeparator,
		NcActions,
		AccountCog,
		AccountEdit,
		NcActionButton,
		DeleteAlert,
		NcDialog,
		NcTextField,
		NcSelect,
		NcButton,
		NcListItem,
		NcModal,
		EntityCountNetwork,
	},

	props: {
		data: {
			type: Object,
			required: true,
		},
		peopleArea: {
			type: Object,
			required: true,
		},
		items: {
			type: Array,
			required: false,
			default: () => [],
		},
	},

	data() {
		return {
			show: false,
			options: [],
			Empleados: [],
			showDialog: false,
			area: '',
			padre: '',
			preferencias_areas: null,
		}
	},

	computed: {
		employeeCount() {
			return this.peopleArea?.area?.length || 0
		},

		buttons() {
			return [
				{
					label: this.t('empleados', 'Cancelar'),
					callback: () => { this.lastResponse = 'Pressed "Cancel"' },
				},
				{
					label: this.t('empleados', 'Eliminar'),
					type: 'primary',
					callback: () => { this.eliminarDepartamento(this.data.Id_departamento) },
				},
			]
		},
	},

	mounted() {
		this.$root.$on('show', (data) => {
			this.show = data
		})
		this.preferencias_areas = localStorage.getItem('nextcloud_empleados_preferencias_areas')
		if (this.preferencias_areas === null) {
			localStorage.setItem('nextcloud_empleados_preferencias_areas', 'false')
			this.preferencias_areas = false
		} else {
			this.preferencias_areas = this.preferencias_areas === 'true'
		}
	},

	methods: {
		// expone t en el template
		t,

		onNetworkSelect(item) {
			if (!item || !item.Id_departamento) {
				return
			}
			this.$root.$emit('send-data-areas', item)
		},

		showEdit() {
			this.show = !this.show
			if (this.show === true) {
				this.getall()
				this.padre = this.data.Id_padre
				this.area = this.data.Nombre
			}
		},
		closeModal() {
			this.show = !this.show
		},
		async eliminarDepartamento(departamento) {
			this.showDialog = false
			try {
				await axios.post(generateUrl('/apps/empleados/EliminarArea'), {
					id_departamento: departamento,
				})
				showSuccess(this.t('empleados', 'Área eliminada exitosamente'))
				this.$root.$emit('reload')
				this.$root.$emit('send-data-areas', {})
			} catch (err) {
				showError(this.t('empleados', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},

		ChangeView() {
			this.preferencias_areas = !this.preferencias_areas
			localStorage.setItem('nextcloud_empleados_preferencias_areas', this.preferencias_areas)
		},

		checknull(value) {
			return value == null ? '' : value
		},

		async getall() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetAreasFix'))
				this.options = response?.data?.ocs?.data
			} catch (err) {
				showError(this.t('empleados', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		async guardarcambioarea() {
			if (this.padre == null) {
				this.padre = ''
			} else if (this.padre.label) {
				this.padre = this.padre.label
			}

			try {
				await axios.post(generateUrl('/apps/empleados/GuardarCambioArea'), {
					id_departamento: this.data.Id_departamento,
					padre: this.padre,
					nombre: this.area,
				})
				showSuccess(this.t('empleados', 'Área actualizada exitosamente'))
				this.$root.$emit('reload')
				this.$root.$emit('send-data-areas', {})
				this.showEdit()
			} catch (err) {
				this.showEdit()
				showError(this.t('empleados', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},
	},
}
</script>

<style>
.area-details {
	padding: 20px;
}

.area-hero {
	display: flex;
	justify-content: space-between;
	gap: 20px;
	padding: 28px;
	border: 1px solid var(--color-border);
	border-radius: 24px;
	background: var(--color-main-background);
	box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
}

.area-hero__content {
	flex: 1;
	min-width: 0;
}

.area-hero__eyebrow {
	display: inline-flex;
	margin-bottom: 12px;
	padding: 6px 12px;
	border-radius: 999px;
	background: rgba(52, 120, 246, 0.12);
	color: var(--color-primary-element);
	font-size: 12px;
	font-weight: 700;
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.area-hero__title-row {
	display: flex;
	align-items: center;
	gap: 12px;
	flex-wrap: wrap;
}

.area-hero__title {
	margin: 0;
	font-size: 34px;
	line-height: 1.05;
	letter-spacing: -0.02em;
}

.area-hero__count {
	display: inline-flex;
	align-items: center;
	padding: 8px 14px;
	border-radius: 999px;
	background: var(--color-background-hover);
	color: var(--color-main-text);
	font-size: 13px;
	font-weight: 700;
}

.area-hero__description {
	max-width: 720px;
	margin: 14px 0 0;
	color: var(--color-text-maxcontrast);
	line-height: 1.6;
}

.area-hero__meta {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 14px;
	margin-top: 22px;
}

.area-meta-card {
	padding: 16px 18px;
	border: 1px solid var(--color-border);
	border-radius: 18px;
	background: var(--color-background-hover);
}

.area-meta-card__label {
	display: block;
	margin-bottom: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.area-meta-card__value {
	display: block;
	font-size: 16px;
	line-height: 1.4;
	color: var(--color-main-text);
}

.area-hero__actions {
	display: flex;
	align-items: flex-start;
	justify-content: flex-end;
}

.employees-panel {
	margin-top: 22px;
	padding: 22px;
	border: 1px solid var(--color-border);
	border-radius: 24px;
	background: var(--color-main-background);
	box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
}

.employees-panel__header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
	margin-bottom: 18px;
}

.employees-panel__title {
	margin: 0;
	font-size: 22px;
}

.employees-panel__subtitle {
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
}

.employees-panel__view-badge {
	display: inline-flex;
	align-items: center;
	padding: 8px 14px;
	border-radius: 999px;
	border: 1px solid var(--color-border);
	color: var(--color-main-text);
	font-size: 13px;
	font-weight: 600;
}

.employees-grid-panel,
.employees-list-panel {
	padding: 8px;
	border-radius: 20px;
}

.employees-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
	gap: 16px;
	padding: 0;
	margin: 0;
	list-style: none;
}

.employees-grid__item {
	min-width: 0;
}

.employee-card {
	display: flex;
	align-items: center;
	gap: 16px;
	height: 100%;
	padding: 18px;
	border: 1px solid rgba(148, 163, 184, 0.2);
	border-radius: 20px;
	transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.employee-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 18px 34px rgba(15, 23, 42, 0.12);
}

.employee-card__body {
	min-width: 0;
}

.employee-card__name {
	font-size: 15px;
	font-weight: 700;
	color: var(--color-main-text);
	word-break: break-word;
}

.employee-card__user {
	margin-top: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	word-break: break-word;
}

.employees-list {
	padding: 0;
	margin: 0;
	list-style: none;
}

.modal__content {
	margin: 40px;
}

.modal-form {
	display: flex;
	flex-direction: column;
}

.form-group {
	margin: calc(var(--default-grid-baseline) * 4) 0;
	display: flex;
	flex-direction: column;
	align-items: flex-start;
}
.areas-empty-state {
	min-height: calc(100vh - var(--header-height) - 80px);
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 32px;
}

.areas-empty-state--network {
	width: 100%;
	height: calc(100vh - var(--header-height) - 24px);
	min-height: 640px;
	align-items: stretch;
	justify-content: stretch;
	padding: 8px 12px 12px;
}

.areas-empty-card {
	width: min(760px, 100%);
	padding: 36px;
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 16px rgba(0, 0, 0, 0.08);
	text-align: center;
}

.areas-empty-image {
	width: 150px;
	margin-bottom: 16px;
	opacity: 0.95;
}

.areas-empty-card h2 {
	margin: 0 0 8px;
	font-size: 24px;
	font-weight: 700;
	color: var(--color-main-text);
}

.areas-empty-description {
	max-width: 560px;
	margin: 0 auto 24px;
	color: var(--color-text-maxcontrast);
	line-height: 1.5;
}

.areas-empty-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
	margin: 24px 0;
}

.areas-empty-item {
	padding: 16px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	text-align: left;
}

.areas-empty-item strong {
	display: block;
	margin-bottom: 6px;
	color: var(--color-main-text);
	font-size: 15px;
}

.areas-empty-item span {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	line-height: 1.4;
}

.areas-empty-actions {
	display: flex;
	justify-content: center;
	gap: 12px;
	flex-wrap: wrap;
	margin-top: 20px;
}

@media (max-width: 960px) {
	.area-hero {
		flex-direction: column;
	}

	.area-hero__meta {
		grid-template-columns: 1fr;
	}

	.area-hero__actions {
		justify-content: flex-start;
	}
}

@media (max-width: 700px) {
	.area-details {
		padding: 12px;
	}

	.area-hero,
	.employees-panel {
		padding: 18px;
		border-radius: 20px;
	}

	.area-hero__title {
		font-size: 28px;
	}

	.modal__content {
		margin: 24px 18px;
	}

	.areas-empty-state {
		align-items: flex-start;
		padding: 20px 12px;
	}

	.areas-empty-card {
		padding: 24px 16px;
	}

	.areas-empty-grid {
		grid-template-columns: 1fr;
	}

	.areas-empty-item {
		text-align: center;
	}
}
</style>
