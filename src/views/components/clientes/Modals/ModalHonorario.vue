<template>
	<NcModal v-if="open" :name="title" @close="$emit('close')">
		<div class="modal-content">
			<div class="modal-header">
				<p class="section-label">
					{{ t('empleados', 'Billing') }}
				</p>
				<h2>{{ title }}</h2>
			</div>

			<div class="tipo-honorario-selector">
				<button v-for="tipo in tiposHonorario"
					:key="tipo.value"
					class="tipo-btn"
					:class="{ 'tipo-btn--active': tipoHonorario === tipo.value }"
					type="button"
					:disabled="isEditing"
					@click="!isEditing && $emit('update:tipoHonorario', tipo.value)">
					<span class="tipo-icon">
						<span v-if="tipo.value === 'parcial'">📅</span>
						<span v-else-if="tipo.value === 'iguala'">🔄</span>
						<span v-else>⚡</span>
					</span>
					{{ tipo.label }}
				</button>
			</div>

			<NcNoteCard type="info" class="tipo-desc">
				<span v-if="tipoHonorario === 'parcial'">
					{{ t('empleados', 'Fixed period. Installments are calculated automatically by month between start and end date.') }}
				</span>
				<span v-else-if="tipoHonorario === 'iguala'">
					{{ t('empleados', 'Indefinite monthly fee. A new installment is generated each month. You can finalize it at any time.') }}
				</span>
				<span v-else>
					{{ t('empleados', 'One-time fee. A single installment is created for the selected month.') }}
				</span>
			</NcNoteCard>

			<NcNoteCard v-if="isEditing" type="warning" class="tipo-desc">
				{{ t('empleados', 'Dates and amount cannot be changed here to avoid regenerating installments. Only service, currency, title date and special flag can be edited.') }}
			</NcNoteCard>

			<div class="form-grid">
				<NcTextField :value="tipoServicio"
					:label="t('empleados', 'Service type')"
					@update:value="$emit('update:tipoServicio', $event)" />

				<NcSelect :value="tituloAnio"
					:options="anios"
					:placeholder="t('empleados', 'Year')"
					label="label"
					track-by="value"
					:searchable="false"
					@input="$emit('update:tituloAnio', $event)" />

				<NcSelect :value="tipoMoneda"
					:options="currencyOptions"
					label="label"
					track-by="value"
					:searchable="false"
					@input="$emit('update:tipoMoneda', $event)" />

				<NcTextField type="number"
					:value="importeTotal"
					:disabled="isEditing"
					:label="t('empleados', 'Total amount')"
					@update:value="$emit('update:importeTotal', $event)" />

				<div class="special-client-card span-2">
					<NcCheckboxRadioSwitch :checked="especial"
						type="switch"
						@update:checked="$emit('update:especial', $event)" />
					<div class="special-client-info">
						<h3>{{ t('empleados', 'Special fee') }}</h3>
						<p>{{ t('empleados', 'Marks this service fee as special.') }}</p>
					</div>
				</div>

				<NcTextArea class="span-2"
					:value="descripcion"
					:label="t('empleados', 'Description')"
					:rows="3"
					@update:value="$emit('update:descripcion', $event)" />

				<div class="span-2">
					<span class="date-label">{{ t('empleados', 'Start date') }}</span>
				</div>
				<NcSelect :value="mesInicio"
					:options="meses"
					:placeholder="t('empleados', 'Month')"
					label="label"
					track-by="value"
					:searchable="false"
					:disabled="isEditing"
					@input="$emit('update:mesInicio', $event)" />
				<NcSelect :value="anioInicio"
					:options="anios"
					:placeholder="t('empleados', 'Year')"
					label="label"
					track-by="value"
					:searchable="false"
					:disabled="isEditing"
					@input="$emit('update:anioInicio', $event)" />

				<template v-if="tipoHonorario === 'parcial'">
					<div class="span-2">
						<span class="date-label">{{ t('empleados', 'End date') }}</span>
					</div>
					<NcSelect :value="mesFin"
						:options="meses"
						:placeholder="t('empleados', 'Month')"
						label="label"
						track-by="value"
						:searchable="false"
						:disabled="isEditing"
						@input="$emit('update:mesFin', $event)" />
					<NcSelect :value="anioFin"
						:options="anios"
						:placeholder="t('empleados', 'Year')"
						label="label"
						track-by="value"
						:searchable="false"
						:disabled="isEditing"
						@input="$emit('update:anioFin', $event)" />
					<NcSelect :value="periodicidad"
						class="span-2"
						:options="periodicidadOptions"
						:input-label="t('empleados', 'Select installment period')"
						label="label"
						track-by="value"
						:searchable="false"
						:disabled="isEditing"
						@input="$emit('update:periodicidad', $event)" />
				</template>

				<NcNoteCard v-if="!isEditing && tipoHonorario === 'parcial' && periodBreakdown.length > 0"
					type="info"
					class="span-2">
					{{ t('empleados', '{n} installment(s): {detail} {currency}', {
						n: periodBreakdown.length,
						detail: periodAmounts.map(a => formatImporte(a)).join(' + '),
						currency: tipoMoneda ? tipoMoneda.value : ''
					}) }}
				</NcNoteCard>
				<NcNoteCard v-else-if="!isEditing && tipoHonorario === 'iguala'" type="info" class="span-2">
					{{ t('empleados', 'Monthly fee of {amount} {currency} starting {mes} {anio}', {
						amount: formatImporte(Number(importeTotal || 0)),
						currency: tipoMoneda ? tipoMoneda.value : '',
						mes: mesInicio ? mesInicio.label : '—',
						anio: anioInicio ? anioInicio.value : ''
					}) }}
				</NcNoteCard>
				<NcNoteCard v-else-if="!isEditing && tipoHonorario === 'eventual'" type="info" class="span-2">
					{{ t('empleados', 'Single installment of {amount} {currency}', {
						amount: formatImporte(Number(importeTotal || 0)),
						currency: tipoMoneda ? tipoMoneda.value : ''
					}) }}
				</NcNoteCard>
			</div>

			<div class="modal-actions">
				<NcButton @click="$emit('close')">
					{{ t('empleados', 'Cancel') }}
				</NcButton>
				<NcButton type="primary" :disabled="!isValid || saving" @click="$emit('save')">
					{{ saveLabel }}
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
	NcTextArea,
	NcButton,
	NcSelect,
	NcCheckboxRadioSwitch,
	NcNoteCard,
} from '@nextcloud/vue'

export default {
	name: 'ModalHonorario',

	components: {
		NcModal,
		NcTextField,
		NcTextArea,
		NcButton,
		NcSelect,
		NcCheckboxRadioSwitch,
		NcNoteCard,
	},

	props: {
		open: { type: Boolean, default: false },
		title: { type: String, required: true },
		saveLabel: { type: String, required: true },
		saving: { type: Boolean, default: false },
		isEditing: { type: Boolean, default: false },
		isValid: { type: Boolean, default: false },

		tiposHonorario: { type: Array, default: () => [] },
		meses: { type: Array, default: () => [] },
		anios: { type: Array, default: () => [] },
		currencyOptions: { type: Array, default: () => [] },
		periodicidadOptions: { type: Array, default: () => [] },
		periodBreakdown: { type: Array, default: () => [] },
		periodAmounts: { type: Array, default: () => [] },
		formatImporte: { type: Function, required: true },

		tipoHonorario: { type: String, default: 'parcial' },
		especial: { type: Boolean, default: false },
		tipoServicio: { type: String, default: '' },
		tituloAnio: { type: Object, default: null },
		tipoMoneda: { type: Object, default: null },
		importeTotal: { type: [String, Number], default: '' },
		descripcion: { type: String, default: '' },
		mesInicio: { type: Object, default: null },
		anioInicio: { type: Object, default: null },
		mesFin: { type: Object, default: null },
		anioFin: { type: Object, default: null },
		periodicidad: { type: Object, default: null },
	},

	methods: { t },
}
</script>

<style scoped lang="scss">
@import './modales-comunes.scss';

.tipo-honorario-selector {
	display: flex;
	gap: 8px;
	margin-bottom: 4px;
}

.tipo-btn {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 4px;
	padding: 10px 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-soft);
	cursor: pointer;
	font-size: 0.8rem;
	color: var(--color-text-maxcontrast);
	transition: all 0.15s ease;

	&:hover {
		border-color: var(--color-primary-element);
		background: var(--color-primary-element-light);
	}

	&--active {
		border-color: var(--color-primary-element);
		border-width: 2px;
		background: var(--color-primary-element-light);
		color: var(--color-primary-element);
		font-weight: 600;
	}
}

.tipo-icon {
	font-size: 1.3rem;
}

.tipo-desc {
	margin-bottom: 4px;
}
</style>
