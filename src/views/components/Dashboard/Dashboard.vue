<template id="content">
	<NcAppContent :name="t('empleados', 'Employees dashboard')">
		<div class="dashboard">
			<section class="hero">
				<div class="hero-copy">
					<p class="eyebrow">
						{{ t('empleados', 'Employees module') }}
					</p>
					<h1>{{ t('empleados', 'Bienvenido al módulo Empleados') }}</h1>
					<p class="hero-description">
						{{ t('empleados', 'Consulta información laboral, administra equipos y accede a los procesos básicos de capital humano desde un solo lugar.') }}
					</p>
				</div>

				<div class="hero-actions">
					<NcButton
						v-if="isAdmin"
						type="primary"
						@click="go('Empleados')">
						<template #icon>
							<BadgeAccountAlert :size="20" />
						</template>
						{{ t('empleados', 'Employees') }}
					</NcButton>
					<NcButton
						v-if="configuraciones.modulo_ausencias === 'true'"
						@click="go('Calendario')">
						<template #icon>
							<CalendarBlank :size="20" />
						</template>
						{{ t('empleados', 'Calendar') }}
					</NcButton>
					<NcButton
						v-if="configuraciones.modulo_reporte_tiempos === 'true'"
						@click="go('Reports')">
						<template #icon>
							<CalendarClock :size="20" />
						</template>
						{{ t('empleados', 'My reports') }}
					</NcButton>
				</div>
			</section>

			<section class="summary-grid">
				<div
					v-for="item in summaryCards"
					:key="item.key"
					class="summary-card">
					<div class="summary-icon">
						<component :is="item.icon" :size="22" />
					</div>
					<div>
						<p>{{ item.label }}</p>
						<strong>{{ loading ? '...' : item.value }}</strong>
					</div>
				</div>
			</section>

			<NcNoteCard
				v-if="!isAdmin"
				type="info"
				class="notice">
				{{ t('empleados', 'Este inicio muestra accesos disponibles para tu usuario. Las métricas de capital humano solo se cargan para usuarios con permisos de administración o recursos humanos.') }}
			</NcNoteCard>

			<section class="content-grid">
				<div class="panel">
					<div class="panel-head">
						<div>
							<p class="eyebrow">
								{{ t('empleados', 'Start here') }}
							</p>
							<h2>{{ t('empleados', 'Accesos rápidos') }}</h2>
						</div>
					</div>

					<div class="quick-grid">
						<button
							v-for="action in quickActions"
							:key="action.route"
							class="quick-action"
							type="button"
							@click="go(action.route)">
							<span class="quick-icon">
								<component :is="action.icon" :size="22" />
							</span>
							<span>
								<strong>{{ action.title }}</strong>
								<small>{{ action.description }}</small>
							</span>
						</button>
					</div>
				</div>

				<div class="panel">
					<div class="panel-head">
						<div>
							<p class="eyebrow">
								{{ t('empleados', 'Organization') }}
							</p>
							<h2>{{ t('empleados', 'Estructura actual') }}</h2>
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

					<div v-if="loading" class="empty-state">
						<NcLoadingIcon :size="28" />
						<span>{{ t('empleados', 'Loading') }}</span>
					</div>
					<div v-else-if="isAdmin" class="structure-list">
						<div
							v-for="item in structureItems"
							:key="item.label"
							class="structure-item">
							<span>{{ item.label }}</span>
							<strong>{{ item.value }}</strong>
						</div>
					</div>
					<div v-else class="empty-state">
						<span>{{ t('empleados', 'No hay métricas disponibles para este perfil.') }}</span>
					</div>
				</div>

				<div class="panel panel-wide">
					<div class="panel-head">
						<div>
							<p class="eyebrow">
								{{ t('empleados', 'Useful context') }}
							</p>
							<h2>{{ t('empleados', 'Qué puedes hacer en este módulo') }}</h2>
						</div>
					</div>

					<div class="feature-grid">
						<div
							v-for="feature in features"
							:key="feature.title"
							class="feature">
							<div class="feature-icon">
								<component :is="feature.icon" :size="22" />
							</div>
							<div>
								<strong>{{ feature.title }}</strong>
								<p>{{ feature.description }}</p>
							</div>
						</div>
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
import FileChartOutline from 'vue-material-design-icons/FileChartOutline.vue'
import FileSign from 'vue-material-design-icons/FileSign.vue'
import OfficeBuilding from 'vue-material-design-icons/OfficeBuilding.vue'
import Reload from 'vue-material-design-icons/Reload.vue'

export default {
	name: 'Dashboard',

	components: {
		AccountGroup,
		AccountTieOutline,
		BadgeAccountAlert,
		Bank,
		CalendarBlank,
		CalendarClock,
		FileChartOutline,
		FileSign,
		NcAppContent,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		OfficeBuilding,
		Reload,
	},

	inject: {
		groupuser: { default: () => ({}) },
		configuraciones: { default: () => ({}) },
		subordinates: { default: () => [] },
	},

	data() {
		return {
			loading: false,
			empleados: [],
			areas: [],
			puestos: [],
			equipos: [],
		}
	},

	computed: {
		isAdmin() {
			return 'admin' in this.groupuser || 'recursos_humanos' in this.groupuser
		},

		summaryCards() {
			return [
				{
					key: 'empleados',
					label: t('empleados', 'Employees'),
					value: this.isAdmin ? this.empleados.length : '-',
					icon: 'BadgeAccountAlert',
				},
				{
					key: 'areas',
					label: t('empleados', 'Areas / Departments'),
					value: this.isAdmin ? this.areas.length : '-',
					icon: 'OfficeBuilding',
				},
				{
					key: 'equipos',
					label: t('empleados', 'Teams'),
					value: this.isAdmin ? this.equipos.length : '-',
					icon: 'AccountGroup',
				},
				{
					key: 'puestos',
					label: t('empleados', 'Positions'),
					value: this.isAdmin ? this.puestos.length : '-',
					icon: 'AccountTieOutline',
				},
			]
		},

		quickActions() {
			const actions = []

			if (this.isAdmin) {
				actions.push(
					{
						route: 'Empleados',
						title: t('empleados', 'Employees'),
						description: t('empleados', 'Consulta y actualiza expedientes.'),
						icon: 'BadgeAccountAlert',
					},
					{
						route: 'Areas',
						title: t('empleados', 'Areas / Departments'),
						description: t('empleados', 'Mantén ordenada la estructura.'),
						icon: 'OfficeBuilding',
					},
					{
						route: 'Equipos',
						title: t('empleados', 'Teams'),
						description: t('empleados', 'Revisa responsables y miembros.'),
						icon: 'AccountGroup',
					},
				)
			}

			if (this.configuraciones.modulo_ausencias === 'true') {
				actions.push({
					route: 'Calendario',
					title: t('empleados', 'Calendar'),
					description: t('empleados', 'Solicitudes, vacaciones y permisos.'),
					icon: 'CalendarBlank',
				})
			}

			if (this.configuraciones.modulo_reporte_tiempos === 'true') {
				actions.push({
					route: 'Reports',
					title: t('empleados', 'My reports'),
					description: t('empleados', 'Registra y consulta tiempo reportado.'),
					icon: 'CalendarClock',
				})
			}

			if (this.configuraciones.modulo_ahorro === 'true') {
				actions.push({
					route: 'Ahorros',
					title: t('empleados', 'Savings module'),
					description: t('empleados', 'Consulta o solicita movimientos.'),
					icon: 'Bank',
				})
			}

			return actions
		},

		structureItems() {
			return [
				{ label: t('empleados', 'Registered employees'), value: this.empleados.length },
				{ label: t('empleados', 'Departments with records'), value: this.countWithEmployees(this.areas) },
				{ label: t('empleados', 'Teams with members'), value: this.countWithEmployees(this.equipos) },
				{ label: t('empleados', 'Defined positions'), value: this.puestos.length },
			]
		},

		features() {
			return [
				{
					title: t('empleados', 'Employee files'),
					description: t('empleados', 'Datos laborales, personales, notas y documentos en una vista centralizada.'),
					icon: 'BadgeAccountAlert',
				},
				{
					title: t('empleados', 'Organization structure'),
					description: t('empleados', 'Áreas, puestos y equipos conectados con cada empleado.'),
					icon: 'AccountGroup',
				},
				{
					title: t('empleados', 'Working time'),
					description: t('empleados', 'Calendario de ausencias y reportes de tiempo cuando los módulos están activos.'),
					icon: 'CalendarClock',
				},
				{
					title: t('empleados', 'Requests'),
					description: t('empleados', 'Accesos a solicitudes y paneles operativos según tus permisos.'),
					icon: 'FileSign',
				},
			]
		},
	},

	mounted() {
		this.loadData()
	},

	methods: {
		t,

		go(routeName) {
			this.$router.push({ name: routeName })
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

				this.empleados = this.getData(empleados)?.Empleados || []
				this.areas = this.getData(areas)
				this.puestos = this.getData(puestos)
				this.equipos = this.getData(equipos)
			} catch (err) {
				this.empleados = []
				this.areas = []
				this.puestos = []
				this.equipos = []
			} finally {
				this.loading = false
			}
		},

		getData(response) {
			const data = response?.data?.ocs?.data ?? response?.data ?? []
			return Array.isArray(data) || typeof data === 'object' ? data : []
		},

		countWithEmployees(items) {
			return items.filter((item) => Number(item.cantidad_empleados || 0) > 0).length
		},
	},
}
</script>

<style scoped>
.dashboard {
	display: flex;
	flex-direction: column;
	gap: 18px;
	padding: 24px 32px;
}

.hero {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	gap: 18px;
	align-items: end;
	padding: 24px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
}

.hero-copy {
	max-width: 760px;
}

.eyebrow {
	margin: 0 0 6px;
	color: var(--color-primary-element);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.hero h1 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 30px;
	font-weight: 700;
	line-height: 1.2;
}

.hero-description {
	margin: 10px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 15px;
	line-height: 1.5;
}

.hero-actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 10px;
}

.summary-grid {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 14px;
}

.summary-card,
.panel {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.summary-card {
	display: flex;
	align-items: center;
	gap: 12px;
	min-width: 0;
	padding: 16px;
}

.summary-icon,
.quick-icon,
.feature-icon {
	display: inline-flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	width: 40px;
	height: 40px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-primary-element);
}

.summary-card p {
	margin: 0 0 4px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 600;
}

.summary-card strong {
	color: var(--color-main-text);
	font-size: 26px;
	line-height: 1;
}

.notice {
	margin: 0;
}

.content-grid {
	display: grid;
	grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
	gap: 18px;
}

.panel {
	padding: 18px;
}

.panel-wide {
	grid-column: 1 / -1;
}

.panel-head {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 14px;
}

.panel h2 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 18px;
	font-weight: 700;
}

.quick-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 10px;
}

.quick-action {
	display: flex;
	align-items: center;
	gap: 12px;
	min-height: 74px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
	text-align: left;
	cursor: pointer;
	transition: border-color 120ms ease, background-color 120ms ease;
}

.quick-action:hover,
.quick-action:focus {
	border-color: var(--color-primary-element-light);
	background: var(--color-background-hover);
	outline: none;
}

.quick-action strong,
.feature strong {
	display: block;
	color: var(--color-main-text);
	font-size: 14px;
}

.quick-action small,
.feature p {
	display: block;
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.4;
}

.structure-list {
	display: grid;
	gap: 8px;
}

.structure-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.structure-item span {
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.structure-item strong {
	color: var(--color-main-text);
	font-size: 18px;
}

.empty-state {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 160px;
	gap: 10px;
	color: var(--color-text-maxcontrast);
	font-weight: 600;
	text-align: center;
}

.feature-grid {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 12px;
}

.feature {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	min-width: 0;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

@media (max-width: 1100px) {
	.summary-grid,
	.feature-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.content-grid {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 768px) {
	.dashboard {
		padding: 14px;
	}

	.hero {
		grid-template-columns: 1fr;
		padding: 18px;
	}

	.hero h1 {
		font-size: 24px;
	}

	.hero-actions {
		justify-content: flex-start;
	}

	.summary-grid,
	.quick-grid,
	.feature-grid {
		grid-template-columns: 1fr;
	}

	.panel {
		padding: 14px;
	}
}
</style>
