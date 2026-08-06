<template>
	<div class="empleados-dashboard-widget">
		<p class="description">
			Registra tu tiempo del día sin abrir el módulo completo.
		</p>

		<div class="estado-card" :class="estadoClass">
			<div class="estado-title">
				Estado de hoy
			</div>

			<div v-if="loadingEstado" class="estado-value">
				Cargando...
			</div>

			<div v-else class="estado-value">
				{{ estadoLabel }}
			</div>

			<div class="estado-detail">
				Horas reportadas: {{ horasHoy }} h
			</div>
		</div>

		<NcButton
			type="primary"
			wide
			@click="openModal">
			Reportar tiempo
		</NcButton>

		<ReportTimeModal v-if="modal" @created="loadEstadoHoy" @close="closeModal" />
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
		}
	},

	computed: {
		estadoLabel() {
			const estado = this.estadoHoy?.estado

			if (estado === 'reportado') {
				return 'Reportado'
			}

			if (estado === 'sin_empleado') {
				return 'Sin empleado asignado'
			}

			return 'Pendiente'
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
				showError(t('empleados', 'No se pudo cargar el estado de hoy: {error}', { error: String(err) }))
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
	margin-bottom: 12px;
	color: var(--color-text-maxcontrast);
}

.fit {
	width: 100%;
}

.time-selector {
	display: flex;
	gap: 8px;
	margin: 12px 0;
	align-items: center;
}

.radios {
	display: flex;
	margin: 8px 0 12px;
}

.estimatetime {
	flex: 1;
}

.date-picker {
	min-width: 180px;
}

.top {
	margin-top: 12px;
}

.save {
	display: flex;
	justify-content: flex-end;
}
.estado-card {
	border: 1px solid var(--color-border);
	border-radius: 12px;
	padding: 12px;
	margin-bottom: 14px;
	background-color: var(--color-background-hover);
}

.estado-title {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	margin-bottom: 4px;
}

.estado-value {
	font-size: 20px;
	font-weight: 700;
	margin-bottom: 4px;
}

.estado-detail {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
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
