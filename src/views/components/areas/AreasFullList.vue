<template>
	<AppContentList class="content-list">
		<div class="contacts-list__header">
			<div class="search-contacts-field">
				<div class="container-search">
					<div class="input-container">
						<input v-model="query" type="text" :placeholder="t('empleados', 'Search Areas/Departament...')">
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
						<NcActions :open="button" @click="toggle">
							<template #icon>
								<Cog :size="20" />
							</template>

							<NcActionButton @click="AgregarNuevo()">
								<template #icon>
									<AccountMultiplePlusOutline :size="20" />
								</template>
								{{ t('empleados', 'Add new department') }}
							</NcActionButton>

							<NcActionButton @click="Exportar()">
								<template #icon>
									<DatabaseExport :size="20" />
								</template>
								{{ t('empleados', 'Export list') }}
							</NcActionButton>

							<NcActionSeparator />

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

		<VirtualList
			ref="scroller"
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
			:name="t('empleados', 'Add new department')"
			@close="closeModal">
			<div class="modal__content">
				<div class="form-group center">
					<NcTextField
						required
						:value.sync="nombre_area"
						:label="t('empleados', 'Area / Department name')" />
					<NcSelect
						v-model="padre"
						:input-label="t('empleados', 'Parent area / department')"
						:options="options" />
					<br>
					<NcButton
						class="center"
						:aria-label="t('empleados', 'Save changes')"
						type="primary"
						@click="crearArea()">
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
import Upload from 'vue-material-design-icons/Upload.vue'
import Cog from 'vue-material-design-icons/Cog.vue'

import {
	NcAppContentList as AppContentList,
	NcActions,
	NcActionButton,
	NcActionSeparator,
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
		NcActionSeparator,
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
		list: { type: Array, required: true },
		contacts: { type: Array, required: true },
		searchQuery: { type: String, default: '' },
		reloadBus: { type: Object, required: true },
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
			sortOrder: 'asc',
			hideEmpty: false,
		}
	},

	computed: {
		filteredList() {
			let areas = this.contacts.filter(item => this.matchSearch(item.Nombre))

			if (this.hideEmpty) {
				areas = areas.filter(
					item => Number(item.cantidad_empleados) > 0
				)
			}

			areas.sort((a, b) => {
				if (this.sortOrder === 'asc') {
					return a.Nombre.localeCompare(b.Nombre)
				}

				return b.Nombre.localeCompare(a.Nombre)
			})

			return areas
		},
	},

	watch: {
		modal(newVal, oldVal) {
			if (oldVal !== newVal && newVal === true) {
				this.getallsAreas()
			}
		},
	},

	mounted() {
		this.query = this.searchQuery
	},

	methods: {
		// Exponer i18n a la plantilla
		t,

		matchSearch(nombre) {
			if (this.query.trim() !== '') {
				return nombre.toString().toLowerCase().includes(this.query.trim().toLowerCase())
			}
			return true
		},

		async getallsAreas() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetAreasFix'))
					.then(
						(response) => { this.options = response?.data?.ocs?.data },
						(err) => { showError(err) },
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		Exportar() {
			this.toggle()
			axios.get(generateUrl('/apps/empleados/ExportListAreas'), { responseType: 'blob' })
				.then(
					(response) => {
						const url = URL.createObjectURL(new Blob([response.data], {
							type: 'application/vnd.ms-excel',
						}))
						const link = document.createElement('a')
						link.href = url
						link.setAttribute('download', 'areas.xlsx')
						document.body.appendChild(link)
						link.click()
					},
					(err) => {
						showError(t('empleados', 'Se ha producido un error {error}, reporte al administrador', { error: String(err) }))
					},
				)
		},

		async importar() {
			this.toggle()
			const formData = new FormData()
			formData.append('AreafileXLSX', this.$refs.file.files[0])
			try {
				await axios.post(generateUrl('/apps/empleados/ImportListAreas'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				}).then(
					() => {
						this.$root.$emit('getall')
						this.$root.$emit('reload')
						this.$root.$emit('send-data-areas', [])
						showSuccess(t('empleados', 'Se actualizó la base de datos exitosamente'))
					},
					(err) => { showError(err) },
				)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
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
			const padreValor = (this.padre && this.padre.label) ? this.padre.label : ''
			if (this.nombre_area.trim() === '') {
				showError(t('empleados', 'The area/department name cannot be empty.'))
				return
			}
			try {
				await axios.post(generateUrl('/apps/empleados/crearArea'), {
					padre: padreValor,
					nombre: this.nombre_area,
				}).then(
					() => {
						showSuccess(t('empleados', 'Área creada exitosamente'))
						this.$root.$emit('reload')
						this.nombre_area = ''
						this.padre = null
						this.modal = false
					},
					(err) => { showError(err) },
				)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
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
