<template>
	<aside v-if="employee" class="sim-details">
		<button
			type="button"
			class="sim-details__close"
			:aria-label="t('empleados', 'Close')"
			@click="$emit('close')">
			×
		</button>

		<div class="sim-details__avatar" :style="{ background: accent }">
			<img
				v-if="avatarReady"
				:src="employee.avatarUrl"
				alt=""
				@error="$emit('avatar-error', employee.id)">
			<span v-else>{{ initials }}</span>
		</div>

		<h3>{{ employee.displayName }}</h3>
		<p class="sim-details__uid">
			{{ employee.uid }}
		</p>

		<dl>
			<div>
				<dt>{{ t('empleados', 'Area') }}</dt>
				<dd>{{ areaLabel }}</dd>
			</div>
			<div>
				<dt>{{ t('empleados', 'Position') }}</dt>
				<dd>{{ puestoLabel }}</dd>
			</div>
			<div>
				<dt>{{ t('empleados', 'Presence') }}</dt>
				<dd>{{ statusLine }}</dd>
			</div>
			<div>
				<dt>{{ t('empleados', 'Reports in period') }}</dt>
				<dd>{{ employee.reportesPeriodo ?? 0 }}</dd>
			</div>
			<div>
				<dt>{{ t('empleados', 'Reports today') }}</dt>
				<dd>{{ employee.reportesHoy ?? 0 }}</dd>
			</div>
			<div>
				<dt>{{ t('empleados', 'Reports this week') }}</dt>
				<dd>{{ employee.reportesSemana ?? 0 }}</dd>
			</div>
			<div v-for="(line, idx) in eventLines" :key="`ev-${idx}`">
				<dt>{{ t('empleados', 'Today\'s event') }}</dt>
				<dd>{{ line }}</dd>
			</div>
		</dl>

		<div class="sim-details__actions">
			<button
				type="button"
				class="sim-details__follow"
				@click="$emit('follow', !following)">
				{{ following ? t('empleados', 'Stop following') : t('empleados', 'Follow') }}
			</button>
		</div>

		<p class="sim-details__hint">
			{{ t('empleados', 'Activity is only used for visual dynamics in this experimental simulation.') }}
		</p>
	</aside>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { initialsFromName, colorForArea } from '../../../utils/officeSimulationPhysics.js'
import { EVENT_TYPES, normalizeDailyEvents } from '../../../utils/dailyOfficeEvents.js'

export default {
	name: 'EmployeeSimulationDetails',

	props: {
		employee: {
			type: Object,
			default: null,
		},
		avatarReady: {
			type: Boolean,
			default: false,
		},
		following: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['close', 'avatar-error', 'follow'],

	computed: {
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

	methods: {
		t,
	},
}
</script>

<style scoped lang="scss">
.sim-details {
	position: absolute;
	z-index: 25;
	top: 12px;
	right: 12px;
	width: min(280px, calc(100% - 24px));
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: 14px;
	background: var(--color-main-background);
	box-shadow: 0 10px 28px rgba(0, 0, 0, 0.14);
}

.sim-details__close {
	position: absolute;
	top: 8px;
	right: 10px;
	width: 28px;
	height: 28px;
	border: 0;
	border-radius: 8px;
	background: transparent;
	color: var(--color-main-text);
	font-size: 20px;
	line-height: 1;
	cursor: pointer;
}

.sim-details__close:hover {
	background: var(--color-background-hover);
}

.sim-details__avatar {
	display: grid;
	place-items: center;
	width: 64px;
	height: 64px;
	margin: 4px auto 12px;
	overflow: hidden;
	border-radius: 50%;
	color: #fff;
	font-size: 20px;
	font-weight: 700;
}

.sim-details__avatar img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.sim-details h3 {
	margin: 0;
	text-align: center;
	font-size: 16px;
}

.sim-details__uid {
	margin: 4px 0 14px;
	text-align: center;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.sim-details dl {
	display: grid;
	gap: 10px;
	margin: 0;
}

.sim-details dt {
	color: var(--color-text-maxcontrast);
	font-size: 11px;
	text-transform: uppercase;
	letter-spacing: 0.03em;
}

.sim-details dd {
	margin: 2px 0 0;
	font-size: 14px;
}

.sim-details__hint {
	margin: 14px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.35;
}

.sim-details__actions {
	margin-top: 12px;
}

.sim-details__follow {
	width: 100%;
	padding: 7px 10px;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	background: var(--color-background-dark);
	color: var(--color-main-text);
	font-size: 13px;
	cursor: pointer;
}

.sim-details__follow:hover {
	border-color: var(--color-primary-element);
}
</style>
