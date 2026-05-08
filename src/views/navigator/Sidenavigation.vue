<template>
	<NcAppNavigation class="empleados-side-navigation" :class="[`empleados-side-navigation--${navigationMode}`]">
		<button class="side-toggle-button side-toggle-button--floating"
			type="button"
			:title="navigationModeLabel"
			:aria-label="navigationModeLabel"
			@click="toggleNavigationMode">
			<span class="side-toggle-icon">
				<span />
				<span />
				<span />
			</span>
		</button>

		<div v-show="navigationMode !== 'hidden'" class="side-navigation-content">
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
					<NcAppNavigationItem :name="t('empleados', 'Inventory and support')" :to="{ name: 'Inventario' }">
						<template #icon>
							<Laptop :size="20" />
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

					<NcAppNavigationItem v-if="canSeeHumanResources"
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
				</NcAppNavigationList>
			</div>
		</div>
	</NcAppNavigation>
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

import {
	NcAppNavigation,
	NcAppNavigationItem,
	NcAppNavigationList,
	NcAppNavigationCaption,
} from '@nextcloud/vue'

import { translate as t } from '@nextcloud/l10n'

const STORAGE_KEY = 'empleados.sideNavigationMode'

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
	},

	inject: ['groupuser', 'configuraciones', 'subordinates'],

	data() {
		return {
			navigationMode: 'normal',
		}
	},

	computed: {
		navigationModeLabel() {
			if (this.navigationMode === 'normal') {
				return t('empleados', 'Collapse navigation')
			}

			if (this.navigationMode === 'compact') {
				return t('empleados', 'Hide navigation')
			}

			return t('empleados', 'Show navigation')
		},

		canSeeHumanResources() {
			return this.hasGroup('admin') || this.hasGroup('recursos_humanos')
		},

		canSeeAdminReports() {
			return this.isTruthy(this.configuraciones?.CanAdminReports)
		},

		canSeeCustomers() {
			return this.canSeeHumanResources && this.isModuleEnabled('modulo_clientes')
		},

		canSeeInventory() {
			return this.canSeeHumanResources
				&& (
					this.isModuleEnabled('modulo_inventario')
					|| this.isModuleEnabled('modulo_soporte')
				)
		},

		reportTimesEnabled() {
			return this.isModuleEnabled('modulo_reporte_tiempos')
		},

		savingsEnabled() {
			return this.isModuleEnabled('modulo_ahorro')
		},

		absencesEnabled() {
			return this.isModuleEnabled('modulo_ausencias')
		},

		canSeePurchases() {
			return this.isModuleEnabled('modulo_compras')
				&& (
					this.hasGroup('admin')
					|| this.hasGroup('compras_admin')
					|| this.hasGroup('compras_autorizadores')
					|| this.hasGroup('compras_contabilidad')
					|| this.hasGroup('compras_solicitantes')
				)
		},
	},

	watch: {
		navigationMode(value) {
			this.saveNavigationMode(value)
		},
	},

	mounted() {
		this.navigationMode = this.getSavedNavigationMode()
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
			const nextMode = {
				normal: 'compact',
				compact: 'hidden',
				hidden: 'normal',
			}

			this.navigationMode = nextMode[this.navigationMode] || 'normal'
		},

		hasGroup(groupName) {
			if (!groupName || !this.groupuser) {
				return false
			}

			if (Array.isArray(this.groupuser)) {
				return this.groupuser.includes(groupName)
					|| this.groupuser.some(group => {
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

		isModuleEnabled(moduleName) {
			return this.isTruthy(this.configuraciones?.[moduleName])
		},
	},
}
</script>
<style scoped lang="scss">
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

.side-toggle-button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 40px;
	height: 40px;
	padding: 0;
	border: 0;
	border-radius: 10px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	cursor: pointer;
}

.side-toggle-button:hover,
.side-toggle-button:focus {
	background: var(--color-background-hover);
}

.side-toggle-button--floating {
	position: absolute;
	z-index: 50;
	top: 6px;
	right: -50px;
}

.side-toggle-icon {
	display: flex;
	flex-direction: column;
	gap: 4px;
	width: 18px;
}

.side-toggle-icon span {
	display: block;
	width: 18px;
	height: 2px;
	border-radius: 999px;
	background: currentColor;
}

.side-navigation-content {
	width: 100%;
	box-sizing: border-box;
}

/* ---------------- NORMAL ---------------- */
/* ya cubierto por .empleados-side-navigation */

/* ---------------- COMPACT ---------------- */
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

/* ---------------- HIDDEN ---------------- */
/* Aquí sí queda sin ocupar espacio */
.empleados-side-navigation--hidden {
	width: 0 !important;
	min-width: 0 !important;
	max-width: 0 !important;
	border: 0 !important;
	background: transparent !important;
	overflow: visible !important;
}

.empleados-side-navigation--hidden .side-navigation-content {
	display: none !important;
}

</style>
