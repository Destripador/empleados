<template>
	<div class="calendar">
		<FullCalendar ref="calendar" :options="options" />
	</div>
</template>
<script>
import FullCalendar from '@fullcalendar/vue'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import multiMonthPlugin from '@fullcalendar/multimonth'
import { translate as t } from '@nextcloud/l10n'
import { addOneCalendarDay, maintenanceTypeLabel, toApiDate } from '../../../../utils/mantenimientoFormatters.js'
export default {
	name: 'MantenimientoCalendario',
	components: { FullCalendar },
	props: { loadEvents: { type: Function, required: true }, refreshKey: { type: Number, default: 0 } },
	computed: { options() { return { plugins: [dayGridPlugin, interactionPlugin, multiMonthPlugin], initialView: 'dayGridMonth', headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,multiMonthYear' }, buttonText: { today: t('empleados', 'Today'), month: t('empleados', 'Month'), year: t('empleados', 'Year') }, events: this.fetchEvents, eventClick: info => this.$emit('open-group', Number(info.event.extendedProps.groupId)), fixedWeekCount: false, dayMaxEvents: true, height: 'auto' } } },
	watch: { refreshKey() { this.$refs.calendar?.getApi().refetchEvents() } },
	methods: {
		async fetchEvents(info, success, failure) {
			try {
				const groups = await this.loadEvents({ start: toApiDate(info.start), end: toApiDate(new Date(info.end.getTime() - 86400000)) })
				success(groups.map(group => { const progress = group.progress || group; const hasProgress = Object.prototype.hasOwnProperty.call(progress, 'total'); const completed = hasProgress ? Number(progress.completed || progress.completados || 0) : null; const total = hasProgress ? Number(progress.total || 0) : null; const ratio = hasProgress ? ` · ${completed}/${total}` : ''; const periodStart = group.periodStart || group.fecha_inicio || group.fecha_programada; const periodEnd = group.periodEnd || group.fecha_fin || group.fecha_programada; return { id: String(group.id), title: `${group.departamento_nombre || group.titulo || ''} — ${maintenanceTypeLabel(group.tipo, t)}${ratio}`, start: periodStart, end: addOneCalendarDay(periodEnd), allDay: true, extendedProps: { groupId: group.id, departmentName: group.departamento_nombre, type: group.tipo, operationalStatus: progress.operational_status || progress.estado_operativo, completed, total, technicianName: group.tecnico_nombre, periodStart, periodEnd } } }))
			} catch (error) { this.$emit('load-error', error); failure(error) }
		},
	},
}
</script>
<style scoped>.calendar { min-width: 0; overflow-x: auto; } .calendar :deep(.fc) { min-width: 620px; }</style>
