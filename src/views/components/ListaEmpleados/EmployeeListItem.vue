<template>
	<div class="contacts-list__item-wrapper">
		<ListItem
			:key="source.Id_empleados"
			class="list-item-style envelope"
			:class="{ 'envelope--inactive': source.isInactive }"
			:name="source.uid"
			@click.prevent="showDetails(source)">
			<template #icon>
				<div class="app-content-list-item-icon">
					<BaseAvatar
						:display-name="source.uid"
						:user="source.uid"
						:size="40" />
				</div>
			</template>
			<template v-if="source.displayname" #name>
				{{ source.displayname }}
				<span v-if="source.isInactive" class="inactive-badge">{{ t('empleados', 'Inactive') }}</span>
			</template>
			<template v-else #name>
				{{ source.uid }}
				<span v-if="source.isInactive" class="inactive-badge">{{ t('empleados', 'Inactive') }}</span>
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
	name: 'EmployeeListItem',
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
	},
	methods: {
		t,
		showDetails(data) {
			this.$bus.emit('send-data', data)
			this.$bus.emit('show', false)
		},
	},
}
</script>

<style lang="scss" scoped>
.envelope {
	.app-content-list-item-icon { height: 40px; }
	&--inactive {
		opacity: 0.55;
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
.inactive-badge {
	display: inline-flex;
	align-items: center;
	margin-left: 6px;
	padding: 1px 7px;
	border-radius: 999px;
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 10px;
	font-weight: 700;
	text-transform: uppercase;
}
.list-item-style { list-style: none; }
</style>

<style lang="scss">
.contacts-list__item-wrapper {
	&[draggable='true'] .avatardiv * { cursor: move !important; }
	&[draggable='false'] .avatardiv * { cursor: not-allowed !important; }
}
</style>
