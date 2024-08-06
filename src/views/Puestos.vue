<template id="content">
	<NcContent app-name="empleados">
		<navigator />
		<!--NcAppContent>
			<div>
				<h2 class="board-title">
					<AccountGroup :size="20" decorative class="icon" />
					<span>{{ t('empleados', 'Puestos') }}</span>
				</h2>
			</div>
		</NcAppContent-->
		<puestos />
	</NcContent>
</template>

<script>
// navigator
import navigator from './navigator/Sidenavigation.vue'
import puestos from './components/puestos/PuestosList.vue'

import { showError /* showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

import {
	NcContent,
} from '@nextcloud/vue'

export default {
	name: 'Puestos',
	components: {
		navigator,
		puestos,
		NcContent,
	},

	data() {
		return {
			loading: true,
		}
	},

	async mounted() {
		this.getall()
	},

	methods: {
		async getall() {
			this.loading = false
			try {
				await axios.get(generateUrl('/apps/empleados/GetPuestosList'))
					.then(
						(response) => {
							// eslint-disable-next-line no-console
							console.log(response)
							this.loading = false
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [' + err + ']'))
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
