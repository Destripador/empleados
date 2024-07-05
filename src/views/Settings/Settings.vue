<!-- eslint-disable vue/require-v-for-key -->
<template>
	<div v-if="loading">
		<div class="center-screen" style="background-color: #fff;">
			<NcLoadingIcon :size="64" appearance="dark" name="Loading on light background" />
		</div>
	</div>
	<div v-else id="admin">
		<div>
			<h2 class="board-title">
				<AccountGroup :size="20"
					decorative
					class="icon" />
				<span>Empleados</span>
			</h2>
		</div>

		<div v-if="Empleados.length > 0" class="container">
			<div style="display: flex; gap: 12px; flex: 1">
				<div>
					<h2 class="titles">
						<span>Empleados activos</span>
					</h2>
				</div>
			</div>
			<table class="grid">
				<tr>
					<th class="header__cell header__cell--avatar">
						&nbsp;
					</th>
					<th>{{ t('empleados', 'Nombre') }}</th>
					<th>{{ t('empleados', 'Opciones') }}</th>
				</tr>
				<tr v-for="(item, index) in Empleados" v-bind="$attrs">
					<td class="row__cell row__cell--avatar">
						<NcAvatar :user="item.uid"
							:display-name="item.displayname"
							:show-user-status-compact="false"
							:show-user-status="false" />
					</td>

					<td>
						{{ item.displayname }}
					</td>

					<td>
						<NcActions>
							<NcActionButton @click="EliminarUser(index)">
								<template #icon>
									<Delete :size="20" />
								</template>
								Eliminar
							</NcActionButton>
						</NcActions>
					</td>
				</tr>
			</table>
		</div>
		<br>
		<div v-if="Usuarios.length > 0" class="container">
			<div style="display: flex; gap: 12px; flex: 1">
				<div>
					<h2 class="titles">
						<span>Usuarios sin cuenta de empleado</span>
					</h2>
				</div>
			</div>
			<table class="grid">
				<tr>
					<th class="header__cell header__cell--avatar">
						&nbsp;
					</th>
					<th>{{ t('empleados', 'Nombre') }}</th>
					<th>{{ t('empleados', 'Opciones') }}</th>
				</tr>
				<tr v-for="(item, index) in Usuarios" v-bind="$attrs">
					<td class="row__cell row__cell--avatar">
						<NcAvatar :user="item.uid"
							:display-name="item.displayname"
							:show-user-status-compact="false"
							:show-user-status="false" />
					</td>

					<td>
						{{ item.displayname }}
					</td>

					<td>
						<NcActions>
							<NcActionButton @click="ActivarUser(index)">
								<template #icon>
									<Plus :size="20" />
								</template>
								Activar
							</NcActionButton>
						</NcActions>
					</td>
				</tr>
			</table>
		</div>
	</div>
</template>

<script>
// iconos
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
// import WalletPlus from 'vue-material-design-icons/WalletPlus'
// import Cog from 'vue-material-design-icons/Cog'
// import Check from 'vue-material-design-icons/Check'

// imports
import { NcActions, NcActionButton, NcLoadingIcon, NcAvatar } from '@nextcloud/vue'
import { showError /* showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'Settings',
	components: {
		NcAvatar,
		NcActions,
		NcActionButton,
		NcLoadingIcon,
		AccountGroup,
		Delete,
		Plus,
	},

	data() {
		return {
			loading: true,
			Empleados: [],
			Usuarios: [],
			map: {},
		}
	},

	async mounted() {
		this.getall()
	},

	methods: {
		async getall() {
			this.loading = false
			try {
				await axios.get(generateUrl('/apps/empleados/GetUserLists'))
					.then(
						(response) => {
							this.Usuarios = []
							this.Empleados = []
							this.map = {}

							for (let i = 0; i < response.data.Empleados.length; i++) {
								this.map[response.data.Empleados[i].Id_user] = true
								this.Empleados.push(response.data.Empleados[i])
							}

							// Recorrer el segundo array y verificar si sus elementos existen en el objeto
							for (let i = 0; i < response.data.Users.length; i++) {
								if (!this.map[response.data.Users[i].uid]) {
									this.Usuarios.push(response.data.Users[i])
								}
							}

							this.loading = false
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [' + err + ']'))
			}
		},

		async EliminarUser(index) {
			try {
				await axios.post(generateUrl('/apps/empleados/EliminarEmpleado'),
					{
						id_empleados: this.Empleados[index].Id_empleados,
					})
					.then(
						(response) => {
							this.getall()
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [' + err + ']'))
			}
		},

		async ActivarUser(index) {
			try {
				await axios.post(generateUrl('/apps/empleados/ActivarEmpleado'),
					{
						id_user: this.Usuarios[index].uid,
					})
					.then(
						(response) => {
							this.getall()
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [02] [' + err + ']'))
			}
		},
	},
}
</script>

<style>

.board-title {
	padding-left: 20px;
	margin-right: 10px;
	margin-top: 14px;
	font-size: 25px;
	display: flex;
	align-items: center;
	font-weight: bold;
	.icon {
		margin-right: 8px;
	}
}

.center-screen {
  display: flex;
  justify-content: center;
  align-items: center;
  text-align: center;
  min-height: 100vh;
}

.titles {
	margin-right: 10px;
	margin-top: 14px;
	font-size: 17px;
	display: flex;
	align-items: center;
	.icon {
		margin-right: 8px;
	}
}
.container {
	padding-left: 20px;
	padding-right: 20px;
}

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
</style>
