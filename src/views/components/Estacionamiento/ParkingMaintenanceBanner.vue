<template>
	<section class="parking-maintenance-banner" role="status">
		<div class="parking-maintenance-banner__icon" aria-hidden="true">
			⚠
		</div>
		<div class="parking-maintenance-banner__copy">
			<strong>{{ t('empleados', 'MAINTENANCE MODE ACTIVE') }}</strong>
			<p>{{ t('empleados', 'The public parking map is temporarily hidden while assignments are updated.') }}</p>
			<p>{{ t('empleados', 'The changes you are viewing have not been published yet.') }}</p>
			<div v-if="status.reason || status.startedAt || status.until" class="parking-maintenance-banner__meta">
				<span v-if="status.reason">
					<b>{{ t('empleados', 'Reason') }}:</b> {{ status.reason }}
				</span>
				<span v-if="status.startedAt">
					<b>{{ t('empleados', 'Started') }}:</b> {{ formatDate(status.startedAt) }}
				</span>
				<span v-if="status.until">
					<b>{{ t('empleados', 'Estimated availability') }}:</b> {{ formatDate(status.until) }}
				</span>
			</div>
		</div>
		<NcButton
			v-if="canPublish"
			type="primary"
			:disabled="publishing"
			@click="$emit('publish')">
			{{ t('empleados', 'Publish parking') }}
		</NcButton>
	</section>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
	name: 'ParkingMaintenanceBanner',

	components: {
		NcButton,
	},

	props: {
		status: {
			type: Object,
			required: true,
		},
		canPublish: {
			type: Boolean,
			default: false,
		},
		publishing: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['publish'],

	methods: {
		t,
		formatDate(value) {
			const date = new Date(value)
			if (Number.isNaN(date.getTime())) {
				return value
			}

			return new Intl.DateTimeFormat(undefined, {
				dateStyle: 'medium',
				timeStyle: 'short',
			}).format(date)
		},
	},
}
</script>

<style scoped lang="scss">
.parking-maintenance-banner {
	position: sticky;
	top: 0;
	z-index: 20;
	display: grid;
	grid-template-columns: auto minmax(0, 1fr) auto;
	gap: 14px;
	align-items: center;
	box-sizing: border-box;
	width: 100%;
	margin-bottom: 14px;
	padding: 14px 16px;
	border: 2px solid var(--color-warning);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 5px 18px rgba(0, 0, 0, 0.14);
}

.parking-maintenance-banner__icon {
	display: grid;
	place-items: center;
	width: 38px;
	height: 38px;
	border-radius: 50%;
	background: var(--color-warning);
	color: var(--color-primary-element-text);
	font-size: 1.3rem;
	font-weight: 800;
}

.parking-maintenance-banner__copy {
	min-width: 0;
}

.parking-maintenance-banner__copy > strong {
	display: block;
	margin-bottom: 3px;
	color: var(--color-main-text);
	font-size: 0.92rem;
	letter-spacing: 0.04em;
}

.parking-maintenance-banner__copy p {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.84rem;
	line-height: 1.35;
}

.parking-maintenance-banner__meta {
	display: flex;
	flex-wrap: wrap;
	gap: 4px 16px;
	margin-top: 7px;
	font-size: 0.78rem;
}

@media (max-width: 700px) {
	.parking-maintenance-banner {
		position: relative;
		grid-template-columns: auto minmax(0, 1fr);
		padding: 12px;
	}

	.parking-maintenance-banner :deep(.button-vue) {
		grid-column: 1 / -1;
		width: 100%;
	}
}
</style>
