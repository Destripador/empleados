<!-- eslint-disable no-unmodified-loop-condition -->
<template>
	<div class="editar-ausencia">
		<!-- Atrapa el autofocus del modal para que no abra el select de tipo -->
		<span ref="focusSink" tabindex="0" class="focus-sink" />
		<NcNoteCard
			v-if="admin"
			type="warning"
			:heading="t('empleados', 'ATTENTION')"
			:text="t('empleados', 'You are editing an absence in admin mode.')" />

		<!-- Tipo de ausencia -->
		<section class="form-section">
			<h3>{{ t('empleados', 'Absence type') }}</h3>
			<NcSelect
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

		<!-- Selector de fechas -->
		<section class="form-section">
			<h3>{{ t('empleados', 'Absence period') }}</h3>

			<div class="date-inputs">
				<div class="date-field">
					<label>{{ t('empleados', 'From') }}</label>
					<input
						v-model="fechaDesdeStr"
						type="date"
						class="date-input"
						@change="recalcularDias">
				</div>
				<div class="date-field">
					<label>{{ t('empleados', 'To') }}</label>
					<input
						v-model="fechaHastaStr"
						type="date"
						class="date-input"
						@change="recalcularDias">
				</div>
			</div>

			<!-- Resumen del periodo -->
			<div v-if="diasHabiles > 0" class="period-grid">
				<div class="period-item">
					<span>{{ t('empleados', 'Business days') }}</span>
					<strong>{{ diasHabiles }}</strong>
				</div>
				<div v-if="AusenciaSeleccionada && Number(AusenciaSeleccionada.solicitar_prima_vacacional) === 1" class="period-item">
					<span>{{ t('empleados', 'Available days') }}</span>
					<strong>{{ TotalDias }}</strong>
				</div>
				<div v-if="AusenciaSeleccionada && Number(AusenciaSeleccionada.solicitar_prima_vacacional) === 1" class="period-item">
					<span>{{ t('empleados', 'Remaining after edit') }}</span>
					<strong>{{ diasRestantes }}</strong>
				</div>
			</div>

			<NcNoteCard
				v-if="loadingEmpleado"
				type="info"
				:text="t('empleados', 'Loading employee days...')" />

			<NcNoteCard
				v-if="exceedsAvailableDays"
				type="warning"
				:text="t('empleados', 'You cannot request more days than available.')" />

			<NcNoteCard
				v-if="excedeFechaLimite"
				type="warning"
				:text="t('empleados', 'You cannot schedule vacation days outside your current period. The limit to use these days is {fecha}.', { fecha: fechaLimiteFormateada })" />
		</section>

		<!-- Archivo (si aplica) -->
		<section v-if="AusenciaSeleccionada && AusenciaSeleccionada.solicitar_archivo" class="form-section">
			<h3>{{ t('empleados', 'Files') }}</h3>
			<NcNoteCard type="info" :text="t('empleados', 'It is necessary to upload a file to justify your absence.')" />
			<input ref="fileInput"
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
					:disabled="primaDisabled">
					{{ t('empleados', 'Request vacation bonus') }}
				</NcCheckboxRadioSwitch>
				<NcNoteCard
					v-if="primaVacacionalUsada"
					type="warning"
					:text="t('empleados', 'Your vacation bonus for this year has already been used. You may request it again if your previous absence is cancelled.')" />
				<NcNoteCard
					v-if="bloqueaPorDiciembre"
					type="warning"
					:text="t('empleados', 'You cannot request the vacation bonus for a period that includes days in December.')" />
			</template>

			<NcTextArea
				v-model="comentarios"
				resize="vertical"
				:label="t('empleados', 'Comments')"
				:placeholder="t('empleados', 'Add a comment to your request (OPTIONAL)')"
				:helper-text="t('empleados', 'Add a comment to your request (OPTIONAL)')" />
		</section>

		<!-- Acciones -->
		<div class="form-actions">
			<NcLoadingIcon v-if="loading" :size="32" />
			<NcButton
				v-else
				type="primary"
				:disabled="!canSave"
				wide
				@click="guardar">
				<template #icon>
					<ContentSave :size="20" />
				</template>
				{{ t('empleados', 'Save changes') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import ContentSave from 'vue-material-design-icons/ContentSave.vue'
import Upload from 'vue-material-design-icons/Upload.vue'
import FileDocumentOutline from 'vue-material-design-icons/FileDocumentOutline.vue'

import {
	NcButton,
	NcSelect,
	NcTextArea,
	NcCheckboxRadioSwitch,
	NcNoteCard,
	NcLoadingIcon,
} from '@nextcloud/vue'

export default {
	name: 'EditarAusencia',

	components: {
		NcButton,
		NcSelect,
		NcTextArea,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		NcLoadingIcon,
		ContentSave,
		Upload,
		FileDocumentOutline,
	},

	props: {
		ausencia: { type: Object, required: true },
		diasDisponibles: { type: [String, Number], required: true },
		fechaLimitePeriodoActual: { type: String, default: null },
		prima: { type: Number, default: 0 },
		employees: { type: Array, default: () => [] },
		admin: { type: Boolean, default: false },
		idHistorial: { type: [Number, String], default: 0 },
		usernameEmpleado: { type: String, default: null },
	},

	emits: ['saved', 'close'],

	data() {
		return {
			TipoAusencias: [],
			AusenciaSeleccionada: null,
			// Fechas como string yyyy-mm-dd para el input[type=date]
			fechaDesdeStr: this.ausencia.fecha_de
				? this.ausencia.fecha_de.substring(0, 10)
				: '',
			fechaHastaStr: this.ausencia.fecha_hasta
				? this.ausencia.fecha_hasta.substring(0, 10)
				: '',
			diasHabiles: 0,
			TotalDias: parseInt(this.diasDisponibles, 10) || 0,
			comentarios: this.ausencia.notas || '',
			SolicitarPrima: Number(this.ausencia.prima_vacacional) === 1,
			selectedFiles: [],
			loading: false,
			primaVacacionalUsada: false,
			// ← NUEVO: datos frescos del empleado dueño de la ausencia (modo admin)
			loadingEmpleado: false,
			diasInfoEmpleado: null,
		}
	},

	computed: {
		// Días que tenía la ausencia original (para devolver y restar correctamente)
		diasOriginales() {
			return Number(this.ausencia.dias_solicitados) || 0
		},

		diasDisponiblesVigente() {
			const val = this.diasInfoEmpleado?.dias_disponibles ?? this.diasDisponibles
			return parseInt(val, 10) || 0
		},

		fechaLimitePeriodoVigente() {
			return this.diasInfoEmpleado?.fecha_limite_periodo_actual ?? this.fechaLimitePeriodoActual
		},

		// Días disponibles ajustados: se devuelven los días originales, luego se restan los nuevos
		diasRestantes() {
			if (!this.AusenciaSeleccionada
				|| Number(this.AusenciaSeleccionada.solicitar_prima_vacacional) !== 1) {
				return this.TotalDias
			}
			// Días disponibles reales = actuales + originales (porque ya se descontaron)
			const disponiblesReales = this.TotalDias + this.diasOriginales
			return disponiblesReales - this.diasHabiles
		},

		exceedsAvailableDays() {
			if (!this.AusenciaSeleccionada
				|| Number(this.AusenciaSeleccionada.solicitar_prima_vacacional) !== 1) {
				return false
			}
			const disponiblesReales = this.TotalDias + this.diasOriginales
			return this.diasHabiles > disponiblesReales
		},

		esAusenciaVacacional() {
			return this.AusenciaSeleccionada && Number(this.AusenciaSeleccionada.solicitar_prima_vacacional) === 1
		},

		excedeFechaLimite() {
			if (!this.esAusenciaVacacional || !this.fechaLimitePeriodoVigente || !this.fechaHastaStr) return false
			const limite = new Date(this.fechaLimitePeriodoVigente + 'T00:00:00')
			const desde = new Date(this.fechaDesdeStr + 'T00:00:00')
			const hasta = new Date(this.fechaHastaStr + 'T00:00:00')
			return desde > limite || hasta > limite
		},

		fechaLimiteFormateada() {
			if (!this.fechaLimitePeriodoVigente) return ''
			return new Date(this.fechaLimitePeriodoVigente + 'T00:00:00').toLocaleDateString('es-MX')
		},

		canSave() {
			return this.AusenciaSeleccionada
				&& this.fechaDesdeStr
				&& this.fechaHastaStr
				&& this.diasHabiles > 0
				&& !this.exceedsAvailableDays
				&& !this.excedeFechaLimite
				&& !this.loadingEmpleado
		},

		primaDisabled() {
			return this.primaVacacionalUsada || this.diasHabiles < 2 || this.bloqueaPorDiciembre
		},

		incluyeDiciembre() {
			if (!this.fechaDesdeStr || !this.fechaHastaStr) return false
			const start = new Date(this.fechaDesdeStr + 'T00:00:00')
			const end = new Date(this.fechaHastaStr + 'T00:00:00')
			let cursor = new Date(start.getFullYear(), start.getMonth(), 1)
			const finMes = new Date(end.getFullYear(), end.getMonth(), 1)
			while (cursor <= finMes) {
				if (cursor.getMonth() === 11) return true

				cursor = new Date(
					cursor.getFullYear(),
					cursor.getMonth() + 1,
					1,
				)
			}
			return false
		},

		bloqueaPorDiciembre() {
			return this.esAusenciaVacacional && this.incluyeDiciembre
		},
	},

	watch: {
		async AusenciaSeleccionada(tipo) {
			this.primaVacacionalUsada = false
			if (tipo && Number(tipo.solicitar_prima_vacacional) === 1) {
				await this.checkPrimaVacacional(this.ausencia.id_historial_ausencias)
			}
		},
		fechaDesdeStr() {
			if (this.AusenciaSeleccionada && Number(this.AusenciaSeleccionada.solicitar_prima_vacacional) === 1) {
				this.checkPrimaVacacional(this.ausencia.id_historial_ausencias)
			}
		},

		incluyeDiciembre(nuevo) {
			if (nuevo && this.SolicitarPrima) {
				this.SolicitarPrima = false
			}
		},
	},

	mounted() {
		this.GetTipoAusencias()
		this.recalcularDias()
		if (this.ausencia?.solicitar_prima_vacacional === 1) {
			this.checkPrimaVacacional(this.ausencia.id_historial_ausencias)
		}
		if (this.admin && this.usernameEmpleado) {
			const empleadoMatch = this.employees.find(e => e.user === this.usernameEmpleado)
			if (empleadoMatch) {
				this.fetchDiasEmpleado(empleadoMatch.Id_empleados)
			}
		}
	},

	methods: {
		t,

		async fetchDiasEmpleado(idEmpleado) {
			this.loadingEmpleado = true
			try {
				const res = await axios.post(generateUrl('/apps/empleados/GetAusenciasByUser'), {
					id: idEmpleado,
				})
				this.diasInfoEmpleado = res?.data?.ocs?.data?.[0] || null
				this.TotalDias = this.diasDisponiblesVigente
			} catch (err) {
				showError(t('empleados', 'Error al obtener los días del empleado'))
			} finally {
				this.loadingEmpleado = false
			}
		},

		async GetTipoAusencias() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/getTipo'))
				this.TipoAusencias = response.data.map(item => ({
					id: item.id_tipo_ausencia,
					label: item.nombre,
					descripcion: item.descripcion,
					solicitar_archivo: item.solicitar_archivo,
					solicitar_prima_vacacional: item.solicitar_prima_vacacional,
				}))
				this.AusenciaSeleccionada = this.TipoAusencias.find(
					t => String(t.id) === String(this.ausencia.id_tipo_ausencia),
				) || null

				if (this.AusenciaSeleccionada
					&& Number(this.AusenciaSeleccionada.solicitar_prima_vacacional) === 1) {
					await this.checkPrimaVacacional(this.ausencia.id_historial_ausencias)
				}
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [{error}]', { error: String(err) }))
			}
		},

		recalcularDias() {
			if (!this.fechaDesdeStr || !this.fechaHastaStr) {
				this.diasHabiles = 0
				return
			}
			const start = new Date(this.fechaDesdeStr + 'T00:00:00')
			const end = new Date(this.fechaHastaStr + 'T00:00:00')

			if (end < start) {
				this.diasHabiles = 0
				return
			}

			const fecha = new Date(start)
			let count = 0
			// eslint-disable-next-line no-unmodified-loop-condition
			while (fecha <= end) {
				const dia = fecha.getDay()
				if (dia !== 0 && dia !== 6) count++
				fecha.setDate(fecha.getDate() + 1)
			}
			this.diasHabiles = count

			if (this.diasHabiles < 2) {
				this.SolicitarPrima = false
			}
		},

		handleDrop(event) {
			this.uploadFile({ target: { files: event.dataTransfer.files } })
		},

		uploadFile(event) {
			this.selectedFiles = Array.from(event.target.files || event.dataTransfer.files)
		},

		formatFileSize(size) {
			return size < 1024 * 1024
				? `${(size / 1024).toFixed(2)} KB`
				: `${(size / (1024 * 1024)).toFixed(2)} MB`
		},

		async guardar() {
			if (!this.canSave) return
			this.loading = true
			try {
				const formData = new FormData()
				formData.append('id', this.ausencia.id_historial_ausencias)
				formData.append('id_tipo_ausencia', this.AusenciaSeleccionada.id)
				formData.append('fecha_de', this.fechaDesdeStr)
				formData.append('fecha_hasta', this.fechaHastaStr)
				formData.append('dias_solicitados', this.diasHabiles)
				formData.append('prima_vacacional', this.SolicitarPrima ? 1 : 0)
				formData.append('notas', this.comentarios || '')

				for (let i = 0; i < this.selectedFiles.length; i++) {
					formData.append('archivos[]', this.selectedFiles[i])
				}

				const response = await axios.post(
					generateUrl('/apps/empleados/EditarAusencia'),
					formData,
					{ headers: { 'Content-Type': 'multipart/form-data' } },
				)

				if (response.data?.ocs?.data?.success) {
					showSuccess(t('empleados', 'Absence updated successfully'))
					this.$emit('saved')
				} else {
					showError(t('empleados', 'Error updating absence'))
				}
			} catch (err) {
				showError(t('empleados', 'Error updating absence: {error}', { error: String(err) }))
			} finally {
				this.loading = false
			}
		},

		async checkPrimaVacacional(excludeId = 0) {
			try {
				const res = await axios.get(
					generateUrl('/apps/empleados/check-prima-vacacional')
					+ `?exclude_id=${excludeId}`
					+ `&fecha_de=${encodeURIComponent(this.fechaDesdeStr)}`,
				)
				this.primaVacacionalUsada = res.data.ocs.data.used === true
			} catch (e) {
				console.error('Error al verificar prima vacacional', e)
			}
		},
	},
}
</script>

<style scoped>
.form-section {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.form-section h3 {
	font-size: 0.85rem;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: var(--color-text-maxcontrast);
	margin: 0;
}

/* ── Date inputs ─────────────────────────────── */
.date-inputs {
	display: flex;
	gap: 16px;
	flex-wrap: wrap;
}

.date-field {
	display: flex;
	flex-direction: column;
	gap: 4px;
	flex: 1;
	min-width: 160px;
}

.date-field label {
	font-size: 0.8rem;
	color: var(--color-text-maxcontrast);
}

.date-input {
	padding: 8px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 0.95rem;
	width: 100%;
}

.date-input:focus {
	outline: none;
	border-color: var(--color-primary);
	box-shadow: 0 0 0 2px var(--color-primary-light);
}

/* ── Period grid ─────────────────────────────── */
.period-grid {
	display: flex;
	gap: 16px;
	flex-wrap: wrap;
}

.period-item {
	display: flex;
	flex-direction: column;
	gap: 2px;
	background: var(--color-background-hover);
	border-radius: var(--border-radius);
	padding: 8px 14px;
}

.period-item span {
	font-size: 0.75rem;
	color: var(--color-text-maxcontrast);
}

.period-item strong {
	font-size: 1.1rem;
}

/* ── File drop ───────────────────────────────── */
.file-input { display: none; }

.drop-area {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	padding: 20px;
	border: 2px dashed var(--color-border);
	border-radius: var(--border-radius);
	cursor: pointer;
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
	width: 100%;
	transition: border-color 0.2s;
}

.drop-area:hover {
	border-color: var(--color-primary);
	color: var(--color-primary);
}

.file-list {
	list-style: none;
	padding: 0;
	margin: 0;
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.file-list li {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 0.9rem;
}

.form-actions {
	display: flex;
	justify-content: flex-end;
}

.editar-ausencia {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-height: 75vh;
    overflow-y: auto;
}

.focus-sink {
	position: absolute;
	width: 1px;
	height: 1px;
	overflow: hidden;
	opacity: 0;
	pointer-events: none;
}
</style>
