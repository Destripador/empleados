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
			<areaslist
				:list="areasList"
				:contacts="Areas"
				:search-query="searchQuery"
				:reload-bus="reloadBus" />
		</template>

		<!-- main contacts details -->
		<!--ContactDetails :data="data_empleado" /-->
	</NcAppContent>
</template>

<script>
// agregados
import areaslist from './AreasFullList.vue'
// import ContactDetails from './ContactDetails.vue'

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
	name: 'AreasList',
	components: {
		areaslist,
		NcEmptyContent,
		NcAppContent,
		NcLoadingIcon,
		// ContactsList,
		// ContactDetails,
	},

	data() {
		return {
			loading: true,
			Areas: [],
			searchQuery: '',
			reloadBus: mitt(),
			areasList: [],
			data_areas: {},
		}
	},

	async mounted() {
		this.getall()
		this.$root.$on('send-data-areas', (data) => {
			this.data_areas = data
		})
	},

	methods: {
		async getall() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetAreasList'))
					.then(
						(response) => {
							this.Areas = response.data
							// eslint-disable-next-line no-console
							console.log(response.data)
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
