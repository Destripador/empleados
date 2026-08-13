<template id="EmployeeList">
	<NcAppContent v-if="loadingProp" :name="t('empleados', 'Loading')">
		<NcEmptyContent class="empty-content" :name="t('empleados', 'Loading')">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>
	</NcAppContent>

	<NcAppContent v-else :name="t('empleados', 'Loading')">
		<!-- contacts list -->
		<template #list>
			<ContentList
				:employees="empleadosProp"
				:search-query="searchQuery" />
		</template>

		<!-- main contacts details -->
		<EmployeeDetails :data="data_empleado" :empleados-prop="empleadosProp" />
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
		}
	},

	mounted() {
		this.$bus.on('send-data', (data) => {
			this.data_empleado = data
		})
		// deteccion de esc
		window.addEventListener('keydown', this.onKeyDown)
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.onKeyDown)
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
		},
	},
}
</script>
