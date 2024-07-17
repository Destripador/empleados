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
			:estimate-size="68"
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

			// eslint-disable-next-line no-console
			console.log('Compi: ', this.contacts
				.filter(item => this.matchSearch(item.displayname)))

			return this.contacts
				.filter(item => this.matchSearch(item.displayname))
		},
	},

	watch: {
		selectedContact(key) {
			this.$nextTick(() => {
				this.scrollToContact(key)
			})
		},
		list(val, old) {
			// we just loaded the list and the url already have a selected contact
			// if not, the selectedContact watcher will take over
			// to select the first entry
			if (val.length !== 0 && old.length === 0 && this.selectedContact) {
				this.$nextTick(() => {
					this.scrollToContact(this.selectedContact)
				})
			}
		},
	},

	mounted() {
		this.query = this.searchQuery
	},

	methods: {
		// Select closest contact on deletion
		selectContact(oldIndex) {
			if (this.list.length > 0 && oldIndex < this.list.length) {
				// priority to the one above then the one after
				const newContact = oldIndex === 0 ? this.list[oldIndex + 1] : this.list[oldIndex - 1]
				if (newContact) {
					this.$router.push({ name: 'contact', params: { selectedGroup: this.selectedGroup, selectedContact: newContact.key } })
				}
			}
		},

		/**
		 * Scroll to the desired contact if in the list and not visible
		 *
		 * @param {string} key the contact unique key
		 */
		scrollToContact(key) {
			const item = this.$el.querySelector('#' + btoa(key).slice(0, -2))

			// if the item is not visible in the list or barely visible
			if (!(item && item.getBoundingClientRect().y > 50)) { // header height
				const index = this.list.findIndex(contact => contact.key === key)
				if (index > -1) {
					this.$refs.scroller.scrollToIndex(index)
				}
			}

			// if item is a bit out (bottom) of the list, let's just scroll a bit to the top
			if (item) {
				const pos = item.getBoundingClientRect().y + this.itemHeight - (this.$el.offsetHeight + 50)
				if (pos > 0) {
					const scroller = this.$refs.scroller.$el
					scroller.scrollToOffset(scroller.scrollTop + pos)
				}
			}
		},

		matchSearch(contacts) {

			// eslint-disable-next-line no-console
			console.log('doña: ', contacts.toString().toLowerCase().search(this.query.trim().toLowerCase()))

			if (this.query.trim() !== '') {
				return contacts.toString().toLowerCase().search(this.query.trim().toLowerCase()) !== -1
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
