<template>
	<section>
		<div class="toolbar">
			<input v-model="searchInput"
				type="search"
				:placeholder="t('empleados', 'Search devices')"
				@input="scheduleSearch"><select v-model="filters.status" @change="reloadFirst">
					<option value="">
						{{ t('empleados', 'All statuses') }}
					</option><option v-for="status in statuses" :key="status" :value="status">
						{{ maintenanceStatusLabel(status, t) }}
					</option>
				</select><select v-if="showTechnicianFilter"
					v-model="filters.technicianUid"
					:disabled="techniciansLoading || Boolean(techniciansError)"
					@change="reloadFirst">
					<option value="">
						{{ technicianOptionLabel }}
					</option><option v-for="item in technicians" :key="item.uid" :value="item.uid">
						{{ item.displayName }}
					</option>
				</select>
		</div>
		<div v-if="loading" class="state">
			<NcLoadingIcon :size="40" />
		</div><NcNoteCard v-else-if="error" type="error" :text="error" /><div v-else class="table-wrap">
			<table v-if="items.length">
				<thead><tr><th>{{ t('empleados', 'Device') }}</th><th>{{ t('empleados', 'Custodian') }}</th><th>{{ t('empleados', 'Department') }}</th><th>{{ t('empleados', 'Technician') }}</th><th>{{ t('empleados', 'Scheduled date') }}</th><th>{{ t('empleados', 'Status') }}</th><th>{{ t('empleados', 'Overdue') }}</th><th>{{ t('empleados', 'Last update') }}</th><th>{{ t('empleados', 'Actions') }}</th></tr></thead><tbody>
					<tr v-for="item in items" :key="item.id">
						<td>{{ item.equipo_identificador || item.equipo_nombre || '—' }}</td><td>{{ item.empleado_nombre || '—' }}</td><td>{{ item.departamento_nombre || '—' }}</td><td>{{ item.tecnico_nombre || '—' }}</td><td>{{ item.fecha_programada ? formatCalendarDate(item.fecha_programada) : t('empleados', 'No day assigned') }}</td><td><span class="status">{{ maintenanceStatusLabel(item.estado, t) }}</span></td><td>{{ isOverdue(item) ? t('empleados', 'Yes') : t('empleados', 'No') }}</td><td>{{ item.fecha_actualizacion || '—' }}</td><td>
							<NcButton size="small" @click="open(item.id)">
								{{ t('empleados', 'Open') }}
							</NcButton>
						</td>
					</tr>
				</tbody>
			</table><NcEmptyContent v-else :name="t('empleados', 'No maintenance records found')" />
		</div>
		<div class="pagination">
			<span>{{ paginationLabel }}</span><NcButton :disabled="offset === 0 || loading" @click="changeOffset(Math.max(0, offset - limit))">
				{{ t('empleados', 'Previous') }}
			</NcButton><NcButton :disabled="offset + items.length >= total || loading" @click="changeOffset(offset + limit)">
				{{ t('empleados', 'Next') }}
			</NcButton>
		</div>
	</section>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'; import { NcButton, NcEmptyContent, NcLoadingIcon, NcNoteCard } from '@nextcloud/vue'; import maintenanceService from '../../../../services/mantenimientoService.js'; import { formatCalendarDate, maintenanceStatusLabel, toApiDate } from '../../../../utils/mantenimientoFormatters.js'
export default { name: 'MantenimientoEquiposTable', components: { NcButton, NcEmptyContent, NcLoadingIcon, NcNoteCard }, props: { groupId: { type: Number, required: true }, showTechnicianFilter: { type: Boolean, default: false }, technicians: { type: Array, default: () => [] }, techniciansLoading: { type: Boolean, default: false }, techniciansError: { type: String, default: '' } }, data() { return { items: [], total: 0, limit: 25, offset: 0, loading: false, error: '', searchInput: '', timer: null, filters: { status: '', technicianUid: '' }, statuses: ['pending', 'scheduled', 'in_progress', 'completed', 'rescheduled', 'cancelled', 'not_applicable'] } }, computed: { paginationLabel() { return this.total ? t('empleados', 'Showing {start}–{end} of {total}', { start: this.offset + 1, end: Math.min(this.offset + this.items.length, this.total), total: this.total }) : '' }, technicianOptionLabel() { if (this.techniciansLoading) return t('empleados', 'Loading technicians…'); if (this.techniciansError) return t('empleados', 'Could not load technicians.'); if (!this.technicians.length) return t('empleados', 'No technicians configured'); return t('empleados', 'All technicians') } }, mounted() { this.restoreState(); this.load() }, beforeDestroy() { clearTimeout(this.timer) }, methods: { t, maintenanceStatusLabel, formatCalendarDate, restoreState() { const query = this.$route.query || {}; this.searchInput = String(query.tableSearch || ''); this.filters.status = this.statuses.includes(query.tableStatus) ? query.tableStatus : ''; this.filters.technicianUid = this.showTechnicianFilter ? String(query.tableTechnician || '') : ''; const offset = Number(query.tableOffset); this.offset = Number.isInteger(offset) && offset >= 0 ? offset : 0 }, open(id) { this.$emit('open', { id, query: { tableSearch: this.searchInput || undefined, tableStatus: this.filters.status || undefined, tableTechnician: this.showTechnicianFilter ? this.filters.technicianUid || undefined : undefined, tableOffset: this.offset || undefined } }) }, isOverdue(item) { return Boolean(item.fecha_programada) && ['pending', 'scheduled', 'in_progress', 'rescheduled'].includes(item.estado) && item.fecha_programada < toApiDate(new Date()) }, scheduleSearch() { clearTimeout(this.timer); this.timer = setTimeout(this.reloadFirst, 350) }, reloadFirst() { this.offset = 0; this.load() }, changeOffset(value) { this.offset = value; this.load() }, async load() { this.loading = true; this.error = ''; try { const data = await maintenanceService.getGroupMaintenances(this.groupId, { status: this.filters.status || undefined, technicianUid: this.showTechnicianFilter ? this.filters.technicianUid || undefined : undefined, search: this.searchInput.trim() || undefined, limit: this.limit, offset: this.offset }); this.items = data.items || []; this.total = Number(data.pagination?.total || data.total || 0) } catch (error) { this.error = error.status === 500 ? t('empleados', 'An internal error occurred.') : error.message } finally { this.loading = false } } } }
</script>
<style scoped lang="scss">.toolbar,.pagination { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:12px 0; }.toolbar input,.toolbar select { min-height:36px; padding:6px 10px; border:1px solid var(--color-border-maxcontrast); border-radius:var(--border-radius); background:var(--color-main-background); color:var(--color-main-text); }.toolbar input { flex:1; min-width:190px; }.table-wrap { overflow-x:auto; }table { width:100%; border-collapse:collapse; min-width:1050px; }th,td { padding:9px; border-bottom:1px solid var(--color-border); text-align:left; }.status { padding:3px 8px; border-radius:12px; background:var(--color-background-dark); }.pagination { justify-content:flex-end; }.pagination span { margin-right:auto; }.state { text-align:center; padding:32px; }</style>
