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

		<!-- Capital Humano (solo admin / RH) -->
		<div v-if="isAdmin()">
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

		<!-- REPORTE DE TIEMPOS -->
		<div v-if="configuraciones.modulo_reporte_tiempos === 'true'">
			<NcAppNavigationCaption
				:heading-id="t('empleados', 'Report Times')"
				is-heading
				:name="t('empleados', 'Report Times')" />
			<NcAppNavigationList :aria-labelledby="t('empleados', 'Report Times')">
				<NcAppNavigationItem
					:name="t('empleados', 'My reports | Create report')"
					:to="{ name: 'Reports' }">
					<template #icon>
						<CalendarClock :size="20" />
					</template>
				</NcAppNavigationItem>

				<NcAppNavigationItem
					v-if="(subordinates?.length || 0) > 0"
					:name="t('empleados', 'Admin reports')"
					:to="{ name: 'Adminreports' }">
					<template #icon>
						<FileChartOutline :size="20" />
					</template>
				</NcAppNavigationItem>
			</NcAppNavigationList>
		</div>

		<!-- AHORRO -->
		<div v-if="ahorroModulo()">
			<NcAppNavigationCaption
				:heading-id="t('empleados', 'Savings module')"
				is-heading
				:name="t('empleados', 'Savings module')" />
			<NcAppNavigationList :aria-labelledby="t('empleados', 'Savings module')">
				<NcAppNavigationItem
					:name="t('empleados', 'Request')"
					:to="{ name: 'Ahorros' }">
					<template #icon>
						<FileSign :size="20" />
					</template>
				</NcAppNavigationItem>
			</NcAppNavigationList>
			<NcAppNavigationList v-if="isAdmin()" :aria-labelledby="t('empleados', 'Savings module')">
				<NcAppNavigationItem
					:name="t('empleados', 'Admin panel')"
					:to="{ name: 'PanelAhorros' }">
					<template #icon>
						<Bank :size="20" />
					</template>
				</NcAppNavigationItem>
			</NcAppNavigationList>
		</div>

		<!-- Ausencias -->
		<div v-if="ausenciasModulo()">
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

		<!-- CLIENTES -->
		<div v-if="isAdmin() && configuraciones.modulo_clientes === 'true'">
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
// ICONOS
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
	},
	inject: ['groupuser', 'configuraciones', 'subordinates'],
	methods: {
		t, // expone t al template
		navigateTo(route) {
			this.$router.push({ name: route })
		},
		isAdmin() {
			return 'admin' in this.groupuser || 'recursos_humanos' in this.groupuser
		},
		ahorroModulo() {
			return this.configuraciones.modulo_ahorro === 'true'
		},
		ausenciasModulo() {
			return this.configuraciones.modulo_ausencias === 'true'
		},
	},
}
</script>
