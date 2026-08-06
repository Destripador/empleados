<template>
	<div class="well">
		<div class="empleado-view-switch-wrapper">
			<div class="empleado-view-switch">
				<button
					type="button"
					class="empleado-switch-btn"
					:class="{ active: viewMode === 'information' }"
					:aria-pressed="viewMode === 'information' ? 'true' : 'false'"
					@click="setViewMode('information')">
					{{ t('empleados', 'Information') }}
				</button>
				<button
					type="button"
					class="empleado-switch-btn"
					:class="{ active: viewMode === 'onboarding' }"
					:aria-pressed="viewMode === 'onboarding' ? 'true' : 'false'"
					@click="setViewMode('onboarding')">
					{{ t('empleados', 'Boarding') }}
				</button>
			</div>
		</div>

		<div class="empleado-content">
			<div v-if="viewMode === 'information'" class="top">
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
									<input
										id="Aniversario"
										:value="cargandoPeriodo ? '…' : Aniversario"
										type="text"
										disabled
										class="inputtype">
								</div>

								<!-- Vacation -->
								<div class="box1Inside">
									<label for="Vacaciones" class="labeltype">
										<BagSuitcase :size="20" />
										{{ t('empleados', 'Vacation') }}
									</label>
									<div class="stepper-wrapper">
										<div v-if="show" class="stepper-arrows">
											<button type="button"
												class="stepper-btn"
												:disabled="cargandoPeriodo"
												@click="incrementarVacaciones(1)">
												<ChevronUp :size="11" fill-color="currentColor" />
											</button>
											<button type="button"
												class="stepper-btn"
												:disabled="cargandoPeriodo"
												@click="incrementarVacaciones(-1)">
												<ChevronDown :size="11" fill-color="currentColor" />
											</button>
										</div>
										<input id="Vacaciones"
											v-model.number="Vacaciones"
											type="number"
											step="1"
											min="0"
											:disabled="!show || cargandoPeriodo"
											:placeholder="cargandoPeriodo ? '…' : ''"
											class="inputtype stepper-input">
									</div>
								</div>

								<!-- Save vacation days -->
								<div
									v-if="show"
									class="topRefresh MarginRight">
									<NcButton
										type="primary"
										:disabled="guardandoDias || cargandoPeriodo || String(Vacaciones) === String(diasDerechoOriginal)"
										@click="GuardarDiasDerecho()">
										<template #icon>
											<NcLoadingIcon v-if="guardandoDias" :size="20" />
											<ContentSaveOutline v-else :size="20" />
										</template>
										{{ t('empleados', 'Save') }}
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
						</div>
					</div>

					<div v-if="inventoryEnabled">
						<div class="divider">
							<span>{{ t('empleados', 'Systems') }}</span>
						</div>
						<div class="flexible">
							<div class="box1Inside equipo-asignado-field">
								<label for="Equipos_asignados" class="labeltype">
									<Laptopaccount :size="20" />
									{{ t('empleados', 'Assigned devices') }}
								</label>

								<NcSelect
									v-if="show && canModifyInventory"
									id="Equipos_asignados"
									v-model="Equipos_asignados"
									class="equipo-computo-select"
									:disabled="cargandoEquipos"
									:options="inventarioEquipos"
									:multiple="true"
									:close-on-select="false"
									track-by="id_equipo"
									label="label"
									:input-label="t('empleados', 'Assigned devices')"
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
											<div>
												<h1> {{ Equipo.label }} </h1>
											</div>
										</div>
									</template>
								</NcSelect>

								<p v-if="cargandoEquipos" class="assigned-equipment-empty">
									{{ t('empleados', 'Loading assigned equipment…') }}
								</p>
								<p v-else-if="errorEquipos" class="assigned-equipment-empty">
									{{ errorEquipos }}
								</p>
								<template v-else>
									<component
										:is="canNavigateAssignedEquipment ? 'button' : 'div'"
										v-for="equipoAsignado in Equipos_asignados"
										:key="equipoAsignado.id_equipo"
										class="assigned-equipment-card"
										:class="{ 'assigned-equipment-card--interactive': canNavigateAssignedEquipment }"
										:type="canNavigateAssignedEquipment ? 'button' : null"
										:title="canNavigateAssignedEquipment ? t('empleados', 'Open this device in IT Inventory') : null"
										:aria-label="canNavigateAssignedEquipment ? t('empleados', 'Open {device} in IT Inventory', { device: equipoOptionTitle(equipoAsignado) }) : null"
										@click="openAssignedEquipment(equipoAsignado)">
										<Laptopaccount :size="32" aria-hidden="true" />
										<div class="assigned-equipment-content">
											<div class="assigned-equipment-heading">
												<strong>{{ equipoOptionTitle(equipoAsignado) }}</strong>
												<span v-if="equipoAsignado.estado" class="equipo-status" :class="`equipo-status--${String(equipoAsignado.estado).toLowerCase()}`">
													{{ equipoAsignado.estado }}
												</span>
											</div>
											<span v-if="equipoAsignado.nombre_sistema">{{ t('empleados', 'System name') }}: {{ equipoAsignado.nombre_sistema }}</span>
											<span v-if="equipoAsignado.numero_serie">{{ t('empleados', 'Serial number') }}: {{ equipoAsignado.numero_serie }}</span>
											<span v-if="equipoModel(equipoAsignado)">{{ t('empleados', 'Model') }}: {{ equipoModel(equipoAsignado) }}</span>
											<span v-if="!canAccessInventory" class="assigned-equipment-note">{{ t('empleados', 'Inventory details are read-only for your account.') }}</span>
											<span v-else class="assigned-equipment-link-hint">{{ t('empleados', 'Open in IT Inventory') }}</span>
										</div>
									</component>
								</template>

								<p v-if="!cargandoEquipos && !errorEquipos && Equipos_asignados.length === 0" class="assigned-equipment-empty">
									{{ t('empleados', 'No equipment assigned.') }}
								</p>
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

			<div v-else class="top">
				<div class="boarding-header">
					<div class="divider boarding-divider">
						<span>{{ boardingOn === 1 ? t('empleados', 'OnBoarding') : t('empleados', 'OffBoarding') }}</span>
					</div>

					<div class="boarding-toggle-wrapper">
						<div class="boarding-toggle" :class="{ 'boarding-toggle--off': boardingOn === 0 }">
							<span class="boarding-toggle-thumb" aria-hidden="true" />
							<button
								type="button"
								class="boarding-toggle-btn"
								:class="{ active: boardingOn === 1 }"
								:aria-pressed="boardingOn === 1 ? 'true' : 'false'"
								:disabled="boardingLoading"
								@click="setBoardingOn(1)">
								{{ t('empleados', 'On') }}
							</button>
							<button
								type="button"
								class="boarding-toggle-btn"
								:class="{ active: boardingOn === 0 }"
								:aria-pressed="boardingOn === 0 ? 'true' : 'false'"
								:disabled="boardingLoading"
								@click="setBoardingOn(0)">
								{{ t('empleados', 'Off') }}
							</button>
						</div>
					</div>
				</div>

				<NcEmptyContent v-if="boardingLoading" :name="t('empleados', 'Loading')">
					<template #icon>
						<NcLoadingIcon :size="20" />
					</template>
				</NcEmptyContent>

				<template v-else>
					<ul v-if="boardingItemsFiltered.length" class="onboarding-checklist">
						<li
							v-for="item in boardingItemsFiltered"
							:key="item.id_empleado_boarding"
							class="onboarding-item"
							:class="{ 'onboarding-item--done': isChecked(item) }">
							<NcCheckboxRadioSwitch
								:checked="isChecked(item)"
								:disabled="boardingSavingId === item.id_empleado_boarding"
								@update:checked="value => toggleItemStatus(item, value)">
								{{ item.nombre }}
							</NcCheckboxRadioSwitch>
							<NcLoadingIcon v-if="boardingSavingId === item.id_empleado_boarding" :size="16" />
						</li>
					</ul>
					<p v-else class="boarding-empty">
						{{ t('empleados', 'No items in this checklist yet.') }}
					</p>
				</template>
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
import permissionsMixin from '../../../../mixins/permissions.js'

// ICONOS
import Badgeaccountoutline from 'vue-material-design-icons/BadgeAccountOutline.vue'
import Piggybankoutline from 'vue-material-design-icons/PiggyBankOutline.vue'
import Calendarrange from 'vue-material-design-icons/CalendarRange.vue'
import Laptopaccount from 'vue-material-design-icons/LaptopAccount.vue'
import BagSuitcase from 'vue-material-design-icons/BagSuitcase.vue'
import PartyPopper from 'vue-material-design-icons/PartyPopper.vue'
import Bank from 'vue-material-design-icons/Bank.vue'
import Cash from 'vue-material-design-icons/Cash.vue'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import AccountArrowRightOutline from 'vue-material-design-icons/AccountArrowRightOutline.vue'
import AccountArrowLeftOutline from 'vue-material-design-icons/AccountArrowLeftOutline.vue'

import {
	NcAvatar,
	NcButton,
	NcSelect,
	NcListItem,
	NcCheckboxRadioSwitch,
	NcLoadingIcon,
	NcEmptyContent,
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
		Piggybankoutline,
		Laptopaccount,
		Cash,
		ContentSaveOutline,
		ChevronUp,
		ChevronDown,
		AccountArrowRightOutline,
		AccountArrowLeftOutline,
		NcLoadingIcon,
		NcEmptyContent,
		OrganizationChart,
		NcButton,
		NcSelect,
		NcListItem,
		NcCheckboxRadioSwitch,
	},

	mixins: [permissionsMixin],

	inject: {
		configuraciones: {
			default: () => ({}),
		},
	},

	props: {
		data: { type: Object, required: true },
		show: { type: Boolean, required: true },
		empleados: { type: Array, required: true },
		automaticsave: { type: String, required: true },
	},

	data() {
		return {
			viewMode: 'information',
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
			Equipos_asignados: [],
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
			cargandoEquipos: false,
			errorEquipos: '',
			diasDerechoOriginal: '',
			guardandoDias: false,
			cargandoPeriodo: false,
			// Checklist de OnBoarding / OffBoarding
			boardingOn: 1, // 1 = OnBoarding, 0 = OffBoarding
			boardingItems: [],
			boardingLoading: false,
			boardingInitialized: false,
			boardingSavingId: null,
		}
	},

	computed: {
		inventoryEnabled() {
			return this.isTruthy(this.configuraciones?.modulo_inventario)
		},
		canAccessInventory() {
			return this.canSee('inventario')
		},
		canModifyInventory() {
			return this.canSee('inventario.admin') || this.isAdminUser()
		},
		canNavigateAssignedEquipment() {
			return this.inventoryEnabled && this.canAccessInventory
		},
		boardingItemsFiltered() {
			return this.boardingItems.filter(item => Number(item.on) === this.boardingOn)
		},
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
					news.Sueldo,
					news.dias_disponibles,
					news.id_aniversario,
					news.state)

				await this.cargarPeriodoActual(news.Id_empleados)
				if (this.inventoryEnabled && this.canAccessInventory) {
					await this.getInventarioEquipos(news.Id_empleados)
				}

				// Nuevo empleado: el checklist anterior ya no aplica
				this.boardingItems = []
				this.boardingInitialized = false
				if (this.viewMode === 'onboarding') {
					this.boardingInitialized = true
					await this.cargarBoardingChecklist()
				}
			}
		},

		Ingreso: {
			handler(nuevaFecha) {
				if (!nuevaFecha || !this.show) return

				const años = this.calcularAniversarioDesdeFecha(nuevaFecha)
				if (años !== null) {
					this.Aniversario = años
				}
			},
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
			this.data.Sueldo,
			this.data.dias_disponibles,
			this.data.id_aniversario,
			this.data.state)

		await this.cargarPeriodoActual(this.data.Id_empleados)
		if (this.inventoryEnabled && this.canAccessInventory) {
			await this.getInventarioEquipos(this.data.Id_empleados)
		}
	},

	methods: {
		t,

		setViewMode(viewMode) {
			this.viewMode = viewMode
			if (viewMode === 'onboarding' && !this.boardingInitialized) {
				this.boardingInitialized = true
				this.cargarBoardingChecklist()
			}
		},

		setBoardingOn(on) {
			this.boardingOn = on
		},

		isChecked(item) {
			return Number(item.status) === 1
		},

		async cargarBoardingChecklist() {
			const idEmpleado = this.data?.Id_empleados
			if (!idEmpleado) return

			this.boardingLoading = true
			try {
				// Asegura que existan los registros del checklist a partir del catálogo (alta y baja)
				await Promise.all([
					axios.post(generateUrl('/apps/empleados/generarChecklistEmpleado'), { id_empleado: idEmpleado, on: 1 }),
					axios.post(generateUrl('/apps/empleados/generarChecklistEmpleado'), { id_empleado: idEmpleado, on: 0 }),
				])

				const response = await axios.post(generateUrl('/apps/empleados/getChecklistEmpleado'), {
					id_empleado: idEmpleado,
				})
				const data = response?.data?.ocs?.data
				this.boardingItems = Array.isArray(data) ? data : []
			} catch (err) {
				showError(t('empleados', 'No se pudo cargar el checklist [{error}]', { error: String(err), close: true }))
			} finally {
				this.boardingLoading = false
			}
		},

		async toggleItemStatus(item, checked) {
			const nuevoStatus = checked ? 1 : 0
			this.boardingSavingId = item.id_empleado_boarding
			try {
				await axios.post(generateUrl('/apps/empleados/marcarStatusBoarding'), {
					id_empleado_boarding: item.id_empleado_boarding,
					status: nuevoStatus,
				})
				item.status = nuevoStatus
			} catch (err) {
				showError(t('empleados', 'No se pudo actualizar el ítem [{error}]', { error: String(err), close: true }))
			} finally {
				this.boardingSavingId = null
			}
		},

		openAssignedEquipment(equipo) {
			if (!this.canNavigateAssignedEquipment) return
			this.$router.push({
				name: 'Inventario',
				query: { deviceId: String(equipo.id_equipo) },
			})
		},

		setAttr(NumeroEmpleado, Ingreso, Area, Puesto, Gerente, Socio, FondoClave, FondoAhorro, NumeroCuenta, Equipo, Sueldo, Vacaciones, Aniversario, state) {
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

		calcularAniversarioDesdeFecha(fechaStr) {
			if (!fechaStr) return null

			const partes = String(fechaStr).split('-')
			if (partes.length !== 3) return null

			// Construir en hora LOCAL (año, mes 0-indexado, día) — evita el shift de UTC
			const ingreso = new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]))
			if (Number.isNaN(ingreso.getTime())) return null

			const hoy = new Date()
			let años = hoy.getFullYear() - ingreso.getFullYear()
			const diffMeses = hoy.getMonth() - ingreso.getMonth()

			if (diffMeses < 0 || (diffMeses === 0 && hoy.getDate() < ingreso.getDate())) {
				años--
			}

			return Math.max(0, años)
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
					equipoasignado: '',
					equipo: this.Equipo.value,
					sueldo: this.checknull(this.Sueldo),
					id_aniversario: this.checknull(this.Aniversario),
					dias_disponibles: this.checknull(this.Vacaciones),
				})
				if (this.inventoryEnabled && this.canModifyInventory) {
					await this.sincronizarEquiposAtomico()
				}

				this.GetAllEquipo(this.Equipo.value)
				this.$bus.emit('getall')
				this.$bus.emit('show', false)
				showSuccess(t('empleados', 'Datos actualizados'), { close: true })
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepción [03] [{error}]', { error: String(err), close: true }))
			}
		},

		async sincronizarEquiposAtomico() {
			const deseados = this.Equipos_asignados.map(e => Number(e.id_equipo))

			await axios.put(generateUrl(`/apps/empleados/inventario/empleados/${this.data.Id_empleados}/equipos`), {
				equipos: deseados,
			})

			await this.getInventarioEquipos(this.data.Id_empleados)
			showSuccess(t('empleados', 'Equipment assigned successfully'), { close: true })
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
		async getInventarioEquipos(idEmpleado) {
			if (!this.inventoryEnabled || !this.canAccessInventory) return
			this.cargandoEquipos = true
			this.errorEquipos = ''
			try {
				const [optionsResponse, assignedResponse] = await Promise.all([
					axios.get(generateUrl('/apps/empleados/GetInventarioEquiposSelect'), {
						params: { employee: idEmpleado, onlyAvailable: true },
					}),
					axios.get(generateUrl(`/apps/empleados/inventario/empleados/${idEmpleado}/equipos`)),
				])

				const data = this.normalizeInventarioEquiposResponse(optionsResponse)

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

				this.Equipos_asignados = this.normalizeInventarioEquiposResponse(assignedResponse, 'equipos')
					.map(equipo => this.findInventarioEquipo(equipo.id_equipo) || equipo)
			} catch (err) {
				this.errorEquipos = t('empleados', 'Could not load assigned equipment')
				showError(this.errorEquipos)
			} finally {
				this.cargandoEquipos = false
			}
		},

		normalizeInventarioEquiposResponse(response, key = 'data') {
			const payload = response?.data?.ocs?.data || response?.data || response

			if (Array.isArray(payload)) {
				return payload
			}

			if (Array.isArray(payload?.[key])) {
				return payload[key]
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
		equipoModel(equipo) {
			return [equipo.marca, equipo.modelo].filter(Boolean).join(' ')
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

		async GuardarDiasDerecho() {
			if (String(this.Vacaciones) === String(this.diasDerechoOriginal)) return

			this.guardandoDias = true
			try {
				await axios.post(generateUrl('/apps/empleados/AsignarDiasDerecho'), {
					id_empleado: this.data.Id_empleados,
					dias_disponibles: this.checknull(this.Vacaciones),
				})
				this.diasDerechoOriginal = this.Vacaciones
				showSuccess(t('empleados', 'Días de vacaciones asignados'), { close: true })
			} catch (err) {
				showError(t('empleados', 'No se pudieron asignar los días [{error}]', { error: String(err), close: true }))
			} finally {
				this.guardandoDias = false
			}
		},

		async cargarPeriodoActual(idEmpleado) {
			if (!idEmpleado) return

			this.cargandoPeriodo = true
			this.Aniversario = ''
			this.Vacaciones = ''

			try {
				const response = await axios.post(generateUrl('/apps/empleados/GetAusenciasByUser'), {
					id: idEmpleado,
				})
				const periodo = response?.data?.ocs?.data?.[0]

				if (periodo) {
					this.Aniversario = this.checknull(periodo.id_aniversario)
					this.Vacaciones = this.checknull(periodo.dias_disponibles)
					this.diasDerechoOriginal = this.Vacaciones
				}
			} catch (err) {
				showError(t('empleados', 'No se pudo cargar el periodo de vacaciones [{error}]', { error: String(err), close: true }))
			} finally {
				this.cargandoPeriodo = false
			}
		},

		incrementarVacaciones(delta) {
			const actual = Number(this.Vacaciones) || 0
			this.Vacaciones = Math.max(0, actual + delta)
		},
	},
}
</script>

<style>
.orgchart td {
	background-color: transparent;
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

.box-chart .orgchart-container {
	display: block;
	width: 100%;
	height: auto;
	overflow: visible;
	border: 0;
	background: transparent;
}

.box-chart .orgchart {
	min-height: 10px;
	background: transparent;
	background-image: none;
}

.box-chart .orgchart .node .title {
	box-sizing: border-box;
	width: 100%;
	height: auto;
	min-height: 32px;
	padding: 6px 10px;
	overflow: hidden;
	border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-size: 12px;
	font-weight: 700;
	line-height: 20px;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.box-chart .orgchart .node .content {
	box-sizing: border-box;
	width: 100%;
	height: auto;
	min-height: 82px;
	padding: 10px 12px;
	overflow: visible;
	border-color: var(--color-border);
	background: var(--color-main-background);
	color: var(--color-main-text);
	line-height: normal;
	white-space: normal;
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

.assigned-equipment-card {
	display: flex;
	width: 100%;
	margin-top: 12px;
	padding: 16px;
	gap: 14px;
	align-items: flex-start;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-main-text);
	text-align: left;
	grid-column: 2;
}

.assigned-equipment-card--interactive {
	cursor: pointer;
	transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
}

.assigned-equipment-card--interactive:hover {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
}

.assigned-equipment-card--interactive:focus-visible {
	border-color: var(--color-primary-element);
	box-shadow: 0 0 0 2px var(--color-primary-element);
	outline: none;
}

.assigned-equipment-content {
	display: flex;
	min-width: 0;
	flex: 1;
	flex-direction: column;
	gap: 4px;
}

.assigned-equipment-heading {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
}

.assigned-equipment-heading strong {
	overflow-wrap: anywhere;
	font-size: 15px;
}

.assigned-equipment-link-hint,
.assigned-equipment-note,
.assigned-equipment-empty {
	margin: 8px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.assigned-equipment-empty {
	grid-column: 2;
}

.assigned-equipment-link-hint {
	color: var(--color-primary-element);
	font-weight: 600;
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

.stepper-wrapper {
	display: flex;
	align-items: stretch;
	gap: 6px;
}

.stepper-input {
	padding-right: 12px;
	-moz-appearance: textfield;
}

.stepper-input::-webkit-outer-spin-button,
.stepper-input::-webkit-inner-spin-button {
	margin: 0;
	-webkit-appearance: none;
}

.stepper-arrows {
	display: flex;
	flex-direction: column;
	justify-content: center;
	gap: 2px;
	flex-shrink: 0;
}

/* Selector reforzado + !important para ganarle al botón default de Nextcloud */
.stepper-wrapper .stepper-arrows button.stepper-btn {
	display: flex !important;
	width: 20px !important;
	height: 16px !important;
	min-width: 0 !important;
	min-height: 0 !important;
	align-items: center;
	justify-content: center;
	padding: 0 !important;
	margin: 0 !important;
	border: 1px solid var(--color-border) !important;
	border-radius: 5px !important;
	background: var(--color-background-hover) !important;
	box-shadow: none !important;
	color: var(--color-text-maxcontrast);
	line-height: 0;
	cursor: pointer;
	transition: background-color 100ms ease, color 100ms ease, border-color 100ms ease;
}

.stepper-wrapper .stepper-arrows button.stepper-btn:disabled {
	cursor: not-allowed;
	opacity: 0.35;
}

.stepper-wrapper .stepper-arrows button.stepper-btn:hover:not(:disabled) {
	background: var(--color-primary-element-light) !important;
	border-color: var(--color-primary-element) !important;
	color: var(--color-primary-element);
}

.stepper-wrapper .stepper-arrows button.stepper-btn :deep(svg) {
	width: 11px !important;
	height: 11px !important;
	margin: 0 !important;
}

@media (max-width: 768px) {
	.assigned-equipment-card,
	.assigned-equipment-empty {
		grid-column: 1;
	}
}

.empleado-content {
	/* ya no necesita ser relative/flotante: el switch va arriba, centrado */
}

/* Switch Information / OnBoarding — arriba, centrado */
.empleado-view-switch-wrapper {
	display: flex;
	justify-content: center;
	margin-bottom: 8px;
}

.empleado-view-switch {
	display: inline-flex;
	gap: 2px;
	padding: 4px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 999px;
	box-shadow: 0 6px 20px rgba(15, 23, 42, 0.14);
}

.empleado-switch-btn,
.empleado-switch-btn:hover,
.empleado-switch-btn:focus,
.empleado-switch-btn:focus-visible,
.empleado-switch-btn:active {
	all: unset;
	box-sizing: border-box;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	padding: 7px 18px;
	border-radius: 999px;
	font-size: 12.5px;
	font-weight: 600;
	letter-spacing: 0.02em;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
}

.empleado-switch-btn:hover:not(.active) {
	color: var(--color-main-text);
}

.empleado-switch-btn.active,
.empleado-switch-btn.active:hover,
.empleado-switch-btn.active:focus,
.empleado-switch-btn.active:active {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text, #fff);
	box-shadow: 0 2px 10px rgba(52, 120, 246, 0.35);
}

@media (max-width: 600px) {
	.empleado-view-switch {
		width: 100%;
	}

	.empleado-switch-btn {
		flex: 1;
		padding: 7px 8px;
	}
}

/* Encabezado del checklist: título a la izquierda, toggle a la derecha */
.boarding-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	flex-wrap: nowrap;
}

.boarding-divider {
	flex: 1 1 auto;
	min-width: 0;
	margin: 22px 0 14px;
}

.boarding-toggle-wrapper {
	display: flex;
	flex-shrink: 0;
	padding-top: 34px;
}

/* Toggle deslizante, minimalista — todo forzado con !important porque
   los estilos globales de botón de Nextcloud (incluyendo :hover/:focus/:active)
   traen su propio background/box-shadow que si no, se cuela encima */
.boarding-toggle {
	position: relative;
	display: inline-flex !important;
	flex-shrink: 0;
	width: 76px !important;
	height: 20px !important;
	padding: 2px !important;
	margin: 0 !important;
	background: var(--color-background-darker, var(--color-background-hover)) !important;
	border: 1px solid var(--color-border) !important;
	border-radius: 999px !important;
	box-sizing: border-box;
}

.boarding-toggle-thumb {
	position: absolute;
	top: 2px;
	left: 2px;
	width: calc(50% - 2px);
	height: calc(100% - 4px);
	border-radius: 999px;
	background: var(--color-primary-element);
	transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
	pointer-events: none;
}

.boarding-toggle--off .boarding-toggle-thumb {
	transform: translateX(100%);
}

.boarding-toggle-btn,
.boarding-toggle-btn:hover,
.boarding-toggle-btn:focus,
.boarding-toggle-btn:focus-visible,
.boarding-toggle-btn:active {
	all: unset;
	position: relative;
	z-index: 1;
	box-sizing: border-box;
	display: flex !important;
	flex: 1 1 0;
	align-items: center;
	justify-content: center;
	height: 100% !important;
	min-height: 0 !important;
	padding: 0 !important;
	margin: 0 !important;
	background: transparent !important;
	border: 0 !important;
	border-radius: 999px !important;
	outline: 0 !important;
	box-shadow: none !important;
	font-size: 9.5px !important;
	font-weight: 700 !important;
	line-height: 1 !important;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	-webkit-appearance: none;
	appearance: none;
	transition: color 0.18s ease;
}

.boarding-toggle-btn:disabled {
	cursor: not-allowed;
	opacity: 0.6;
}

.boarding-toggle-btn:hover:not(:disabled):not(.active) {
	color: var(--color-main-text) !important;
}

.boarding-toggle-btn.active,
.boarding-toggle-btn.active:hover,
.boarding-toggle-btn.active:focus {
	color: var(--color-primary-element-text, #fff) !important;
}

/* Checklist funcional */
.onboarding-checklist {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 0;
	margin: 4px 0 0;
	list-style: none;
}

.onboarding-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
	padding: 4px 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-main-text);
	font-size: 14px;
	transition: background-color 120ms ease, border-color 120ms ease;
}

.onboarding-item--done {
	background: var(--color-background-dark);
	border-color: var(--color-border);
}

.onboarding-item--done :deep(.checkbox-radio-switch__label) {
	color: var(--color-text-maxcontrast);
	text-decoration: line-through;
}

.boarding-empty {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

@media (max-width: 600px) {
	.boarding-header {
		flex-direction: column;
		align-items: stretch;
	}

	.boarding-toggle-wrapper {
		align-self: flex-end;
		padding-top: 0;
		margin-top: 8px;
	}
}
</style>
