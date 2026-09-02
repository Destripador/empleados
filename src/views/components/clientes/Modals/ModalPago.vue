<template>
	<NcModal v-if="open"
		size="small"
		:name="t('empleados', 'Register Payment')"
		@close="$emit('close')">
		<div class="payment-modal">
			<div class="payment-icon-wrapper payment-icon-wrapper--pago">
				<div class="payment-icon">
					💳
				</div>
			</div>
			<h2>{{ t('empleados', 'Register Payment') }}</h2>
			<p class="payment-subtitle">
				{{ t('empleados', 'Select the payment date for this installment.') }}
			</p>
			<div class="payment-field">
				<NcTextField :value="fecha"
					type="date"
					:label="t('empleados', 'Payment date')"
					@update:value="$emit('update:fecha', $event)" />
			</div>

			<div class="payment-actions">
				<NcButton @click="$emit('close')">
					{{ t('empleados', 'Cancel') }}
				</NcButton>
				<NcButton type="primary" @click="$emit('save')">
					{{ t('empleados', 'Save') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcModal, NcTextField, NcButton } from '@nextcloud/vue'

export default {
	name: 'ModalPago',

	components: { NcModal, NcTextField, NcButton },

	props: {
		open: { type: Boolean, default: false },
		fecha: { type: String, default: '' },
	},

	methods: { t },
}
</script>

<style scoped lang="scss">
@import './modales-pago.scss';
</style>
