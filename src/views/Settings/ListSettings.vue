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

		<div class="settings-container">
			<!-- Block: Automatic note saving -->
			<div class="settings-card settings-card-compact">
				<NcCheckboxRadioSwitch
					:checked="guardado_notas"
					type="switch"
					@update:checked="onChangeGuardadoNotas">
					{{ t('empleados', 'Automatic note saving') }}
				</NcCheckboxRadioSwitch>
			</div>

			<!-- Block: Accrue vacation -->
			<div class="settings-card settings-card-compact">
				<NcCheckboxRadioSwitch
					:checked="acumular_vacaciones"
					type="switch"
					@update:checked="onChangeacumular_vacaciones">
					{{ t('empleados', 'Allow all users to accrue vacation') }}
				</NcCheckboxRadioSwitch>
			</div>

			<!-- Block: Savings module -->
			<div class="settings-card">
				<NcNoteCard :type="'info'" :heading="t('empleados','Savings module')">
					<p>
						{{ t('empleados', 'If the savings module is enabled, users will see the savings option in their menu.') }}
					</p>
					<p>
						{{ t('empleados', 'When the module is enabled, all users\\’ states are reset to 0.') }}
					</p>
					<NcCheckboxRadioSwitch
						:checked="modulo_ahorro"
						type="switch"
						@update:checked="onChangemodulo_ahorro">
						{{ t('empleados', 'Enable savings module') }}
					</NcCheckboxRadioSwitch>
				</NcNoteCard>
			</div>

			<!-- Block: Absences module -->
			<div class="settings-card">
				<NcNoteCard :type="'info'" :heading="t('empleados','Absences module')">
					<p>
						{{ t('empleados', 'If the absences module is enabled, users will see the absences option in their menu.') }}
					</p>
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
						{{ t('empleados', 'Read-only (no one can request absences)') }}
					</NcCheckboxRadioSwitch>
				</NcNoteCard>
			</div>

			<!-- Block: Customers module -->
			<div class="settings-card">
				<NcNoteCard :type="'info'" :heading="t('empleados','Customers module')">
					<NcCheckboxRadioSwitch
						:checked="modulo_clientes"
						type="switch"
						@update:checked="onChangemodulo_clientes">
						{{ t('empleados', 'Enable customers module') }}
					</NcCheckboxRadioSwitch>
				</NcNoteCard>
			</div>

			<!-- Block: report times module -->
			<div class="settings-card">
				<NcNoteCard :type="'info'" :heading="t('empleados','Report times module')">
					<NcCheckboxRadioSwitch
						:checked="modulo_reporte_tiempos"
						type="switch"
						@update:checked="onChangemodulo_reporte_tiempos">
						{{ t('empleados', 'Enable report times module') }}
					</NcCheckboxRadioSwitch>
				</NcNoteCard>
			</div>

			<div class="settings-card settings-card-wide">
				<NcNoteCard :type="'info'" :heading="t('empleados','Report times settings')">
					<p>
						Configuración de recordatorios y cumplimiento del módulo de reportes de tiempo.
					</p>

					<NcCheckboxRadioSwitch
						:checked="reportes_recordatorios_enabled"
						type="switch"
						@update:checked="reportes_recordatorios_enabled = !reportes_recordatorios_enabled">
						Activar recordatorios automáticos
					</NcCheckboxRadioSwitch>

					<NcCheckboxRadioSwitch
						:checked="reportes_recordatorios_email"
						type="switch"
						@update:checked="reportes_recordatorios_email = !reportes_recordatorios_email">
						Enviar recordatorios por correo
					</NcCheckboxRadioSwitch>

					<div class="settings-grid">
						<NcTextField
							:value.sync="reportes_recordatorios_grupo"
							label="Grupo obligado a reportar" />

						<NcTextField
							:value.sync="reportes_recordatorios_hora"
							type="number"
							min="0"
							max="23"
							label="Hora de recordatorio" />

						<NcTextField
							:value.sync="reportes_recordatorios_zona_horaria"
							label="Zona horaria" />

						<NcTextField
							:value.sync="reportes_horas_minimas"
							type="number"
							min="0"
							label="Horas mínimas para considerar reportado" />
					</div>

					<div class="actions-row">
						<NcButton
							:aria-label="t('empleados','Apply changes')"
							type="primary"
							@click="saveConfiguracionReportes">
							{{ t('empleados','Apply changes') }}
						</NcButton>
					</div>
				</NcNoteCard>
			</div>

			<!-- Block: Single select for Data Manager -->
			<div class="settings-card settings-card-wide">
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

			<!-- Block: Multi-select for Human Resources -->
			<div class="settings-card settings-card-wide">
				<NcSelect
					v-bind="propsCapitalHumano"
					v-model="selectedUsers"
					:input-label="t('empleados','Select Human Resources users')" />

				<div class="actions-row">
					<NcButton
						:aria-label="t('empleados','Apply changes')"
						type="primary"
						@click="saveCapitalHumano">
						{{ t('empleados','Apply changes') }}
					</NcButton>
				</div>
			</div>

			<div class="settings-card settings-card-wide">
				<NcPasswordField :value.sync="secrettoken"
					label="Secret token to admin moves"
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

			// Users list (from GetConfigurations) used for Data Manager and HR selector
			optionsGestor: [],

			selected_user: null, // Selected Data Manager
			guardado_notas: false,
			acumular_vacaciones: false,
			modulo_ahorro: false,
			modulo_ausencias: false,
			modulo_ausencias_readonly: false,
			modulo_clientes: false,
			modulo_reporte_tiempos: false,

			// MULTI SELECT — Human Resources
			propsCapitalHumano: {
				userSelect: true,
				multiple: true,
				closeOnSelect: false,
				options: [], // Filled with optionsGestor
			},

			// Selected uids for HR
			selectedUsers: [],

			// From GetCapitalHumano (actual HR users)
			capitalHumano: [],
			secrettoken: null,

			reportes_recordatorios_enabled: true,
			reportes_recordatorios_grupo: 'empleados',
			reportes_recordatorios_hora: 17,
			reportes_recordatorios_zona_horaria: 'America/Mexico_City',
			reportes_recordatorios_email: true,
			reportes_horas_minimas: 0,
		}
	},

	async mounted() {
		// Load settings and HR list in parallel
		await Promise.all([
			this.getall(),
			this.fetchCapitalHumano(),
		])

		// Setup HR selector with options and pre-selected users
		this.setupCapitalHumanoSelector()

		this.loading = false
	},

	methods: {
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

				const reportes = response.data.Reportes || {}

				this.reportes_recordatorios_enabled = String(reportes.recordatorios_enabled ?? 'true') === 'true'
				this.reportes_recordatorios_grupo = reportes.recordatorios_grupo || 'empleados'
				this.reportes_recordatorios_hora = Number(reportes.recordatorios_hora ?? 17)
				this.reportes_recordatorios_zona_horaria = reportes.recordatorios_zona_horaria || 'America/Mexico_City'
				this.reportes_recordatorios_email = String(reportes.recordatorios_email ?? 'true') === 'true'
				this.reportes_horas_minimas = Number(reportes.horas_minimas ?? 0)

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
		 * Update Human Resources list
		 */
		async saveCapitalHumano() {
			try {
				await axios.post(generateUrl('/apps/empleados/UpdateCapitalHumano'), {
					capitalhumano: this.selectedUsers,
				})
				showSuccess(t('empleados', 'Human Resources updated'))
			} catch (err) {
				showError(t('empleados', 'Error updating Human Resources: {error}', { error: String(err) }))
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
		 * Get current Human Resources users
		 */
		async fetchCapitalHumano() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetCapitalHumano'))
				this.capitalHumano = response.data
			} catch (error) {
				showError(t('empleados', 'Error fetching Human Resources users'))
				console.error(error)
			}
		},

		/**
		 * Configure multi-select for HR:
		 * - Use ALL users from "optionsGestor" as options
		 * - Preselect those present in "capitalHumano"
		 */
		setupCapitalHumanoSelector() {
			// 1) Convert optionsGestor to NcSelect format
			this.propsCapitalHumano.options = this.optionsGestor.map(user => ({
				id: user.id,
				displayName: user.displayName || user.uid,
				isNoUser: false,
				icon: '',
				user: user.uid,
				preloadedUserStatus: {
					icon: '',
					status: user.isEnabled ? 'online' : 'offline',
					message: user.isEnabled ? this.t('empleados', 'Active') : this.t('empleados', 'Inactive'),
				},
			}))

			// 2) Extract ids from capitalHumano
			const capitalHumanoIds = this.capitalHumano.map(u => u.id)

			// 3) Preselect
			this.selectedUsers = this.propsCapitalHumano.options
				.filter(opt => capitalHumanoIds.includes(opt.id))
				.map(opt => opt.id)
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
				})

				showSuccess(t('empleados', 'Configuration updated'))
			} catch (err) {
				showError(t('empleados', 'Exception [UpdateReportSettings]: {error}', { error: String(err) }))
				console.error(err)
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

/* Container */
.settings-container {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 16px;
	padding: 0 20px 28px;
}

.settings-card {
	min-width: 0;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.settings-card-compact {
	display: flex;
	align-items: center;
	min-height: 58px;
}

.settings-card-wide {
	grid-column: 1 / -1;
}

.settings-card :deep(.notecard) {
	margin: 0;
}

.settings-card :deep(p) {
	margin: 0 0 10px;
	line-height: 1.45;
}

.settings-card :deep(.checkbox-radio-switch) {
	margin-top: 8px;
}

.actions-row {
	display: flex;
	justify-content: flex-end;
	margin-top: 14px;
}

.settings-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(220px, 1fr));
	gap: 12px;
	max-width: 760px;
	margin-top: 14px;
}

@media (max-width: 700px) {
	.board-title {
		margin: 10px 14px 14px;
		font-size: 22px;
	}

	.settings-container {
		grid-template-columns: 1fr;
		padding: 0 14px 20px;
	}

	.settings-card-wide {
		grid-column: auto;
	}

	.settings-grid {
		grid-template-columns: 1fr;
	}

	.actions-row {
		justify-content: stretch;
	}
}
</style>
