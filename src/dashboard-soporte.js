import Vue from 'vue'
import SoporteEquipoDashboardWidget from './Dashboard/SoporteEquipoDashboardWidget.vue'

export const SUPPORT_WIDGET_ID = 'empleados-soporte-equipo'

const registerWidget = () => {
	if (typeof window.OCA?.Dashboard?.register !== 'function') {
		console.error('[empleados] La API del Dashboard de Nextcloud no está disponible.')
		return
	}

	window.OCA.Dashboard.register(SUPPORT_WIDGET_ID, (el) => {
		const View = Vue.extend(SoporteEquipoDashboardWidget)
		new View().$mount(el)
	})
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', registerWidget, { once: true })
} else {
	registerWidget()
}
