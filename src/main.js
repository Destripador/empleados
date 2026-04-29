import Vue from 'vue'
import App from './views/App.vue'

import router from './router/index.js'
import Router from 'vue-router'
import mitt from 'mitt'

import { loadTranslations, translate as t, translatePlural as n } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

Vue.use(Router)

Vue.mixin({ methods: { t, n } })

Vue.prototype.OC = window.OC
Vue.prototype.OCA = window.OCA

// Obtener configuraciones iniciales desde el DOM
const dataElement = document.getElementById('data')
const configuraciones = dataElement
	? JSON.parse(dataElement.getAttribute('data-parameters') || '{}')
	: {}

const groupElement = document.getElementById('group-user')
const groups = groupElement
	? JSON.parse(groupElement.getAttribute('data-parameters') || '{}')
	: {}

const employeeElement = document.getElementById('employee')
const employee = employeeElement
	? JSON.parse(employeeElement.getAttribute('data-parameters') || '{}')
	: {}

const subordinatesElement = document.getElementById('subordinates')
const subordinates = subordinatesElement
	? JSON.parse(subordinatesElement.getAttribute('data-parameters') || '{}')
	: {}

const emitter = mitt()
Vue.prototype.$bus = emitter

const isTruthy = (value) => {
	return value === true
		|| value === 'true'
		|| value === 1
		|| value === '1'
}

const userHasGroup = (groupName) => {
	if (!groupName || !groups) {
		return false
	}

	if (Array.isArray(groups)) {
		return groups.includes(groupName)
			|| groups.some(group => {
				return group?.id === groupName
					|| group?.gid === groupName
					|| group?.name === groupName
			})
	}

	if (typeof groups === 'object') {
		return Object.prototype.hasOwnProperty.call(groups, groupName)
			|| groups[groupName] === true
			|| Object.values(groups).includes(groupName)
	}

	return false
}

const loadRuntimeConfigurations = async () => {
	try {
		const response = await axios.get(generateUrl('/apps/empleados/GetConfigurations'), {
			headers: {
				Accept: 'application/json',
				'OCS-APIRequest': true,
			},
		})

		const data = response?.data?.ocs?.data ?? response?.data ?? {}

		Object.assign(configuraciones, data)
	} catch (err) {
		console.error('No se pudo cargar GetConfigurations desde main.js:', err)
	}

	const adminReportsGroup = configuraciones?.Reportes?.admin_reports_group
		|| configuraciones?.reportes_admin_reports_group
		|| 'recursos_humanos'

	configuraciones.CanAdminReports = isTruthy(configuraciones?.CanAdminReports)
		|| userHasGroup('admin')
		|| userHasGroup(adminReportsGroup)
}

loadTranslations('empleados').then(async () => {
	await loadRuntimeConfigurations()

	const View = Vue.extend(App)

	new View({
		router,
		propsData: {
			parameters: configuraciones,
			groupsUser: groups,
			employee,
			subordinatesGroup: subordinates,
		},
	}).$mount('#content')
})
