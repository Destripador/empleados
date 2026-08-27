<template>
	<p v-if="items.length === 0" class="empty-state">
		{{ t('empleados', 'No data for this selection.') }}
	</p>
	<ol v-else class="ranking-list">
		<li v-for="item in items" :key="item.key">
			<div>
				<strong>{{ item.label }}</strong>
				<span>{{ item.hoursText }} · {{ item.percentageText }}</span>
			</div>
			<div class="bar">
				<span :style="{ width: `${item.width}%` }" />
			</div>
		</li>
	</ol>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'AdminRankingList',
	props: {
		items: { type: Array, default: () => [] },
	},
	methods: { t },
}
</script>

<style scoped>
.ranking-list {
	display: grid;
	gap: 12px;
	margin: 0;
	padding: 0;
	list-style: none;
}
.ranking-list li { display: grid; gap: 6px; }
.ranking-list li > div:first-child { display: flex; justify-content: space-between; gap: 12px; }
.bar { height: 6px; overflow: hidden; border-radius: 999px; background: var(--color-border); }
.bar span { display: block; height: 100%; background: var(--color-primary-element); }
.empty-state { color: var(--color-text-maxcontrast); }
</style>
