<template>
	<NcModal v-if="open"
		size="normal"
		:name="t('empleados', 'Generate request')"
		@close="$emit('close')">
		<div class="modal-content">
			<div class="modal-header">
				<p class="section-label">
					{{ t('empleados', 'Billing') }}
				</p>
				<h2>{{ t('empleados', 'Generate service fee requests') }}</h2>
				<p>
					{{ t('empleados', '{n} fee(s) selected', { n: honorarios.length }) }}
				</p>
			</div>

			<div class="multi-request-list">
				<div v-for="honorario in honorarios"
					:key="honorario.id_honorario"
					class="multi-request-item">
					{{ honorario.tipo_servicio || t('empleados', 'Service') }}
				</div>
			</div>

			<div class="modal-actions">
				<NcButton @click="$emit('close')">
					{{ t('empleados', 'Cancel') }}
				</NcButton>
				<NcButton :disabled="downloading || notifying" @click="$emit('download')">
					{{ downloading ? t('empleados', 'Downloading...') : t('empleados', 'Download requests') }}
				</NcButton>
				<NcButton type="primary" :disabled="downloading || notifying" @click="$emit('notify')">
					{{ notifying ? t('empleados', 'Sending...') : t('empleados', 'Notify by email') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcModal, NcButton } from '@nextcloud/vue'

export default {
	name: 'ModalReporteMultiple',

	components: { NcModal, NcButton },

	props: {
		open: { type: Boolean, default: false },
		honorarios: { type: Array, default: () => [] },
		downloading: { type: Boolean, default: false },
		notifying: { type: Boolean, default: false },
	},

	methods: { t },
}
</script>

<style scoped lang="scss">
@import './modales-comunes.scss';

.multi-request-list {
	display: flex;
	flex-direction: column;
	gap: 6px;
	max-height: 260px;
	overflow-y: auto;
}

.multi-request-item {
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-background-soft);
	font-size: 0.875rem;
	color: var(--color-main-text);
}
</style>
