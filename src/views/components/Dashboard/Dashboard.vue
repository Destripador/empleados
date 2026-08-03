<template>
	<NcAppContent :name="t('empleados', 'Employees dashboard')">
		<div class="dashboard">
			<section class="hero">
				<div class="hero-main">
					<p class="kicker">
						{{ t('empleados', 'ERP for Nextcloud') }}
					</p>

					<h1>{{ greeting }}</h1>

					<p class="hero-text">
						{{ t('empleados', 'Manage employees, teams, departments, time reports, absences, savings and IT assets from one workspace.') }}
					</p>

					<div class="hero-actions">
						<NcButton
							v-if="isAdmin"
							type="primary"
							@click="go('Empleados')">
							<template #icon>
								<BadgeAccountAlert :size="20" />
							</template>
							{{ t('empleados', 'Manage employees') }}
						</NcButton>

						<NcButton
							v-if="timeReportsEnabled"
							@click="go('Reports')">
							<template #icon>
								<CalendarClock :size="20" />
							</template>
							{{ t('empleados', 'Report time') }}
						</NcButton>

						<NcButton
							v-if="inventoryEnabled && isAdmin"
							@click="go('Inventario')">
							<template #icon>
								<Laptop :size="20" />
							</template>
							{{ t('empleados', 'IT Inventory') }}
						</NcButton>
					</div>
				</div>

				<div class="hero-side">
					<div class="date-card">
						<span>{{ t('empleados', 'Today') }}</span>
						<strong>{{ currentDateLabel }}</strong>
					</div>

					<div class="workspace-card">
						<div class="workspace-icon">
							<AccountGroup :size="36" />
						</div>

						<div>
							<span>{{ t('empleados', 'Workspace') }}</span>
							<strong>{{ t('empleados', 'Human Resources') }}</strong>
						</div>
					</div>

					<div class="hero-stats">
						<div>
							<span>{{ t('empleados', 'Modules') }}</span>
							<strong>{{ enabledModules }}</strong>
						</div>

						<div>
							<span>{{ t('empleados', 'Role') }}</span>
							<strong>{{ isAdmin ? t('empleados', 'Admin') : t('empleados', 'User') }}</strong>
						</div>
					</div>
				</div>
			</section>

			<section class="kpi-grid">
				<div
					v-for="item in kpis"
					:key="item.key"
					class="kpi-card">
					<div class="kpi-icon">
						<component :is="item.icon" :size="24" />
					</div>

					<div class="kpi-body">
						<span>{{ item.label }}</span>
						<strong>{{ loading ? '...' : item.value }}</strong>
						<small>{{ item.description }}</small>
					</div>
				</div>
			</section>

			<NcNoteCard
				v-if="!isAdmin"
				type="info"
				class="notice">
				{{ t('empleados', 'This dashboard shows the options available for your user. Administrative metrics are only available for administrators or Human Resources users.') }}
			</NcNoteCard>

			<section class="layout">
				<div class="panel apps-panel">
					<div class="panel-header">
						<div>
							<p class="section-label">
								{{ t('empleados', 'Applications') }}
							</p>
							<h2>{{ t('empleados', 'Business apps') }}</h2>
						</div>

						<NcButton
							v-if="isAdmin"
							:aria-label="t('empleados', 'Refresh')"
							@click="loadData">
							<template #icon>
								<Reload :size="20" />
							</template>
						</NcButton>
					</div>

					<div class="app-grid">
						<button
							v-for="action in quickActions"
							:key="action.route"
							type="button"
							class="app-tile"
							@click="go(action.route)">
							<span class="app-icon">
								<component :is="action.icon" :size="30" />
							</span>

							<span class="app-title">{{ action.title }}</span>
							<span class="app-description">{{ action.description }}</span>
						</button>
					</div>
				</div>

				<div class="right-column">
					<SoporteEquipoDashboardWidget v-if="showEquipmentSupportWidget" class="panel" />

					<div
						v-if="timeReportsEnabled"
						class="panel today-panel"
						:class="todayReportClass">
						<div class="panel-header compact">
							<div>
								<p class="section-label">
									{{ t('empleados', 'Today') }}
								</p>
								<h2>{{ t('empleados', 'Time report') }}</h2>
							</div>
						</div>

						<div class="today-status">
							<div class="today-icon">
								<CalendarClock :size="26" />
							</div>

							<div>
								<strong>{{ todayReportLabel }}</strong>
								<span>{{ todayReportDescription }}</span>
							</div>
						</div>

						<div class="hours-box">
							<span>{{ t('empleados', 'Reported hours') }}</span>
							<strong>{{ todayHours }} h</strong>
						</div>

						<NcButton
							wide
							type="primary"
							@click="go('Reports')">
							{{ t('empleados', 'Open reports') }}
						</NcButton>
					</div>

					<div class="panel status-panel">
						<div class="panel-header compact">
							<div>
								<p class="section-label">
									{{ t('empleados', 'Overview') }}
								</p>
								<h2>{{ t('empleados', 'Organization') }}</h2>
							</div>
						</div>

						<div v-if="loading" class="empty-state">
							<NcLoadingIcon :size="32" />
							<span>{{ t('empleados', 'Loading') }}</span>
						</div>

						<div v-else-if="isAdmin" class="status-list">
							<div
								v-for="item in structureItems"
								:key="item.label"
								class="status-row">
								<div>
									<strong>{{ item.label }}</strong>
									<span>{{ item.description }}</span>
								</div>

								<b>{{ item.value }}</b>
							</div>
						</div>

						<div v-else class="empty-state">
							<span>{{ t('empleados', 'No administrative metrics available for this profile.') }}</span>
						</div>
					</div>
				</div>
			</section>

			<section class="panel modules-panel">
				<div class="panel-header">
					<div>
						<p class="section-label">
							{{ t('empleados', 'Modules') }}
						</p>
						<h2>{{ t('empleados', 'Installed modules') }}</h2>
					</div>
				</div>

				<div class="module-grid">
					<div
						v-for="module in modules"
						:key="module.key"
						class="module-card"
						:class="{ disabled: !module.enabled }">
						<div class="module-icon">
							<component :is="module.icon" :size="24" />
						</div>

						<div class="module-info">
							<strong>{{ module.title }}</strong>
							<p>{{ module.description }}</p>
						</div>

						<span class="module-state" :class="{ enabled: module.enabled }">
							{{ module.enabled ? t('empleados', 'Enabled') : t('empleados', 'Disabled') }}
						</span>
					</div>
				</div>
			</section>
		</div>
	</NcAppContent>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

import {
	NcAppContent,
	NcButton,
	NcLoadingIcon,
	NcNoteCard,
} from '@nextcloud/vue'

import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import AccountTieOutline from 'vue-material-design-icons/AccountTieOutline.vue'
import BadgeAccountAlert from 'vue-material-design-icons/BadgeAccountAlert.vue'
import Bank from 'vue-material-design-icons/Bank.vue'
import CalendarBlank from 'vue-material-design-icons/CalendarBlank.vue'
import CalendarClock from 'vue-material-design-icons/CalendarClock.vue'
import OfficeBuilding from 'vue-material-design-icons/OfficeBuilding.vue'
import Reload from 'vue-material-design-icons/Reload.vue'
import Laptop from 'vue-material-design-icons/Laptop.vue'
import ViewList from 'vue-material-design-icons/ViewList.vue'

import inventarioService from '../../../services/inventarioService.js'
import permissionsMixin from '../../../mixins/permissions.js'
import SoporteEquipoDashboardWidget from '../../../Dashboard/SoporteEquipoDashboardWidget.vue'

export default {
	name: 'Dashboard',

	components: {
		NcAppContent,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		AccountGroup,
		AccountTieOutline,
		BadgeAccountAlert,
		Bank,
		CalendarBlank,
		CalendarClock,
		OfficeBuilding,
		Reload,
		Laptop,
		SoporteEquipoDashboardWidget,
		ViewList,
	},

	mixins: [permissionsMixin],

	inject: {
		groupuser: { default: () => ({}) },
		configuraciones: { default: () => ({}) },
		employee: { default: () => [] },
		subordinates: { default: () => [] },
	},

	data() {
		return {
			loading: false,
			loadingToday: false,
			empleados: [],
			areas: [],
			puestos: [],
			equipos: [],
			inventoryTotal: 0,
			estadoHoy: null,
		}
	},

	computed: {
		isAdmin() {
			return this.hasGroup('admin') || this.hasGroup('recursos_humanos')
		},

		currentEmployee() {
			if (Array.isArray(this.employee)) {
				return this.employee[0] || {}
			}

			return this.employee || {}
		},

		greeting() {
			const name = this.currentEmployee?.displayname
				|| this.currentEmployee?.Id_user
				|| this.currentEmployee?.uid
				|| ''

			if (name) {
				return t('empleados', 'Welcome, {name}', { name })
			}

			return t('empleados', 'Employees workspace')
		},

		currentDateLabel() {
			return new Intl.DateTimeFormat(undefined, {
				weekday: 'long',
				day: '2-digit',
				month: 'short',
			}).format(new Date())
		},

		timeReportsEnabled() {
			return this.isTruthy(this.configuraciones?.modulo_reporte_tiempos)
		},

		absencesEnabled() {
			return this.isTruthy(this.configuraciones?.modulo_ausencias)
		},

		savingsEnabled() {
			return this.isTruthy(this.configuraciones?.modulo_ahorro)
		},

		customersEnabled() {
			return this.isTruthy(this.configuraciones?.modulo_clientes)
		},

		inventoryEnabled() {
			return this.isTruthy(this.configuraciones?.modulo_inventario)
				|| this.isTruthy(this.configuraciones?.modulo_soporte)
		},

		showEquipmentSupportWidget() {
			return this.isTruthy(this.configuraciones?.modulo_inventario)
				&& this.canSeeAny(['inventario', 'soporte'])
		},

		enabledModules() {
			return this.modules.filter((module) => module.enabled).length
		},

		todayHours() {
			return Number(this.estadoHoy?.horas_reportadas || 0).toFixed(2)
		},

		todayReportLabel() {
			const estado = this.estadoHoy?.estado

			if (this.loadingToday) {
				return t('empleados', 'Loading...')
			}

			if (estado === 'reportado') {
				return t('empleados', 'Reported')
			}

			if (estado === 'sin_empleado') {
				return t('empleados', 'No employee profile')
			}

			return t('empleados', 'Pending')
		},

		todayReportDescription() {
			const estado = this.estadoHoy?.estado

			if (estado === 'reportado') {
				return t('empleados', 'Your time report for today is complete.')
			}

			if (estado === 'sin_empleado') {
				return t('empleados', 'Your user is not linked to an employee profile.')
			}

			return t('empleados', 'You still have pending time to report today.')
		},

		todayReportClass() {
			const estado = this.estadoHoy?.estado

			if (estado === 'reportado') {
				return 'is-ok'
			}

			if (estado === 'sin_empleado') {
				return 'is-warning'
			}

			return 'is-pending'
		},

		kpis() {
			const base = [
				{
					key: 'employees',
					label: t('empleados', 'Employees'),
					value: this.isAdmin ? this.empleados.length : '-',
					description: t('empleados', 'Registered profiles'),
					icon: BadgeAccountAlert,
				},
				{
					key: 'departments',
					label: t('empleados', 'Departments'),
					value: this.isAdmin ? this.areas.length : '-',
					description: t('empleados', 'Company areas'),
					icon: OfficeBuilding,
				},
				{
					key: 'teams',
					label: t('empleados', 'Teams'),
					value: this.isAdmin ? this.equipos.length : '-',
					description: t('empleados', 'Work groups'),
					icon: AccountGroup,
				},
				{
					key: 'positions',
					label: t('empleados', 'Positions'),
					value: this.isAdmin ? this.puestos.length : '-',
					description: t('empleados', 'Defined roles'),
					icon: AccountTieOutline,
				},
			]

			if (this.showEquipmentSupportWidget && this.isAdmin) {
				base.push({
					key: 'devices',
					label: t('empleados', 'Devices'),
					value: this.inventoryTotal,
					description: t('empleados', 'IT assets'),
					icon: Laptop,
				})
			}

			return base
		},

		quickActions() {
			const actions = []

			if (this.isAdmin) {
				actions.push(
					{
						route: 'Empleados',
						title: t('empleados', 'Employees'),
						description: t('empleados', 'Manage employee files'),
						icon: BadgeAccountAlert,
					},
					{
						route: 'Areas',
						title: t('empleados', 'Departments'),
						description: t('empleados', 'Manage company areas'),
						icon: OfficeBuilding,
					},
					{
						route: 'Puestos',
						title: t('empleados', 'Positions'),
						description: t('empleados', 'Manage job positions'),
						icon: AccountTieOutline,
					},
					{
						route: 'Equipos',
						title: t('empleados', 'Teams'),
						description: t('empleados', 'Manage work teams'),
						icon: AccountGroup,
					},
				)
			}

			if (this.inventoryEnabled && this.isAdmin) {
				actions.push({
					route: 'Inventario',
					title: t('empleados', 'IT Inventory'),
					description: t('empleados', 'Devices, models and support'),
					icon: Laptop,
				})
			}

			if (this.absencesEnabled) {
				actions.push({
					route: 'Calendario',
					title: t('empleados', 'Calendar'),
					description: t('empleados', 'Vacations and absences'),
					icon: CalendarBlank,
				})
			}

			if (this.timeReportsEnabled) {
				actions.push({
					route: 'Reports',
					title: t('empleados', 'Time Reports'),
					description: t('empleados', 'Register work time'),
					icon: CalendarClock,
				})
			}

			if (this.savingsEnabled) {
				actions.push({
					route: 'Ahorros',
					title: t('empleados', 'Savings'),
					description: t('empleados', 'Savings requests'),
					icon: Bank,
				})
			}

			if (this.customersEnabled && this.isAdmin) {
				actions.push({
					route: 'CompaniesGroups',
					title: t('empleados', 'Customers'),
					description: t('empleados', 'Companies and groups'),
					icon: ViewList,
				})
			}

			return actions
		},

		structureItems() {
			return [
				{
					label: t('empleados', 'Registered employees'),
					value: this.empleados.length,
					description: t('empleados', 'Employee profiles available in the module'),
				},
				{
					label: t('empleados', 'Departments with records'),
					value: this.countWithEmployees(this.areas),
					description: t('empleados', 'Departments currently linked to employees'),
				},
				{
					label: t('empleados', 'Teams with members'),
					value: this.countWithEmployees(this.equipos),
					description: t('empleados', 'Teams currently linked to employees'),
				},
				{
					label: t('empleados', 'IT devices'),
					value: this.showEquipmentSupportWidget ? this.inventoryTotal : '-',
					description: t('empleados', 'Registered company devices'),
				},
			]
		},

		modules() {
			return [
				{
					key: 'human-resources',
					title: t('empleados', 'Human Resources'),
					description: t('empleados', 'Employees, departments, positions and teams.'),
					icon: BadgeAccountAlert,
					enabled: true,
				},
				{
					key: 'time-reports',
					title: t('empleados', 'Time Reports'),
					description: t('empleados', 'Work time reports by client and activity.'),
					icon: CalendarClock,
					enabled: this.timeReportsEnabled,
				},
				{
					key: 'absences',
					title: t('empleados', 'Vacations and Absences'),
					description: t('empleados', 'Vacation calendar and absence control.'),
					icon: CalendarBlank,
					enabled: this.absencesEnabled,
				},
				{
					key: 'savings',
					title: t('empleados', 'Savings'),
					description: t('empleados', 'Employee savings requests and admin panel.'),
					icon: Bank,
					enabled: this.savingsEnabled,
				},
				{
					key: 'customers',
					title: t('empleados', 'Customers'),
					description: t('empleados', 'Companies, groups and activities.'),
					icon: ViewList,
					enabled: this.customersEnabled,
				},
				{
					key: 'inventory',
					title: t('empleados', 'IT Inventory'),
					description: t('empleados', 'Computer equipment, models and support history.'),
					icon: Laptop,
					enabled: this.inventoryEnabled,
				},
			]
		},
	},

	mounted() {
		this.loadData()

		if (this.timeReportsEnabled) {
			this.loadTodayReport()
		}
	},

	methods: {
		t,

		go(routeName) {
			this.$router.push({ name: routeName })
		},

		hasGroup(groupName) {
			if (!groupName || !this.groupuser) {
				return false
			}

			if (Array.isArray(this.groupuser)) {
				return this.groupuser.includes(groupName)
					|| this.groupuser.some((group) => {
						return group?.id === groupName
							|| group?.gid === groupName
							|| group?.name === groupName
					})
			}

			if (typeof this.groupuser === 'object') {
				return Object.prototype.hasOwnProperty.call(this.groupuser, groupName)
					|| this.groupuser[groupName] === true
					|| Object.values(this.groupuser).includes(groupName)
			}

			return false
		},

		isTruthy(value) {
			return value === true
				|| value === 'true'
				|| value === 1
				|| value === '1'
		},

		async loadData() {
			if (!this.isAdmin) {
				return
			}

			this.loading = true

			try {
				const [empleados, areas, puestos, equipos] = await Promise.all([
					axios.get(generateUrl('/apps/empleados/GetEmpleadosList')),
					axios.get(generateUrl('/apps/empleados/GetAreasList')),
					axios.get(generateUrl('/apps/empleados/GetPuestosList')),
					axios.get(generateUrl('/apps/empleados/GetEquiposList')),
				])

				this.empleados = this.extractArray(empleados, 'Empleados')
				this.areas = this.extractArray(areas)
				this.puestos = this.extractArray(puestos)
				this.equipos = this.extractArray(equipos)

				if (this.showEquipmentSupportWidget) {
					await this.loadInventorySummary()
				}
			} catch (err) {
				this.resetAdminData()
			} finally {
				this.loading = false
			}
		},

		async loadInventorySummary() {
			if (!this.showEquipmentSupportWidget) return
			try {
				const response = await inventarioService.getEquipos({ limit: 1, offset: 0 })
				this.inventoryTotal = Number(response?.total || 0)
			} catch (err) {
				this.inventoryTotal = 0
			}
		},

		async loadTodayReport() {
			this.loadingToday = true

			try {
				const response = await axios.get(generateUrl('/apps/empleados/estadoReporteHoy'))
				this.estadoHoy = response?.data?.ocs?.data ?? response?.data ?? null
			} catch (err) {
				this.estadoHoy = null
			} finally {
				this.loadingToday = false
			}
		},

		extractArray(response, key = null) {
			const data = response?.data?.ocs?.data ?? response?.data ?? []

			if (key && Array.isArray(data?.[key])) {
				return data[key]
			}

			if (Array.isArray(data?.data)) {
				return data.data
			}

			if (Array.isArray(data)) {
				return data
			}

			if (data && typeof data === 'object') {
				return Object.values(data)
			}

			return []
		},

		countWithEmployees(items) {
			return this.extractPlainArray(items).filter((item) => {
				return Number(item.cantidad_empleados || item.total_empleados || 0) > 0
			}).length
		},

		extractPlainArray(data) {
			if (Array.isArray(data)) {
				return data
			}

			if (data && typeof data === 'object') {
				return Object.values(data)
			}

			return []
		},

		resetAdminData() {
			this.empleados = []
			this.areas = []
			this.puestos = []
			this.equipos = []
			this.inventoryTotal = 0
		},
	},
}
</script>

<style scoped>
.dashboard {
	--erp-primary: #714b67;
	--erp-primary-dark: #56384e;
	--erp-secondary: #017e84;
	--erp-soft: rgba(113, 75, 103, 0.08);

	display: flex;
	flex-direction: column;
	gap: 18px;
	width: 100%;
	min-height: 100%;
	padding: 24px 32px 40px;
	background:
		radial-gradient(circle at 0% 0%, rgba(113, 75, 103, 0.10), transparent 28%),
		var(--color-background-hover);
}

.hero {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 340px;
	gap: 18px;
	padding: 28px;
	border: 1px solid rgba(113, 75, 103, 0.18);
	border-radius: 26px;
	background: linear-gradient(135deg, var(--erp-primary), var(--erp-primary-dark));
	color: white;
	box-shadow: 0 18px 36px rgba(0, 0, 0, 0.16);
}

.hero-main {
	display: flex;
	flex-direction: column;
	justify-content: center;
	min-width: 0;
}

.kicker {
	margin: 0 0 8px;
	color: rgba(255, 255, 255, 0.74);
	font-size: 12px;
	font-weight: 800;
	letter-spacing: .08em;
	text-transform: uppercase;
}

.hero h1 {
	margin: 0;
	font-size: clamp(30px, 4vw, 46px);
	font-weight: 850;
	line-height: 1.05;
}

.hero-text {
	max-width: 800px;
	margin: 12px 0 0;
	color: rgba(255, 255, 255, 0.84);
	font-size: 15px;
	line-height: 1.55;
}

.hero-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	margin-top: 22px;
}

.hero-side {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.date-card,
.workspace-card,
.hero-stats > div {
	border: 1px solid rgba(255, 255, 255, 0.20);
	border-radius: 20px;
	background: rgba(255, 255, 255, 0.10);
	backdrop-filter: blur(12px);
}

.date-card {
	padding: 14px 16px;
}

.workspace-card {
	display: flex;
	align-items: center;
	gap: 14px;
	padding: 16px;
}

.workspace-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 58px;
	height: 58px;
	border-radius: 18px;
	background: rgba(255, 255, 255, 0.16);
}

.date-card span,
.workspace-card span,
.hero-stats span {
	display: block;
	color: rgba(255, 255, 255, 0.72);
	font-size: 12px;
	font-weight: 700;
}

.date-card strong,
.workspace-card strong,
.hero-stats strong {
	display: block;
	margin-top: 4px;
	color: white;
	font-size: 18px;
	font-weight: 850;
}

.hero-stats {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px;
}

.hero-stats > div {
	padding: 14px;
}

.kpi-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
	gap: 14px;
}

.kpi-card,
.panel {
	border: 1px solid var(--color-border);
	border-radius: 22px;
	background: var(--color-main-background);
	box-shadow: 0 8px 22px rgba(0, 0, 0, 0.055);
}

.kpi-card {
	display: flex;
	align-items: center;
	gap: 14px;
	min-width: 0;
	padding: 16px;
}

.kpi-icon,
.app-icon,
.module-icon,
.today-icon {
	display: inline-flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	width: 48px;
	height: 48px;
	border-radius: 17px;
	background: var(--erp-soft);
	color: var(--erp-primary);
}

.kpi-body {
	min-width: 0;
}

.kpi-body span {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
}

.kpi-body strong {
	display: block;
	margin-top: 2px;
	color: var(--color-main-text);
	font-size: 29px;
	font-weight: 850;
	line-height: 1;
}

.kpi-body small {
	display: block;
	margin-top: 5px;
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.notice {
	margin: 0;
}

.layout {
	display: grid;
	grid-template-columns: minmax(0, 1.4fr) minmax(340px, .6fr);
	gap: 18px;
}

.panel {
	padding: 18px;
}

.right-column {
	display: flex;
	flex-direction: column;
	gap: 18px;
}

.panel-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 14px;
	margin-bottom: 16px;
}

.panel-header.compact {
	margin-bottom: 12px;
}

.section-label {
	margin: 0 0 4px;
	color: var(--erp-secondary);
	font-size: 12px;
	font-weight: 850;
	letter-spacing: .06em;
	text-transform: uppercase;
}

.panel h2 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 20px;
	font-weight: 850;
}

.app-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
	gap: 14px;
}

.app-tile {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 10px;
	min-height: 148px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: 20px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	cursor: pointer;
	text-align: left;
	transition:
		transform 140ms ease,
		box-shadow 140ms ease,
		border-color 140ms ease,
		background 140ms ease;
}

.app-tile:hover,
.app-tile:focus {
	border-color: rgba(113, 75, 103, 0.45);
	background: linear-gradient(180deg, var(--color-main-background), var(--erp-soft));
	box-shadow: 0 12px 26px rgba(0, 0, 0, 0.11);
	transform: translateY(-2px);
	outline: none;
}

.app-title {
	display: block;
	font-size: 15px;
	font-weight: 850;
}

.app-description {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.35;
}

.today-panel {
	position: relative;
	overflow: hidden;
}

.today-panel::before {
	position: absolute;
	top: 0;
	left: 0;
	width: 5px;
	height: 100%;
	content: "";
	background: var(--erp-primary);
}

.today-panel.is-ok::before {
	background: #46ba61;
}

.today-panel.is-pending::before {
	background: #e9322d;
}

.today-panel.is-warning::before {
	background: #eca700;
}

.today-status {
	display: flex;
	gap: 12px;
	align-items: flex-start;
	margin-bottom: 14px;
}

.today-status strong {
	display: block;
	color: var(--color-main-text);
	font-size: 16px;
	font-weight: 850;
}

.today-status span {
	display: block;
	margin-top: 4px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.35;
}

.hours-box {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 12px;
	margin-bottom: 14px;
	border-radius: 16px;
	background: var(--color-background-hover);
}

.hours-box span {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
}

.hours-box strong {
	color: var(--color-main-text);
	font-size: 20px;
	font-weight: 850;
}

.status-list {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.status-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 14px;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: 18px;
	background: var(--color-background-hover);
}

.status-row strong {
	display: block;
	color: var(--color-main-text);
	font-size: 14px;
}

.status-row span {
	display: block;
	margin-top: 3px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.35;
}

.status-row b {
	display: flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	min-width: 42px;
	height: 42px;
	border-radius: 14px;
	background: var(--erp-soft);
	color: var(--erp-primary);
	font-size: 20px;
}

.empty-state {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 180px;
	gap: 10px;
	color: var(--color-text-maxcontrast);
	font-weight: 700;
	text-align: center;
}

.module-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
	gap: 12px;
}

.module-card {
	position: relative;
	display: flex;
	align-items: flex-start;
	gap: 12px;
	min-width: 0;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: 18px;
	background: var(--color-background-hover);
}

.module-card.disabled {
	opacity: .64;
}

.module-info {
	min-width: 0;
	padding-right: 86px;
}

.module-info strong {
	display: block;
	color: var(--color-main-text);
	font-size: 14px;
	font-weight: 850;
}

.module-info p {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.35;
}

.module-state {
	position: absolute;
	top: 12px;
	right: 12px;
	padding: 4px 9px;
	border-radius: 999px;
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 11px;
	font-weight: 850;
}

.module-state.enabled {
	background: rgba(1, 126, 132, 0.12);
	color: var(--erp-secondary);
}

@media (max-width: 1180px) {
	.hero,
	.layout {
		grid-template-columns: 1fr;
	}

	.hero-side {
		display: grid;
		grid-template-columns: 1fr 1fr;
	}

	.date-card {
		grid-column: 1 / -1;
	}
}

@media (max-width: 768px) {
	.dashboard {
		padding: 14px;
	}

	.hero {
		padding: 20px;
		border-radius: 20px;
	}

	.hero-side {
		grid-template-columns: 1fr;
	}

	.date-card {
		grid-column: auto;
	}

	.hero-stats {
		grid-template-columns: 1fr;
	}

	.app-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media (max-width: 520px) {
	.kpi-grid,
	.app-grid,
	.module-grid {
		grid-template-columns: 1fr;
	}
}
</style>
