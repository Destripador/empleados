import Vue from 'vue'
import DashboardReportesWidget from './Dashboard/DashboardReportesWidget.vue'

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
