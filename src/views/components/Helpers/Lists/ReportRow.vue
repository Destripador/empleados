<template>
	<div class="row">
		<NcListItem
			:name="titleText"
			bold
			:counter-number="counterText"
			:details="detailsText"
			:force-display-actions="true"
			@click.prevent="handleRowClick">
			<template #subname>
				{{ subnameText }}
				<span v-if="isInternalReport" class="origin-badge">{{ originLabel }}</span>
				<span v-if="isInternalReport" class="billable-badge">{{ t('empleados', 'Non-billable') }}</span>
			</template>

			<template #indicator>
				<CheckboxBlankCircleOutline
					:size="16"
					:fill-color="indicatorColor" />
			</template>

			<template #actions>
				<NcActionButton v-if="editable"
					:close-after-click="true"
					@click="edit(index)">
					<template #icon>
						<DeleteAlert :size="20" />
					</template>
					{{ t('empleados', 'Edit') }}
				</NcActionButton>
				<NcActionButton v-if="editable"
					:close-after-click="true"
					@click="showDialog = true, reportid = source.id">
					<template #icon>
						<DeleteAlert :size="20" />
					</template>
					{{ t('empleados', 'Delete') }}
				</NcActionButton>
				<NcActionButton v-if="isSupportReport && source.id_equipo" @click="viewSupport">
					<template #icon>
						<OpenInNew :size="20" />
					</template>
					{{ t('empleados', 'View support') }}
				</NcActionButton>
				<NcActionButton v-if="!editable && canViewDetails"
					:close-after-click="true"
					@click="edit()">
					<template #icon>
						<OpenInNew :size="20" />
					</template>
					{{ t('empleados', 'View details') }}
				</NcActionButton>
			</template>
		</NcListItem>
		<NcDialog
			:open.sync="showDialog"
			:name="t('empleados', 'Confirm')"
			:message="t('empleados', 'Do you want to delete this item?')"
			:buttons="buttons" />
		<NcModal
			v-if="ShowEdit"
			ref="modalRef"
			size="normal"
			:name="editModalTitle"
			@close="closeEdit">
			<div class="report-time-form">
				<p v-if="isSupportReport" class="report-time-form__banner">
					{{ t('empleados', 'Technical support report') }}
					<template v-if="supportDeviceLabel">
						· {{ supportDeviceLabel }}
					</template>
				</p>
				<div class="report-time-form__fields">
					<input
						ref="trapFocus"
						type="text"
						style="position:absolute;opacity:0;height:0;width:0;pointer-events:none;">

					<NcSelect
						v-model="activity_selected"
						:input-label="isSupportReport ? t('empleados', 'Origin') : t('empleados', 'Proyect')"
						:options="projectOptions"
						class="fit"
						:clearable="false"
						:disabled="true" />

					<div class="report-time-form__row">
						<NcDateTimePicker
							v-model="time"
							type="date"
							:disabled="!editable" />
						<NcTextField
							required
							:value.sync="time_activity"
							type="number"
							:label="t('empleados', 'Estimate time')"
							:disabled="!editable" />
					</div>

					<div class="report-time-form__units">
						<NcCheckboxRadioSwitch
							v-model="type_time"
							:button-variant="true"
							value="minutos"
							:name="t('empleados', 'Minutes')"
							type="radio"
							button-variant-grouped="horizontal"
							:disabled="!editable">
							{{ t('empleados', 'Minutes') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch
							v-model="type_time"
							:button-variant="true"
							value="horas"
							:name="t('empleados', 'Hours')"
							type="radio"
							button-variant-grouped="horizontal"
							:disabled="!editable">
							{{ t('empleados', 'Hours') }}
						</NcCheckboxRadioSwitch>
					</div>

					<NcSelect
						v-model="listas_selected"
						:input-label="t('empleados', 'Activity')"
						:options="editActivities"
						class="fit"
						:disabled="!editable" />

					<NcTextArea
						required
						resize="vertical"
						:value.sync="description_activity"
						:label="t('empleados', 'Description activity')"
						:disabled="!editable" />

					<div v-if="isSupportReport && source.origen_id" class="report-time-form__meta">
						<span>{{ t('empleados', 'Support') }} #{{ source.origen_id }}</span>
						<button
							v-if="source.id_equipo"
							type="button"
							class="report-time-form__link"
							@click="viewSupport">
							{{ t('empleados', 'View support') }}
						</button>
					</div>

					<div class="report-time-form__actions">
						<NcButton
							v-if="editable"
							type="primary"
							@click="modify()">
							{{ t('empleados', 'Edit Activity') }}
						</NcButton>
						<NcButton
							v-else
							type="primary"
							@click="closeEdit">
							{{ t('empleados', 'Close') }}
						</NcButton>
					</div>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import CheckboxBlankCircleOutline from 'vue-material-design-icons/CheckboxBlankCircleOutline.vue'
import {
	NcListItem,
	NcActionButton,
	NcDialog,
	NcModal,
	NcButton,
	NcSelect,
	NcDateTimePicker,
	NcTextField,
	NcCheckboxRadioSwitch,
	NcTextArea,
} from '@nextcloud/vue'
import DeleteAlert from 'vue-material-design-icons/DeleteAlert.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import '../../../../css/report-time-form.css'

const MIN_EDITABLE = 40 // minutos

export default {
	name: 'ReportRow',
	components: {
		NcListItem,
		CheckboxBlankCircleOutline,
		NcActionButton,
		DeleteAlert,
		NcDialog,
		NcModal,
		NcButton,
		NcSelect,
		NcDateTimePicker,
		NcTextField,
		NcCheckboxRadioSwitch,
		NcTextArea,
		OpenInNew,
	},
	props: {
		source: { type: Object, required: true },
		index: { type: Number, required: false, default: null },
		actividades: { type: Array, required: false, default: () => [] },
		listas: { type: Array, required: false, default: () => [] },
	},
	data() {
		return {
			nowTick: Date.now(),
			timer: null,
			showDialog: false,
			ShowEdit: false,
			description_activity: '',
			type_time: 'minutos',
			time_activity: 0,
			time: new Date(),
			activity_selected: null,
			listas_selected: null,
			reportid: null,
			id_activity: null,
		}
	},
	computed: {
		buttons() {
			return [
				{
					label: this.t('empleados', 'Cancelar'),
					callback: () => { this.lastResponse = 'Pressed "Cancel"' },
				},
				{
					label: this.t('empleados', 'Eliminar'),
					type: 'primary',
					callback: () => { this.delete() },
				},
			]
		},
		// created_at viene tipo "2025-12-30 17:12:52"
		createdDate() {
			const s = this.source?.created_at || this.source?.createdAt || ''
			if (!s) return null
			// MySQL "YYYY-MM-DD HH:mm:ss" -> ISO simple "YYYY-MM-DDTHH:mm:ss"
			const d = new Date(String(s).replace(' ', 'T'))
			return isNaN(d.getTime()) ? null : d
		},
		minutosTranscurridos() {
			if (!this.createdDate) return null
			// usa nowTick para reactividad
			return (this.nowTick - this.createdDate.getTime()) / 60000
		},
		editable() {
			if (this.isAutomaticReport || this.isAbsenceReport) return false
			// editable = antes de 20 min
			if (this.minutosTranscurridos === null) return false
			return this.minutosTranscurridos < MIN_EDITABLE
		},
		canViewDetails() {
			return !this.isAbsenceReport
		},
		editModalTitle() {
			if (this.isSupportReport) {
				return t('empleados', 'Technical support report')
			}
			return this.editable
				? t('empleados', 'Edit Activity')
				: t('empleados', 'Report')
		},
		supportDeviceLabel() {
			return this.source?.nombre_dispositivo
				|| this.source?.actividad_nombre
				|| ''
		},
		projectOptions() {
			if (this.isSupportReport) {
				return [{
					id: 'soporte_ti',
					label: this.supportDeviceLabel
						? `${t('empleados', 'Support TI')} · ${this.supportDeviceLabel}`
						: t('empleados', 'Support TI'),
				}]
			}
			return this.editActivities
		},
		isSupportReport() {
			return this.source?.origen === 'soporte_ti'
		},
		isAbsenceReport() {
			return this.source?.tipo_trabajo === 'ausencia'
				|| Number(this.source?.id_cliente) === 99999
				|| Number(this.source?.id_actividad) === 99999
		},
		isAutomaticReport() {
			const origin = String(this.source?.origen || '')
			return origin !== '' && !['manual', 'manual_interno'].includes(origin)
		},
		isInternalReport() {
			return this.source?.tipo_trabajo === 'interno' || (this.source?.id_cliente == null && this.source?.tipo_trabajo !== 'ausencia')
		},
		originLabel() {
			if (this.isSupportReport) return t('empleados', 'Support TI')
			if (this.source?.origen === 'manual_interno' || !this.source?.origen) return t('empleados', 'Manual internal report')
			return String(this.source.origen)
		},
		editActivities() {
			const type = this.isInternalReport ? 'interno' : 'cliente'
			return this.listas.filter(activity => (activity.tipo_actividad || 'cliente') === type)
		},
		indicatorColor() {
			// rojo editable, verde bloqueado
			return this.editable ? 'red' : 'green'
		},
		counterText() {
			const v = this.source?.tiempo_registrado ?? this.source?.tiempoRegistrado ?? 0
			const mins = Number.isFinite(Number(v)) ? Math.trunc(Number(v)) : 0
			return `${mins} min`
		},
		detailsText() {
			return this.source?.fecha_registro ?? this.source?.fechaRegistro ?? ''
		},
		titleText() {
			if (this.isSupportReport) {
				return `${t('empleados', 'Support TI')} · ${this.supportDeviceLabel || this.source?.actividadNombre || ''}`
			}
			return this.isInternalReport
				? `${t('empleados', 'Internal work')} · ${this.source?.actividadNombre || ''}`
				: (this.source?.clienteNombre || '')
		},
		subnameText() {
			if (this.isSupportReport) {
				return this.source?.descripcion || t('empleados', 'Technical support report')
			}
			if (!this.isInternalReport) return this.source?.actividadNombre || ''
			const activity = this.listas.find(item => Number(item.id) === Number(this.source?.id_actividad))
			const areas = (activity?.areas || []).map(area => area.nombre).filter(Boolean).join(', ')
			return areas ? `${t('empleados', 'Specific areas')}: ${areas}` : t('empleados', 'Entire company')
		},
	},
	mounted() {
		// Fuerza recalcular cada 30s para que cambie el color cuando se cumplan 20 min
		this.timer = setInterval(() => {
			this.nowTick = Date.now()
		}, 30 * 1000)
	},
	beforeDestroy() {
		if (this.timer) clearInterval(this.timer)
	},

	methods: {
		t, // exponer i18n a la plantilla
		 onKeyDown(e) {
			if (e.key === 'Escape') this.onEsc()
		},

		onEsc() {
			this.showDialog = false
		},
		handleRowClick() {
			if (this.canViewDetails) this.edit()
		},
		viewSupport() {
			this.$router.push({ name: 'Inventario', query: { deviceId: String(this.source.id_equipo) } })
		},

		async delete() {
			try {
				await axios.post(generateUrl('/apps/empleados/deleteReport'), {
					id: parseInt(this.reportid),
				}).then(
					() => {
						showSuccess(t('empleados', 'Se ha eliminadon exitosamente'))
						this.$bus.emit('gethistorial')
						this.closeEdit()
					},
					(err) => { showError(this.backendError(err)) },
				)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
			this.showDialog = false
		},
		edit() {
			if (this.isSupportReport) {
				this.activity_selected = {
					id: 'soporte_ti',
					label: this.supportDeviceLabel
						? `${t('empleados', 'Support TI')} · ${this.supportDeviceLabel}`
						: t('empleados', 'Support TI'),
				}
			} else {
				this.activity_selected = this.source.clienteNombre
			}
			this.listas_selected = this.source.actividadNombre
			this.id_activity = this.source.id_actividad
			this.description_activity = this.source.descripcion
			this.time_activity = this.source.tiempo_registrado
			this.type_time = 'minutos'
			this.time = new Date(this.source.fecha_registro)
			this.ShowEdit = true
		},
		async modify() {
			try {
				await axios.post(generateUrl('/apps/empleados/modificarReporte'), {
					id_reporte: parseInt(this.source.id),
					id_actividad: this.listas_selected.id ?? this.id_activity,
					tipo_trabajo: this.source.tipo_trabajo || (this.isInternalReport ? 'interno' : 'cliente'),
					id_cliente: this.isInternalReport ? null : this.source.id_cliente,
					tiemporegistrado: Number(this.time_activity ?? 0),
					descripcion: this.description_activity,
					tipo: this.type_time,
					fecharegistrada: this.time.toISOString().slice(0, 10),
				}).then(
					() => {
						showSuccess(t('empleados', 'Datos actualizados correctamente'))
						this.$bus.emit('gethistorial')
						this.closeEdit()
					},
					(err) => { showError(this.backendError(err)) },
				)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},
		closeEdit() {
			this.ShowEdit = false
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
.row { padding: 1px 1px; border-bottom: 1px solid var(--color-border); }
.origin-badge,
.billable-badge {
	display: inline-block;
	margin-left: 6px;
	padding: 1px 6px;
	border-radius: 999px;
	background: var(--color-background-dark);
	font-size: 11px;
}
</style>
