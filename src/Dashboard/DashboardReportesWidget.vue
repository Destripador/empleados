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

		<NcModal
			v-if="modal"
			:name="t('empleados', 'Add new activity')"
			@close="closeModal">
			<div class="modal__content">
				<div class="form-group">
					<NcSelect
						v-model="activity_selected"
						:input-label="t('empleados', 'Proyect')"
						:options="actividades"
						class="fit" />

					<div class="time-selector">
						<div class="wrapper">
							<NcDateTimePicker
								v-model="time"
								class="date-picker"
								type="date" />
						</div>

						<div class="estimatetime">
							<NcTextField
								required
								:value.sync="time_activity"
								type="number"
								min="1"
								:label="t('empleados', 'Estimate time')" />
						</div>
					</div>

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

					<NcSelect
						v-model="listas_selected"
						:input-label="t('empleados', 'Activity')"
						:options="listas"
						class="fit" />

					<NcTextArea
						required
						resize="vertical"
						:value.sync="description_activity"
						class="top"
						:label="t('empleados', 'Description activity')" />

					<div class="save top">
						<NcButton
							type="primary"
							:disabled="!isFormValid || saving"
							@click="create">
							{{ saving ? 'Guardando...' : 'Crear reporte' }}
						</NcButton>
					</div>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

import {
	NcButton,
	NcModal,
	NcSelect,
	NcDateTimePicker,
	NcTextField,
	NcTextArea,
	NcCheckboxRadioSwitch,
} from '@nextcloud/vue'

export default {
	name: 'DashboardReportesWidget',

	components: {
		NcButton,
		NcModal,
		NcSelect,
		NcDateTimePicker,
		NcTextField,
		NcTextArea,
		NcCheckboxRadioSwitch,
	},

	data() {
		return {
			modal: false,
			saving: false,
			loadingCatalogs: false,
			loadingEstado: false,
			estadoHoy: null,

			description_activity: '',
			type_time: 'minutos',
			time_activity: 0,
			time: new Date(),

			listas: [],
			actividades: [],

			activity_selected: null,
			listas_selected: null,
		}
	},

	computed: {
		isFormValid() {
			const clienteId = this.activity_selected?.id
			const actividadId = this.listas_selected?.id
			const tiempo = Number(this.time_activity)
			const descripcion = String(this.description_activity || '').trim()
			const fecha = this.time instanceof Date ? this.time : new Date(this.time)

			// eslint-disable-next-line no-console
			console.log(clienteId, actividadId, tiempo, descripcion, fecha)

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
		await this.loadEstadoHoy()
	},

	methods: {
		t,

		async openModal() {
			this.modal = true

			if (this.actividades.length === 0 || this.listas.length === 0) {
				await this.loadCatalogs()
			}
		},

		closeModal() {
			this.modal = false
			this.resetForm()
		},

		async loadCatalogs() {
			this.loadingCatalogs = true

			try {
				await Promise.all([
					this.GetCompaniesGroups(),
					this.GetActividades(),
				])
			} finally {
				this.loadingCatalogs = false
			}
		},

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
					id: item.id,
					label: item.nombre,
				}))
				// eslint-disable-next-line no-console
				console.log(this.actividades)
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
				this.closeModal()
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

.modal__content {
	padding: 20px;
	min-width: 520px;
	max-width: 700px;
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
