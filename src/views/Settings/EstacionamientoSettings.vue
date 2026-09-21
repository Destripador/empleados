<template>
	<div v-if="loading" class="app-settings-loading" role="status">
		<NcLoadingIcon :size="40" :name="t('empleados', 'Loading...')" />
	</div>
	<div v-else class="app-settings-page parking-settings">
		<header class="app-settings-header">
			<p class="app-settings-eyebrow">
				{{ t('empleados', 'Parking') }}
			</p>
			<h2 class="app-settings-title">
				{{ t('empleados', 'Parking settings') }}
			</h2>
			<p class="app-settings-description">
				{{ t('empleados', 'Manage publication status, space assignments and obstruction relationships.') }}
			</p>
		</header>

		<section class="app-settings-panel parking-status-card" aria-labelledby="parking-mode-title">
			<header class="app-settings-panel__header">
				<div>
					<h3 id="parking-mode-title">
						{{ t('empleados', 'Parking publication status') }}
					</h3>
					<p>{{ t('empleados', 'Control whether the parking map is published or temporarily under maintenance.') }}</p>
				</div>
				<span v-if="parkingStatusLoaded"
					class="parking-status"
					:class="isMaintenance ? 'parking-status--maintenance' : 'parking-status--operational'">
					<AlertCircleOutline v-if="isMaintenance" :size="16" aria-hidden="true" />
					<CheckCircleOutline v-else :size="16" aria-hidden="true" />
					{{ isMaintenance ? t('empleados', 'Under maintenance') : t('empleados', 'Operational') }}
				</span>
			</header>

			<div class="app-settings-panel__body parking-status-card__body">
				<div v-if="!parkingStatusLoaded" class="parking-status-unavailable" role="status">
					<AlertCircleOutline :size="28" aria-hidden="true" />
					<div>
						<strong>{{ t('empleados', 'Parking status is unavailable') }}</strong>
						<p>{{ t('empleados', 'The map cannot be displayed safely until its publication status is known.') }}</p>
					</div>
					<NcButton @click="fetchParkingStatus">
						{{ t('empleados', 'Retry') }}
					</NcButton>
				</div>

				<ParkingMaintenanceBanner
					v-else-if="isMaintenance"
					embedded
					:status="parkingStatus"
					:can-publish="true"
					:publishing="publishing"
					@publish="showPublishModal = true" />

				<div v-else class="parking-mode-operational">
					<div>
						<strong>{{ t('empleados', 'Parking published') }}</strong>
						<p>{{ t('empleados', 'The current parking map is visible to authorized users.') }}</p>
					</div>
					<NcButton type="primary" @click="showActivationModal = true">
						{{ t('empleados', 'Activate maintenance') }}
					</NcButton>
				</div>
			</div>
		</section>

		<section class="app-settings-panel parking-assignments" aria-labelledby="parking-assignments-title">
			<header class="app-settings-panel__header">
				<div>
					<h3 id="parking-assignments-title">
						{{ t('empleados', 'Assignments') }}
					</h3>
					<p>{{ t('empleados', 'Manage the occupants of each space and the spaces that it obstructs.') }}</p>
				</div>
			</header>

			<div v-if="espaciosLoadError" class="parking-spaces-error" role="alert">
				<AlertCircleOutline :size="28" aria-hidden="true" />
				<div>
					<strong>{{ t('empleados', 'Could not load the parking spaces.') }}</strong>
					<p>{{ t('empleados', 'Assignments cannot be shown until the space catalog is available.') }}</p>
				</div>
				<NcButton :disabled="espaciosLoading" @click="fetchEspacios">
					{{ t('empleados', 'Retry') }}
				</NcButton>
			</div>

			<VueTabs v-else class="settings-secondary-tabs parking-tabs">
				<VTab id="parking-space-assignments" :title="t('empleados', 'Asignar espacios')">
					<div class="parking-tab-body">
						<div v-if="espacios.length"
							class="parking-table-region"
							role="region"
							:aria-label="t('empleados', 'Parking space assignments')"
							tabindex="0">
							<table class="parking-table">
								<caption class="app-settings-sr-only">
									{{ t('empleados', 'Parking space assignments') }}
								</caption>
								<thead>
									<tr>
										<th scope="col" class="parking-table__space">
											{{ t('empleados', 'Space') }}
										</th>
										<th scope="col">
											{{ t('empleados', 'Employees') }}
										</th>
										<th scope="col" class="parking-table__actions">
											{{ t('empleados', 'Action') }}
										</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="espacio in espacios" :key="espacio.id_espacio">
										<td class="parking-table__space">
											<strong>{{ espacio.numero }}</strong>
										</td>
										<td v-if="casillaEdit == espacio.id_espacio">
											<NcSelect
												v-bind="selectConfig"
												v-model="empleados"
												:input-label="t('empleados','Usuarios que ocupa el espacio')" />
										</td>
										<td v-else>
											<span class="parking-table__hint">
												{{ t('empleados', 'Select Edit to review or change assigned employees.') }}
											</span>
										</td>
										<td class="parking-table__actions">
											<NcButton v-if="casillaEdit == espacio.id_espacio"
												type="primary"
												@click="guardar(espacio.id_espacio)">
												{{ t('empleados', 'Save') }}
											</NcButton>
											<NcButton v-else @click="edit(espacio.id_espacio)">
												{{ t('empleados', 'Edit') }}
											</NcButton>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
						<NcEmptyContent v-else
							:name="t('empleados', 'No parking spaces configured')"
							:description="t('empleados', 'Parking spaces will appear here when they are added.')">
							<template #icon>
								<ParkingIcon :size="32" />
							</template>
						</NcEmptyContent>
					</div>
				</VTab>
				<VTab id="parking-obstruction-relationships" :title="t('empleados', 'Espacios que obstruyen')">
					<div class="parking-tab-body">
						<div v-if="espacios.length"
							class="parking-table-region"
							role="region"
							:aria-label="t('empleados', 'Parking obstruction relationships')"
							tabindex="0">
							<table class="parking-table">
								<caption class="app-settings-sr-only">
									{{ t('empleados', 'Parking obstruction relationships') }}
								</caption>
								<thead>
									<tr>
										<th scope="col" class="parking-table__space">
											{{ t('empleados', 'Space') }}
										</th>
										<th scope="col">
											{{ t('empleados', 'Blocked spaces') }}
										</th>
										<th scope="col" class="parking-table__actions">
											{{ t('empleados', 'Action') }}
										</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="espacio in espacios" :key="espacio.id_espacio">
										<td class="parking-table__space">
											<strong>{{ espacio.numero }}</strong>
										</td>
										<td v-if="casillaEditObs == espacio.id_espacio">
											<NcSelect
												v-bind="selectObstruyenConfig"
												v-model="espaciosObstruyen"
												:input-label="t('empleados','Espacios a los que obstruye')" />
										</td>
										<td v-else>
											<span class="parking-table__hint">
												{{ t('empleados', 'Select Edit to review or change blocked spaces.') }}
											</span>
										</td>
										<td class="parking-table__actions">
											<NcButton v-if="casillaEditObs == espacio.id_espacio"
												type="primary"
												@click="guardarObstruye(espacio.id_espacio)">
												{{ t('empleados', 'Save') }}
											</NcButton>
											<NcButton v-else @click="editObstruye(espacio.id_espacio)">
												{{ t('empleados', 'Edit') }}
											</NcButton>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
						<NcEmptyContent v-else
							:name="t('empleados', 'No parking spaces configured')"
							:description="t('empleados', 'Parking spaces will appear here when they are added.')">
							<template #icon>
								<ParkingIcon :size="32" />
							</template>
						</NcEmptyContent>
					</div>
				</VTab>
			</VueTabs>
		</section>

		<NcModal v-if="showActivationModal"
			:name="t('empleados', 'Activate parking maintenance')"
			size="normal"
			@close="closeActivationModal">
			<form class="parking-mode-modal" @submit.prevent="activateMaintenance">
				<h3>{{ t('empleados', 'Temporarily hide the public parking map?') }}</h3>
				<p>{{ t('empleados', 'Administrators will keep access while assignments are updated. Other users will see a maintenance notice.') }}</p>
				<p>{{ t('empleados', 'The map will be marked as a draft until it is published.') }}</p>
				<NcTextField
					:value.sync="activationForm.reason"
					:label="t('empleados', 'Reason (optional)')"
					:maxlength="500" />
				<NcTextField
					:value.sync="activationForm.until"
					:label="t('empleados', 'Estimated availability (optional)')"
					type="datetime-local" />
				<p v-if="!activationUntilIsValid" class="parking-mode-modal__error" role="alert">
					{{ t('empleados', 'Estimated availability must be in the future.') }}
				</p>
				<div class="parking-mode-modal__actions">
					<NcButton native-type="button" :disabled="activating" @click="closeActivationModal">
						{{ t('empleados', 'Cancel') }}
					</NcButton>
					<NcButton
						type="primary"
						native-type="submit"
						:disabled="activating || !activationUntilIsValid">
						{{ activating ? t('empleados', 'Activating…') : t('empleados', 'Activate maintenance') }}
					</NcButton>
				</div>
			</form>
		</NcModal>

		<NcModal v-if="showPublishModal"
			:name="t('empleados', 'Publish parking')"
			size="normal"
			@close="showPublishModal = false">
			<div class="parking-mode-modal">
				<h3>{{ t('empleados', 'Confirm parking publication') }}</h3>
				<p>{{ t('empleados', 'The current assignments will become visible to all parking users.') }}</p>
				<p>{{ t('empleados', 'Make sure the map is complete and correct before publishing.') }}</p>
				<div class="parking-mode-modal__actions">
					<NcButton :disabled="publishing" @click="showPublishModal = false">
						{{ t('empleados', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" :disabled="publishing" @click="publishParking">
						{{ publishing ? t('empleados', 'Publishing…') : t('empleados', 'Publish parking') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
// icons

// nextcloud/vue
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
// import { ref } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { VueTabs, VTab } from 'vue-nav-tabs/dist/vue-tabs.js'
import { translate as t } from '@nextcloud/l10n'
import ParkingMaintenanceBanner from '../components/Estacionamiento/ParkingMaintenanceBanner.vue'
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import ParkingIcon from 'vue-material-design-icons/Parking.vue'

export default {
	name: 'EstacionamientoSettings',
	components: {
		AlertCircleOutline,
		CheckCircleOutline,
		NcButton,
		NcEmptyContent,
		NcModal,
		NcSelect,
		NcTextField,
		NcLoadingIcon,
		ParkingMaintenanceBanner,
		ParkingIcon,
		VueTabs,
		VTab,
	},

	data() {
		return {
			loading: true,
			parkingStatusLoaded: false,
			espaciosLoading: false,
			espaciosLoadError: false,
			parkingStatus: {
				mode: 'operational',
				maintenance: false,
				canManage: true,
				reason: null,
				startedAt: null,
				until: null,
			},
			statusPollTimer: null,
			showActivationModal: false,
			showPublishModal: false,
			activating: false,
			publishing: false,
			activationForm: {
				reason: '',
				until: '',
			},
			nombre: '',
			apellido: '',
			numRows: 24,
			selectConfig: {
				userSelect: true,
				multiple: true,
				closeOnSelect: false,
				options: [], // Filled with optionsGestor
			},
			selectObstruyenConfig: {
				userSelect: true,
				multiple: true,
				closeOnSelect: false,
				options: [], // Filled with optionsGestor
			},
			empleados: [],
			espacios: [],
			casillaEdit: null,
			casillaEditObs: null,
			espaciosObstruyen: [],
		}
	},
	computed: {
		isMaintenance() {
			return this.parkingStatus.maintenance === true
		},
		activationUntilIsValid() {
			if (!this.activationForm.until) {
				return true
			}

			const until = new Date(this.activationForm.until)
			return !Number.isNaN(until.getTime()) && until.getTime() > Date.now()
		},
	},

	// ciclos de vida
	beforeCreate() {
		// console.log('beforeCreate')
	},
	created() {
		// console.log('created')
	},
	beforeMount() {
		// console.log('beforeMount')
	},
	async mounted() {
		await Promise.all([
			this.fetchParkingStatus(),
			this.fetchEspacios(),
		])
		this.loading = false
		this.statusPollTimer = window.setInterval(() => this.fetchParkingStatus(false), 30000)
	},
	beforeUpdate() {
		// console.log('beforeUpdate')
	},
	updated() {
		// console.log('updated')
	},
	beforeDestroy() {
		if (this.statusPollTimer) {
			window.clearInterval(this.statusPollTimer)
		}
	},
	unmounted() {
		// console.log('unmounted')
	},

	methods: {
		t,
		async fetchParkingStatus(showFailure = true) {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/espacios/status'))
				this.parkingStatus = response?.data?.ocs?.data || this.parkingStatus
				this.parkingStatusLoaded = true
				if (!this.isMaintenance) {
					this.showPublishModal = false
				}
			} catch (error) {
				if (showFailure) {
					showError(t('empleados', 'Could not load the parking publication status.'))
				}
			}
		},
		closeActivationModal() {
			if (!this.activating) {
				this.showActivationModal = false
			}
		},
		async activateMaintenance() {
			if (!this.activationUntilIsValid) {
				return
			}

			this.activating = true
			try {
				const until = this.activationForm.until
					? new Date(this.activationForm.until).toISOString()
					: null
				const response = await axios.post(generateUrl('/apps/empleados/espacios/maintenance'), {
					reason: this.activationForm.reason.trim() || null,
					until,
				})
				this.parkingStatus = response?.data?.ocs?.data?.parking || this.parkingStatus
				this.showActivationModal = false
				this.activationForm = { reason: '', until: '' }
				showSuccess(t('empleados', 'Parking maintenance mode activated.'))
			} catch (error) {
				const message = error?.response?.data?.ocs?.data?.message
				showError(message || t('empleados', 'Could not activate parking maintenance.'))
				await this.fetchParkingStatus()
			} finally {
				this.activating = false
			}
		},
		async publishParking() {
			this.publishing = true
			try {
				const response = await axios.post(generateUrl('/apps/empleados/espacios/publish'))
				this.parkingStatus = response?.data?.ocs?.data?.parking || this.parkingStatus
				this.showPublishModal = false
				showSuccess(t('empleados', 'Parking published successfully.'))
			} catch (error) {
				const message = error?.response?.data?.ocs?.data?.message
				showError(message || t('empleados', 'Could not publish the parking map.'))
				await this.fetchParkingStatus()
			} finally {
				this.publishing = false
			}
		},
		async enviar() {
			// eslint-disable-next-line indent
            /* try {
				await axios.post(generateUrl('/apps/empleados/ejemplo'), {
					nombre_enviar: this.nombre,
					apellido_enviar: this.apellido,
				})
				showSuccess('enviado exitoso')
			} catch (err) {
				// eslint-disable-next-line no-console
				console.log(err)
				showError('Esta petición falló')
			} */
		},
		async edit(idEspacio) {
			this.casillaEdit = idEspacio
			this.empleados = [] // Limpiamos la selección actual
			try {
				// Cargar lista de todos los empleados para el NcSelect
				if (this.selectConfig.options.length === 0) {
					const resUsers = await axios.get(generateUrl('/apps/empleados/GetUserLists'))
					// Usar mapeo a empleados.id_empleados
					this.selectConfig.options = resUsers.data.ocs.data.Empleados.map(user => ({
						id: user.Id_empleados,
						displayName: user.displayName || user.uid,
						isNoUser: false,
						icon: '',
						user: user.Id_user,
						preloadedUserStatus: {
							icon: '',
							status: user.Estatus === 'activo' ? 'online' : 'offline',
							message: user.Estatus === 'activo' ? t('empleados', 'Active') : t('empleados', 'Inactive'),
						},
					}))
				}

				// Cargar qué empleados ya tienen ese espacio
				const resAsignados = await axios.get(generateUrl(`/apps/empleados/espacios/${idEspacio}/empleados`))
				// NcSelect múltiple espera un array de los IDs seleccionados
				const idsAsignados = resAsignados.data?.ocs?.data.empleados || []
				this.empleados = this.selectConfig.options.filter(opt => idsAsignados.includes(opt.id))
			} catch (err) {
				// eslint-disable-next-line no-console
				console.log(err)
				showError('Esta petición falló' + err.message)
			}
		},
		async guardar(idEspacio) {
			try {
				await axios.post(generateUrl('/apps/empleados/espacios/guardarAsignacion'), {
					id_espacio: idEspacio,
					id_empleados: this.empleados.map(e => e.id),
				})
				this.casillaEdit = null
			} catch (err) {
				showError('Error al guardar la asignación')
			}
		},
		async editObstruye(idEspacio) {
			this.casillaEditObs = idEspacio
			this.espaciosObstruyen = [] // Limpiamos la selección actual
			try {
				// Cargar lista de todos los espacios para el NcSelect
				if (this.selectObstruyenConfig.options.length === 0) {
					const resEspacios = await axios.get(generateUrl('/apps/empleados/GetEspacios'))
					// Usar mapeo a espacios.id_espacio
					this.selectObstruyenConfig.options = resEspacios.data.ocs.data.Espacio.map(espacio => ({
						id: espacio.id_espacio,
						displayName: espacio.numero,
						isNoUser: false,
						icon: '',
					}))
				}

				// Cargar qué espacios ya obstruyen a este espacio
				const resObstruyen = await axios.get(generateUrl(`/apps/empleados/espacios/${idEspacio}/obstruyen`))
				// NcSelect múltiple espera un array de los IDs seleccionados
				const idsObstruyen = resObstruyen.data?.ocs?.data.espacios || []
				this.espaciosObstruyen = this.selectObstruyenConfig.options.filter(opt => idsObstruyen.includes(opt.id))
			} catch (err) {
				// eslint-disable-next-line no-console
				console.log(err)
				showError('Petición a espacios que obstruyen falló' + err.message)

			}
		},
		async guardarObstruye(idEspacio) {
			try {
				await axios.post(generateUrl('/apps/empleados/espacios/guardarObstruyen'), {
					id_espacio: idEspacio,
					id_espacios_obstruyen: this.espaciosObstruyen.map(e => e.id),
				})
				this.casillaEditObs = null
			} catch (err) {
				showError('Error al guardar los espacios que obstruyen')
			}
		},
		async fetchEspacios() {
			this.espaciosLoading = true
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetEspacios'))
				const espacios = response?.data?.ocs?.data?.Espacio
				this.espacios = Array.isArray(espacios) ? espacios : []
				this.espaciosLoadError = false
			} catch (err) {
				this.espaciosLoadError = true
				showError(t('empleados', 'Could not load the parking spaces.'))
			} finally {
				this.espaciosLoading = false
			}
		},
	},
}
</script>

<style scoped>
.parking-status-card__body {
	padding: 0;
}

.parking-status {
	display: inline-flex;
	align-items: center;
	flex: 0 0 auto;
	gap: 6px;
	min-height: 26px;
	padding: 2px 9px;
	border-radius: var(--border-radius-pill, 999px);
	font-size: 0.8rem;
	font-weight: 700;
	white-space: nowrap;
}

.parking-status--maintenance {
	background: var(--color-warning-hover);
	color: var(--color-warning-text);
}

.parking-status--operational {
	background: var(--color-success-hover);
	color: var(--color-success-text);
}

.parking-mode-operational,
.parking-status-unavailable {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 20px;
	padding: 16px;
}

.parking-mode-operational > div,
.parking-status-unavailable > div {
	display: grid;
	min-width: 0;
	gap: 3px;
}

.parking-mode-operational p,
.parking-status-unavailable p {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.875rem;
	line-height: 1.4;
}

.parking-status-unavailable {
	justify-content: flex-start;
	color: var(--color-warning-text);
	background: var(--color-warning-hover);
}

.parking-spaces-error {
	display: flex;
	align-items: center;
	gap: 14px;
	margin: 16px;
	padding: 16px;
	border-radius: var(--border-radius-large);
	background: var(--color-error-hover);
	color: var(--color-error-text);
}

.parking-spaces-error > div {
	display: grid;
	min-width: 0;
	gap: 3px;
}

.parking-spaces-error p {
	margin: 0;
	line-height: 1.4;
}

.parking-spaces-error :deep(.button-vue) {
	margin-inline-start: auto;
}

.parking-status-unavailable :deep(.button-vue) {
	margin-inline-start: auto;
}

.parking-tab-body {
	min-width: 0;
	padding: 16px;
}

.parking-table-region {
	width: 100%;
	overflow-x: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.parking-table-region:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.parking-table {
	width: 100%;
	min-width: 620px;
	border-collapse: collapse;
	font-size: 0.875rem;
	text-align: start;
}

.parking-table thead {
	background: var(--color-background-hover);
}

.parking-table th,
.parking-table td {
	padding: 10px 14px;
	border-bottom: 1px solid var(--color-border);
	color: var(--color-main-text);
	vertical-align: middle;
}

.parking-table th {
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	font-weight: 700;
	letter-spacing: 0.04em;
	text-align: start;
	text-transform: uppercase;
}

.parking-table tbody tr:last-child td {
	border-bottom: 0;
}

.parking-table tbody tr:hover {
	background: var(--color-background-hover);
}

.parking-table__space {
	width: 7rem;
	font-variant-numeric: tabular-nums;
}

.parking-table__actions {
	width: 8rem;
	text-align: end !important;
	white-space: nowrap;
}

.parking-table__hint {
	color: var(--color-text-maxcontrast);
}

.parking-mode-modal {
	display: grid;
	box-sizing: border-box;
	width: min(540px, calc(100vw - 32px));
	gap: 14px;
	padding: 22px 24px 24px;
}

.parking-mode-modal h3,
.parking-mode-modal p {
	margin: 0;
}

.parking-mode-modal__error {
	color: var(--color-error);
	font-weight: 600;
}

.parking-mode-modal__actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 6px;
}

@media (max-width: 600px) {
	.parking-mode-operational,
	.parking-status-unavailable,
	.parking-spaces-error {
		align-items: stretch;
		flex-direction: column;
	}

	.parking-status-unavailable :deep(.button-vue),
	.parking-spaces-error :deep(.button-vue) {
		margin-inline-start: 0;
	}

	.parking-mode-operational :deep(.button-vue),
	.parking-status-unavailable :deep(.button-vue),
	.parking-spaces-error :deep(.button-vue) {
		width: 100%;
	}

	.parking-mode-modal__actions {
		flex-direction: column-reverse;
	}
}
</style>
