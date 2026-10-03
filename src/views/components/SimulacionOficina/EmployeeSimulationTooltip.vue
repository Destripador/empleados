<template>
	<div
		v-if="employee"
		class="sim-tooltip"
		:style="style">
		<div class="sim-tooltip__avatar" :style="{ background: accent }">
			<img
				v-if="avatarReady"
				:src="employee.avatarUrl"
				alt=""
				@error="$emit('avatar-error', employee.id)">
			<span v-else>{{ initials }}</span>
		</div>
		<div class="sim-tooltip__body">
			<strong>{{ employee.displayName }}</strong>
			<span v-if="statusLine" class="sim-tooltip__status">{{ statusLine }}</span>
			<span>{{ areaLabel }}</span>
			<span>{{ puestoLabel }}</span>
			<small>{{ reportsLabel }}</small>
			<small v-if="todayLabel">{{ todayLabel }}</small>
			<small
				v-for="(line, idx) in eventLines"
				:key="`ev-${idx}`"
				class="sim-tooltip__event">
				{{ line }}
			</small>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { initialsFromName, colorForArea } from '../../../utils/officeSimulationPhysics.js'
import { EVENT_TYPES, normalizeDailyEvents } from '../../../utils/dailyOfficeEvents.js'

export default {
	name: 'EmployeeSimulationTooltip',

	props: {
		employee: {
			type: Object,
			default: null,
		},
		x: {
			type: Number,
			default: 0,
		},
		y: {
			type: Number,
			default: 0,
		},
		avatarReady: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		style() {
			return {
				left: `${this.x + 14}px`,
				top: `${this.y - 10}px`,
			}
		},
		initials() {
			return initialsFromName(this.employee?.displayName)
		},
		accent() {
			return colorForArea(this.employee?.areaId ?? this.employee?.area?.id)
		},
		areaLabel() {
			return this.employee?.areaName
				|| this.employee?.area?.nombre
				|| t('empleados', 'No area')
		},
		puestoLabel() {
			return this.employee?.puestoName
				|| this.employee?.puesto?.nombre
				|| t('empleados', 'No position')
		},
		reportsLabel() {
			return t('empleados', '{count} reports in period', {
				count: this.employee?.reportesPeriodo ?? 0,
			})
		},
		todayLabel() {
			const count = this.employee?.reportesHoy ?? 0
			if (!count) {
				return ''
			}
			return t('empleados', '{count} reports today', { count })
		},
		eventLines() {
			const events = this.employee?.specialEvents?.length
				? this.employee.specialEvents
				: normalizeDailyEvents(this.employee?.dailyEvents)
			return events.map((event) => {
				if (event.type === EVENT_TYPES.WORK_ANNIVERSARY) {
					const years = event.years || 0
					return t('empleados', '🎉 Work anniversary: {years} years', { years })
				}
				if (event.type === EVENT_TYPES.BIRTHDAY) {
					return t('empleados', '🎂 Birthday today')
				}
				return `${event.icon || ''} ${event.type}`.trim()
			})
		},
		statusLabel() {
			const status = this.employee?.userStatus || 'offline'
			switch (status) {
			case 'online':
				return t('empleados', 'Online now')
			case 'away':
				return t('empleados', 'Away')
			case 'busy':
				return t('empleados', 'Busy')
			case 'dnd':
				return t('empleados', 'Do not disturb')
			default:
				return t('empleados', 'Offline')
			}
		},
		statusLine() {
			const icon = this.employee?.statusIcon
			const message = this.employee?.statusMessage
			if (icon && message) {
				return `${icon} ${message}`
			}
			if (message) {
				return message
			}
			if (icon) {
				return `${icon} ${this.statusLabel}`
			}
			return this.statusLabel
		},
	},
}
</script>

<style scoped lang="scss">
.sim-tooltip {
	position: absolute;
	z-index: 20;
	display: flex;
	gap: 10px;
	align-items: center;
	min-width: 180px;
	max-width: 260px;
	padding: 10px 12px;
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-main-background);
	box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
	pointer-events: none;
}

.sim-tooltip__avatar {
	display: grid;
	place-items: center;
	width: 40px;
	height: 40px;
	overflow: hidden;
	border-radius: 50%;
	color: #fff;
	font-size: 13px;
	font-weight: 700;
	flex-shrink: 0;
}

.sim-tooltip__avatar img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.sim-tooltip__body {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}

.sim-tooltip__body strong {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-size: 13px;
}

.sim-tooltip__body span,
.sim-tooltip__body small {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.sim-tooltip__status {
	color: var(--color-main-text) !important;
	font-weight: 600;
}

.sim-tooltip__event {
	color: var(--color-main-text) !important;
	font-weight: 600;
}
</style>
