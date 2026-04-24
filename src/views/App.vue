<template id="content">
	<NcContent app-name="empleados">
		<navigator v-if="configuraciones.usuario_almacenamiento != null && String(configuraciones.usuario_almacenamiento).trim() !== ''" />
		<router-view v-if="configuraciones.usuario_almacenamiento != null && String(configuraciones.usuario_almacenamiento).trim() !== ''" />
		<NcEmptyContent v-else
			:name="t('empleados', 'Finish the initial setup')"
			:description="t('empleados', 'Go to global settings and select the data manager.')"
			style="background-color: white;">
			<template #icon>
				<AlertCircleOutline />
			</template>
		</NcEmptyContent>
	</NcContent>
</template>

<script>
// Importing necessary components
import navigator from './navigator/Sidenavigation.vue'
import { NcContent, NcEmptyContent } from '@nextcloud/vue'

// icons
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'

export default {
	name: 'App',
	components: {
		navigator,
		NcContent,
		NcEmptyContent,
		AlertCircleOutline,
	},

	provide() {
		return {
			configuraciones: this.configuraciones,
			groupuser: this.groupsuser,
			employee: this.employee,
			subordinates: this.subordinates,
		}
	},

	props: {
		// Configuration parameters
		parameters: {
			type: Object,
			required: true,
		},
		groupsUser: {
			type: Object,
			required: true,
		},
		employee: {
			type: Array,
			required: true,
		},
		subordinatesGroup: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
			configuraciones: this.parameters,
			groupsuser: this.groupsUser,
			employeeUser: this.employee,
			subordinates: this.subordinatesGroup,
		}
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
