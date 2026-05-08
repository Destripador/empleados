<template>
	<div class="contacts-list__item-wrapper">
		<!-- Empty state -->
		<div v-if="Object.keys(data).length === 0">
			<div class="emptycontent">
				<!-- Empty state -->
				<div v-if="Object.keys(data).length === 0" class="employee-empty-state">
					<div class="employee-empty-card">
						<img class="employee-empty-image"
							src="../../../../img/crowesito-think.png"
							alt="Empty employee state">

						<h2>{{ t('empleados', 'Select an employee to start') }}</h2>

						<p class="employee-empty-description">
							{{ t('empleados', 'Choose an employee from the list to view their profile, notes, personal information and files.') }}
						</p>

						<div class="employee-empty-stats">
							<div class="employee-empty-stat">
								<strong>{{ empleadosProp.length }}</strong>
								<span>{{ t('empleados', 'Employees') }}</span>
							</div>

							<div class="employee-empty-stat">
								<strong>{{ activeEmployees }}</strong>
								<span>{{ t('empleados', 'Active') }}</span>
							</div>

							<div class="employee-empty-stat">
								<strong>{{ inactiveEmployees }}</strong>
								<span>{{ t('empleados', 'Inactive') }}</span>
							</div>
						</div>

						<div class="employee-empty-actions">
							<NcButton @click="$bus.emit('getall')">
								{{ t('empleados', 'Refresh list') }}
							</NcButton>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Employee info -->
		<div v-else class="container">
			<div class="container-search-profile">
				<div class="button-container-profile">
					<NcActions>
						<template #icon>
							<AccountCog :size="20" />
						</template>

						<NcActionButton :close-after-click="true" @click="showEdit">
							<template #icon>
								<AccountEdit :size="20" />
							</template>
							{{ show ? t('empleados', 'Disable editing') : t('empleados', 'Enable editing') }}
						</NcActionButton>

						<NcActionSeparator />

						<NcActionButton :close-after-click="true" :disabled="true">
							<template #icon>
								<AccountEdit :size="20" />
							</template>
							{{ t('empleados', 'Export') }}
						</NcActionButton>

						<NcActionButton @click="DeactiveUserDialog(data.Id_empleados)">
							<template #icon>
								<AccountEdit :size="20" />
							</template>
							{{ t('empleados', 'Disable employee') }}
						</NcActionButton>
					</NcActions>
				</div>
			</div>

			<div class="card-container">
				<div class="user-card">
					<div class="avatar">
						<NcAvatar :url="getAvatarUrl(data.uid)" :size="100" />
						<div v-if="show" class="center">
							<NcButton @click="$refs.fileInput.click()">
								{{ t('empleados', 'Change photo') }}
							</NcButton>
						</div>
					</div>

					<div class="info">
						<h2>{{ data.displayname || data.uid }}</h2>
						<h2 v-if="data.mail">
							{{ data.mail }}
						</h2>
					</div>
				</div>
			</div>

			<!-- Tabs -->
			<div class="center">
				<VueTabs active-tab-color="#fdb913c"
					active-text-color="white"
					type="grow"
					centered>
					<VTab :title="t('empleados', 'Employee')">
						<EmpleadoTab
							:data="data"
							:show="show"
							:empleados="empleadosProp"
							:automaticsave="automatic_save_note" />
					</VTab>

					<VTab :title="t('empleados', 'Notes')">
						<NotasTab
							:data="data"
							:show="show"
							:empleados="Empleados"
							:automaticsave="automatic_save_note" />
					</VTab>

					<VTab :title="t('empleados', 'Personal')">
						<PersonalTab :data="data" :show="show" :empleados="Empleados" />
					</VTab>

					<VTab :title="t('empleados', 'Files')">
						<FilesTab :data="data" :show="show" :empleados="Empleados" />
					</VTab>
				</VueTabs>
			</div>
		</div>

		<!-- Dialogs -->
		<NcDialog
			:open.sync="showDeactiveUserDialog"
			:name="t('empleados', 'Confirmation')"
			:message="t('empleados', 'Are you sure you want to disable the account?')"
			:buttons="buttons" />

		<input
			ref="fileInput"
			type="file"
			class="file-input"
			accept="image/*"
			@change="onFileSelected">

		<CropperDialog
			:open.sync="showCropper"
			:preview-url="previewUrl"
			@confirm="handleCroppedImage"
			@error="handleCropperError" />
	</div>
</template>

<script>
import EmpleadoTab from './Tabs/EmpleadoTab.vue'
import PersonalTab from './Tabs/PersonalTab.vue'
import NotasTab from './Tabs/NotasTab.vue'
import FilesTab from './Tabs/FilesTab.vue'
import CropperDialog from './CropperDialog.vue'
import { VueTabs, VTab } from 'vue-nav-tabs/dist/vue-tabs.js'
import 'vue-nav-tabs/themes/vue-tabs.css'
import AccountEdit from 'vue-material-design-icons/AccountEdit.vue'
import AccountCog from 'vue-material-design-icons/AccountCog.vue'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import {
	NcAvatar,
	NcActions,
	NcActionButton,
	NcActionSeparator,
	NcDialog,
	NcButton,
} from '@nextcloud/vue'

export default {
	name: 'EmployeeDetails',
	components: {
		EmpleadoTab,
		PersonalTab,
		NotasTab,
		FilesTab,
		CropperDialog,
		VueTabs,
		VTab,
		AccountEdit,
		AccountCog,
		NcAvatar,
		NcActions,
		NcActionButton,
		NcActionSeparator,
		NcDialog,
		NcButton,
	},
	inject: ['configuraciones'],
	props: {
		data: {
			type: Object,
			default: () => ({}),
		},
		empleadosProp: {
			type: Array,
			default: () => [],
		},
	},
	data() {
		return {
			show: false,
			automatic_save_note: this.configuraciones.automatic_save_note,
			Empleados: [],
			showDeactiveUserDialog: false,
			SelectedEmpleado: null,
			showCropper: false,
			previewUrl: null,
			avatarKey: 0,
			avatarVersion: 0,
			buttons: [
				{ label: this.t('empleados', 'OK'), type: 'primary', callback: () => this.DeactiveUser() },
			],
		}
	},
	computed: {
		avatarUrl() {
			return generateUrl(`/avatar/${this.data.uid}/512?v=${this.avatarVersion}`)
		},

		activeEmployees() {
			return this.empleadosProp.filter((empleado) => {
				return empleado.estado === 1
					|| empleado.estado === '1'
					|| empleado.enabled === true
					|| empleado.disabled === false
			}).length
		},

		inactiveEmployees() {
			return this.empleadosProp.filter((empleado) => {
				return empleado.estado === 0
					|| empleado.estado === '0'
					|| empleado.enabled === false
					|| empleado.disabled === true
			}).length
		},
	},
	mounted() {
		this.$bus.on('show', (data) => {
			this.show = data
		})

		if (this.automatic_save_note === undefined || this.automatic_save_note === null) {
			this.automatic_save_note = 'true'
		}
	},
	methods: {
		t,

		refreshAvatar() {
			this.avatarKey++
		},
		getAvatarUrl(uid) {
			const size = 512
			const timestamp = Date.now()
			return generateUrl(`/avatar/${uid}/${size}`) + `?v=${timestamp}`
		},
		showEdit() {
			this.show = !this.show
			if (this.show) this.$bus.emit('getall')
		},
		DeactiveUserDialog(IdEmpleado) {
			this.showDeactiveUserDialog = true
			this.SelectedEmpleado = IdEmpleado
		},
		async DeactiveUser() {
			try {
				await axios.post(generateUrl('/apps/empleados/DesactivarEmpleado'), {
					id_empleados: this.SelectedEmpleado,
				})
				this.$bus.emit('getall')
				this.$bus.emit('send-data', {})
			} catch (err) {
				showError(this.t('empleados', 'An exception has occurred [03] [{error}]', { error: String(err) }))
			}
		},
		onFileSelected(event) {
			const file = event.target.files[0]
			if (!file) return
			this.previewUrl = URL.createObjectURL(file)
			this.showCropper = true
		},
		async handleCroppedImage(blob) {
			const formData = new FormData()
			formData.append('avatar', blob)
			formData.append('uid', this.data.uid)
			try {
				const response = await axios.post(generateUrl('/apps/empleados/uploadAvatar'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				})
				this.avatarVersion = response.data.version
				this.refreshAvatar()
			} catch (err) {
				showError(this.t('empleados', 'Error uploading image.'))
			}
		},
		handleCropperError(msg) {
			showError(msg)
		},
	},
}
</script>

<style lang="scss" scoped>
.envelope {
	.app-content-list-item-icon { height: 40px; }
	&__subtitle {
		display: flex;
		gap: 4px;
		&__subject {
			color: var(--color-main-text);
			line-height: 130%;
			overflow: hidden;
			text-overflow: ellipsis;
		}
	}
}
.list-item-style { list-style: none; }
.contacts-list__item-wrapper {
	&[draggable='true'] .avatardiv * { cursor: move !important; }
	&[draggable='false'] .avatardiv * { cursor: not-allowed !important; }
}
#emptycontent, .emptycontent { margin-top: 2vh; }
.container { padding: 20px 15px 0px 6px; align-items: center; }
.container-progress { margin: 20px 30% 0; align-items: center; }
.wrapper { display: flex; gap: 4px; align-items: flex-end; flex-wrap: wrap; margin: 0 5%; }
.contacts-list { max-height: calc(100vh - var(--header-height) - 48px); overflow: auto; }
.contacts-list__header { min-height: 48px; }
.margin-left-icon { margin-right: 20px; }
.button-container-profile { margin-top: -30px; position: absolute; right: 30px; z-index: 9999; }
.well { margin: 0 auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
.user-card { display: flex; align-items: center; padding: 0 10px 10px; }
.info { display: flex; flex-direction: column; }
.info h2 { margin: 0; width: 100%; }
.card-container { display: flex; justify-content: center; align-items: center; }
.avatar { padding-right: 10px; }
.file-input { display: none; }
.employee-empty-state {
	min-height: calc(100vh - var(--header-height) - 80px);
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 32px;
}

.employee-empty-card {
	width: min(720px, 100%);
	padding: 36px;
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 16px rgba(0, 0, 0, 0.08);
	text-align: center;
}

.employee-empty-image {
	width: 150px;
	margin-bottom: 16px;
	opacity: 0.95;
}

.employee-empty-card h2 {
	margin: 0 0 8px;
	font-size: 24px;
	font-weight: 700;
	color: var(--color-main-text);
}

.employee-empty-description {
	max-width: 520px;
	margin: 0 auto 24px;
	color: var(--color-text-maxcontrast);
	line-height: 1.5;
}

.employee-empty-stats {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
	margin: 24px 0;
}

.employee-empty-stat {
	padding: 16px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
}

.employee-empty-stat strong {
	display: block;
	font-size: 26px;
	font-weight: 700;
	color: var(--color-primary-element);
}

.employee-empty-stat span {
	display: block;
	margin-top: 4px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.employee-empty-actions {
	display: flex;
	justify-content: center;
	gap: 12px;
	flex-wrap: wrap;
	margin-top: 20px;
}

@media (max-width: 700px) {
	.employee-empty-state {
		align-items: flex-start;
		padding: 20px 12px;
	}

	.employee-empty-card {
		padding: 24px 16px;
	}

	.employee-empty-stats {
		grid-template-columns: 1fr;
	}
}
</style>
