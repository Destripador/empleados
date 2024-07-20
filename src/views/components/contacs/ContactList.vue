<template>
	<AppContentList class="content-list">
		<div class="contacts-list__header">
			<div class="search-contacts-field">
				<input v-model="query" type="text" :placeholder="t('empleados', 'Buscar empleados...')">
			</div>
		</div>
		<VirtualList ref="scroller"
			class="contacts-list"
			data-key="Id_empleados"
			:data-sources="filteredList"
			:data-component="ContactsListItem"
			:estimate-size="60"
			:extra-props="{reloadBus}" />
	</AppContentList>
</template>

<script>
import { NcAppContentList as AppContentList } from '@nextcloud/vue'
import ContactsListItem from './ContactsListItem.vue'
import VirtualList from 'vue-virtual-scroll-list'

export default {
	name: 'ContactList',

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
