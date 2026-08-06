<template>
	<NcAppContent v-if="loading" :name="t('empleados', 'Loading')">
		<NcEmptyContent class="empty-content" :name="t('empleados', 'Loading')">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>
	</NcAppContent>

	<NcAppContent v-else :name="t('empleados', 'Loading')">
		<template #list>
			<EquiposFullList
				:list="EquiposList"
				:contacts="Equipos"
				:search-query="searchQuery"
				:reload-bus="reloadBus" />
		</template>

		<EquiposDetails
			:data="data_Equipos"
			:people-area="peopleArea"
			:items="Equipos" />

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
		window.addEventListener('keydown', this.onKeyDown)
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.onKeyDown)
	},

	methods: {
		// Exponer t a la plantilla
		t,

		onKeyDown(e) {
			if (e.key === 'Escape') {
				this.onEsc()
			}
		},

		onEsc() {
			this.data_Equipos = {}
			this.peopleArea = {}
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
</style>
