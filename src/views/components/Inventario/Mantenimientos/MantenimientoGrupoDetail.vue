<template>
	<NcAppContent :name="t('empleados', 'Maintenance campaign')">
		<main class="page">
			<NcButton @click="back">
				{{ t('empleados', 'Back to maintenance calendar') }}
			</NcButton><div v-if="invalidId">
				<NcNoteCard type="error" :text="t('empleados', 'The requested campaign ID is invalid.')" />
			</div><div v-else-if="loading" class="state">
				<NcLoadingIcon :size="48" />
			</div><NcNoteCard v-else-if="error" type="error" :text="error" /><template v-else-if="group">
				<header>
					<div><h2>{{ group.titulo || t('empleados', 'Maintenance campaign') }}</h2><p>{{ group.descripcion || t('empleados', 'No description available') }}</p></div><div v-if="capabilities.canAdministerMaintenance" class="actions">
						<NcButton :disabled="saving" @click="openTechnicianEditor">
							{{ t('empleados', 'Change technician') }}
						</NcButton><NcButton type="error" :disabled="saving || group.estado_admin === 'cancelled'" @click="cancelCampaign">
							{{ t('empleados', 'Cancel campaign') }}
						</NcButton>
					</div>
				</header><section class="card facts">
					<p><strong>{{ t('empleados', 'Department') }}:</strong> {{ group.departamento_nombre || '—' }}</p><p><strong>{{ t('empleados', 'Type') }}:</strong> {{ maintenanceTypeLabel(group.tipo, t) }}</p><p><strong>{{ t('empleados', 'Campaign period') }}:</strong> {{ period }}</p><p><strong>{{ t('empleados', 'Period start') }}:</strong> {{ formatCalendarDate(periodStart) }}</p><p><strong>{{ t('empleados', 'Period end') }}:</strong> {{ formatCalendarDate(periodEnd) }}</p><p><strong>{{ t('empleados', 'Default schedule') }}:</strong> {{ schedule }}</p><p><strong>{{ t('empleados', 'Technician') }}:</strong> {{ group.tecnico_nombre || '—' }}</p><p><strong>{{ t('empleados', 'Administrative status') }}:</strong> {{ group.estado_admin || '—' }}</p>
				</section><section v-if="editingTechnician && capabilities.canAdministerMaintenance" class="card technician-editor">
					<h3>{{ t('empleados', 'Change technician') }}</h3>
					<label>{{ t('empleados', 'Technician') }}<select v-model="selectedTechnicianUid" :disabled="saving || techniciansLoading"><option value="">{{ techniciansLoading ? t('empleados', 'Loading technicians…') : t('empleados', 'Unassigned') }}</option><option v-for="technician in technicians" :key="technician.uid" :value="technician.uid">{{ technician.displayName }}</option></select></label>
					<NcNoteCard v-if="techniciansError" type="error" :text="t('empleados', 'Could not load technicians.')" />
					<NcNoteCard v-else-if="!techniciansLoading && !technicians.length" type="warning" :text="t('empleados', 'There are no users with inventory technician permission. You can leave the campaign unassigned and configure permissions later.')" />
					<div class="actions">
						<NcButton :disabled="saving" @click="editingTechnician = false">
							{{ t('empleados', 'Cancel') }}
						</NcButton><NcButton type="primary" :disabled="saving || techniciansLoading || Boolean(techniciansError)" @click="saveTechnician">
							{{ t('empleados', 'Save technician') }}
						</NcButton>
					</div>
				</section><section class="card">
					<h3>{{ t('empleados', 'Progress') }}</h3><div class="progress">
						<strong>{{ Number(progress.percentage || 0) }}%</strong><progress :value="Number(progress.percentage || 0)" max="100" />
					</div><div class="counts">
						<span v-for="item in progressItems" :key="item.key">{{ item.label }}: <strong>{{ item.value }}</strong></span>
					</div>
				</section><section class="card">
					<h3>{{ t('empleados', 'Campaign devices') }}</h3><MantenimientoEquiposTable ref="table"
						:group-id="groupId"
						:show-technician-filter="showTechnicianFilter"
						:technicians="technicians"
						:technicians-loading="techniciansLoading"
						:technicians-error="techniciansError"
						@open="openMaintenance" />
				</section>
			</template>
		</main>
	</NcAppContent>
</template>
<script>
import { translate as t } from '@nextcloud/l10n'; import { showError, showSuccess } from '@nextcloud/dialogs'; import { NcAppContent, NcButton, NcLoadingIcon, NcNoteCard } from '@nextcloud/vue'; import permissionsMixin from '../../../../mixins/permissions.js'; import maintenanceService from '../../../../services/mantenimientoService.js'; import { formatCalendarDate, formatDateRange, formatOptionalTimeRange, isPositiveId, maintenanceCapabilities, maintenanceStatusLabel, maintenanceTypeLabel } from '../../../../utils/mantenimientoFormatters.js'; import MantenimientoEquiposTable from './MantenimientoEquiposTable.vue'
export default { name: 'MantenimientoGrupoDetail', components: { NcAppContent, NcButton, NcLoadingIcon, NcNoteCard, MantenimientoEquiposTable }, mixins: [permissionsMixin], inject: { configuraciones: { default: () => ({}) } }, data() { return { group: null, progress: {}, technicians: [], techniciansLoading: false, techniciansError: '', editingTechnician: false, selectedTechnicianUid: '', loading: false, saving: false, error: '' } }, computed: { invalidId() { return !isPositiveId(this.$route.params.id) }, groupId() { return Number(this.$route.params.id) }, capabilities() { return maintenanceCapabilities(this.permissions, this.configuraciones) }, showTechnicianFilter() { return this.capabilities.canViewMaintenance && !this.capabilities.isTechnician }, periodStart() { return this.group?.periodStart || this.group?.fecha_inicio || this.group?.fecha_programada }, periodEnd() { return this.group?.periodEnd || this.group?.fecha_fin || this.group?.fecha_programada }, period() { return formatDateRange(this.periodStart, this.periodEnd) }, schedule() { return formatOptionalTimeRange(this.group?.startTime || this.group?.hora_inicio, this.group?.endTime || this.group?.hora_fin, t('empleados', 'No schedule defined')) }, progressItems() { return ['total', 'pending', 'scheduled', 'in_progress', 'completed', 'rescheduled', 'cancelled', 'not_applicable', 'overdue'].map(key => ({ key, label: key === 'total' ? t('empleados', 'Total') : maintenanceStatusLabel(key, t), value: Number(this.progress[key] || 0) })) } }, mounted() { if (!this.invalidId) this.load() }, methods: { t, formatCalendarDate, maintenanceStatusLabel, maintenanceTypeLabel, back() { this.$router.push({ name: 'Mantenimientos' }) }, openMaintenance({ id, query }) { this.$router.push({ name: 'MantenimientoDetalle', params: { id }, query: { groupId: this.groupId, ...query } }) }, async loadTechnicians() { if (!this.showTechnicianFilter || this.technicians.length) return; this.techniciansLoading = true; this.techniciansError = ''; try { this.technicians = await maintenanceService.getTechnicians() } catch (error) { this.techniciansError = t('empleados', 'Could not load technicians.') } finally { this.techniciansLoading = false } }, async load() { this.loading = true; this.error = ''; try { const data = await maintenanceService.getGroup(this.groupId); this.group = data.group; this.progress = data.progress || {}; await this.loadTechnicians() } catch (error) { this.error = error.status === 404 ? t('empleados', 'The maintenance campaign does not exist.') : error.status === 500 ? t('empleados', 'An internal error occurred.') : error.message } finally { this.loading = false } }, async cancelCampaign() { const reason = window.prompt(t('empleados', 'Enter the cancellation reason.')); if (!reason?.trim() || !window.confirm(t('empleados', 'Cancel this campaign?'))) return; await this.perform(() => maintenanceService.cancelGroup(this.groupId, reason.trim()), t('empleados', 'Campaign cancelled.')) }, openTechnicianEditor() { this.selectedTechnicianUid = this.group.tecnico_uid || ''; this.editingTechnician = true }, async saveTechnician() { if (await this.perform(() => maintenanceService.assignGroupTechnician(this.groupId, this.selectedTechnicianUid || null), t('empleados', 'Technician updated.'))) this.editingTechnician = false }, async perform(operation, success) { if (this.saving) return false; this.saving = true; try { await operation(); showSuccess(success); await this.load(); this.$refs.table?.load(); return true } catch (error) { showError(error.status === 500 ? t('empleados', 'An internal error occurred.') : error.message); return false } finally { this.saving = false } } } }
</script>
<style scoped lang="scss">.page { padding:24px; width:100%; min-width:0; }header { display:flex; justify-content:space-between; gap:16px; align-items:start; margin-top:18px; }header h2 { margin:0; }.actions { display:flex; gap:8px; flex-wrap:wrap; }.card { margin-top:16px; padding:18px; border:1px solid var(--color-border); border-radius:var(--border-radius-large); background:var(--color-main-background); }.facts { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:8px; }.facts p { margin:0; }.technician-editor { display:grid; gap:12px; }.technician-editor label { display:grid; gap:6px; max-width:460px; }.technician-editor select { min-height:38px; padding:8px; }.progress { display:flex; gap:12px; align-items:center; }.progress progress { width:min(500px,100%); }.counts { display:flex; flex-wrap:wrap; gap:12px; margin-top:12px; }.state { text-align:center; padding:40px; }@media(max-width:700px){header{display:block}.actions{margin-top:12px}}</style>
