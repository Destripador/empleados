<template>
	<NcModal v-if="open"
		size="normal"
		:name="t('empleados', 'Generate request')"
		@close="$emit('close')">
		<div class="modal-content">
			<span tabindex="0" class="focus-catcher" aria-hidden="true" />

			<div class="modal-header">
				<h2>{{ t('empleados', 'Generate service fee request') }}</h2>
			</div>

			<div class="form-grid">
				<NcSelect :value="departamento"
					class="span-2"
					:options="departamentoOptions"
					:placeholder="t('empleados', 'Department')"
					label="label"
					track-by="value"
					@input="$emit('update:departamento', $event)" />

				<NcTextField class="span-2"
					:value="asunto"
					:label="t('empleados', 'Subject')"
					@update:value="$emit('update:asunto', $event)" />
			</div>

			<div class="modal-actions">
				<NcButton @click="$emit('close')">
					{{ t('empleados', 'Cancel') }}
				</NcButton>
				<NcButton :disabled="generating || sending" @click="$emit('generate')">
					{{ generating ? t('empleados', 'Generating...') : t('empleados', 'Download request') }}
				</NcButton>
				<NcButton type="primary" :disabled="generating || sending" @click="$emit('send')">
					{{ sending ? t('empleados', 'Sending...') : t('empleados', 'Generate request and send') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcModal, NcTextField, NcButton, NcSelect } from '@nextcloud/vue'

export default {
	name: 'ModalReporteHonorario',

	components: { NcModal, NcTextField, NcButton, NcSelect },

	props: {
		open: { type: Boolean, default: false },
		departamento: { type: Object, default: null },
		departamentoOptions: { type: Array, default: () => [] },
		asunto: { type: String, default: '' },
		generating: { type: Boolean, default: false },
		sending: { type: Boolean, default: false },
	},

	methods: { t },
}
</script>

<style scoped lang="scss">
@import './modales-comunes.scss';
</style>
