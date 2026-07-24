<template id="content">
	<section class="anniversary-info">
		<header class="info-header">
			<h2>{{ t('empleados', 'Vacation Table') }}</h2>
			<p>
				{{ t('empleados', 'This table shows how many vacation days you are entitled to based on your years with the company. It is a guide based on the Federal Labor Law, reformed in 2023.') }}
			</p>
		</header>

		<div class="faq-section">
			<h3>{{ t('empleados', 'Frequently Asked Questions') }}</h3>

			<div v-for="(pregunta, index) in preguntas" :key="index" class="faq-item">
				<button
					class="faq-title"
					type="button"
					:aria-expanded="pregunta.abierto ? 'true' : 'false'"
					@click="toggle(index)">
					<span>{{ pregunta.titulo }}</span>
					<ChevronUp v-if="pregunta.abierto" :size="20" />
					<ChevronDown v-else :size="20" />
				</button>
				<div v-show="pregunta.abierto" class="faq-content">
					{{ pregunta.contenido }}
				</div>
			</div>
		</div>

		<NcNoteCard type="info" :heading="t('empleados', 'Recommendation')">
			<p>
				{{ t('empleados', 'Check this table every time you reach a work anniversary. That way you can plan your time off in advance and enjoy your days to the fullest.') }}
			</p>
		</NcNoteCard>
	</section>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcNoteCard } from '@nextcloud/vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'

export default {
	name: 'MensajeAniversarios',
	components: {
		ChevronDown,
		ChevronUp,
		NcNoteCard,
	},

	props: {
		info: { type: Object, required: true },
		acumular: { type: String, required: true },
	},

	data() {
		return {
			preguntas: [
				{
					titulo: t('empleados', 'From when do I have the right to vacation?'),
					contenido: t('empleados', 'From your first full year worked you can already take vacation. The minimum is 12 days and it increases each year.'),
					abierto: false,
				},
				{
					titulo: t('empleados', 'How are the days counted?'),
					contenido: t('empleados', 'The days shown in the table are business days. Saturdays, Sundays, and holidays are not counted.'),
					abierto: false,
				},
				{
					titulo: t('empleados', 'Can I split my vacation?'),
					contenido: t('empleados', 'Yes. By law, at least half should be taken consecutively, but you can talk to Human Resources to distribute the days according to your needs and your team’s.'),
					abierto: false,
				},
				{
					titulo: t('empleados', 'What happens if I do not take my vacation?'),
					contenido: this.acumular === 'true'
						? t('empleados', 'Unused vacation is not lost, but it is important to use it. Resting is a right and also helps your health and performance.')
						: t('empleados', 'If you do not take your vacation, it is lost. It is important to use it to take care of your health and wellbeing.'),
					abierto: false,
				},
			],
		}
	},

	methods: {
		t,
		toggle(index) {
			this.preguntas[index].abierto = !this.preguntas[index].abierto
		},
	},
}
</script>

<style scoped>
.anniversary-info {
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.info-header h2,
.faq-section h3 {
	margin: 0 0 8px;
}

.info-header p,
.faq-content {
	color: var(--color-text-maxcontrast);
	line-height: 1.5;
}

.faq-section {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.faq-item {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	overflow: hidden;
	background-color: var(--color-main-background);
}

.faq-title {
	display: flex;
	gap: 12px;
	align-items: center;
	justify-content: space-between;
	width: 100%;
	min-height: 44px;
	padding: 10px 12px;
	border: none;
	background-color: transparent;
	color: var(--color-main-text);
	cursor: pointer;
	font-weight: 700;
	text-align: left;
}

.faq-title:hover,
.faq-title:focus-visible {
	background-color: var(--color-background-hover);
}

.faq-content {
	padding: 0 12px 12px;
	border: none;
}
</style>
