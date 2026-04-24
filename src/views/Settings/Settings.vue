<!-- eslint-disable vue/require-v-for-key -->
<template>
	<div class="container">
		<div v-if="loading">
			<div class="loader-settings">
				<NcLoadingIcon :size="64" />
			</div>
		</div>
		<div v-else>
			<VueTabs>
				<VTab :title="t('empleados', 'Employees')">
					<EmpleadosSettings v-if="datamanager[0] !== null" />
					<NcEmptyContent v-else
						:name="t('empleados', 'Finish the initial setup')"
						:description="t('empleados', 'Go to global settings and select the data manager.')">
						<template #icon>
							<AlertCircleOutline />
						</template>
					</NcEmptyContent>
				</VTab>

				<VTab :title="t('empleados', 'Working time')">
					<TiempoLaboralSettings v-if="datamanager[0] !== null" />
					<NcEmptyContent v-else
						:name="t('empleados', 'Finish the initial setup')"
						:description="t('empleados', 'Go to global settings and select the data manager.')">
						<template #icon>
							<AlertCircleOutline />
						</template>
					</NcEmptyContent>
				</VTab>

				<VTab :title="t('empleados', 'Global settings')">
					<ListSettings />
				</VTab>
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

import { showError /*, showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

import { VueTabs, VTab } from 'vue-nav-tabs/dist/vue-tabs.js'
import 'vue-nav-tabs/themes/vue-tabs.css'

import { NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'

export default {
	name: 'Settings',
	components: {
		EmpleadosSettings,
		TiempoLaboralSettings,
		ListSettings,
		VueTabs,
		VTab,
		NcEmptyContent,
		AlertCircleOutline,
		NcLoadingIcon,
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

<style>
.board-title {
	padding-left: 20px;
	margin-right: 10px;
	margin-top: 14px;
	font-size: 25px;
	display: flex;
	align-items: center;
	font-weight: bold;
}
.board-title .icon {
	margin-right: 8px;
}

.center-screen {
  display: flex;
  justify-content: center;
  align-items: center;
  text-align: center;
  min-height: 100vh;
}

.titles {
	margin-right: 10px;
	margin-top: 14px;
	font-size: 17px;
	display: flex;
	align-items: center;
}
.titles .icon {
	margin-right: 8px;
}

.container {
	padding-left: 20px;
	padding-right: 20px;
	margin-top: 10px;
}

.rsg {
	padding-top: 16px;
	padding-bottom: 16px;
	border: 1px solid rgb(232, 232, 232);
	border-radius: 3px;
	display: flex;
	margin-left: 20px;
	margin-right: 20px;
	width: auto;
}
.loader-settings {
	display: flex;
	justify-content: center;
	align-items: center;
	min-height: 60vh;
}
</style>
