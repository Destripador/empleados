<template>
	<div class="support-widget-card">
		<div class="support-widget-card__icon" aria-hidden="true">
			<Wrench :size="28" />
		</div>
		<div class="support-widget-card__copy">
			<h3>{{ t('empleados', 'Register device support') }}</h3>
			<p>{{ t('empleados', 'Record technical assistance and the time spent.') }}</p>
			<small>{{ t('empleados', 'The time will be added automatically to your reports as a non-billable activity.') }}</small>
		</div>
		<NcButton
			ref="openButton"
			type="primary"
			wide
			:aria-label="t('empleados', 'Register device support')"
			@click="openModal">
			<template #icon>
				<Wrench :size="20" />
			</template>
			{{ t('empleados', 'Register support') }}
		</NcButton>

		<NcModal
			v-if="modalOpen"
			:name="t('empleados', 'Register device support')"
			class="support-widget-modal"
			@close="closeModal">
			<div class="support-widget-modal__content">
				<RegistrarSoporteForm
					ref="supportForm"
					@cancel="closeModal"
					@success="handleSuccess" />
			</div>
		</NcModal>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcModal } from '@nextcloud/vue'
import Wrench from 'vue-material-design-icons/Wrench.vue'

import RegistrarSoporteForm from '../components/Inventario/RegistrarSoporteForm.vue'

export default {
	name: 'SoporteEquipoDashboardWidget',
	components: { NcButton, NcModal, RegistrarSoporteForm, Wrench },
	data() {
		return { modalOpen: false }
	},
	methods: {
		t,
		openModal() {
			this.modalOpen = true
			this.$nextTick(() => this.$refs.supportForm?.focusInitialField())
		},
		closeModal() {
			this.modalOpen = false
			this.$nextTick(() => this.$refs.openButton?.$el?.focus())
		},
		handleSuccess() {
			// El resultado permanece visible hasta que la persona cierre el modal.
		},
	},
}
</script>

<style scoped lang="scss">
.support-widget-card {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr);
	gap: 14px;
	align-items: start;
	padding: 8px;
}

.support-widget-card__icon {
	display: grid;
	place-items: center;
	width: 48px;
	height: 48px;
	border-radius: 50%;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
}

.support-widget-card__copy {
	display: grid;
	gap: 4px;
	min-width: 0;
}

.support-widget-card h3,
.support-widget-card p {
	margin: 0;
}

.support-widget-card h3 {
	font-size: 18px;
	line-height: 1.3;
	white-space: normal;
}

.support-widget-card p,
.support-widget-card small {
	color: var(--color-text-maxcontrast);
}

.support-widget-card > button {
	grid-column: 1 / -1;
}

.support-widget-modal__content {
	width: min(720px, calc(100vw - 48px));
	max-width: 100%;
	max-height: calc(100vh - 120px);
	padding: 20px;
	overflow-y: auto;
}

@media (max-width: 520px) {
	.support-widget-card {
		grid-template-columns: 1fr;
	}

	.support-widget-card__icon {
		display: none;
	}

	.support-widget-modal__content {
		width: calc(100vw - 24px);
		max-height: calc(100vh - 72px);
		padding: 14px;
	}
}
</style>
