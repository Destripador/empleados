<template>
	<NcModal :name="t('empleados', 'Import customers')" @close="$emit('close')">
		<div class="modal-content">
			<h2>{{ t('empleados', 'Import customers') }}</h2>
			<p>{{ t('empleados', 'This feature is not available yet.') }}</p>
		</div>
	</NcModal>
</template>

<script>
import { NcModal } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'ModalClientes',

	components: {
		NcModal,
	},

	methods: {
		t,
	},
}
</script>

<style scoped lang="scss">
.modal-content {
	padding: 24px;
}
</style>
