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
							<div class="box1Inside">
								<label for="Equipo_asignado" class="labeltype">
									<Laptopaccount :size="20" />
									{{ t('empleados', 'Assigned equipment') }}
								</label>
								<input id="Equipo_asignado"
									v-model="Equipo_asignado"
									type="text"
									:disabled="!show"
									class="inputtype">
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
									<ul style="max-height:  calc(30vh - 4rem); overflow-y: auto;">
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
		data(news) {
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
			}
		},
	},

	mounted() {
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
				if (equipo === '' || equipo === null || equipo === undefined) {
					showError(t('empleados', 'This employee doesn’t belong to a team — assign them to one.'), { close: true })
				} else {
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
					equipoasignado: this.checknull(this.Equipo_asignado),
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
	},
}
</script>

<style>
.box-chart { margin-top: 10px; }
.box{ display: flex; }
.box1 { flex: 3; padding-right: 2%; }
.box1Inside { flex: 3; }
.MarginRight { padding-right: 5px; }
.box2 { flex: 2; }
.main { display: flex; flex-wrap: wrap; }

/* Responsive */
@media (max-width: 768px) {
	.box1, .box2 { flex: 1 1 100%; }
}
@media (min-width: 769px) {
	.box1 { flex: 3; }
	.box2 { flex: 2; }
}

.label-input-trabajo { display: grid; align-items: center; width: 100%; }
.wrapper { display: flex; gap: 4px; align-items: flex-end; flex-wrap: wrap; }
.external-label { display: flex; align-items: center; gap: 10px; margin-top: 2px; }
.labelEmpleado { font-weight: bold; display: inline-flex; align-items: center; gap: 5px; min-width: 150px; }
.inputtype { flex: 1; height: 40px; padding: 8px 12px; font-size: 14px; border-radius: 5px; border: 1px solid #ccc; width: 100%; }

.divider { position: relative; text-align: center; margin: 1rem 0; }
.divider::before { content: ""; position: absolute; top: 50%; left: 0; width: 100%; height: 1px; background: #ccc; z-index: 0; }
.divider span { position: relative; background: #fff; padding: 0 1rem; z-index: 1; font-weight: 500; }

.label-input-puesto { display: grid; margin-top: 5px; align-items: center; width: 100%; }

.rst { padding-top: 5px; padding-bottom: 5px; border: 1px solid rgb(232, 232, 232); border-radius: 3px; }
.rst-title { background-color: rgba(240, 240, 240, 0.37); border: 1px solid rgb(232, 232, 232); border-radius: 3px; width: auto; margin-top: 20px; }
.item { box-shadow: rgba(0, 41, 0, 0.15) 0px 0px 11px 1px; width: 100px; margin: 10px; border-radius: 15px; }

.float { max-width: 1200px; margin: 0 auto; }
.float:after { content: "."; display: block; height: 0; clear: both; visibility: hidden; }
.float-item { float: left; }
.inline-b { max-width:1200px; margin:0 auto; }
.inline-b-item { display: inline-block; }
.title_flex { display: flex; justify-content: center; }
.subtitle_flex { padding-top: 5px; margin-right: 20px; }
#nodo-oculto { display: none; height: 0; padding: 0; margin: 0; }
.orgchart{ min-height: 10px; }
.flexible { display: flex; align-items: center; gap: 10px; }
.topRefresh { margin-top: 26px; }
</style>
