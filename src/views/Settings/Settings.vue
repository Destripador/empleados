<!-- eslint-disable vue/require-v-for-key -->
<template>
	<div class="admin-settings-shell">
		<div v-if="loading" class="admin-settings-loading" role="status">
			<NcLoadingIcon :size="40" :name="t('empleados', 'Loading...')" />
		</div>
		<div v-else class="admin-settings-content">
			<VueTabs class="settings-primary-tabs">
				<VTab id="settings-employees" :title="t('empleados', 'Employees')">
					<EmpleadosSettings v-if="datamanager[0] !== null" />
					<NcEmptyContent v-else
						:name="t('empleados', 'Finish the initial setup')"
						:description="t('empleados', 'Go to global settings and select the data manager.')">
						<template #icon>
							<AlertCircleOutline />
						</template>
					</NcEmptyContent>
				</VTab>

				<VTab id="settings-groups-permissions" :title="t('empleados', 'Group and permissions')">
					<GroupSettings v-if="datamanager[0] !== null" />
					<NcEmptyContent v-else
						:name="t('empleados', 'Finish the initial setup')"
						:description="t('empleados', 'Go to global settings and select the data manager.')">
						<template #icon>
							<AlertCircleOutline />
						</template>
					</NcEmptyContent>
				</VTab>

				<VTab id="settings-working-time" :title="t('empleados', 'Working time')">
					<TiempoLaboralSettings v-if="datamanager[0] !== null" />
					<NcEmptyContent v-else
						:name="t('empleados', 'Finish the initial setup')"
						:description="t('empleados', 'Go to global settings and select the data manager.')">
						<template #icon>
							<AlertCircleOutline />
						</template>
					</NcEmptyContent>
				</VTab>

				<VTab id="settings-global" :title="t('empleados', 'Global settings')">
					<ListSettings />
				</VTab>

				<VTab id="settings-movements" :title="t('empleados', 'Movimientos')">
					<MovimientosSettings v-if="datamanager[0] !== null" />
					<NcEmptyContent v-else
						:name="t('empleados', 'Finish the initial setup')"
						:description="t('empleados', 'Go to global settings and select the data manager.')">
						<template #icon>
							<AlertCircleOutline />
						</template>
					</NcEmptyContent>
				</VTab>

				<VTab :title="t('empleados', 'Monedas')">
					<MonedaSettings />
				</VTab>

				<VTab :title="t('empleados', 'Estacionamiento')">
					<VTab id="settings-parking" :title="t('empleados', 'Estacionamiento')">
						<EstacionamientoSettings />
					</VTab>
				</vtab>
			</VueTabs>
		</div>
	</div>
</template>

<script>
// ICONS
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'

import TiempoLaboralSettings from './TiempoLaboralSettings.vue'
import EmpleadosSettings from './EmpleadosSettings.vue'
import ListSettings from './ListSettings.vue'
import GroupSettings from './GroupSettings.vue'
import MovimientosSettings from './MovimientosSettings.vue'
import MonedaSettings from './MonedaSettings.vue'

import { showError /*, showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import { VueTabs, VTab } from 'vue-nav-tabs/dist/vue-tabs.js'
import 'vue-nav-tabs/themes/vue-tabs.css'
import './settings-shared.css'

import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import EstacionamientoSettings from './EstacionamientoSettings.vue'

export default {
	name: 'Settings',
	components: {
		EmpleadosSettings,
		TiempoLaboralSettings,
		ListSettings,
		GroupSettings,
		VueTabs,
		VTab,
		NcEmptyContent,
		AlertCircleOutline,
		NcLoadingIcon,
		EstacionamientoSettings,
		MovimientosSettings,
		MonedaSettings,
	},

	data() {
		return {
			loading: false,
			datamanager: [null],
		}
	},

	mounted() {
		this.getall()
		this.$bus.on('GetDataManager', () => {
			 this.getall()
		})
	},

	methods: {
		t,
		/**
		 * Load global configuration, including "Users" for Data Manager.
		 */
		async getall() {
			try {
				this.loading = true
				const response = await axios.get(generateUrl('/apps/empleados/GetDataManager'))

				this.datamanager = response.data

				this.loading = false
			} catch (err) {
				this.loading = false
				showError(t('empleados', 'Exception [GetConfigurations]: {error}', { error: String(err) }))
				console.error(err)
			}
		},
	},
}
</script>
