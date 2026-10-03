<template>
	<div
		class="time-bar"
		:class="{
			'time-bar--empty': total <= 0,
			'time-bar--compact': compact,
			'time-bar--hours': showHours,
		}">
		<div
			v-if="total > 0"
			class="time-bar__track"
			role="img"
			:aria-label="ariaLabel">
			<span
				v-for="segment in barSegments"
				:key="segment.key"
				class="time-bar__segment"
				:class="`time-bar__segment--${segment.key}`"
				:title="segmentTitle(segment)"
				:style="{ width: `${segment.width}%` }" />
		</div>
		<ul v-if="total > 0 && legendSegments.length" class="time-bar__legend">
			<li
				v-for="segment in legendSegments"
				:key="`legend-${segment.key}`"
				class="time-bar__item">
				<span class="time-bar__descriptor">
					<span class="time-bar__swatch" :class="`time-bar__swatch--${segment.key}`" aria-hidden="true" />
					<span class="time-bar__label">{{ segment.label }}</span>
				</span>
				<span v-if="showHours" class="time-bar__hours">{{ formatHours(segment.hours) }}</span>
				<span class="time-bar__percent">{{ formatPercent(segment.percentage) }}</span>
			</li>
		</ul>
		<p v-else class="time-bar__empty">
			{{ t('empleados', 'No accounted time for this period.') }}
		</p>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'AdminTimeDistributionBar',
	props: {
		clientHours: { type: Number, default: 0 },
		internalHours: { type: Number, default: 0 },
		absenceHours: { type: Number, default: 0 },
		mostrarClientes: { type: Boolean, default: true },
		mostrarAusencias: { type: Boolean, default: true },
		showHours: { type: Boolean, default: false },
		compact: { type: Boolean, default: false },
	},
	computed: {
		segments() {
			return [
				{
					key: 'client',
					label: t('empleados', 'Clients'),
					hours: this.number(this.clientHours),
					visible: this.mostrarClientes,
				},
				{
					key: 'internal',
					label: t('empleados', 'Internal'),
					hours: this.number(this.internalHours),
					visible: true,
				},
				{
					key: 'absence',
					label: t('empleados', 'Absences'),
					hours: this.number(this.absenceHours),
					visible: this.mostrarAusencias,
				},
			].filter(item => item.visible)
		},
		total() {
			return this.segments.reduce((sum, item) => sum + item.hours, 0)
		},
		legendSegments() {
			return this.segments.map(item => ({
				...item,
				percentage: this.total > 0 ? (item.hours / this.total) * 100 : 0,
			}))
		},
		barSegments() {
			if (this.total <= 0) {
				return []
			}
			return this.legendSegments
				.filter(item => item.hours > 0)
				.map(item => ({
					...item,
					width: Math.min(100, Math.max(1.5, item.percentage)),
				}))
		},
		ariaLabel() {
			if (this.legendSegments.length === 0 || this.total <= 0) {
				return t('empleados', 'Time distribution')
			}
			return this.legendSegments
				.map(item => this.segmentTitle(item))
				.join(', ')
		},
	},
	methods: {
		t,
		number(value) {
			const parsed = Number(value)
			return Number.isFinite(parsed) ? parsed : 0
		},
		formatHours(value) {
			return `${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 }).format(this.number(value))} h`
		},
		formatPercent(value) {
			return `${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 }).format(this.number(value))}%`
		},
		segmentTitle(segment) {
			if (this.showHours) {
				return `${segment.label} ${this.formatHours(segment.hours)} · ${this.formatPercent(segment.percentage)}`
			}
			return `${segment.label} ${this.formatPercent(segment.percentage)}`
		},
	},
}
</script>

<style scoped>
.time-bar {
	--reports-client-color: var(--color-primary-element);
	--reports-internal-color: var(--color-success);
	--reports-absence-color: var(--color-warning);
	display: grid;
	gap: calc(var(--default-grid-baseline) * 2);
	min-width: 0;
}

.time-bar__track {
	display: flex;
	gap: 2px;
	overflow: hidden;
	width: 100%;
	height: calc(var(--default-grid-baseline) * 3);
	border-radius: var(--border-radius-pill, 999px);
	background: var(--color-border);
}

.time-bar__segment {
	display: block;
	min-width: 0;
	height: 100%;
}

.time-bar__segment--client,
.time-bar__swatch--client {
	background: var(--reports-client-color);
}

.time-bar__segment--internal,
.time-bar__swatch--internal {
	background: var(--reports-internal-color);
}

.time-bar__segment--absence,
.time-bar__swatch--absence {
	background: var(--reports-absence-color);
}

.time-bar__legend {
	display: grid;
	gap: calc(var(--default-grid-baseline) * 1.5);
	margin: 0;
	padding: 0;
	list-style: none;
	color: var(--color-text-maxcontrast);
	font-size: 0.875rem;
	line-height: 1.35;
}

.time-bar__item {
	display: grid;
	align-items: center;
	grid-template-columns: minmax(0, 1fr) auto;
	column-gap: calc(var(--default-grid-baseline) * 2);
	min-width: 0;
}

.time-bar--hours .time-bar__item {
	grid-template-columns: minmax(0, 1fr) auto auto;
}

.time-bar__descriptor {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	min-width: 0;
}

.time-bar__label {
	min-width: 0;
	color: var(--color-main-text);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.time-bar__hours,
.time-bar__percent {
	font-variant-numeric: tabular-nums;
	white-space: nowrap;
	text-align: end;
}

.time-bar__hours {
	padding-inline-start: calc(var(--default-grid-baseline) * 2);
	color: var(--color-main-text);
	font-weight: 600;
}

.time-bar__percent {
	min-width: 3.75rem;
	color: var(--color-main-text);
	font-weight: 700;
}

.time-bar__swatch {
	flex: 0 0 auto;
	width: calc(var(--default-grid-baseline) * 2);
	height: calc(var(--default-grid-baseline) * 2);
	border-radius: 50%;
}

.time-bar__empty {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.875rem;
}

.time-bar--compact .time-bar__track {
	height: calc(var(--default-grid-baseline) * 2);
}

.time-bar--compact {
	gap: calc(var(--default-grid-baseline) * 1.5);
}

.time-bar--compact .time-bar__legend {
	gap: calc(var(--default-grid-baseline) * 0.75);
	font-size: 0.8rem;
}

.time-bar--compact .time-bar__item {
	column-gap: calc(var(--default-grid-baseline) * 1.5);
}

.time-bar--compact .time-bar__descriptor {
	gap: calc(var(--default-grid-baseline) * 1.5);
}

.time-bar--compact .time-bar__hours {
	padding-inline-start: var(--default-grid-baseline);
}

.time-bar--compact .time-bar__percent {
	min-width: 3.25rem;
}
</style>
