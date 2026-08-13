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
			<PuestosFullList
				:list="puestosList"
				:contacts="Puestos"
				:search-query="searchQuery"
				:reload-bus="reloadBus" />
		</template>

		<!-- main contacts details -->
		<PuestosDetails :data="data_puestos" :people-area="peopleArea" :items="Puestos" />
		<FloatingHelpButton
			:open.sync="modalMensajePuestos"
			:title="t('empleados', 'Puestos information')"
			:icon="AccountGroup">
			<MensajePuestos />
		</FloatingHelpButton>
	</NcAppContent>
</template>

<script>
// agregados
import PuestosFullList from './PuestosFullList.vue'
import PuestosDetails from './perfil/PuestosDetails.vue'
import FloatingHelpButton from '../Helpers/FloatingHelpButton.vue'
import MensajePuestos from './MensajePuestos.vue'

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
	name: 'PuestosList',
	components: {
		PuestosFullList,
		NcEmptyContent,
		NcAppContent,
		NcLoadingIcon,
		PuestosDetails,
		// ContactsList,
		FloatingHelpButton,
		MensajePuestos,
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
			modalMensajePuestos: false,
			AccountGroup,
		}
	},

	async mounted() {
		this.getall()
		this.$root.$on('send-data-puestos', (data) => {
			this.data_puestos = data || {}
			if (data && data.Id_puestos) {
				this.getallpuesto(data.Id_puestos)
			} else {
				this.peopleArea = {}
			}
		})
		this.$root.$on('delete-puestos', () => {
			this.getall()
		})
		this.$root.$on('reload', () => {
			this.getall()
		})
		window.addEventListener('keydown', this.onKeyDown)
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.onKeyDown)
	},

	methods: {
		t,

		onKeyDown(e) {
			if (e.key === 'Escape') {
				this.onEsc()
			}
		},

		onEsc() {
			this.data_puestos = {}
			this.peopleArea = {}
		},

		async getallpuesto(puesto) {
			try {
				await axios.get(generateUrl('/apps/empleados/GetEmpleadosPuesto/' + puesto))
					.then(
						(response) => {
							this.peopleArea = response?.data?.ocs?.data
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [01] [{error}]', { error: String(err) }))
			}
		},

		async getall() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetPuestosList'))
					.then(
						(response) => {
							this.Puestos = response?.data?.ocs?.data
							this.loading = false
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [01] [{error}]', { error: String(err) }))
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
