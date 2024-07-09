<template id="content">
	<NcContent app-name="empleados">
		<navigator />
		<NcAppContent>
			<div>
				<h2 class="board-title">
					<AccountGroup :size="20" decorative class="icon" />
					<span>{{ t('empleados', 'Empleados') }}</span>
				</h2>
			</div>
			<div>
				<Vuetable ref="vuetable"
					:fields="FieldsEmpleados"
					:api-mode="false"
					:data="Empleados"
					data-path=""
					pagination-path="" />
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script>
// navigator
import navigator from './navigator/Sidenavigation.vue'
import Vuetable from 'vuetable-2'

// iconos
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'

import { showError /* showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

import {
	NcAppContent,
	NcContent,
} from '@nextcloud/vue'

export default {
	name: 'App',
	components: {
		navigator,
		Vuetable,
		NcAppContent,
		NcContent,
		AccountGroup,
	},

	data() {
		return {
			loading: true,
			Empleados: [],
			table: {
				tableWrapper: '',
				tableHeaderClass: 'fixed',
				tableBodyClass: 'vuetable-semantic-no-top fixed',
				tableClass: 'ui blue selectable unstackable celled table',
				loadingClass: 'loading',
				ascendingIcon: 'blue chevron up icon',
				descendingIcon: 'blue chevron down icon',
				ascendingClass: 'sorted-asc',
				descendingClass: 'sorted-desc',
				sortableIcon: 'grey sort icon',
				handleIcon: 'grey sidebar icon',
			},

			pagination: {
				wrapperClass: 'ui right floated pagination menu',
				activeClass: 'active large',
				disabledClass: 'disabled',
				pageClass: 'item',
				linkClass: 'icon item',
				paginationClass: 'ui bottom attached segment grid',
				paginationInfoClass: 'left floated left aligned six wide column',
				dropdownClass: 'ui search dropdown',
				icons: {
					first: 'angle double left icon',
					prev: 'left chevron icon',
					next: 'right chevron icon',
					last: 'angle double right icon',
				},
			},

			paginationInfo: {
				infoClass: 'left floated left aligned six wide column',
			},
			FieldsEmpleados:
			[
				/* {
				name: '',
				title: ''
				}, */
				{
					name: 'Id_empleados',
					title: 'ID',
				},
				{
					name: 'Id_user',
					title: 'NOMBRE USUARIO',
				},
				{
					name: 'Numero_empleado',
					title: 'NUMERO DE EMPLEADO',
				},
				{
					name: 'Ingreso',
					title: 'INGRESO',
				},
				{
					name: 'Correo_contacto',
					title: 'CORREO DE CONTACTO',
				},
				{
					name: 'Id_departamento',
					title: 'DEPARTAMENTO',
				},
				{
					name: 'Id_puesto',
					title: 'PUESTO',
				},
				{
					name: 'Id_gerente',
					title: '',
				},
				{
					name: 'Id_socio',
					title: '',
				},
				{
					name: 'Fondo_clave',
					title: '',
				},
				{
					name: 'Numero_cuenta',
					title: '',
				},
				{
					name: 'Equipo_asignado',
					title: '',
				},
				{
					name: 'Sueldo',
					title: '',
				},
				{
					name: 'Fecha_nacimiento',
					title: '',
				},
				{
					name: 'Estado',
					title: '',
				},
			],
		}
	},

	async mounted() {
		this.getall()
	},

	methods: {
		async getall() {
			this.loading = false
			try {
				await axios.get(generateUrl('/apps/empleados/GetEmpleadosList'))
					.then(
						(response) => {
							this.Empleados = response.data.Empleados

							// eslint-disable-next-line no-console
							console.log(response.data.Empleados)

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
	},
}
</script>

<style scoped lang="scss">
	.container {
		padding-left: 60px;
	}
	.board-title {
		padding-left: 60px;
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
</style>
