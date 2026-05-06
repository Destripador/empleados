<template>
	<div class="well">
		<div class="top">
			<div class="main">
				<div class="box1">
					<div>
						<div class="divider">
							<span>{{ t('empleados', 'Work information') }}</span>
						</div>
						<div class="flexible">
							<!-- Employee number -->
							<div class="box1Inside">
								<label for="Numero_empleado" class="labeltype">
									<Badgeaccountoutline :size="20" />
									{{ t('empleados', 'Employee No.') }}
								</label>
								<input id="Numero_empleado"
									v-model="Numero_empleado"
									type="text"
									:disabled="!show"
									class="inputtype">
							</div>

							<!-- Salary -->
							<div class="box1Inside">
								<label for="Sueldo" class="labeltype">
									<Cash :size="20" />
									{{ t('empleados', 'Salary') }}
								</label>
								<input id="Sueldo"
									v-model="Sueldo"
									type="text"
									:disabled="!show"
									class="inputtype">
							</div>

							<!-- Bank account -->
							<div class="box1Inside">
								<label for="Numero_cuenta" class="labeltype">
									<Bank :size="20" />
									{{ t('empleados', 'Bank account') }}
								</label>
								<input id="Numero_cuenta"
									v-model="Numero_cuenta"
									type="text"
									:disabled="!show"
									class="inputtype">
							</div>
						</div>
						<div class="flexible top">
							<!-- Start date -->
							<div class="box1Inside">
								<label for="Ingreso" class="labeltype">
									<Calendarrange :size="20" />
									{{ t('empleados', 'Start date') }}
								</label>
								<input id="Ingreso"
									v-model="Ingreso"
									type="date"
									:disabled="!show"
									class="inputtype">
							</div>

							<!-- Anniversary -->
							<div class="box1Inside">
								<label for="Aniversario" class="labeltype">
									<PartyPopper :size="20" />
									{{ t('empleados', 'Anniversary') }}
								</label>
								<input id="Aniversario"
									v-model="Aniversario"
									type="text"
									:disabled="!show"
									class="inputtype">
							</div>

							<!-- Vacation -->
							<div class="box1Inside">
								<label for="Vacaciones" class="labeltype">
									<BagSuitcase :size="20" />
									{{ t('empleados', 'Vacation') }}
								</label>
								<input id="Vacaciones"
									v-model="Vacaciones"
									type="text"
									:disabled="!show"
									class="inputtype">
							</div>

							<!-- Calculate vacations -->
							<div
								v-if="Ingreso && (Aniversario == 0 || !Aniversario) && (!Vacaciones || Vacaciones == 0.00)"
								class="topRefresh MarginRight">
								<NcButton
									type="primary"
									:disabled="!show"
									@click="CalcularVacaciones()">
									<template #icon>
										<Refresh :size="20" />
									</template>
									{{ t('empleados', 'Calculate') }}
								</NcButton>
							</div>
						</div>
					</div>

					<div>
						<div class="divider">
							<span>{{ t('empleados', 'Savings fund') }}</span>
						</div>
						<div class="flexible">
							<div class="box1Inside">
								<label for="Fondo_clave" class="labeltype">
									<Piggybankoutline :size="20" />
									{{ t('empleados', 'Fund key') }}
								</label>
								<input id="Fondo_clave"
									v-model="Fondo_clave"
									type="text"
									:disabled="!show"
									class="inputtype">
							</div>

							<div class="box1Inside">
								<label for="Fondo_ahorro" class="labeltype">
									<Piggybankoutline :size="20" />
									{{ t('empleados', 'Savings fund') }}
								</label>
								<input id="Fondo_ahorro"
									v-model="Fondo_ahorro"
									type="text"
									:disabled="!show"
									class="inputtype">
							</div>

							<div class="topRefresh MarginRight">
								<NcCheckboxRadioSwitch
									v-model="state"
									type="switch">
									{{ state ? t('empleados', 'Can request') : t('empleados', 'Read-only mode') }}
								</NcCheckboxRadioSwitch>
							</div>
						</div>
					</div>

					<div>
						<div class="divider">
							<span>{{ t('empleados', 'Systems') }}</span>
						</div>
						<div class="flexible">
							<div class="box1Inside equipo-asignado-field">
								<label for="Equipo_asignado" class="labeltype">
									<Laptopaccount :size="20" />
									{{ t('empleados', 'Assigned equipment') }}
								</label>

								<NcSelect
									id="Equipo_asignado"
									v-model="Equipo_asignado"
									class="equipo-computo-select"
									:disabled="!show"
									:options="inventarioEquipos"
									:input-label="t('empleados', 'Assigned equipment')"
									:label-outside="true"
									:placeholder="t('empleados', 'Select assigned equipment')">
									<template #selected-option="option">
										<div class="equipo-selected-option">
											<strong>{{ equipoOptionTitle(option) }}</strong>
											<span>{{ equipoOptionSubtitle(option) }}</span>
										</div>
									</template>

									<template #option="option">
										<div class="equipo-dropdown-option">
											<div class="equipo-dropdown-main">
												<strong>{{ equipoOptionTitle(option) }}</strong>
												<span
													v-if="option.estado"
													class="equipo-status"
													:class="`equipo-status--${String(option.estado).toLowerCase()}`">
													{{ option.estado }}
												</span>
											</div>

											<div class="equipo-dropdown-subtitle">
												{{ equipoOptionSubtitle(option) }}
											</div>
										</div>
									</template>
								</NcSelect>
							</div>
						</div>
					</div>
				</div>

				<div class="box2">
					<div class="divider">
						<span>{{ t('empleados', 'Employment structure') }}</span>
					</div>

					<div>
						<!-- Organization Chart -->
						<div class="box2" :style="show ? { display: 'none' } : {}">
							<div class="box-chart">
								<OrganizationChart :datasource="generateChar(data.uid, gerente, socio)">
									<template slot-scope="{ nodeData }">
										<div class="title">
											{{ nodeData.title }}
										</div>
										<div class="content">
											<div class="center">
												<div class="avatar-chart mini-top">
													<NcAvatar v-if="nodeData.name == '?'"
														display-name="?"
														:size="40" />
													<NcAvatar v-else
														:user="nodeData.name"
														:display-name="nodeData.name"
														:size="40" />
												</div>
												<div class="name-chart">
													{{ nodeData.name }}
												</div>
											</div>
										</div>
									</template>
								</OrganizationChart>
							</div>
						</div>

						<!-- Department and Position -->
						<div class="main">
							<div class="label-input-trabajo">
								<NcSelect id="Id_departamento"
									v-model="area"
									class="container__select"
									:disabled="!show"
									:options="optionsarea"
									:input-label="t('empleados','Department')" />
							</div>

							<div class="label-input-trabajo">
								<NcSelect id="Id_puesto"
									v-model="puesto"
									class="container__select_puesto"
									:disabled="!show"
									:options="optionspuesto"
									:input-label="t('empleados','Position')" />
							</div>
						</div>

						<!-- Partner and Manager -->
						<div v-if="show" class="main">
							<div class="label-input-trabajo">
								<NcSelect v-model="socio"
									class="select"
									:disabled="!show"
									:options="EmpleadosList"
									:user-select="true"
									:input-label="t('empleados','Partner')" />
							</div>

							<div class="label-input-trabajo">
								<NcSelect v-model="gerente"
									class="select"
									:disabled="!show"
									:options="EmpleadosList"
									:user-select="true"
									:input-label="t('empleados','Manager')" />
							</div>
						</div>

						<!-- Team -->
						<div v-if="show" class="main">
							<div class="label-input-puesto">
								<NcSelect v-model="Equipo"
									class="select"
									:disabled="!show"
									:options="optionsequipos"
									:input-label="t('empleados','Team')" />
							</div>
						</div>
						<div v-else class="">
							<div v-if="!Equipo == '' || !Equipo == null">
								<div class="rst-title">
									<div class="title_flex">
										<div class="subtitle_flex">
											<NcAvatar :user="Equipo.jefe" :display-name="Equipo.jefe" :size="20" />
										</div>
										<div>
											<h1> {{ Equipo.label }} </h1>
										</div>
									</div>
								</div>
								<div class="rst">
									<ul class="team-list">
										<NcListItem
											v-for="(item) in peopleEquipo.equipo"
											:key="item.Id_empleados"
											:name="item.displayname ? item.displayname : item.Id_user"
											@click.prevent="showDetails(item)">
											<template #icon>
												<NcAvatar disable-menu
													:size="44"
													:user="item.Id_user"
													:display-name="item.Id_user" />
											</template>
										</NcListItem>
									</ul>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<br>
			<div class="div-center">
				<NcButton
					v-if="show"
					aria-label="Guardar"
					type="primary"
					@click="CambiosEmpleado()">
					{{ t('empleados', 'Apply changes') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import OrganizationChart from 'vue-organization-chart'
import { generateUrl } from '@nextcloud/router'
import 'vue-nav-tabs/themes/vue-tabs.css'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

// ICONOS
import Badgeaccountoutline from 'vue-material-design-icons/BadgeAccountOutline.vue'
import Piggybankoutline from 'vue-material-design-icons/PiggyBankOutline.vue'
import Calendarrange from 'vue-material-design-icons/CalendarRange.vue'
import Laptopaccount from 'vue-material-design-icons/LaptopAccount.vue'
import BagSuitcase from 'vue-material-design-icons/BagSuitcase.vue'
import PartyPopper from 'vue-material-design-icons/PartyPopper.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Bank from 'vue-material-design-icons/Bank.vue'
import Cash from 'vue-material-design-icons/Cash.vue'

import {
	NcAvatar,
	NcButton,
	NcSelect,
	NcListItem,
	NcCheckboxRadioSwitch,
} from '@nextcloud/vue'

export default {
	name: 'EmpleadoTab',

	components: {
		NcAvatar,
		Badgeaccountoutline,
		Calendarrange,
		Bank,
		PartyPopper,
		BagSuitcase,
		Refresh,
		Piggybankoutline,
		Laptopaccount,
		Cash,
		OrganizationChart,
		NcButton,
		NcSelect,
		NcListItem,
		NcCheckboxRadioSwitch,
	},

	props: {
		data: { type: Object, required: true },
		show: { type: Boolean, required: true },
		empleados: { type: Array, required: true },
		automaticsave: { type: String, required: true },
	},

	data() {
		return {
			area: '',
			puesto: '',
			gerente: null,
			socio: null,
			optionsarea: [],
			optionspuesto: [],
			optionsequipos: [],
			Numero_empleado: '',
			Ingreso: '',
			Fondo_clave: '',
			Fondo_ahorro: '',
			Numero_cuenta: '',
			Equipo_asignado: '',
			Sueldo: '',
			Equipo: '',
			areaSend: '',
			puestoSend: '',
			EquipoSend: '',
			peopleEquipo: {},
			EmpleadosList: [],
			Aniversario: '',
			Vacaciones: '',
			state: false,
			inventarioEquipos: [],
		}
	},

	watch: {
		// FIX: la firma correcta es (newVal, oldVal)
		state(newVal, oldVal) {
			// Solo enviar si realmente cambió
			if (newVal !== oldVal) {
				// true => '1' (puede solicitar), false => '0' (solo lectura)
				this.cambioEstado(newVal ? '1' : '0')
			}
		},
		async data(news) {
			if (news) {
				this.setAttr(
					news.Numero_empleado,
					news.Ingreso,
					news.Id_departamento,
					news.Id_puesto,
					news.Id_gerente,
					news.Id_socio,
					news.Fondo_clave,
					news.Fondo_ahorro,
					news.Numero_cuenta,
					news.Id_equipo,
					news.Equipo_asignado,
					news.Sueldo,
					news.dias_disponibles,
					news.id_aniversario,
					news.state)

				await this.getInventarioEquipos(news.Equipo_asignado)
			}
		},
	},

	async mounted() {
		this.EmpleadosList = this.empleados.map(empleados => ({
			id: empleados.Id_user,
			displayName: empleados.displayname ? empleados.displayname : empleados.Id_user,
			isNoUser: false,
			icon: '',
			user: empleados.Id_user,
		}))

		this.setAttr(
			this.data.Numero_empleado,
			this.data.Ingreso,
			this.data.Id_departamento,
			this.data.Id_puesto,
			this.data.Id_gerente,
			this.data.Id_socio,
			this.data.Fondo_clave,
			this.data.Fondo_ahorro,
			this.data.Numero_cuenta,
			this.data.Id_equipo,
			this.data.Equipo_asignado,
			this.data.Sueldo,
			this.data.dias_disponibles,
			this.data.id_aniversario,
			this.data.state)

		await this.getInventarioEquipos(this.data.Equipo_asignado)
	},

	methods: {
		t,

		setAttr(NumeroEmpleado, Ingreso, Area, Puesto, Gerente, Socio, FondoClave, FondoAhorro, NumeroCuenta, Equipo, EquipoAsignado, Sueldo, Vacaciones, Aniversario, state) {
			this.Numero_empleado = this.checknull(NumeroEmpleado)
			this.Ingreso = this.checknull(Ingreso)
			this.area = Area
			this.puesto = Puesto
			this.gerente = this.checknull(Gerente)
			this.socio = this.checknull(Socio)
			this.Fondo_clave = this.checknull(FondoClave)
			this.Fondo_ahorro = this.checknull(FondoAhorro)
			this.Numero_cuenta = this.checknull(NumeroCuenta)
			this.Equipo = this.checknull(Equipo)
			this.Equipo_asignado = this.checknull(EquipoAsignado)
			this.Sueldo = this.checknull(Sueldo)
			this.Vacaciones = this.checknull(Vacaciones)
			this.Aniversario = this.checknull(Aniversario)

			// Mapeo de estado: '1' = puede solicitar; '0'/'2' = solo lectura
			if (state === '0' || state === '2') {
				this.state = false
			} else if (state === '1') {
				this.state = true
			}

			this.getAreas(this.area)
			this.getPuestos(this.puesto)
			this.getEquipos(this.Equipo)
		},

		async getAreas(Area) {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetAreasFix'))
				this.optionsarea = response?.data?.ocs?.data
				if (Area && Area.length !== 0) {
					this.area = this.optionsarea.find(areas => areas.value === parseInt(Area)).label
				} else {
					this.area = ''
				}
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepción [01] [{error}]', { error: String(err), close: true }))
			}
		},

		async getPuestos(Puesto) {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetPuestosFix'))
				this.optionspuesto = response?.data?.ocs?.data
				if (Puesto && Puesto.length !== 0) {
					this.puesto = this.optionspuesto.find(role => role.value === parseInt(Puesto)).label
				} else {
					this.puesto = ''
				}
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepción [01] [{error}]', { error: String(err), close: true }))
			}
		},

		async getEquipos(Equipo) {
			this.loading = false
			this.GetAllEquipo(Equipo)
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetEquiposList'))
				const data = response?.data?.ocs?.data
				this.optionsequipos = data.map(equipo => ({
					value: equipo.Id_equipo,
					label: equipo.Nombre,
					jefe: equipo.Id_jefe_equipo,
				}))
				if (Equipo && Equipo.length !== 0) {
					this.Equipo = this.optionsequipos.find(role => role.value === parseInt(Equipo))
				} else {
					this.Equipo = ''
				}
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepción [01] [{error}]', { error: String(err), close: true }))
			}
		},

		async GetAllEquipo(equipo) {
			try {
				if (equipo !== '' || equipo !== null || equipo !== undefined) {
					const response = await axios.get(generateUrl('/apps/empleados/GetEmpleadosEquipo/' + equipo))
					const data = response?.data?.ocs?.data
					this.peopleEquipo = data
				}
			} catch (err) {
				// eslint-disable-next-line no-console
				console.log(err)
			}
		},

		generateChar(user, gerente, socio) {
			if (!gerente) gerente = '?'
			if (!socio) socio = '?'
			return {
				id: 'nodo-oculto',
				children: [
					{ id: '1', name: socio, title: t('empleados', 'Boss') },
					{ id: '2', name: gerente, title: t('empleados', 'Manager') },
					{ id: '3', name: user, title: t('empleados', 'Employee') },
				],
			}
		},

		checknull(value) {
			return value ?? ''
		},

		async CambiosEmpleado() {
			try {
				this.areaSend = this.area?.value
				this.puestoSend = this.puesto?.value

				if (!this.area?.value) {
					this.areaSend = this.optionsarea.find(role => role.label === this.area)?.value || ''
				}

				if (!this.puesto?.value) {
					this.puestoSend = this.optionspuesto.find(role => role.label === this.puesto)?.value || ''
				}

				this.socio = this.socio?.id || this.socio
				this.gerente = this.gerente?.id || this.gerente

				await axios.post(generateUrl('/apps/empleados/CambiosEmpleado'), {
					id_empleados: this.data.Id_empleados,
					numeroempleado: this.checknull(this.Numero_empleado),
					ingreso: this.checknull(this.Ingreso),
					area: this.checknull(this.areaSend),
					puesto: this.checknull(this.puestoSend),
					socio: this.socio,
					gerente: this.checknull(this.gerente),
					fondoclave: this.checknull(this.Fondo_clave),
					fondoahorro: this.checknull(this.Fondo_ahorro),
					numerocuenta: this.checknull(this.Numero_cuenta),
					equipoasignado: this.getEquipoAsignadoValue(),
					equipo: this.Equipo.value,
					sueldo: this.checknull(this.Sueldo),
					id_aniversario: this.checknull(this.Aniversario),
					dias_disponibles: this.checknull(this.Vacaciones),
				})
				this.GetAllEquipo(this.Equipo.value)
				this.$bus.emit('getall')
				this.$bus.emit('show', false)
				showSuccess(t('empleados', 'Datos actualizados'), { close: true })
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepción [03] [{error}]', { error: String(err), close: true }))
			}
		},

		async CalcularVacaciones() {
			try {
				const response = await axios.post(generateUrl('/apps/empleados/GetAniversarioByDate'), {
					ingreso: this.checknull(this.Ingreso),
				})
				this.Aniversario = response?.data?.ocs?.data[0]?.numero_aniversario
				this.Vacaciones = response?.data?.ocs?.data[0]?.dias
			} catch (err) {
				showError(t('empleados', 'No se pudo calcular las vacaciones, verifica tabla de aniversarios'), { close: true })
			}
		},

		async cambioEstado(state) {
			try {
				await axios.post(generateUrl('/apps/empleados/ActualizarEstadoAhorro'), {
					id_ahorro: this.data.id_ahorro,
					state,
				})
			} catch (err) {
				showError(err)
			}
		},
		showDetails(data) {
			// eslint-disable-next-line no-console
			console.log(data)
			this.$bus.emit('send-data', data)
			this.$bus.emit('show', false)
		},
		async getInventarioEquipos(currentEquipoId = null) {
			try {
				const current = currentEquipoId || this.getEquipoAsignadoValue()

				const response = await axios.get(generateUrl('/apps/empleados/GetInventarioEquiposSelect'), {
					params: {
						current,
						onlyAvailable: true,
					},
				})

				const data = this.normalizeInventarioEquiposResponse(response)

				this.inventarioEquipos = data.map(equipo => ({
					value: equipo.value || equipo.id_equipo,
					label: equipo.label || this.inventarioEquipoLabel(equipo),
					id_equipo: equipo.id_equipo || equipo.value,
					nombre_dispositivo: equipo.nombre_dispositivo || '',
					nombre_sistema: equipo.nombre_sistema || '',
					numero_serie: equipo.numero_serie || '',
					estado: equipo.estado || '',
					marca: equipo.marca || '',
					modelo: equipo.modelo || '',
					empleado_id: equipo.empleado_id || null,
					empleado_uid: equipo.empleado_uid || null,
				}))

				const selected = this.findInventarioEquipo(current)

				if (selected) {
					this.Equipo_asignado = selected
				} else if (current) {
					this.Equipo_asignado = {
						value: current,
						label: `${t('empleados', 'Assigned equipment')} #${current}`,
						id_equipo: current,
					}
				} else {
					this.Equipo_asignado = ''
				}
			} catch (err) {
				showError(t('empleados', 'No se pudo cargar el inventario de equipos [{error}]', {
					error: String(err),
					close: true,
				}))
			}
		},

		normalizeInventarioEquiposResponse(response) {
			const payload = response?.data?.ocs?.data || response?.data || response

			if (Array.isArray(payload)) {
				return payload
			}

			if (Array.isArray(payload?.data)) {
				return payload.data
			}

			if (Array.isArray(payload?.ocs?.data)) {
				return payload.ocs.data
			}

			if (Array.isArray(payload?.ocs?.data?.data)) {
				return payload.ocs.data.data
			}

			return []
		},

		inventarioEquipoLabel(equipo) {
			return [
				equipo.nombre_dispositivo,
				equipo.nombre_sistema,
				equipo.numero_serie,
				equipo.marca && equipo.modelo ? `${equipo.marca} ${equipo.modelo}` : '',
			]
				.filter(Boolean)
				.join(' - ')
		},

		findInventarioEquipo(value) {
			const id = typeof value === 'object' ? value.value : value

			return this.inventarioEquipos.find(equipo => {
				return String(equipo.value) === String(id)
			|| String(equipo.label) === String(id)
			}) || ''
		},
		getEquipoAsignadoValue() {
			if (!this.Equipo_asignado) {
				return ''
			}

			if (typeof this.Equipo_asignado === 'object') {
				return this.Equipo_asignado.value || ''
			}

			return this.Equipo_asignado
		},
		equipoOptionTitle(option) {
			if (!option) {
				return ''
			}

			return option.nombre_dispositivo
		|| option.nombre_sistema
		|| option.label
		|| `${t('empleados', 'Equipment')} #${option.value || option.id_equipo || ''}`
		},

		equipoOptionSubtitle(option) {
			if (!option) {
				return ''
			}

			return [
				option.nombre_sistema && option.nombre_sistema !== option.nombre_dispositivo
					? option.nombre_sistema
					: '',
				option.numero_serie ? `${t('empleados', 'Serial')}: ${option.numero_serie}` : '',
				option.marca || option.modelo
					? [option.marca, option.modelo].filter(Boolean).join(' ')
					: '',
			]
				.filter(Boolean)
				.join(' · ')
		},
	},
}
</script>

<style>
.well {
	background: var(--color-main-background);
}

.top {
	margin-top: 14px;
}

.main {
	display: flex;
	flex-wrap: wrap;
	gap: 18px;
	align-items: flex-start;
}

.box {
	display: flex;
}

.box1 {
	flex: 3 1 620px;
	min-width: 0;
	padding: 0 20px 0 0;
}

.box2 {
	flex: 2 1 360px;
	min-width: 320px;
}

.box1Inside {
	flex: 1 1 210px;
	min-width: 180px;
}

.flexible {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-start;
	gap: 14px;
}

.MarginRight {
	padding-right: 5px;
}

.labeltype {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	min-height: 24px;
	margin-bottom: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 600;
}

.labeltype .material-design-icon {
	color: var(--color-primary-element);
}

.inputtype {
	width: 100%;
	min-height: 40px;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 14px;
	transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
}

.inputtype:focus {
	border-color: var(--color-primary-element);
	box-shadow: 0 0 0 2px var(--color-primary-element-light);
	outline: none;
}

.inputtype:disabled {
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
	cursor: not-allowed;
	opacity: 1;
}

.inputtype:hover:not(:disabled) {
	border-color: var(--color-primary-element-light);
}

.divider {
	position: relative;
	margin: 22px 0 14px;
	text-align: left;
}

.divider::before {
	content: "";
	position: absolute;
	top: 50%;
	left: 0;
	width: 100%;
	height: 1px;
	background: var(--color-border);
	z-index: 0;
}

.divider span {
	position: relative;
	z-index: 1;
	display: inline-flex;
	padding: 0 12px 0 0;
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 15px;
	font-weight: 700;
}

.label-input-trabajo,
.label-input-puesto {
	display: grid;
	align-items: center;
	width: 100%;
	min-width: 0;
}

.label-input-puesto {
	margin-top: 2px;
}

.box-chart {
	margin: 2px 0 16px;
	overflow-x: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.box-chart .orgchart {
	min-height: 10px;
	background: transparent;
}

.box-chart .title {
	padding: 6px 10px;
	border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-size: 12px;
	font-weight: 700;
}

.box-chart .content {
	padding: 10px 12px;
	background: var(--color-main-background);
}

.center {
	text-align: center;
}

.avatar-chart {
	display: flex;
	justify-content: center;
}

.name-chart {
	max-width: 150px;
	margin-top: 6px;
	overflow: hidden;
	color: var(--color-main-text);
	font-size: 13px;
	font-weight: 600;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.topRefresh {
	display: flex;
	align-items: center;
	min-height: 40px;
	margin-top: 30px;
}

.rst-title {
	width: auto;
	margin-top: 20px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
	background: var(--color-background-hover);
}

.title_flex {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 10px;
	min-height: 48px;
	padding: 8px 12px;
}

.title_flex h1 {
	margin: 0;
	font-size: 16px;
	font-weight: 700;
	line-height: 1.3;
}

.subtitle_flex {
	display: flex;
	align-items: center;
	padding-top: 0;
	margin-right: 0;
}

.rst {
	padding: 4px 0;
	border: 1px solid var(--color-border);
	border-top: 0;
	border-radius: 0 0 var(--border-radius-large) var(--border-radius-large);
	background: var(--color-main-background);
}

.team-list {
	max-height: calc(30vh - 4rem);
	padding: 0;
	margin: 0;
	overflow-y: auto;
}

.div-center {
	display: flex;
	justify-content: center;
	margin: 22px 0 4px;
}

.wrapper {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	align-items: flex-end;
}

.external-label {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-top: 2px;
}

.labelEmpleado {
	display: inline-flex;
	align-items: center;
	min-width: 150px;
	gap: 5px;
	font-weight: bold;
}

.item {
	width: 100px;
	margin: 10px;
	border-radius: 8px;
	box-shadow: 0 2px 10px rgba(0, 41, 0, 0.12);
}

.float,
.inline-b {
	max-width: 1200px;
	margin: 0 auto;
}

.float:after {
	display: block;
	height: 0;
	clear: both;
	visibility: hidden;
	content: ".";
}

.float-item {
	float: left;
}

.inline-b-item {
	display: inline-block;
}

#nodo-oculto {
	display: none;
	height: 0;
	padding: 0;
	margin: 0;
}

@media (max-width: 768px) {
	.top {
		margin-top: 8px;
	}

	.main {
		gap: 12px;
	}

	.box1,
	.box2 {
		flex: 1 1 100%;
		min-width: 0;
		padding-right: 0;
	}

	.box1Inside {
		flex-basis: 100%;
		min-width: 0;
	}

	.topRefresh {
		width: 100%;
		margin-top: 4px;
	}
	.equipo-asignado-field {
		grid-template-columns: 1fr;
		max-width: none;
		min-width: 0;
	}

	.equipo-asignado-field .labeltype {
		margin-bottom: 6px;
	}
}
.equipo-asignado-field {
	display: grid;
	grid-template-columns: 190px minmax(320px, 1fr);
	align-items: center;
	column-gap: 18px;
	row-gap: 8px;
	flex: 1 1 100%;
	max-width: 720px;
	min-width: 320px;
}

.equipo-asignado-field .labeltype {
	margin-bottom: 0;
	justify-content: flex-start;
}

.equipo-computo-select .vs__dropdown-toggle {
	min-height: 44px;
	padding: 4px 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
}

.equipo-computo-select.vs--open .vs__dropdown-toggle,
.equipo-computo-select .vs__dropdown-toggle:focus-within {
	border-color: var(--color-primary-element);
	box-shadow: 0 0 0 2px var(--color-primary-element-light);
}

.equipo-computo-select.vs--disabled .vs__dropdown-toggle {
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
	cursor: not-allowed;
	opacity: 1;
}

.equipo-computo-select .vs__selected-options {
	min-width: 0;
	padding: 0;
}

.equipo-computo-select .vs__selected {
	display: flex;
	align-items: center;
	max-width: 100%;
	min-width: 0;
	margin: 0;
	padding: 0;
	color: var(--color-main-text);
}

.equipo-computo-select .vs__search {
	min-width: 0;
	margin: 0;
	padding: 0 4px;
	color: var(--color-main-text);
}

.equipo-computo-select .vs__actions {
	padding: 0 2px 0 8px;
}

.equipo-computo-select .vs__dropdown-menu {
	width: 100%;
	min-width: 420px;
	max-height: 320px;
	padding: 6px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
}

.equipo-computo-select .vs__dropdown-option {
	padding: 0;
	border-radius: var(--border-radius-large);
	color: var(--color-main-text);
}

.equipo-computo-select .vs__dropdown-option--highlight {
	background: var(--color-background-hover);
	color: var(--color-main-text);
}
.equipo-selected-option {
	display: flex;
	flex-direction: column;
	justify-content: center;
	min-width: 0;
	line-height: 1.25;
}

.equipo-selected-option strong {
	display: block;
	max-width: 100%;
	overflow: hidden;
	color: var(--color-main-text);
	font-size: 14px;
	font-weight: 700;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.equipo-selected-option span {
	display: block;
	max-width: 100%;
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.equipo-dropdown-option {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 10px 12px;
}

.equipo-dropdown-main {
	display: flex;
	gap: 8px;
	align-items: center;
	justify-content: space-between;
	min-width: 0;
}

.equipo-dropdown-main strong {
	overflow: hidden;
	color: var(--color-main-text);
	font-size: 14px;
	font-weight: 700;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.equipo-dropdown-subtitle {
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.equipo-status {
	flex-shrink: 0;
	padding: 2px 8px;
	border-radius: 999px;
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 11px;
	font-weight: 700;
	text-transform: capitalize;
}

.equipo-status--activo,
.equipo-status--asignado {
	background: var(--color-success);
	color: var(--color-primary-element-text);
}

.equipo-status--mantenimiento {
	background: var(--color-warning);
	color: var(--color-main-text);
}

.equipo-status--baja,
.equipo-status--inactivo {
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}
</style>
