<template>
	<div class="contacts-list__item-wrapper">
		<div v-if="Object.keys( data ).length == 0">
			<div class="emptycontent">
				<img src="../../../../img/crowesito-think.png" width="170px">
				<h2>Selecciona un empleado para empezar</h2>
			</div>
		</div>
		<div v-else class="container">
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
									:value="data.Numero_empleado"
									:v-model="Edit.Numero_empleado"
									label="Numero_empleado" />
								<NcTextField
									:value="data.Ingreso"
									:v-model="Edit.Ingreso"
									label="Ingreso" />
								<NcTextField
									:value="data.Id_departamento"
									:v-model="Edit.Id_departamento"
									label="Id_departamento" />
								<NcTextField
									:value="data.Id_puesto"
									:v-model="Edit.Id_puesto"
									label="Id_puesto" />
								<NcTextField
									:value="data.Id_gerente"
									:v-model="Edit.Id_gerente"
									label="Id_gerente" />
								<NcTextField
									:value="data.Id_socio"
									:v-model="Edit.Id_socio"
									label="Id_socio" />
								<NcTextField
									:value="data.Fondo_clave"
									:v-model="Edit.Fondo_clave"
									label="Fondo_clave" />
								<NcTextField
									:value="data.Numero_cuenta"
									:v-model="Edit.Numero_cuenta"
									label="Numero_cuenta" />
								<NcTextField
									:value="data.Equipo_asignado"
									:v-model="Edit.Equipo_asignado"
									label="Equipo_asignado" />
								<NcTextField
									:value="data.Sueldo"
									:v-model="Edit.Sueldo"
									label="Sueldo" />
							</div>
							<div v-else class="top">
								<div>
									<label for="Numero_empleado" class="labeltype">Numero de empleado</label>
									<input id="Numero_empleado"
										type="text"
										disabled="true"
										class="inputtype"
										:value="data.Numero_empleado">
								</div>

								<div>
									<label for="Ingreso" class="labeltype">Fecha de Ingreso</label>
									<input id="Ingreso"
										type="text"
										disabled="true"
										class="inputtype"
										:value="data.Ingreso">
								</div>

								<div>
									<label for="Id_departamento" class="labeltype">Departamento</label>
									<input id="Id_departamento"
										type="text"
										disabled="true"
										class="inputtype"
										:value="data.Id_departamento">
								</div>

								<div>
									<label for="Id_puesto" class="labeltype">Puesto</label>
									<input id="Id_puesto"
										type="text"
										disabled="true"
										class="inputtype"
										:value="data.Id_puesto">
								</div>

								<div>
									<label for="Id_gerente" class="labeltype">Gerente</label>
									<input id="Id_gerente"
										type="text"
										disabled="true"
										class="inputtype"
										:value="data.Id_gerente">
								</div>

								<div>
									<label for="Id_socio" class="labeltype">Socio</label>
									<input id="Id_socio"
										type="text"
										disabled="true"
										class="inputtype"
										:value="data.Id_socio">
								</div>

								<div>
									<label for="Fondo_clave" class="labeltype">Fondo clave</label>
									<input id="Fondo_clave"
										type="text"
										disabled="true"
										class="inputtype"
										:value="data.Fondo_clave">
								</div>

								<div>
									<label for="Numero_cuenta" class="labeltype">Numero cuenta bancaria</label>
									<input id="Numero_cuenta"
										type="text"
										disabled="true"
										class="inputtype"
										:value="data.Numero_cuenta">
								</div>

								<div>
									<label for="Equipo_asignado" class="labeltype">Equipo asignado</label>
									<input id="Equipo_asignado"
										type="text"
										disabled="true"
										class="inputtype"
										:value="data.Equipo_asignado">
								</div>

								<div>
									<label for="Sueldo" class="labeltype">Sueldo</label>
									<input id="Sueldo"
										type="text"
										disabled="true"
										class="inputtype"
										:value="data.Sueldo">
								</div>
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

import {
	NcAvatar,
	NcTextField,
	// NcProgressBar,
} from '@nextcloud/vue'

export default {
	name: 'ContactDetails',

	components: {
		NcAvatar,
		// NcProgressBar,
		VueTabs,
		VTab,
		NcTextField,
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
	display: inline-block;
	text-align: left;
	width: 30%;
}

.top{
	margin-top: 20px;
}
</style>
