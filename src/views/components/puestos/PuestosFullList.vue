<template>
	<AppContentList class="content-list">
		<div class="contacts-list__header">
			<div class="search-contacts-field">
				<div class="container-search">
					<div class="input-container">
						<input v-model="query" type="text" :placeholder="t('empleados', 'Search positions...')">
					</div>
					<div class="filters-container">
						<NcButton
							class="filter-icon-button"
							type="tertiary"
							@click.stop="toggleFilters"
							:title="t('empleados', 'Filters')">
							<template #icon>
								<FilterVariant :size="20" />
							</template>
							{{ t('empleados') }}
							<span v-if="hideEmpty" class="filter-badge">1</span>
						</NcButton>

						<div v-if="showFilters" class="filter-dropdown" @click.stop>
							<div class="filter-section">
								<p class="filter-section-label">{{ t('empleados', 'Sort') }}</p>
								<select v-model="sortOrder">
									<option value="asc">A-Z</option>
									<option value="desc">Z-A</option>
								</select>
							</div>

							<hr class="filter-divider">

							<div class="filter-section">
								<label>
									<input v-model="hideEmpty" type="checkbox">
									{{ t('empleados', 'Hide empty') }}
								</label>
							</div>
						</div>
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
			:name="t('empleados', 'Add new Position')"
			@close="closeModal">
			<div class="modal__content">
				<div class="form-group center">
					<NcTextField :value.sync="nombre_area"
						:label="t('empleados', 'Position name')" />
					<br>
					<NcTextField :value.sync="nivel_area"
						type="number"
						:label="t('empleados', 'Level')" />
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
import FilterVariant from 'vue-material-design-icons/FilterVariant.vue'

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
		FilterVariant,
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
			nivel_area: '',
			sortOrder: 'asc',
			hideEmpty: false,
			showFilters: false,
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
		this._onClickOutside = (event) => {
			const wrap = this.$el.querySelector('.filters-container')
			if (wrap && !wrap.contains(event.target)) {
				this.showFilters = false
			}
		}
		document.addEventListener('click', this._onClickOutside)
	},

	beforeDestroy() {
		document.removeEventListener('click', this._onClickOutside)
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
			this.nivel_area = ''
		},
		toggle() {
			this.button = !this.button
		},
		toggleFilters() {
			this.showFilters = !this.showFilters
		},
		async crearPuesto() {
			try {
				await axios.post(generateUrl('/apps/empleados/crearPuesto'),
					{
						nombre: this.nombre_area,
						nivel: this.nivel_area !== '' ? Number(this.nivel_area) : null,
					})
					.then(
						(response) => {
							showSuccess(t('empleados', 'Position created successfully'))
							this.$root.$emit('reload')
							this.nombre_area = ''
							this.nivel_area = ''
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
.container-search {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    grid-template-areas: "input filters button";
    align-items: center;
    gap: 6px 4px;
}
.filters-container {
    position: relative;
    display: inline-flex;
    align-items: center;
    grid-area: filters;
    margin: 0;
    overflow: visible;
}

.filter-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 18px;
	height: 18px;
	padding: 0 5px;
	margin-left: 4px;
	border-radius: 999px;
	background-color: var(--color-primary);
	color: #fff;
	font-size: 11px;
	font-weight: 600;
}

.filter-dropdown {
	position: absolute;
	top: calc(100% + 6px);
	right: 0;
	z-index: 100000;
	width: 190px;
	box-sizing: border-box;
	padding: 6px 0;
	overflow: hidden;
	border: 1px solid rgba(0, 0, 0, 0.28);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}

.filter-section {
	display: flex;
	flex-direction: column;
	gap: 4px;
	box-sizing: border-box;
	width: 100%;
	padding: 6px 10px;
}

.filter-section-label {
	margin: 0 0 4px;
	color: var(--color-text-maxcontrast);
	font-size: 10px;
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.filter-section select {
	width: 100%;
	height: 28px;
	box-sizing: border-box;
	padding: 1px 22px 1px 7px;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 12px;
}

.filter-section label {
	display: inline-flex;
	align-items: center;
	min-height: 26px;
	gap: 5px;
	background-color: var(--color-main-background);
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1;
}

.filter-section input[type='checkbox'] {
	width: 13px;
	height: 13px;
	margin: 0;
}

.filter-divider {
	margin: 2px 0;
	border: none;
	border-top: 1px solid var(--color-border);
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

.filter-icon-button {
	min-width: unset !important;
	padding-left: 4px !important;
	padding-right: 4px !important;
}
</style>
