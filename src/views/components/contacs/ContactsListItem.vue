<template>
	<div class="contacts-list__item-wrapper">
		<ListItem
			:key="source.Id_empleados"
			class="list-item-style envelope"
			:name="source.uid"
			@click="showDetails(source)">
			<template #icon>
				<div class="app-content-list-item-icon">
					<BaseAvatar
						:display-name="source.uid"
						:user="source.uid"
						:show-user-status="false"
						:size="40" />
				</div>
			</template>
			<template v-if="source.displayname" #name>
				{{ source.displayname }}
			</template>
			<template v-else #name>
				{{ source.uid }}
			</template>
		</ListItem>
	</div>
</template>

<script>

import {
	NcListItem as ListItem,
	NcAvatar as BaseAvatar,
} from '@nextcloud/vue'

export default {
	name: 'ContactsListItem',

	components: {
		ListItem,
		BaseAvatar,
	},

	props: {
		index: {
			type: Number,
			required: true,
		},
		source: {
			type: Object,
			required: true,
		},
		reloadBus: {
			type: Object,
			required: true,
		},
	},

	methods: {
		showDetails(data) {
			this.$root.$emit('send-data', data)
			this.$root.$emit('show', false)
		},
	},
}
</script>
<style lang="scss" scoped>

.envelope {
	.app-content-list-item-icon {
		height: 40px; // To prevent some unexpected spacing below the avatar
	}

	&__subtitle {
		display: flex;
		gap: 4px;

		&__subject {
			color: var(--color-main-text);
			line-height: 130%;
			overflow: hidden;
			text-overflow: ellipsis;
		}
	}
}

.list-item-style {
	list-style: none;
}

</style>
<style lang="scss">
.contacts-list__item-wrapper {
	&[draggable='true'] .avatardiv * {
		cursor: move !important;
	}

	&[draggable='false'] .avatardiv * {
		cursor: not-allowed !important;
	}
}
</style>
