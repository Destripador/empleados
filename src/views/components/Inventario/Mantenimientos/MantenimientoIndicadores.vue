<template>
	<div class="indicators" aria-live="polite">
		<div v-for="item in items" :key="item.key" class="indicator">
			<span>{{ item.label }}</span><strong>{{ item.value }}</strong>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
export default {
	name: 'MantenimientoIndicadores',
	props: { groups: { type: Array, default: () => [] }, overdue: { type: Number, default: 0 } },
	computed: {
		items() {
			const totals = { pending: 0, in_progress: 0, completed: 0 }
			let hasProgress = false
			const groups = [...new Map(this.groups.map(group => [String(group.id), group])).values()]
			for (const group of groups) {
				const progress = group.progress || group
				hasProgress = hasProgress || ['pending', 'in_progress', 'completed'].some(key => Object.prototype.hasOwnProperty.call(progress, key))
				totals.pending += Number(progress.pending || progress.pendientes || 0)
				totals.in_progress += Number(progress.in_progress || progress.en_proceso || 0)
				totals.completed += Number(progress.completed || progress.completados || 0)
			}
			return [
				{ key: 'scheduled', label: t('empleados', 'Campaigns in visible range'), value: groups.length },
				{ key: 'pending', label: t('empleados', 'Pending'), value: hasProgress ? totals.pending : '—' },
				{ key: 'in_progress', label: t('empleados', 'In progress'), value: hasProgress ? totals.in_progress : '—' },
				{ key: 'completed', label: t('empleados', 'Completed'), value: hasProgress ? totals.completed : '—' },
				{ key: 'overdue', label: t('empleados', 'Overdue'), value: this.overdue },
			]
		},
	},
}
</script>

<style scoped lang="scss">
.indicators { display: grid; grid-template-columns: repeat(auto-fit, minmax(135px, 1fr)); gap: 12px; }
.indicator { padding: 14px; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); background: var(--color-main-background); }
.indicator span { display: block; color: var(--color-text-maxcontrast); }
.indicator strong { display: block; margin-top: 4px; font-size: 1.5rem; }
</style>
