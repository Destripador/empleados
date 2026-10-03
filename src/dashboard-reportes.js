import Vue from 'vue'
import { generateFilePath } from '@nextcloud/router'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import DashboardReportesWidget from './Dashboard/DashboardReportesWidget.vue'

// eslint-disable-next-line no-unused-vars
/* global __webpack_public_path__: writable */
__webpack_public_path__ = generateFilePath('empleados', '', 'js/')

Vue.mixin({ methods: { t, n } })

const registerWidget = () => {
	if (!window.OCA || !window.OCA.Dashboard) {
		return
	}

	window.OCA.Dashboard.register('empleados_reportes', (el) => {
		const View = Vue.extend(DashboardReportesWidget)
		new View().$mount(el)
	})
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', registerWidget)
} else {
	registerWidget()
}
