<template>
	<AppContentList class="content-list">
		<div class="contacts-list__header">
			<div class="search-contacts-field">
				<input v-model="query" type="text" :placeholder="t('empleados', 'Buscar areas...')">
			</div>
		</div>
		<VirtualList ref="scroller"
			class="contacts-list"
			data-key="Id_departamento"
			:data-sources="filteredList"
			:data-component="AreasListItem"
			:estimate-size="60"
			:extra-props="{reloadBus}" />
	</AppContentList>
</template>

<script>
import { NcAppContentList as AppContentList } from '@nextcloud/vue'
import AreasListItem from './AreasListItem.vue'
import VirtualList from 'vue-virtual-scroll-list'

export default {
	name: 'AreasFullList',

	components: {
		AppContentList,
		VirtualList,
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
		}
	},

	computed: {
		filteredList() {
			return this.contacts
				.filter(item => this.matchSearch(item.Nombre))
		},
	},

	mounted() {
		// eslint-disable-next-line no-console
		console.log('contac: ', this.contacts)
		this.query = this.searchQuery
	},

	methods: {
		matchSearch(areas) {
			// eslint-disable-next-line no-console
			console.log(areas)
			if (this.query.trim() !== '') {
				return areas.toString().toLowerCase().search(this.query.trim().toLowerCase()) !== -1
			}
			return true
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

</style>
