<template>
	<NcModal :name="t('empleados', 'Importar clientes')" @close="$emit('close')">
		<div class="modal-content">
			<h2>{{ t('empleados', 'Importar clientes') }}</h2>
			<p>{{ t('empleados', 'Funciona') }}</p>
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
