<template id="content">
	<form class="absence-request" @submit.prevent="EnviarAusencia">
		<NcButton
			ref="initialFocusButton"
			class="focus-placeholder"
			type="tertiary"
			tabindex="0"
			@click.prevent>
			Inicio
		</NcButton>
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

		<NcNoteCard
			v-if="excedeFechaLimite"
			type="warning"
			:text="t('empleados', 'You cannot schedule vacation days outside your current period. The limit to use these days is {fecha}.', { fecha: fechaLimiteFormateada })" />

		<template v-if="AusenciaSeleccionada && !exceedsAvailableDays">
			<NcNoteCard
				v-if="esAusenciaVacacional && diasAcumuladosNum > 0"
				type="warning"
				:text="t('empleados', 'You have accumulated vacation days from your previous period: {dias} days, available until {fecha}. After that date they will be lost.', { dias: diasAcumuladosNum, fecha: fechaExpiracionFormateada })" />

			<NcNoteCard
				v-if="esAusenciaVacacional && diasDelAcumuladoAUsar > 0 && diasDelPeriodoActualAUsar > 0"
				type="info"
				:text="t('empleados', 'This request will be split: {acumulado} day(s) will be taken from your accumulated (expiring) balance, and {actual} day(s) from your current period.', { acumulado: diasDelAcumuladoAUsar, actual: diasDelPeriodoActualAUsar })" />

			<NcNoteCard
				v-if="acumuladoNoAplicaPorFecha"
				type="info"
				:text="t('empleados', 'Vacation days will be deducted from the balance of your current period, as accrued vacation days must be used within the corresponding period (before {fecha}).', { fecha: fechaExpiracionFormateada })" />

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
		diasDisponibles: { type: [String, Number], required: true },
		diasAcumulados: { type: [Number, String], default: 0 },
		fechaExpiracionAcumulados: { type: String, default: null },
		fechaLimitePeriodoActual: { type: String, default: null },
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
			loadingEmpleado: false,
			diasDisponiblesActual: null,
			diasAcumuladosActual: null,
			diasInfoEmpleado: null,
		}
	},

	computed: {
		diasAcumuladosNum() {
			const val = this.diasInfoEmpleado?.dias_acumulados ?? this.diasAcumulados
			return parseFloat(val) || 0
		},

		esAusenciaVacacional() {
			return this.AusenciaSeleccionada && Number(this.AusenciaSeleccionada.solicitar_prima_vacacional) === 1
		},

		incluyeDiciembre() {
			if (!this.date?.start) return false
			const start = new Date(this.date.start)
			const end = this.date.end ? new Date(this.date.end) : start
			let cursor = new Date(start.getFullYear(), start.getMonth(), 1)
			const finMes = new Date(end.getFullYear(), end.getMonth(), 1)
			while (cursor <= finMes) {
				if (cursor.getMonth() === 11) return true

				cursor = new Date(
					cursor.getFullYear(),
					cursor.getMonth() + 1,
					1
				)
			}
			return false
		},

		bloqueaPorDiciembre() {
			return this.esAusenciaVacacional && this.incluyeDiciembre
		},

		fechaExpiracionAcumuladosVigente() {
			return this.diasInfoEmpleado?.fecha_expiracion_acumulados ?? this.fechaExpiracionAcumulados
		},

		fechaLimitePeriodoVigente() {
			return this.diasInfoEmpleado?.fecha_limite_periodo_actual ?? this.fechaLimitePeriodoActual
		},

		// FIX: usar parseFechaLocal en vez de `new Date(stringYmd)` para evitar
		// el desfase de un día causado por la interpretación UTC de JS.
		fechaExpiracionFormateada() {
			const d = this.parseFechaLocal(this.fechaExpiracionAcumuladosVigente)
			if (!d) return ''
			return d.toLocaleDateString('es-MX')
		},

		// FIX: mismo parseo seguro
		excedeFechaLimite() {
			if (!this.esAusenciaVacacional || !this.fechaLimitePeriodoVigente || !this.date?.start) return false
			const limite = this.parseFechaLocal(this.fechaLimitePeriodoVigente)
			if (!limite) return false
			limite.setHours(0, 0, 0, 0)
			const start = new Date(this.date.start)
			start.setHours(0, 0, 0, 0)
			const end = this.date.end ? new Date(this.date.end) : start
			end.setHours(0, 0, 0, 0)
			return start > limite || end > limite
		},

		// FIX: mismo parseo seguro
		fechaLimiteFormateada() {
			const d = this.parseFechaLocal(this.fechaLimitePeriodoVigente)
			if (!d) return ''
			return d.toLocaleDateString('es-MX')
		},

		// FIX: mismo parseo seguro — este es el que causaba el split incorrecto
		// (2/2 en vez de 3/1) por estar comparando contra un límite un día antes
		// del real.
		diasDentroDeVigencia() {
			if (!this.fechaExpiracionAcumuladosVigente || !this.date?.start) return this.diasSolicitados

			const limite = this.parseFechaLocal(this.fechaExpiracionAcumuladosVigente)
			if (!limite) return this.diasSolicitados
			limite.setHours(0, 0, 0, 0)
			const start = new Date(this.date.start)
			start.setHours(0, 0, 0, 0)
			const end = this.date.end ? new Date(this.date.end) : start
			end.setHours(0, 0, 0, 0)

			const cursor = new Date(start)
			let count = 0
			// eslint-disable-next-line no-unmodified-loop-condition
			while (cursor <= end) {
				if (cursor > limite) break
				const dia = cursor.getDay()
				if (dia !== 0 && dia !== 6) count++
				cursor.setDate(cursor.getDate() + 1)
			}
			return count
		},

		diasDelAcumuladoAUsar() {
			if (!this.esAusenciaVacacional) return 0
			return Math.min(this.diasAcumuladosNum, this.diasSolicitados, this.diasDentroDeVigencia)
		},

		diasDelPeriodoActualAUsar() {
			if (!this.esAusenciaVacacional) return 0
			return this.diasSolicitados - this.diasDelAcumuladoAUsar
		},

		acumuladoNoAplicaPorFecha() {
			return this.esAusenciaVacacional
				&& this.diasAcumuladosNum > 0
				&& this.diasDelAcumuladoAUsar === 0
		},

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

		primaDisabled() {
			return this.primaVacacionalUsada || this.diasSolicitados < 2 || this.bloqueaPorDiciembre
		},
	},
	watch: {
		async employees_list(nuevo) {
			if (nuevo?.user) {
				this.$emit('empleado-cambiado', nuevo.user)
				await this.fetchDiasEmpleado(nuevo)
			} else {
				this.diasInfoEmpleado = null
				this.recalcularDias()
			}
		},

		async AusenciaSeleccionada(tipo) {
			this.SolicitarPrima = false
			this.primaVacacionalUsada = false
			if (tipo && Number(tipo.solicitar_prima_vacacional) === 1) {
				await this.checkPrimaVacacional()
			}
		},

		diasSolicitados(nuevo) {
			if (nuevo < 2) {
				this.SolicitarPrima = false
			}
		},

		// Si el padre actualiza estos props
		diasDisponibles() {
			this.recalcularDias()
		},

		diasAcumulados() {
			this.recalcularDias()
		},

		incluyeDiciembre() {
			if (this.SolicitarPrima && this.bloqueaPorDiciembre) {
				this.SolicitarPrima = false
			}
		},
	},

	mounted() {
		this.TotalDias = parseInt(this.diasDisponibles, 10) + this.diasAcumuladosNum
		this.RestanteDias = this.TotalDias - this.diasSolicitados
		this.GetTipoAusencias()
		if (this.AusenciaSeleccionada && Number(this.AusenciaSeleccionada.solicitar_prima_vacacional) === 1) {
			this.checkPrimaVacacional()
		}

		this.recalcularDias()
		this.GetTipoAusencias()
		if (this.AusenciaSeleccionada && Number(this.AusenciaSeleccionada.solicitar_prima_vacacional) === 1) {
			this.checkPrimaVacacional()
		}
	},

	methods: {
		t,

		// FIX: parsea un string "yyyy-mm-dd" como fecha LOCAL, evitando que
		// `new Date("yyyy-mm-dd")` lo interprete como medianoche UTC (lo cual
		// en zonas horarias negativas como Torreón/UTC-6 desplaza la fecha
		// un día hacia atrás).
		parseFechaLocal(fechaStr) {
			if (!fechaStr) return null
			const partes = String(fechaStr).split('-')
			if (partes.length !== 3) return null
			const d = new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]))
			return Number.isNaN(d.getTime()) ? null : d
		},

		// FIX: formatea una fecha a "dd/mm/yyyy" de forma explícita, sin
		// depender del locale del navegador. `toLocaleDateString()` sin
		// argumentos usa el idioma configurado en el navegador del usuario:
		// en es-MX da "dd/mm/yyyy" pero en en-US da "mm/dd/yyyy", lo cual
		// el backend (que siempre espera 'd/m/Y') puede leer mal o invertir
		// silenciosamente día y mes.
		formatFechaParaBackend(fecha) {
			if (!fecha) return ''
			const d = String(fecha.getDate()).padStart(2, '0')
			const m = String(fecha.getMonth() + 1).padStart(2, '0')
			const y = fecha.getFullYear()
			return `${d}/${m}/${y}`
		},

		async fetchDiasEmpleado(empleadoSeleccionado) {
			try {
				const idEmpleados = empleadoSeleccionado.Id_empleados // ya viene en las options
				const res = await axios.post(generateUrl('/apps/empleados/GetAusenciasByUser'), {
					id: idEmpleados,
				})
				this.diasInfoEmpleado = res?.data?.ocs?.data?.[0] || null
				this.recalcularDias()
				if (this.AusenciaSeleccionada?.solicitar_prima_vacacional === 1) {
					await this.checkPrimaVacacional()
				}
			} catch (err) {
				showError(t('empleados', 'Error al obtener los días del empleado seleccionado'))
			}
		},

		recalcularDias() {
			const disponibles = this.diasInfoEmpleado?.dias_disponibles ?? this.diasDisponibles
			const acumulados = this.diasInfoEmpleado?.dias_acumulados ?? this.diasAcumulados
			this.TotalDias = parseInt(disponibles, 10) + (parseFloat(acumulados) || 0)
			this.RestanteDias = this.TotalDias - this.diasSolicitados
			this.GetTipoAusencias()
		},

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
				// FIX: formato explícito d/m/Y en vez de toLocaleDateString() sin
				// argumentos, para no depender del idioma del navegador.
				formData.append('fecha_de', this.formatFechaParaBackend(this.date.start))
				formData.append('fecha_hasta', this.date.end ? this.formatFechaParaBackend(this.date.end) : '')
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
					// FIX: mismo formato explícito d/m/Y para consistencia
					+ `&fecha_de=${encodeURIComponent(this.formatFechaParaBackend(this.date.start))}`
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
.focus-placeholder {
	position: absolute !important;
	width: 1px !important;
	height: 1px !important;
	padding: 0 !important;
	margin: -1px !important;
	overflow: hidden !important;
	clip: rect(0, 0, 0, 0) !important;
	clip-path: inset(50%) !important;
	white-space: nowrap !important;
	border: 0 !important;
}
</style>
