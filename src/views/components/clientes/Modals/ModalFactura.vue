<template>
	<NcModal v-if="open"
		size="small"
		:name="t('empleados', 'Register Invoice')"
		@close="$emit('close')">
		<div class="payment-modal">
			<div class="payment-icon-wrapper">
				<div class="payment-icon">
					🧾
				</div>
			</div>
			<h2>{{ t('empleados', 'Register Invoice') }}</h2>
			<p class="payment-subtitle">
				{{ t('empleados', 'Select the invoice date for this installment.') }}
			</p>
			<div class="payment-field">
				<NcTextField :value="fecha"
					type="date"
					:label="t('empleados', 'Invoice date')"
					@update:value="$emit('update:fecha', $event)" />
			</div>

			<div class="payment-advanced">
				<button type="button"
					class="payment-advanced__toggle"
					@click="$emit('update:showAdvanced', !showAdvanced)">
					<DotsHorizontal :size="16" />
					{{ t('empleados', 'Advanced options') }}
					<ChevronDown :size="14" class="payment-advanced__chevron" :class="{ open: showAdvanced }" />
				</button>

				<div v-if="showAdvanced" class="payment-advanced__body">
					<NcSelect :value="clientePagador"
						:options="clientesPagadorOptions"
						:clearable="true"
						:placeholder="t('empleados', 'Invoiced by another company')"
						label="label"
						track-by="value"
						@input="$emit('update:clientePagador', $event)" />
					<p class="payment-advanced__hint">
						{{ t('empleados', 'Only fill this in if a related company (parent or sister) invoiced this installment instead.') }}
					</p>
				</div>
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
import { NcModal, NcTextField, NcButton, NcSelect } from '@nextcloud/vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'

export default {
	name: 'ModalFactura',

	components: { NcModal, NcTextField, NcButton, NcSelect, DotsHorizontal, ChevronDown },

	props: {
		open: { type: Boolean, default: false },
		fecha: { type: String, default: '' },
		showAdvanced: { type: Boolean, default: false },
		clientePagador: { type: Object, default: null },
		clientesPagadorOptions: { type: Array, default: () => [] },
	},

	methods: { t },
}
</script>

<style scoped lang="scss">
@import './modales-pago.scss';
</style>
