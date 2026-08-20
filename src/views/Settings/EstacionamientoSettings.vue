<template>
	<div v-if="loading">
		<!-- Loading section -->
		<div class="center-screen">
			<NcLoadingIcon :size="64" appearance="dark" :name="t('empleados', 'Loading...')" />
		</div>
	</div>
	<div v-else id="admin">
		<div class="container">
			<section class="parking-mode-control" aria-labelledby="parking-mode-title">
				<h2 id="parking-mode-title">
					{{ t('empleados', 'Parking publication status') }}
				</h2>
				<p class="parking-mode-current">
					<span>{{ t('empleados', 'Current state') }}</span>
					<strong>{{ isMaintenance ? t('empleados', 'Under maintenance') : t('empleados', 'Operational') }}</strong>
				</p>
				<ParkingMaintenanceBanner
					v-if="isMaintenance"
					:status="parkingStatus"
					:can-publish="true"
					:publishing="publishing"
					@publish="showPublishModal = true" />
				<div v-else class="parking-mode-operational">
					<div>
						<strong>{{ t('empleados', 'Operational') }}</strong>
						<p>{{ t('empleados', 'The current parking map is visible to authorized users.') }}</p>
					</div>
					<NcButton type="primary" @click="showActivationModal = true">
						{{ t('empleados', 'Activate maintenance') }}
					</NcButton>
				</div>
			</section>
			<VueTabs>
				<VTab :title="t('empleados', 'Asignar espacios')">
					<div class="table_component" role="region" tabindex="0">
						<table>
							<thead>
								<tr>
									<th>{{ t('empleados', 'Space') }}</th>
									<th>{{ t('empleados', 'Employees') }}</th>
									<th>{{ t('empleados', 'Action') }}</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="espacio in espacios" :key="espacio.id_espacio">
									<td>{{ espacio.numero }}</td>
									<td v-if="casillaEdit == espacio.id_espacio">
										<NcSelect
											v-bind="selectConfig"
											v-model="empleados"
											:input-label="t('empleados','Usuarios que ocupa el espacio')" />
									</td>
									<td v-else>
										{{ t('empleados', 'Ready to edit') }}
									</td>
									<td>
										<NcButton v-if="casillaEdit == espacio.id_espacio" @click="guardar(espacio.id_espacio)">
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
				</VTab>
				<VTab :title="t('empleados', 'Espacios que obstruyen')">
					<div class="table_component" role="region" tabindex="0">
						<table>
							<thead>
								<tr>
									<th>{{ t('empleados', 'Space') }}</th>
									<th>{{ t('empleados', 'Blocked spaces') }}</th>
									<th>{{ t('empleados', 'Action') }}</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="espacio in espacios" :key="espacio.id_espacio">
									<td>{{ espacio.numero }}</td>
									<td v-if="casillaEditObs == espacio.id_espacio">
										<NcSelect
											v-bind="selectObstruyenConfig"
											v-model="espaciosObstruyen"
											:input-label="t('empleados','Espacios a los que obstruye')" />
									</td>
									<td v-else>
										{{ t('empleados', 'Ready to edit') }}
									</td>
									<td>
										<NcButton v-if="casillaEditObs == espacio.id_espacio" @click="guardarObstruye(espacio.id_espacio)">
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
				</VTab>
			</VueTabs>
		</div>

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
import {
	NcButton,
	NcLoadingIcon,
	NcModal,
	NcSelect,
	NcTextField,
} from '@nextcloud/vue'
// import { ref } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { VueTabs, VTab } from 'vue-nav-tabs/dist/vue-tabs.js'
import { translate as t } from '@nextcloud/l10n'
import ParkingMaintenanceBanner from '../components/Estacionamiento/ParkingMaintenanceBanner.vue'

export default {
	name: 'EstacionamientoSettings',
	components: {
		NcButton,
		NcModal,
		NcSelect,
		NcTextField,
		NcLoadingIcon,
		ParkingMaintenanceBanner,
		VueTabs,
		VTab,
	},

	data() {
		return {
			loading: true,
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
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetEspacios'))
				this.espacios = response?.data?.ocs?.data.Espacio
			} catch (err) {
				showError('Error al cargar espacios')
			}
		},
	},
}
</script>

<style scoped>
.container {
    display: grid;
    gap: 22px;
}

.parking-mode-control {
    display: grid;
    gap: 12px;
    max-width: 1100px;
}

.parking-mode-control h2 {
    margin: 0;
}

.parking-mode-current {
    display: flex;
    gap: 8px;
    margin: 0;
}

.parking-mode-operational {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 16px;
    border: 1px solid var(--color-border);
    border-inline-start: 5px solid var(--color-success);
    border-radius: var(--border-radius-large);
    background: var(--color-main-background);
}

.parking-mode-operational p {
    margin: 4px 0 0;
    color: var(--color-text-maxcontrast);
}

.parking-mode-modal {
    display: grid;
    gap: 14px;
    box-sizing: border-box;
    width: min(540px, calc(100vw - 32px));
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
    .parking-mode-operational {
        align-items: stretch;
        flex-direction: column;
    }

    .parking-mode-modal__actions {
        flex-direction: column-reverse;
    }
}

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

/* Centered loading */
.center-screen {
  display: flex;
  justify-content: center;
  align-items: center;
  text-align: center;
  min-height: 100vh;
}
</style>
