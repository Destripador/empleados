<template>
	<div class="contacts-list__item-wrapper">
		<!-- Empty state -->
		<div v-if="Object.keys(data).length === 0">
			<div class="emptycontent">
				<div v-if="Object.keys(data).length === 0" class="employee-empty-state">
					<OrganigramaNetwork />
				</div>
			</div>
		</div>

		<!-- Employee info -->
		<div v-else class="container">
			<div class="mobile-back-bar">
				<button
					type="button"
					class="mobile-back-btn"
					:aria-label="t('empleados', 'Back to list')"
					@click="goBackToList">
					<ArrowLeft :size="20" />
					<span>{{ t('empleados', 'Back') }}</span>
				</button>
			</div>
			<div class="profile-toolbar">
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
						<h2 v-if="data.mail" class="info-email">
							{{ data.mail }}
						</h2>
					</div>
				</div>
			</div>

			<!-- Tabs (PC) -->
			<div v-if="!isMobile" class="center">
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

					<VTab :title="t('empleados', 'Personal')">
						<PersonalTab :data="data" :show="show" :empleados="Empleados" />
					</VTab>

					<VTab :title="t('empleados', 'Boarding')">
						<BoardingTab :data="data" />
					</VTab>

					<VTab :title="t('empleados', 'Notes')">
						<NotasTab
							:data="data"
							:show="show"
							:empleados="Empleados"
							:automaticsave="automatic_save_note" />
					</VTab>

					<VTab :title="t('empleados', 'Files')">
						<FilesTab :data="data" :show="show" :empleados="Empleados" />
					</VTab>
				</VueTabs>
			</div>

			<!-- Tab selector (móvil / pantalla angosta) -->
			<div v-else>
				<div class="tabs-select-wrapper">
					<select
						v-model="activeTab"
						class="tabs-select"
						@change="$event.target.blur()">
						<option v-for="tab in tabsList" :key="tab.value" :value="tab.value">
							{{ tab.label }}
						</option>
					</select>
				</div>

				<div class="tab-content">
					<EmpleadoTab
						v-if="activeTab === 'empleado'"
						:data="data"
						:show="show"
						:empleados="empleadosProp"
						:automaticsave="automatic_save_note" />

					<PersonalTab
						v-else-if="activeTab === 'personal'"
						:data="data"
						:show="show"
						:empleados="Empleados" />

					<BoardingTab
						v-else-if="activeTab === 'boarding'"
						:data="data" />

					<NotasTab
						v-else-if="activeTab === 'notas'"
						:data="data"
						:show="show"
						:empleados="Empleados"
						:automaticsave="automatic_save_note" />

					<FilesTab
						v-else-if="activeTab === 'files'"
						:data="data"
						:show="show"
						:empleados="Empleados" />
				</div>
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
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import OrganigramaNetwork from './Organigrama/OrganigramaNetwork.vue'
import EmpleadoTab from './Tabs/EmpleadoTab.vue'
import BoardingTab from './Tabs/BoardingTab.vue'
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
		BoardingTab,
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
		OrganigramaNetwork,
		ArrowLeft,
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
			// Tab activo del select (solo se usa en móvil). 'empleado' por default.
			activeTab: 'empleado',
			// Igual que en EmployeeList.vue: decide si mostramos los tabs
			// completos (PC) o el select de un solo recuadrito (móvil).
			isMobile: typeof window !== 'undefined' && window.matchMedia
				? window.matchMedia('(max-width: 600px)').matches
				: false,
			mobileMediaQuery: null,
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

		// Lista única de tabs, usada tanto por los botones (PC) como
		// por el <select> (móvil), para no duplicar las etiquetas.
		tabsList() {
			return [
				{ value: 'empleado', label: this.t('empleados', 'Employee') },
				{ value: 'personal', label: this.t('empleados', 'Personal') },
				{ value: 'boarding', label: this.t('empleados', 'Boarding') },
				{ value: 'notas', label: this.t('empleados', 'Notes') },
				{ value: 'files', label: this.t('empleados', 'Files') },
			]
		},
	},
	watch: {
		// Al cambiar de empleado, siempre volvemos a la pestaña "Employee".
		data() {
			this.activeTab = 'empleado'
		},
	},
	mounted() {
		this.$bus.on('show', (data) => {
			this.show = data
		})

		if (this.automatic_save_note === undefined || this.automatic_save_note === null) {
			this.automatic_save_note = 'true'
		}

		// Mantiene isMobile actualizado si se gira el celular o se
		// redimensiona la ventana (mismo breakpoint de 600px usado en
		// el resto de ajustes "móvil" de este componente).
		this.mobileMediaQuery = window.matchMedia('(max-width: 600px)')
		this._onMobileChange = (event) => {
			this.isMobile = event.matches
		}
		if (this.mobileMediaQuery.addEventListener) {
			this.mobileMediaQuery.addEventListener('change', this._onMobileChange)
		} else {
			this.mobileMediaQuery.addListener(this._onMobileChange)
		}
	},

	beforeDestroy() {
		if (this.mobileMediaQuery) {
			if (this.mobileMediaQuery.removeEventListener) {
				this.mobileMediaQuery.removeEventListener('change', this._onMobileChange)
			} else {
				this.mobileMediaQuery.removeListener(this._onMobileChange)
			}
		}
	},
	methods: {
		t,

		// Notifica a EmployeeList.vue que debe volver a mostrar la lista
		// (esto solo tiene efecto visual en móvil).
		goBackToList() {
			this.$bus.emit('back-to-list')
		},

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

.container {
	box-sizing: border-box;
	width: 100%;
	max-width: 100%;
	padding: 20px;
	overflow-x: hidden;
}

.container-progress { margin: 20px 30% 0; align-items: center; }
.wrapper { display: flex; gap: 4px; align-items: flex-end; flex-wrap: wrap; margin: 0 5%; }
.contacts-list { max-height: calc(100vh - var(--header-height) - 48px); overflow: auto; }
.contacts-list__header { min-height: 48px; }
.margin-left-icon { margin-right: 20px; }

// Botón de editar: siempre pegado a la esquina superior derecha,
// tanto en escritorio como en móvil.
.profile-toolbar {
	display: flex;
	justify-content: flex-end;
	margin-bottom: 8px;
}

.well { margin: 0 auto; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }

// En desktop el avatar y el nombre van en fila; en móvil se acomoda
// centrado y en columna para que no se encimen con nombres largos.
.card-container {
	display: flex;
	justify-content: center;
	align-items: center;
	width: 100%;
}

.user-card {
	display: flex;
	align-items: center;
	gap: 16px;
	width: 100%;
	max-width: 480px;
	padding: 0 10px 10px;
	box-sizing: border-box;
}

.info {
	display: flex;
	flex-direction: column;
	min-width: 0;
}

.info h2 {
	margin: 0;
	width: 100%;
	overflow-wrap: anywhere;
}

.info-email {
	font-size: 14px;
	font-weight: 400;
	color: var(--color-text-maxcontrast);
}

.avatar {
	display: flex;
	flex-direction: column;
	align-items: center;
	flex-shrink: 0;
	padding-right: 10px;
}

.file-input { display: none; }

.employee-empty-card {
	width: min(720px, 100%);
	padding: 36px;
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 16px rgba(0, 0, 0, 0.08);
	text-align: center;
	box-sizing: border-box;
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

.employee-empty-state {
	height: calc(100vh - var(--header-height) - 80px);
	padding: 16px;
	box-sizing: border-box;
	overflow-y: auto;
}

.mobile-back-bar {
	display: none;
}

.mobile-back-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	width: fit-content;
	min-width: 0;
	height: auto;
	box-sizing: border-box;
	padding: 8px 14px;
	border: 1px solid var(--color-border);
	border-radius: 20px;
	background: #e6eef3;
	color: #012f3b;
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
}

@media (max-width: 900px) {
	.mobile-back-bar {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		width: 100%;
		height: 52px;
		padding: 8px 10px 12px;
		box-sizing: border-box;
	}

	.mobile-back-btn {
		display: inline-flex;
		flex: 0 0 auto;
		width: fit-content;
		min-width: 0;
		max-width: max-content;
		height: auto;
		margin: 0;
		padding: 8px 14px;
		border-radius: 10px;
		background: #e6eef3;

		font-weight: 680;
		font-size: 15px;
	}
}

::v-deep(.nav-tabs) {
	display: flex;
	flex-wrap: nowrap;
	overflow-x: auto;
	overflow-y: hidden;
	-webkit-overflow-scrolling: touch;
	scrollbar-width: thin;
	white-space: nowrap;
}

::v-deep(.nav-tabs > li) {
	flex: 0 0 auto;
}

::v-deep(.nav-tabs > li > a) {
	padding: 10px 14px;
	font-size: 13px;
	white-space: nowrap;
}

// Selector de tabs (móvil / pantalla angosta): un solo recuadrito
// en vez de las 5 pestañas. Solo se renderiza cuando isMobile es true.
.tabs-select-wrapper {
	display: flex;
	justify-content: center;
	margin: 4px 0 16px;
}

.tabs-select {
	width: min(260px, 100%);
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 14px;
	font-weight: 600;
	cursor: pointer;
}

.tabs-select:focus {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 1px;
}

.tab-content {
	width: 100%;
}

@media (max-width: 600px) {
	.container {
		padding: 12px 10px 20px;
	}

	.user-card {
		flex-direction: column;
		text-align: center;
		gap: 10px;
		padding: 0 0 10px;
	}

	.info {
		align-items: center;
	}

	.info h2 {
		font-size: 19px;
		text-align: center;
	}

	.employee-empty-card {
		padding: 20px 14px;
	}

	.employee-empty-stats {
		grid-template-columns: 1fr;
	}

	.employee-empty-state {
		padding: 10px;
	}
}
</style>
