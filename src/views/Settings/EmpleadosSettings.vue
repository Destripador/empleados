<!-- eslint-disable vue/require-v-for-key -->
<template>
	<div>
		<!-- Loading -->
		<div v-if="loading">
			<div class="center-screen" style="background-color: #fff;">
				<NcLoadingIcon :size="64" appearance="dark" name="Loading on light background" />
			</div>
		</div>

		<!-- Main -->
		<div v-else id="admin">
			<div>
				<h2 class="board-title">
					<AccountGroup :size="20" decorative class="icon" />
					<span>{{ t('empleados', 'Employees') }}</span>
				</h2>
			</div>

			<VueTabs>
				<!-- Active employees -->
				<VTab :title="t('empleados', 'Active employees')">
					<div v-if="Empleados.length > 0" class="container" style="max-height:  calc(80vh - 4rem); overflow-y: auto;">
						<table class="grid">
							<tr>
								<th class="header__cell header__cell--avatar">
									&nbsp;
								</th>
								<th>{{ t('empleados', 'Name') }}</th>
								<th>{{ t('empleados', 'Options') }}</th>
							</tr>
							<tr v-for="(item, index) in Empleados" v-bind="$attrs">
								<td class="row__cell row__cell--avatar">
									<NcAvatar
										:user="item.uid"
										:display-name="item.displayname"
										:show-user-status-compact="false"
										:show-user-status="false" />
								</td>
								<td v-if="item.displayname">
									{{ item.displayname }}
								</td>
								<td v-else>
									{{ item.uid }}
								</td>
								<td>
									<NcActions>
										<NcActionButton @click="DeactiveUserDialog(index, item.displayname)">
											<template #icon>
												<AccountOff :size="20" />
											</template>
											{{ t('empleados', 'Disable account') }}
										</NcActionButton>
									</NcActions>
								</td>
							</tr>
						</table>
					</div>
					<div v-else class="container">
						<br>
						<NcEmptyContent :name="t('empleados', 'No users yet')">
							<template #icon>
								<AccountOff :size="20" />
							</template>
						</NcEmptyContent>
					</div>
				</VTab>

				<!-- Deactivated employees -->
				<VTab :title="t('empleados', 'Deactivated employees')">
					<div v-if="Desactivados.length > 0" class="container" style="max-height: calc(80vh - 4rem); overflow-y: auto;">
						<table class="grid">
							<tr>
								<th class="header__cell header__cell--avatar">
									&nbsp;
								</th>
								<th>{{ t('empleados', 'Name') }}</th>
								<th>{{ t('empleados', 'Options') }}</th>
							</tr>
							<tr v-for="(item, index) in Desactivados" v-bind="$attrs">
								<td class="row__cell row__cell--avatar">
									<NcAvatar
										:user="item.uid"
										:display-name="item.displayname"
										:show-user-status-compact="false"
										:show-user-status="false" />
								</td>
								<td v-if="item.displayname">
									{{ item.displayname }}
								</td>
								<td v-else>
									{{ item.uid }}
								</td>
								<td>
									<NcActions>
										<NcActionButton close-after-click @click="ActivarUsuario(index)">
											<template #icon>
												<AccountPlus :size="20" />
											</template>
											{{ t('empleados', 'Activate') }}
										</NcActionButton>
										<NcActionButton close-after-click @click="EliminarUserDialog(index)">
											<template #icon>
												<Delete :size="20" />
											</template>
											{{ t('empleados', 'Delete') }}
										</NcActionButton>
									</NcActions>
								</td>
							</tr>
						</table>
					</div>
					<div v-else class="container">
						<br>
						<NcEmptyContent :name="t('empleados', 'No users yet')">
							<template #icon>
								<AccountOff :size="20" />
							</template>
						</NcEmptyContent>
					</div>
				</VTab>

				<!-- Users without employee record -->
				<VTab :title="t('empleados', 'Users without employee record')">
					<div v-if="loadingEmployees" class="loader-settings">
						<NcLoadingIcon :size="70" />
					</div>
					<div v-else>
						<div v-if="Usuarios.length > 0" class="container" style="max-height: calc(80vh - 4rem); overflow-y: auto;">
							<table class="grid">
								<tr>
									<th class="header__cell header__cell--avatar">
										&nbsp;
									</th>
									<th>{{ t('empleados', 'Name') }}</th>
									<th>{{ t('empleados', 'Options') }}</th>
								</tr>
								<tr v-for="(item, index) in Usuarios" v-bind="$attrs">
									<td class="row__cell row__cell--avatar">
										<NcAvatar
											:user="item.uid"
											:display-name="item.displayname"
											:show-user-status-compact="false"
											:show-user-status="false" />
									</td>
									<td>{{ JSON.parse(item.data).displayname.value }}</td>
									<td>
										<NcActions>
											<NcActionButton @click="ActivarUser(index)">
												<template #icon>
													<Plus :size="20" />
												</template>
												{{ t('empleados', 'Activate') }}
											</NcActionButton>
										</NcActions>
									</td>
								</tr>
							</table>
						</div>
					</div>
				</VTab>
				<!-- Permissions -->
				<VTab :title="t('empleados', 'Permissions')">
					<div class="container permisos-container">
						<div class="permisos-card">
							<h3>{{ t('empleados', 'User permissions') }}</h3>

							<p class="permisos-help">
								{{ t('empleados', 'Assign module permissions using controlled Nextcloud groups.') }}
							</p>

							<div class="permisos-grid">
								<label>
									{{ t('empleados', 'User') }}
									<select v-model="selectedPermisosUid" @change="loadPermisosUsuario">
										<option value="">
											{{ t('empleados', 'Select user') }}
										</option>

										<option
											v-for="user in permisosUsuarios"
											:key="user.uid"
											:value="user.uid">
											{{ user.displayname || user.uid }}
										</option>
									</select>
								</label>
							</div>

							<div v-if="selectedPermisosUid" class="permisos-groups">
								<h4>{{ t('empleados', 'Allowed groups') }}</h4>

								<NcCheckboxRadioSwitch
									v-for="group in permisosGrupos"
									:key="group.id"
									:checked="selectedPermisosGroups.includes(group.id)"
									type="switch"
									@update:checked="togglePermisoGroup(group.id)">
									{{ group.label }}
								</NcCheckboxRadioSwitch>

								<div class="permisos-actions">
									<NcButton
										type="primary"
										:disabled="loadingPermisos"
										@click="savePermisosUsuario">
										{{ t('empleados', 'Save permissions') }}
									</NcButton>
								</div>
							</div>
						</div>
					</div>
				</VTab>
			</VueTabs>
		</div>

		<!-- Dialog: deactivate -->
		<NcDialog
			:open.sync="showDeactiveUserDialog"
			:name="t('empleados', 'Confirmation')"
			:message="t('empleados', 'Are you sure you want to disable the account of {name}?', { name: selected.name || '' })"
			:buttons="buttons" />

		<!-- Dialog: delete -->
		<NcDialog
			:open.sync="showEliminarUserDialog"
			:name="t('empleados', 'Are you sure you want to delete?')"
			:message="t('empleados', 'This action will delete all employee information')"
			:buttons="ButtonsEliminarUser" />
	</div>
</template>

<script>
// Icons
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import AccountOff from 'vue-material-design-icons/AccountOff.vue'
import AccountPlus from 'vue-material-design-icons/AccountPlus.vue'

// Components & utils
import {
	NcActions,
	NcActionButton,
	NcLoadingIcon,
	NcAvatar,
	NcDialog,
	NcEmptyContent,
	NcButton,
	NcCheckboxRadioSwitch,
} from '@nextcloud/vue'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { VueTabs, VTab } from 'vue-nav-tabs/dist/vue-tabs.js'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'EmpleadosSettings',
	components: {
		NcAvatar,
		NcActions,
		NcActionButton,
		NcLoadingIcon,
		AccountGroup,
		Delete,
		Plus,
		VueTabs,
		VTab,
		AccountOff,
		NcDialog,
		AccountPlus,
		NcEmptyContent,
		NcButton,
		NcCheckboxRadioSwitch,
	},

	data() {
		return {
			showDeactiveUserDialog: false,
			showEliminarUserDialog: false,
			selected: [],
			loading: true,
			Empleados: [],
			Usuarios: [],
			Desactivados: [],
			map: {},
			selectArray: [],
			ButtonsEliminarUser: [
				{
					label: t('empleados', 'OK'),
					type: 'primary',
					callback: () => { this.EliminarUser(this.selected.index) },
				},
			],
			buttons: [
				{
					label: t('empleados', 'OK'),
					type: 'primary',
					callback: () => { this.DeactiveUser(this.selected.index) },
				},
			],
			loadingEmployees: false,
			permisosUsuarios: [],
			permisosGrupos: [],
			selectedPermisosUid: '',
			selectedPermisosGroups: [],
			loadingPermisos: false,
		}
	},

	async mounted() {
		this.getall()
	},

	methods: {
		t,
		// Fetch all lists
		async getall() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetUserLists'))
					.then(
						(response) => {
							this.Usuarios = []
							this.Empleados = response?.data?.ocs?.data.Empleados
							this.Desactivados = response?.data?.ocs?.data.Desactivados

							this.map = {}

							response?.data?.ocs?.data.Empleados.forEach(empleado => {
								this.map[empleado.Id_user] = true
							})

							response?.data?.ocs?.data.Desactivados.forEach(empleado => {
								this.map[empleado.Id_user] = true
							})

							this.Usuarios = response?.data?.ocs?.data.Users.filter(user => !this.map[user.uid])

							this.buildPermisosUsuarios()
							this.loadPermisosGrupos()

							this.loading = false
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'An exception occurred [01] [{error}]', { error: String(err) }))
			}
		},

		DeactiveUserDialog(index, name) {
			this.selected.index = index
			this.selected.name = name
			this.showDeactiveUserDialog = true
		},

		EliminarUserDialog(index) {
			this.selected.index = index
			this.showEliminarUserDialog = true
		},

		async ActivarUsuario(index) {
			try {
				await axios.post(generateUrl('/apps/empleados/ActivarUsuario'), {
					id_empleados: this.Desactivados[index].Id_empleados,
				}).then(
					() => { this.getall() },
					(err) => { showError(err) },
				)
			} catch (err) {
				showError(t('empleados', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async EliminarUser(index) {
			this.showDeactiveUserDialog = false
			try {
				await axios.post(generateUrl('/apps/empleados/EliminarEmpleado'), {
					id_empleados: this.Desactivados[index].Id_empleados,
					id_user: this.Desactivados[index].Id_user,
				}).then(
					() => { this.getall() },
					(err) => { showError(err) },
				)
			} catch (err) {
				showError(t('empleados', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async DeactiveUser(index) {
			try {
				await axios.post(generateUrl('/apps/empleados/DesactivarEmpleado'), {
					id_empleados: this.Empleados[index].Id_empleados,
				}).then(
					() => { this.getall() },
					(err) => { showError(err) },
				)
			} catch (err) {
				showError(t('empleados', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async ActivarUser(index) {
			try {
				this.loadingEmployees = true
				await axios.post(generateUrl('/apps/empleados/ActivarEmpleado'), {
					id_user: this.Usuarios[index].uid,
				}).then(
					() => {
						this.getall()
						this.loadingEmployees = false
					 },
					(err) => {
						showError(err)
						this.loadingEmployees = false
					},
				)
			} catch (err) {
				showError(t('empleados', 'An exception occurred [02] [{error}]', { error: String(err) }))
			}
		},
		buildPermisosUsuarios() {
			const users = []

			this.Empleados.forEach(user => {
				users.push({
					uid: user.Id_user || user.uid,
					displayname: user.displayname || user.DisplayName || user.Id_user || user.uid,
				})
			})

			this.Desactivados.forEach(user => {
				users.push({
					uid: user.Id_user || user.uid,
					displayname: user.displayname || user.DisplayName || user.Id_user || user.uid,
				})
			})

			this.Usuarios.forEach(user => {
				let displayname = user.uid

				try {
					displayname = JSON.parse(user.data)?.displayname?.value || user.uid
				} catch (e) {
					displayname = user.displayname || user.uid
				}

				users.push({
					uid: user.uid,
					displayname,
				})
			})

			const seen = {}

			this.permisosUsuarios = users.filter(user => {
				if (!user.uid || seen[user.uid]) {
					return false
				}

				seen[user.uid] = true
				return true
			})
		},

		async loadPermisosGrupos() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/permisos/grupos'))
				const payload = this.getPayload(response)

				if (payload.status !== 'ok') {
					throw new Error(payload.message || 'No se pudieron cargar los grupos.')
				}

				this.permisosGrupos = payload.data
			} catch (err) {
				showError(t('empleados', 'Error loading permission groups: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		async loadPermisosUsuario() {
			if (!this.selectedPermisosUid) {
				this.selectedPermisosGroups = []
				return
			}

			this.loadingPermisos = true

			try {
				const response = await axios.get(
					generateUrl('/apps/empleados/permisos/usuario/{uid}', {
						uid: this.selectedPermisosUid,
					}),
				)

				const payload = this.getPayload(response)

				if (payload.status !== 'ok') {
					throw new Error(payload.message || 'No se pudieron cargar los permisos.')
				}

				this.selectedPermisosGroups = payload.data.groups || []
			} catch (err) {
				showError(t('empleados', 'Error loading user permissions: {error}', { error: String(err) }))
				console.error(err)
			} finally {
				this.loadingPermisos = false
			}
		},

		togglePermisoGroup(groupId) {
			if (this.selectedPermisosGroups.includes(groupId)) {
				this.selectedPermisosGroups = this.selectedPermisosGroups.filter(id => id !== groupId)
				return
			}

			this.selectedPermisosGroups.push(groupId)
		},

		async savePermisosUsuario() {
			if (!this.selectedPermisosUid) {
				showError(t('empleados', 'Select a user first'))
				return
			}

			this.loadingPermisos = true

			try {
				const response = await axios.post(
					generateUrl('/apps/empleados/permisos/usuario/{uid}', {
						uid: this.selectedPermisosUid,
					}),
					{
						groups: this.selectedPermisosGroups,
					},
				)

				const payload = this.getPayload(response)

				if (payload.status !== 'ok') {
					throw new Error(payload.message || 'No se pudieron guardar los permisos.')
				}

				this.selectedPermisosGroups = payload.data.groups || []
				showSuccess(t('empleados', 'Permissions updated'))
			} catch (err) {
				showError(t('empleados', 'Error saving permissions: {error}', { error: String(err) }))
				console.error(err)
			} finally {
				this.loadingPermisos = false
			}
		},
		getPayload(response) {
			return response?.data?.ocs?.data || response?.data
		},
	},
}
</script>

<style>
/* Board title */
.board-title {
	padding-left: 20px;
	margin-right: 10px;
	margin-top: 14px;
	font-size: 25px;
	display: flex;
	align-items: center;
	font-weight: bold;
}
.board-title .icon {
	margin-right: 8px;
}

/* Centered loading */
.center-screen {
  display: flex;
  justify-content: center;
  align-items: center;
  text-align: center;
  min-height: 100vh;
}

/* Subtitles */
.titles {
	margin-right: 10px;
	margin-top: 14px;
	font-size: 17px;
	display: flex;
	align-items: center;
}
.titles .icon {
	margin-right: 8px;
}

/* Container */
.container {
	padding-left: 20px;
	padding-right: 20px;
}

/* Table wrapper demo */
.rsg {
	padding-top: 16px;
	padding-bottom: 16px;
	border: 1px solid rgb(232, 232, 232);
	border-radius: 3px;
	display: flex;
	margin-left: 20px;
	margin-right: 20px;
	width: auto;
}
.permisos-container {
	padding-top: 20px;
}

.permisos-card {
	max-width: 720px;
	padding: 20px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.permisos-card h3 {
	margin-top: 0;
}

.permisos-help {
	opacity: .75;
	margin-bottom: 18px;
}

.permisos-grid {
	display: grid;
	grid-template-columns: minmax(240px, 1fr);
	gap: 12px;
	margin-bottom: 20px;
}

.permisos-grid label {
	display: flex;
	flex-direction: column;
	gap: 8px;
	font-weight: 600;
}

.permisos-grid select {
	min-height: 38px;
	padding: 8px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.permisos-groups {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.permisos-actions {
	margin-top: 18px;
	display: flex;
	justify-content: flex-end;
}
</style>
