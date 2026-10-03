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
		<template v-if="!isMobile" #list>
			<PuestosFullList
				:list="puestosList"
				:contacts="Puestos"
				:search-query="searchQuery"
				:reload-bus="reloadBus" />
		</template>

		<!-- main contacts details -->
		<PuestosDetails
			v-if="!isMobile"
			:data="data_puestos"
			:people-area="peopleArea"
			:items="Puestos" />

		<!-- Móvil: una sola vista a la vez, con navegación -->
		<template v-if="isMobile">
			<PuestosFullList
				v-show="mobileView === 'list'"
				:list="puestosList"
				:contacts="Puestos"
				:search-query="searchQuery"
				:reload-bus="reloadBus"
				class="mobile-pane" />
			<PuestosDetails
				v-show="mobileView !== 'list'"
				:data="data_puestos"
				:people-area="peopleArea"
				:items="Puestos"
				class="mobile-pane" />
		</template>

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
			isMobile: false,
			mobileView: 'list', // 'list' | 'detail'
			mql: null,
		}
	},

	async mounted() {
		this.getall()

		this.mql = window.matchMedia('(max-width: 900px)')
		this.updateIsMobile()
		if (this.mql.addEventListener) {
			this.mql.addEventListener('change', this.updateIsMobile)
		} else {
			this.mql.addListener(this.updateIsMobile)
		}

		this.$root.$on('send-data-puestos', (data) => {
			this.data_puestos = data || {}
			if (data && data.Id_puestos) {
				this.getallpuesto(data.Id_puestos)
				if (this.isMobile) {
					this.mobileView = 'detail'
				}
			} else {
				this.peopleArea = {}
				if (this.isMobile) {
					this.mobileView = 'list'
				}
			}
		})
		this.$root.$on('delete-puestos', () => {
			this.getall()
		})
		this.$root.$on('reload', () => {
			this.getall()
		})
		this.$root.$on('mobile-back', () => {
			this.mobileView = 'list'
			this.data_puestos = {}
			this.peopleArea = {}
		})
		window.addEventListener('keydown', this.onKeyDown)
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.onKeyDown)
		if (this.mql) {
			if (this.mql.removeEventListener) {
				this.mql.removeEventListener('change', this.updateIsMobile)
			} else {
				this.mql.removeListener(this.updateIsMobile)
			}
		}
	},

	methods: {
		t,

		onKeyDown(e) {
			if (e.key === 'Escape') {
				this.onEsc()
			}
		},

		updateIsMobile() {
			this.isMobile = this.mql ? this.mql.matches : window.innerWidth <= 900
			if (!this.isMobile) {
				this.mobileView = 'list'
			}
		},

		onEsc() {
			this.data_puestos = {}
			this.peopleArea = {}
			if (this.isMobile) {
				this.mobileView = 'list'
			}
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

	.mobile-pane {
		width: 100%;
		height: 100%;
	}

</style>
