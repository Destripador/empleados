<template>
	<div class="activity-details">
		<div class="details-header">
			<div class="details-icon">
				<ClipboardTextClockOutline :size="30" />
			</div>
			<div>
				<p class="eyebrow">
					{{ t('empleados', 'Activity') }}
				</p>
				<h2>{{ nombre_activity || t('empleados', 'Without name') }}</h2>
			</div>
		</div>

		<div class="details-grid">
			<div class="detail-field detail-field-wide">
				<div class="field-icon">
					<TextBoxOutline :size="20" />
				</div>
				<div>
					<span>{{ t('empleados', 'Description') }}</span>
					<p>{{ detalles_activity || t('empleados', 'No description available.') }}</p>
				</div>
			</div>

			<div class="detail-field">
				<div class="field-icon">
					<TimerSandFull :size="20" />
				</div>
				<div>
					<span>{{ t('empleados', 'Estimated time') }}</span>
					<strong>{{ tiempo_estimado || '-' }}</strong>
				</div>
			</div>

			<div class="detail-field">
				<div class="field-icon">
					<ClockCheck :size="20" />
				</div>
				<div>
					<span>{{ t('empleados', 'Real time') }}</span>
					<strong>{{ tiempo_real || '-' }}</strong>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

import ClipboardTextClockOutline from 'vue-material-design-icons/ClipboardTextClockOutline.vue'
import ClockCheck from 'vue-material-design-icons/ClockCheck.vue'
import TextBoxOutline from 'vue-material-design-icons/TextBoxOutline.vue'
import TimerSandFull from 'vue-material-design-icons/TimerSandFull.vue'

export default {
	name: 'ActividadesDetalles',

	components: {
		ClipboardTextClockOutline,
		ClockCheck,
		TextBoxOutline,
		TimerSandFull,
	},

	props: {
		select: { type: Array, required: true },
	},

	data() {
		return {
			nombre_activity: '',
			detalles_activity: '',
			tiempo_estimado: '',
			tiempo_real: '',
		}
	},

	watch: {
		select: {
			immediate: true,
			deep: true,
			handler(nuevo) {
				this.nombre_activity = nuevo?.[0]?.nombre ?? ''
				this.detalles_activity = nuevo?.[0]?.detalles ?? ''
				this.tiempo_estimado = nuevo?.[0]?.tiempo_estimado ?? ''
				this.tiempo_real = nuevo?.[0]?.tiempo_real ?? ''
			},
		},
	},

	methods: {
		t,
	},
}
</script>

<style scoped>
.activity-details {
	max-width: 900px;
	margin: 28px auto 0;
	padding: 22px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
}

.details-header {
	display: flex;
	align-items: center;
	gap: 14px;
	margin-bottom: 18px;
}

.details-icon,
.field-icon {
	display: inline-flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-primary-element);
}

.details-icon {
	width: 54px;
	height: 54px;
}

.field-icon {
	width: 36px;
	height: 36px;
}

.eyebrow {
	margin: 0 0 4px;
	color: var(--color-primary-element);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.details-header h2 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 24px;
	font-weight: 700;
	line-height: 1.2;
}

.details-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
}

.detail-field {
	display: flex;
	align-items: flex-start;
	min-width: 0;
	gap: 12px;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.detail-field-wide {
	grid-column: 1 / -1;
}

.detail-field span {
	display: block;
	margin-bottom: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.detail-field strong,
.detail-field p {
	margin: 0;
	color: var(--color-main-text);
	font-size: 14px;
	line-height: 1.5;
	overflow-wrap: anywhere;
}

@media (max-width: 768px) {
	.activity-details {
		margin-top: 18px;
		padding: 14px;
	}

	.details-grid {
		grid-template-columns: 1fr;
	}

	.detail-field-wide {
		grid-column: auto;
	}

	.details-header h2 {
		font-size: 20px;
	}
}
</style>
