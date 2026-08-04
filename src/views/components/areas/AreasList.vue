<template id="EmployeeList">
	<NcAppContent v-if="loading" name="Loading">
		<NcEmptyContent class="empty-content" :name="t('empleados', 'Loading')">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>
	</NcAppContent>

	<NcAppContent v-else name="Loading">
		<!-- contacts list -->
		<template #list>
			<AreasFullList
				:list="areasList"
				:contacts="Areas"
				:search-query="searchQuery"
				:reload-bus="reloadBus" />
		</template>

		<!-- main contacts details -->
		<AreasDetails :data="data_areas" :people-area="peopleArea" />
		<FloatingHelpButton
			:open.sync="modalMensajeAreas"
			:title="t('empleados', 'Areas information')"
			:icon="AccountGroup">
			<MensajeAreas />
		</FloatingHelpButton>
	</NcAppContent>
</template>

<script>
// agregados
import AreasFullList from './AreasFullList.vue'
import AreasDetails from './perfil/AreasDetails.vue'
import FloatingHelpButton from '../Helpers/FloatingHelpButton.vue'
import MensajeAreas from './MensajeAreas.vue'

import { showError /* showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import mitt from 'mitt'
import { translate as t } from '@nextcloud/l10n'

import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'

import {
	NcEmptyContent,
	NcAppContent,
	NcLoadingIcon,
} from '@nextcloud/vue'

export default {
	name: 'AreasList',
	components: {
		AreasFullList,
		NcEmptyContent,
		NcAppContent,
		NcLoadingIcon,
		AreasDetails,
		FloatingHelpButton,
		MensajeAreas,
	},

	data() {
		return {
			loading: true,
			Areas: [],
			searchQuery: '',
			reloadBus: mitt(),
			areasList: [],
			data_areas: {},
			peopleArea: {},
			modalMensajeAreas: false,
			AccountGroup,
		}
	},

	async mounted() {
		this.getall()
		this.$root.$on('send-data-areas', (data) => {
			this.data_areas = data
			this.getalldepartament(data.Id_departamento)
		})
		this.$root.$on('delete-areas', () => {
			this.getall()
		})
		this.$root.$on('reload', () => {
			this.getall()
		})
	},

	methods: {
		// expone i18n en plantilla
		t,

		async getalldepartament(departamento) {
			try {
				await axios.get(generateUrl('/apps/empleados/GetEmpleadosArea/' + departamento))
					.then(
						(response) => {
							this.peopleArea = response?.data?.ocs?.data
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		async getall() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetAreasList'))
					.then(
						(response) => {
							if (response?.data?.ocs?.meta?.status !== 'ok') {
								showError(response?.data?.ocs?.meta?.message)
								this.loading = false
								window.location.href = '/apps/empleados/#/'
								return
							}
							this.Areas = response?.data?.ocs?.data
							this.loading = false
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
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
