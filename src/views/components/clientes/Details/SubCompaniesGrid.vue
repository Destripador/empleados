<template>
	<div v-if="items.length > 0" class="children-grid">
		<button v-for="item in items"
			:key="item.key"
			type="button"
			class="child-card"
			@click="$emit('select', item.id)">
			<div class="child-icon">
				<OfficeBuilding :size="20" />
			</div>

			<div class="child-info">
				<span class="value-text">{{ item.title }}</span>
				<span>{{ item.subtitle }}</span>
			</div>

			<div v-if="item.badge !== undefined" class="child-count">
				{{ item.badge }}
			</div>
		</button>
	</div>

	<NcEmptyContent v-else-if="emptyLabel"
		:name="emptyLabel"
		:description="emptyDescription">
		<template #icon>
			<OfficeBuilding />
		</template>
	</NcEmptyContent>
</template>

<script>
import OfficeBuilding from 'vue-material-design-icons/OfficeBuilding.vue'
import { NcEmptyContent } from '@nextcloud/vue'

export default {
	name: 'SubCompaniesGrid',

	components: { OfficeBuilding, NcEmptyContent },

	props: {
		// [{ key, id, title, subtitle, badge }]
		items: { type: Array, default: () => [] },
		emptyLabel: { type: String, default: '' },
		emptyDescription: { type: String, default: '' },
	},
}
</script>

<style scoped lang="scss">
.children-grid {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.child-card {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 14px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-soft);
	border: 1px solid var(--color-border);
	cursor: pointer;
	text-align: left;
	width: 100%;
	transition: background 0.15s ease, border-color 0.15s ease;

	&:hover {
		background: var(--color-background-hover);
		border-color: var(--color-primary-element);
	}
}

.child-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 36px;
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	flex-shrink: 0;
}

.child-info {
	display: flex;
	flex-direction: column;
	gap: 2px;
	flex: 1;
	min-width: 0;

	.value-text {
		font-size: 0.875rem;
		font-weight: 600;
		color: var(--color-main-text);
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	span {
		font-size: 0.75rem;
		color: var(--color-text-maxcontrast);
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
}

.child-count {
	font-size: 0.75rem;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	flex-shrink: 0;
}
</style>
