<template>
	<NcAppContent name="Empleados – Reporte rápido">
		<div class="quick-report-page">
			<div class="quick-report-card">
				<div class="header">
					<h2>Reporte rápido de tiempo</h2>
					<p>
						Registra tus actividades del día de forma rápida.
					</p>
				</div>
				<div class="estado-card" :class="estadoClass">
					<div>
						<strong>Estado de hoy:</strong> {{ loadingEstado ? 'Cargando...' : estadoLabel }}
					</div>
					<div>
						Horas reportadas hoy: {{ horasHoy }} h
					</div>
				</div>

				<div v-if="loading" class="loading">
					<NcLoadingIcon :size="48" />
				</div>

				<div v-else class="form">
					<NcSelect
						v-model="activity_selected"
						:input-label="t('empleados', 'Proyecto / Cliente')"
						:options="actividades"
						class="fit" />

					<NcSelect
						v-model="listas_selected"
						:input-label="t('empleados', 'Actividad')"
						:options="listas"
						class="fit top" />

					<div class="time-selector">
						<NcDateTimePicker
							v-model="time"
							class="date-picker"
							type="date" />

						<NcTextField
							required
							:value.sync="time_activity"
							type="number"
							min="1"
							:label="t('empleados', 'Tiempo')" />

						<div class="radios">
							<NcCheckboxRadioSwitch
								v-model="type_time"
								:button-variant="true"
								value="minutos"
								name="Minutos"
								type="radio"
								button-variant-grouped="horizontal">
								Minutos
							</NcCheckboxRadioSwitch>

							<NcCheckboxRadioSwitch
								v-model="type_time"
								:button-variant="true"
								value="horas"
								name="Horas"
								type="radio"
								button-variant-grouped="horizontal">
								Horas
							</NcCheckboxRadioSwitch>
						</div>
					</div>

					<NcTextArea
						required
						resize="vertical"
						:value.sync="description_activity"
						class="top"
						:label="t('empleados', 'Descripción de la actividad')" />

					<div class="actions">
						<NcButton
							type="secondary"
							@click="resetForm">
							Limpiar
						</NcButton>

						<NcButton
							type="primary"
							:disabled="!isFormValid || saving"
							@click="create">
							{{ saving ? 'Guardando...' : 'Guardar reporte' }}
						</NcButton>
					</div>
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
	NcButton,
	NcLoadingIcon,
	NcTextArea,
	NcCheckboxRadioSwitch,
	NcTextField,
	NcDateTimePicker,
	NcSelect,
} from '@nextcloud/vue'

export default {
	name: 'QuickReport',

	components: {
		NcAppContent,
		NcButton,
		NcLoadingIcon,
		NcTextArea,
		NcCheckboxRadioSwitch,
		NcTextField,
		NcDateTimePicker,
		NcSelect,
	},

	data() {
		return {
			loading: true,
			saving: false,

			description_activity: '',
			type_time: 'minutos',
			time_activity: 0,
			time: new Date(),

			listas: [],
			actividades: [],

			activity_selected: null,
			listas_selected: null,

			estadoHoy: null,
			loadingEstado: false,
		}
	},

	computed: {
		isFormValid() {
			const clienteId = this.activity_selected?.id
			const actividadId = this.listas_selected?.id
			const tiempo = Number(this.time_activity)
			const descripcion = String(this.description_activity || '').trim()
			const fecha = this.time instanceof Date ? this.time : new Date(this.time)

			return Boolean(
				clienteId !== null
				&& clienteId !== undefined
				&& actividadId !== null
				&& actividadId !== undefined
				&& Number.isFinite(tiempo)
				&& tiempo > 0
				&& descripcion.length > 0
				&& !isNaN(fecha.getTime()),
			)
		},
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
		this.loading = true

		try {
			await Promise.all([
				this.GetCompaniesGroups(),
				this.GetActividades(),
				this.loadEstadoHoy(),
			])
		} finally {
			this.loading = false
		}
	},

	methods: {
		t,

		async GetActividades() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetActividades'))

				if (response?.data?.ocs?.meta?.status !== 'ok') {
					showError(response?.data?.ocs?.meta?.message || 'No se pudieron cargar las actividades')
					return
				}

				const arr = Array.isArray(response?.data?.ocs?.data)
					? response.data.ocs.data
					: []

				this.listas = arr.map((item) => ({
					id: item.id_actividad,
					label: item.nombre,
					count: item.tiempo_real,
				}))
			} catch (err) {
				showError(t('empleados', 'Error cargando actividades: {error}', { error: String(err) }))
			}
		},

		async GetCompaniesGroups() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetCompaniesGroups'))

				if (response?.data?.ocs?.meta?.status !== 'ok') {
					showError(response?.data?.ocs?.meta?.message || 'No se pudieron cargar los clientes')
					return
				}

				const arr = Array.isArray(response?.data?.ocs?.data)
					? response.data.ocs.data
					: []

				this.actividades = arr.map((item) => ({
					id: item.id_cliente,
					label: item.nombre,
				}))
			} catch (err) {
				showError(t('empleados', 'Error cargando clientes: {error}', { error: String(err) }))
			}
		},

		async create() {
			if (!this.isFormValid) {
				showError(t('empleados', 'Completa todos los campos obligatorios con valores válidos.'))
				return
			}

			const fecha = this.time instanceof Date ? this.time : new Date(this.time)

			const payload = {
				id_cliente: this.activity_selected.id,
				id_actividad: this.listas_selected.id,
				tiemporegistrado: Number(this.time_activity),
				descripcion: String(this.description_activity || '').trim(),
				tipo: this.type_time,
				time: fecha.toISOString().slice(0, 10),
			}

			this.saving = true

			try {
				await axios.post(generateUrl('/apps/empleados/crearReporte'), payload)

				showSuccess(t('empleados', 'Reporte creado exitosamente'))
				await this.loadEstadoHoy()
				this.resetForm()
			} catch (err) {
				showError(t('empleados', 'Error creando reporte: {error}', { error: String(err) }))
			} finally {
				this.saving = false
			}
		},

		resetForm() {
			this.description_activity = ''
			this.type_time = 'minutos'
			this.time_activity = 0
			this.time = new Date()
			this.activity_selected = null
			this.listas_selected = null
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
.quick-report-page {
	width: 100%;
	min-height: 100%;
	display: flex;
	justify-content: center;
	align-items: flex-start;
	padding: 32px;
	box-sizing: border-box;
}

.quick-report-card {
	width: 100%;
	max-width: 760px;
	background-color: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 16px;
	padding: 24px;
	box-shadow: 0 4px 18px rgba(0, 0, 0, .08);
}

.header {
	margin-bottom: 24px;
}

.header h2 {
	margin: 0 0 8px;
	font-size: 24px;
	font-weight: 700;
}

.header p {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.loading {
	display: flex;
	justify-content: center;
	padding: 48px;
}

.fit {
	width: 100%;
}

.top {
	margin-top: 16px;
}

.time-selector {
	display: grid;
	grid-template-columns: 180px 1fr auto;
	gap: 12px;
	align-items: center;
	margin-top: 16px;
}

.radios {
	display: flex;
	align-items: center;
}

.actions {
	display: flex;
	justify-content: flex-end;
	gap: 12px;
	margin-top: 24px;
}

@media (max-width: 700px) {
	.quick-report-page {
		padding: 16px;
	}

	.time-selector {
		grid-template-columns: 1fr;
	}
}
</style>
