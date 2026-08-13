<!-- eslint-disable vue/require-v-for-key -->
<template>
	<div v-if="loading">
		<!-- Loading section -->
		<div class="center-screen">
			<NcLoadingIcon :size="64" appearance="dark" name="Loading on light background" />
		</div>
	</div>

	<div v-else id="admin">
		<!-- Main title -->
		<div>
			<h2 class="board-title">
				<AccountGroup :size="20" decorative class="icon" />
				<span>{{ t('empleados', 'Global settings') }}</span>
			</h2>
		</div>

		<div class="settings-layout">
			<section class="settings-category settings-category-wide">
				<div class="category-header">
					<p class="section-label">
						{{ t('empleados', 'General') }}
					</p>
					<h3>{{ t('empleados', 'Base behavior') }}</h3>
					<p>{{ t('empleados', 'Settings that affect core employee workflows and shared files.') }}</p>
				</div>

				<div class="settings-grid">
					<div class="settings-card">
						<div class="setting-row">
							<div>
								<strong>{{ t('empleados', 'Automatic note saving') }}</strong>
								<span>{{ t('empleados', 'Save employee notes without requiring a manual action.') }}</span>
							</div>

							<NcCheckboxRadioSwitch
								:checked="guardado_notas"
								type="switch"
								@update:checked="onChangeGuardadoNotas">
								{{ guardado_notas ? t('empleados', 'Enabled') : t('empleados', 'Disabled') }}
							</NcCheckboxRadioSwitch>
						</div>
					</div>

					<div class="settings-card">
						<div class="setting-row">
							<div>
								<strong>{{ t('empleados', 'Vacation accrual') }}</strong>
								<span>{{ t('empleados', 'Allow all users to accrue vacation automatically.') }}</span>
							</div>

							<NcCheckboxRadioSwitch
								:checked="acumular_vacaciones"
								type="switch"
								@update:checked="onChangeacumular_vacaciones">
								{{ acumular_vacaciones ? t('empleados', 'Enabled') : t('empleados', 'Disabled') }}
							</NcCheckboxRadioSwitch>
						</div>
					</div>
				</div>
			</section>

			<section class="settings-category settings-category-wide">
				<div class="category-header">
					<p class="section-label">
						{{ t('empleados', 'Modules') }}
					</p>
					<h3>{{ t('empleados', 'Available app areas') }}</h3>
					<p>{{ t('empleados', 'Enable or hide functional areas from the employee navigation.') }}</p>
				</div>

				<div class="modules-grid">
					<div class="settings-card">
						<div class="module-card-header">
							<strong>{{ t('empleados','Purchases module') }}</strong>
							<span>{{ t('empleados', 'Purchase requests, approvals, suppliers and tracking.') }}</span>
						</div>

						<NcCheckboxRadioSwitch
							:checked="modulo_compras"
							type="switch"
							@update:checked="onChangemodulo_compras">
							{{ t('empleados', 'Enable purchases module') }}
						</NcCheckboxRadioSwitch>
					</div>

					<div class="settings-card">
						<div class="module-card-header">
							<strong>{{ t('empleados','Savings module') }}</strong>
							<span>{{ t('empleados', 'Savings menu and related user status.') }}</span>
						</div>

						<NcCheckboxRadioSwitch
							:checked="modulo_ahorro"
							type="switch"
							@update:checked="onChangemodulo_ahorro">
							{{ t('empleados', 'Enable savings module') }}
						</NcCheckboxRadioSwitch>
					</div>

					<div class="settings-card">
						<div class="module-card-header">
							<strong>{{ t('empleados','Absences module') }}</strong>
							<span>{{ t('empleados', 'Absence requests and availability controls.') }}</span>
						</div>

						<NcCheckboxRadioSwitch
							:checked="modulo_ausencias"
							type="switch"
							@update:checked="onChangemodulo_ausencias">
							{{ t('empleados', 'Enable absences module') }}
						</NcCheckboxRadioSwitch>

						<NcCheckboxRadioSwitch
							:checked="modulo_ausencias_readonly"
							type="switch"
							@update:checked="onChangemodulo_ausencias_readonly">
							{{ t('empleados', 'Read-only mode') }}
						</NcCheckboxRadioSwitch>
					</div>

					<div class="settings-card">
						<div class="module-card-header">
							<strong>{{ t('empleados','Customers module') }}</strong>
							<span>{{ t('empleados', 'Customer groups and companies for time reports.') }}</span>
						</div>

						<NcCheckboxRadioSwitch
							:checked="modulo_clientes"
							type="switch"
							@update:checked="onChangemodulo_clientes">
							{{ t('empleados', 'Enable customers module') }}
						</NcCheckboxRadioSwitch>
					</div>

					<div class="settings-card">
						<div class="module-card-header">
							<strong>{{ t('empleados','Report times module') }}</strong>
							<span>{{ t('empleados', 'Time reporting and compliance views.') }}</span>
						</div>

						<NcCheckboxRadioSwitch
							:checked="modulo_reporte_tiempos"
							type="switch"
							@update:checked="onChangemodulo_reporte_tiempos">
							{{ t('empleados', 'Enable report times module') }}
						</NcCheckboxRadioSwitch>
					</div>

					<div class="settings-card">
						<div class="module-card-header">
							<strong>{{ t('empleados','IT Inventory module') }}</strong>
							<span>{{ t('empleados', 'Computer equipment, hardware models and assignments.') }}</span>
						</div>

						<NcCheckboxRadioSwitch
							:checked="modulo_inventario"
							type="switch"
							@update:checked="onChangemodulo_inventario">
							{{ t('empleados', 'Enable IT inventory module') }}
						</NcCheckboxRadioSwitch>
					</div>

					<div class="settings-card">
						<div class="module-card-header">
							<strong>{{ t('empleados','IT Support module') }}</strong>
							<span>{{ t('empleados', 'Technical support and device maintenance history.') }}</span>
						</div>

						<NcCheckboxRadioSwitch
							:checked="modulo_soporte"
							type="switch"
							@update:checked="onChangemodulo_soporte">
							{{ t('empleados', 'Enable IT support module') }}
						</NcCheckboxRadioSwitch>
					</div>
				</div>
			</section>

			<section class="settings-category settings-category-wide">
				<div class="category-header">
					<p class="section-label">
						{{ t('empleados', 'Purchases') }}
					</p>
					<h3>{{ t('empleados', 'Purchase document logo') }}</h3>
					<p>{{ t('empleados', 'Configure the logo used in generated purchase request PDFs.') }}</p>
				</div>

				<div class="settings-card settings-form-card">
					<div class="logo-settings-layout">
						<div class="logo-preview">
							<img v-if="logoDocumentoUrl"
								:src="logoDocumentoUrl"
								alt=""
								@error="logoDocumentoUrl = ''">

							<span v-else>
								{{ t('empleados', 'No logo configured') }}
							</span>
						</div>

						<div class="logo-settings-content">
							<strong>{{ t('empleados', 'Document logo') }}</strong>
							<span>
								{{ t('empleados', 'Use a PNG or JPG image. This logo will appear in generated purchase request PDFs.') }}
							</span>

							<input ref="logoDocumentoInput"
								type="file"
								accept="image/png,image/jpeg"
								style="display: none"
								@change="onLogoDocumentoSelected">

							<div class="actions-row logo-actions">
								<NcButton :disabled="loadingLogoDocumento" @click="$refs.logoDocumentoInput.click()">
									{{ t('empleados', 'Upload logo') }}
								</NcButton>

								<NcButton :disabled="loadingLogoDocumento || !logoDocumentoUrl"
									@click="eliminarLogoDocumento">
									{{ t('empleados', 'Remove logo') }}
								</NcButton>
							</div>
						</div>
					</div>
				</div>
			</section>

			<section class="settings-category settings-category-wide">
				<div class="category-header">
					<p class="section-label">
						{{ t('empleados', 'Time reports') }}
					</p>
					<h3>{{ t('empleados','Report times settings') }}</h3>
					<p>{{ t('empleados', 'Reminder and compliance settings for the time reports module.') }}</p>
				</div>

				<div class="settings-card settings-form-card">
					<div class="switch-grid">
						<NcCheckboxRadioSwitch
							:checked="reportes_recordatorios_enabled"
							type="switch"
							@update:checked="reportes_recordatorios_enabled = !reportes_recordatorios_enabled">
							{{ t('empleados', 'Enable automatic reminders') }}
						</NcCheckboxRadioSwitch>

						<NcCheckboxRadioSwitch
							:checked="reportes_recordatorios_email"
							type="switch"
							@update:checked="reportes_recordatorios_email = !reportes_recordatorios_email">
							{{ t('empleados', 'Send reminders by email') }}
						</NcCheckboxRadioSwitch>
					</div>

					<div class="settings-grid">
						<NcTextField
							:value.sync="reportes_recordatorios_grupo"
							:label="t('empleados', 'Group required to report')" />

						<NcTextField
							:value.sync="reportes_recordatorios_hora"
							type="number"
							min="0"
							max="23"
							:label="t('empleados', 'Reminder hour')" />

						<NcTextField
							:value.sync="reportes_recordatorios_zona_horaria"
							:label="t('empleados', 'Time zone')" />

						<NcTextField
							:value.sync="reportes_horas_minimas"
							type="number"
							min="0"
							:label="t('empleados', 'Minimum hours to consider reported')" />

						<NcSelect
							v-model="selected_admin_reports_group"
							:input-label="t('empleados', 'Group with access to admin reports and compliance tracking')"
							:options="optionsGroups"
							class="fit" />
					</div>

					<div class="actions-row">
						<NcButton
							:aria-label="t('empleados','Apply changes')"
							type="primary"
							@click="saveConfiguracionReportes">
							{{ t('empleados','Apply changes') }}
						</NcButton>
					</div>
				</div>
			</section>

			<section class="settings-category settings-category-wide">
				<div class="category-header">
					<p class="section-label">
						{{ t('empleados', 'Files and security') }}
					</p>
					<h3>{{ t('empleados', 'Data manager and provisioning') }}</h3>
					<p>{{ t('empleados', 'Control the account used for shared employee files and the provisioning token.') }}</p>
				</div>

				<div class="settings-grid">
					<div class="settings-card settings-form-card">
						<NcNoteCard v-if="selected_user" :type="'warning'" :heading="t('empleados','ATTENTION')">
							<p>
								{{ t('empleados', 'If you change the file manager user after it has already been set, file loss may occur. Consider making a backup before proceeding.') }}
							</p>
						</NcNoteCard>

						<NcSelect
							v-model="selected_user"
							:input-label="t('empleados','Data manager user')"
							:options="optionsGestor"
							:user-select="true" />

						<div class="actions-row">
							<NcButton
								:aria-label="t('empleados','Apply changes')"
								type="primary"
								@click="saveGestor">
								{{ t('empleados','Apply changes') }}
							</NcButton>
						</div>
					</div>

					<div class="settings-card settings-form-card">
						<NcPasswordField :value.sync="secrettoken"
							:label="t('empleados', 'Secret token to admin moves')"
							as-text />
						<div class="actions-row">
							<NcButton
								:aria-label="t('empleados','Apply changes')"
								type="primary"
								@click="saveSecretToken">
								{{ t('empleados','Apply changes') }}
							</NcButton>
						</div>
					</div>
				</div>
			</section>
		</div>
	</div>
</template>

<script>
// Icons
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'

// @nextcloud/vue components
import {
	NcButton,
	NcLoadingIcon,
	NcSelect,
	NcNoteCard,
	NcCheckboxRadioSwitch,
	NcPasswordField,
	NcTextField,
} from '@nextcloud/vue'

// Nextcloud utils
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'ListSettings',
	components: {
		AccountGroup,
		NcSelect,
		NcButton,
		NcNoteCard,
		NcLoadingIcon,
		NcCheckboxRadioSwitch,
		NcPasswordField,
		NcTextField,
	},

	data() {
		return {
			loading: true,

			// General configurations
			configuraciones: [],

			// Users list (from GetConfigurations) used for Data Manager selector
			optionsGestor: [],

			selected_user: null, // Selected Data Manager
			guardado_notas: false,
			acumular_vacaciones: false,
			modulo_ahorro: false,
			modulo_ausencias: false,
			modulo_ausencias_readonly: false,
			modulo_clientes: false,
			modulo_reporte_tiempos: false,

			modulo_inventario: false,
			modulo_soporte: false,
			secrettoken: null,

			reportes_recordatorios_enabled: true,
			reportes_recordatorios_grupo: 'empleados',
			reportes_recordatorios_hora: 17,
			reportes_recordatorios_zona_horaria: 'America/Mexico_City',
			reportes_recordatorios_email: true,
			reportes_horas_minimas: 0,
			optionsGroups: [],
			selected_admin_reports_group: null,
			reportes_admin_reports_group: '',
			modulo_compras: false,
			logoDocumentoUrl: '',
			loadingLogoDocumento: false,
		}
	},

	async mounted() {
		await this.getall()
		await this.refreshLogoDocumento()
	},

	beforeDestroy() {
		this.revokeLogoDocumentoUrl()
	},

	methods: {
		t,
		/**
		 * Load global configuration, including "Users" for Data Manager.
		 */
		async getall() {
			try {
				this.loading = true
				const response = await axios.get(generateUrl('/apps/empleados/GetConfigurations'))

				this.optionsGestor = response.data.Users
				this.selected_user = response.data.Gestor_actual

				this.guardado_notas = (response.data.Guardado_notas === 'true')
				this.acumular_vacaciones = (response.data.Acumular_vacaciones === 'true')
				this.modulo_ahorro = (response.data.modulo_ahorro === 'true')
				this.modulo_ausencias = (response.data.modulo_ausencias === 'true')
				this.modulo_ausencias_readonly = (response.data.modulo_ausencias_readonly === 'true')
				this.modulo_clientes = (response.data.modulo_clientes === 'true')
				this.modulo_reporte_tiempos = (response.data.modulo_reporte_tiempos === 'true')
				this.modulo_inventario = (response.data.modulo_inventario === 'true')
				this.modulo_soporte = (response.data.modulo_soporte === 'true')
				this.modulo_compras = (response.data.modulo_compras === 'true')

				const reportes = response.data.Reportes || {}

				this.reportes_recordatorios_enabled = String(reportes.recordatorios_enabled ?? 'true') === 'true'
				this.reportes_recordatorios_grupo = reportes.recordatorios_grupo || 'empleados'
				this.reportes_recordatorios_hora = Number(reportes.recordatorios_hora ?? 17)
				this.reportes_recordatorios_zona_horaria = reportes.recordatorios_zona_horaria || 'America/Mexico_City'
				this.reportes_recordatorios_email = String(reportes.recordatorios_email ?? 'true') === 'true'
				this.reportes_horas_minimas = Number(reportes.horas_minimas ?? 0)
				this.optionsGroups = (response.data.Groups || []).map(group => ({
					id: group.id,
					label: group.label || group.id,
				}))

				this.reportes_admin_reports_group = reportes.admin_reports_group || ''

				this.selected_admin_reports_group = this.optionsGroups.find(
					group => group.id === this.reportes_admin_reports_group,
				) || (this.reportes_admin_reports_group
					? {
						id: this.reportes_admin_reports_group,
						label: this.reportes_admin_reports_group,
					}
					: null)

				this.loading = false
			} catch (err) {
				this.loading = false
				showError(t('empleados', 'Exception [GetConfigurations]: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		/**
		 * Update selected Data Manager
		 */
		async saveGestor() {
			if (!this.selected_user || !this.selected_user.id) {
				showError(t('empleados', 'No Data Manager selected'))
				return
			}
			try {
				await axios.post(generateUrl('/apps/empleados/ActualizarGestor'), {
					id_gestor: this.selected_user.id,
				})
				showSuccess(t('empleados', 'Manager updated'))
				this.$bus?.emit('GetDataManager') // Notify other components
			} catch (err) {
				showError(t('empleados', 'Error updating manager: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		/**
		 * Toggle: Automatic note saving
		 */
		async onChangeGuardadoNotas() {
			this.guardado_notas = !this.guardado_notas
			try {
				await axios.post(generateUrl('/apps/empleados/ActualizarConfiguracion'), {
					id_configuracion: 'automatic_save_note',
					data: this.guardado_notas.toString(),
				})
				showSuccess(t('empleados', 'Configuration updated'))
				this.$bus?.emit('GetDataManager') // Notify other components
			} catch (err) {
				showError(t('empleados', 'Exception [UpdateConfiguration]: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		/**
		 * Toggle: Customers module
		 */
		async onChangemodulo_clientes() {
			this.modulo_clientes = !this.modulo_clientes
			try {
				await axios.post(generateUrl('/apps/empleados/ActualizarConfiguracion'), {
					id_configuracion: 'modulo_clientes',
					data: this.modulo_clientes.toString(),
				})
				showSuccess(t('empleados', 'Configuration updated'))
			} catch (err) {
				showError(t('empleados', 'Exception [UpdateConfiguration]: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		/**
		 * Toggle: Report times module
		 */
		async onChangemodulo_reporte_tiempos() {
			this.modulo_reporte_tiempos = !this.modulo_reporte_tiempos
			try {
				await axios.post(generateUrl('/apps/empleados/ActualizarConfiguracion'), {
					id_configuracion: 'modulo_reporte_tiempos',
					data: this.modulo_reporte_tiempos.toString(),
				})
				showSuccess(t('empleados', 'Configuration updated'))
			} catch (err) {
				showError(t('empleados', 'Exception [UpdateConfiguration]: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		/**
		 * Toggle: IT inventory module
		 */
		async onChangemodulo_inventario() {
			this.modulo_inventario = !this.modulo_inventario

			try {
				await axios.post(generateUrl('/apps/empleados/ActualizarConfiguracion'), {
					id_configuracion: 'modulo_inventario',
					data: this.modulo_inventario.toString(),
				})

				showSuccess(t('empleados', 'Configuration updated. Refresh the page to update the navigation menu.'))
			} catch (err) {
				showError(t('empleados', 'Exception [UpdateConfiguration]: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		/**
		 * Toggle: IT support module
		 */
		async onChangemodulo_soporte() {
			this.modulo_soporte = !this.modulo_soporte

			try {
				await axios.post(generateUrl('/apps/empleados/ActualizarConfiguracion'), {
					id_configuracion: 'modulo_soporte',
					data: this.modulo_soporte.toString(),
				})

				showSuccess(t('empleados', 'Configuration updated. Refresh the page to update the navigation menu.'))
			} catch (err) {
				showError(t('empleados', 'Exception [UpdateConfiguration]: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		/**
		 * Toggle: Accrue vacation
		 */
		async onChangeacumular_vacaciones() {
			this.acumular_vacaciones = !this.acumular_vacaciones
			try {
				await axios.post(generateUrl('/apps/empleados/ActualizarConfiguracion'), {
					id_configuracion: 'acumular_vacaciones',
					data: this.acumular_vacaciones.toString(),
				})
				showSuccess(t('empleados', 'Configuration updated'))
			} catch (err) {
				showError(t('empleados', 'Exception [UpdateConfiguration]: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		/**
		 * Toggle: Savings module
		 */
		async onChangemodulo_ahorro() {
			this.modulo_ahorro = !this.modulo_ahorro
			try {
				await axios.post(generateUrl('/apps/empleados/ActualizarConfiguracion'), {
					id_configuracion: 'modulo_ahorro',
					data: this.modulo_ahorro.toString(),
				})
				showSuccess(t('empleados', 'Configuration updated'))
			} catch (err) {
				showError(t('empleados', 'Exception [UpdateConfiguration]: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		/**
		 * Toggle: Absences module
		 */
		async onChangemodulo_ausencias() {
			this.modulo_ausencias = !this.modulo_ausencias
			try {
				await axios.post(generateUrl('/apps/empleados/ActualizarConfiguracion'), {
					id_configuracion: 'modulo_ausencias',
					data: this.modulo_ausencias.toString(),
				})
				showSuccess(t('empleados', 'Configuration updated'))
			} catch (err) {
				showError(t('empleados', 'Exception [UpdateConfiguration]: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		/**
		 * Toggle: Absences module read-only
		 */
		async onChangemodulo_ausencias_readonly() {
			this.modulo_ausencias_readonly = !this.modulo_ausencias_readonly
			try {
				await axios.post(generateUrl('/apps/empleados/ActualizarConfiguracion'), {
					id_configuracion: 'ausencias_readonly',
					data: this.modulo_ausencias_readonly.toString(),
				})
				showSuccess(t('empleados', 'Configuration updated'))
			} catch (err) {
				showError(t('empleados', 'Exception [UpdateConfiguration]: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		/**
		 * Save secret token for admin moves
		 */
		async saveSecretToken() {
			this.modulo_ausencias = !this.modulo_ausencias
			try {
				await axios.post(generateUrl('/apps/empleados/provisioning'), {
					secret: this.secrettoken,
				})
				showSuccess(t('empleados', 'Configuration updated'))
			} catch (err) {
				showError(t('empleados', 'Exception [UpdateConfiguration]: {error}', { error: String(err) }))
				console.error(err)
			}
		},
		async saveConfiguracionReportes() {
			try {
				await axios.post(generateUrl('/apps/empleados/ActualizarConfiguracionReportes'), {
					recordatorios_enabled: this.reportes_recordatorios_enabled.toString(),
					recordatorios_grupo: this.reportes_recordatorios_grupo,
					recordatorios_hora: Number(this.reportes_recordatorios_hora),
					recordatorios_zona_horaria: this.reportes_recordatorios_zona_horaria,
					recordatorios_email: this.reportes_recordatorios_email.toString(),
					horas_minimas: Number(this.reportes_horas_minimas),
					admin_reports_group: this.selected_admin_reports_group?.id || this.reportes_admin_reports_group,
				})

				showSuccess(t('empleados', 'Configuration updated'))
			} catch (err) {
				showError(t('empleados', 'Exception [UpdateReportSettings]: {error}', { error: String(err) }))
				console.error(err)
			}
		},
		/**
		 * Toggle: Purchases module
		 */
		async onChangemodulo_compras() {
			this.modulo_compras = !this.modulo_compras

			try {
				await axios.post(generateUrl('/apps/empleados/ActualizarConfiguracion'), {
					id_configuracion: 'modulo_compras',
					data: this.modulo_compras.toString(),
				})

				showSuccess(t('empleados', 'Configuration updated. Refresh the page to update the navigation menu.'))
			} catch (err) {
				showError(t('empleados', 'Exception [UpdateConfiguration]: {error}', { error: String(err) }))
				console.error(err)
			}
		},

		revokeLogoDocumentoUrl() {
			if (this.logoDocumentoUrl && this.logoDocumentoUrl.startsWith('blob:')) {
				URL.revokeObjectURL(this.logoDocumentoUrl)
			}

			this.logoDocumentoUrl = ''
		},

		async refreshLogoDocumento() {
			this.revokeLogoDocumentoUrl()

			try {
				const response = await axios.get(
					generateUrl('/apps/empleados/compras/settings/logo'),
					{
						responseType: 'blob',
						headers: {
							requesttoken: OC.requestToken,
						},
					},
				)

				if (!response.data || response.data.size === 0) {
					this.logoDocumentoUrl = ''
					return
				}

				this.logoDocumentoUrl = URL.createObjectURL(response.data)
			} catch (error) {
				this.logoDocumentoUrl = ''

				if (error?.response?.status !== 404) {
					console.error(error)
				}
			}
		},

		async onLogoDocumentoSelected(event) {
			const file = event.target.files?.[0] || null

			if (!file) {
				return
			}

			if (!['image/png', 'image/jpeg'].includes(file.type)) {
				showError(t('empleados', 'Only PNG or JPG logos are allowed.'))
				event.target.value = ''
				return
			}

			if (file.size > 2 * 1024 * 1024) {
				showError(t('empleados', 'The logo must not exceed 2 MB.'))
				event.target.value = ''
				return
			}

			this.loadingLogoDocumento = true

			try {
				const formData = new FormData()
				formData.append('logo', file)

				const response = await axios.post(
					generateUrl('/apps/empleados/compras/settings/logo'),
					formData,
					{
						headers: {
							'Content-Type': 'multipart/form-data',
						},
					},
				)

				const payload = response.data

				if (!payload.success) {
					throw new Error(payload.message || t('empleados', 'Could not upload logo.'))
				}

				showSuccess(payload.message || t('empleados', 'Logo uploaded successfully.'))
				await this.refreshLogoDocumento()
			} catch (error) {
				console.error(error)
				showError(t('empleados', 'Error uploading logo: {error}', { error: String(error) }))
			} finally {
				this.loadingLogoDocumento = false
				event.target.value = ''
			}
		},

		async eliminarLogoDocumento() {
			this.loadingLogoDocumento = true

			try {
				const response = await axios.delete(
					generateUrl('/apps/empleados/compras/settings/logo'),
				)

				const payload = response.data

				if (!payload.success) {
					throw new Error(payload.message || t('empleados', 'Could not remove logo.'))
				}

				showSuccess(payload.message || t('empleados', 'Logo removed successfully.'))
				this.revokeLogoDocumentoUrl()
			} catch (error) {
				console.error(error)
				showError(t('empleados', 'Error removing logo: {error}', { error: String(error) }))
			} finally {
				this.loadingLogoDocumento = false
			}
		},
	},
}
</script>

<style scoped>
/* Board title */
.board-title {
	display: flex;
	align-items: center;
	gap: 10px;
	margin: 14px 20px 18px;
	color: var(--color-main-text);
	font-size: 25px;
	font-weight: bold;
}

/* Centered loading */
.center-screen {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 55vh;
	text-align: center;
}

.settings-layout {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 18px;
	padding: 0 20px 28px;
}

.settings-category {
	min-width: 0;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.settings-category-wide {
	grid-column: 1 / -1;
}

.category-header {
	margin-bottom: 16px;
}

.section-label {
	margin: 0 0 4px;
	color: var(--color-primary-element);
	font-size: 12px;
	font-weight: 700;
	letter-spacing: .04em;
	text-transform: uppercase;
}

.category-header h3 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 20px;
	font-weight: 700;
}

.category-header p {
	max-width: 820px;
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	line-height: 1.4;
}

.settings-grid,
.modules-grid {
	display: grid;
	gap: 12px;
}

.settings-grid {
	grid-template-columns: repeat(2, minmax(220px, 1fr));
}

.modules-grid {
	grid-template-columns: repeat(3, minmax(220px, 1fr));
}

.settings-card {
	min-width: 0;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.settings-form-card {
	background: var(--color-main-background);
}

.settings-card :deep(.notecard) {
	margin: 0 0 14px;
}

.settings-card :deep(p) {
	margin: 0 0 10px;
	line-height: 1.45;
}

.settings-card :deep(.checkbox-radio-switch) {
	margin-top: 10px;
}

.setting-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	min-height: 58px;
}

.setting-row strong,
.module-card-header strong {
	display: block;
	color: var(--color-main-text);
	font-size: 15px;
}

.setting-row span,
.module-card-header span {
	display: block;
	margin-top: 4px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	line-height: 1.4;
}

.module-card-header {
	min-height: 64px;
	margin-bottom: 8px;
}

.switch-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(220px, 1fr));
	gap: 10px 16px;
	margin-bottom: 16px;
}

.actions-row {
	display: flex;
	justify-content: flex-end;
	margin-top: 16px;
}

@media (max-width: 1100px) {
	.modules-grid {
		grid-template-columns: repeat(2, minmax(220px, 1fr));
	}
}

@media (max-width: 700px) {
	.board-title {
		margin: 10px 14px 14px;
		font-size: 22px;
	}

	.settings-layout {
		grid-template-columns: 1fr;
		padding: 0 14px 20px;
	}

	.settings-category-wide {
		grid-column: auto;
	}

	.settings-grid,
	.modules-grid,
	.switch-grid {
		grid-template-columns: 1fr;
	}

	.setting-row {
		align-items: flex-start;
		flex-direction: column;
	}

	.actions-row {
		justify-content: stretch;
	}
}

.logo-settings-layout {
	display: flex;
	align-items: center;
	gap: 18px;
}

.logo-preview {
	width: 220px;
	min-height: 96px;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	display: flex;
	align-items: center;
	justify-content: center;
}

.logo-preview img {
	max-width: 180px;
	max-height: 70px;
	object-fit: contain;
}

.logo-preview span {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	text-align: center;
}

.logo-settings-content {
	display: flex;
	flex: 1;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
}

.logo-settings-content strong {
	color: var(--color-main-text);
	font-size: 15px;
}

.logo-settings-content span {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	line-height: 1.4;
}

.logo-actions {
	justify-content: flex-start;
	gap: 10px;
	margin-top: 10px;
}

@media (max-width: 700px) {
	.logo-settings-layout {
		align-items: stretch;
		flex-direction: column;
	}

	.logo-preview {
		width: 100%;
	}
}
</style>
