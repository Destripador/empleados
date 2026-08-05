<template>
	<div class="filters">
		<label><span>{{ t('empleados', 'Department') }}</span><select :value="value.departmentId" @change="update('departmentId', $event.target.value)"><option value="">{{ t('empleados', 'All departments') }}</option><option v-for="item in departments" :key="departmentId(item)" :value="departmentId(item)">{{ departmentName(item) }}</option></select></label>
		<label v-if="showTechnician"><span>{{ t('empleados', 'Technician') }}</span><select :value="value.technicianUid" :disabled="techniciansLoading || Boolean(techniciansError)" @change="update('technicianUid', $event.target.value)"><option value="">{{ technicianOptionLabel }}</option><option v-for="item in technicians" :key="item.uid" :value="item.uid">{{ item.displayName }}</option></select></label>
		<label><span>{{ t('empleados', 'Type') }}</span><select :value="value.type" @change="update('type', $event.target.value)"><option value="">{{ t('empleados', 'All types') }}</option><option value="preventive">{{ t('empleados', 'Preventive') }}</option><option value="corrective">{{ t('empleados', 'Corrective') }}</option><option value="special">{{ t('empleados', 'Special') }}</option></select></label>
		<label><span>{{ t('empleados', 'Status') }}</span><select :value="value.status" @change="update('status', $event.target.value)"><option value="">{{ t('empleados', 'All statuses') }}</option><option value="active">{{ t('empleados', 'Active') }}</option><option value="cancelled">{{ t('empleados', 'Cancelled') }}</option></select></label>
		<label class="search"><span>{{ t('empleados', 'Search') }}</span><input :value="search" type="search" @input="scheduleSearch($event.target.value)"></label>
		<label class="check"><input :checked="value.showOverdue" type="checkbox" @change="update('showOverdue', $event.target.checked)">{{ t('empleados', 'Show overdue') }}</label>
		<NcButton type="tertiary" @click="clear">
			{{ t('empleados', 'Clear filters') }}
		</NcButton>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
export default {
	name: 'MantenimientoFiltros',
	components: { NcButton },
	props: { value: { type: Object, required: true }, departments: { type: Array, default: () => [] }, technicians: { type: Array, default: () => [] }, techniciansLoading: { type: Boolean, default: false }, techniciansError: { type: String, default: '' }, showTechnician: { type: Boolean, default: false } },
	data() { return { timer: null, search: this.value.search || '' } },
	computed: { technicianOptionLabel() { if (this.techniciansLoading) return t('empleados', 'Loading technicians…'); if (this.techniciansError) return t('empleados', 'Could not load technicians.'); if (!this.technicians.length) return t('empleados', 'No technicians configured'); return t('empleados', 'All technicians') } },
	beforeDestroy() { clearTimeout(this.timer) },
	methods: {
		t,
		departmentId(item) { return item.Id_departamento || item.id_departamento || item.id },
		departmentName(item) { return item.Nombre || item.nombre || item.name || this.departmentId(item) },
		update(key, value) { this.$emit('input', { ...this.value, [key]: value }); this.$emit('change') },
		scheduleSearch(value) { this.search = value; clearTimeout(this.timer); this.timer = setTimeout(() => this.update('search', value.trim()), 350) },
		clear() { this.search = ''; this.$emit('input', { departmentId: '', technicianUid: '', type: '', status: '', search: '', showOverdue: false }); this.$emit('change') },
	},
}
</script>

<style scoped lang="scss">
.filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; }
label { display: grid; gap: 4px; min-width: 150px; } label span { font-size: .85rem; color: var(--color-text-maxcontrast); }
select, input[type='search'] { min-height: 36px; padding: 6px 10px; border: 1px solid var(--color-border-maxcontrast); border-radius: var(--border-radius); background: var(--color-main-background); color: var(--color-main-text); }
.search { flex: 1; min-width: 190px; } .check { display: flex; flex-direction: row; align-items: center; min-height: 36px; }
</style>
