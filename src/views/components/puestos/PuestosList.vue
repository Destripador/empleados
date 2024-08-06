<template id="EmployeeList">
	<NcAppContent v-if="loading" name="Loading">
		<NcEmptyContent class="empty-content" name="Cargando">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>
	</NcAppContent>
	<NcAppContent v-else name="Loading">
		<!-- contacts list -->
		<template #list>
			<PuestosFullList
				:list="puestosList"
				:contacts="Puestos"
				:search-query="searchQuery"
				:reload-bus="reloadBus" />
		</template>

		<!-- main contacts details -->
		<PuestosDetails :data="data_puestos" :people-area="peopleArea" />
	</NcAppContent>
</template>

<script>
// agregados
import PuestosFullList from './PuestosFullList.vue'
import PuestosDetails from './perfil/PuestosDetails.vue'

import { showError /* showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import mitt from 'mitt'

import {
	NcEmptyContent,
	NcAppContent,
	NcLoadingIcon,
} from '@nextcloud/vue'

export default {
	name: 'PuestosList',
	components: {
		PuestosFullList,
		NcEmptyContent,
		NcAppContent,
		NcLoadingIcon,
		PuestosDetails,
		// ContactsList,
	},

	data() {
		return {
			loading: true,
			Puestos: [],
			searchQuery: '',
			reloadBus: mitt(),
			puestosList: [],
			data_puestos: {},
			peopleArea: {},
		}
	},

	async mounted() {
		this.getall()
		this.$root.$on('send-data-puestos', (data) => {
			this.data_puestos = data
			this.getallpuesto(data.Id_puestos)
		})
		this.$root.$on('delete-puestos', (data) => {
			this.getall()
		})
		this.$root.$on('reload', () => {
			this.getall()
		})
	},

	methods: {
		async getallpuesto(puesto) {
			try {
				await axios.get(generateUrl('/apps/empleados/GetEmpleadosPuesto/' + puesto))
					.then(
						(response) => {
							this.peopleArea = response.data
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [' + err + ']'))
			}
		},

		async getall() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetPuestosList'))
					.then(
						(response) => {
							this.Puestos = response.data
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
