import Vue from 'vue'
import App from './views/App.vue'

import router from './router/index.js'
import Router from 'vue-router'
import mitt from 'mitt'

import { loadTranslations, translate as t, translatePlural as n } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateFilePath, generateUrl } from '@nextcloud/router'

// eslint-disable-next-line no-unused-vars
/* global __webpack_public_path__: writable */
__webpack_public_path__ = generateFilePath('empleados', '', 'js/')

Vue.use(Router)

Vue.mixin({ methods: { t, n } })

Vue.prototype.OC = window.OC
Vue.prototype.OCA = window.OCA

const parseDomJson = (id, defaultValue = {}) => {
	const element = document.getElementById(id)

	if (!element) {
		return defaultValue
	}

	try {
		return JSON.parse(element.getAttribute('data-parameters') || JSON.stringify(defaultValue))
	} catch (error) {
		console.error(`No se pudo leer ${id}:`, error)
		return defaultValue
	}
}

// Obtener configuraciones iniciales desde el DOM
const configuraciones = parseDomJson('data', {})
const groups = parseDomJson('group-user', {})
const employee = parseDomJson('employee', [])
const subordinates = parseDomJson('subordinates', [])

const permissionsContext = {
	uid: null,
	is_admin: false,
	groups: [],
	modules: {},
}

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

const getResponsePayload = (response) => {
	const payload = response?.data?.ocs?.data ?? response?.data ?? {}

	return payload?.data ?? payload
}

const loadRuntimeConfigurations = async () => {
	try {
		const response = await axios.get(generateUrl('/apps/empleados/GetConfigurations'), {
			headers: {
				Accept: 'application/json',
				'OCS-APIRequest': true,
			},
		})

		const data = getResponsePayload(response)

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

const loadPermissionsContext = async () => {
	try {
		const response = await axios.get(generateUrl('/apps/empleados/permisos/contexto'), {
			headers: {
				Accept: 'application/json',
				'OCS-APIRequest': true,
			},
		})

		const data = getResponsePayload(response)

		Object.assign(permissionsContext, data)
	} catch (err) {
		console.error('No se pudo cargar permisos/contexto desde main.js:', err)
	}
}

loadTranslations('empleados').then(async () => {
	await loadRuntimeConfigurations()
	await loadPermissionsContext()

	const View = Vue.extend(App)

	new View({
		router,
		propsData: {
			parameters: configuraciones,
			groupsUser: groups,
			employee,
			subordinatesGroup: subordinates,
			permissionsContext,
		},
	}).$mount('#content')
})
