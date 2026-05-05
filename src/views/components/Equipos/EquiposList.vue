<template id="EmployeeList">
	<NcAppContent v-if="loading" :name="t('empleados', 'Loading')">
		<NcEmptyContent class="empty-content" :name="t('empleados', 'Loading')">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>
	</NcAppContent>

	<NcAppContent v-else :name="t('empleados', 'Loading')">
		<!-- contacts list -->
		<template #list>
			<EquiposFullList
				:list="EquiposList"
				:contacts="Equipos"
				:search-query="searchQuery"
				:reload-bus="reloadBus" />
		</template>

		<!-- main contacts details -->
		<EquiposDetails
			:data="data_Equipos"
			:people-area="peopleArea" />
	</NcAppContent>
</template>

<script>
// agregados
import EquiposFullList from './EquiposFullList.vue'
import EquiposDetails from './perfil/EquiposDetails.vue'

import { showError /* showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import mitt from 'mitt'
import { translate as t } from '@nextcloud/l10n'

import {
	NcEmptyContent,
	NcAppContent,
	NcLoadingIcon,
} from '@nextcloud/vue'

export default {
	name: 'EquiposList',
	components: {
		EquiposFullList,
		NcEmptyContent,
		NcAppContent,
		NcLoadingIcon,
		EquiposDetails,
	},

	data() {
		return {
			loading: true,
			Equipos: [],
			searchQuery: '',
			reloadBus: mitt(),
			EquiposList: [],
			data_Equipos: {},
			peopleArea: {},
		}
	},

	async mounted() {
		this.getall()

		this.$root.$on('send-data-equipos', (data) => {
			this.data_Equipos = data || {}
			if (data && data.Id_equipo) {
				this.getallequipo(data.Id_equipo)
			} else {
				this.peopleArea = {}
			}
		})

		this.$root.$on('delete-Equipos', () => {
			this.getall()
		})

		this.$root.$on('reload', () => {
			this.getall()
		})
	},

	methods: {
		// Exponer t a la plantilla
		t,

		async getallequipo(equipo) {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetEmpleadosEquipo/' + encodeURIComponent(equipo)))
				this.peopleArea = response?.data?.ocs?.data
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		async getall() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetEquiposList'))
				this.Equipos = response?.data?.ocs?.data
				this.loading = false
			} catch (err) {
				this.loading = false
				showError(t('empleados', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
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
