<template>
	<NcAppContent :name="t('empleados', 'Reports compliance')">
		<div class="cumplimiento-page">
			<div class="header">
				<div>
					<h2>{{ t('empleados', 'Reports compliance') }}</h2>
					<p>{{ t('empleados', 'Daily time-report status by employee.') }}</p>
				</div>

				<NcDateTimePicker
					v-model="fecha"
					type="date"
					class="date-picker"
					@input="loadCumplimiento" />
			</div>

			<div class="header-actions">
				<NcDateTimePicker
					v-model="fecha"
					type="date"
					class="date-picker"
					@input="loadCumplimiento" />

				<NcButton
					type="primary"
					:disabled="sendingReminder || kpis.pendientes <= 0"
					@click="enviarRecordatoriosPendientes">
					{{ sendingReminder ? t('empleados', 'Sending...') : t('empleados', 'Remind pending') }}
				</NcButton>
			</div>

			<div v-if="loading" class="loading">
				<NcLoadingIcon :size="48" />
			</div>

			<div v-else>
				<div class="kpis">
					<div class="kpi-card">
						<div class="kpi-label">
							{{ t('empleados', 'Employees') }}
						</div>
						<div class="kpi-value">
							{{ kpis.total_empleados }}
						</div>
					</div>

					<div class="kpi-card ok">
						<div class="kpi-label">
							{{ t('empleados', 'Reported') }}
						</div>
						<div class="kpi-value">
							{{ kpis.reportados }}
						</div>
					</div>

					<div class="kpi-card pending">
						<div class="kpi-label">
							{{ t('empleados', 'Pending') }}
						</div>
						<div class="kpi-value">
							{{ kpis.pendientes }}
						</div>
					</div>

					<div class="kpi-card">
						<div class="kpi-label">
							{{ t('empleados', 'Compliance') }}
						</div>
						<div class="kpi-value">
							{{ kpis.porcentaje_cumplimiento }}%
						</div>
					</div>

					<div class="kpi-card">
						<div class="kpi-label">
							{{ t('empleados', 'Hours') }}
						</div>
						<div class="kpi-value">
							{{ kpis.total_horas }}
						</div>
					</div>
				</div>

				<div class="table-card">
					<table class="cumplimiento-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Employee') }}</th>
								<th>{{ t('empleados', 'User') }}</th>
								<th>{{ t('empleados', 'Status') }}</th>
								<th>{{ t('empleados', 'Records') }}</th>
								<th>{{ t('empleados', 'Minutes') }}</th>
								<th>{{ t('empleados', 'Hours') }}</th>
							</tr>
						</thead>

						<tbody>
							<tr
								v-for="empleado in empleados"
								:key="empleado.id_empleado">
								<td>{{ empleado.displayname }}</td>
								<td>{{ empleado.id_user }}</td>
								<td>
									<span class="badge" :class="empleado.estado">
										{{ empleado.estado === 'reportado' ? t('empleados', 'Reported') : t('empleados', 'Pending') }}
									</span>
								</td>
								<td>{{ empleado.registros }}</td>
								<td>{{ empleado.minutos_reportados }}</td>
								<td>{{ empleado.horas_reportadas }}</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div v-if="empleados.length === 0" class="empty">
					{{ t('empleados', 'No employees to display.') }}
				</div>
			</div>
		</div>
	</NcAppContent>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

import {
	NcAppContent,
	NcLoadingIcon,
	NcDateTimePicker,
	NcButton,
} from '@nextcloud/vue'

export default {
	name: 'CumplimientoReportes',

	components: {
		NcAppContent,
		NcLoadingIcon,
		NcDateTimePicker,
		NcButton,
	},

	data() {
		return {
			loading: true,
			fecha: new Date(),
			kpis: {
				total_empleados: 0,
				reportados: 0,
				pendientes: 0,
				total_registros: 0,
				total_minutos: 0,
				total_horas: 0,
				porcentaje_cumplimiento: 0,
			},
			empleados: [],
			sendingReminder: false,
		}
	},

	async mounted() {
		await this.loadCumplimiento()
	},

	methods: {
		t,
		formatFecha(fecha) {
			const date = fecha instanceof Date ? fecha : new Date(fecha)

			if (isNaN(date.getTime())) {
				return new Date().toISOString().slice(0, 10)
			}

			return date.toISOString().slice(0, 10)
		},

		async loadCumplimiento() {
			this.loading = true

			try {
				const fecha = this.formatFecha(this.fecha)

				const response = await axios.get(
					generateUrl('/apps/empleados/GetCumplimientoReportesHoy'),
					{
						params: {
							fecha,
						},
						headers: {
							Accept: 'application/json',
							'OCS-APIRequest': true,
						},
					},
				)

				const data = response?.data?.ocs?.data ?? response?.data ?? {}

				this.kpis = {
					...this.kpis,
					...(data.kpis || {}),
				}

				this.empleados = Array.isArray(data.empleados)
					? data.empleados
					: []
			} catch (err) {
				showError(t('empleados', 'Could not load compliance data: {error}', { error: String(err) }))
			} finally {
				this.loading = false
			}
		},
		async enviarRecordatoriosPendientes() {
			this.sendingReminder = true

			try {
				const fecha = this.formatFecha(this.fecha)

				const response = await axios.post(
					generateUrl('/apps/empleados/EnviarRecordatoriosPendientesHoy'),
					{
						fecha,
					},
				)

				const data = response?.data?.ocs?.data ?? response?.data ?? {}

				showSuccess(
					t('empleados', 'Reminders sent: {sent}. Skipped: {skipped}.', {
						sent: data.enviados || 0,
						skipped: data.omitidos || 0,
					}),
				)

				await this.loadCumplimiento()
			} catch (err) {
				showError(t('empleados', 'Could not send reminders: {error}', { error: String(err) }))
			} finally {
				this.sendingReminder = false
			}
		},
	},
}
</script>

<style scoped>
.cumplimiento-page {
	padding: 24px;
	width: 100%;
	box-sizing: border-box;
}

.header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 16px;
	margin-bottom: 24px;
}

.header h2 {
	margin: 0;
	font-size: 26px;
	font-weight: 700;
}

.header p {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
}

.date-picker {
	min-width: 220px;
}

.loading {
	display: flex;
	justify-content: center;
	padding: 48px;
}

.kpis {
	display: grid;
	grid-template-columns: repeat(5, minmax(120px, 1fr));
	gap: 12px;
	margin-bottom: 24px;
}

.kpi-card {
	border: 1px solid var(--color-border);
	border-radius: 14px;
	padding: 16px;
	background-color: var(--color-main-background);
}

.kpi-label {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	margin-bottom: 6px;
}

.kpi-value {
	font-size: 28px;
	font-weight: 700;
}

.kpi-card.ok {
	border-left: 5px solid #46ba61;
}

.kpi-card.pending {
	border-left: 5px solid #e9322d;
}

.table-card {
	border: 1px solid var(--color-border);
	border-radius: 14px;
	overflow: auto;
	background-color: var(--color-main-background);
}

.cumplimiento-table {
	width: 100%;
	border-collapse: collapse;
}

.cumplimiento-table th,
.cumplimiento-table td {
	padding: 12px;
	border-bottom: 1px solid var(--color-border);
	text-align: left;
	white-space: nowrap;
}

.cumplimiento-table th {
	font-weight: 700;
	background-color: var(--color-background-hover);
}

.badge {
	display: inline-flex;
	align-items: center;
	border-radius: 999px;
	padding: 4px 10px;
	font-size: 13px;
	font-weight: 700;
}

.badge.reportado {
	background-color: rgba(70, 186, 97, .15);
	color: #2f8f46;
}

.badge.pendiente {
	background-color: rgba(233, 50, 45, .15);
	color: #c4211d;
}

.empty {
	margin-top: 24px;
	color: var(--color-text-maxcontrast);
}

@media (max-width: 900px) {
	.kpis {
		grid-template-columns: repeat(2, minmax(120px, 1fr));
	}

	.header {
		flex-direction: column;
		align-items: flex-start;
	}
}
.header-actions {
	display: flex;
	align-items: center;
	gap: 12px;
}
</style>
