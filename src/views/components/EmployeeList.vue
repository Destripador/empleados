<template id="EmployeeList">
	<NcAppContent v-if="loading" name="Loading">
		<NcEmptyContent class="empty-content" name="Cargando">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>
	</NcAppContent>
	<NcAppContent v-else name="Loading">
		<!-- contacts list -->
		<template #list>
			<ContactsList :list="contactsList"
				:contacts="Empleados"
				:search-query="searchQuery" />
		</template>

		<!-- main contacts details -->
		<!--ContactDetails :contact-key="selectedContact" :contacts="sortedContacts" :reload-bus="reloadBus" /-->
	</NcAppContent>
</template>

<script>
// agregados
import ContactsList from './ContactList.vue'

import { showError /* showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

import {
	NcEmptyContent,
	NcAppContent,
	NcLoadingIcon,
} from '@nextcloud/vue'

export default {
	name: 'EmployeeList',
	components: {
		NcEmptyContent,
		NcAppContent,
		NcLoadingIcon,
		ContactsList,
	},

	data() {
		return {
			loading: true,
			Empleados: [],
			searchQuery: '',
		}
	},

	computed: {
		// store variables
		contacts() {
			return this.$store.getters.getContacts
		},
		groups() {
			return this.$store.getters.getGroups
		},
		sortedContacts() {
			return this.$store.getters.getSortedContacts
		},

		selectedContact() {
			return this.$route.params.selectedContact
		},

		/**
		 * Is this a real group ?
		 * Aka not a dynamically generated one like `All contacts`
		 *
		 * @return {boolean}
		 */
		isRealGroup() {
			return this.groups.findIndex(group => group.name === this.selectedGroup) > -1
		},
		/**
		 * Is the current group empty
		 *
		 * @return {boolean}
		 */
		isEmptyGroup() {
			return this.contactsList.length === 0
		},

		showDetails() {
			return !!this.selectedContact
		},
	},

	async mounted() {
		this.getall()
	},

	methods: {
		async getall() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetEmpleadosList'))
					.then(
						(response) => {
							this.Empleados = response.data.Empleados

							// eslint-disable-next-line no-console
							console.log(response.data.Empleados)
							this.Empleados = response.data.Empleados
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
		onPaginationData(paginationData) {
			this.$refs.pagination.setPaginationData(paginationData)
		},
		onChangePage(page) {
			this.$refs.vuetable.changePage(page)
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
