<template>
	<NcModal
		size="normal"
		:name="t('empleados', 'Add new activity')"
		@close="closeModal">
		<div class="report-time-form">
			<div class="report-time-form__fields">
				<input ref="trapFocus"
					type="text"
					style="position:absolute;opacity:0;height:0;width:0;pointer-events:none;">

				<p class="report-time-form__label">
					{{ t('empleados', 'Work type') }}
				</p>

				<div class="report-time-form__work-type">
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

				<p v-if="workType === 'interno'" class="report-time-form__hint">
					{{ t('empleados', 'Internal activities are non-billable') }}
				</p>

				<NcSelect v-if="workType === 'cliente'"
					v-model="selectedClient"
					:input-label="t('empleados', 'Project')"
					:options="clients"
					:disabled="loadingCatalogs || saving"
					class="fit" />

				<div class="report-time-form__row">
					<NcDateTimePicker v-model="reportDate"
						type="date"
						:disabled="saving" />

					<NcTextField required
						:value.sync="reportedTime"
						type="number"
						min="1"
						:disabled="saving"
						:label="t('empleados', 'Estimate time')" />
				</div>

				<div class="report-time-form__units">
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

				<NcSelect v-model="selectedActivity"
					:input-label="t('empleados', 'Activity')"
					:options="availableActivities"
					:disabled="loadingCatalogs || saving"
					class="fit" />

				<NcTextArea required
					resize="vertical"
					:value.sync="description"
					:disabled="saving"
					:label="t('empleados', 'Description activity')" />

				<div class="report-time-form__actions">
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

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcDateTimePicker from '@nextcloud/vue/dist/Components/NcDateTimePicker.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import '../../../css/report-time-form.css'

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
						'Complete all required fields with valid values.',
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
