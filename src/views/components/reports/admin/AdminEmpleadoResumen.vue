<template>
	<section v-if="empleado" class="employee-summary" aria-labelledby="employee-summary-title">
		<header class="employee-header">
			<div>
				<p class="eyebrow">
					{{ t('empleados', 'Employee summary') }}
				</p>
				<h3 id="employee-summary-title">
					{{ empleado.nombre || empleado.displayname || t('empleados', 'Employee') }}
				</h3>
				<p v-if="empleado.area" class="employee-area">
					{{ empleado.area }}
				</p>
			</div>
			<span class="status-pill" :class="statusClass(periodo)">
				{{ formatPercent(periodo.porcentaje_cumplimiento) }}
			</span>
		</header>

		<div class="period-grid">
			<article v-for="item in periodCards" :key="item.key" class="period-card">
				<span>{{ item.label }}</span>
				<strong>{{ formatPercent(item.data.porcentaje_cumplimiento) }}</strong>
				<small>{{ formatHours(item.data.horas_reportadas) }} / {{ formatHours(item.data.horas_esperadas) }}</small>
				<small class="period-range">{{ formatRange(item.data) }}</small>
			</article>
		</div>

		<div class="metric-grid">
			<div class="metric">
				<span>{{ t('empleados', 'Expected hours') }}</span>
				<strong>{{ formatHours(periodo.horas_esperadas) }}</strong>
			</div>
			<div class="metric">
				<span>{{ t('empleados', 'Reported hours') }}</span>
				<strong>{{ formatHours(periodo.horas_reportadas) }}</strong>
			</div>
			<div class="metric metric--pending">
				<span>{{ t('empleados', 'Pending hours') }}</span>
				<strong>{{ formatHours(periodo.horas_pendientes) }}</strong>
			</div>
			<div v-if="mostrarClientes" class="metric">
				<span>{{ t('empleados', 'Client work') }}</span>
				<strong>{{ formatHours(empleado.horas_cliente) }}</strong>
			</div>
			<div class="metric">
				<span>{{ t('empleados', 'Internal work') }}</span>
				<strong>{{ formatHours(empleado.horas_internas) }}</strong>
			</div>
			<div v-if="mostrarAusencias" class="metric">
				<span>{{ t('empleados', 'Absences') }}</span>
				<strong>{{ formatHours(empleado.horas_ausencia) }}</strong>
			</div>
		</div>

		<div class="highlights-grid">
			<article v-if="mostrarClientes" class="highlight">
				<span>{{ t('empleados', 'Main customer') }}</span>
				<strong>{{ empleado.cliente_principal || t('empleados', 'No client work in this selection.') }}</strong>
			</article>
			<article class="highlight">
				<span>{{ t('empleados', 'Main internal activity') }}</span>
				<strong>{{ empleado.actividad_principal || t('empleados', 'No internal work in this selection.') }}</strong>
			</article>
		</div>
	</section>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'AdminEmpleadoResumen',
	props: {
		empleado: {
			type: Object,
			default: null,
		},
		mostrarClientes: {
			type: Boolean,
			default: true,
		},
		mostrarAusencias: {
			type: Boolean,
			default: true,
		},
	},
	computed: {
		periodo() {
			return this.empleado?.cumplimiento?.periodo || {}
		},
		periodCards() {
			const compliance = this.empleado?.cumplimiento || {}
			return [
				{ key: 'periodo', label: t('empleados', 'Selected period'), data: compliance.periodo || {} },
				{ key: 'quincena', label: t('empleados', 'Fortnight'), data: compliance.quincena || {} },
				{ key: 'mes', label: t('empleados', 'Month'), data: compliance.mes || {} },
			]
		},
	},
	methods: {
		t,
		number(value) {
			const parsed = Number(value)
			return Number.isFinite(parsed) ? parsed : 0
		},
		formatHours(value) {
			return `${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(this.number(value))} h`
		},
		formatPercent(value) {
			return `${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 }).format(this.number(value))}%`
		},
		formatRange(data) {
			if (!data?.fecha_inicio || !data?.fecha_fin) return '—'
			return `${this.formatDate(data.fecha_inicio)} – ${this.formatDate(data.fecha_fin)}`
		},
		formatDate(value) {
			const [year, month, day] = String(value).split('-').map(Number)
			if (!year || !month || !day) return String(value || '')
			return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' })
				.format(new Date(year, month - 1, day, 12))
		},
		statusClass(data) {
			const percent = this.number(data?.porcentaje_cumplimiento)
			if (percent >= 100) return 'status-pill--ok'
			if (percent >= 70) return 'status-pill--warning'
			return 'status-pill--danger'
		},
	},
}
</script>

<style scoped>
.employee-summary {
	display: grid;
	gap: 18px;
	margin: 0 0 22px;
	padding: 20px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.employee-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
}

.employee-header h3,
.employee-header p {
	margin: 0;
}

.eyebrow,
.metric span,
.highlight span,
.period-card > span {
	color: var(--color-text-maxcontrast);
	font-size: .78rem;
	font-weight: 700;
	letter-spacing: .04em;
	text-transform: uppercase;
}

.employee-area {
	margin-top: 4px !important;
	color: var(--color-text-maxcontrast);
}

.status-pill {
	min-width: 76px;
	padding: 7px 12px;
	border-radius: 999px;
	font-size: 1rem;
	font-weight: 800;
	text-align: center;
}

.status-pill--ok { background: var(--color-success-hover); color: var(--color-success-text); }
.status-pill--warning { background: var(--color-warning-hover); color: var(--color-warning-text); }
.status-pill--danger { background: var(--color-error-hover); color: var(--color-error-text); }

.period-grid,
.metric-grid,
.highlights-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
}

.period-card,
.metric,
.highlight {
	display: flex;
	flex-direction: column;
	gap: 5px;
	min-width: 0;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.period-card strong {
	font-size: 1.65rem;
}

.period-card small,
.highlight small {
	color: var(--color-text-maxcontrast);
}

.period-range {
	font-size: .75rem;
}

.metric strong,
.highlight strong {
	font-size: 1.05rem;
	overflow-wrap: anywhere;
}

@media (max-width: 900px) {
	.period-grid,
	.metric-grid,
	.highlights-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media (max-width: 600px) {
	.employee-summary {
		padding: 14px;
	}

	.period-grid,
	.metric-grid,
	.highlights-grid {
		grid-template-columns: 1fr;
	}
}
</style>
