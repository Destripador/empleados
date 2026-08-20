<template>
	<section class="parking-maintenance-notice">
		<div class="parking-maintenance-notice__symbol" aria-hidden="true">
			🅿️
		</div>
		<h2>{{ t('empleados', 'Parking under maintenance') }}</h2>
		<p>{{ t('empleados', 'We are updating the parking assignments.') }}</p>
		<p>{{ t('empleados', 'During this process the information may be incomplete, so the map is temporarily hidden.') }}</p>
		<p class="parking-maintenance-notice__warning">
			{{ t('empleados', 'Assignments made during maintenance should not be considered final.') }}
		</p>

		<dl v-if="status.reason || status.startedAt || status.until" class="parking-maintenance-notice__details">
			<div v-if="status.reason">
				<dt>{{ t('empleados', 'Reason') }}</dt>
				<dd>{{ status.reason }}</dd>
			</div>
			<div v-if="status.startedAt">
				<dt>{{ t('empleados', 'Started') }}</dt>
				<dd>{{ formatDate(status.startedAt) }}</dd>
			</div>
			<div v-if="status.until">
				<dt>{{ t('empleados', 'Estimated availability') }}</dt>
				<dd>{{ formatDate(status.until) }}</dd>
			</div>
		</dl>
	</section>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'ParkingMaintenanceNotice',

	props: {
		status: {
			type: Object,
			required: true,
		},
	},

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
.parking-maintenance-notice {
	display: grid;
	place-items: center;
	box-sizing: border-box;
	width: min(680px, calc(100% - 32px));
	margin: clamp(24px, 8vh, 90px) auto;
	padding: clamp(24px, 5vw, 48px);
	border: 1px solid var(--color-border);
	border-radius: 22px;
	background: var(--color-main-background);
	box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
	text-align: center;
}

.parking-maintenance-notice__symbol {
	display: grid;
	place-items: center;
	width: 82px;
	height: 82px;
	margin-bottom: 16px;
	border-radius: 24px;
	background: var(--color-primary-element-light);
	font-size: 2.8rem;
}

.parking-maintenance-notice h2 {
	margin: 0 0 12px;
	font-size: clamp(1.45rem, 4vw, 2.1rem);
}

.parking-maintenance-notice > p {
	margin: 3px 0;
	max-width: 58ch;
	color: var(--color-text-maxcontrast);
	line-height: 1.55;
}

.parking-maintenance-notice .parking-maintenance-notice__warning {
	margin-top: 16px;
	padding: 11px 14px;
	border-inline-start: 4px solid var(--color-warning);
	border-radius: 0 10px 10px 0;
	background: var(--color-background-hover);
	color: var(--color-main-text);
	font-weight: 700;
}

.parking-maintenance-notice__details {
	display: grid;
	gap: 10px;
	width: min(480px, 100%);
	margin: 24px 0 0;
	padding: 16px;
	border-radius: 14px;
	background: var(--color-background-hover);
	text-align: start;
}

.parking-maintenance-notice__details > div {
	display: grid;
	grid-template-columns: minmax(130px, 0.42fr) minmax(0, 1fr);
	gap: 12px;
}

.parking-maintenance-notice__details dt {
	color: var(--color-text-maxcontrast);
	font-size: 0.82rem;
	font-weight: 700;
}

.parking-maintenance-notice__details dd {
	margin: 0;
	overflow-wrap: anywhere;
}

@media (max-width: 520px) {
	.parking-maintenance-notice {
		width: calc(100% - 20px);
		margin-block: 18px;
		padding: 22px 16px;
	}

	.parking-maintenance-notice__details > div {
		grid-template-columns: 1fr;
		gap: 2px;
	}
}
</style>
