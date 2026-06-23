<template>
	<div class="activity-details">
		<NcEmptyContent
			v-if="!hasActivity"
			:name="t('empleados', 'No activity selected')"
			:description="t('empleados', 'Select an activity from the list to view its details.')">
			<template #icon>
				<ClipboardTextClockOutline />
			</template>
		</NcEmptyContent>

		<template v-else>
			<div class="details-header">
				<div class="details-icon">
					<ClipboardTextClockOutline :size="30" />
				</div>
				<div class="details-title">
					<p class="eyebrow">
						{{ t('empleados', 'Activity') }}
					</p>
					<h2>{{ activityName }}</h2>
					<p class="subtitle">
						{{ t('empleados', 'Time report activity catalog item') }}
					</p>
				</div>

				<!-- Badge cargable en el header -->
				<span :class="['badge', activity.cargable ? 'badge--billable' : 'badge--nonbillable']">
					<CurrencyUsd v-if="activity.cargable" :size="14" />
					<CurrencyUsdOff v-else :size="14" />
					{{ activity.cargable ? t('empleados', 'Billable') : t('empleados', 'Non-billable') }}
				</span>
			</div>

			<!-- Grid de tiempo -->
			<div class="summary-grid">
				<div class="summary-card">
					<div class="field-icon">
						<TimerSandFull :size="20" />
					</div>
					<div>
						<span>{{ t('empleados', 'Estimated time') }}</span>
						<strong>{{ estimatedTimeLabel }}</strong>
					</div>
				</div>

				<div class="summary-card">
					<div class="field-icon">
						<ClockCheck :size="20" />
					</div>
					<div>
						<span>{{ t('empleados', 'Real time') }}</span>
						<strong>{{ realTimeLabel }}</strong>
					</div>
				</div>

				<div class="summary-card" :class="differenceClass">
					<div class="field-icon">
						<TrendingUp v-if="difference > 0" :size="20" />
						<TrendingDown v-else-if="difference < 0" :size="20" />
						<Minus v-else :size="20" />
					</div>
					<div>
						<span>{{ t('empleados', 'Difference') }}</span>
						<strong>{{ differenceLabel }}</strong>
					</div>
				</div>
			</div>

			<!-- Detalle descripción + metadatos -->
			<div class="details-grid">
				<div class="detail-card detail-card-wide">
					<div class="field-icon">
						<TextBoxOutline :size="20" />
					</div>
					<div class="detail-content">
						<span>{{ t('empleados', 'Description') }}</span>
						<p>{{ activityDescription }}</p>
					</div>
				</div>

				<div class="detail-card">
					<div class="field-icon">
						<TimerSandFull :size="20" />
					</div>
					<div class="detail-content">
						<span>{{ t('empleados', 'Time unit') }}</span>
						<strong>{{ t('empleados', 'Minutes (stored)') }}</strong>
					</div>
				</div>

				<div class="detail-card">
					<div class="field-icon">
						<ClockCheck :size="20" />
					</div>
					<div class="detail-content">
						<span>{{ t('empleados', 'Status') }}</span>
						<strong>{{ statusLabel }}</strong>
					</div>
				</div>
			</div>
		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcEmptyContent } from '@nextcloud/vue'

import ClipboardTextClockOutline from 'vue-material-design-icons/ClipboardTextClockOutline.vue'
import ClockCheck from 'vue-material-design-icons/ClockCheck.vue'
import TextBoxOutline from 'vue-material-design-icons/TextBoxOutline.vue'
import TimerSandFull from 'vue-material-design-icons/TimerSandFull.vue'
import TrendingUp from 'vue-material-design-icons/TrendingUp.vue'
import TrendingDown from 'vue-material-design-icons/TrendingDown.vue'
import Minus from 'vue-material-design-icons/Minus.vue'
import CurrencyUsd from 'vue-material-design-icons/CurrencyUsd.vue'
import CurrencyUsdOff from 'vue-material-design-icons/CurrencyUsdOff.vue'

export default {
	name: 'ActividadesDetalles',

	components: {
		NcEmptyContent,
		ClipboardTextClockOutline,
		ClockCheck,
		TextBoxOutline,
		TimerSandFull,
		TrendingUp,
		TrendingDown,
		Minus,
		CurrencyUsd,
		CurrencyUsdOff,
	},

	props: {
		select: {
			type: Array,
			required: true,
		},
	},

	computed: {
		activity() {
			return Array.isArray(this.select) && this.select.length > 0
				? this.select[0]
				: null
		},

		hasActivity() {
			return Boolean(this.activity)
		},

		activityName() {
			return this.activity?.nombre
				|| this.activity?.name
				|| t('empleados', 'Without name')
		},

		activityDescription() {
			return this.activity?.detalles
				|| this.activity?.description
				|| t('empleados', 'No description available.')
		},

		estimatedTime() {
			return this.toNumber(this.activity?.tiempo_estimado)
		},

		realTime() {
			return this.toNumber(
				this.activity?.tiempo_real
				?? this.activity?.count,
			)
		},

		estimatedTimeLabel() {
			return this.formatMinutes(this.estimatedTime)
		},

		realTimeLabel() {
			return this.formatMinutes(this.realTime)
		},

		difference() {
			if (this.estimatedTime === null || this.realTime === null) return null
			return this.realTime - this.estimatedTime
		},

		differenceLabel() {
			if (this.difference === null) return '-'
			if (this.difference === 0) return this.formatMinutes(0)
			const prefix = this.difference > 0 ? '+' : ''
			return `${prefix}${this.formatMinutes(this.difference)}`
		},

		differenceClass() {
			if (this.difference === null || this.difference === 0) return ''
			return this.difference > 0 ? 'summary-card--over' : 'summary-card--under'
		},

		statusLabel() {
			if (this.realTime === null || this.realTime === 0) return t('empleados', 'Not used yet')
			if (this.estimatedTime === null || this.estimatedTime === 0) return t('empleados', 'In use')
			if (this.realTime > this.estimatedTime) return t('empleados', 'Above estimate')
			if (this.realTime < this.estimatedTime) return t('empleados', 'Below estimate')
			return t('empleados', 'On estimate')
		},
	},

	methods: {
		t,

		toNumber(value) {
			if (value === null || value === undefined || value === '') return null
			const parsed = Number(value)
			return Number.isFinite(parsed) ? parsed : null
		},

		/**
		 * La BD siempre guarda minutos. Mostramos en h:mm si >= 60, si no en min.
		 */
		formatMinutes(value) {
			if (value === null || value === undefined) return '-'
			const v = Math.abs(value)
			if (v >= 60) {
				const h = Math.floor(v / 60)
				const m = v % 60
				const label = m > 0 ? `${h}h ${m}min` : `${h}h`
				return value < 0 ? `-${label}` : label
			}
			return `${value} min`
		},
	},
}
</script>

<style scoped>
.activity-details {
	width: min(920px, 100%);
	margin: 24px auto 0;
	padding: 22px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

/* ── Header ── */
.details-header {
	display: flex;
	align-items: center;
	gap: 14px;
	margin-bottom: 18px;
}

.details-title {
	flex: 1;
	min-width: 0;
}

.details-icon,
.field-icon {
	display: inline-flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-primary-element);
}

.details-icon {
	width: 56px;
	height: 56px;
}

.field-icon {
	width: 38px;
	height: 38px;
}

/* ── Badge cargable ── */
.badge {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	flex-shrink: 0;
	padding: 4px 10px;
	border-radius: 20px;
	font-size: 12px;
	font-weight: 700;
	letter-spacing: .03em;
}

.badge--billable {
	background: color-mix(in srgb, var(--color-success) 60%);
	color: #50ec07;
}

.badge--nonbillable {
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
}

/* ── Tipografía header ── */
.eyebrow {
	margin: 0 0 4px;
	color: var(--color-primary-element);
	font-size: 12px;
	font-weight: 700;
	letter-spacing: .04em;
	text-transform: uppercase;
}

.details-header h2 {
	margin: 0;
	overflow: hidden;
	color: var(--color-main-text);
	font-size: 24px;
	font-weight: 700;
	line-height: 1.2;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.subtitle {
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

/* ── Summary grid (3 columnas) ── */
.summary-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
	margin-bottom: 12px;
}

.summary-card,
.detail-card {
	display: flex;
	align-items: flex-start;
	min-width: 0;
	gap: 12px;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	transition: border-color 0.15s;
}

/* Colores semánticos diferencia */
.summary-card--over {
	border-color: color-mix(in srgb, var(--color-error) 40%, transparent);
	background: color-mix(in srgb, var(--color-error) 6%, var(--color-background-hover));
}

.summary-card--under {
	border-color: color-mix(in srgb, var(--color-success) 40%, transparent);
	background: color-mix(in srgb, var(--color-success) 6%, var(--color-background-hover));
}

.summary-card span,
.detail-card span {
	display: block;
	margin-bottom: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
	letter-spacing: .03em;
	text-transform: uppercase;
}

.summary-card strong,
.detail-card strong {
	display: block;
	color: var(--color-main-text);
	font-size: 18px;
	font-weight: 700;
	line-height: 1.3;
}

/* ── Details grid (2 columnas) ── */
.details-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
}

.detail-card-wide {
	grid-column: 1 / -1;
}

.detail-content {
	min-width: 0;
}

.detail-card p {
	margin: 0;
	color: var(--color-main-text);
	font-size: 14px;
	line-height: 1.5;
	overflow-wrap: anywhere;
}

/* ── Responsive ── */
@media (max-width: 900px) {
	.summary-grid {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 768px) {
	.activity-details {
		margin-top: 16px;
		padding: 14px;
	}

	.details-grid {
		grid-template-columns: 1fr;
	}

	.detail-card-wide {
		grid-column: auto;
	}

	.details-header {
		flex-wrap: wrap;
		align-items: flex-start;
	}

	.details-header h2 {
		font-size: 20px;
		white-space: normal;
	}

	.badge {
		order: -1;
		margin-inline-start: auto;
	}
}
</style>
