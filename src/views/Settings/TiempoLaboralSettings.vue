<!-- eslint-disable vue/require-v-for-key -->
<template>
	<div class="settings-page">
		<div class="settings-header">
			<p class="page-eyebrow">
				{{ t('empleados', 'Working time') }}
			</p>
			<h2>{{ t('empleados', 'Time & Absence Configuration') }}</h2>
			<p class="page-description">
				{{ t('empleados', 'Manage anniversaries, absence types and holidays used across the system.') }}
			</p>
		</div>

		<div class="settings-grid">
			<!-- ── Anniversaries ── -->
			<div class="settings-card">
				<div class="card-header">
					<div class="card-title-wrap">
						<div class="card-icon">
							<CalendarStar :size="20" />
						</div>
						<div>
							<p class="card-eyebrow">
								{{ t('empleados', 'Seniority') }}
							</p>
							<h3>{{ t('empleados', 'Anniversaries') }}</h3>
						</div>
					</div>
					<NcActions>
						<NcActionButton :close-after-click="true" @click="showAddAniversario">
							<template #icon>
								<Plus :size="20" />
							</template>
							{{ t('empleados', 'Add anniversary') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="$refs.file.click()">
							<template #icon>
								<Import :size="20" />
							</template>
							{{ t('empleados', 'Import list') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="Exportar()">
							<template #icon>
								<Export :size="20" />
							</template>
							{{ t('empleados', 'Export / template') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="vaciar()">
							<template #icon>
								<Delete :size="20" />
							</template>
							{{ t('empleados', 'Clear table') }}
						</NcActionButton>
					</NcActions>
				</div>

				<div class="card-table-wrap">
					<table class="data-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Anniversary') }}</th>
								<th>{{ t('empleados', 'Days off') }}</th>
								<th class="col-actions" />
							</tr>
						</thead>
						<tbody>
							<tr v-if="Aniversarios.length === 0">
								<td colspan="3" class="empty-row">
									{{ t('empleados', 'No anniversaries defined yet.') }}
								</td>
							</tr>
							<tr v-for="item in Aniversarios" :key="item.numero_aniversario">
								<td>
									<span class="badge">{{ item.numero_aniversario }}</span>
								</td>
								<td>{{ item.dias }} {{ t('empleados', 'days') }}</td>
								<td class="col-actions">
									<NcActions>
										<NcActionButton :close-after-click="true" @click="editAniversario(item)">
											<template #icon>
												<Pencil :size="20" />
											</template>
											{{ t('empleados', 'Edit') }}
										</NcActionButton>
										<NcActionButton :close-after-click="true" @click="deleteAniversario(item.numero_aniversario)">
											<template #icon>
												<Delete :size="20" />
											</template>
											{{ t('empleados', 'Delete') }}
										</NcActionButton>
									</NcActions>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- ── Absence types ── -->
			<div class="settings-card">
				<div class="card-header">
					<div class="card-title-wrap">
						<div class="card-icon">
							<FileDocumentOutline :size="20" />
						</div>
						<div>
							<p class="card-eyebrow">
								{{ t('empleados', 'Absences') }}
							</p>
							<h3>{{ t('empleados', 'Absence types') }}</h3>
						</div>
					</div>
					<NcActions>
						<NcActionButton :close-after-click="true" @click="showAddTipo">
							<template #icon>
								<Plus :size="20" />
							</template>
							{{ t('empleados', 'Add type') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="$refs.fileTipo.click()">
							<template #icon>
								<Import :size="20" />
							</template>
							{{ t('empleados', 'Import list') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="ExportarTipo()">
							<template #icon>
								<Export :size="20" />
							</template>
							{{ t('empleados', 'Export / template') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="vaciarTipo()">
							<template #icon>
								<Delete :size="20" />
							</template>
							{{ t('empleados', 'Clear table') }}
						</NcActionButton>
					</NcActions>
				</div>

				<div class="card-table-wrap">
					<table class="data-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Name') }}</th>
								<th>{{ t('empleados', 'Description') }}</th>
								<th class="col-center">
									{{ t('empleados', 'File') }}
								</th>
								<th class="col-center">
									{{ t('empleados', 'Billable') }}
								</th>
								<th class="col-center">
									{{ t('empleados', 'Private') }}
								</th>
								<th class="col-actions" />
							</tr>
						</thead>
						<tbody>
							<tr v-if="TipoAusencias.length === 0">
								<td colspan="6" class="empty-row">
									{{ t('empleados', 'No absence types defined yet.') }}
								</td>
							</tr>
							<tr v-for="item in TipoAusencias" :key="item.id">
								<td class="col-name">
									{{ item.nombre }}
								</td>
								<td class="col-desc">
									{{ item.descripcion }}
								</td>
								<td class="col-center">
									<span :class="item.solicitar_archivo == 1 ? 'pill pill--yes' : 'pill pill--no'">
										{{ item.solicitar_archivo == 1 ? t('empleados', 'Yes') : t('empleados', 'No') }}
									</span>
								</td>
								<td class="col-center">
									<span :class="item.cargable == 1 ? 'pill pill--yes' : 'pill pill--no'">
										{{ item.cargable == 1 ? t('empleados', 'Yes') : t('empleados', 'No') }}
									</span>
								</td>
								<td class="col-center">
									<span :class="item.privado > 0 ? 'pill pill--yes' : 'pill pill--no'">
										{{ item.privado > 0 ? t('empleados', 'Yes') : t('empleados', 'No') }}
									</span>
								</td>
								<td class="col-actions">
									<NcActions>
										<NcActionButton :close-after-click="true" @click="editTipo(item)">
											<template #icon>
												<Pencil :size="20" />
											</template>
											{{ t('empleados', 'Edit') }}
										</NcActionButton>
										<NcActionButton :close-after-click="true" @click="deleteTipo(item.id_tipo_ausencia)">
											<template #icon>
												<Delete :size="20" />
											</template>
											{{ t('empleados', 'Delete') }}
										</NcActionButton>
									</NcActions>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- ── Boarding catalog ── -->
			<div class="settings-card">
				<div class="card-header">
					<div class="card-title-wrap">
						<div class="card-icon">
							<AccountArrowRightOutline :size="20" />
						</div>
						<div>
							<p class="card-eyebrow">
								{{ t('empleados', 'Checklist') }}
							</p>
							<h3>{{ t('empleados', 'Boarding') }}</h3>
						</div>
					</div>
					<NcActions>
						<NcActionButton :close-after-click="true" @click="showAddBoardingItem">
							<template #icon>
								<Plus :size="20" />
							</template>
							{{ t('empleados', 'Add item') }}
						</NcActionButton>
					</NcActions>
				</div>

				<div class="card-table-wrap">
					<table class="data-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Name') }}</th>
								<th class="col-center">
									{{ t('empleados', 'Type') }}
								</th>
								<th class="col-actions" />
							</tr>
						</thead>
						<tbody>
							<tr v-if="BoardingCatalogo.length === 0">
								<td colspan="3" class="empty-row">
									{{ t('empleados', 'No boarding items defined yet.') }}
								</td>
							</tr>
							<tr v-for="item in BoardingCatalogo" :key="item.id_boarding">
								<td class="col-name">
									{{ item.nombre }}
								</td>
								<td class="col-center">
									<span :class="Number(item.on) === 1 ? 'pill pill--yes' : 'pill pill--no'">
										{{ Number(item.on) === 1 ? t('empleados', 'OnBoarding') : t('empleados', 'OffBoarding') }}
									</span>
								</td>
								<td class="col-actions">
									<NcActions>
										<NcActionButton :close-after-click="true" @click="editBoardingItem(item)">
											<template #icon>
												<Pencil :size="20" />
											</template>
											{{ t('empleados', 'Edit') }}
										</NcActionButton>
										<NcActionButton :close-after-click="true" @click="deleteBoardingItem(item.id_boarding)">
											<template #icon>
												<Delete :size="20" />
											</template>
											{{ t('empleados', 'Delete') }}
										</NcActionButton>
									</NcActions>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- ── Holidays ── -->
			<div class="settings-card">
				<div class="card-header">
					<div class="card-title-wrap">
						<div class="card-icon">
							<CalendarMultiple :size="20" />
						</div>
						<div>
							<p class="card-eyebrow">
								{{ t('empleados', 'Calendar') }}
							</p>
							<h3>{{ t('empleados', 'Holidays') }}</h3>
						</div>
					</div>
					<NcActions>
						<NcActionButton :close-after-click="true" @click="showAddFestivo">
							<template #icon>
								<Plus :size="20" />
							</template>
							{{ t('empleados', 'Add holiday') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="$refs.fileFestivo.click()">
							<template #icon>
								<Import :size="20" />
							</template>
							{{ t('empleados', 'Import list') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="exportarFestivos()">
							<template #icon>
								<Export :size="20" />
							</template>
							{{ t('empleados', 'Export / template') }}
						</NcActionButton>
						<NcActionButton :close-after-click="true" @click="vaciarFestivos()">
							<template #icon>
								<Delete :size="20" />
							</template>
							{{ t('empleados', 'Clear table') }}
						</NcActionButton>
					</NcActions>
				</div>

				<div class="card-table-wrap">
					<table class="data-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Name') }}</th>
								<th>{{ t('empleados', 'Date') }}</th>
								<th class="col-center">
									{{ t('empleados', 'Official') }}
								</th>
								<th class="col-actions" />
							</tr>
						</thead>
						<tbody>
							<tr v-if="Festivos.length === 0">
								<td colspan="4" class="empty-row">
									{{ t('empleados', 'No holidays defined yet.') }}
								</td>
							</tr>
							<tr v-for="item in Festivos" :key="item.id_festivo">
								<td class="col-name">
									{{ item.nombre }}
								</td>
								<td>
									<span class="date-chip">{{ item.fecha }}</span>
								</td>
								<td class="col-center">
									<span :class="item.oficial == 1 ? 'pill pill--yes' : 'pill pill--no'">
										{{ item.oficial == 1 ? t('empleados', 'Yes') : t('empleados', 'No') }}
									</span>
								</td>
								<td class="col-actions">
									<NcActions>
										<NcActionButton :close-after-click="true" @click="editFestivo(item)">
											<template #icon>
												<Pencil :size="20" />
											</template>
											{{ t('empleados', 'Edit') }}
										</NcActionButton>
										<NcActionButton v-if="item.oficial != 1" :close-after-click="true" @click="deleteFestivo(item.id_festivo)">
											<template #icon>
												<Delete :size="20" />
											</template>
											{{ t('empleados', 'Delete') }}
										</NcActionButton>
									</NcActions>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- hidden file inputs -->
		<input ref="file"
			type="file"
			style="display:none"
			accept=".xlsx"
			@change="importar()">
		<input ref="fileTipo"
			type="file"
			style="display:none"
			accept=".xlsx"
			@change="importarTipo()">
		<input ref="fileFestivo"
			type="file"
			style="display:none"
			accept=".xlsx"
			@change="importarFestivos()">

		<!-- ── Modal: Add/Edit anniversary ── -->
		<NcModal
			v-if="modalAddAniversario"
			ref="modalRef"
			:name="editingAniversario ? t('empleados', 'Edit anniversary') : t('empleados', 'Add anniversary')"
			@close="closeModalAniversario">
			<div class="modal-body">
				<div class="modal-header-section">
					<p class="card-eyebrow">
						{{ t('empleados', 'Seniority') }}
					</p>
					<h2>{{ editingAniversario ? t('empleados', 'Edit anniversary') : t('empleados', 'New anniversary rule') }}</h2>
					<p>{{ t('empleados', 'Define how many days off are granted at each anniversary year.') }}</p>
				</div>
				<div class="form-grid">
					<NcTextField :label="t('empleados', 'Anniversary number')" :value.sync="NumeroAniversario" />
					<NcTextField :label="t('empleados', 'Days off')" :value.sync="DiasAniversario" />
				</div>
				<div class="modal-actions">
					<NcButton @click="closeModalAniversario">
						{{ t('empleados', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" :disabled="!NumeroAniversario || !DiasAniversario" @click="guardarAniversario">
						{{ t('empleados', 'Save') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- ── Modal: Add/Edit absence type ── -->
		<NcModal
			v-if="modalAddTipo"
			ref="modalRef"
			:name="editingTipo ? t('empleados', 'Edit absence type') : t('empleados', 'Add absence type')"
			@close="closeModalTipo">
			<div class="modal-body">
				<div class="modal-header-section">
					<p class="card-eyebrow">
						{{ t('empleados', 'Absences') }}
					</p>
					<h2>{{ editingTipo ? t('empleados', 'Edit absence type') : t('empleados', 'New absence type') }}</h2>
					<p>{{ t('empleados', 'Define a new category of absence employees can request.') }}</p>
				</div>
				<div class="form-grid span-2">
					<NcTextField class="span-2" :label="t('empleados', 'Name')" :value.sync="NombreTipo" />
					<NcTextField class="span-2" :label="t('empleados', 'Description')" :value.sync="DescripcionTipo" />
					<div class="switch-card span-2">
						<NcCheckboxRadioSwitch v-model="SolicitarArchivoTipo" type="switch" />
						<div>
							<p class="switch-label">
								{{ t('empleados', 'Request file') }}
							</p>
							<p class="switch-desc">
								{{ t('empleados', 'Employee must attach a document when requesting this absence.') }}
							</p>
						</div>
					</div>
					<div class="switch-card span-2">
						<NcCheckboxRadioSwitch v-model="solicitar_prima_vacacional" type="switch" />
						<div>
							<p class="switch-label">
								{{ t('empleados', 'Vacation bonus') }}
							</p>
							<p class="switch-desc">
								{{ t('empleados', 'This absence type triggers vacation bonus calculation.') }}
							</p>
						</div>
					</div>
					<div class="switch-card span-2">
						<NcCheckboxRadioSwitch v-model="cargable" type="switch" />
						<div>
							<p class="switch-label">
								{{ t('empleados', 'Billable') }}
							</p>
							<p class="switch-desc">
								{{ t('empleados', 'This absence type is deducted from the employee\'s available days.') }}
							</p>
						</div>
					</div>
					<div class="switch-card span-2">
						<NcCheckboxRadioSwitch v-model="privado" type="switch" />
						<div>
							<p class="switch-label">
								{{ t('empleados', 'Private') }}
							</p>
							<p class="switch-desc">
								{{ t('empleados', 'Only admins and HR can see and request this absence type.') }}
							</p>
						</div>
					</div>
				</div>
				<div class="modal-actions">
					<NcButton @click="closeModalTipo">
						{{ t('empleados', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" :disabled="!NombreTipo || !DescripcionTipo" @click="guardarTipo">
						{{ t('empleados', 'Save') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- ── Modal: Add/Edit holiday ── -->
		<NcModal
			v-if="modalFestivo"
			:name="editingFestivo ? t('empleados', 'Edit holiday') : t('empleados', 'Add holiday')"
			@close="closeModalFestivo">
			<div class="modal-body">
				<div class="modal-header-section">
					<p class="card-eyebrow">
						{{ t('empleados', 'Calendar') }}
					</p>
					<h2>{{ editingFestivo ? t('empleados', 'Edit holiday') : t('empleados', 'New holiday') }}</h2>
					<p>{{ t('empleados', 'Public holidays are excluded from working day calculations.') }}</p>
					<p v-if="editingFestivo && editingFestivo.oficial == 1" class="official-warning">
						{{ t('empleados', 'This is an official holiday. If its date depends on a weekday rule (e.g. "third Monday of March"), your edit may be overwritten automatically next January 1st.') }}
					</p>
				</div>
				<div class="form-grid">
					<NcTextField class="span-2" :label="t('empleados', 'Holiday name')" :value.sync="festivoNombre" />
					<div class="span-2">
						<NcTextField
							class="span-2"
							type="date"
							:label="t('empleados', 'Date (day and month only)')"
							:value.sync="festivoFecha" />
						<p class="field-hint">
							{{ t('empleados', 'The year is ignored — the holiday repeats every year.') }}
						</p>
					</div>
				</div>
				<div class="modal-actions">
					<NcButton @click="closeModalFestivo">
						{{ t('empleados', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" :disabled="!festivoNombre || !festivoFecha" @click="guardarFestivo">
						{{ t('empleados', 'Save') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- ── Modal: Add/Edit boarding item ── -->
		<NcModal
			v-if="modalAddBoarding"
			:name="editingBoardingItem ? t('empleados', 'Edit boarding item') : t('empleados', 'Add boarding item')"
			@close="closeModalBoarding">
			<div class="modal-body">
				<div class="modal-header-section">
					<p class="card-eyebrow">
						{{ t('empleados', 'Checklist') }}
					</p>
					<h2>{{ editingBoardingItem ? t('empleados', 'Edit boarding item') : t('empleados', 'New boarding item') }}</h2>
					<p>{{ t('empleados', 'Items appear in the employee\'s OnBoarding or OffBoarding checklist.') }}</p>
				</div>
				<div class="form-grid">
					<NcTextField class="span-2" :label="t('empleados', 'Item name')" :value.sync="nombreBoarding" />
					<div class="switch-card span-2">
						<NcCheckboxRadioSwitch v-model="onBoardingItem" type="switch" />
						<div>
							<p class="switch-label">
								{{ onBoardingItem ? t('empleados', 'OnBoarding') : t('empleados', 'OffBoarding') }}
							</p>
							<p class="switch-desc">
								{{ t('empleados', 'Whether this item belongs to the onboarding or offboarding checklist.') }}
							</p>
						</div>
					</div>
				</div>
				<div class="modal-actions">
					<NcButton @click="closeModalBoarding">
						{{ t('empleados', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" :disabled="!nombreBoarding" @click="guardarBoardingItem">
						{{ t('empleados', 'Save') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import Delete from 'vue-material-design-icons/Delete.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Import from 'vue-material-design-icons/Import.vue'
import Export from 'vue-material-design-icons/Export.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import CalendarStar from 'vue-material-design-icons/CalendarStar.vue'
import CalendarMultiple from 'vue-material-design-icons/CalendarMultiple.vue'
import FileDocumentOutline from 'vue-material-design-icons/FileDocumentOutline.vue'
import AccountArrowRightOutline from 'vue-material-design-icons/AccountArrowRightOutline.vue'

import {
	NcActions,
	NcActionButton,
	NcModal,
	NcTextField,
	NcButton,
	NcCheckboxRadioSwitch,
} from '@nextcloud/vue'
import { ref } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TiempoLaboralSettings',
	components: {
		NcActions,
		NcActionButton,
		NcModal,
		NcTextField,
		NcButton,
		NcCheckboxRadioSwitch,
		Plus,
		Import,
		Export,
		Delete,
		Pencil,
		CalendarStar,
		CalendarMultiple,
		FileDocumentOutline,
		AccountArrowRightOutline,
	},

	data() {
		return {
			// ── Anniversaries ──
			modalAddAniversario: false,
			editingAniversario: null,
			modalRef: ref(null),
			Aniversarios: [],
			NumeroAniversario: null,
			DiasAniversario: null,

			// ── Absence types ──
			modalAddTipo: false,
			editingTipo: null,
			TipoAusencias: [],
			NombreTipo: null,
			DescripcionTipo: null,
			SolicitarArchivoTipo: false,
			solicitar_prima_vacacional: false,
			cargable: false,
			privado: false,

			// ── Holidays ──
			Festivos: [],
			modalFestivo: false,
			editingFestivo: null,
			festivoNombre: '',
			festivoFecha: '',

			// ── Boarding catalog ──
			BoardingCatalogo: [],
			modalAddBoarding: false,
			editingBoardingItem: null,
			nombreBoarding: '',
			onBoardingItem: true,
		}
	},

	mounted() {
		this.getAniversarios()
		this.getTipo()
		this.getFestivos()
		this.getBoardingCatalogo()
	},

	methods: {
		t,

		// ────────────────────────────────────────────
		// Anniversaries
		// ────────────────────────────────────────────
		showAddAniversario() {
			this.editingAniversario = null
			this.NumeroAniversario = null
			this.DiasAniversario = null
			this.modalAddAniversario = true
		},

		editAniversario(item) {
			this.editingAniversario = item
			this.NumeroAniversario = item.numero_aniversario
			this.DiasAniversario = item.dias
			this.modalAddAniversario = true
		},

		closeModalAniversario() {
			this.modalAddAniversario = false
			this.editingAniversario = null
			this.NumeroAniversario = null
			this.DiasAniversario = null
		},

		async getAniversarios() {
			try {
				await axios.get(generateUrl('/apps/empleados/Getaniversarios'))
					.then(
						(response) => { this.Aniversarios = response?.data?.ocs?.data },
						(err) => { showError(err) },
					)
			} catch (err) {
				showError(t('empleados', 'An exception occurred [01] [{error}]', { error: String(err) }))
			}
		},

		async guardarAniversario() {
			try {
				if (this.editingAniversario) {
					await axios.post(generateUrl('/apps/empleados/modificarAniversario'), {
						numero_aniversario: this.editingAniversario.numero_aniversario,
						nuevo_numero_aniversario: this.NumeroAniversario,
						dias: parseFloat(this.DiasAniversario),
					})
				} else {
					await axios.post(generateUrl('/apps/empleados/AgregarNuevoAniversario'), {
						numero_aniversario: this.NumeroAniversario,
						dias: this.DiasAniversario,
					})
				}
				showSuccess(t('empleados', 'Anniversary saved'))
				this.closeModalAniversario()
				this.getAniversarios()
			} catch (err) {
				showError(t('empleados', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async deleteAniversario(numeroAniversario) {
			try {
				await axios.post(generateUrl('/apps/empleados/deleteAniversario'), {
					numero_aniversario: numeroAniversario,
				})
				showSuccess(t('empleados', 'Anniversary deleted'))
				this.getAniversarios()
			} catch (err) {
				showError(t('empleados', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		async vaciar() {
			try {
				await axios.get(generateUrl('/apps/empleados/VaciarAniversarios'))
					.then(
						() => {
							this.getAniversarios()
							showSuccess(t('empleados', 'Anniversaries table cleared'))
						},
						(err) => { showError(err) },
					)
			} catch (err) {
				showError(t('empleados', 'An exception occurred [01] [{error}]', { error: String(err) }))
			}
		},

		Exportar() {
			axios.get(generateUrl('/apps/empleados/ExportListAniversarios'), { responseType: 'blob' }).then(
				(response) => {
					const url = URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.ms-excel' }))
					const link = document.createElement('a')
					link.href = url
					link.setAttribute('download', 'aniversarios.xlsx')
					document.body.appendChild(link)
					link.click()
				},
				(err) => {
					showError(t('empleados', 'An error occurred {error}', { error: String(err) }))
				},
			)
		},

		async importar() {
			const formData = new FormData()
			formData.append('fileXLSX', this.$refs.file.files[0])
			try {
				await axios.post(generateUrl('/apps/empleados/ImportListAniversarios'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				})
				this.getAniversarios()
				showSuccess(t('empleados', 'Database updated successfully'))
			} catch (err) {
				showError(t('empleados', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		// ────────────────────────────────────────────
		// Absence types
		// ────────────────────────────────────────────
		showAddTipo() {
			this.editingTipo = null
			this.NombreTipo = null
			this.DescripcionTipo = null
			this.SolicitarArchivoTipo = false
			this.solicitar_prima_vacacional = false
			this.cargable = false
			this.privado = false
			this.modalAddTipo = true
		},

		editTipo(item) {
			this.editingTipo = item
			this.NombreTipo = item.nombre
			this.DescripcionTipo = item.descripcion
			this.SolicitarArchivoTipo = item.solicitar_archivo === 1
			this.solicitar_prima_vacacional = item.solicitar_prima_vacacional === 1
			this.cargable = item.cargable === 1
			this.privado = item.privado > 0
			this.modalAddTipo = true
		},

		closeModalTipo() {
			this.modalAddTipo = false
			this.editingTipo = null
			this.NombreTipo = null
			this.DescripcionTipo = null
			this.SolicitarArchivoTipo = false
			this.solicitar_prima_vacacional = false
			this.cargable = false
			this.privado = false
		},

		async getTipo() {
			try {
				await axios.get(generateUrl('/apps/empleados/getTipo'))
					.then(
						(response) => { this.TipoAusencias = response.data },
						(err) => { showError(err) },
					)
			} catch (err) {
				showError(t('empleados', 'An exception occurred [01] [{error}]', { error: String(err) }))
			}
		},

		async guardarTipo() {
			try {
				if (this.editingTipo) {
					await axios.post(generateUrl('/apps/empleados/modificarTipo'), {
						id: this.editingTipo.id_tipo_ausencia,
						nombre: this.NombreTipo,
						descripcion: this.DescripcionTipo,
						solicitar_archivo: this.SolicitarArchivoTipo ? 1 : 0,
						solicitar_prima_vacacional: this.solicitar_prima_vacacional ? 1 : 0,
						cargable: this.cargable ? 1 : 0,
						privado: this.privado ? 1 : 0,
					})
				} else {
					await axios.post(generateUrl('/apps/empleados/AgregarNuevoTipo'), {
						nombre: this.NombreTipo,
						descripcion: this.DescripcionTipo,
						solicitar_archivo: this.SolicitarArchivoTipo ? 1 : 0,
						solicitar_prima_vacacional: this.solicitar_prima_vacacional ? 1 : 0,
						cargable: this.cargable ? 1 : 0,
						privado: this.privado ? 1 : 0,
					})
				}
				showSuccess(t('empleados', 'Absence type saved'))
				this.closeModalTipo()
				this.getTipo()
			} catch (err) {
				showError(t('empleados', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async deleteTipo(id) {
			try {
				await axios.post(generateUrl('/apps/empleados/deleteTipo'), { id })
				showSuccess(t('empleados', 'Absence type deleted'))
				this.getTipo()
			} catch (err) {
				showError(t('empleados', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		async vaciarTipo() {
			try {
				await axios.get(generateUrl('/apps/empleados/VaciarTipo'))
					.then(
						() => {
							this.getTipo()
							showSuccess(t('empleados', 'Absence types table cleared'))
						},
						(err) => { showError(err) },
					)
			} catch (err) {
				showError(t('empleados', 'An exception occurred [01] [{error}]', { error: String(err) }))
			}
		},

		ExportarTipo() {
			axios.get(generateUrl('/apps/empleados/ExportarTipo'), { responseType: 'blob' }).then(
				(response) => {
					const url = URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.ms-excel' }))
					const link = document.createElement('a')
					link.href = url
					link.setAttribute('download', 'tipos_ausencias.xlsx')
					document.body.appendChild(link)
					link.click()
				},
				(err) => {
					showError(t('empleados', 'An error occurred {error}', { error: String(err) }))
				},
			)
		},

		async importarTipo() {
			const formData = new FormData()
			formData.append('fileXLSX', this.$refs.fileTipo.files[0])
			try {
				await axios.post(generateUrl('/apps/empleados/importarTipo'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				})
				this.getTipo()
				showSuccess(t('empleados', 'Database updated successfully'))
			} catch (err) {
				showError(t('empleados', 'An exception occurred [03] [{error}]', { error: String(err) }))
			}
		},

		// ────────────────────────────────────────────
		// Holidays
		// ────────────────────────────────────────────
		async getFestivos() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/getFestivos'))
				this.Festivos = response?.data?.ocs?.data ?? response?.data ?? []
			} catch (err) {
				showError(t('empleados', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		showAddFestivo() {
			this.editingFestivo = null
			this.festivoNombre = ''
			this.festivoFecha = ''
			this.modalFestivo = true
		},

		editFestivo(item) {
			this.editingFestivo = item
			this.festivoNombre = item.nombre
			this.festivoFecha = '2000-' + item.fecha
			this.modalFestivo = true
		},

		closeModalFestivo() {
			this.modalFestivo = false
			this.editingFestivo = null
		},

		async guardarFestivo() {
			try {
				if (this.editingFestivo) {
					await axios.post(generateUrl('/apps/empleados/modificarFestivo'), {
						id_festivo: this.editingFestivo.id_festivo,
						nombre: this.festivoNombre,
						fecha: this.festivoFecha,
					})
				} else {
					await axios.post(generateUrl('/apps/empleados/crearFestivo'), {
						nombre: this.festivoNombre,
						fecha: this.festivoFecha,
					})
				}
				showSuccess(t('empleados', 'Holiday saved'))
				this.closeModalFestivo()
				this.getFestivos()
			} catch (err) {
				showError(t('empleados', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		async deleteFestivo(id) {
			try {
				await axios.post(generateUrl('/apps/empleados/deleteFestivo'), { id_festivo: id })
				showSuccess(t('empleados', 'Holiday deleted'))
				this.getFestivos()
			} catch (err) {
				showError(t('empleados', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		async importarFestivos() {
			const formData = new FormData()
			formData.append('festivosfileXLSX', this.$refs.fileFestivo.files[0])
			try {
				await axios.post(generateUrl('/apps/empleados/importarFestivos'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				})
				showSuccess(t('empleados', 'Database updated successfully'))
				this.getFestivos()
			} catch (err) {
				showError(t('empleados', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		exportarFestivos() {
			axios.get(generateUrl('/apps/empleados/exportarFestivos'), { responseType: 'blob' }).then(
				(response) => {
					const url = URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.ms-excel' }))
					const link = document.createElement('a')
					link.href = url
					link.setAttribute('download', 'festivos.xlsx')
					document.body.appendChild(link)
					link.click()
				},
				(err) => { showError(String(err)) },
			)
		},

		async vaciarFestivos() {
			try {
				await axios.get(generateUrl('/apps/empleados/vaciarFestivos'))
				showSuccess(t('empleados', 'Holidays table cleared'))
				this.getFestivos()
			} catch (err) {
				showError(t('empleados', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		// ────────────────────────────────────────────
		// Boarding catalog
		// ────────────────────────────────────────────
		async getBoardingCatalogo() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/getBoarding'))
				this.BoardingCatalogo = response?.data?.ocs?.data ?? response?.data ?? []
			} catch (err) {
				showError(t('empleados', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		showAddBoardingItem() {
			this.editingBoardingItem = null
			this.nombreBoarding = ''
			this.onBoardingItem = true
			this.modalAddBoarding = true
		},

		editBoardingItem(item) {
			this.editingBoardingItem = item
			this.nombreBoarding = item.nombre
			this.onBoardingItem = Number(item.on) === 1
			this.modalAddBoarding = true
		},

		closeModalBoarding() {
			this.modalAddBoarding = false
			this.editingBoardingItem = null
			this.nombreBoarding = ''
			this.onBoardingItem = true
		},

		async guardarBoardingItem() {
			try {
				if (this.editingBoardingItem) {
					await axios.post(generateUrl('/apps/empleados/modificarBoarding'), {
						id_boarding: this.editingBoardingItem.id_boarding,
						nombre: this.nombreBoarding,
						on: this.onBoardingItem ? 1 : 0,
					})
				} else {
					await axios.post(generateUrl('/apps/empleados/crearBoarding'), {
						nombre: this.nombreBoarding,
						on: this.onBoardingItem ? 1 : 0,
					})
				}
				showSuccess(t('empleados', 'Boarding item saved'))
				this.closeModalBoarding()
				this.getBoardingCatalogo()
			} catch (err) {
				showError(t('empleados', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},

		async deleteBoardingItem(id) {
			try {
				await axios.post(generateUrl('/apps/empleados/deleteBoarding'), { id_boarding: id })
				showSuccess(t('empleados', 'Boarding item deleted'))
				this.getBoardingCatalogo()
			} catch (err) {
				showError(t('empleados', 'An exception occurred [{error}]', { error: String(err) }))
			}
		},
	},
}
</script>

<style scoped lang="scss">
/* ── Page layout ── */
.settings-page {
	display: flex;
	flex-direction: column;
	gap: 24px;
	padding: 24px;
	max-width: 1200px;
}

.settings-header {
	display: flex;
	flex-direction: column;
	gap: 4px;

	h2 {
		margin: 4px 0 6px;
		font-size: 1.5rem;
		font-weight: 700;
		color: var(--color-main-text);
	}
}

.page-eyebrow {
	font-size: 0.72rem;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	color: var(--color-primary-element);
	margin: 0;
}

.page-description {
	font-size: 0.875rem;
	color: var(--color-text-maxcontrast);
	margin: 0;
}

/* ── Cards grid ── */
.settings-grid {
	display: grid;
	grid-template-columns: 0.85fr 1.15fr;
	grid-auto-rows: 1fr;
	gap: 16px;
	align-items: stretch;

	@media (max-width: 1024px) {
		grid-template-columns: 1fr;
	}
}

/* ── Card ── */
.settings-card {
	display: flex;
	flex-direction: column;
	height: 100%;
	border-radius: var(--border-radius-large);
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	overflow: hidden;
}

.card-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 16px 12px 16px 16px;
	border-bottom: 1px solid var(--color-border);
	background: var(--color-background-soft);
}

.card-title-wrap {
	display: flex;
	align-items: center;
	gap: 12px;

	h3 {
		margin: 0;
		font-size: 0.95rem;
		font-weight: 600;
		color: var(--color-main-text);
	}
}

.card-eyebrow {
	font-size: 0.65rem;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	color: var(--color-primary-element);
	margin: 0 0 1px;
}

.card-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 36px;
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	flex-shrink: 0;
}

/* ── Table ── */
.card-table-wrap {
	overflow-x: auto;
	max-height: calc(60vh - 4rem);
	overflow-y: auto;
}

.data-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.85rem;

	thead tr {
		background: var(--color-background-soft);
		border-bottom: 1px solid var(--color-border);
	}

	th {
		padding: 10px 14px;
		font-size: 0.7rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: var(--color-text-maxcontrast);
		text-align: left;
		white-space: nowrap;
	}

	tbody tr {
		border-bottom: 1px solid var(--color-border);
		transition: background 0.1s ease;

		&:last-child {
			border-bottom: none;
		}

		&:hover {
			background: var(--color-background-hover);
		}
	}

	td {
		padding: 10px 14px;
		color: var(--color-main-text);
		vertical-align: middle;
	}
}

.col-center {
	text-align: center !important;
}

.col-name {
	font-weight: 600;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	max-width: 140px;
}

.col-desc {
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	max-width: 140px;
}

.col-actions {
	width: 44px;
	text-align: center;
}

.empty-row {
	text-align: center;
	color: var(--color-text-maxcontrast);
	font-style: italic;
	padding: 24px 14px !important;
}

/* ── Badges / chips ── */
.badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 28px;
	height: 24px;
	padding: 0 8px;
	border-radius: 999px;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	font-size: 0.75rem;
	font-weight: 700;
}

.pill {
	display: inline-flex;
	align-items: center;
	padding: 2px 10px;
	border-radius: 999px;
	font-size: 0.7rem;
	font-weight: 600;

	&--yes {
		background: #dcfce7;
		color: #166534;
	}

	&--no {
		background: var(--color-background-soft);
		color: var(--color-text-maxcontrast);
		border: 1px solid var(--color-border);
	}
}

.date-chip {
	display: inline-flex;
	align-items: center;
	padding: 2px 8px;
	border-radius: 6px;
	background: var(--color-background-soft);
	border: 1px solid var(--color-border);
	font-size: 0.8rem;
	font-family: monospace;
	color: var(--color-main-text);
}

.official-warning {
	margin: 6px 0 0 !important;
	padding: 8px 12px;
	border-radius: var(--border-radius);
	background: #fef9c3;
	color: #713f12;
	font-size: 0.8rem !important;
}

/* ── Modals ── */
.modal-body {
	display: flex;
	flex-direction: column;
	gap: 20px;
	padding: 24px;
}

.modal-header-section {
	display: flex;
	flex-direction: column;
	gap: 4px;

	h2 {
		margin: 4px 0 2px;
		font-size: 1.15rem;
		font-weight: 700;
		color: var(--color-main-text);
	}

	p {
		margin: 0;
		font-size: 0.875rem;
		color: var(--color-text-maxcontrast);
	}
}

.form-grid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 12px;

	.span-2 {
		grid-column: span 2;
	}
}

.switch-card {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	padding: 12px 14px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-soft);
	border: 1px solid var(--color-border);
}

.switch-label {
	margin: 0 0 2px;
	font-size: 0.875rem;
	font-weight: 600;
	color: var(--color-main-text);
}

.switch-desc {
	margin: 0;
	font-size: 0.75rem;
	color: var(--color-text-maxcontrast);
}

.modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}
</style>
