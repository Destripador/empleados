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
				<NcNoteCard v-if="selected_user" type="warning" heading="ATENCION">
					<p>Si decide cambiar el usuario gestor de archivos cuando ya haya sido asignado, puede generar perdida de archivos, considere generar un respaldo antes de realizar este proceso</p>
				</NcNoteCard>
				<NcSelect v-model="selected_user"
					input-label="Usuario gestor de datos"
					:options="options"
					:user-select="true" />

				<NcButton
					aria-label="Aplicar cambios"
					type="primary"
					@click="saveGestor">
					Aplicar cambios
				</NcButton>
			</div>
			<br>
			<div class="grid">
				<NcCheckboxRadioSwitch
					:checked="guardado_notas"
					type="switch"
					@update:checked="onChangeGuardadoNotas">
					Guardado automatico de notas
				</NcCheckboxRadioSwitch>
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
import { /* NcActions, NcActionButton, */ NcButton, NcLoadingIcon, NcSelect, NcNoteCard, NcCheckboxRadioSwitch /*, NcAvatar */ } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'ListSettings',
	components: {
		// NcAvatar,
		// NcActions,
		// NcActionButton,
		NcNoteCard,
		NcLoadingIcon,
		AccountGroup,
		NcSelect,
		NcButton,
		NcCheckboxRadioSwitch,
		// Delete,
		// Plus,
	},

	data() {
		return {
			loading: true,
			configuraciones: [],
			options: [],
			selected_user: null,
			guardado_notas: false,
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
							// usuarios nextcloud
							this.options = response.data.Users

							// Gestor de datos
							this.selected_user = response.data.Gestor_actual

							// Guardado de notas automatico
							this.guardado_notas = (response.data.Guardado_notas === 'true')

							// Desactivar animacion de guardado
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

		async saveGestor() {
			if (this.selected_user.id == null) {
				showError('No has realizado ningun cambio')
			} else {
				try {
					await axios.post(generateUrl('/apps/empleados/ActualizarGestor'),
						{
							id_gestor: this.selected_user.id,
						})
						.then(
							(response) => {
								showSuccess('Gestor actualizado')
							},
							(err) => {
								showError(err)
							},
						)
				} catch (err) {
					showError(t('empleados', 'Se ha producido una excepcion [03] [' + err + ']'))
				}
			}
		},

		async onChangeGuardadoNotas() {
			this.guardado_notas = !this.guardado_notas
			try {
				await axios.post(generateUrl('/apps/empleados/ActualizarConfiguracion'),
					{
						id_configuracion: 'automatic_save_note',
						data: this.guardado_notas.toString(),
					})
					.then(
						(response) => {
							showSuccess('Configuracion actualizada')
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [' + err + ']'))
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
