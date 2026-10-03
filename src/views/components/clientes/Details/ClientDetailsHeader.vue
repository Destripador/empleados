<template>
	<div class="details-header" :class="{ 'details-header--especial': client.especial }">
		<div class="details-icon details-icon--logo">
			<ClienteLogo
				:id="client.id"
				:logo="client.logo"
				:bust="logoBust"
				size="lg"
				:alt="client.nombre || t('empleados', 'Customer')" />
		</div>

		<div class="details-title">
			<p class="eyebrow">
				{{ clientType }}
			</p>
			<h2>{{ client.nombre || t('empleados', 'Without name') }}</h2>
			<p>{{ client.detalles || t('empleados', 'No description available.') }}</p>
			<div v-if="canEditLogo" class="logo-actions">
				<NcButton type="tertiary" @click="$emit('trigger-logo-upload')">
					{{ client.logo ? t('empleados', 'Replace logo') : t('empleados', 'Add logo') }}
				</NcButton>
				<NcButton v-if="client.logo" type="tertiary" @click="$emit('remove-logo')">
					{{ t('empleados', 'Remove logo') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import ClienteLogo from '../../../../components/clientes/ClienteLogo.vue'

export default {
	name: 'ClientDetailsHeader',

	components: { NcButton, ClienteLogo },

	props: {
		client: { type: Object, required: true },
		clientType: { type: String, required: true },
		logoBust: { type: [Number, String], default: 0 },
		canEditLogo: { type: Boolean, default: false },
	},

	methods: { t },
}
</script>

<style scoped lang="scss">
.details-header {
	display: flex;
	align-items: flex-start;
	gap: 16px;
}

.details-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 52px;
	height: 52px;
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	flex-shrink: 0;
}

.details-icon--logo {
	width: auto;
	height: auto;
	background: transparent;
	padding: 0;
}

.logo-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 0.35rem;
	margin-top: 0.5rem;
}

.details-title {
	display: flex;
	flex-direction: column;
	gap: 4px;

	.eyebrow {
		font-size: 0.72rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: var(--color-primary-element);
		margin: 0;
	}

	h2 {
		margin: 0;
		font-size: 1.25rem;
		font-weight: 700;
		color: var(--color-main-text);
	}

	p {
		margin: 0;
		font-size: 0.875rem;
		color: var(--color-text-maxcontrast);
	}
}

.details-header--especial {
	background: linear-gradient(135deg, #6c9cda 10%, var(--color-main-background) 100%);
	border-radius: 8px;
	padding: 16px 16px 10px 16px;
}

.details-header--especial .eyebrow,
.details-header--especial h2,
.details-header--especial p {
	color: #ffffff;
}

.details-header--especial .details-icon {
	color: #ffffff;
}
</style>
