<template>
	<NcModal :name="t('empleados', 'Potential duplicate maintenance')" @close="$emit('review')">
		<div class="dialog">
			<h2>{{ t('empleados', 'Potential duplicate maintenance') }}</h2><p>{{ t('empleados', '{count} conflicting devices were found.', { count: conflicts.length }) }}</p><div class="table-wrap">
				<table>
					<thead><tr><th>{{ t('empleados', 'Device') }}</th><th>{{ t('empleados', 'Existing campaign') }}</th><th>{{ t('empleados', 'Campaign period') }}</th><th>{{ t('empleados', 'Type') }}</th><th>{{ t('empleados', 'Status') }}</th></tr></thead><tbody>
						<tr v-for="(item, index) in conflicts" :key="item.id || index">
							<td>{{ item.equipo_identificador || item.equipmentId || item.id_equipo || '—' }}</td><td>{{ item.grupo_titulo || item.groupId || item.id_grupo || '—' }}</td><td>{{ formatDateRange(item.existingPeriodStart || item.fecha_programada, item.existingPeriodEnd || item.fecha_programada) }}</td><td>{{ item.type || item.tipo || '—' }}</td><td>{{ item.status || item.estado || '—' }}</td>
						</tr>
					</tbody>
				</table>
			</div><div class="actions">
				<NcButton :disabled="busy" @click="$emit('cancel')">
					{{ t('empleados', 'Cancel') }}
				</NcButton><NcButton :disabled="busy" @click="$emit('review')">
					{{ t('empleados', 'Review selection') }}
				</NcButton><NcButton type="primary" :disabled="busy" @click="confirmOverride">
					{{ t('empleados', 'Create anyway') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'; import { NcButton, NcModal } from '@nextcloud/vue'
import { formatDateRange } from '../../../../utils/mantenimientoFormatters.js'
export default { name: 'MantenimientoConflictDialog', components: { NcButton, NcModal }, props: { conflicts: { type: Array, default: () => [] }, busy: { type: Boolean, default: false } }, methods: { t, formatDateRange, confirmOverride() { if (window.confirm(t('empleados', 'Create the campaign despite potential duplicates?'))) this.$emit('confirm') } } }
</script>
<style scoped lang="scss">.dialog { padding: 24px; max-width: 850px; } .table-wrap { overflow-x: auto; } table { width: 100%; border-collapse: collapse; } th,td { padding: 8px; border-bottom: 1px solid var(--color-border); text-align: left; } .actions { display: flex; justify-content: end; gap: 8px; margin-top: 20px; flex-wrap: wrap; }</style>
