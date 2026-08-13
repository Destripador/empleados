<template>
	<section class="anniversary-card">
		<div class="anniversary-card__icon">
			<TrophyOutline :size="42" />
		</div>

		<div class="anniversary-card__content">
			<p class="anniversary-card__label">
				{{ t('empleados', 'Anniversary') }}
			</p>
			<h2>{{ anniversaryNumber }}</h2>
			<p>
				{{ t('empleados', 'Thanks!') }}
			</p>
		</div>

		<div class="anniversary-stats">
			<div class="stat-item">
				<BriefcaseClockOutline :size="20" />
				<span>{{ t('empleados', 'Worked days') }}</span>
				<strong>{{ workedDays }}</strong>
			</div>
			<div class="stat-item">
				<CalendarStar :size="20" />
				<span>{{ t('empleados', 'Days off') }}</span>
				<strong>{{ info.dias_disponibles || 0 }}</strong>
			</div>
		</div>
	</section>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import BriefcaseClockOutline from 'vue-material-design-icons/BriefcaseClockOutline.vue'
import CalendarStar from 'vue-material-design-icons/CalendarStar.vue'
import TrophyOutline from 'vue-material-design-icons/TrophyOutline.vue'

/**
 * Parse YYYY-MM-DD (or ISO datetime) as a local calendar date at midnight.
 *
 * @param {string|null|undefined} value
 * @return {Date|null}
 */
function parseLocalDate(value) {
	if (!value || typeof value !== 'string') {
		return null
	}

	const match = value.trim().match(/^(\d{4})-(\d{2})-(\d{2})/)
	if (!match) {
		return null
	}

	const year = Number(match[1])
	const month = Number(match[2]) - 1
	const day = Number(match[3])
	const date = new Date(year, month, day)

	if (
		date.getFullYear() !== year
		|| date.getMonth() !== month
		|| date.getDate() !== day
	) {
		return null
	}

	return date
}

/**
 * Format a Date as MM-DD for holiday lookups.
 *
 * @param {Date} date
 * @return {string}
 */
function toMonthDay(date) {
	const month = String(date.getMonth() + 1).padStart(2, '0')
	const day = String(date.getDate()).padStart(2, '0')
	return `${month}-${day}`
}

export default {
	name: 'TrofeosAniversarios',

	components: {
		BriefcaseClockOutline,
		CalendarStar,
		TrophyOutline,
	},

	props: {
		info: {
			type: Object,
			required: true,
		},
		acumular: {
			type: String,
			required: true,
		},
		/**
		 * Optional holiday list. Each item should expose `fecha` as MM-DD
		 * (same format used by the calendar).
		 */
		festivos: {
			type: Array,
			default: () => [],
		},
	},

	computed: {
		anniversaryNumber() {
			return Number(this.info.id_aniversario) || 0
		},

		festivosSet() {
			const set = new Set()
			this.festivos.forEach(item => {
				if (item?.fecha) {
					set.add(String(item.fecha))
				}
			})
			return set
		},

		/**
		 * Approximate business days worked since hire date
		 * (Mon–Fri, excluding configured holidays when available).
		 */
		workedDays() {
			const start = this.resolveHireDate()
			if (!start) {
				return 0
			}

			const today = new Date()
			today.setHours(0, 0, 0, 0)

			if (start.getTime() > today.getTime()) {
				return 0
			}

			return this.countBusinessDays(start, today)
		},
	},

	methods: {
		t,

		resolveHireDate() {
			const fromIngreso = parseLocalDate(this.info.fecha_ingreso)
			if (fromIngreso) {
				return fromIngreso
			}

			// Fallback: reconstruct hire date from the current period start.
			const periodoInicio = parseLocalDate(this.info.periodo_inicio)
			if (periodoInicio) {
				const reconstructed = new Date(periodoInicio)
				reconstructed.setFullYear(reconstructed.getFullYear() - this.anniversaryNumber)
				return reconstructed
			}

			return null
		},

		countBusinessDays(start, end) {
			const current = new Date(start.getFullYear(), start.getMonth(), start.getDate())
			const last = new Date(end.getFullYear(), end.getMonth(), end.getDate())
			let count = 0

			while (current.getTime() <= last.getTime()) {
				const weekday = current.getDay()
				const isWeekend = weekday === 0 || weekday === 6
				const isHoliday = this.festivosSet.has(toMonthDay(current))

				if (!isWeekend && !isHoliday) {
					count++
				}

				current.setDate(current.getDate() + 1)
			}

			return count
		},
	},
}
</script>

<style scoped>
.anniversary-card {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr);
	gap: 16px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-main-background);
}

.anniversary-card__icon {
	display: grid;
	place-items: center;
	width: 64px;
	height: 64px;
	border-radius: 50%;
	background-color: var(--color-primary-element-light);
	color: var(--color-primary-element);
}

.anniversary-card__content {
	min-width: 0;
}

.anniversary-card__label,
.anniversary-card__content p {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.anniversary-card__content h2 {
	margin: 2px 0;
	font-size: 32px;
	line-height: 1.1;
}

.anniversary-stats {
	grid-column: 1 / -1;
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 8px;
}

.stat-item {
	display: grid;
	grid-template-columns: 24px minmax(0, 1fr) auto;
	gap: 8px;
	align-items: center;
	padding: 10px 12px;
	border-radius: var(--border-radius, 6px);
	background-color: var(--color-background-hover);
}

.stat-item span {
	color: var(--color-text-maxcontrast);
}

@media (max-width: 600px) {
	.anniversary-stats {
		grid-template-columns: 1fr;
	}
}
</style>
