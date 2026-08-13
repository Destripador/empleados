<template>
	<div v-if="loading">
		<!-- Loading section -->
		<div class="center-screen">
			<NcLoadingIcon :size="64" appearance="dark" name="Loading on light background" />
		</div>
	</div>
	<div v-else id="admin">
		<div class="container">
			<NcButton @click="showModal">
				Asignar espacios
			</NcButton>
			<VueTabs>
				<VTab :title="t('empleados', 'Asignar espacios')">
					<div class="table_component" role="region" tabindex="0">
						<table>
							<thead>
								<tr>
									<th>Espacio</th>
									<th>Empleados</th>
									<th>Acción</th>
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
										Listo para editar
									</td>
									<td>
										<NcButton v-if="casillaEdit == espacio.id_espacio" @click="guardar(espacio.id_espacio)">
											Guardar
										</NcButton>
										<NcButton v-else @click="edit(espacio.id_espacio)">
											Editar
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
									<th>Espacio</th>
									<th>Espacios a los que obstruye</th>
									<th>Acción</th>
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
										Listo para editar
									</td>
									<td>
										<NcButton v-if="casillaEditObs == espacio.id_espacio" @click="guardarObstruye(espacio.id_espacio)">
											Guardar
										</NcButton>
										<NcButton v-else @click="editObstruye(espacio.id_espacio)">
											Editar
										</NcButton>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</VTab>
			</VueTabs>
		</div>
	</div>
</template>

<script>
// icons

// nextcloud/vue
import {
	NcButton,
	NcLoadingIcon,
	NcSelect,
} from '@nextcloud/vue'
// import { ref } from 'vue'
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { VueTabs, VTab } from 'vue-nav-tabs/dist/vue-tabs.js'
// import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'EstacionamientoSettings',
	components: {
		NcButton,
		NcSelect,
		NcLoadingIcon,
		VueTabs,
		VTab,
	},

	data() {
		return {
			loading: true,
			modal: false,
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
			this.fetchEspacios(),
		])
		this.loading = false
	},
	beforeUpdate() {
		// console.log('beforeUpdate')
	},
	updated() {
		// console.log('updated')
	},
	beforeUnmount() {
		// console.log('beforeUnmount')
	},
	unmounted() {
		// console.log('unmounted')
	},

	methods: {
		showModal() {
			this.modal = true
		},
		closeModal() {
			this.modal = false
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
					const resEspacios = await axios.get(generateUrl('apps/empleados/GetEspacios'))
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

/* Centered loading */
.center-screen {
  display: flex;
  justify-content: center;
  align-items: center;
  text-align: center;
  min-height: 100vh;
}
</style>
