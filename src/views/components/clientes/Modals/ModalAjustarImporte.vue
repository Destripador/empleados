<template>
	<NcModal v-if="open" :name="t('empleados', 'Modify amount')" @close="$emit('close')">
		<div class="modal-content">
			<div class="modal-header">
				<p class="section-label">
					{{ t('empleados', 'Billing') }}
				</p>
				<h2>{{ t('empleados', 'Modify installment amount') }}</h2>
			</div>

			<NcNoteCard type="info">
				{{ t('empleados', 'The difference will be redistributed automatically among the other {n} pending installment(s) of this fee.', { n: otrasPendientes }) }}
			</NcNoteCard>

			<NcNoteCard v-if="otrasPendientes === 0" type="warning">
				{{ t('empleados', 'This is the last pending installment, so the amount must match exactly what is left of the total.') }}
			</NcNoteCard>

			<div class="form-grid">
				<NcTextField type="number"
					class="span-2"
					:value="nuevoImporte"
					:label="t('empleados', 'New amount')"
					step="0.01"
					min="0"
					@update:value="$emit('update:nuevoImporte', $event)" />
			</div>

			<p class="hint-text">
				{{ t('empleados', 'Current amount: {actual} {moneda} · Fee total: {total} {moneda}', {
					actual: formatImporte(importeActual),
					total: formatImporte(importeTotal),
					moneda
				}) }}
			</p>

			<NcNoteCard v-if="!isValid && errorMessage" type="error">
				{{ errorMessage }}
			</NcNoteCard>

			<div class="modal-actions">
				<NcButton @click="$emit('close')">
					{{ t('empleados', 'Cancel') }}
				</NcButton>
				<NcButton type="primary" :disabled="saving || !isValid" @click="$emit('save')">
					{{ saving ? t('empleados', 'Saving...') : t('empleados', 'Save changes') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import {
	NcModal,
	NcTextField,
	NcButton,
	NcNoteCard,
} from '@nextcloud/vue'

export default {
	name: 'ModalAjustarImporte',

	components: {
		NcModal,
		NcTextField,
		NcButton,
		NcNoteCard,
	},

	props: {
		open: { type: Boolean, default: false },
		saving: { type: Boolean, default: false },
		nuevoImporte: { type: [String, Number], default: '' },
		importeActual: { type: [String, Number], default: 0 },
		importeTotal: { type: [String, Number], default: 0 },
		moneda: { type: String, default: 'MXN' },
		otrasPendientes: { type: Number, default: 0 },
		isValid: { type: Boolean, default: true },
		errorMessage: { type: String, default: '' },
	},

	methods: {
		t,
		formatImporte(valor) {
			return Number(valor).toLocaleString('es-MX', {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			})
		},
	},
}
</script>

<style scoped lang="scss">
@import './modales-comunes.scss';

.hint-text {
	margin: 0;
	font-size: 0.8rem;
	color: var(--color-text-maxcontrast);
}
</style>
