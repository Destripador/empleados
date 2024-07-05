<template>
	<NcAppNavigation>
		<template #list>
			<NcAppNavigationItem name="Inicio" href="/index.php/apps/ahorrosgossler/">
				<template #icon>
					<!--Home :size="20" /-->
				</template>
			</NcAppNavigationItem>

			<NcAppNavigationItem name="Solicitar" href="/index.php/apps/ahorrosgossler/solicitar">
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
