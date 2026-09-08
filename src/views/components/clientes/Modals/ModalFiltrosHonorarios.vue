<template>
	<NcModal v-if="open"
		size="small"
		:name="t('empleados', 'Filter fees')"
		@close="$emit('close')">
		<div class="modal-content">
			<div class="modal-header">
				<p class="section-label">
					{{ t('empleados', 'Billing') }}
				</p>
				<h2>{{ t('empleados', 'Filter fees') }}</h2>
			</div>

			<div class="form-grid">
				<NcTextField class="span-2"
					:value="busqueda"
					:label="t('empleados', 'Search by service name')"
					@update:value="$emit('update:busqueda', $event)" />

				<NcSelect :value="estado"
					class="span-2"
					:options="estadoOptions"
					:placeholder="t('empleados', 'Status')"
					label="label"
					track-by="value"
					:clearable="true"
					@input="$emit('update:estado', $event)" />

				<NcSelect :value="tipo"
					class="span-2"
					:options="tipoOptions"
					:placeholder="t('empleados', 'Fee type')"
					label="label"
					track-by="value"
					:clearable="true"
					@input="$emit('update:tipo', $event)" />

				<div class="special-client-card span-2">
					<NcCheckboxRadioSwitch :checked="soloEspecial"
						type="switch"
						@update:checked="$emit('update:soloEspecial', $event)" />
					<div class="special-client-info">
						<h3>{{ t('empleados', 'Special fees only') }}</h3>
					</div>
				</div>

				<div class="span-2">
					<span class="date-label">{{ t('empleados', 'Date range') }}</span>
				</div>
				<NcTextField :value="desde"
					type="date"
					:label="t('empleados', 'From')"
					@update:value="$emit('update:desde', $event)" />
				<NcTextField :value="hasta"
					type="date"
					:label="t('empleados', 'To')"
					@update:value="$emit('update:hasta', $event)" />
			</div>

			<div class="modal-actions">
				<NcButton @click="$emit('reset')">
					{{ t('empleados', 'Clear filters') }}
				</NcButton>
				<NcButton type="primary" @click="$emit('close')">
					{{ t('empleados', 'Apply') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import {
	NcModal,
	NcButton,
	NcTextField,
	NcSelect,
	NcCheckboxRadioSwitch,
} from '@nextcloud/vue'

export default {
	name: 'ModalFiltrosHonorarios',

	components: {
		NcModal,
		NcButton,
		NcTextField,
		NcSelect,
		NcCheckboxRadioSwitch,
	},

	props: {
		open: { type: Boolean, default: false },
		busqueda: { type: String, default: '' },
		estado: { type: Object, default: null },
		tipo: { type: Object, default: null },
		soloEspecial: { type: Boolean, default: false },
		desde: { type: String, default: '' },
		hasta: { type: String, default: '' },
	},

	data() {
		return {
			estadoOptions: [
				{ label: t('empleados', 'Active'), value: 'activo' },
				{ label: t('empleados', 'Completed'), value: 'completado' },
			],
			tipoOptions: [
				{ label: t('empleados', 'Installments'), value: 'parcial' },
				{ label: t('empleados', 'Retainer fee'), value: 'iguala' },
				{ label: t('empleados', 'One-time'), value: 'eventual' },
			],
		}
	},

	methods: { t },
}
</script>

<style scoped lang="scss">
@import './modales-comunes.scss';
</style>
