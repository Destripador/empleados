<!-- eslint-disable vue/require-v-for-key -->
<template>
	<VueTabs>
		<VTab title="Configuraciones">
			<ListSettings />
		</VTab>

		<VTab title="Empleados">
			<EmpleadosSettings />
		</VTab>
	</VueTabs>
</template>

<script>
import EmpleadosSettings from './EmpleadosSettings.vue'
import ListSettings from './ListSettings.vue'
// global registration

import { VueTabs, VTab } from 'vue-nav-tabs/dist/vue-tabs.js'
import 'vue-nav-tabs/themes/vue-tabs.css'
// iconos
// import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
// import Delete from 'vue-material-design-icons/Delete.vue'
// import Plus from 'vue-material-design-icons/Plus.vue'
// import WalletPlus from 'vue-material-design-icons/WalletPlus'
// import Cog from 'vue-material-design-icons/Cog'
// import Check from 'vue-material-design-icons/Check'

// imports
// import { NcActions, NcActionButton, NcLoadingIcon, NcAvatar } from '@nextcloud/vue'
// import { showError /* showSuccess */ } from '@nextcloud/dialogs'
// import { generateUrl } from '@nextcloud/router'
//  import axios from '@nextcloud/axios'

export default {
	name: 'Settings',
	components: {
		EmpleadosSettings,
		ListSettings,
		VueTabs,
		VTab,
	},

	data() {
		return {
		}
	},

	mounted() {},

	methods: {},
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
	.icon {
		margin-right: 8px;
	}
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
	.icon {
		margin-right: 8px;
	}
}
.container {
	padding-left: 20px;
	padding-right: 20px;
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
</style>
