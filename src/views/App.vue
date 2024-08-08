<template id="content">
	<NcContent app-name="empleados">
		<navigator />
		<!--EmployeeList /-->
		<h2>XXXXXXXXXXXXX</h2>
		<h2>{{ parameters }}</h2>
	</NcContent>
</template>

<script>
// navigator
import navigator from './navigator/Sidenavigation.vue'
// import EmployeeList from './components/contacs/EmployeeList.vue'

import {
	NcContent,
} from '@nextcloud/vue'

export default {
	name: 'App',
	components: {
		navigator,
		// EmployeeList,
		NcContent,
	},
	props: {
		parameters: {
			type: Array,
			required: true,
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
