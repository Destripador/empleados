<template>
	<NcModal :name="t('empleados', 'Schedule maintenance')" size="large" @close="close">
		<div class="form-modal">
			<h2>{{ t('empleados', 'Schedule maintenance') }}</h2>
			<p class="step">
				{{ t('empleados', 'Step {step} of 4', { step }) }}
			</p>
			<NcNoteCard v-if="errorMessage" type="error" :text="errorMessage" />

			<div v-if="step === 1" class="fields">
				<label>{{ t('empleados', 'Title') }}<input v-model.trim="form.title" required></label>
				<label>{{ t('empleados', 'Type') }}<select v-model="form.type"><option value="preventive">{{ t('empleados', 'Preventive') }}</option><option value="corrective">{{ t('empleados', 'Corrective') }}</option><option value="special">{{ t('empleados', 'Special') }}</option></select></label>
				<label class="wide">{{ t('empleados', 'Description') }}<textarea v-model.trim="form.description" rows="4" /></label>
			</div>

			<div v-if="step === 2">
				<div class="fields">
					<label>{{ t('empleados', 'Department') }}<select v-model="form.departmentId" @change="departmentChanged"><option value="">{{ t('empleados', 'Select a department') }}</option><option v-for="item in departments" :key="departmentId(item)" :value="departmentId(item)">{{ departmentName(item) }}</option></select></label>
					<label class="checkbox"><input v-model="form.includeDescendants" type="checkbox" @change="loadEquipment(0)">{{ t('empleados', 'Include descendant departments') }}</label>
					<label>{{ t('empleados', 'Search devices') }}<input v-model="equipmentSearch" type="search" @input="scheduleEquipmentSearch"></label>
				</div>
				<NcLoadingIcon v-if="equipmentLoading" :size="36" />
				<div v-else class="table-wrap">
					<table>
						<thead>
							<tr>
								<th>
									<input type="checkbox"
										:checked="allVisibleSelected"
										:aria-label="t('empleados', 'Select visible devices')"
										@change="toggleVisible($event.target.checked)">
								</th><th>{{ t('empleados', 'Device') }}</th><th>{{ t('empleados', 'Model') }}</th><th>{{ t('empleados', 'Serial number') }}</th><th>{{ t('empleados', 'Custodian') }}</th><th>{{ t('empleados', 'Department') }}</th><th>{{ t('empleados', 'Status') }}</th>
							</tr>
						</thead><tbody>
							<tr v-for="item in equipment" :key="item.id">
								<td><input type="checkbox" :checked="selectedIds.includes(Number(item.id))" @change="toggleOne(item.id, $event.target.checked)"></td><td>{{ item.identifier || item.name || '—' }}</td><td>{{ [item.brand, item.model].filter(Boolean).join(' ') || '—' }}</td><td>{{ item.serialNumber || '—' }}</td><td>{{ item.employee?.name || '—' }}</td><td>{{ item.department?.name || '—' }}</td><td>{{ item.status || '—' }}</td>
							</tr>
						</tbody>
					</table><NcEmptyContent v-if="!equipment.length" :name="t('empleados', 'No eligible devices found')" />
				</div>
				<div class="pagination">
					<span>{{ t('empleados', '{count} devices selected', { count: selectedIds.length }) }}</span><NcButton :disabled="equipmentOffset === 0 || equipmentLoading" @click="loadEquipment(Math.max(0, equipmentOffset - equipmentLimit))">
						{{ t('empleados', 'Previous') }}
					</NcButton><NcButton :disabled="equipmentOffset + equipment.length >= equipmentTotal || equipmentLoading" @click="loadEquipment(equipmentOffset + equipmentLimit)">
						{{ t('empleados', 'Next') }}
					</NcButton>
				</div>
			</div>

			<div v-if="step === 3" class="fields">
				<label>{{ t('empleados', 'Period start') }}<input v-model="form.periodStart" type="date" required></label>
				<label>{{ t('empleados', 'Period end') }}<input v-model="form.periodEnd"
					type="date"
					:min="form.periodStart || undefined"
					:max="periodEndMax || undefined"
					required></label>
				<label>{{ t('empleados', 'Default start time') }}<input v-model="form.startTime" type="time"></label>
				<label>{{ t('empleados', 'Default end time') }}<input v-model="form.endTime" type="time"></label>
				<label>{{ t('empleados', 'Technician') }}<select v-model="form.technicianUid" :disabled="techniciansLoading"><option value="">{{ techniciansLoading ? t('empleados', 'Loading technicians…') : t('empleados', 'Unassigned') }}</option><option v-for="item in technicians" :key="technicianUid(item)" :value="technicianUid(item)">{{ technicianName(item) }}</option></select></label>
				<NcNoteCard v-if="techniciansError" type="error" :text="t('empleados', 'Could not load technicians.')" />
				<NcNoteCard v-else-if="!techniciansLoading && !technicians.length" type="warning" :text="t('empleados', 'There are no users with inventory technician permission. You can create the campaign unassigned and configure permissions later.')" />
			</div>

			<div v-if="step === 4" class="confirmation">
				<h3>{{ t('empleados', 'Confirm campaign') }}</h3><dl><dt>{{ t('empleados', 'Title') }}</dt><dd>{{ form.title }}</dd><dt>{{ t('empleados', 'Type') }}</dt><dd>{{ form.type }}</dd><dt>{{ t('empleados', 'Department') }}</dt><dd>{{ selectedDepartmentName }}</dd><dt>{{ t('empleados', 'Campaign period') }}</dt><dd>{{ formatDateRange(form.periodStart, form.periodEnd) }}</dd><dt>{{ t('empleados', 'Default schedule') }}</dt><dd>{{ formatOptionalTimeRange(form.startTime, form.endTime, t('empleados', 'No schedule defined')) }}</dd><dt>{{ t('empleados', 'Technician') }}</dt><dd>{{ selectedTechnicianName || t('empleados', 'Unassigned') }}</dd><dt>{{ t('empleados', 'Devices') }}</dt><dd>{{ selectedIds.length }}</dd></dl>
				<NcNoteCard v-if="!form.technicianUid" type="warning" :text="t('empleados', 'This campaign will be created without an assigned technician.')" />
			</div>

			<div class="actions">
				<NcButton :disabled="busy" @click="step === 1 ? close() : step--">
					{{ step === 1 ? t('empleados', 'Cancel') : t('empleados', 'Previous') }}
				</NcButton><NcButton v-if="step < 4"
					type="primary"
					:disabled="busy"
					@click="next">
					{{ t('empleados', 'Next') }}
				</NcButton><NcButton v-else
					type="primary"
					:disabled="busy"
					@click="submit(false)">
					<NcLoadingIcon v-if="busy" :size="20" />{{ t('empleados', 'Create campaign') }}
				</NcButton>
			</div>
			<MantenimientoConflictDialog v-if="conflicts"
				:conflicts="conflicts"
				:busy="busy"
				@cancel="closeConflict"
				@review="closeConflict"
				@confirm="submit(true)" />
		</div>
	</NcModal>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcEmptyContent, NcLoadingIcon, NcModal, NcNoteCard } from '@nextcloud/vue'
import maintenanceService from '../../../../services/mantenimientoService.js'
import { addOneCalendarDay, formatDateRange, formatOptionalTimeRange, toggleSelectedEquipment } from '../../../../utils/mantenimientoFormatters.js'
import MantenimientoConflictDialog from './MantenimientoConflictDialog.vue'
export default {
	name: 'MantenimientoGrupoForm',
	components: { NcButton, NcEmptyContent, NcLoadingIcon, NcModal, NcNoteCard, MantenimientoConflictDialog },
	props: { departments: { type: Array, default: () => [] }, technicians: { type: Array, default: () => [] }, techniciansLoading: { type: Boolean, default: false }, techniciansError: { type: String, default: '' } },
	data() { return { step: 1, busy: false, errorMessage: '', conflicts: null, equipment: [], equipmentTotal: 0, equipmentLimit: 25, equipmentOffset: 0, equipmentSearch: '', equipmentLoading: false, equipmentTimer: null, selectedIds: [], form: { title: '', type: 'preventive', description: '', departmentId: '', includeDescendants: false, periodStart: '', periodEnd: '', startTime: '', endTime: '', technicianUid: '' } } },
	computed: {
		allVisibleSelected() { return this.equipment.length > 0 && this.equipment.every(item => this.selectedIds.includes(Number(item.id))) },
		selectedDepartmentName() { const item = this.departments.find(row => String(this.departmentId(row)) === String(this.form.departmentId)); return item ? this.departmentName(item) : '—' },
		selectedTechnicianName() { const item = this.technicians.find(row => this.technicianUid(row) === this.form.technicianUid); return item ? this.technicianName(item) : '' },
		periodEndMax() { let value = this.form.periodStart; for (let day = 1; day < 31 && value; day++) value = addOneCalendarDay(value); return value },
	},
	beforeDestroy() { clearTimeout(this.equipmentTimer) },
	methods: {
		t,
		formatDateRange,
		formatOptionalTimeRange,
		departmentId(item) {
			return item.value
				?? item.Id_departamento
				?? item.id_departamento
				?? item.id
				?? ''
		},
		departmentName(item) {
			return item.label
				?? item.Nombre
				?? item.nombre
				?? item.name
				?? String(this.departmentId(item) || '')
		},
		technicianUid(item) { return item?.uid ?? '' },
		technicianName(item) { return item?.displayName ?? item?.uid ?? '' },
		close() { if (!this.busy) this.$emit('close') },
		closeConflict() { this.conflicts = null },
		departmentChanged() { this.selectedIds = []; this.loadEquipment(0) },
		scheduleEquipmentSearch() { clearTimeout(this.equipmentTimer); this.equipmentTimer = setTimeout(() => this.loadEquipment(0), 350) },
		async loadEquipment(offset = 0) { if (!this.form.departmentId) { this.equipment = []; this.equipmentTotal = 0; return } this.equipmentLoading = true; try { const data = await maintenanceService.getEligibleEquipment(this.form.departmentId, { includeDescendants: this.form.includeDescendants, includeInactive: false, search: this.equipmentSearch.trim() || undefined, limit: this.equipmentLimit, offset }); this.equipment = data.items || []; this.equipmentTotal = Number(data.total || 0); this.equipmentOffset = offset } catch (error) { this.errorMessage = error.status === 500 ? t('empleados', 'An internal error occurred.') : error.message } finally { this.equipmentLoading = false } },
		toggleVisible(checked) { this.selectedIds = toggleSelectedEquipment(this.selectedIds, this.equipment, checked) },
		toggleOne(id, checked) { this.selectedIds = toggleSelectedEquipment(this.selectedIds, [{ id }], checked) },
		next() { this.errorMessage = ''; if (this.step === 1 && !this.form.title) return this.fail(t('empleados', 'Title is required.')); if (this.step === 2 && (!this.form.departmentId || !this.selectedIds.length)) return this.fail(t('empleados', 'Select a department and at least one device.')); if (this.step === 3) { if (!this.form.periodStart || !this.form.periodEnd) return this.fail(t('empleados', 'Enter a valid campaign period.')); if (this.form.periodEnd < this.form.periodStart) return this.fail(t('empleados', 'The end date cannot be earlier than the start date.')); if (this.periodEndMax && this.form.periodEnd > this.periodEndMax) return this.fail(t('empleados', 'The campaign period is too long.')); if ((this.form.startTime || this.form.endTime) && (!this.form.startTime || !this.form.endTime || this.form.endTime <= this.form.startTime)) return this.fail(t('empleados', 'Enter a valid date and time range.')) } this.step++ },
		fail(message) { this.errorMessage = message },
		payload(allowPotentialDuplicates) { return { title: this.form.title, departmentId: Number(this.form.departmentId), includeDescendants: this.form.includeDescendants, type: this.form.type, periodStart: this.form.periodStart, periodEnd: this.form.periodEnd, startTime: this.form.startTime || null, endTime: this.form.endTime || null, technicianUid: this.form.technicianUid || null, description: this.form.description || null, equipmentIds: [...new Set(this.selectedIds)], allowPotentialDuplicates } },
		async submit(allowPotentialDuplicates) { if (this.busy) return; this.busy = true; this.errorMessage = ''; try { const result = await maintenanceService.createGroup(this.payload(allowPotentialDuplicates)); this.$emit('created', result) } catch (error) { if (error.code === 'maintenance_duplicate_conflict') this.conflicts = error.conflicts; else this.errorMessage = error.status === 500 ? t('empleados', 'An internal error occurred.') : error.message } finally { this.busy = false } },
	},
}
</script>

<style scoped lang="scss">.form-modal { padding: 24px; width: min(1000px, 90vw); max-width: 100%; } .step { color: var(--color-text-maxcontrast); }.fields { display: grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap: 16px; }.fields label { display:grid; gap:6px; }.fields .wide { grid-column:1/-1; }.fields input,.fields select,.fields textarea { padding:8px; border:1px solid var(--color-border-maxcontrast); border-radius:var(--border-radius); background:var(--color-main-background); color:var(--color-main-text); }.checkbox { align-self:end; display:flex!important; grid-auto-flow:column; justify-content:start; }.table-wrap { overflow:auto; max-height:45vh; }table { width:100%; border-collapse:collapse; min-width:760px; }th,td { padding:8px; border-bottom:1px solid var(--color-border); text-align:left; }.pagination,.actions { display:flex; justify-content:flex-end; align-items:center; gap:8px; margin-top:18px; flex-wrap:wrap; }.pagination span { margin-right:auto; }.confirmation dl { display:grid; grid-template-columns:minmax(120px,180px) 1fr; }.confirmation dt { font-weight:bold; }.confirmation dd { margin:0; }</style>
