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
				<span>Configuraciones globales</span>
			</h2>
		</div>

		<div class="container">
			<div class="grid">
				<NcSelect v-model="selected_user"
					input-label="Usuario gestor de datos"
					:options="options"
					:user-select="true" />
			</div>
		</div>
	</div>
</template>

<script>
// iconos
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
// import Delete from 'vue-material-design-icons/Delete.vue'
// import Plus from 'vue-material-design-icons/Plus.vue'
// import WalletPlus from 'vue-material-design-icons/WalletPlus'
// import Cog from 'vue-material-design-icons/Cog'
// import Check from 'vue-material-design-icons/Check'

// imports
import { /* NcActions, NcActionButton, */ NcLoadingIcon, NcSelect /*, NcAvatar */ } from '@nextcloud/vue'
import { showError /* showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'ListSettings',
	components: {
		// NcAvatar,
		// NcActions,
		// NcActionButton,
		NcLoadingIcon,
		AccountGroup,
		NcSelect,
		// Delete,
		// Plus,
	},

	data() {
		return {
			loading: true,
			configuraciones: [],
			options: [],
			selected_user: null,
		}
	},

	async mounted() {
		this.getall()
	},

	methods: {
		async getall() {
			this.loading = false
			try {
				await axios.get(generateUrl('/apps/empleados/GetConfigurations'))
					.then(
						(response) => {

							// eslint-disable-next-line no-console
							console.log('DDDDDD: ', response.data.Configuraciones)

							this.options = response.data.Users
							this.selected_user = response.data.Configuraciones[0]
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
