<template id="content">
	<PuestosList />
</template>

<script>
import PuestosList from './PuestosList.vue'

export default {
	name: 'Puestos',
	components: {
		PuestosList,
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
