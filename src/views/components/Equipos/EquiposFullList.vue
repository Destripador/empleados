<template>
	<AppContentList class="content-list">
		<div class="contacts-list__header">
			<div class="search-contacts-field">
				<div class="container-search">
					<div class="input-container">
						<input
							v-model="query"
							type="text"
							:placeholder="t('empleados', 'Search teams...')">
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
								{{ t('empleados', 'Add new team') }}
							</NcActionButton>

							<NcActionButton @click="Exportar()">
								<template #icon>
									<DatabaseExport :size="20" />
								</template>
								{{ t('empleados', 'Export list / template') }}
							</NcActionButton>

							<NcActionSeparator />

							<NcActionButton :disabled="true"
								@click="$refs.file.click()">
								<template #icon>
									<Upload :size="20" />
								</template>
								{{ t('empleados', 'Import from template') }}
							</NcActionButton>
						</NcActions>
					</div>
				</div>
			</div>
		</div>

		<VirtualList
			ref="scroller"
			class="contacts-list"
			data-key="Id_equipo"
			:data-sources="filteredList"
			:data-component="EquiposListItem"
			:estimate-size="60"
			:extra-props="{ reloadBus }" />

		<input
			ref="file"
			type="file"
			style="display: none"
			accept=".xlsx"
			@change="importar()">

		<NcModal
			v-if="modal"
			ref="modalRef"
			:name="t('empleados', 'Add new team')"
			@close="closeModal">
			<div class="modal__content">
				<div class="form-group center">
					<NcTextField
						:value.sync="nombre_equipo"
						:label="t('empleados', 'Team name')" />
					<br>
					<NcSelect
						v-model="selected_user"
						:input-label="t('empleados', 'Manager team')"
						:options="optionsGestor"
						:user-select="true" />
					<br>
					<NcButton
						class="center"
						:aria-label="t('empleados', 'Save changes')"
						type="primary"
						@click="crearEquipo()">
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
	NcButton,
	NcSelect,
} from '@nextcloud/vue'
import EquiposListItem from './EquiposListItem.vue'
import VirtualList from 'vue-virtual-scroll-list'

export default {
	name: 'EquiposFullList',

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
		NcButton,
		NcSelect,
	},

	props: {
		list: { type: Array, required: true },
		contacts: { type: Array, required: true },
		searchQuery: { type: String, default: '' },
		reloadBus: { type: Object, required: true },
	},

	data() {
		return {
			query: '',
			modal: false,
			button: false,
			nombre_equipo: '',
			optionsGestor: [], // Usuarios para elegir jefe
			selected_user: null, // Usuario seleccionado como jefe
			EquiposListItem,
		}
	},

	computed: {
		filteredList() {
			return this.contacts.filter(item => this.matchSearch(item?.Nombre ?? ''))
		},
	},

	watch: {
		modal(newVal, oldVal) {
			// Si abres/cerras, podrías recargar data auxiliar si hiciera falta
			if (newVal !== oldVal && !newVal) {
				// Al cerrar, limpia selección
				this.nombre_equipo = ''
				this.selected_user = null
			}
		},
	},

	mounted() {
		this.query = this.searchQuery
	},

	methods: {
		// Exponer t en plantilla
		t,

		matchSearch(name) {
			const q = this.query.trim().toLowerCase()
			if (!q) return true
			return String(name).toLowerCase().includes(q)
		},

		async cargarUsuariosParaJefe() {
			// reutilizamos el endpoint global de configuraciones para traer Users
			const resp = await axios.get(generateUrl('/apps/empleados/GetConfigurations'))
			this.optionsGestor = resp.data?.Users ?? []
		},

		async Exportar() {
			this.toggle()
			try {
				const { data } = await axios.get(
					generateUrl('/apps/empleados/ExportListEquipos'),
					{ responseType: 'blob' },
				)
				const url = URL.createObjectURL(new Blob([data], { type: 'application/vnd.ms-excel' }))
				const link = document.createElement('a')
				link.href = url
				link.setAttribute('download', 'equipos.xlsx')
				document.body.appendChild(link)
				link.click()
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		async importar() {
			this.toggle()
			const formData = new FormData()
			formData.append('equipofileXLSX', this.$refs.file.files[0])
			try {
				await axios.post(
					generateUrl('/apps/empleados/ImportListEquipos'),
					formData,
					{ headers: { 'Content-Type': 'multipart/form-data' } },
				)
				this.$root.$emit('getall')
				this.$root.$emit('reload')
				this.$root.$emit('send-data-equipos', {})
				showSuccess(t('empleados', 'Se actualizo la base de datos exitosamente'))
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},

		async AgregarNuevo() {
			this.toggle()
			try {
				await this.cargarUsuariosParaJefe()
				this.modal = true
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		closeModal() {
			this.modal = false
		},

		toggle() {
			this.button = !this.button
		},

		async crearEquipo() {
			if (!this.nombre_equipo) {
				showError(t('empleados', 'Por favor, complete los campos requeridos'))
				return
			}
			if (!this.selected_user || !this.selected_user.id) {
				showError(t('empleados', 'Seleccione un jefe de equipo'))
				return
			}

			try {
				await axios.post(generateUrl('/apps/empleados/crearEquipo'), {
					nombre: this.nombre_equipo,
					jefe: this.selected_user.id,
				})
				showSuccess(t('empleados', 'Equipo creado exitosamente'))
				this.$root.$emit('reload')
				this.nombre_equipo = ''
				this.selected_user = null
				this.modal = false
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
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
