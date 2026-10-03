import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'

Vue.use(Router)

export default new Router({
	mode: 'hash',
	linkActiveClass: 'active',
	base: generateUrl('/apps/empleados', ''),

	routes: [
		{
			path: '/',
			name: 'Home',
			component: () => import(
				/* webpackChunkName: "dashboard" */
				'../views/components/Dashboard/Dashboard.vue'
			),
		},
		{
			path: '/Empleados',
			name: 'Empleados',
			component: () => import(
				/* webpackChunkName: "empleados-lista" */
				'../views/components/ListaEmpleados/Employees.vue'
			),
		},
		{
			path: '/Puestos',
			name: 'Puestos',
			component: () => import(
				/* webpackChunkName: "puestos" */
				'../views/components/puestos/Puestos.vue'
			),
		},
		{
			path: '/Areas',
			name: 'Areas',
			component: () => import(
				/* webpackChunkName: "areas" */
				'../views/components/areas/Areas.vue'
			),
		},
		{
			path: '/Equipos',
			name: 'Equipos',
			component: () => import(
				/* webpackChunkName: "equipos" */
				'../views/components/Equipos/Equipos.vue'
			),
		},
		{
			path: '/Calendario',
			name: 'Calendario',
			component: () => import(
				/* webpackChunkName: "tiempo-libre" */
				'../views/components/TiempoLibre/TiempoLibre.vue'
			),
		},
		{
			path: '/Solicitar',
			name: 'Ahorros',
			component: () => import(
				/* webpackChunkName: "ahorros-solicitar" */
				'../views/components/ahorros/Solicitar.vue'
			),
		},
		{
			path: '/PanelAhorros',
			name: 'PanelAhorros',
			component: () => import(
				/* webpackChunkName: "ahorros-panel" */
				'../views/components/ahorros/PanelAhorros.vue'
			),
		},
		{
			path: '/Activities',
			name: 'Activities',
			component: () => import(
				/* webpackChunkName: "actividades" */
				'../views/components/clientes/Actividades.vue'
			),
		},
		{
			path: '/CompaniesGroups',
			name: 'CompaniesGroups',
			component: () => import(
				/* webpackChunkName: "clientes-empresas" */
				'../views/components/clientes/CompaniesGroups.vue'
			),
		},
		{
			path: '/ClientesDashboard',
			name: 'ClientesDashboard',
			redirect: { name: 'CompaniesGroups', query: { view: 'resumen' } },
		},
		{
			path: '/Costs',
			name: 'Costs',
			component: () => import(
				/* webpackChunkName: "costos" */
				'../views/components/costos/Costos.vue'
			),
		},
		{
			path: '/Reports',
			name: 'Reports',
			component: () => import(
				/* webpackChunkName: "reportes" */
				'../views/components/reports/Reports.vue'
			),
		},
		{
			path: '/Adminreports',
			name: 'Adminreports',
			component: () => import(
				/* webpackChunkName: "reportes-admin" */
				'../views/components/reports/admin/Adminreports.vue'
			),
		},
		{
			path: '/Adminreports/equipo/:teamId',
			name: 'AdminReportTeam',
			component: () => import(
				/* webpackChunkName: "reportes-admin" */
				'../views/components/reports/admin/Adminreports.vue'
			),
		},
		{
			path: '/Adminreports/equipo/:teamId/empleado/:employeeId',
			name: 'AdminReportTeamEmployee',
			component: () => import(
				/* webpackChunkName: "reportes-admin" */
				'../views/components/reports/admin/Adminreports.vue'
			),
		},
		{
			path: '/Adminreports/empleado/:employeeId',
			name: 'AdminReportEmployee',
			component: () => import(
				/* webpackChunkName: "reportes-admin" */
				'../views/components/reports/admin/Adminreports.vue'
			),
		},
		{
			path: '/quick-report',
			name: 'quick-report',
			component: () => import(
				/* webpackChunkName: "quick-report" */
				'../views/components/reports/QuickReport.vue'
			),
		},
		{
			path: '/cumplimiento-reportes',
			name: 'cumplimiento-reportes',
			component: () => import(
				/* webpackChunkName: "cumplimiento-reportes" */
				'../views/components/reports/CumplimientoReportes.vue'
			),
		},
		{
			path: '/Inventario',
			name: 'Inventario',
			component: () => import(
				/* webpackChunkName: "inventario" */
				'../views/components/Inventario/Inventario.vue'
			),
		},
		{
			path: '/Inventario/Mantenimientos',
			name: 'Mantenimientos',
			component: () => import(
				/* webpackChunkName: "inventario-mantenimientos" */
				'../views/components/Inventario/Mantenimientos/MantenimientosView.vue'
			),
		},
		{
			path: '/Inventario/Mantenimientos/Grupos/:id',
			name: 'MantenimientoGrupo',
			component: () => import(
				/* webpackChunkName: "inventario-mantenimiento-grupo" */
				'../views/components/Inventario/Mantenimientos/MantenimientoGrupoDetail.vue'
			),
		},
		{
			path: '/Inventario/Mantenimientos/:id',
			name: 'MantenimientoDetalle',
			component: () => import(
				/* webpackChunkName: "inventario-mantenimiento-detalle" */
				'../views/components/Inventario/Mantenimientos/MantenimientoDetail.vue'
			),
		},
		{
			path: '/compras',
			name: 'compras',
			component: () => import(
				/* webpackChunkName: "compras" */
				'../views/components/Compras/MisSolicitudes.vue'
			),
		},
		{
			path: '/office-simulation',
			name: 'SimulacionOficina',
			component: () => import(
				/* webpackChunkName: "simulacion-oficina" */
				'../views/components/SimulacionOficina/SimulacionOficina.vue'
			),
		},
	],
})
