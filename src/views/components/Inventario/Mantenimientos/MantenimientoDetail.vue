<template>
	<NcAppContent :name="t('empleados', 'Maintenance detail')">
		<main class="page">
			<nav class="navigation">
				<NcButton @click="backToCampaign">
					{{ t('empleados', 'Back to campaign') }}
				</NcButton>
				<NcButton @click="backToCalendar">
					{{ t('empleados', 'Back to maintenance calendar') }}
				</NcButton>
			</nav>
			<NcNoteCard v-if="invalidId" type="error" :text="t('empleados', 'The requested maintenance ID is invalid.')" />
			<div v-else-if="loading" class="state">
				<NcLoadingIcon :size="48" />
			</div>
			<NcNoteCard v-else-if="error" type="error" :text="error" />
			<template v-else-if="maintenance">
				<header>
					<div><h2>{{ deviceName }}</h2><p>{{ group.titulo || t('empleados', 'Maintenance campaign') }}</p></div>
					<span class="status">{{ maintenanceStatusLabel(maintenance.estado, t) }}</span>
				</header>

				<section class="card facts">
					<h3>{{ t('empleados', 'Device information') }}</h3>
					<p><strong>{{ t('empleados', 'Device') }}:</strong> {{ deviceName }}</p>
					<p><strong>{{ t('empleados', 'Model') }}:</strong> {{ display(maintenance.modelo_nombre) }}</p>
					<p><strong>{{ t('empleados', 'Serial number') }}:</strong> {{ display(maintenance.numero_serie) }}</p>
					<p><strong>{{ t('empleados', 'Custodian') }}:</strong> {{ display(maintenance.empleado_nombre) }}</p>
					<p><strong>{{ t('empleados', 'Department') }}:</strong> {{ display(maintenance.departamento_nombre || group.departamento_nombre) }}</p>
					<p><strong>{{ t('empleados', 'Type') }}:</strong> {{ maintenanceTypeLabel(maintenance.tipo, t) }}</p>
				</section>

				<section class="card facts">
					<h3>{{ t('empleados', 'Campaign period') }}</h3>
					<p><strong>{{ t('empleados', 'Period start') }}:</strong> {{ formatCalendarDate(periodStart) }}</p>
					<p><strong>{{ t('empleados', 'Period end') }}:</strong> {{ formatCalendarDate(periodEnd) }}</p>
					<p><strong>{{ t('empleados', 'Default schedule') }}:</strong> {{ defaultSchedule }}</p>
				</section>

				<section class="card">
					<h3>{{ t('empleados', 'Individual scheduling') }}</h3>
					<p v-if="!maintenance.fecha_programada">
						{{ t('empleados', 'No specific day assigned') }}
					</p>
					<p v-else>
						{{ formatCalendarDate(maintenance.fecha_programada) }} · {{ individualSchedule }}
					</p>
					<form v-if="canSchedule" class="form-grid" @submit.prevent="saveSchedule">
						<label>{{ t('empleados', 'Scheduled day') }}<input v-model="scheduleForm.scheduledDate"
							type="date"
							:min="periodStart"
							:max="periodEnd"
							required
							@input="scheduleDirty = true"></label>
						<label>{{ t('empleados', 'Start time') }}<input v-model="scheduleForm.startTime" type="time" @input="scheduleDirty = true"></label>
						<label>{{ t('empleados', 'End time') }}<input v-model="scheduleForm.endTime" type="time" @input="scheduleDirty = true"></label>
						<NcButton native-type="submit" :disabled="busy || !scheduleDirty">
							{{ t('empleados', 'Save scheduling') }}
						</NcButton>
					</form>
				</section>

				<section class="card facts">
					<h3>{{ t('empleados', 'Status and technician') }}</h3>
					<p><strong>{{ t('empleados', 'Technician') }}:</strong> {{ display(maintenance.tecnico_nombre) }}</p>
					<p><strong>{{ t('empleados', 'Actual start') }}:</strong> {{ displayDateTime(maintenance.fecha_inicio_real) }}</p>
					<p><strong>{{ t('empleados', 'Actual completion') }}:</strong> {{ displayDateTime(maintenance.fecha_fin_real) }}</p>
					<p><strong>{{ t('empleados', 'Last update') }}:</strong> {{ displayDateTime(maintenance.fecha_actualizacion) }}</p>
					<label v-if="canAssignTechnician" class="full">{{ t('empleados', 'Technician') }}
						<select v-model="technicianUid" :disabled="busy || techniciansLoading || Boolean(techniciansError)" @change="assignTechnician">
							<option value="">{{ technicianOptionLabel }}</option>
							<option v-for="item in technicians" :key="item.uid" :value="item.uid">{{ item.displayName }}</option>
						</select>
					</label>
					<NcNoteCard v-if="canAssignTechnician && techniciansError"
						class="full"
						type="error"
						:text="t('empleados', 'Could not load technicians.')" />
					<NcNoteCard v-else-if="canAssignTechnician && !techniciansLoading && !technicians.length"
						class="full"
						type="warning"
						:text="t('empleados', 'There are no users with inventory technician permission. You can leave this maintenance unassigned and configure permissions later.')" />
				</section>

				<section class="card">
					<h3>{{ t('empleados', 'Checklist') }}</h3>
					<MantenimientoChecklist :items="checklist"
						:editable="canEditChecklist"
						:saving="savingChecklist"
						@dirty-change="checklistDirty = $event"
						@save="saveChecklist" />
				</section>

				<section class="card">
					<h3>{{ t('empleados', 'Work performed') }}</h3>
					<form class="form-grid" @submit.prevent="saveWork">
						<label>{{ t('empleados', 'Preliminary result') }}<textarea v-model="workForm.resultado" :readonly="!canSaveWork" @input="workDirty = true" /></label>
						<label>{{ t('empleados', 'Actions performed') }}<textarea v-model="workForm.accionesRealizadas" :readonly="!canSaveWork" @input="workDirty = true" /></label>
						<label>{{ t('empleados', 'Incidents') }}<textarea v-model="workForm.incidencias" :readonly="!canSaveWork" @input="workDirty = true" /></label>
						<label>{{ t('empleados', 'Spare parts') }}<textarea v-model="workForm.repuestos" :readonly="!canSaveWork" @input="workDirty = true" /></label>
						<label>{{ t('empleados', 'Observations') }}<textarea v-model="workForm.observaciones" :readonly="!canSaveWork" @input="workDirty = true" /></label>
						<label>{{ t('empleados', 'Suggested next date') }}<input v-model="workForm.proximaFecha"
							type="date"
							:readonly="!canSaveWork"
							@input="workDirty = true"></label>
						<div v-if="canSaveWork" class="full form-action">
							<span v-if="workDirty">{{ t('empleados', 'Unsaved changes') }}</span><NcButton native-type="submit" :disabled="busy || !workDirty">
								{{ t('empleados', 'Save progress') }}
							</NcButton>
						</div>
					</form>
				</section>

				<section class="card">
					<h3>{{ t('empleados', 'Audit') }}</h3>
					<ul v-if="audit.length">
						<li v-for="(item, index) in audit" :key="item.id || index">
							{{ display(item.tipo_cambio || item.tipo) }} · {{ displayDateTime(item.fecha_creacion || item.fecha) }}<span v-if="item.comentario"> · {{ item.comentario }}</span>
						</li>
					</ul>
					<p v-else>
						{{ t('empleados', 'Audit information is not available for your access level.') }}
					</p>
				</section>

				<section v-if="hasActions" class="card actions">
					<h3>{{ t('empleados', 'Actions') }}</h3>
					<NcButton v-if="canStart"
						type="primary"
						:disabled="busy"
						@click="startMaintenance">
						{{ t('empleados', 'Start maintenance') }}
					</NcButton>
					<NcButton v-if="canComplete"
						type="primary"
						:disabled="busy"
						@click="completeMaintenance">
						{{ t('empleados', 'Complete maintenance') }}
					</NcButton>
					<NcButton v-if="canReschedule" :disabled="busy" @click="openReschedule">
						{{ t('empleados', 'Reschedule') }}
					</NcButton>
					<NcButton v-if="canMarkNotApplicable" :disabled="busy" @click="markNotApplicable">
						{{ t('empleados', 'Mark as not applicable') }}
					</NcButton>
					<NcButton v-if="canCancel"
						type="error"
						:disabled="busy"
						@click="cancelMaintenance">
						{{ t('empleados', 'Cancel maintenance') }}
					</NcButton>
				</section>

				<div v-if="showReschedule" class="modal-backdrop" @click.self="closeReschedule">
					<form class="modal" @submit.prevent="rescheduleMaintenance">
						<h3>{{ t('empleados', 'Reschedule') }}</h3>
						<label>{{ t('empleados', 'New date') }}<input v-model="rescheduleForm.scheduledDate"
							type="date"
							:min="periodStart"
							:max="periodEnd"
							required></label>
						<label>{{ t('empleados', 'Start time') }}<input v-model="rescheduleForm.startTime" type="time"></label>
						<label>{{ t('empleados', 'End time') }}<input v-model="rescheduleForm.endTime" type="time"></label>
						<label>{{ t('empleados', 'Reason') }}<textarea v-model="rescheduleForm.reason" required /></label>
						<div class="actions">
							<NcButton @click="closeReschedule">
								{{ t('empleados', 'Cancel') }}
							</NcButton><NcButton type="primary" native-type="submit" :disabled="busy">
								{{ t('empleados', 'Reschedule') }}
							</NcButton>
						</div>
					</form>
				</div>
			</template>
		</main>
	</NcAppContent>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { NcAppContent, NcButton, NcLoadingIcon, NcNoteCard } from '@nextcloud/vue'
import permissionsMixin from '../../../../mixins/permissions.js'
import maintenanceService from '../../../../services/mantenimientoService.js'
import { formatCalendarDate, formatOptionalTimeRange, isPositiveId, maintenanceCapabilities, maintenanceRecordCapabilities, maintenanceStatusLabel, maintenanceTypeLabel } from '../../../../utils/mantenimientoFormatters.js'
import MantenimientoChecklist from './MantenimientoChecklist.vue'

export default {
	name: 'MantenimientoDetail',
	components: { NcAppContent, NcButton, NcLoadingIcon, NcNoteCard, MantenimientoChecklist },
	mixins: [permissionsMixin],
	inject: { configuraciones: { default: () => ({}) } },
	beforeRouteLeave(to, from, next) { next(!this.hasUnsavedChanges || window.confirm(t('empleados', 'You have unsaved changes. Leave this page?'))) },
	data() {
		return {
			maintenance: null,
			group: {},
			checklist: [],
			audit: [],
			technicians: [],
			techniciansLoading: false,
			techniciansError: '',
			loading: false,
			busy: false,
			savingChecklist: false,
			error: '',
			scheduleDirty: false,
			checklistDirty: false,
			workDirty: false,
			showReschedule: false,
			technicianUid: '',
			scheduleForm: { scheduledDate: '', startTime: '', endTime: '' },
			rescheduleForm: { scheduledDate: '', startTime: '', endTime: '', reason: '' },
			workForm: { resultado: '', accionesRealizadas: '', incidencias: '', repuestos: '', observaciones: '', proximaFecha: '' },
		}
	},
	computed: {
		invalidId() { return !isPositiveId(this.$route.params.id) },
		maintenanceId() { return Number(this.$route.params.id) },
		capabilities() { return maintenanceCapabilities(this.permissions, this.configuraciones) },
		actionCapabilities() { return maintenanceRecordCapabilities(this.maintenance, this.capabilities, this.permissions?.uid) },
		canOperate() { return this.actionCapabilities.canOperate },
		status() { return this.maintenance?.estado || '' },
		canSchedule() { return this.actionCapabilities.canSchedule },
		canStart() { return this.actionCapabilities.canStart },
		canEditChecklist() { return this.actionCapabilities.canEditChecklist },
		canSaveWork() { return this.actionCapabilities.canSaveWork },
		canComplete() { return this.actionCapabilities.canComplete },
		canReschedule() { return this.actionCapabilities.canReschedule },
		canMarkNotApplicable() { return this.actionCapabilities.canMarkNotApplicable },
		canCancel() { return this.actionCapabilities.canCancel },
		canAssignTechnician() { return this.actionCapabilities.canAssignTechnician },
		hasActions() { return this.canStart || this.canComplete || this.canReschedule || this.canMarkNotApplicable || this.canCancel },
		hasUnsavedChanges() { return this.scheduleDirty || this.checklistDirty || this.workDirty },
		deviceName() { return this.maintenance?.equipo_identificador || this.maintenance?.equipo_nombre || '—' },
		periodStart() { return this.group?.fecha_inicio || this.group?.periodStart || this.group?.fecha_programada || '' },
		periodEnd() { return this.group?.fecha_fin || this.group?.periodEnd || this.group?.fecha_programada || '' },
		defaultSchedule() { return formatOptionalTimeRange(this.group?.hora_inicio || this.group?.startTime, this.group?.hora_fin || this.group?.endTime, t('empleados', 'No schedule defined')) },
		individualSchedule() { return formatOptionalTimeRange(this.maintenance?.hora_inicio_programada, this.maintenance?.hora_fin_programada, t('empleados', 'No schedule defined')) },
		technicianOptionLabel() { if (this.techniciansLoading) return t('empleados', 'Loading technicians…'); if (this.techniciansError) return t('empleados', 'Could not load technicians.'); return t('empleados', 'Unassigned') },
	},
	mounted() { window.addEventListener('beforeunload', this.beforeUnload); if (!this.invalidId) this.load() },
	beforeDestroy() { window.removeEventListener('beforeunload', this.beforeUnload) },
	methods: {
		t,
		formatCalendarDate,
		maintenanceStatusLabel,
		maintenanceTypeLabel,
		display(value) { return value === null || value === undefined || value === '' || typeof value === 'object' ? '—' : String(value) },
		displayDateTime(value) { if (!value || Number.isNaN(Date.parse(String(value).replace(' ', 'T')))) return '—'; return String(value) },
		beforeUnload(event) { if (!this.hasUnsavedChanges) return; event.preventDefault(); event.returnValue = '' },
		backToCampaign() { const id = this.$route.query.groupId || this.maintenance?.id_grupo; this.$router.push(isPositiveId(id) ? { name: 'MantenimientoGrupo', params: { id }, query: this.returnQuery() } : { name: 'Mantenimientos' }) },
		backToCalendar() { this.$router.push({ name: 'Mantenimientos' }) },
		returnQuery() { const query = { ...this.$route.query }; delete query.groupId; return query },
		setForms() {
			const m = this.maintenance
			this.scheduleForm = { scheduledDate: m.fecha_programada || '', startTime: String(m.hora_inicio_programada || '').slice(0, 5), endTime: String(m.hora_fin_programada || '').slice(0, 5) }
			this.workForm = { resultado: m.resultado || '', accionesRealizadas: m.acciones_realizadas || '', incidencias: m.incidencias || '', repuestos: m.repuestos || '', observaciones: m.observaciones || '', proximaFecha: m.proxima_fecha || '' }
			this.technicianUid = m.tecnico_uid || ''
			this.scheduleDirty = this.checklistDirty = this.workDirty = false
		},
		async load() {
			this.loading = true; this.error = ''
			try {
				const data = await maintenanceService.getMaintenance(this.maintenanceId)
				this.maintenance = data.maintenance || {}; this.group = data.group || {}; this.checklist = data.checklist || []; this.audit = data.audit || []; this.setForms()
				if (this.capabilities.canAdministerMaintenance && !this.technicians.length) await this.loadTechnicians()
			} catch (error) { this.error = this.errorMessage(error) } finally { this.loading = false }
		},
		async loadTechnicians() { this.techniciansLoading = true; this.techniciansError = ''; try { this.technicians = await maintenanceService.getTechnicians() } catch (error) { this.technicians = []; this.techniciansError = t('empleados', 'Could not load technicians.') } finally { this.techniciansLoading = false } },
		errorMessage(error) {
			if (error.status === 403) return t('empleados', 'Access denied.')
			if (error.status === 404) return t('empleados', 'The maintenance record does not exist.')
			if (error.status === 500) return t('empleados', 'An internal error occurred.')
			return error.message
		},
		validateSchedule(form, requireReason = false) {
			if (!form.scheduledDate || form.scheduledDate < this.periodStart || form.scheduledDate > this.periodEnd) return t('empleados', 'The scheduled date must be within the campaign period.')
			if (Boolean(form.startTime) !== Boolean(form.endTime) || (form.startTime && form.endTime <= form.startTime)) return t('empleados', 'The end time must be later than the start time.')
			if (requireReason && !form.reason.trim()) return t('empleados', 'Reason is required.')
			return ''
		},
		async perform(operation, success) {
			if (this.busy) return; this.busy = true
			try { await operation(); showSuccess(success); await this.load(); this.$bus?.emit('maintenance-updated', { groupId: this.maintenance.id_grupo }) } catch (error) { if (error.status === 409) { showError(t('empleados', 'The maintenance changed while you were editing it. The data will be reloaded.')); await this.load() } else showError(this.errorMessage(error)) } finally { this.busy = false }
		},
		async saveSchedule() { const validation = this.validateSchedule(this.scheduleForm); if (validation) return showError(validation); await this.perform(() => maintenanceService.scheduleMaintenance(this.maintenanceId, this.scheduleForm), t('empleados', 'Scheduling saved.')) },
		async startMaintenance() { if (!window.confirm(t('empleados', 'Do you want to start maintenance on this device?'))) return; await this.perform(() => maintenanceService.startMaintenance(this.maintenanceId), t('empleados', 'Maintenance started.')) },
		async saveChecklist(items) {
			if (this.savingChecklist) return; this.savingChecklist = true
			try { await maintenanceService.updateChecklist(this.maintenanceId, items); showSuccess(t('empleados', 'Checklist saved.')); await this.load() } catch (error) { if (error.status === 409) { showError(t('empleados', 'The maintenance changed while you were editing it. The data will be reloaded.')); await this.load() } else showError(this.errorMessage(error)) } finally { this.savingChecklist = false }
		},
		workPayload() { const payload = {}; for (const [key, value] of Object.entries(this.workForm)) if (String(value || '').trim()) payload[key] = String(value).trim(); return payload },
		async saveWork() { await this.perform(() => maintenanceService.updateWork(this.maintenanceId, this.workPayload()), t('empleados', 'Progress saved.')) },
		async completeMaintenance() {
			if (this.checklistDirty) return showError(t('empleados', 'Save checklist changes before completing.'))
			if (!this.workForm.resultado.trim()) return showError(t('empleados', 'Result is required.'))
			if (!this.workForm.accionesRealizadas.trim()) return showError(t('empleados', 'Actions performed are required.'))
			const pending = this.checklist.filter(item => item.resultado === 'pending').length
			const attention = this.checklist.filter(item => item.resultado === 'attention').length
			if (pending) return showError(t('empleados', 'Complete every checklist item before finishing.'))
			if (this.checklist.some(item => item.resultado === 'attention' && !String(item.observacion || '').trim())) return showError(t('empleados', 'Attention observation is required'))
			const summary = `${this.deviceName}\n${t('empleados', 'Result')}: ${this.workForm.resultado}\n${t('empleados', 'Checklist completed')}: ${this.checklist.length - pending}/${this.checklist.length}\n${t('empleados', 'Items requiring attention')}: ${attention}\n${t('empleados', 'Next date')}: ${this.workForm.proximaFecha || '—'}\n\n${t('empleados', 'This maintenance will become read-only after completion.')}`
			if (!window.confirm(summary)) return
			await this.perform(() => maintenanceService.completeMaintenance(this.maintenanceId, this.workPayload()), t('empleados', 'Maintenance completed.'))
		},
		openReschedule() { this.rescheduleForm = { scheduledDate: this.maintenance.fecha_programada || '', startTime: String(this.maintenance.hora_inicio_programada || '').slice(0, 5), endTime: String(this.maintenance.hora_fin_programada || '').slice(0, 5), reason: '' }; this.showReschedule = true },
		closeReschedule() { if (this.rescheduleForm.reason && !window.confirm(t('empleados', 'Discard these changes?'))) return; this.showReschedule = false },
		async rescheduleMaintenance() { const validation = this.validateSchedule(this.rescheduleForm, true); if (validation) return showError(validation); await this.perform(() => maintenanceService.rescheduleMaintenance(this.maintenanceId, this.rescheduleForm), t('empleados', 'Maintenance rescheduled.')); this.showReschedule = false },
		async markNotApplicable() { const reason = window.prompt(t('empleados', 'Enter the reason.')); if (!reason?.trim() || !window.confirm(t('empleados', 'Mark this maintenance as not applicable?'))) return; await this.perform(() => maintenanceService.markNotApplicable(this.maintenanceId, reason.trim()), t('empleados', 'Maintenance marked as not applicable.')) },
		async cancelMaintenance() { const reason = window.prompt(t('empleados', 'Enter the cancellation reason.')); if (!reason?.trim() || !window.confirm(t('empleados', 'Cancel this maintenance?'))) return; await this.perform(() => maintenanceService.cancelMaintenance(this.maintenanceId, reason.trim()), t('empleados', 'Maintenance cancelled.')) },
		async assignTechnician() { if (!window.confirm(t('empleados', 'Change the assigned technician?'))) { this.technicianUid = this.maintenance.tecnico_uid || ''; return } await this.perform(() => maintenanceService.assignTechnician(this.maintenanceId, this.technicianUid || null), t('empleados', 'Technician updated.')) },
	},
}
</script>

<style scoped lang="scss">
.page { padding: 24px; width: 100%; min-width: 0; }
.navigation, .actions, .form-action { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
header { display: flex; justify-content: space-between; align-items: start; margin-top: 18px; gap: 12px; } header h2 { margin: 0; }
.status { padding: 6px 12px; border-radius: 16px; background: var(--color-background-dark); }
.card { margin-top: 16px; padding: 18px; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); background: var(--color-main-background); }
.card h3 { grid-column: 1 / -1; margin-top: 0; }
.facts, .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
.facts p { margin: 0; }.full { grid-column: 1 / -1; }
label { display: flex; flex-direction: column; gap: 5px; }
input, select, textarea { box-sizing: border-box; width: 100%; min-height: 38px; padding: 8px; border: 1px solid var(--color-border-maxcontrast); border-radius: var(--border-radius); background: var(--color-main-background); color: var(--color-main-text); }
textarea { min-height: 90px; resize: vertical; }.form-action { justify-content: flex-end; }.state { text-align: center; padding: 40px; } ul { padding-left: 22px; }
.modal-backdrop { position: fixed; inset: 0; z-index: 2000; display: grid; place-items: center; padding: 20px; background: rgba(0, 0, 0, .5); }
.modal { width: min(520px, 100%); max-height: 90vh; overflow: auto; padding: 24px; border-radius: var(--border-radius-large); background: var(--color-main-background); box-shadow: 0 8px 30px rgba(0, 0, 0, .25); }
.modal label { margin-bottom: 12px; }.modal .actions { justify-content: flex-end; }
</style>
