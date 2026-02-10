<template id="content">
	<div
		class="table_component"
		role="region">
		<div class="modal__content">
			<form class="bg-white shadow-md rounded px-8 pt-6 pb-8">
				<div class="table_component" role="region" tabindex="0">
					<div>
						<div class="table_component" role="region" tabindex="0">
							<table v-if="admin">
								<thead>
									<tr>
										<th>
											{{ t('empleados', 'As an administrator, you can request or assign an absence for the selected employee') }}
										</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>
											<NcSelect v-bind="propsEmployees" v-model="employees_list" />
										</td>
									</tr>
								</tbody>
							</table>

							<table>
								<thead>
									<tr>
										<th>
											{{ t('empleados', 'Select the absence type') }}
										</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>
											<NcSelect id="id"
												v-model="AusenciaSeleccionada"
												:no-wrap="true"
												class="hide-label"
												:options="TipoAusencias"
												:keep-open="false"
												:input-label="t('empleados', 'Absence type')" />
										</td>
									</tr>
								</tbody>
							</table>

							<table v-if="AusenciaSeleccionada && AusenciaSeleccionada.descripcion">
								<thead>
									<tr>
										<th>
											{{ t('empleados', 'Details') }}
										</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td v-if="AusenciaSeleccionada && AusenciaSeleccionada.descripcion">
											{{ AusenciaSeleccionada.descripcion }}
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>

					<div v-if="AusenciaSeleccionada && AusenciaSeleccionada.descripcion">
						<div v-if="AusenciaSeleccionada &&
							AusenciaSeleccionada.solicitar_prima_vacacional == 1 &&
							diasSolicitados > TotalDias">
							<NcNoteCard type="info">
								<p>
									{{ t('empleados', 'You cannot request more days than available.') }}
								</p>
							</NcNoteCard>
						</div>
						<div v-else>
							<div v-if="admin">
								<br>
								<NcNoteCard
									type="warning"
									:heading="t('empleados', 'ATTENTION')"
									:text="t('empleados', 'You are registering an absence in admin mode. Notifications and automatic messages will remain active, and the corresponding days will be deducted from the selected employee.')" />
								<br>
							</div>
							<div>
								<table class="top">
									<caption>{{ t('empleados', 'PERIOD DATA') }}</caption>
									<tbody>
										<tr v-if="AusenciaSeleccionada && AusenciaSeleccionada.solicitar_prima_vacacional == 1">
											<td>{{ t('empleados', 'Available days') }}</td>
											<td>
												<span v-if="TotalDias">
													{{ TotalDias }}
												</span>
											</td>
										</tr>
										<tr>
											<td>{{ t('empleados', 'Days to take') }}</td>
											<td>
												<span class="block text-gray-600 text-sm text-left font-bold mb-2">
													{{ diasSolicitados }}
												</span>
											</td>
										</tr>
										<tr v-if="AusenciaSeleccionada && AusenciaSeleccionada.solicitar_prima_vacacional == 1">
											<td>{{ t('empleados', 'Remaining days') }}</td>
											<td>
												<span v-if="RestanteDias">
													{{ RestanteDias }}
												</span>
											</td>
										</tr>
									</tbody>
								</table>

								<table class="top">
									<thead>
										<tr>
											<th>
												{{ t('empleados', 'Absence period') }}
											</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>
												<span v-if="date">
													{{ t('empleados', 'From:') }} {{ date.start.toLocaleDateString() }}
													-
													{{ t('empleados', 'To:') }} {{ date.end ? date.end.toLocaleDateString() : t('empleados', 'Undefined') }}
												</span>
											</td>
										</tr>
									</tbody>
								</table>

								<div v-if="AusenciaSeleccionada && AusenciaSeleccionada.solicitar_archivo">
									<div class="top">
										<NcNoteCard type="info" :text="t('empleados', 'It is necessary to upload a file to justify your absence.')" />
									</div>

									<input ref="fileInput"
										type="file"
										class="file-input"
										multiple
										@change="uploadFile">

									<div
										class="drop-area top"
										@dragover.prevent
										@dragenter.prevent
										@drop.prevent="handleDrop"
										@click="$refs.fileInput.click()">
										{{ t('empleados', 'Drop files here or click to select') }}
									</div>

									<div v-if="selectedFiles.length > 0" class="top">
										<div class="table_component" role="region" tabindex="0">
											<table>
												<caption>{{ t('empleados', 'Selected files:') }}</caption>
												<thead>
													<tr>
														<th>{{ t('empleados', 'File name') }}</th>
														<th>{{ t('empleados', 'Size') }}</th>
													</tr>
												</thead>
												<tbody>
													<tr v-for="(file, index) in selectedFiles" :key="index">
														<td>{{ file.name }}</td>
														<td>
															{{ file.size < 1024 * 1024 ? (file.size / 1024).toFixed(2) + ' KB' : (file.size / (1024 * 1024)).toFixed(2) + ' MB' }}
														</td>
													</tr>
												</tbody>
											</table>
										</div>
									</div>
								</div>

								<div class="top">
									<NcCheckboxRadioSwitch
										v-if="AusenciaSeleccionada &&
											AusenciaSeleccionada.solicitar_prima_vacacional == 1 &&
											prima == 1"
										v-model="SolicitarPrima">
										{{ t('empleados', 'Request vacation bonus') }}
									</NcCheckboxRadioSwitch>
								</div>

								<div class="top">
									<NcTextArea
										v-model="comentarios"
										resize="vertical"
										:label="t('empleados', 'Comments')"
										:placeholder="t('empleados', 'Add a comment to your request (OPTIONAL)')"
										:helper-text="t('empleados', 'Add a comment to your request (OPTIONAL)')" />
								</div>
							</div>
						</div>

						<div v-if="loading">
							<NcLoadingIcon :size="64" />
						</div>

						<div v-else class="top">
							<NcButton
								variant="secondary"
								wide
								:disabled="!canSubmit"
								@click="EnviarAusencia()">
								<template #icon>
									<Airplane :size="20" />
								</template>
								{{ t('empleados', 'Submit') }}
							</NcButton>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
</template>

<script>
import { showError /* showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

// icons
import Airplane from 'vue-material-design-icons/Airplane.vue'

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
		NcLoadingIcon,
	},

	inject: ['employee'],

	props: {
		diasSolicitados: { type: Number, required: true },
		diasDisponibles: { type: [String, Number], required: true },
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
		}
	},

	computed: {
		canSubmit() {
			if (!this.AusenciaSeleccionada || !this.date?.start || !this.date?.end) {
				return false
			}

			if (this.admin && !this.employees_list?.user) {
				return false
			}

			if (this.AusenciaSeleccionada.solicitar_archivo && this.selectedFiles.length === 0) {
				return false
			}

			if (this.AusenciaSeleccionada.solicitar_prima_vacacional === 1 && this.diasSolicitados > this.TotalDias) {
				return false
			}

			return true
		},
	},

	mounted() {
		this.TotalDias = Number(this.diasDisponibles) || 0
		this.RestanteDias = this.TotalDias - this.diasSolicitados
		this.GetTipoAusencias()
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

		formatDateForApi(date) {
			const day = String(date.getDate()).padStart(2, '0')
			const month = String(date.getMonth() + 1).padStart(2, '0')
			const year = date.getFullYear()
			return `${day}/${month}/${year}`
		},

		async EnviarAusencia() {
			if (!this.canSubmit) {
				showError(t('empleados', 'Please complete all required fields before submitting the request.'))
				return
			}

			this.loading = true
			try {
				const formData = new FormData()
				if (this.admin && this.employees_list?.user) {
					formData.append('id_usuario', this.employees_list.user)
				}
				formData.append('id_tipo_ausencia', this.AusenciaSeleccionada.id)
				formData.append('dias_solicitados', this.diasSolicitados)
				formData.append('fecha_de', this.formatDateForApi(this.date.start))
				formData.append('fecha_hasta', this.date.end ? this.formatDateForApi(this.date.end) : '')
				formData.append('prima_vacacional', this.SolicitarPrima ? '1' : '0')
				formData.append('notas', this.comentarios || '')

				for (let i = 0; i < this.selectedFiles.length; i++) {
					formData.append('archivos[]', this.selectedFiles[i])
				}

				const response = await axios.post(
					generateUrl('/apps/empleados/EnviarAusencia'),
					formData,
					{ headers: { 'Content-Type': 'multipart/form-data' } },
				)

				// eslint-disable-next-line no-console
				console.log(response.data)

				this.$bus.emit('close-solicitud')
				this.loading = false
			} catch (err) {
				this.loading = false
				showError(t('empleados', 'Error sending absence request: {error}', { error: String(err) }))
			}
		},
	},
}
</script>

<style>
.table_component {
	overflow: auto;
	width: 100%;
}

.table_component table {
	border: 1px solid #dededf;
	height: 100%;
	width: 100%;
	table-layout: fixed;
	border-collapse: collapse;
	border-spacing: 1px;
	text-align: left;
}

.table_component caption {
	caption-side: top;
	text-align: left;
}

.table_component th {
	border: 1px solid #dededf;
	background-color: #eceff1;
	color: #000000;
	padding: 5px;
}

.table_component td {
	border: 1px solid #dededf;
	background-color: #ffffff;
	color: #000000;
	padding: 5px;
}
.hide-label label { display: none !important; }
.file-input { display: none; }
.drop-area {
	border: 2px dashed #999;
	border-radius: 8px;
	padding: 20px;
	text-align: center;
	color: #666;
	cursor: pointer;
	transition: background-color 0.3s;
}
.drop-area:hover { background-color: #f0f0f0; }
</style>
