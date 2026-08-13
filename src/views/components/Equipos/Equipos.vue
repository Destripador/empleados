<template id="content">
	<EquiposList />
</template>

<script>
import EquiposList from './EquiposList.vue'

export default {
	name: 'Equipos',
	components: {
		EquiposList,
	},

	data() {
		return {
			loading: true,
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
