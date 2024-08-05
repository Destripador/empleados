<template>
	<AppContentList class="content-list">
		<div class="contacts-list__header">
			<div class="search-contacts-field">
				<div class="container-search">
					<div class="input-container">
						<input v-model="query" type="text" :placeholder="t('empleados', 'Buscar empleados...')">
					</div>
					<div class="button-container">
						<NcActions
							:open="button"
							@click="toggle">
							<template #icon>
								<Cog :size="20" />
							</template>
							<NcActionButton @click="AgregarNuevo()">
								<template #icon>
									<AccountMultiplePlusOutline :size="20" />
								</template>
								Agregar area nueva
							</NcActionButton>
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
						</NcActions>
					</div>
				</div>
			</div>
		</div>
		<VirtualList ref="scroller"
			class="contacts-list"
			data-key="Id_departamento"
			:data-sources="filteredList"
			:data-component="AreasListItem"
			:estimate-size="60"
			:extra-props="{reloadBus}" />
		<input
			ref="file"
			type="file"
			style="display: none"
			accept=".xlsx"
			@change="importar()">
		<NcModal
			v-if="modal"
			ref="modalRef"
			name="Agregar nueva area"
			@close="closeModal">
			<div class="modal__content">
				<div class="form-group center">
					<NcTextField :value.sync="nombre_area"
						label="Nombre del area" />
					<NcSelect v-model="padre"
						input-label="Area Padre"
						:options="options" />
					<br>
					<NcButton
						class="center"
						aria-label="Guardar cambios"
						type="primary"
						@click="crearArea()">
						Guardar cambios
					</NcButton>
				</div>
			</div>
		</NcModal>
	</AppContentList>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

// Iconos
import DatabaseExport from 'vue-material-design-icons/DatabaseExport.vue'
import AccountMultiplePlusOutline from 'vue-material-design-icons/AccountMultiplePlusOutline.vue'
// import Download from 'vue-material-design-icons/Download.vue'
import Upload from 'vue-material-design-icons/Upload.vue'
import Cog from 'vue-material-design-icons/Cog.vue'

import {
	NcAppContentList as AppContentList,
	NcActions,
	NcActionButton,
	NcModal,
	NcTextField,
	NcSelect,
	NcButton,
} from '@nextcloud/vue'
import AreasListItem from './AreasListItem.vue'
import VirtualList from 'vue-virtual-scroll-list'

export default {
	name: 'AreasFullList',

	components: {
		AppContentList,
		VirtualList,
		NcActions,
		NcActionButton,
		Cog,
		Upload,
		DatabaseExport,
		AccountMultiplePlusOutline,
		NcModal,
		NcTextField,
		NcSelect,
		NcButton,
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
			AreasListItem,
			query: '',
			modal: false,
			button: false,
			padre: '',
			options: [],
			nombre_area: '',
		}
	},

	computed: {
		filteredList() {
			return this.contacts
				.filter(item => this.matchSearch(item.Nombre))
		},
	},

	watch: {
		modal(news, olds) {
			if (olds !== news) {
				this.getallsAreas()
			}
		},
	},

	mounted() {
		this.query = this.searchQuery
	},

	methods: {
		matchSearch(areas) {
			if (this.query.trim() !== '') {
				return areas.toString().toLowerCase().search(this.query.trim().toLowerCase()) !== -1
			}
			return true
		},

		async getallsAreas() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetAreasFix'))
					.then(
						(response) => {
							this.options = response.data
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [' + err + ']'))
			}
		},

		Exportar() {
			this.toggle()
			axios.get(
				generateUrl('/apps/empleados/ExportListAreas'),
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
			this.toggle()
			const formData = new FormData()
			formData.append('AreafileXLSX', this.$refs.file.files[0])
			try {
				await axios.post(generateUrl('/apps/empleados/ImportListAreas'), formData,
					{
						headers: {
							'Content-Type': 'multipart/form-data',
						},
					})
					.then(
						(response) => {
							this.$root.$emit('getall')
							this.$root.$emit('reload')
							this.$root.$emit('send-data-areas', [])
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
		AgregarNuevo() {
			this.toggle()
			this.modal = true
		},
		closeModal() {
			this.modal = false
		},
		toggle() {
			this.button = !this.button
		},
		async crearArea() {
			if (this.padre.label) {
				this.padre = this.padre.label
			} else {
				this.padre = ''
			}
			try {
				await axios.post(generateUrl('/apps/empleados/crearArea'),
					{
						padre: this.padre,
						nombre: this.nombre_area,
					})
					.then(
						(response) => {
							showSuccess('Area creada exitosamente')
							this.$root.$emit('reload')
							this.nombre_area = ''
							this.padre = null
							this.modal = false
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

.modal__content {
	margin: 50px;
}

.modal__content h2 {
	text-align: center;
}

.form-group {
	margin: calc(var(--default-grid-baseline) * 4) 0;
	display: flex;
	flex-direction: column;
	align-items: flex-start;
}
</style>
