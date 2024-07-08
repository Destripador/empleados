<template>
	<NcAppNavigation>
		<template #list>
			<NcAppNavigationItem name="Inicio" href="/index.php/apps/empleados/">
				<template #icon>
					<!--Home :size="20" /-->
				</template>
			</NcAppNavigationItem>

			<NcAppNavigationItem name="Empleados" href="/index.php/apps/empleados">
				<template #icon>
					<!--FileDocumentPlusOutline :size="20" /-->
				</template>
			</NcAppNavigationItem>

			<NcAppNavigationItem name="Puestos" href="/index.php/apps/empleados/Puestos">
				<template #icon>
					<!--FileDocumentPlusOutline :size="20" /-->
				</template>
			</NcAppNavigationItem>

			<NcAppNavigationItem name="Areas" href="/index.php/apps/empleados/Areas">
				<template #icon>
					<!--FileDocumentPlusOutline :size="20" /-->
				</template>
			</NcAppNavigationItem>
		</template>
	</NcAppNavigation>
</template>

<script>
import {
	NcAppNavigation,
	NcAppNavigationItem,
} from '@nextcloud/vue'

export default {
	name: 'Sidenavigation',
	components: {
		NcAppNavigation,
		NcAppNavigationItem,
	},
}
</script>

<!--style scoped lang="scss">
</style-->
