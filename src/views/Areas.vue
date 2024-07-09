<template>
	<NcContent app-name="empleados">
		<navigator />
		<NcAppContent>
			<div>
				<h2 class="board-title">
					<AccountGroup :size="20" decorative class="icon" />
					<span>{{ t('empleados', 'Areas') }}</span>
				</h2>
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script>
// navigator
import navigator from './navigator/Sidenavigation.vue'

// iconos
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'

import { showError /* showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

import {
	NcAppContent,
	NcContent,
} from '@nextcloud/vue'

export default {
	name: 'Areas',
	components: {
		navigator,
		NcAppContent,
		NcContent,
		AccountGroup,
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
				await axios.get(generateUrl('/apps/empleados/GetAreasList'))
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
