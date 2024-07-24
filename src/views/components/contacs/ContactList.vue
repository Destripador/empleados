<template>
	<AppContentList class="content-list">
		<div class="contacts-list__header">
			<div class="search-contacts-field">
				<div class="container-search">
					<div class="input-container">
						<input v-model="query" type="text" :placeholder="t('empleados', 'Buscar empleados...')">
					</div>
					<div class="button-container">
						<NcActions>
							<template #icon>
								<Cog :size="20" />
							</template>
							<NcActionButton @click="Exportar()">
								<template #icon>
									<DatabaseExport :size="20" />
								</template>
								Exportar listado
							</NcActionButton>
							<NcActionSeparator />
							<!--NcActionButton @click="showMessage('Delete')">
								<template #icon>
									<Download :size="20" />
								</template>
								Exportar plantilla vacia
							</NcActionButton-->
							<NcActionButton @click="$refs.file.click()">
								<template #icon>
									<Upload :size="20" />
								</template>
								Importar datos desde plantilla
							</NcActionButton>
							<NcActionLink href="https://nextcloud.com">
								<template #icon>
									<Cog :size="20" />
								</template>
								Link
							</NcActionLink>
						</NcActions>
					</div>
				</div>
			</div>
		</div>
		<VirtualList ref="scroller"
			class="contacts-list"
			data-key="Id_empleados"
			:data-sources="filteredList"
			:data-component="ContactsListItem"
			:estimate-size="60"
			:extra-props="{reloadBus}" />
		<input
			ref="file"
			type="file"
			style="display: none"
			accept=".xlsx"
			@change="importar()">
	</AppContentList>
</template>

<script>
import {
	NcAppContentList as AppContentList,
	NcActions,
	NcActionButton,
	NcActionSeparator,
} from '@nextcloud/vue'

import { showError, showSuccess } from '@nextcloud/dialogs'
import ContactsListItem from './ContactsListItem.vue'
import VirtualList from 'vue-virtual-scroll-list'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
// import mitt from 'mitt'

import DatabaseExport from 'vue-material-design-icons/DatabaseExport.vue'
// import Download from 'vue-material-design-icons/Download.vue'
import Upload from 'vue-material-design-icons/Upload.vue'
import Cog from 'vue-material-design-icons/Cog.vue'

export default {
	name: 'ContactList',

	components: {
		AppContentList,
		VirtualList,
		NcActions,
		NcActionButton,
		Cog,
		Upload,
		// Download,
		DatabaseExport,
		NcActionSeparator,
	},

	props: {
		list: {
			type: Array,
			required: true,
		},
		contacts: {
			type: Array,
			required: true,
		},
		searchQuery: {
			type: String,
			default: '',
		},
		reloadBus: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			ContactsListItem,
			query: '',
		}
	},

	computed: {
		filteredList() {
			return this.contacts
				.filter(item => this.matchSearch(item.displayname, item.uid))
		},
	},

	mounted() {
		this.query = this.searchQuery
	},

	methods: {
		matchSearch(empleados, uid) {
			try {
				if (this.query.trim() !== '') {
					return empleados.toString().toLowerCase().search(this.query.trim().toLowerCase()) !== -1
				}
			} catch (error) {
				if (this.query.trim() !== '') {
					return uid.toString().toLowerCase().search(this.query.trim().toLowerCase()) !== -1
				}
			}
			return true
		},
		Exportar() {
			axios.get(
				generateUrl('/apps/empleados/ExportListEmpleados'),
				{
					responseType: 'blob',
				},
			).then(
				(response) => {
					const url = URL.createObjectURL(new Blob([response.data], {
						type: 'application/vnd.ms-excel',
					}))

					const link = document.createElement('a')
					link.href = url
					link.setAttribute('download', 'historial.xlsx')
					document.body.appendChild(link)
					link.click()
				},
				(err) => {
					showError(t('ahorrosgossler', 'Se ha producido un error ' + err + ', reporte al administrador'))
					this.exportardata = false
				},
			)
		},
		async importar() {
			// eslint-disable-next-line no-console
			console.log(this.$refs.file.files[0])
			const formData = new FormData()
			formData.append('fileXLSX', this.$refs.file.files[0])
			try {
				await axios.post(generateUrl('/apps/empleados/ImportListEmpleados'), formData,
					{
						headers: {
							'Content-Type': 'multipart/form-data',
						},
					})
					.then(
						(response) => {
							// this.getall()
							// eslint-disable-next-line no-console
							console.log('MENSAJE 2.0: ', response)
							showSuccess(t('empleados', 'Se actualizo la base de datos exitosamente'))
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [' + err + ']'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
// Make virtual scroller scrollable
.contacts-list {
	max-height: calc(100vh - var(--header-height) - 48px);
	overflow: auto;
}

// Add empty header to contacts-list that solves overlapping of contacts with app-navigation-toogle
.contacts-list__header {
	min-height: 48px;
}

// Search field
.search-contacts-field {
	padding: 5px 10px 5px 50px;
	margin-top: 4px;

	> input {
		width: 100%;
	}
}

.content-list {
	overflow-y: auto;
	padding: 0 4px;
}

.container-search {
            display: flex;
        }
        .input-container {
            flex: 1;
            margin-right: 5px;
        }
        .input-container input {
            width: 100%;
        }
        .button-container button {
            width: 100%;
        }
</style>
