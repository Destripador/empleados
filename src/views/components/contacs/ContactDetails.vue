<!-- eslint-disable object-curly-newline -->
<template>
	<div class="contacts-list__item-wrapper">
		<div v-if="Object.keys( data ).length == 0">
			<div class="emptycontent">
				<img src="../../../../img/crowesito-think.png" width="170px">
				<h2>Selecciona un empleado para empezar</h2>
			</div>
		</div>
		<div v-else class="container">
			<div>
				<div class="container-search-profile">
					<div class="button-container-profile">
						<NcActions>
							<template #icon>
								<AccountCog :size="20" />
							</template>
							<NcActionButton @click="showEdit">
								<template #icon>
									<AccountEdit :size="20" />
								</template>
								Habilitar edicion
							</NcActionButton>
							<NcActionSeparator />
							<NcActionButton @click="showEdit">
								<template #icon>
									<AccountEdit :size="20" />
								</template>
								Exportar
							</NcActionButton>
							<NcActionButton @click="showEdit">
								<template #icon>
									<AccountEdit :size="20" />
								</template>
								Mostrar archivos en explorador
							</NcActionButton>
							<NcActionButton @click="showEdit">
								<template #icon>
									<AccountEdit :size="20" />
								</template>
								Enviar correo
							</NcActionButton>
							<NcActionButton @click="showEdit">
								<template #icon>
									<AccountEdit :size="20" />
								</template>
								Deshablitar usuario
							</NcActionButton>
						</NcActions>
					</div>
				</div>
			</div>
			<div class="center">
				<NcAvatar :user="data.uid" :display-name="data.uid" :size="120" />
				<div v-if="data.displayname">
					<h2>{{ data.displayname }}</h2>
				</div>
				<div v-else>
					<h2>{{ data.uid }}</h2>
				</div>

				<div>
					<VueTabs
						active-tab-color="#fdb913c"
						active-text-color="white"
						type="pills"
						centered>
						<VTab title="Empleado">
							<!--div class="container-progress">
								<span>
									Documentacion
									<NcProgressBar :value="60" size="medium" />
								</span>
							</div-->

							<div v-if="show">
								<NcTextField
									:value="checknull(data.Numero_empleado)"
									:v-model="Edit.Numero_empleado"
									label="Numero_empleado">
									<Badgeaccountoutline class="margin-left-icon" :size="20" />
								</NcTextField>
								<NcTextField
									:value="checknull(data.Ingreso)"
									:v-model="Edit.Ingreso"
									label="Ingreso">
									<Calendarrange class="margin-left-icon" :size="20" />
								</NcTextField>
								<NcTextField
									:value="checknull(data.Id_departamento)"
									:v-model="Edit.Id_departamento"
									label="Id_departamento">
									<Briefcaseaccount class="margin-left-icon" :size="20" />
								</NcTextField>
								<NcTextField
									:value="checknull(data.Id_puesto)"
									:v-model="Edit.Id_puesto"
									label="Id_puesto">
									<Briefcaseaccount class="margin-left-icon" :size="20" />
								</NcTextField>
								<NcTextField
									:value="checknull(data.Id_gerente)"
									:v-model="Edit.Id_gerente"
									label="Id_gerente">
									<Accountmultiple class="margin-left-icon" :size="20" />
								</NcTextField>
								<NcTextField
									:value="checknull(data.Id_socio)"
									:v-model="Edit.Id_socio"
									label="Id_socio">
									<Accountgroup class="margin-left-icon" :size="20" />
								</NcTextField>
								<NcTextField
									:value="checknull(data.Fondo_clave)"
									:v-model="Edit.Fondo_clave"
									label="Fondo_clave">
									<Piggybankoutline class="margin-left-icon" :size="20" />
								</NcTextField>
								<NcTextField
									:value="checknull(data.Numero_cuenta)"
									:v-model="Edit.Numero_cuenta"
									label="Numero_cuenta">
									<Bank class="margin-left-icon" :size="20" />
								</NcTextField>
								<NcTextField
									:value="checknull(data.Equipo_asignado)"
									:v-model="Edit.Equipo_asignado"
									label="Equipo_asignado">
									<Laptopaccount class="margin-left-icon" :size="20" />
								</NcTextField>
								<NcTextField
									:value="checknull(data.Sueldo)"
									:v-model="Edit.Sueldo"
									label="Sueldo">
									<Cash class="margin-left-icon" :size="20" />
								</NcTextField>
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
												type="text"
												disabled="true"
												class="inputtype"
												:value="checknull(data.Id_departamento)">
										</div>

										<div>
											<label for="Id_puesto" class="labeltype">
												<Briefcaseaccount class="margin-left-icon" :size="20" />
												Puesto
											</label>
											<input id="Id_puesto"
												type="text"
												disabled="true"
												class="inputtype"
												:value="checknull(data.Id_puesto)">
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
															<div class="avatar-chart">
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
									:disabled="true"
									:value="checknull(data.Notas)" />
							</div>
						</VTab>

						<VTab title="Personal" />

						<VTab title="Archivos" />
					</VueTabs>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { VueTabs, VTab } from 'vue-nav-tabs/dist/vue-tabs.js'
import 'vue-nav-tabs/themes/vue-tabs.css'

import OrganizationChart from 'vue-organization-chart'

// ICONOS
// import Magnify from 'vue-material-design-icons/Magnify.vue'
import Badgeaccountoutline from 'vue-material-design-icons/BadgeAccountOutline.vue'
import Briefcaseaccount from 'vue-material-design-icons/BriefcaseAccount.vue'
import Piggybankoutline from 'vue-material-design-icons/PiggyBankOutline.vue'
import Accountmultiple from 'vue-material-design-icons/AccountMultiple.vue'
import Calendarrange from 'vue-material-design-icons/CalendarRange.vue'
import Laptopaccount from 'vue-material-design-icons/LaptopAccount.vue'
import Accountgroup from 'vue-material-design-icons/AccountGroup.vue'
import AccountEdit from 'vue-material-design-icons/AccountEdit.vue'
import AccountCog from 'vue-material-design-icons/AccountCog.vue'
import Bank from 'vue-material-design-icons/Bank.vue'
import Cash from 'vue-material-design-icons/Cash.vue'
// import Cog from 'vue-material-design-icons/Cog.vue'

import {
	NcAvatar,
	NcTextField,
	NcActions,
	NcActionButton,
	NcActionSeparator,
	NcTextArea,
	// NcProgressBar,
} from '@nextcloud/vue'

export default {
	name: 'ContactDetails',

	components: {
		NcAvatar,
		VueTabs,
		VTab,
		NcTextField,
		NcActionSeparator,
		NcActions,
		// Cog,
		Badgeaccountoutline,
		Calendarrange,
		Accountgroup,
		Briefcaseaccount,
		Accountmultiple,
		Bank,
		Piggybankoutline,
		Laptopaccount,
		Cash,
		AccountCog,
		AccountEdit,
		NcActionButton,
		OrganizationChart,
		NcTextArea,
	},

	props: {
		data: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			show: false,
			Edit: [],
		}
	},

	methods: {
		showEdit() {
			this.show = !this.show
		},

		generateChar(user, gerente, socio) {
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
			if (satanizar == null) {
				return ''
			}

			return satanizar
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
</style>
