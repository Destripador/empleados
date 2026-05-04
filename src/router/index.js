import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'

import Employees from '../views/components/ListaEmpleados/Employees.vue'
import Calendario from '../views/components/TiempoLibre/TiempoLibre.vue'
import Equipos from '../views/components/Equipos/Equipos.vue'
import Puestos from '../views/components/puestos/Puestos.vue'
import Areas from '../views/components/areas/Areas.vue'
import Ahorros from '../views/components/ahorros/Solicitar.vue'
import PanelAhorros from '../views/components/ahorros/PanelAhorros.vue'
import Dashboard from '../views/components/Dashboard/Dashboard.vue'
import CompaniesGroups from '../views/components/clientes/CompaniesGroups.vue'
import Actividades from '../views/components/clientes/Actividades.vue'
import Reports from '../views/components/reports/Reports.vue'
import Adminreports from '../views/components/reports/admin/Adminreports.vue'
import Ejemplo from '../views/components/ejemplo/Ejemplo.vue'
import QuickReport from '../views/components/reports/QuickReport.vue'
import CumplimientoReportes from '../views/components/reports/CumplimientoReportes.vue'
import Inventario from '../views/components/Inventario/Inventario.vue'

Vue.use(Router)

export default new Router({
	mode: 'hash',
	linkActiveClass: 'active',
	// if index.php is in the url AND we got this far, then it's working:
	// let's keep using index.php in the url
	base: generateUrl('/apps/empleados', ''),
	routes: [
		{
			path: '/',
			component: Dashboard,
			name: 'Home',
		},
		{
			path: '/Empleados',
			component: Employees,
			name: 'Empleados',
		},
		{
			path: '/Puestos',
			component: Puestos,
			name: 'Puestos',
		},
		{
			path: '/Areas',
			component: Areas,
			name: 'Areas',
		},
		{
			path: '/Equipos',
			component: Equipos,
			name: 'Equipos',
		},
		{
			path: '/Calendario',
			component: Calendario,
			name: 'Calendario',
		},
		{
			path: '/Solicitar',
			component: Ahorros,
			name: 'Ahorros',
		},
		{
			path: '/PanelAhorros',
			component: PanelAhorros,
			name: 'PanelAhorros',
		},
		{
			path: '/Activities',
			component: Actividades,
			name: 'Activities',
		},
		{
			path: '/CompaniesGroups',
			component: CompaniesGroups,
			name: 'CompaniesGroups',
		},
		{
			path: '/Reports',
			component: Reports,
			name: 'Reports',
		},
		{
			path: '/Adminreports',
			component: Adminreports,
			name: 'Adminreports',
		},
		{
			path: '/ejemplo', // Nueva ruta
			component: Ejemplo, // Asumiendo que el componente se llama Sidenavitaion.vue
			name: 'ejemplo',
		},
		{
			path: '/quick-report',
			name: 'quick-report',
			component: QuickReport,
		},
		{
			path: '/cumplimiento-reportes',
			name: 'cumplimiento-reportes',
			component: CumplimientoReportes,
		},
		{
			path: '/Inventario',
			component: Inventario,
			name: 'Inventario',
		},
	],
})
