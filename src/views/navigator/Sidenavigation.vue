<template>
	<NcAppNavigation>
		<!-- General -->
		<NcAppNavigationCaption
			:heading-id="t('empleados', 'General')"
			is-heading
			:name="t('empleados', 'General')" />

		<NcAppNavigationList :aria-labelledby="t('empleados', 'General')">
			<NcAppNavigationItem
				:name="t('empleados', 'Home')"
				:to="{ name: 'Home' }"
				exact>
				<template #icon>
					<ViewDashboard :size="20" />
				</template>
			</NcAppNavigationItem>
		</NcAppNavigationList>

		<!-- Human Resources -->
		<div v-if="canSeeHumanResources">
			<NcAppNavigationCaption
				:heading-id="t('empleados', 'Human Resources')"
				is-heading
				:name="t('empleados', 'Human Resources')" />

			<NcAppNavigationList :aria-labelledby="t('empleados', 'Human Resources')">
				<NcAppNavigationItem
					:name="t('empleados', 'Employees')"
					:to="{ name: 'Empleados' }">
					<template #icon>
						<BadgeAccountAlert :size="20" />
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem
					:name="t('empleados', 'Areas / Departments')"
					:to="{ name: 'Areas' }">
					<template #icon>
						<OfficeBuilding :size="20" />
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem
					:name="t('empleados', 'Positions')"
					:to="{ name: 'Puestos' }">
					<template #icon>
						<AccountTieOutline :size="20" />
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem
					:name="t('empleados', 'Teams')"
					:to="{ name: 'Equipos' }">
					<template #icon>
						<AccountGroup :size="20" />
					</template>
				</NcAppNavigationItem>
			</NcAppNavigationList>
		</div>

		<!-- Purchases -->
		<div v-if="canSeePurchases">
			<NcAppNavigationCaption
				:heading-id="t('empleados', 'Purchases')"
				is-heading
				:name="t('empleados', 'Purchases')" />

			<NcAppNavigationList :aria-labelledby="t('empleados', 'Purchases')">
				<NcAppNavigationItem
					:name="t('empleados', 'Purchase requests')"
					:to="{ name: 'compras' }">
					<template #icon>
						<CartOutline :size="20" />
					</template>
				</NcAppNavigationItem>
			</NcAppNavigationList>
		</div>

		<!-- IT Inventory -->
		<div v-if="canSeeInventory">
			<NcAppNavigationCaption
				:heading-id="t('empleados', 'IT Management')"
				is-heading
				:name="t('empleados', 'IT Management')" />

			<NcAppNavigationList :aria-labelledby="t('empleados', 'IT Management')">
				<NcAppNavigationItem
					:name="t('empleados', 'Inventory and support')"
					:to="{ name: 'Inventario' }">
					<template #icon>
						<Laptop :size="20" />
					</template>
				</NcAppNavigationItem>
			</NcAppNavigationList>
		</div>

		<!-- Time Reports -->
		<div v-if="reportTimesEnabled">
			<NcAppNavigationCaption
				:heading-id="t('empleados', 'Time Reports')"
				is-heading
				:name="t('empleados', 'Time Reports')" />

			<NcAppNavigationList :aria-labelledby="t('empleados', 'Time Reports')">
				<NcAppNavigationItem
					:name="t('empleados', 'My reports')"
					:to="{ name: 'Reports' }">
					<template #icon>
						<CalendarClock :size="20" />
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem
					v-if="canSeeAdminReports"
					:name="t('empleados', 'Admin reports')"
					:to="{ name: 'Adminreports' }">
					<template #icon>
						<FileChartOutline :size="20" />
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem
					v-if="canSeeAdminReports"
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
			<NcAppNavigationCaption
				:heading-id="t('empleados', 'Savings')"
				is-heading
				:name="t('empleados', 'Savings')" />

			<NcAppNavigationList :aria-labelledby="t('empleados', 'Savings')">
				<NcAppNavigationItem
					:name="t('empleados', 'Request')"
					:to="{ name: 'Ahorros' }">
					<template #icon>
						<FileSign :size="20" />
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem
					v-if="canSeeHumanResources"
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
			<NcAppNavigationCaption
				:heading-id="t('empleados', 'Working time')"
				is-heading
				:name="t('empleados', 'Working time')" />

			<NcAppNavigationList :aria-labelledby="t('empleados', 'Working time')">
				<NcAppNavigationItem
					:name="t('empleados', 'Calendar')"
					:to="{ name: 'Calendario' }">
					<template #icon>
						<CalendarBlank :size="20" />
					</template>
				</NcAppNavigationItem>
			</NcAppNavigationList>
		</div>

		<!-- Customers -->
		<div v-if="canSeeCustomers">
			<NcAppNavigationCaption
				:heading-id="t('empleados', 'Customers')"
				is-heading
				:name="t('empleados', 'Customers')" />

			<NcAppNavigationList :aria-labelledby="t('empleados', 'Customers')">
				<NcAppNavigationItem
					:name="t('empleados', 'Companies / Groups')"
					:to="{ name: 'CompaniesGroups' }">
					<template #icon>
						<HexagonMultipleOutline :size="20" />
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem
					:name="t('empleados', 'Activities')"
					:to="{ name: 'Activities' }">
					<template #icon>
						<ViewList :size="20" />
					</template>
				</NcAppNavigationItem>
			</NcAppNavigationList>
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

	computed: {
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

	methods: {
		t,

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
