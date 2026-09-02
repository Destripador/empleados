<template>
	<AppContentList class="content-list">
		<div class="contacts-list__header">
			<div class="search-contacts-field">
				<div class="container-search">
					<div class="input-container">
						<input v-model="query" type="text" :placeholder="t('empleados', 'Search employees...')">
					</div>
					<div class="filters-container">
						<NcButton
							class="filter-icon-button"
							type="tertiary"
							:title="t('empleados', 'Filters')"
							@click.stop="toggleFilters">
							<template #icon>
								<FilterVariant :size="20" />
							</template>
							{{ t('empleados') }}
							<span v-if="showDeactivated" class="filter-badge">1</span>
						</NcButton>

						<div v-if="showFilters" class="filter-dropdown" @click.stop>
							<div class="filter-section">
								<label>
									<input
										v-model="showDeactivated"
										type="checkbox"
										@change="onToggleDeactivated">
									{{ t('empleados', 'Show deactivated employees') }}
								</label>
							</div>
						</div>
					</div>
					<div class="button-container">
						<NcButton
							class="org-chart-mobile-button"
							type="tertiary"
							:aria-label="t('empleados', 'View org chart')"
							:title="t('empleados', 'View org chart')"
							@click="showOrgChart">
							<template #icon>
								<Sitemap :size="20" />
							</template>
						</NcButton>

						<NcActions>
							<template #icon>
								<Cog :size="20" />
							</template>

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

		<p v-if="loadingDeactivated" class="deactivated-loading">
			{{ t('empleados', 'Loading deactivated employees…') }}
		</p>

		<VirtualList
			ref="scroller"
			class="contacts-list"
			data-key="Id_empleados"
			:data-sources="filteredList"
			:data-component="EmployeeListItem"
			:estimate-size="60" />

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
	NcButton,
} from '@nextcloud/vue'

import { showError, showSuccess } from '@nextcloud/dialogs'
import EmployeeListItem from './EmployeeListItem.vue'
import VirtualList from 'vue-virtual-scroll-list'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import DatabaseExport from 'vue-material-design-icons/DatabaseExport.vue'
import Upload from 'vue-material-design-icons/Upload.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import FilterVariant from 'vue-material-design-icons/FilterVariant.vue'
import Sitemap from 'vue-material-design-icons/Sitemap.vue'

export default {
	name: 'ContentList',

	components: {
		AppContentList,
		VirtualList,
		NcActions,
		NcActionButton,
		Cog,
		Upload,
		DatabaseExport,
		NcActionSeparator,
		NcButton,
		FilterVariant,
		Sitemap,
	},

	props: {
		employees: {
			type: Array,
			required: true,
		},
		searchQuery: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			EmployeeListItem,
			query: '',
			showFilters: false,
			showDeactivated: false,
			deactivatedEmployees: [],
			loadingDeactivated: false,
			deactivatedLoaded: false,
		}
	},

	computed: {
		filteredList() {
			const base = this.showDeactivated
				? [...this.employees, ...this.deactivatedEmployees]
				: this.employees

			return base.filter(item => this.matchSearch(item.displayname, item.uid))
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
		// expone t al template y métodos
		t,

		toggleFilters() {
			this.showFilters = !this.showFilters
		},

		// Abre el organigrama en el panel de detalle (usado sobre todo en
		// móvil, donde ese panel está oculto por defecto).
		showOrgChart() {
			this.$bus.emit('show-org-chart')
		},

		async onToggleDeactivated() {
			if (this.showDeactivated && !this.deactivatedLoaded) {
				await this.loadDeactivated()
			}
		},

		async loadDeactivated() {
			this.loadingDeactivated = true
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetUserLists'))
				const data = response?.data?.ocs?.data
				const desactivados = Array.isArray(data?.Desactivados) ? data.Desactivados : []

				this.deactivatedEmployees = desactivados.map(item => ({
					...item,
					Id_empleados: item.Id_empleados,
					uid: item.uid || item.Id_user,
					displayname: item.displayname || item.uid || item.Id_user,
					isInactive: true,
				}))
				this.deactivatedLoaded = true
			} catch (err) {
				showError(t('empleados', 'Could not load deactivated employees [{error}]', { error: String(err) }))
				this.showDeactivated = false
			} finally {
				this.loadingDeactivated = false
			}
		},

		matchSearch(displayname, uid) {
			try {
				if (this.query.trim() !== '') {
					return displayname.toString().toLowerCase().includes(this.query.trim().toLowerCase())
				}
			} catch (error) {
				if (this.query.trim() !== '') {
					return uid.toString().toLowerCase().includes(this.query.trim().toLowerCase())
				}
			}
			return true
		},

		Exportar() {
			axios.get(generateUrl('/apps/empleados/ExportListEmpleados'), { responseType: 'blob' })
				.then((response) => {
					const url = URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.ms-excel' }))
					const link = document.createElement('a')
					link.href = url
					link.setAttribute('download', 'empleados.xlsx')
					document.body.appendChild(link)
					link.click()
				})
				.catch((err) => {
					showError(t('empleados', 'An error occurred {error}, please report to the administrator', { error: String(err) }))
				})
		},

		async importar() {
			const file = this.$refs.file?.files?.[0]
			if (!file) return

			const formData = new FormData()
			formData.append('fileXLSX', file)

			try {
				await axios.post(generateUrl('/apps/empleados/ImportListEmpleados'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				})
				this.$bus?.emit('getall')
				showSuccess(t('empleados', 'Database updated successfully'))
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [03] [{error}]', { error: String(err) }))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
// Mismo patrón que AreasFullList.vue / PuestosFullList.vue: grid de una
// sola fila que se va encogiendo, sin reflow a 2 filas en móvil.
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
	width: 220px;
	max-width: calc(100vw - 24px);
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

.filter-section label {
	display: inline-flex;
	align-items: center;
	min-height: 26px;
	gap: 5px;
	background-color: var(--color-main-background);
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.3;
}

.filter-section input[type='checkbox'] {
	width: 13px;
	height: 13px;
	margin: 0;
	flex-shrink: 0;
}

.deactivated-loading {
	padding: 8px 12px;
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

// Make virtual scroller scrollable
.contacts-list {
	max-height: calc(100vh - var(--header-height) - 48px);
	max-height: calc(100dvh - var(--header-height) - 48px);
	overflow: auto;
	-webkit-overflow-scrolling: touch;
}

// Add empty header to contacts-list that solves overlapping of contacts with app-navigation-toogle
.contacts-list__header {
	min-height: 48px;
}

// Search field: mismo padding-left de 50px que Areas/Puestos para
// dejarle espacio al botón de hamburguesa.
.search-contacts-field {
	padding: 5px 10px 5px 50px;
	margin-top: 4px;

	> input {
		width: 100%;
	}
}

.content-list {
	overflow-y: auto;
	overflow-x: hidden;
	padding: 0 4px;
}

.input-container {
	grid-area: input;
	min-width: 0;
}
.input-container input {
	width: 100%;
	box-sizing: border-box;
	min-width: 0;
}

.button-container {
	grid-area: button;
	display: flex;
	align-items: center;
	gap: 4px;
	flex-shrink: 0;
}

// Botón de organigrama: oculto en escritorio (ahí ya se ve el organigrama
// directamente en el panel de detalle), visible solo en móvil.
.org-chart-mobile-button {
	display: none;
	flex: 0 0 auto;
}

@media (max-width: 900px) {
	.org-chart-mobile-button {
		display: inline-flex;
	}
}

// Igual que Areas/Puestos: botones compactos para que quepan
// filtro + organigrama + engranaje en una sola línea.
.filter-icon-button {
	min-width: unset !important;
	padding-left: 4px !important;
	padding-right: 4px !important;
	flex-shrink: 0;
}

// ============ RESPONSIVE ============
@media (max-width: 600px) {
	.search-contacts-field {
		padding: 5px 8px 5px 46px;
	}

	.container-search {
		gap: 6px 4px;
	}

	.filter-dropdown {
		right: -8px;
		width: min(240px, calc(100vw - 16px));
	}
}
</style>
