<template id="content">
	<NcContent app-name="empleados">
		<navigator />
		<EmployeeList />
	</NcContent>
</template>

<script>
// navigator
import navigator from './navigator/Sidenavigation.vue'
import EmployeeList from './components/EmployeeList.vue'

import {
	NcContent,
} from '@nextcloud/vue'

export default {
	name: 'App',
	components: {
		navigator,
		EmployeeList,
		NcContent,
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
