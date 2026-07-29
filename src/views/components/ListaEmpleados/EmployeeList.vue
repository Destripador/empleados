<template id="EmployeeList">
	<NcAppContent v-if="loadingProp" :name="t('empleados', 'Loading')">
		<NcEmptyContent class="empty-content" :name="t('empleados', 'Loading')">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>
	</NcAppContent>

	<NcAppContent v-else :name="t('empleados', 'Loading')">
		<!-- contacts list -->
		<template #list>
			<ContentList
				:employees="empleadosProp"
				:search-query="searchQuery" />
		</template>

		<!-- main contacts details -->
		<EmployeeDetails :data="data_empleado" :empleados-prop="empleadosProp" />

		<ContextAssistant
			v-if="mostrarAsistenteIa"
			scope="empleados-listado"
			:context="aiContext"
			:context-key="aiContextKey"
			:title="t('empleados', 'Asistente de empleados')"
			:suggestions="aiSuggestions" />
	</NcAppContent>
</template>

<script>
// agregados
import ContextAssistant from '../../../components/Ai/ContextAssistant.vue'
import ContentList from './ContentList.vue'
import EmployeeDetails from './EmployeeDetails.vue'

import {
	NcEmptyContent,
	NcAppContent,
	NcLoadingIcon,
} from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'EmployeeList',
	components: {
		NcEmptyContent,
		NcAppContent,
		NcLoadingIcon,
		ContentList,
		ContextAssistant,
		EmployeeDetails,
	},

	props: {
		empleadosProp: {
			type: Array,
			required: true,
		},
		loadingProp: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			searchQuery: '',
			data_empleado: {},
			aiContextVersion: 0,
		}
	},

	computed: {
		mostrarAsistenteIa() {
			return !this.loadingProp
				&& Array.isArray(this.empleadosProp)
				&& this.empleadosProp.length > 0
		},

		aiContextKey() {
			return [
				'empleados-listado',
				this.aiContextVersion,
				this.empleadosProp.length,
			].join(':')
		},

		aiSuggestions() {
			return [
				t('empleados', '¿Cuántos empleados hay?'),
				t('empleados', '¿Quién tiene más antigüedad?'),
				t('empleados', '¿Quiénes ingresaron más recientemente?'),
				t('empleados', '¿Qué empleados no tienen número de empleado?'),
				t('empleados', 'Resume la plantilla laboral'),
				t('empleados', '¿Quiénes tienen más días de vacaciones asignados?'),
			]
		},

		aiEmployees() {
			return this.empleadosProp
				.slice(0, 200)
				.map(item => this.aiEmployee(item))
		},

		aiContext() {
			return {
				resumen: {
					total_empleados:
						this.empleadosProp.length,
					contexto_truncado:
						this.empleadosProp.length > 200,
				},
				empleados:
					this.aiEmployees,
			}
		},
	},

	watch: {
		empleadosProp: {
			deep: true,
			handler() {
				this.aiContextVersion++
			},
		},
	},

	mounted() {
		this.$bus.on('send-data', (data) => {
			this.data_empleado = data
		})
		// deteccion de esc
		window.addEventListener('keydown', this.onKeyDown)
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.onKeyDown)
	},

	methods: {
		t,
		aiDisplayValue(value) {
			if (value === null || value === undefined || value === '') {
				return null
			}

			if (
				typeof value === 'string'
				|| typeof value === 'number'
			) {
				return String(value)
			}

			if (typeof value === 'object') {
				const displayValue = value.displayName
					?? value.displayname
					?? value.label
					?? value.name
					?? value.nombre
					?? value.user
					?? null

				if (
					typeof displayValue === 'string'
					|| typeof displayValue === 'number'
				) {
					return String(displayValue)
				}
			}

			return null
		},
		aiNumberOrNull(value) {
			if (
				value === null
				|| value === undefined
				|| value === ''
			) {
				return null
			}

			const number = Number(value)

			return Number.isFinite(number)
				? number
				: null
		},
		aiCalculateSeniority(fechaStr) {
			if (!fechaStr) return null

			const partes = String(fechaStr).split('-')
			if (partes.length !== 3) return null

			const ingreso = new Date(
				Number(partes[0]),
				Number(partes[1]) - 1,
				Number(partes[2]),
			)
			if (Number.isNaN(ingreso.getTime())) return null

			const hoy = new Date()
			let años = hoy.getFullYear() - ingreso.getFullYear()
			const diffMeses = hoy.getMonth() - ingreso.getMonth()

			if (
				diffMeses < 0
				|| (
					diffMeses === 0
					&& hoy.getDate() < ingreso.getDate()
				)
			) {
				años--
			}

			return Math.max(0, años)
		},
		aiEmploymentStatus(item) {
			const estado = item.estado ?? item.Estado ?? null

			if (
				estado === 1
				|| estado === '1'
				|| estado === true
				|| item.enabled === true
				|| item.disabled === false
			) {
				return t('empleados', 'Activo')
			}

			if (
				estado === 0
				|| estado === '0'
				|| estado === false
				|| item.enabled === false
				|| item.disabled === true
			) {
				return t('empleados', 'Inactivo')
			}

			return t('empleados', 'No disponible')
		},
		aiEmployee(item) {
			const employee = (
				item !== null
				&& typeof item === 'object'
				&& !Array.isArray(item)
			)
				? item
				: {}
			const fechaIngreso = employee.Ingreso
				?? employee.ingreso
				?? null

			return {
				nombre: this.aiDisplayValue(
					employee.displayname
						?? employee.displayName
						?? employee.uid
						?? null,
				),
				usuario: this.aiDisplayValue(
					employee.uid
						?? employee.Id_user
						?? null,
				),
				numero_empleado: this.aiDisplayValue(
					employee.Numero_empleado
						?? employee.numero_empleado
						?? null,
				),
				fecha_ingreso: this.aiDisplayValue(fechaIngreso),
				antiguedad_anios:
					this.aiCalculateSeniority(fechaIngreso),
				area: this.aiDisplayValue(
					employee.area
						?? employee.Area
						?? employee.departamento
						?? employee.Departamento
						?? employee.nombre_departamento
						?? employee.Nombre_departamento
						?? null,
				),
				puesto: this.aiDisplayValue(
					employee.puesto
						?? employee.Puesto
						?? employee.nombre_puesto
						?? employee.Nombre_puesto
						?? null,
				),
				gerente: this.aiDisplayValue(
					employee.gerente
						?? employee.Gerente
						?? employee.Id_gerente
						?? null,
				),
				socio: this.aiDisplayValue(
					employee.socio
						?? employee.Socio
						?? employee.Id_socio
						?? null,
				),
				equipo: this.aiDisplayValue(
					employee.equipo
						?? employee.Equipo
						?? employee.nombre_equipo
						?? employee.Nombre_equipo
						?? null,
				),
				dias_vacaciones_derecho:
					this.aiNumberOrNull(
						employee.dias_derecho
							?? employee.dias_disponibles
							?? null,
					),
				equipo_asignado: this.aiDisplayValue(
					employee.equipo_asignado_nombre
						?? employee.Equipo_asignado_nombre
						?? null,
				),
				estado_laboral:
					this.aiEmploymentStatus(employee),
			}
		},
		onKeyDown(e) {
			if (e.key === 'Escape') this.onEsc()
		},
		onEsc() {
			// eslint-disable-next-line no-console
			console.log('Esc pressed')
			this.data_empleado = {}
		},
	},
}
</script>
