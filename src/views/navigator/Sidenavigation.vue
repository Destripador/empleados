<template>
	<div class="empleados-nav-root">
		<!-- Botón de apertura/cierre: fuera de NcAppNavigation para que nunca quede recortado -->
		<button
			class="side-toggle-button"
			type="button"
			:class="{ 'side-toggle-button--open': isMobile && navigationMode !== 'hidden' }"
			:style="{ insetInlineStart: toggleOffset }"
			:title="navigationModeLabel"
			:aria-label="navigationModeLabel"
			:aria-expanded="(navigationMode !== 'hidden').toString()"
			@click="toggleNavigationMode">
			<span class="side-toggle-icon">
				<span />
				<span />
				<span />
			</span>
		</button>

		<!-- Fondo oscuro solo en móvil, cuando el menú está abierto -->
		<div
			v-if="isMobile && navigationMode !== 'hidden'"
			class="mobile-nav-backdrop"
			@click="closeNavigation" />

		<component
			:is="navRootTag"
			class="empleados-side-navigation"
			:class="[
				`empleados-side-navigation--${navigationMode}`,
				{ 'empleados-side-navigation--mobile': isMobile },
			]">
			<div v-show="navigationMode !== 'hidden'" class="side-navigation-content" @click="onContentClick">
				<!-- General -->
				<NcAppNavigationCaption v-if="navigationMode === 'normal'"
					:heading-id="t('empleados', 'General')"
					is-heading
					:name="t('empleados', 'General')" />

				<NcAppNavigationList :aria-labelledby="t('empleados', 'General')">
					<NcAppNavigationItem :name="t('empleados', 'Home')" :to="{ name: 'Home' }" exact>
						<template #icon>
							<ViewDashboard :size="20" />
						</template>
					</NcAppNavigationItem>

					<NcAppNavigationItem :name="t('empleados', 'Office simulation')" :to="{ name: 'SimulacionOficina' }">
						<template #icon>
							<OfficeBuildingMarker :size="20" />
						</template>
					</NcAppNavigationItem>
				</NcAppNavigationList>

				<!-- Human Resources -->
				<div v-if="canSeeHumanResources">
					<NcAppNavigationCaption v-if="navigationMode === 'normal'"
						:heading-id="t('empleados', 'Human Resources')"
						is-heading
						:name="t('empleados', 'Human Resources')" />

					<NcAppNavigationList :aria-labelledby="t('empleados', 'Human Resources')">
						<NcAppNavigationItem :name="t('empleados', 'Employees')" :to="{ name: 'Empleados' }">
							<template #icon>
								<BadgeAccountAlert :size="20" />
							</template>
						</NcAppNavigationItem>

						<NcAppNavigationItem :name="t('empleados', 'Areas / Departments')" :to="{ name: 'Areas' }">
							<template #icon>
								<OfficeBuilding :size="20" />
							</template>
						</NcAppNavigationItem>

						<NcAppNavigationItem :name="t('empleados', 'Positions')" :to="{ name: 'Puestos' }">
							<template #icon>
								<AccountTieOutline :size="20" />
							</template>
						</NcAppNavigationItem>

						<NcAppNavigationItem :name="t('empleados', 'Teams')" :to="{ name: 'Equipos' }">
							<template #icon>
								<AccountGroup :size="20" />
							</template>
						</NcAppNavigationItem>
					</NcAppNavigationList>
				</div>

				<!-- Purchases -->
				<div v-if="canSeePurchases">
					<NcAppNavigationCaption v-if="navigationMode === 'normal'"
						:heading-id="t('empleados', 'Purchases')"
						is-heading
						:name="t('empleados', 'Purchases')" />

					<NcAppNavigationList :aria-labelledby="t('empleados', 'Purchases')">
						<NcAppNavigationItem :name="t('empleados', 'Purchase requests')" :to="{ name: 'compras' }">
							<template #icon>
								<CartOutline :size="20" />
							</template>
						</NcAppNavigationItem>
					</NcAppNavigationList>
				</div>

				<!-- IT Inventory -->
				<div v-if="canSeeInventory">
					<NcAppNavigationCaption v-if="navigationMode === 'normal'"
						:heading-id="t('empleados', 'IT Management')"
						is-heading
						:name="t('empleados', 'IT Management')" />

					<NcAppNavigationList :aria-labelledby="t('empleados', 'IT Management')">
						<NcAppNavigationItem
							:name="t('empleados', 'Inventory and support')"
							:to="{ name: 'Inventario' }"
							exact>
							<template #icon>
								<Laptop :size="20" />
							</template>
						</NcAppNavigationItem>
						<NcAppNavigationItem v-if="canSeeMaintenance"
							:name="t('empleados', 'Maintenance calendar')"
							:to="{ name: 'Mantenimientos' }">
							<template #icon>
								<CalendarMonth :size="20" />
							</template>
						</NcAppNavigationItem>
					</NcAppNavigationList>
				</div>

				<!-- Time Reports -->
				<div v-if="reportTimesEnabled">
					<NcAppNavigationCaption v-if="navigationMode === 'normal'"
						:heading-id="t('empleados', 'Time Reports')"
						is-heading
						:name="t('empleados', 'Time Reports')" />

					<NcAppNavigationList :aria-labelledby="t('empleados', 'Time Reports')">
						<NcAppNavigationItem :name="t('empleados', 'My reports')" :to="{ name: 'Reports' }">
							<template #icon>
								<CalendarClock :size="20" />
							</template>
						</NcAppNavigationItem>

						<NcAppNavigationItem v-if="canSeeAdminReports"
							:name="t('empleados', 'Admin reports')"
							:to="{ name: 'Adminreports' }">
							<template #icon>
								<FileChartOutline :size="20" />
							</template>
						</NcAppNavigationItem>

						<NcAppNavigationItem v-if="canSeeAdminReports"
							:name="t('empleados', 'Compliance tracking')"
							:to="{ name: 'cumplimiento-reportes' }">
							<template #icon>
								<FileChartOutline :size="20" />
							</template>
						</NcAppNavigationItem>
					</NcAppNavigationList>
				</div>

				<!-- Savings -->
				<div v-if="savingsEnabled">
					<NcAppNavigationCaption v-if="navigationMode === 'normal'"
						:heading-id="t('empleados', 'Savings')"
						is-heading
						:name="t('empleados', 'Savings')" />

					<NcAppNavigationList :aria-labelledby="t('empleados', 'Savings')">
						<NcAppNavigationItem :name="t('empleados', 'Request')" :to="{ name: 'Ahorros' }">
							<template #icon>
								<FileSign :size="20" />
							</template>
						</NcAppNavigationItem>

						<NcAppNavigationItem v-if="canSeeSavingsAdmin"
							:name="t('empleados', 'Admin panel')"
							:to="{ name: 'PanelAhorros' }">
							<template #icon>
								<Bank :size="20" />
							</template>
						</NcAppNavigationItem>
					</NcAppNavigationList>
				</div>

				<!-- Working Time -->
				<div v-if="absencesEnabled">
					<NcAppNavigationCaption v-if="navigationMode === 'normal'"
						:heading-id="t('empleados', 'Working time')"
						is-heading
						:name="t('empleados', 'Working time')" />

					<NcAppNavigationList :aria-labelledby="t('empleados', 'Working time')">
						<NcAppNavigationItem :name="t('empleados', 'Calendar')" :to="{ name: 'Calendario' }">
							<template #icon>
								<CalendarBlank :size="20" />
							</template>
						</NcAppNavigationItem>
					</NcAppNavigationList>
				</div>

				<!-- Customers -->
				<div v-if="canSeeCustomers">
					<NcAppNavigationCaption v-if="navigationMode === 'normal'"
						:heading-id="t('empleados', 'Customers')"
						is-heading
						:name="t('empleados', 'Customers')" />

					<NcAppNavigationList :aria-labelledby="t('empleados', 'Customers')">
						<NcAppNavigationItem :name="t('empleados', 'Companies / Groups')" :to="{ name: 'CompaniesGroups' }">
							<template #icon>
								<HexagonMultipleOutline :size="20" />
							</template>
						</NcAppNavigationItem>

						<NcAppNavigationItem :name="t('empleados', 'Activities')" :to="{ name: 'Activities' }">
							<template #icon>
								<ViewList :size="20" />
							</template>
						</NcAppNavigationItem>

						<NcAppNavigationItem v-if="canSeeAdminReports"
							:name="t('empleados', 'Costs')"
							:to="{ name: 'Costs' }">
							<template #icon>
								<Cash :size="20" />
							</template>
						</NcAppNavigationItem>
					</NcAppNavigationList>
				</div>

				<!-- ESTACIONAMIENTO -->
				<!--div>
					<NcAppNavigationCaption v-if="navigationMode === 'normal'"
						:heading-id="t('empleados', 'Estacionamiento')"
						is-heading
						:name="t('empleados', 'Estacionamiento')" />
					<NcAppNavigationList :aria-labelledby="t('empleados', 'Estacionamiento')">
						<NcAppNavigationItem
							:name="t('empleados', 'Estacionamiento')"
							:to="{ name: 'Estacionamiento' }">
							<template #icon>
								<HexagonMultipleOutline :size="20" />
							</template>
						</NcAppNavigationItem>
					</NcAppNavigationList>
				</div-->
			</div>
		</component>
	</div>
</template>

<script>
import HexagonMultipleOutline from 'vue-material-design-icons/HexagonMultipleOutline.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import BadgeAccountAlert from 'vue-material-design-icons/BadgeAccountAlert.vue'
import OfficeBuilding from 'vue-material-design-icons/OfficeBuilding.vue'
import AccountTieOutline from 'vue-material-design-icons/AccountTieOutline.vue'
import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'
import FileSign from 'vue-material-design-icons/FileSign.vue'
import ViewList from 'vue-material-design-icons/ViewList.vue'
import Bank from 'vue-material-design-icons/Bank.vue'
import FileChartOutline from 'vue-material-design-icons/FileChartOutline.vue'
import CalendarClock from 'vue-material-design-icons/CalendarClock.vue'
import CalendarBlank from 'vue-material-design-icons/CalendarBlank.vue'
import Laptop from 'vue-material-design-icons/Laptop.vue'
import CartOutline from 'vue-material-design-icons/CartOutline.vue'
import Cash from 'vue-material-design-icons/Cash.vue'
import CalendarMonth from 'vue-material-design-icons/CalendarMonth.vue'
import OfficeBuildingMarker from 'vue-material-design-icons/OfficeBuildingMarker.vue'

import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationCaption from '@nextcloud/vue/dist/Components/NcAppNavigationCaption.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcAppNavigationList from '@nextcloud/vue/dist/Components/NcAppNavigationList.js'

import { translate as t } from '@nextcloud/l10n'
import permissionsMixin from '../../mixins/permissions.js'

const STORAGE_KEY = 'empleados.sideNavigationMode'
const MOBILE_QUERY = '(max-width: 900px)'

// Ancho aproximado del panel por estado, usado solo para posicionar
// el botón flotante junto al borde del panel en escritorio.
const PANEL_WIDTH = {
	normal: 300,
	compact: 72,
	hidden: 0,
}

export default {
	name: 'Sidenavigation',
	components: {
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppNavigationList,
		NcAppNavigationCaption,
		AccountGroup,
		BadgeAccountAlert,
		OfficeBuilding,
		AccountTieOutline,
		ViewDashboard,
		FileSign,
		Bank,
		CalendarBlank,
		HexagonMultipleOutline,
		ViewList,
		FileChartOutline,
		CalendarClock,
		Laptop,
		CartOutline,
		Cash,
		CalendarMonth,
		OfficeBuildingMarker,
	},

	mixins: [permissionsMixin],

	inject: ['groupuser', 'configuraciones', 'subordinates'],

	data() {
		return {
			navigationMode: 'normal',
			isMobile: false,
			mql: null,
		}
	},

	computed: {
		navigationModeLabel() {
			if (this.isMobile) {
				return this.navigationMode === 'hidden'
					? t('empleados', 'Open navigation')
					: t('empleados', 'Close navigation')
			}

			if (this.navigationMode === 'normal') {
				return t('empleados', 'Collapse navigation')
			}

			if (this.navigationMode === 'compact') {
				return t('empleados', 'Hide navigation')
			}

			return t('empleados', 'Show navigation')
		},

		navRootTag() {
			return this.isMobile ? 'div' : 'NcAppNavigation'
		},

		toggleOffset() {
			if (this.isMobile) {
				return '10px'
			}

			const panelWidth = PANEL_WIDTH[this.navigationMode] ?? PANEL_WIDTH.normal
			return `${Math.max(panelWidth - 22, 10)}px`
		},

		canSeeHumanResources() {
			return this.canSeeAny([
				'empleados.hr',
				'empleados.admin',
			])
		},

		canSeeAdminReports() {
			return this.canSee('reporte_tiempos.admin')
				|| this.canSee('reporte_tiempos.view')
				|| this.isTruthy(this.configuraciones?.CanAdminReports)
		},

		canSeeCustomers() {
			return this.canSee('clientes')
		},

		canSeeInventory() {
			return this.canSee('inventario')
				|| this.canSee('soporte')
		},

		canSeeMaintenance() {
			return this.isModuleEnabled('modulo_inventario')
				&& this.canSee('inventario')
		},

		reportTimesEnabled() {
			return this.isModuleEnabled('modulo_reporte_tiempos')
		},

		savingsEnabled() {
			return this.isModuleEnabled('modulo_ahorro')
		},

		canSeeSavingsAdmin() {
			return this.canSee('ahorro.admin')
				|| this.canSeeAny([
					'empleados.hr',
					'empleados.admin',
				])
		},

		absencesEnabled() {
			return this.isModuleEnabled('modulo_ausencias')
		},

		canSeePurchases() {
			return this.canSee('compras')
		},
	},

	watch: {
		navigationMode(value) {
			// El estado del móvil no se persiste: siempre debe arrancar cerrado.
			if (!this.isMobile) {
				this.saveNavigationMode(value)
			}
		},

		// Al cambiar de módulo, cierra el menú automáticamente en móvil.
		$route() {
			if (this.isMobile) {
				this.navigationMode = 'hidden'
			}
		},
	},

	mounted() {
		this.mql = window.matchMedia(MOBILE_QUERY)
		this.isMobile = this.mql.matches
		this.navigationMode = this.isMobile ? 'hidden' : this.getSavedNavigationMode()

		this._onMqlChange = (event) => {
			this.isMobile = event.matches
			this.navigationMode = this.isMobile ? 'hidden' : this.getSavedNavigationMode()
		}

		if (this.mql.addEventListener) {
			this.mql.addEventListener('change', this._onMqlChange)
		} else {
			this.mql.addListener(this._onMqlChange)
		}

		window.addEventListener('keydown', this.onKeyDown)
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.onKeyDown)

		if (this.mql) {
			if (this.mql.removeEventListener) {
				this.mql.removeEventListener('change', this._onMqlChange)
			} else {
				this.mql.removeListener(this._onMqlChange)
			}
		}
	},

	methods: {
		t,

		getSavedNavigationMode() {
			if (typeof window === 'undefined') {
				return 'normal'
			}

			try {
				const value = window.localStorage.getItem(STORAGE_KEY)

				return ['normal', 'compact', 'hidden'].includes(value)
					? value
					: 'normal'
			} catch (error) {
				return 'normal'
			}
		},

		saveNavigationMode(value) {
			if (typeof window === 'undefined') {
				return
			}

			try {
				window.localStorage.setItem(STORAGE_KEY, value)
			} catch (error) {
				// localStorage puede fallar en modo privado o contextos restringidos.
			}
		},

		toggleNavigationMode() {
			// En móvil el menú solo tiene dos estados: abierto o cerrado.
			if (this.isMobile) {
				this.navigationMode = this.navigationMode === 'hidden' ? 'normal' : 'hidden'
				return
			}

			const nextMode = {
				normal: 'compact',
				compact: 'hidden',
				hidden: 'normal',
			}

			this.navigationMode = nextMode[this.navigationMode] || 'normal'
		},

		closeNavigation() {
			this.navigationMode = 'hidden'
		},

		onKeyDown(e) {
			if (e.key === 'Escape' && this.isMobile && this.navigationMode !== 'hidden') {
				this.closeNavigation()
			}
		},

		// Cierra el menú en móvil al tocar cualquier enlace/módulo.
		onContentClick(event) {
			if (!this.isMobile) {
				return
			}

			const target = event.target.closest('a, button')
			if (!target) {
				return
			}

			this.closeNavigation()
		},

		isModuleEnabled(moduleName) {
			return this.isTruthy(this.configuraciones?.[moduleName])
		},
	},
}
</script>
<style scoped lang="scss">
// El wrapper no genera caja propia: no altera el layout flex/grid
// original del que dependía NcAppNavigation.
.empleados-nav-root {
	display: contents;
}

/* ---------------- BOTÓN FLOTANTE (fuera de NcAppNavigation) ---------------- */
.side-toggle-button {
	position: fixed;
	z-index: 2015;
	top: calc(var(--header-height, 50px) + 10px);
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 44px;
	height: 44px;
	padding: 0;
	border: 1px solid var(--color-border);
	border-radius: 50%;
	background: var(--color-main-background);
	color: var(--color-main-text);
	box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
	cursor: pointer;
	transition:
		background-color 120ms ease,
		transform 120ms ease,
		inset-inline-start 180ms ease;
}

.side-toggle-button:hover,
.side-toggle-button:focus-visible {
	background: var(--color-background-hover);
}

.side-toggle-button:active {
	transform: scale(0.94);
}

.side-toggle-icon {
	position: relative;
	width: 18px;
	height: 14px;
}

.side-toggle-icon span {
	position: absolute;
	left: 0;
	width: 18px;
	height: 2px;
	border-radius: 999px;
	background: currentColor;
	transition: transform 200ms ease, opacity 150ms ease, top 200ms ease;
}

.side-toggle-icon span:nth-child(1) { top: 0; }
.side-toggle-icon span:nth-child(2) { top: 6px; }
.side-toggle-icon span:nth-child(3) { top: 12px; }

.side-toggle-button--open .side-toggle-icon span:nth-child(1) {
	top: 6px;
	transform: rotate(45deg);
}

.side-toggle-button--open .side-toggle-icon span:nth-child(2) {
	opacity: 0;
}

.side-toggle-button--open .side-toggle-icon span:nth-child(3) {
	top: 6px;
	transform: rotate(-45deg);
}

/* ---------------- FONDO OSCURO (solo móvil) ---------------- */
.mobile-nav-backdrop {
	position: fixed;
	inset: 0;
	z-index: 2000;
	background: rgba(0, 0, 0, 0.45);
	animation: empleados-backdrop-fade 160ms ease;
}

@keyframes empleados-backdrop-fade {
	from { opacity: 0; }
	to { opacity: 1; }
}

/* ---------------- PANEL ---------------- */
.empleados-side-navigation {
	position: relative;
	width: 300px !important;
	min-width: 300px !important;
	max-width: 300px !important;
	height: 100%;
	overflow: visible !important;
	transition:
		width 160ms ease,
		min-width 160ms ease,
		max-width 160ms ease;
}

/* Oculta el toggle interno de Nextcloud para evitar doble botón */
.empleados-side-navigation :deep(.app-navigation-toggle),
.empleados-side-navigation :deep(.app-navigation__toggle),
.empleados-side-navigation :deep(.app-navigation-toggle-wrapper),
.empleados-side-navigation :deep(button.app-navigation-toggle) {
	display: none !important;
}

.side-navigation-content {
	width: 100%;
	box-sizing: border-box;
}

/* ---------------- NORMAL (escritorio) ---------------- */
/* ya cubierto por .empleados-side-navigation */

/* ---------------- COMPACT (escritorio) ---------------- */
.empleados-side-navigation--compact {
	width: 72px !important;
	min-width: 72px !important;
	max-width: 72px !important;
}

.empleados-side-navigation--compact .side-navigation-content {
	display: flex;
	flex-direction: column;
	align-items: center;
	width: 100%;
	padding-top: 5px;
}

.empleados-side-navigation--compact :deep(.app-navigation-caption) {
	display: none !important;
}

.empleados-side-navigation--compact :deep(.app-navigation-list) {
	width: 100%;
}

.empleados-side-navigation--compact :deep(.app-navigation-entry) {
	width: 40px !important;
	min-width: auto !important;
	max-width: auto !important;
	margin-right: auto !important;
	margin-left: auto !important;
}

.empleados-side-navigation--compact :deep(.app-navigation-entry__link) {
	justify-content: center !important;
	width: 48px !important;
	min-width: 48px !important;
	padding-right: 0 !important;
	padding-left: 0 !important;
}

.empleados-side-navigation--compact :deep(.app-navigation-entry__icon) {
	margin: 0 !important;
}

.empleados-side-navigation--compact :deep(.app-navigation-entry__utils),
.empleados-side-navigation--compact :deep(.app-navigation-entry__counter),
.empleados-side-navigation--compact :deep(.app-navigation-entry__title),
.empleados-side-navigation--compact :deep(.app-navigation-entry__name),
.empleados-side-navigation--compact :deep(.app-navigation-entry__text),
.empleados-side-navigation--compact :deep(.app-navigation-entry__children),
.empleados-side-navigation--compact :deep(.app-navigation-entry__caption) {
	display: none !important;
}

/* ---------------- HIDDEN (escritorio) ---------------- */
.empleados-side-navigation--hidden:not(.empleados-side-navigation--mobile) {
	width: 0 !important;
	min-width: 0 !important;
	max-width: 0 !important;
	border: 0 !important;
	background: transparent !important;
	overflow: visible !important;
}

.empleados-side-navigation--mobile .side-navigation-content {
	height: 100%;
	padding: 60px 6px 24px;
	overflow-y: auto;
	-webkit-overflow-scrolling: touch;
}

.empleados-side-navigation--hidden:not(.empleados-side-navigation--mobile) .side-navigation-content {
	display: none !important;
}

/* ---------------- MÓVIL: panel como cajón deslizante ---------------- */
.empleados-side-navigation--mobile {
	position: fixed !important;
	top: var(--header-height, 50px);
	bottom: 0;
	left: 0;
	height: calc(100% - var(--header-height, 50px));
	width: min(300px, 84vw) !important;
	min-width: min(300px, 84vw) !important;
	max-width: min(300px, 84vw) !important;
	z-index: 2005;
	background: var(--color-main-background);
	box-shadow: 8px 0 30px rgba(0, 0, 0, 0.22);
	border-radius: 0 18px 18px 0;
	transform: translateX(-100%) !important;
	transition: transform 220ms ease;
	visibility: visible !important;
	opacity: 1 !important;
}

.empleados-side-navigation--mobile.empleados-side-navigation--normal {
	transform: translateX(0) !important;
}

@media (max-width: 900px) {
	.side-toggle-button {
		top: calc(var(--header-height, 50px) + 3.5px) !important;
		inset-inline-start: 11px !important;
		transition:
			background-color 120ms ease,
			transform 120ms ease;
	}
}
</style>
