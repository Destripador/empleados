<template>
	<div class="contacts-list__item-wrapper" :class="{ 'item--especial': Number(source.especial) === 1, 'item--billable': Number(source.cargable) === 1, 'item--disabled': Number(source.estado ?? 1) === 0}">
		<ListItem
			:compact="true"
			class="list-item-style envelope"
			:name="source.name"
			:counter-number="source.count"
			@click.prevent="showDetails(source)">
			<template v-if="source.logoUrl || source.logo" #icon>
				<div class="app-content-list-item-icon">
					<img
						v-if="source.logoUrl && !logoFailed"
						:src="source.logoUrl"
						:alt="source.name"
						class="client-list-logo"
						@error="logoFailed = true">
					<span v-else class="client-list-logo client-list-logo--placeholder" />
				</div>
			</template>
			<template v-else-if="source.image" #icon>
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

	data() {
		return {
			logoFailed: false,
		}
	},

	watch: {
		'source.id'() {
			this.logoFailed = false
		},
		'source.logoUrl'() {
			this.logoFailed = false
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

.client-list-logo {
	width: 40px;
	height: 40px;
	object-fit: contain;
	border-radius: var(--border-radius);
	background: var(--color-main-background);
}

.client-list-logo--placeholder {
	display: block;
	background: var(--color-primary-element-light);
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
	background: linear-gradient(135deg, #3b82f622 0%, var(--color-main-background) 30%);
	border-radius: 8px;
	border-left: 3px solid #8db5f5;
}

.item--billable {
	background: linear-gradient(135deg, #22c55e22 0%, var(--color-main-background) 30%);
	border-radius: 8px;
	border-left: 3px solid #6ee09a;
}

.item--disabled {
	background: linear-gradient(135deg, rgba(0, 2, 1, 0.13) 0%, var(--color-main-background) 30%);
	opacity: 0.5;
	border-radius: 8px;
	border-left: 3px solid var(--color-border-dark);
	filter: grayscale(40%);
}
</style>
