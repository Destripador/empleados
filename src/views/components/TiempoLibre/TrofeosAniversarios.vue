<template>
	<section class="anniversary-card">
		<div class="anniversary-card__icon">
			<TrophyOutline :size="42" />
		</div>

		<div class="anniversary-card__content">
			<p class="anniversary-card__label">
				{{ t('empleados', 'Anniversary') }}
			</p>
			<h2>{{ anniversaryNumber }}</h2>
			<p>
				{{ t('empleados', 'Thanks!') }}
			</p>
		</div>

		<div class="anniversary-stats">
			<div class="stat-item">
				<BriefcaseClockOutline :size="20" />
				<span>{{ t('empleados', 'Worked days') }}</span>
				<strong>{{ workedDays }}</strong>
			</div>
			<div class="stat-item">
				<CalendarStar :size="20" />
				<span>{{ t('empleados', 'Days off') }}</span>
				<strong>{{ info.dias_disponibles || 0 }}</strong>
			</div>
		</div>
	</section>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import BriefcaseClockOutline from 'vue-material-design-icons/BriefcaseClockOutline.vue'
import CalendarStar from 'vue-material-design-icons/CalendarStar.vue'
import TrophyOutline from 'vue-material-design-icons/TrophyOutline.vue'

export default {
	name: 'TrofeosAniversarios',

	components: {
		BriefcaseClockOutline,
		CalendarStar,
		TrophyOutline,
	},

	props: {
		info: {
			type: Object,
			required: true,
		},
		acumular: {
			type: String,
			required: true,
		},
	},

	computed: {
		anniversaryNumber() {
			return this.info.id_aniversario || 0
		},

		workedDays() {
			return (this.anniversaryNumber * 260) + this.calcularDiasLaborales()
		},
	},

	methods: {
		t,

		calcularDiasLaborales() {
			const hoy = new Date()
			const inicio = new Date(hoy.getFullYear(), 0, 1)
			const todayTime = hoy.getTime()
			const current = new Date(inicio)
			let count = 0

			while (current.getTime() <= todayTime) {
				const day = current.getDay()
				if (day !== 0 && day !== 6) {
					count++
				}
				current.setDate(current.getDate() + 1)
			}

			return count
		},
	},
}
</script>

<style scoped>
.anniversary-card {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr);
	gap: 16px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-main-background);
}

.anniversary-card__icon {
	display: grid;
	place-items: center;
	width: 64px;
	height: 64px;
	border-radius: 50%;
	background-color: var(--color-primary-element-light);
	color: var(--color-primary-element);
}

.anniversary-card__content {
	min-width: 0;
}

.anniversary-card__label,
.anniversary-card__content p {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.anniversary-card__content h2 {
	margin: 2px 0;
	font-size: 32px;
	line-height: 1.1;
}

.anniversary-stats {
	grid-column: 1 / -1;
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 8px;
}

.stat-item {
	display: grid;
	grid-template-columns: 24px minmax(0, 1fr) auto;
	gap: 8px;
	align-items: center;
	padding: 10px 12px;
	border-radius: var(--border-radius, 6px);
	background-color: var(--color-background-hover);
}

.stat-item span {
	color: var(--color-text-maxcontrast);
}

@media (max-width: 600px) {
	.anniversary-stats {
		grid-template-columns: 1fr;
	}
}
</style>
