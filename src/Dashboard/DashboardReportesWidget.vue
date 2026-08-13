<template>
	<div class="empleados-dashboard-widget">
		<p class="description">
			{{ t('empleados', 'Register your time for the day without opening the full module.') }}
		</p>

		<div class="estado-card" :class="estadoClass">
			<div class="estado-title">
				{{ t('empleados', 'Today status') }}
			</div>

			<div v-if="loadingEstado" class="estado-value">
				{{ t('empleados', 'Loading...') }}
			</div>

			<template v-else>
				<div class="estado-value">
					{{ estadoLabel }}
				</div>

				<div class="estado-hours">
					<strong>{{ horasHoy }}</strong>
					<span>{{ t('empleados', 'hours reported') }}</span>
					<small v-if="horasObjetivo">
						/ {{ horasObjetivo }} {{ t('empleados', 'h target') }}
					</small>
				</div>

				<div
					v-if="horasObjetivo"
					class="estado-progress"
					role="progressbar"
					:aria-valuenow="progreso"
					aria-valuemin="0"
					aria-valuemax="100">
					<span :style="{ width: `${progreso}%` }" />
				</div>

				<div class="estado-meta">
					<span>
						{{ t('empleados', '{count} entries', { count: registrosHoy }) }}
					</span>
					<span v-if="minutosHoy > 0">
						{{ t('empleados', '{minutes} min', { minutes: Math.round(minutosHoy) }) }}
					</span>
					<span v-if="fechaHoy">
						{{ fechaHoy }}
					</span>
				</div>

				<p class="estado-hint">
					{{ estadoHint }}
				</p>
			</template>
		</div>

		<NcButton
			type="primary"
			wide
			@click="openModal">
			{{ t('empleados', 'Report time') }}
		</NcButton>

		<ReportTimeModal
			v-if="modal"
			@created="loadEstadoHoy"
			@close="closeModal" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

import ReportTimeModal from '../views/components/reports/ReportTimeModal.vue'

import {
	NcButton,
} from '@nextcloud/vue'

export default {
	name: 'DashboardReportesWidget',

	components: {
		NcButton,
		ReportTimeModal,
	},

	data() {
		return {
			modal: false,
			estadoHoy: null,
			loadingEstado: false,
		}
	},

	computed: {
		estadoLabel() {
			const estado = this.estadoHoy?.estado

			if (estado === 'reportado') {
				return t('empleados', 'Reported')
			}

			if (estado === 'sin_empleado') {
				return t('empleados', 'No employee profile')
			}

			return t('empleados', 'Pending report')
		},

		estadoHint() {
			const estado = this.estadoHoy?.estado

			if (estado === 'reportado') {
				return t('empleados', 'Your time report for today is complete.')
			}

			if (estado === 'sin_empleado') {
				return t('empleados', 'Your user is not linked to an employee profile.')
			}

			return t('empleados', 'You still have pending time to report today.')
		},

		estadoClass() {
			const estado = this.estadoHoy?.estado

			if (estado === 'reportado') {
				return 'status-ok'
			}

			if (estado === 'sin_empleado') {
				return 'status-warning'
			}

			return 'status-pending'
		},

		horasHoy() {
			return Number(this.estadoHoy?.horas_reportadas || 0).toFixed(2)
		},

		horasObjetivo() {
			const value = Number(this.estadoHoy?.horas_objetivo || 0)
			return value > 0 ? value.toFixed(2) : null
		},

		progreso() {
			if (this.estadoHoy?.progreso !== null && this.estadoHoy?.progreso !== undefined) {
				return Math.max(0, Math.min(100, Number(this.estadoHoy.progreso) || 0))
			}
			if (!this.horasObjetivo) {
				return 0
			}
			return Math.max(0, Math.min(100, Math.round((Number(this.horasHoy) / Number(this.horasObjetivo)) * 100)))
		},

		registrosHoy() {
			return Number(this.estadoHoy?.registros || 0)
		},

		minutosHoy() {
			return Number(this.estadoHoy?.minutos_reportados || 0)
		},

		fechaHoy() {
			const fecha = this.estadoHoy?.fecha
			if (!fecha) {
				return ''
			}
			try {
				return new Date(`${fecha}T12:00:00`).toLocaleDateString(undefined, {
					weekday: 'short',
					day: 'numeric',
					month: 'short',
				})
			} catch (e) {
				return fecha
			}
		},
	},

	async mounted() {
		await this.loadEstadoHoy()
	},

	methods: {
		t,
		openModal() {
			this.modal = true
		},

		closeModal() {
			this.modal = false
		},

		async loadEstadoHoy() {
			this.loadingEstado = true

			try {
				const response = await axios.get(generateUrl('/apps/empleados/estadoReporteHoy'))

				this.estadoHoy = response?.data?.ocs?.data ?? response?.data ?? null
			} catch (err) {
				this.estadoHoy = null
				showError(t('empleados', 'Could not load today status: {error}', { error: String(err) }))
			} finally {
				this.loadingEstado = false
			}
		},
	},
}
</script>

<style scoped>
.empleados-dashboard-widget {
	padding: 12px;
}

.description {
	margin: 0 0 12px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	line-height: 1.4;
}

.estado-card {
	border: 1px solid var(--color-border);
	border-radius: 12px;
	padding: 12px;
	margin-bottom: 14px;
	background-color: var(--color-background-hover);
}

.estado-title {
	font-size: 12px;
	font-weight: 700;
	letter-spacing: 0.03em;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
	margin-bottom: 6px;
}

.estado-value {
	font-size: 20px;
	font-weight: 700;
	margin-bottom: 8px;
	color: var(--color-main-text);
}

.estado-hours {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	gap: 6px;
	margin-bottom: 8px;
	color: var(--color-main-text);
}

.estado-hours strong {
	font-size: 28px;
	font-weight: 800;
	line-height: 1;
}

.estado-hours span,
.estado-hours small {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.estado-progress {
	height: 8px;
	margin-bottom: 10px;
	border-radius: 999px;
	background: rgba(15, 23, 42, 0.08);
	overflow: hidden;
}

.estado-progress span {
	display: block;
	height: 100%;
	border-radius: inherit;
	background: var(--color-primary-element, #0082c9);
}

.status-ok .estado-progress span {
	background: #46ba61;
}

.status-pending .estado-progress span {
	background: #e9322d;
}

.status-warning .estado-progress span {
	background: #eca700;
}

.estado-meta {
	display: flex;
	flex-wrap: wrap;
	gap: 8px 12px;
	margin-bottom: 8px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 600;
}

.estado-hint {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.4;
}

.status-ok {
	border-left: 5px solid #46ba61;
}

.status-pending {
	border-left: 5px solid #e9322d;
}

.status-warning {
	border-left: 5px solid #eca700;
}
</style>
