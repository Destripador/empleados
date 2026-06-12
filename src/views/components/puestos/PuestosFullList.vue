<template>
	<AppContentList class="content-list">
		<div class="contacts-list__header">
			<div class="search-contacts-field">
				<div class="container-search">
					<div class="input-container">
						<input v-model="query" type="text" :placeholder="t('empleados', 'Search positions...')">
					</div>
					<div class="filters-container">
						<select v-model="sortOrder">
							<option value="asc">A-Z</option>
							<option value="desc">Z-A</option>
						</select>

						<label>
							<input v-model="hideEmpty" type="checkbox">
							Ocultar vacíos
						</label>
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
								{{ t('empleados', 'Add new position') }}
							</NcActionButton>
							<NcActionButton @click="Exportar()">
								<template #icon>
									<DatabaseExport :size="20" />
								</template>
								{{ t('empleados', 'Export list') }}
							</NcActionButton>
							<NcActionSeparator />
							<!--NcActionButton @click="showMessage('Delete')">
								<template #icon>
									<Download :size="20" />
								</template>
								{{ t('empleados', 'Export empty template') }}
							</NcActionButton-->
							<NcActionButton @click="$refs.file.click()">
								<template #icon>
									<Upload :size="20" />
								</template>
								{{ t('empleados', 'Import data from template') }}
							</NcActionButton>
						</NcActions>
					</div>
				</div>
			</div>
		</div>
		<VirtualList ref="scroller"
			class="contacts-list"
			data-key="Id_puestos"
			:data-sources="filteredList"
			:data-component="PuestosListItem"
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
			:name="t('empleados', 'Add new area')"
			@close="closeModal">
			<div class="modal__content">
				<div class="form-group center">
					<NcTextField :value.sync="nombre_area"
						:label="t('empleados', 'Area name')" />
					<br>
					<NcButton
						class="center"
						:aria-label="t('empleados', 'Save changes')"
						type="primary"
						@click="crearPuesto()">
						{{ t('empleados', 'Save changes') }}
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
import { translate as t } from '@nextcloud/l10n'

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
	NcButton,
	NcActionSeparator,
} from '@nextcloud/vue'
import PuestosListItem from './PuestosListItem.vue'
import VirtualList from 'vue-virtual-scroll-list'

export default {
	name: 'PuestosFullList',

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
		NcButton,
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
			PuestosListItem,
			query: '',
			modal: false,
			button: false,
			options: [],
			nombre_area: '',
			sortOrder: 'asc',
			hideEmpty: false,
		}
	},

	computed: {
		filteredList() {
			let puestos = this.contacts
				.filter(item => this.matchSearch(item.Nombre))

			if (this.hideEmpty) {
				puestos = puestos.filter(
					item => Number(item.cantidad_empleados) > 0
				)
			}

			puestos.sort((a, b) => {
				if (this.sortOrder === 'asc') {
					return a.Nombre.localeCompare(b.Nombre)
				}

				return b.Nombre.localeCompare(a.Nombre)
			})

			return puestos
		},
	},

	watch: {
		modal(news, olds) {
			if (olds !== news) {
				this.getallsPuestos()
			}
		},
	},

	mounted() {
		this.query = this.searchQuery
	},

	methods: {
		t,

		matchSearch(puestos) {
			if (this.query.trim() !== '') {
				return puestos.toString().toLowerCase().search(this.query.trim().toLowerCase()) !== -1
			}
			return true
		},

		async getallsPuestos() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetPuestosFix'))
					.then(
						(response) => {
							this.options = response?.data?.ocs?.data
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [01] [{error}]', { error: String(err) }))
			}
		},

		Exportar() {
			this.toggle()
			axios.get(
				generateUrl('/apps/empleados/ExportListPuestos'),
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
					link.setAttribute('download', 'positions.xlsx')
					document.body.appendChild(link)
					link.click()
				},
				(err) => {
					showError(t('empleados', 'An error occurred {error}, please report to the administrator', { error: String(err) }))
					this.exportardata = false
				},
			)
		},
		async importar() {
			this.toggle()
			const formData = new FormData()
			formData.append('puestofileXLSX', this.$refs.file.files[0])
			try {
				await axios.post(generateUrl('/apps/empleados/ImportListPuestos'), formData,
					{
						headers: {
							'Content-Type': 'multipart/form-data',
						},
					})
					.then(
						(response) => {
							this.$root.$emit('getall')
							this.$root.$emit('reload')
							this.$root.$emit('send-data-puestos', {})
							showSuccess(t('empleados', 'Database updated successfully'))
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [03] [{error}]', { error: String(err) }))
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
		async crearPuesto() {
			try {
				await axios.post(generateUrl('/apps/empleados/crearPuesto'),
					{
						nombre: this.nombre_area,
					})
					.then(
						(response) => {
							showSuccess(t('empleados', 'Area created successfully'))
							this.$root.$emit('reload')
							this.nombre_area = ''
							this.modal = false
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [03] [{error}]', { error: String(err) }))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
// Filtro y ordenamiento
.filters-container {
	display: flex;
	align-items: center;
	grid-area: filters;
	flex-wrap: nowrap;
	gap: 5px;
	margin: 0;
	white-space: nowrap;
}

.filters-container select {
	min-width: 64px;
	height: 26px;
	padding: 1px 22px 1px 7px;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 12px;
}

.filters-container label {
	display: inline-flex;
	align-items: center;
	min-height: 26px;
	padding: 0 7px;
	gap: 5px;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	background-color: var(--color-main-background);
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1;
}

.filters-container input[type='checkbox'] {
	width: 13px;
	height: 13px;
	margin: 0;
}

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
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	grid-template-areas:
		"input button"
		"filters button";
	align-items: start;
	gap: 6px 8px;
}
.input-container {
	grid-area: input;
}
.input-container input {
	width: 100%;
}
.button-container {
	grid-area: button;
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
