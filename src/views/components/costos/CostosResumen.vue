<template>
	<section class="costs-summary">
		<div class="summary-heading">
			<div>
				<p class="eyebrow">
					{{ t('empleados', 'Costs') }}
				</p>
				<h2>{{ t('empleados', 'General summary') }}</h2>
			</div>
			<p>{{ periodLabel }}</p>
		</div>

		<div class="kpi-grid kpi-grid--primary">
			<div v-for="card in primaryCards" :key="card.key" class="kpi-card kpi-card--primary">
				<div class="metric-label">
					<span>{{ card.label }}</span>
					<HelpHint
						v-if="card.hint"
						:label="card.hintLabel"
						:text="card.hint" />
				</div>
				<strong>{{ card.value }}</strong>
			</div>
		</div>

		<details class="secondary-indicators">
			<summary>{{ t('empleados', 'More indicators') }}</summary>
			<div class="kpi-grid kpi-grid--secondary">
				<div v-for="card in secondaryCards" :key="card.key" class="kpi-card">
					<div class="metric-label">
						<span>{{ card.label }}</span>
						<HelpHint
							v-if="card.hint"
							:label="card.hintLabel"
							:text="card.hint" />
					</div>
					<strong>{{ card.value }}</strong>
				</div>
			</div>
		</details>

		<p class="cost-note">
			{{ t('empleados', 'Labor costs are calculated using the hourly cost configured for each employee and their reported time.') }}
		</p>

		<NcEmptyContent
			v-if="!participantsCount && !employees.length"
			class="summary-empty"
			:name="t('empleados', 'No employees are available in your scope.')"
			:description="t('empleados', 'There is no cost or availability information to display for the selected period.')" />

		<div v-else class="charts-grid">
			<article class="chart-card chart-card--wide">
				<div class="chart-heading">
					<div>
						<h3>{{ t('empleados', 'Total and billable hours by employee') }}</h3>
						<p>{{ t('empleados', 'Project participants are ordered by billable hours.') }}</p>
					</div>
					<HelpHint
						:label="t('empleados', 'About billable hours')"
						:text="t('empleados', 'Time reported in activities marked as billable.')" />
				</div>
				<NcEmptyContent
					v-if="!leaderChartRows.length"
					class="chart-empty"
					:name="t('empleados', 'No time data is available for project participants in this period.')" />
				<div v-else class="chart-box chart-box--leaders">
					<canvas
						ref="leaderHoursCanvas"
						role="img"
						:aria-label="t('empleados', 'Chart of total and billable hours by employee')" />
				</div>
			</article>

			<article class="chart-card">
				<div class="chart-heading">
					<div>
						<h3>{{ t('empleados', 'Estimated availability by employee') }}</h3>
						<p>{{ t('empleados', 'Employees are ordered from highest to lowest estimated availability.') }}</p>
					</div>
					<HelpHint
						:label="t('empleados', 'About estimated availability')"
						:text="t('empleados', 'Calculated from working days, configured daily hours, approved absences and reported hours. It does not include future project commitments.')" />
				</div>
				<NcEmptyContent
					v-if="!availabilityChartRows.length"
					class="chart-empty"
					:name="t('empleados', 'No estimated availability data is available.')"
					:description="t('empleados', 'Daily reference hours may not be configured for the visible employees.')" />
				<div v-else class="chart-box">
					<canvas
						ref="availabilityCanvas"
						role="img"
						:aria-label="t('empleados', 'Chart of estimated availability by employee')" />
				</div>
			</article>

			<article class="chart-card">
				<div class="chart-heading">
					<div>
						<h3>{{ t('empleados', 'Employees by estimated occupancy level') }}</h3>
						<p>{{ t('empleados', 'Distribution based on effective capacity and reported hours.') }}</p>
					</div>
					<HelpHint
						:label="t('empleados', 'About estimated occupancy')"
						:text="t('empleados', 'Percentage of effective capacity already used by reported hours during the selected period.')" />
				</div>
				<NcEmptyContent
					v-if="!occupancyTotal"
					class="chart-empty"
					:name="t('empleados', 'No estimated occupancy data is available.')" />
				<div v-else class="chart-box">
					<canvas
						ref="occupancyCanvas"
						role="img"
						:aria-label="t('empleados', 'Chart of employees by estimated occupancy level')" />
				</div>
			</article>
		</div>
	</section>
</template>

<script>
// eslint-disable-next-line import/no-named-as-default
import Chart from 'chart.js/auto'
import { translate as t } from '@nextcloud/l10n'
import { NcEmptyContent } from '@nextcloud/vue'

import HelpHint from '../Helpers/HelpHint.vue'

export default {
	name: 'CostosResumen',
	components: {
		HelpHint,
		NcEmptyContent,
	},
	props: {
		kpis: { type: Object, default: () => ({}) },
		leaders: { type: Array, default: () => [] },
		employees: { type: Array, default: () => [] },
		periodLabel: { type: String, default: '' },
	},
	data() {
		return {
			leaderHoursChart: null,
			availabilityChart: null,
			occupancyChart: null,
			themeObserver: null,
			renderFrame: null,
		}
	},
	computed: {
		participantsCount() {
			return Number(this.kpis.total_empleados ?? this.leaders.length ?? 0)
		},
		lowAvailabilityCount() {
			const value = this.firstFinite(this.kpis, [
				'empleados_disponibilidad_baja',
				'empleados_con_disponibilidad_baja',
				'disponibilidad_baja',
			])

			if (value !== null) {
				return value
			}

			return this.employees.filter(employee => {
				return employee.disponibilidad_baja === true
					|| String(employee.nivel_disponibilidad || '').toLowerCase() === 'baja'
			}).length
		},
		insufficientInformationCount() {
			const value = this.firstFinite(this.kpis, [
				'empleados_informacion_insuficiente',
				'empleados_sin_informacion',
				'empleados_sin_informacion_suficiente',
				'empleados_sin_datos_suficientes',
			])

			if (value !== null) {
				return value
			}

			return this.employees.filter(employee => {
				return employee.sin_informacion === true
					|| employee.capacidad_calculable === false
					|| String(employee.calidad_datos || '').toLowerCase() === 'baja'
			}).length
		},
		cards() {
			return [
				{
					key: 'participants',
					label: t('empleados', 'Employees with project participation'),
					value: this.integer(this.participantsCount),
				},
				{
					key: 'companies',
					label: t('empleados', 'Companies in scope'),
					value: this.integer(this.kpis.total_empresas),
				},
				{
					key: 'total-hours',
					label: t('empleados', 'Total hours'),
					value: this.number(this.kpis.horas_totales),
				},
				{
					key: 'billable-hours',
					label: t('empleados', 'Billable hours'),
					value: this.number(this.kpis.horas_cargables),
				},
				{
					key: 'non-billable-hours',
					label: t('empleados', 'Non-billable hours'),
					value: this.number(this.kpis.horas_no_cargables),
				},
				{
					key: 'billable-percentage',
					label: t('empleados', 'Billable percentage'),
					value: `${this.number(this.kpis.porcentaje_cargable)} %`,
					hintLabel: t('empleados', 'About billable percentage'),
					hint: t('empleados', 'Percentage of reported time corresponding to billable activities.'),
				},
				{
					key: 'total-cost',
					label: t('empleados', 'Real labor cost'),
					value: this.money(
						this.kpis.costo_laboral_real ?? this.kpis.costo_total_estimado,
					),
				},
				{
					key: 'billable-cost',
					label: t('empleados', 'Estimated billable cost'),
					value: this.money(this.kpis.costo_cargable_estimado),
					hintLabel: t('empleados', 'About estimated billable cost'),
					hint: t('empleados', 'Estimated cost corresponding only to time reported in billable activities.'),
				},
				{
					key: 'low-availability',
					label: t('empleados', 'Employees with low availability'),
					value: this.integer(this.lowAvailabilityCount),
				},
				{
					key: 'insufficient-information',
					label: t('empleados', 'Employees without sufficient information'),
					value: this.integer(this.insufficientInformationCount),
				},
			]
		},
		primaryCards() {
			const primaryKeys = new Set([
				'participants',
				'companies',
				'total-hours',
				'total-cost',
			])

			return this.cards.filter(card => primaryKeys.has(card.key))
		},
		secondaryCards() {
			const primaryKeys = new Set(this.primaryCards.map(card => card.key))
			return this.cards.filter(card => !primaryKeys.has(card.key))
		},
		leaderChartRows() {
			const rows = this.leaders
				.map(leader => ({
					label: leader.displayname || leader.uid || t('empleados', 'Employee'),
					total: this.numericValue(leader, ['horas_totales']) ?? 0,
					billable: this.numericValue(leader, ['horas_cargables']) ?? 0,
				}))
				.sort((left, right) => right.billable - left.billable)
				.slice(0, 12)

			return rows.some(row => row.total > 0 || row.billable > 0) ? rows : []
		},
		availabilityChartRows() {
			return this.employees
				.map(employee => ({
					label: employee.displayname
						|| employee.nombre
						|| employee.uid
						|| t('empleados', 'Employee'),
					value: this.numericValue(employee, [
						'disponibilidad_estimada',
						'horas_disponibles_estimadas',
					]),
					calculable: employee.capacidad_calculable !== false,
				}))
				.filter(employee => employee.calculable && employee.value !== null)
				.sort((left, right) => right.value - left.value)
				.slice(0, 15)
		},
		occupancyBuckets() {
			const buckets = [0, 0, 0, 0, 0]

			this.employees.forEach(employee => {
				if (employee.capacidad_calculable === false) {
					return
				}

				const occupancy = this.numericValue(employee, ['ocupacion_estimada'])

				if (occupancy === null) {
					return
				}

				if (occupancy < 50) {
					buckets[0] += 1
				} else if (occupancy < 75) {
					buckets[1] += 1
				} else if (occupancy < 90) {
					buckets[2] += 1
				} else if (occupancy <= 100) {
					buckets[3] += 1
				} else {
					buckets[4] += 1
				}
			})

			return buckets
		},
		occupancyTotal() {
			return this.occupancyBuckets.reduce((total, value) => total + value, 0)
		},
	},
	watch: {
		leaders: {
			deep: true,
			handler() {
				this.scheduleRender()
			},
		},
		employees: {
			deep: true,
			handler() {
				this.scheduleRender()
			},
		},
	},
	mounted() {
		this.observeTheme()
		this.scheduleRender()
	},
	beforeDestroy() {
		if (this.renderFrame !== null) {
			cancelAnimationFrame(this.renderFrame)
			this.renderFrame = null
		}

		if (this.themeObserver) {
			this.themeObserver.disconnect()
			this.themeObserver = null
		}

		this.destroyCharts()
	},
	methods: {
		t,
		number(value) {
			return new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(Number(value || 0))
		},
		integer(value) {
			return new Intl.NumberFormat('es-MX').format(Number(value || 0))
		},
		money(value) {
			return new Intl.NumberFormat('es-MX', {
				style: 'currency',
				currency: 'MXN',
			}).format(Number(value || 0))
		},
		firstFinite(source, keys) {
			for (const key of keys) {
				const value = this.numericValue(source, [key])

				if (value !== null) {
					return value
				}
			}

			return null
		},
		numericValue(source, keys) {
			for (const key of keys) {
				const value = source?.[key]

				if (value === null || value === undefined || value === '') {
					continue
				}

				const number = Number(value)

				if (Number.isFinite(number)) {
					return number
				}
			}

			return null
		},
		observeTheme() {
			if (typeof MutationObserver === 'undefined') {
				return
			}

			this.themeObserver = new MutationObserver(() => this.scheduleRender())
			const options = {
				attributes: true,
				attributeFilter: [
					'class',
					'style',
					'data-theme',
					'data-theme-light',
					'data-theme-dark',
				],
			}

			this.themeObserver.observe(document.documentElement, options)

			if (document.body) {
				this.themeObserver.observe(document.body, options)
			}
		},
		scheduleRender() {
			if (!this.$el || this._isBeingDestroyed) {
				return
			}

			if (this.renderFrame !== null) {
				cancelAnimationFrame(this.renderFrame)
			}

			this.renderFrame = requestAnimationFrame(() => {
				this.renderFrame = null
				this.$nextTick(() => this.renderCharts())
			})
		},
		renderCharts() {
			this.renderLeaderHoursChart()
			this.renderAvailabilityChart()
			this.renderOccupancyChart()
		},
		chartColors() {
			const styles = getComputedStyle(this.$el)
			const fallback = styles.color
			const color = name => styles.getPropertyValue(name).trim() || fallback

			return {
				text: color('--color-main-text'),
				muted: color('--color-text-maxcontrast'),
				border: color('--color-border'),
				primary: color('--color-primary-element'),
				success: color('--color-success'),
				warning: color('--color-warning'),
				error: color('--color-error'),
			}
		},
		baseChartOptions(colors) {
			return {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						position: 'bottom',
						labels: {
							color: colors.text,
							usePointStyle: true,
						},
					},
				},
			}
		},
		hoursScales(colors) {
			return {
				x: {
					beginAtZero: true,
					ticks: {
						color: colors.muted,
					},
					grid: {
						color: colors.border,
					},
				},
				y: {
					ticks: {
						color: colors.text,
					},
					grid: {
						display: false,
					},
				},
			}
		},
		renderLeaderHoursChart() {
			this.destroyChart('leaderHoursChart')

			if (!this.leaderChartRows.length || !this.$refs.leaderHoursCanvas) {
				return
			}

			const colors = this.chartColors()
			const options = this.baseChartOptions(colors)
			options.indexAxis = 'y'
			options.scales = this.hoursScales(colors)

			this.leaderHoursChart = new Chart(this.$refs.leaderHoursCanvas, {
				type: 'bar',
				data: {
					labels: this.leaderChartRows.map(row => row.label),
					datasets: [
						{
							label: t('empleados', 'Total hours'),
							data: this.leaderChartRows.map(row => row.total),
							backgroundColor: colors.muted,
							borderRadius: 4,
							borderSkipped: false,
						},
						{
							label: t('empleados', 'Billable hours'),
							data: this.leaderChartRows.map(row => row.billable),
							backgroundColor: colors.primary,
							borderRadius: 4,
							borderSkipped: false,
						},
					],
				},
				options,
			})
		},
		renderAvailabilityChart() {
			this.destroyChart('availabilityChart')

			if (!this.availabilityChartRows.length || !this.$refs.availabilityCanvas) {
				return
			}

			const colors = this.chartColors()
			const options = this.baseChartOptions(colors)
			options.indexAxis = 'y'
			options.scales = this.hoursScales(colors)
			options.plugins.legend.display = false

			this.availabilityChart = new Chart(this.$refs.availabilityCanvas, {
				type: 'bar',
				data: {
					labels: this.availabilityChartRows.map(row => row.label),
					datasets: [{
						label: t('empleados', 'Estimated available hours'),
						data: this.availabilityChartRows.map(row => row.value),
						backgroundColor: colors.success,
						borderRadius: 4,
						borderSkipped: false,
					}],
				},
				options,
			})
		},
		renderOccupancyChart() {
			this.destroyChart('occupancyChart')

			if (!this.occupancyTotal || !this.$refs.occupancyCanvas) {
				return
			}

			const colors = this.chartColors()
			const options = this.baseChartOptions(colors)

			this.occupancyChart = new Chart(this.$refs.occupancyCanvas, {
				type: 'doughnut',
				data: {
					labels: [
						t('empleados', 'Below 50%'),
						t('empleados', 'From 50% to 75%'),
						t('empleados', 'From 75% to 90%'),
						t('empleados', 'From 90% to 100%'),
						t('empleados', 'Above 100%'),
					],
					datasets: [{
						data: this.occupancyBuckets,
						backgroundColor: [
							colors.success,
							colors.primary,
							colors.muted,
							colors.warning,
							colors.error,
						],
						borderColor: colors.border,
						borderWidth: 1,
					}],
				},
				options,
			})
		},
		destroyChart(property) {
			if (this[property]) {
				this[property].destroy()
				this[property] = null
			}
		},
		destroyCharts() {
			this.destroyChart('leaderHoursChart')
			this.destroyChart('availabilityChart')
			this.destroyChart('occupancyChart')
		},
	},
}
</script>

<style scoped lang="scss">
.costs-summary {
	padding: 32px;
	color: var(--color-main-text);
}

.summary-heading {
	display: flex;
	align-items: flex-end;
	justify-content: space-between;
	gap: 16px;
	margin-bottom: 24px;

	h2,
	p {
		margin: 0;
	}

	> p {
		color: var(--color-text-maxcontrast);
	}
}

.eyebrow {
	color: var(--color-primary-element);
	font-weight: 600;
}

.kpi-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
	gap: 16px;
}

.kpi-grid--primary {
	grid-template-columns: repeat(4, minmax(150px, 1fr));
}

.kpi-card {
	display: flex;
	min-height: 92px;
	flex-direction: column;
	justify-content: space-between;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);

	strong {
		font-size: 1.45rem;
	}
}

.kpi-card--primary {
	min-height: 108px;

	strong {
		font-size: 1.65rem;
	}
}

.secondary-indicators {
	margin-top: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);

	> summary {
		min-height: 46px;
		padding: 12px 16px;
		cursor: pointer;
		font-weight: 600;
	}

	&[open] > summary {
		border-bottom: 1px solid var(--color-border);
	}
}

.kpi-grid--secondary {
	grid-template-columns: repeat(3, minmax(170px, 1fr));
	padding: 16px;

	.kpi-card {
		min-height: 82px;
		padding: 14px;
		background: var(--color-background-hover);
	}
}

.metric-label,
.chart-heading {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 8px;
}

.metric-label {
	color: var(--color-text-maxcontrast);
}

.cost-note {
	margin: 20px 0 0;
	color: var(--color-text-maxcontrast);
}

.summary-empty {
	margin-top: 24px;
}

.charts-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 16px;
	margin-top: 28px;
}

.chart-card {
	min-width: 0;
	padding: 20px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.chart-card--wide {
	grid-column: 1 / -1;
}

.chart-heading {
	min-height: 72px;
	margin-bottom: 12px;

	h3,
	p {
		margin: 0;
	}

	h3 {
		font-size: 1rem;
	}

	p {
		margin-top: 4px;
		color: var(--color-text-maxcontrast);
	}
}

.chart-box {
	position: relative;
	height: 320px;
}

.chart-box--leaders {
	height: 360px;
}

.chart-empty {
	min-height: 260px;
}

@media (max-width: 900px) {
	.costs-summary {
		padding: 20px;
	}

	.charts-grid {
		grid-template-columns: minmax(0, 1fr);
	}

	.kpi-grid--primary,
	.kpi-grid--secondary {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.chart-card--wide {
		grid-column: auto;
	}
}

@media (max-width: 600px) {
	.costs-summary {
		padding: 16px;
	}

	.summary-heading {
		align-items: flex-start;
		flex-direction: column;
	}

	.kpi-grid {
		grid-template-columns: minmax(0, 1fr);
	}

	.chart-card {
		padding: 16px;
	}

	.chart-box,
	.chart-box--leaders {
		height: 300px;
	}
}
</style>
