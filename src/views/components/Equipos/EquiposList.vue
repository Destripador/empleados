<template>
	<NcAppContent v-if="loading" :name="t('empleados', 'Loading')">
		<NcEmptyContent class="empty-content" :name="t('empleados', 'Loading')">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>
	</NcAppContent>

	<NcAppContent v-else :name="t('empleados', 'Loading')">
		<template v-if="!isMobile" #list>
			<EquiposFullList
				:list="EquiposList"
				:contacts="Equipos"
				:search-query="searchQuery"
				:reload-bus="reloadBus" />
		</template>

		<EquiposDetails
			v-if="!isMobile"
			:data="data_Equipos"
			:people-area="peopleArea"
			:items="Equipos" />

		<!-- Móvil: una sola vista a la vez, con navegación -->
		<template v-if="isMobile">
			<EquiposFullList
				v-show="mobileView === 'list'"
				:list="EquiposList"
				:contacts="Equipos"
				:search-query="searchQuery"
				:reload-bus="reloadBus"
				class="mobile-pane" />
			<EquiposDetails
				v-show="mobileView !== 'list'"
				:data="mobileView === 'map' ? {} : data_Equipos"
				:people-area="peopleArea"
				:items="Equipos"
				class="mobile-pane" />
		</template>

		<FloatingHelpButton
			:open.sync="modalMensajeEquipos"
			:title="t('empleados', 'Team information')"
			:icon="AccountGroup">
			<MensajeEquipos />
		</FloatingHelpButton>
	</NcAppContent>
</template>

<script>
// agregados
import EquiposFullList from './EquiposFullList.vue'
import EquiposDetails from './perfil/EquiposDetails.vue'
import FloatingHelpButton from '../Helpers/FloatingHelpButton.vue'
import MensajeEquipos from './MensajeEquipos.vue'

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
	name: 'EquiposList',
	components: {
		EquiposFullList,
		NcEmptyContent,
		NcAppContent,
		NcLoadingIcon,
		EquiposDetails,
		FloatingHelpButton,
		MensajeEquipos,
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
			modalMensajeEquipos: false,
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

		this.$root.$on('send-data-equipos', (data) => {
			this.data_Equipos = data || {}
			if (data && data.Id_equipo) {
				this.getallequipo(data.Id_equipo)
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

		this.$root.$on('delete-Equipos', () => {
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
			this.data_Equipos = {}
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
		// Exponer t a la plantilla
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
			this.data_Equipos = {}
			this.peopleArea = {}
			if (this.isMobile) {
				this.mobileView = 'list'
			}
		},

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

	.mobile-pane {
		width: 100%;
		height: 100%;
	}
</style>
