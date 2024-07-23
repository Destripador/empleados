<template>
	<AppContentList class="content-list">
		<div class="contacts-list__header">
			<div class="search-contacts-field">
				<div class="container-search">
					<div class="input-container">
						<input v-model="query" type="text" :placeholder="t('empleados', 'Buscar empleados...')">
					</div>
					<div class="button-container">
						<NcActions>
							<NcActionButton @click="showMessage('Edit')">
								<template #icon>
									<Pencil :size="20" />
								</template>
								Exportar listado completo
							</NcActionButton>
							<NcActionButtonGroup name="Text alignment">
								<NcActionButton aria-label="Align left"
									v-model="alignment"
									type="radio"
									value="l">
									<template #icon>
										<AlignLeft :size="20" />
									</template>
								</NcActionButton>
								<NcActionButton aria-label="Align center"
									v-model="alignment"
									type="radio"
									value="c">
									<template #icon>
										<AlignCenter :size="20" />
									</template>
								</NcActionButton>
								<NcActionButton aria-label="Align right"
									v-model="alignment"
									type="radio"
									value="r">
									<template #icon>
										<AlignRight :size="20" />
									</template>
								</NcActionButton>
							</NcActionButtonGroup>
						</NcActions>
					</div>
				</div>
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
import {
	NcAppContentList as AppContentList,
	NcActions,
	NcActionButton,
	NcActionButtonGroup,
} from '@nextcloud/vue'
import ContactsListItem from './ContactsListItem.vue'
import VirtualList from 'vue-virtual-scroll-list'

export default {
	name: 'ContactList',

	components: {
		AppContentList,
		VirtualList,
		NcActions,
		NcActionButton,
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

.container-search {
            display: flex;
        }
        .input-container {
            flex: 1;
            margin-right: 10px;
        }
        .input-container input {
            width: 100%;
        }
        .button-container button {
            width: 100%;
        }
</style>
