<template>
	<div v-if="show">
		<br>
		<NcTextField
			:value.sync="Numero_empleado"
			label="Numero_empleado">
			<Badgeaccountoutline class="margin-left-icon" :size="20" />
		</NcTextField>
		<br>
		<NcTextField
			type="date"
			:value.sync="Ingreso"
			label="Ingreso">
			<Calendarrange class="margin-left-icon" :size="20" />
		</NcTextField>
		<br>

		<div class="row1">
			<div class="row2">
				<div>
					<NcSelect v-model="area"
						class="select"
						input-label="Area"
						:options="optionsarea" />
				</div>
				<div>
					<NcSelect v-model="puesto"
						class="select"
						input-label="Puesto"
						:options="optionspuesto" />
				</div>
			</div>
			<div class="row2">
				<div>
					<NcSelect v-model="socio"
						class="select"
						input-label="Socio"
						:options="empleados"
						:user-select="true" />
				</div>
				<div>
					<NcSelect v-model="gerente"
						class="select"
						input-label="Gerente"
						:options="empleados"
						:user-select="true" />
				</div>
			</div>
		</div>
		<br>

		<br>
		<NcTextField
			:value.sync="Fondo_clave"
			label="Fondo_clave">
			<Piggybankoutline class="margin-left-icon" :size="20" />
		</NcTextField>
		<br>
		<NcTextField
			:value.sync="Numero_cuenta"
			label="Numero_cuenta">
			<Bank class="margin-left-icon" :size="20" />
		</NcTextField>
		<br>
		<NcTextField
			:value.sync="Equipo_asignado"
			label="Equipo_asignado">
			<Laptopaccount class="margin-left-icon" :size="20" />
		</NcTextField>
		<br>
		<NcTextField
			:value.sync="Sueldo"
			label="Sueldo">
			<Cash class="margin-left-icon" :size="20" />
		</NcTextField>
		<br>
		<div class="div-center">
			<NcButton
				aria-label="Guardar"
				type="primary"
				@click="CambiosEmpleado()">
				Aplicar cambios
			</NcButton>
		</div>
		<br>
	</div>
	<div v-else class="top">
		<div class="box-chart">
			<div class="chart-child">
				<div>
					<label for="Numero_empleado" class="labeltype">
						<Badgeaccountoutline class="margin-left-icon" :size="20" />
						Numero de empleado
					</label>
					<input id="Numero_empleado"
						type="text"
						disabled="true"
						class="inputtype"
						:value="checknull(data.Numero_empleado)">
				</div>

				<div>
					<label for="Ingreso" class="labeltype">
						<Calendarrange class="margin-left-icon" :size="20" />
						Fecha de Ingreso
					</label>
					<input id="Ingreso"
						type="text"
						disabled="true"
						class="inputtype"
						:value="checknull(data.Ingreso)">
				</div>

				<div>
					<label for="Id_departamento" class="labeltype">
						<Briefcaseaccount class="margin-left-icon" :size="20" />
						Departamento
					</label>
					<input id="Id_departamento"
						v-model="area"
						type="text"
						disabled="true"
						class="inputtype">
				</div>

				<div>
					<label for="Id_puesto" class="labeltype">
						<Briefcaseaccount class="margin-left-icon" :size="20" />
						Puesto
					</label>
					<input id="Id_puesto"
						v-model="puesto"
						type="text"
						disabled="true"
						class="inputtype">
				</div>

				<div>
					<label for="Fondo_clave" class="labeltype">
						<Piggybankoutline class="margin-left-icon" :size="20" />
						Fondo clave
					</label>
					<input id="Fondo_clave"
						type="text"
						disabled="true"
						class="inputtype"
						:value="checknull(data.Fondo_clave)">
				</div>

				<div>
					<label for="Numero_cuenta" class="labeltype">
						<Bank class="margin-left-icon" :size="20" />
						Numero cuenta bancaria
					</label>
					<input id="Numero_cuenta"
						type="text"
						disabled="true"
						class="inputtype"
						:value="checknull(data.Numero_cuenta)">
				</div>

				<div>
					<label for="Equipo_asignado" class="labeltype">
						<Laptopaccount class="margin-left-icon" :size="20" />
						Equipo asignado
					</label>
					<input id="Equipo_asignado"
						type="text"
						disabled="true"
						class="inputtype"
						:value="checknull(data.Equipo_asignado)">
				</div>

				<div>
					<label for="Sueldo" class="labeltype">
						<Cash class="margin-left-icon" :size="20" />
						Sueldo
					</label>
					<input id="Sueldo"
						type="text"
						disabled="true"
						class="inputtype"
						:value="checknull(data.Sueldo)">
				</div>
			</div>
			<div>
				<div class="">
					<OrganizationChart :datasource="generateChar(data.uid, data.Id_gerente, data.Id_socio)">
						<template slot-scope="{ nodeData }">
							<div class="title">
								{{ nodeData.title }}
							</div>
							<div class="content">
								<div class="center">
									<div class="avatar-chart mini-top">
										<NcAvatar
											:user="nodeData.name"
											:display-name="nodeData.name"
											:size="40"
											:show-user-status="false" />
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
		</div>
		<br>
		<NcTextArea class="top"
			label="NOTAS EMPLEADO"
			resize="vertical"
			:disabled="show"
			:value.sync="inputValue" />
		<NcButton
			v-if="config === 'false'"
			aria-label="Guardar nota"
			type="primary"
			@click="guardarNota()">
			Guardar nota
		</NcButton>
		<br>
	</div>
</template>

<script>

import debounce from 'debounce'

import 'vue-nav-tabs/themes/vue-tabs.css'
import OrganizationChart from 'vue-organization-chart'

import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'

// ICONOS
// import Magnify from 'vue-material-design-icons/Magnify.vue'
import Badgeaccountoutline from 'vue-material-design-icons/BadgeAccountOutline.vue'
import Briefcaseaccount from 'vue-material-design-icons/BriefcaseAccount.vue'
import Piggybankoutline from 'vue-material-design-icons/PiggyBankOutline.vue'
import Calendarrange from 'vue-material-design-icons/CalendarRange.vue'
import Laptopaccount from 'vue-material-design-icons/LaptopAccount.vue'
import Bank from 'vue-material-design-icons/Bank.vue'
import Cash from 'vue-material-design-icons/Cash.vue'
// import Cog from 'vue-material-design-icons/Cog.vue'

import {
	NcAvatar,
	NcTextArea,
	NcTextField,
	NcButton,
	NcSelect,
} from '@nextcloud/vue'

export default {
	name: 'EmpleadoTab',

	components: {
		NcAvatar,
		// Cog,
		Badgeaccountoutline,
		Calendarrange,
		Briefcaseaccount,
		Bank,
		Piggybankoutline,
		Laptopaccount,
		Cash,
		OrganizationChart,
		NcTextArea,
		NcTextField,
		NcButton,
		NcSelect,
	},

	props: {
		data: {
			type: Object,
			required: true,
		},

		show: {
			type: Boolean,
			required: true,
		},

		empleados: {
			type: Array,
			required: true,
		},
		config: {
			type: String,
			required: true,
		},
	},

	data() {
		const { notas } = ''
		return {
			area: '',
			puesto: '',
			gerente: null,
			socio: null,
			notas: notas ?? '',
			optionsarea: [],
			optionspuesto: [],
			Numero_empleado: '',
			Ingreso: '',
			Fondo_clave: '',
			Numero_cuenta: '',
			Equipo_asignado: '',
			Sueldo: '',
			areaSend: '',
			puestoSend: '',
		}
	},

	computed: {
		inputValue: {
			get() {
				return this.notas
			},
			set(value) {
				this.debouncePropertyChange(value.trim())
			},
		},
		debouncePropertyChange() {
			return debounce(function(value) {
				this.notas = value
				if (this.config) {
					this.guardarNota()
				}
			}, 700)
		},
	},
	watch: {
		data() {
			this.notas = this.data.Notas
			this.getAreas()
			this.getPuestos()
		},
		show(news, olds) {
			this.getAreas()
			this.getPuestos()
			this.Numero_empleado = this.checknull(this.data.Numero_empleado)
			this.Ingreso = this.checknull(this.data.Ingreso)
			this.Fondo_clave = this.checknull(this.data.Fondo_clave)
			this.Numero_cuenta = this.checknull(this.data.Numero_cuenta)
			this.Equipo_asignado = this.checknull(this.data.Equipo_asignado)
			this.Sueldo = this.checknull(this.data.Sueldo)
			this.gerente = this.checknull(this.data.Id_gerente)
			this.socio = this.checknull(this.data.Id_socio)
		},
	},

	mounted() {
		this.getAreas()
		this.getPuestos()
		this.notas = this.data.Notas
		this.gerente = this.data.Id_gerente
		this.socio = this.data.Id_socio
	},

	methods: {
		async getAreas() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetAreasFix'))
					.then(
						(response) => {
							this.optionsarea = response.data
							// eslint-disable-next-line no-console
							console.log(this.data.Id_departamento.length)
							if (this.data.Id_departamento.length !== 0 || this.data.Id_departamento !== null) {
								this.area = this.optionsarea.find(role => role.value === parseInt(this.data.Id_departamento)).label
							} else {
								this.area = ''
							}
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [' + err + ']'))
			}
		},

		async getPuestos() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetPuestosFix'))
					.then(
						(response) => {
							this.optionspuesto = response.data
							if (this.data.Id_puesto.length !== 0 || this.data.Id_puesto !== null) {
								this.puesto = this.optionspuesto.find(role => role.value === parseInt(this.data.Id_puesto)).label
							} else {
								this.puesto = ''
							}
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [01] [' + err + ']'))
			}
		},

		generateChar(user, gerente, socio) {
			if (gerente === '' || gerente == null) {
				gerente = 'Sin Asignar'
			}
			if (socio === '' || socio == null) {
				socio = 'Sin asignar'
			}
			// eslint-disable-next-line comma-spacing
			return {
				id: '1',
				name: socio,
				title: 'Socio',
				children: [
					{
						id: '2',
						name: gerente,
						title: 'Gerente',
						children: [
							{ id: '3', name: user, title: 'Empleado' },
						],
					},
				],
			}
		},
		checknull(satanizar) {
			if (satanizar === null || !satanizar) {
				return ''
			}

			return satanizar
		},
		async guardarNota() {
			try {
				await axios.post(generateUrl('/apps/empleados/GuardarNota'),
					{
						id_empleados: this.data.Id_empleados,
						nota: this.notas,
					})
					.then(
						(response) => {
							showSuccess('Nota ha sido actualizada')
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [' + err + ']'))
			}
		},
		async CambiosEmpleado() {
			try {

				if (this.area.label) {
					this.areaSend = this.checknull(this.area.label)
				} else {
					this.areaSend = this.checknull(this.area)
				}

				if (this.puesto.label) {
					this.puestoSend = this.checknull(this.puesto.label)
				} else {
					this.puestoSend = this.checknull(this.puesto)
				}

				try {
					this.areaSend = this.optionsarea.find(role => role.label === this.areaSend).value
					this.puestoSend = this.optionspuesto.find(role => role.label === this.puestoSend).value
				} catch (err) {
					// eslint-disable-next-line no-console
					console.log(err)
				}

				await axios.post(generateUrl('/apps/empleados/CambiosEmpleado'),
					{
						id_empleados: this.data.Id_empleados,
						numeroempleado: this.checknull(this.Numero_empleado),
						ingreso: this.checknull(this.Ingreso),
						area: this.areaSend,
						puesto: this.puestoSend,
						socio: this.checknull(this.socio.id),
						gerente: this.checknull(this.gerente.id),
						fondoclave: this.checknull(this.Fondo_clave),
						numerocuenta: this.checknull(this.Numero_cuenta),
						equipoasignado: this.checknull(this.Equipo_asignado),
						sueldo: this.checknull(this.Sueldo),
					})
					.then(
						(response) => {
							this.$root.$emit('getall')
							this.$root.$emit('show', false)
							showSuccess('Datos actualizados')
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('empleados', 'Se ha producido una excepcion [03] [' + err + ']'))
			}
		},
	},
}
</script>
<style lang="scss" scoped>

.envelope {
	.app-content-list-item-icon {
		height: 40px; // To prevent some unexpected spacing below the avatar
	}

	&__subtitle {
		display: flex;
		gap: 4px;

		&__subject {
			color: var(--color-main-text);
			line-height: 130%;
			overflow: hidden;
			text-overflow: ellipsis;
		}
	}
}

.list-item-style {
	list-style: none;
}

</style>
<style lang="scss">
.contacts-list__item-wrapper {
	&[draggable='true'] .avatardiv * {
		cursor: move !important;
	}

	&[draggable='false'] .avatardiv * {
		cursor: not-allowed !important;
	}
}
#emptycontent, .emptycontent {
    margin-top: 2vh;
    }
.center-screen {
		display: flex;
		justify-content: center;
		align-items: center;
		text-align: center;
		min-height: 100vh;
}
.container {
		margin-right: 10px;
		margin-left: 10px;
		margin-top: 20px;
		align-items: center;
}
.container-progress {
	margin-right: 30%;
	margin-left: 30%;
	margin-top: 20px;
	align-items: center;
}
.wrapper {
	display: flex;
	gap: 4px;
	align-items: flex-end;
	flex-wrap: wrap;
	margin-right: 5%;
	margin-left: 5%;
}

.external-label {
	display: flex;
	width: 100%;
	margin-top: 1rem;
}

.external-label label {
	padding-top: 12px;
	padding-right: 14px;
	white-space: nowrap;
}

.contacts-list {
	max-height: calc(100vh - var(--header-height) - 48px);
	overflow: auto;
}

// Add empty header to contacts-list that solves overlapping of contacts with app-navigation-toogle
.contacts-list__header {
	min-height: 48px;
}

.inputtype{
    width: 60%;
}

.labeltype{
	display: inline-flex;
	text-align: left;
	width: 30%;
}

.top{
	margin-top: 20px;
}
.mini-top{
	margin-top: 5px;
}

.margin-left-icon{
	margin-right: 20px;
}

.button-container-profile{
	float: right;
	margin-top: -10px;
}
.rsg {
	padding-top: 16px;
	padding-bottom: 16px;
	border: 1px solid rgb(232, 232, 232);
	border-radius: 3px;
	display: flex;
	margin-left: 20px;
	margin-right: 20px;
	width: auto;
}
.box-chart{
  display: flex;
}
.chart-child{
	width: 90%;
}
.div-center{
	max-width: fit-content;
	margin-left: auto;
	margin-right: auto;
}
.row1{
	display: flex;
}
.row2{
	width: 50%;
}
.select {
  width: 95%;
}
</style>
