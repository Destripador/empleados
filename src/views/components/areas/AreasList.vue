<template id="EmployeeList">
	<NcAppContent v-if="loading" :name="t('empleados', 'Loading...')">
		<NcEmptyContent class="empty-content" :name="t('empleados', 'Loading')">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>
	</NcAppContent>

	<NcAppContent v-else :name="t('empleados', 'Loading...')">
		<template v-if="!isMobile" #list>
			<AreasFullList
				:list="areasList"
				:contacts="Areas"
				:search-query="searchQuery"
				:reload-bus="reloadBus" />
		</template>
		<AreasDetails
			v-if="!isMobile"
			:data="data_areas"
			:people-area="peopleArea"
			:items="Areas" />

		<!-- Móvil: una sola vista a la vez, con navegación -->
		<template v-if="isMobile">
			<AreasFullList
				v-show="mobileView === 'list'"
				:list="areasList"
				:contacts="Areas"
				:search-query="searchQuery"
				:reload-bus="reloadBus"
				class="mobile-pane" />
			<AreasDetails
				v-show="mobileView !== 'list'"
				:data="mobileView === 'map' ? {} : data_areas"
				:people-area="peopleArea"
				:items="Areas"
				class="mobile-pane" />
		</template>

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
			isMobile: false,
			mobileView: 'list', // 'list' | 'map' | 'detail'
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

		this.$root.$on('send-data-areas', (data) => {
			this.data_areas = data || {}
			if (data && data.Id_departamento) {
				this.getalldepartament(data.Id_departamento)
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
		this.$root.$on('delete-areas', () => {
			this.getall()
		})
		this.$root.$on('reload', () => {
			this.getall()
		})
		this.$root.$on('show-map', () => {
			if (this.isMobile) {
				this.mobileView = 'map'
			}
		})
		this.$root.$on('mobile-back', () => {
			this.mobileView = 'list'
			this.data_areas = {}
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
		// expone i18n en plantilla
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
			this.data_areas = {}
			this.peopleArea = {}
			if (this.isMobile) {
				this.mobileView = 'list'
			}
		},

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

	.mobile-pane {
		width: 100%;
		height: 100%;
	}
</style>
