<template>
	<div class="contacts-list__item-wrapper" :class="{ 'item--especial': Number(source.especial) === 1 }">
		<ListItem
			:compact="true"
			class="list-item-style envelope"
			:name="source.name"
			:counter-number="source.count"
			@click.prevent="showDetails(source)">
			<template v-if="source.image" #icon>
				<div class="app-content-list-item-icon">
					<BaseAvatar
						:display-name="source.image"
						:user="source.image"
						:size="40" />
				</div>
			</template>
			<template v-if="source.subname" #subname>
				<small>{{ source.subname }}</small>
			</template>
		</ListItem>
	</div>
</template>

<script>
import {
	NcListItem as ListItem,
	NcAvatar as BaseAvatar,
} from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'ListItems',

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
		t, // exponer i18n a la plantilla
		showDetails(data) {
			this.$root.$emit('details', data.id)
		},
	},
}
</script>

<style lang="scss" scoped>
.envelope {
	.app-content-list-item-icon {
		height: 40px; // evita espacio extra bajo el avatar
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

.item--especial {
    background: linear-gradient(135deg, #3b82f622 0%, #ffffffef 30%);
    border-radius: 8px;
    border-left: 1px solid #8db5f5;
}
</style>
