<template>
	<NcModal :name="t('empleados', 'Add new activity')" @close="closeModal">
		<div class="modal__content">
			<div class="form-group">
				<input ref="trapFocus"
					type="text"
					style="position:absolute;opacity:0;height:0;width:0;pointer-events:none;">

				<span class="field-label">
					{{ t('empleados', 'Work type') }}
				</span>

				<div class="radios work-type-radios">
					<NcCheckboxRadioSwitch v-model="workType"
						value="cliente"
						type="radio"
						:disabled="saving">
						{{ t('empleados', 'Client work') }}
					</NcCheckboxRadioSwitch>

					<NcCheckboxRadioSwitch v-model="workType"
						value="interno"
						type="radio"
						:disabled="saving">
						{{ t('empleados', 'Internal work') }}
					</NcCheckboxRadioSwitch>
				</div>

				<p v-if="workType === 'interno'" class="internal-hint">
					{{ t('empleados', 'Internal activities are non-billable') }}
				</p>

				<NcSelect v-if="workType === 'cliente'"
					v-model="selectedClient"
					:input-label="t('empleados', 'Project')"
					:options="clients"
					:disabled="loadingCatalogs || saving"
					class="fit" />

				<div class="time-selector">
					<div class="wrapper">
						<NcDateTimePicker v-model="reportDate"
							class="date-picker"
							type="date"
							:disabled="saving" />
					</div>

					<div class="estimatetime">
						<NcTextField required
							:value.sync="reportedTime"
							type="number"
							min="1"
							:disabled="saving"
							:label="t('empleados', 'Estimate time')" />
					</div>

					<div class="radios time-unit-radios">
						<NcCheckboxRadioSwitch v-model="timeUnit"
							:button-variant="true"
							value="minutos"
							:name="t('empleados', 'Minutes')"
							type="radio"
							:disabled="saving"
							button-variant-grouped="horizontal">
							{{ t('empleados', 'Minutes') }}
						</NcCheckboxRadioSwitch>

						<NcCheckboxRadioSwitch v-model="timeUnit"
							:button-variant="true"
							value="horas"
							:name="t('empleados', 'Hours')"
							type="radio"
							:disabled="saving"
							button-variant-grouped="horizontal">
							{{ t('empleados', 'Hours') }}
						</NcCheckboxRadioSwitch>
					</div>
				</div>

				<NcSelect v-model="selectedActivity"
					:input-label="t('empleados', 'Activity')"
					:options="availableActivities"
					:disabled="loadingCatalogs || saving"
					class="fit" />

				<NcTextArea required
					resize="vertical"
					:value.sync="description"
					:disabled="saving"
					class="top"
					:label="t('empleados', 'Description activity')" />

				<div class="save top">
					<NcButton :aria-label="t('empleados', 'Create Activity')"
						type="primary"
						:disabled="saving || loadingCatalogs || !isFormValid"
						@click="createReport">
						{{ saving
							? t('empleados', 'Saving')
							: t('empleados', 'Create Activity') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
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
	name: 'ReportTimeModal',

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
			saving: false,
			loadingCatalogs: false,

			workType: 'cliente',
			description: '',
			timeUnit: 'minutos',
			reportedTime: 0,
			reportDate: new Date(),

			clients: [],
			activities: [],

			selectedClient: null,
			selectedActivity: null,
		}
	},

	computed: {
		availableActivities() {
			return this.activities.filter((activity) => {
				const type = activity.tipo_actividad || 'cliente'

				return type === this.workType
			})
		},

		isFormValid() {
			const clientId = this.selectedClient?.id
			const activityId = this.selectedActivity?.id
			const time = Number(this.reportedTime)
			const description = String(this.description || '').trim()
			const date = this.reportDate instanceof Date
				? this.reportDate
				: new Date(this.reportDate)

			const hasClient = this.workType === 'interno'
                || (clientId !== null && clientId !== undefined)

			return Boolean(
				hasClient
                && activityId !== null
                && activityId !== undefined
                && Number.isFinite(time)
                && time > 0
                && description.length > 0
                && !isNaN(date.getTime()),
			)
		},
	},

	watch: {
		workType() {
			this.selectedClient = null

			if (
				this.selectedActivity
                && (this.selectedActivity.tipo_actividad || 'cliente') !== this.workType
			) {
				this.selectedActivity = null
			}
		},
	},

	async mounted() {
		await this.loadCatalogs()
	},

	methods: {
		t,

		closeModal() {
			if (this.saving) {
				return
			}

			this.$emit('close')
		},

		async loadCatalogs() {
			this.loadingCatalogs = true

			try {
				await Promise.all([
					this.loadClients(),
					this.loadActivities(),
				])
			} finally {
				this.loadingCatalogs = false
			}
		},

		async loadActivities() {
			try {
				const response = await axios.get(
					generateUrl('/apps/empleados/GetActividades'),
					{
						params: {
							manual: 1,
						},
					},
				)

				if (response?.data?.ocs?.meta?.status !== 'ok') {
					throw new Error(
						response?.data?.ocs?.meta?.message
                        || t('empleados', 'Activities could not be loaded'),
					)
				}

				const data = Array.isArray(response?.data?.ocs?.data)
					? response.data.ocs.data
					: []

				this.activities = data.map(item => ({
					...item,
					id: item.id_actividad ?? item.id,
					label: item.nombre ?? item.label,
					tipo_actividad: item.tipo_actividad || 'cliente',
				}))
			} catch (error) {
				showError(this.backendError(error))
			}
		},

		async loadClients() {
			try {
				const response = await axios.get(
					generateUrl('/apps/empleados/GetCompaniesGroups'),
				)

				if (response?.data?.ocs?.meta?.status !== 'ok') {
					throw new Error(
						response?.data?.ocs?.meta?.message
                        || t('empleados', 'Projects could not be loaded'),
					)
				}

				const data = Array.isArray(response?.data?.ocs?.data)
					? response.data.ocs.data
					: []

				this.clients = data.map(item => ({
					id: item.id,
					label: item.nombre,
				}))
			} catch (error) {
				showError(this.backendError(error))
			}
		},

		async createReport() {
			if (!this.isFormValid || this.saving) {
				showError(
					t(
						'empleados',
						'Completa todos los campos obligatorios con valores válidos.',
					),
				)
				return
			}

			const date = this.reportDate instanceof Date
				? this.reportDate
				: new Date(this.reportDate)

			const payload = {
				tipo_trabajo: this.workType,
				id_cliente: this.workType === 'interno'
					? null
					: this.selectedClient.id,
				id_actividad: this.selectedActivity.id,
				tiemporegistrado: Number(this.reportedTime),
				descripcion: String(this.description || '').trim(),
				tipo: this.timeUnit,
				time: this.formatLocalDateKey(date),
			}

			this.saving = true

			try {
				await axios.post(
					generateUrl('/apps/empleados/crearReporte'),
					payload,
				)

				showSuccess(
					t('empleados', 'Report created successfully'),
				)

				this.$emit('created')
				this.$emit('close')
			} catch (error) {
				showError(this.backendError(error))
			} finally {
				this.saving = false
			}
		},

		formatLocalDateKey(date) {
			const year = date.getFullYear()
			const month = String(date.getMonth() + 1).padStart(2, '0')
			const day = String(date.getDate()).padStart(2, '0')

			return `${year}-${month}-${day}`
		},

		backendError(error) {
			return error?.response?.data?.ocs?.data?.message
                || error?.response?.data?.message
                || error?.message
                || String(error)
		},
	},
}
</script>

<style scoped>
.modal__content {
    padding: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.field-label {
    font-size: 14px;
    font-weight: 600;
}

.fit {
    width: 100%;
}

.time-selector {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin: 12px 0;
}

.radios {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.work-type-radios {
    margin-bottom: 4px;
}

.time-unit-radios {
    margin: 0;
}

.estimatetime {
    flex: 1;
    min-width: 150px;
}

.wrapper {
    display: flex;
    flex-direction: column;
}

.date-picker {
    min-width: 180px;
}

.internal-hint {
    margin: -6px 0 0;
    color: var(--color-text-maxcontrast);
    font-size: 13px;
}

.top {
    margin-top: 4px;
}

.save {
    display: flex;
    justify-content: flex-end;
}

@media (max-width: 600px) {
    .modal__content {
        width: calc(100vw - 20px);
        min-width: 0;
        padding: 16px;
    }

    .time-selector {
        align-items: stretch;
        flex-direction: column;
    }

    .estimatetime,
    .date-picker {
        width: 100%;
        min-width: 0;
    }
}
</style>
