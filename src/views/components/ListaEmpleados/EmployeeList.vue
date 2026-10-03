<template id="EmployeeList">
	<NcAppContent v-if="loadingProp" :name="t('empleados', 'Loading')">
		<NcEmptyContent class="empty-content" :name="t('empleados', 'Loading')">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>
	</NcAppContent>

	<!-- Escritorio: layout de dos paneles, EXACTAMENTE igual que antes -->
	<NcAppContent v-else-if="!isMobile" :name="t('empleados', 'Loading')">
		<template #list>
			<ContentList
				:employees="empleadosProp"
				:search-query="searchQuery" />
		</template>

		<EmployeeDetails
			:data="data_empleado"
			:empleados-prop="empleadosProp" />
	</NcAppContent>

	<!-- Móvil: un solo panel a la vez, mostrado directamente como
	     contenido principal (NO se usa el slot #list, porque en móvil
	     ese slot queda escondido detrás del botón ☰ y por eso se veía
	     la pantalla en blanco). -->
	<NcAppContent v-else :name="t('empleados', 'Loading')">
		<ContentList
			v-if="mobilePane === 'list'"
			:employees="empleadosProp"
			:search-query="searchQuery" />

		<EmployeeDetails
			v-if="mobilePane === 'detail'"
			:data="data_empleado"
			:empleados-prop="empleadosProp" />
	</NcAppContent>
</template>

<script>
// agregados
import ContentList from './ContentList.vue'
import EmployeeDetails from './EmployeeDetails.vue'

import {
	NcEmptyContent,
	NcAppContent,
	NcLoadingIcon,
} from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'EmployeeList',
	components: {
		NcEmptyContent,
		NcAppContent,
		NcLoadingIcon,
		ContentList,
		EmployeeDetails,
	},

	props: {
		empleadosProp: {
			type: Array,
			required: true,
		},
		loadingProp: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			searchQuery: '',
			data_empleado: {},
			// 'list' o 'detail'. Solo se usa en la rama móvil.
			mobilePane: 'list',
			// Se calcula ya mismo (no hasta "mounted") para no dibujar el
			// layout de escritorio por un instante en un celular.
			isMobile: typeof window !== 'undefined' && window.matchMedia
				? window.matchMedia('(max-width: 900px)').matches
				: false,
			mobileMediaQuery: null,
		}
	},

	watch: {
		data_empleado(newVal) {
			// Al seleccionar un empleado, en móvil pasamos al panel de detalle.
			if (newVal && Object.keys(newVal).length > 0) {
				this.mobilePane = 'detail'
			}
		},
	},

	mounted() {
		this.$bus.on('send-data', (data) => {
			this.data_empleado = data
		})

		// Botón "Organigrama" de ContentList.vue
		this.$bus.on('show-org-chart', this.onShowOrgChart)
		// Botón "Volver a la lista" de EmployeeDetails.vue
		this.$bus.on('back-to-list', this.onBackToList)

		// deteccion de esc
		window.addEventListener('keydown', this.onKeyDown)

		// Mantiene isMobile actualizado si se gira el celular o se
		// redimensiona la ventana (breakpoint 900px, igual que el resto
		// de botones de acceso rápido en la app).
		this.mobileMediaQuery = window.matchMedia('(max-width: 900px)')
		this._onMobileChange = (event) => {
			this.isMobile = event.matches
		}
		if (this.mobileMediaQuery.addEventListener) {
			this.mobileMediaQuery.addEventListener('change', this._onMobileChange)
		} else {
			this.mobileMediaQuery.addListener(this._onMobileChange)
		}
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.onKeyDown)

		if (this.$bus?.off) {
			this.$bus.off('show-org-chart', this.onShowOrgChart)
			this.$bus.off('back-to-list', this.onBackToList)
		}

		if (this.mobileMediaQuery) {
			if (this.mobileMediaQuery.removeEventListener) {
				this.mobileMediaQuery.removeEventListener('change', this._onMobileChange)
			} else {
				this.mobileMediaQuery.removeListener(this._onMobileChange)
			}
		}
	},

	methods: {
		t,
		onKeyDown(e) {
			if (e.key === 'Escape') this.onEsc()
		},
		onEsc() {
			// eslint-disable-next-line no-console
			console.log('Esc pressed')
			this.data_empleado = {}
			this.mobilePane = 'list'
		},

		onShowOrgChart() {
			this.data_empleado = {}
			this.mobilePane = 'detail'
		},

		onBackToList() {
			this.mobilePane = 'list'
			this.data_empleado = {}
		},
	},
}
</script>
