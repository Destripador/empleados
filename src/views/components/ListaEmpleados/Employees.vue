<template id="content">
	<EmployeeList
		:empleados-prop="Empleados"
		:loading-prop="loading" />
</template>

<script>
import EmployeeList from './EmployeeList.vue'
import { showError /* showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'Employees',
	components: {
		EmployeeList,
	},

	data() {
		return {
			loading: true,
			Empleados: [],
			data_empleado: {},
		}
	},

	mounted() {
		this.getall()
		this.$bus.on('send-data', (data) => {
			this.data_empleado = data
		})
		this.$bus.on('getall', () => {
			this.getall()
		})
	},

	methods: {
		t,

		async getall() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetEmpleadosList'))
					.then(
						(response) => {
							if (response?.data?.ocs?.meta?.status !== 'ok') {
								showError(response?.data?.ocs?.meta?.message)
								this.loading = false
								window.location.href = '/apps/empleados/#/'
								return
							}
							this.Empleados = response?.data?.ocs?.data.Empleados
							this.loading = false
						},
						(err) => {
							this.loading = false
							showError(err)
						},
					)
			} catch (err) {
				this.loading = false
				showError(t('empleados', 'An exception has occurred [01] [{error}]', { error: String(err) }))
			}
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
