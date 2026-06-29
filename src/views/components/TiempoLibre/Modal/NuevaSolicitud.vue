<template id="content">
	<form class="absence-request" @submit.prevent="EnviarAusencia">
		<NcNoteCard
			v-if="admin"
			type="warning"
			:heading="t('empleados', 'ATTENTION')"
			:text="t('empleados', 'You are registering an absence in admin mode. Notifications and automatic messages will remain active, and the corresponding days will be deducted from the selected employee.')" />

		<section v-if="admin" class="form-section">
			<h3>{{ t('empleados', 'Employee') }}</h3>
			<NcSelect v-bind="propsEmployees" v-model="employees_list" />
		</section>

		<section class="form-section">
			<h3>{{ t('empleados', 'Absence type') }}</h3>
			<NcSelect
				id="id"
				v-model="AusenciaSeleccionada"
				:no-wrap="true"
				:options="TipoAusencias"
				:keep-open="false"
				:input-label="t('empleados', 'Absence type')" />

			<NcNoteCard
				v-if="AusenciaSeleccionada && AusenciaSeleccionada.descripcion"
				type="info"
				:text="AusenciaSeleccionada.descripcion" />
		</section>

		<NcNoteCard
			v-if="exceedsAvailableDays"
			type="info"
			:text="t('empleados', 'You cannot request more days than available.')" />

		<template v-if="AusenciaSeleccionada && !exceedsAvailableDays">
			<section class="form-section">
				<h3>{{ t('empleados', 'Absence period') }}</h3>
				<div class="period-grid">
					<div
						v-for="item in periodItems"
						:key="item.label"
						class="period-item">
						<span>{{ item.label }}</span>
						<strong>{{ item.value }}</strong>
					</div>
				</div>
			</section>

			<section v-if="AusenciaSeleccionada.solicitar_archivo" class="form-section">
				<h3>{{ t('empleados', 'Files') }}</h3>
				<NcNoteCard type="info" :text="t('empleados', 'It is necessary to upload a file to justify your absence.')" />

				<input
					ref="fileInput"
					type="file"
					class="file-input"
					multiple
					@change="uploadFile">

				<button
					type="button"
					class="drop-area"
					@dragover.prevent
					@dragenter.prevent
					@drop.prevent="handleDrop"
					@click="$refs.fileInput.click()">
					<Upload :size="24" />
					<span>{{ t('empleados', 'Drop files here or click to select') }}</span>
				</button>

				<ul v-if="selectedFiles.length > 0" class="file-list">
					<li v-for="(file, index) in selectedFiles" :key="index">
						<FileDocumentOutline :size="20" />
						<span>{{ file.name }}</span>
						<small>{{ formatFileSize(file.size) }}</small>
					</li>
				</ul>
			</section>
			<!-- Prima y comentarios -->
			<section class="form-section">
				<template v-if="AusenciaSeleccionada && AusenciaSeleccionada.solicitar_prima_vacacional == 1">
					<NcCheckboxRadioSwitch
						v-model="SolicitarPrima"
						:disabled="primaVacacionalUsada">
						{{ t('empleados', 'Request vacation bonus') }}
					</NcCheckboxRadioSwitch>
					<NcNoteCard
						v-if="primaVacacionalUsada"
						type="warning"
						:text="t('empleados', 'Your vacation bonus for this year has already been used. You may request it again if your previous absence is cancelled.')" />
				</template>

				<NcTextArea
					v-model="comentarios"
					resize="vertical"
					:label="t('empleados', 'Comments')"
					:placeholder="t('empleados', 'Add a comment to your request (OPTIONAL)')"
					:helper-text="t('empleados', 'Add a comment to your request (OPTIONAL)')" />
			</section>

			<div class="form-actions">
				<NcLoadingIcon v-if="loading" :size="32" />
				<NcButton
					v-else
					type="primary"
					native-type="submit"
					wide>
					<template #icon>
						<Airplane :size="20" />
					</template>
					{{ t('empleados', 'Submit') }}
				</NcButton>
			</div>
		</template>
	</form>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

// icons
import Airplane from 'vue-material-design-icons/Airplane.vue'
import FileDocumentOutline from 'vue-material-design-icons/FileDocumentOutline.vue'
import Upload from 'vue-material-design-icons/Upload.vue'

import {
	NcButton,
	NcSelect,
	NcTextArea,
	NcCheckboxRadioSwitch,
	NcNoteCard,
	NcLoadingIcon,
} from '@nextcloud/vue'

export default {
	name: 'NuevaSolicitud',

	components: {
		NcButton,
		NcSelect,
		NcTextArea,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		Airplane,
		FileDocumentOutline,
		NcLoadingIcon,
		Upload,
	},

	inject: ['employee'],

	props: {
		diasSolicitados: { type: Number, required: true },
		diasDisponibles: { type: String, required: true },
		date: {
			type: Object,
			required: true,
			validator: (value) => {
				return value
					&& value.start instanceof Date
					&& (value.end instanceof Date || value.end === null)
			},
		},
		prima: { type: Number, default: 0 },
		employees: { type: Array, default: () => [] },
		admin: { type: Boolean, default: false },
	},

	data() {
		return {
			TipoAusencias: [],
			AusenciaSeleccionada: null,
			TotalDias: 0,
			RestanteDias: 0,
			comentarios: '',
			SolicitarPrima: false,
			selectedFiles: [],
			loading: false,
			propsEmployees: {
				inputLabel: t('empleados', 'All employees'),
				userSelect: true,
				closeOnSelect: true,
				options: this.employees,
			},
			employees_list: [],
			primaVacacionalUsada: false,
		}
	},

	computed: {
		exceedsAvailableDays() {
			return this.AusenciaSeleccionada
				&& Number(this.AusenciaSeleccionada.solicitar_prima_vacacional) === 1
				&& this.diasSolicitados > this.TotalDias
		},

		periodItems() {
			const items = [
				{
					label: t('empleados', 'Days to take'),
					value: this.diasSolicitados,
				},
				{
					label: t('empleados', 'From:'),
					value: this.date?.start?.toLocaleDateString() || '-',
				},
				{
					label: t('empleados', 'To:'),
					value: this.date?.end?.toLocaleDateString() || t('empleados', 'Undefined'),
				},
			]

			if (Number(this.AusenciaSeleccionada?.solicitar_prima_vacacional) === 1) {
				items.unshift({
					label: t('empleados', 'Available days'),
					value: this.TotalDias,
				})
				items.push({
					label: t('empleados', 'Remaining days'),
					value: this.RestanteDias,
				})
			}

			return items
		},
	},

	watch: {
		async AusenciaSeleccionada(tipo) {
			this.SolicitarPrima = false
			this.primaVacacionalUsada = false
			if (tipo && Number(tipo.solicitar_prima_vacacional) === 1) {
				await this.checkPrimaVacacional()
			}
		},
	},

	mounted() {
		this.TotalDias = parseInt(this.diasDisponibles, 10)
		this.RestanteDias = this.TotalDias - this.diasSolicitados
		this.GetTipoAusencias()
		if (this.AusenciaSeleccionada && Number(this.AusenciaSeleccionada.solicitar_prima_vacacional) === 1) {
			this.checkPrimaVacacional()
		}
	},

	methods: {
		t,

		async GetTipoAusencias() {
			try {
				await axios.get(generateUrl('/apps/empleados/getTipo'))
					.then(
						(response) => {
							this.TipoAusencias = response.data
								.filter(item => !(item.solicitar_prima_vacacional === 1 && this.diasSolicitados > this.TotalDias))
								.map(item => ({
									id: item.id_tipo_ausencia,
									label: item.nombre,
									descripcion: item.descripcion,
									solicitar_archivo: item.solicitar_archivo,
									solicitar_prima_vacacional: item.solicitar_prima_vacacional,
								}))
						},
						(err) => { showError(err) },
					)
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [01] [{error}]', { error: String(err) }))
			}
		},

		handleDrop(event) {
			const files = event.dataTransfer.files
			this.uploadFile({ target: { files } })
		},

		uploadFile(event) {
			const files = event.target.files || event.dataTransfer.files
			this.selectedFiles = Array.from(files)
		},

		formatFileSize(size) {
			return size < 1024 * 1024
				? `${(size / 1024).toFixed(2)} KB`
				: `${(size / (1024 * 1024)).toFixed(2)} MB`
		},

		async EnviarAusencia() {
			this.loading = true
			try {
				const formData = new FormData()
				if (this.admin && this.employees_list?.user) {
					formData.append('id_usuario', this.employees_list.user)
				}
				formData.append('id_tipo_ausencia', this.AusenciaSeleccionada.id)
				formData.append('dias_solicitados', this.diasSolicitados)
				formData.append('fecha_de', this.date.start.toLocaleDateString())
				formData.append('fecha_hasta', this.date.end ? this.date.end.toLocaleDateString() : '')
				formData.append('prima_vacacional', this.SolicitarPrima ? 1 : 0)
				formData.append('notas', this.comentarios || '')

				for (let i = 0; i < this.selectedFiles.length; i++) {
					formData.append('archivos[]', this.selectedFiles[i])
				}

				const response = await axios.post(
					generateUrl('/apps/empleados/EnviarAusencia'),
					formData,
					{ headers: { 'Content-Type': 'multipart/form-data' } },
				)

				if (response.data?.ocs?.data?.success) {
					showSuccess(t('empleados', 'Absence request submitted successfully'))
					this.$bus.emit('close-solicitud')
				} else {
					showError(t('empleados', 'Error sending absence request'))
				}

				this.loading = false
			} catch (err) {
				this.loading = false
				showError(t('empleados', 'Error sending absence request: {error}', { error: String(err) }))
			}
		},

		async checkPrimaVacacional(excludeId = 0) {
			try {
				let url = generateUrl('/apps/empleados/check-prima-vacacional')
					+ `?exclude_id=${excludeId}`
				if (this.admin && this.employees_list?.user) {
					url += `&id_usuario=${this.employees_list.user}`
				}
				const res = await axios.get(url)
				this.primaVacacionalUsada = res.data.ocs.data.used === true
			} catch (e) {
				console.error('Error al verificar prima vacacional', e)
			}
		},
	},
}
</script>

<style scoped>
.absence-request {
	display: flex;
	flex-direction: column;
	gap: 18px;
	width: min(760px, calc(100vw - 48px));
	padding: 24px;
}

.form-section {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.form-section h3 {
	margin: 0;
	font-size: 16px;
	font-weight: 700;
}

.period-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
	gap: 8px;
}

.period-item {
	display: grid;
	gap: 4px;
	min-height: 64px;
	padding: 10px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-main-background);
}

.period-item span,
.file-list small {
	color: var(--color-text-maxcontrast);
}

.period-item strong {
	font-size: 18px;
}

.file-input {
	display: none;
}

.drop-area {
	display: flex;
	gap: 8px;
	align-items: center;
	justify-content: center;
	min-height: 88px;
	border: 2px dashed var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-background-hover);
	color: var(--color-main-text);
	cursor: pointer;
	font-weight: 600;
}

.drop-area:hover,
.drop-area:focus-visible {
	border-color: var(--color-primary-element);
	background-color: var(--color-primary-element-light);
}

.file-list {
	display: grid;
	gap: 6px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.file-list li {
	display: grid;
	grid-template-columns: 24px minmax(0, 1fr) auto;
	gap: 8px;
	align-items: center;
	padding: 8px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius, 6px);
}

.form-actions {
	display: flex;
	justify-content: flex-end;
}
</style>
