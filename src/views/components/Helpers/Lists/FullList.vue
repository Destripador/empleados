<template>
	<AppContentList class="content-list">
		<div class="contacts-list__header">
			<div class="search-contacts-field">
				<div class="container-search">
					<div class="input-container">
						<input v-model="query" type="text" :placeholder="t('empleados', 'Search...')">
					</div>
					<slot name="custombuttons" />
					<div v-if="defaultbuttons" class="button-container">
						<NcActions :open="button" @click="toggle">
							<template #icon>
								<Cog :size="20" />
							</template>
							<NcActionButton @click="AgregarNuevo()">
								<template #icon>
									<AccountMultiplePlusOutline :size="20" />
								</template>
								{{ t('empleados', 'Add new') }}
							</NcActionButton>

							<NcActionButton @click="Exportar()">
								<template #icon>
									<DatabaseExport :size="20" />
								</template>
								{{ t('empleados', 'Export list') }}
							</NcActionButton>

							<NcActionSeparator />

							<NcActionButton @click="importar()">
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
			class="contacts-list virtual-contacts-list"
			data-key="id"
			:data-sources="filteredList"
			:data-component="ListItems"
			:estimate-size="60"
			:extra-props="{ reloadBus }" />
	</AppContentList>
</template>

<script>
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
} from '@nextcloud/vue'
import ListItems from './ListItems.vue'
import VirtualList from 'vue-virtual-scroll-list'

export default {
	name: 'FullList',

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
	},

	props: {
		listas: { type: Array, required: true },
		reloadBus: { type: Object, required: true },
		searchQuery: { type: String, default: '' },
		defaultbuttons: { type: Boolean, default: true, required: false },
	},

	data() {
		return {
			ListItems,
			query: '',
			modal: false,
			button: false,
			options: [],
		}
	},

	computed: {
		filteredList() {
			return this.listas.filter(item => this.matchSearch(item.name))
		},
	},

	mounted() {
		this.query = this.searchQuery
	},

	methods: {
		t, // Exponer i18n a la plantilla
		matchSearch(name) {
			if (this.query.trim() !== '') {
				return name.toString().toLowerCase().includes(this.query.trim().toLowerCase())
			}
			return true
		},

		Exportar() {
			this.toggle()
			this.$root.$emit('exportlist')
		},

		importar() {
			this.toggle()
			this.$root.$emit('importlist')
		},

		AgregarNuevo() {
			this.toggle()
			this.$root.$emit('new', true)
		},

		toggle() {
			this.button = !this.button
		},
	},
}
</script>

<style lang="scss" scoped>
.contacts-list {
	height: calc(100vh - var(--header-height) - 48px);
	min-height: 0;
	overflow-y: auto;
	overflow-x: hidden;
}

.contacts-list__header {
	flex: 0 0 auto;
	min-height: 48px;
	position: relative;
	z-index: 10;
	overflow: visible;
}

// Search field
.search-contacts-field {
    padding: 5px 2px 5px 8px;
    margin-top: 4px;

    > input {
        width: 100%;
    }
}

.content-list {
	position: relative;
	z-index: 20;
	overflow: visible !important;
	padding: 0 4px;
	display: flex;
	flex-direction: column;
	min-height: 0;
	height: 100%;
}

.container-search {
	display: flex;
	flex-wrap: nowrap;
	align-items: center;
	gap: 6px;
	overflow: visible;
}

.input-container {
	flex: 1 1 auto;
	min-width: 0;
	margin-right: 5px;
	margin-left: 0;
}

.input-container input {
	width: 100%;
}

.button-container {
	flex: 0 0 auto;
}

.button-container button {
	width: 100%;
}

// ============ RESPONSIVE ============
@media (max-width: 500px) {
	.input-container {
		flex-basis: 100%;
		margin-right: 0;
	}

	.button-container {
		margin-left: auto;
	}
}

@media (max-width: 900px) {
	.input-container {
		margin-left: 42px;
	}
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

.virtual-contacts-list {
	flex: 1 1 auto;
	min-height: 0;
}
</style>
