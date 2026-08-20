<template>
	<component
		:is="containerComponent"
		v-if="show"
		v-bind="containerProps"
		:class="embedded ? 'office-intro-embedded' : 'office-intro-modal'"
		@close="onModalClose">
		<section class="office-intro" :class="{ 'office-intro--embedded': embedded }">
			<div
				class="office-intro__progress"
				role="progressbar"
				:aria-label="t('empleados', 'Introduction progress')"
				:aria-valuemin="1"
				:aria-valuemax="steps.length"
				:aria-valuenow="currentStepIndex + 1">
				<span>{{ t('empleados', 'Step {current} of {total}', {
					current: currentStepIndex + 1,
					total: steps.length,
				}) }}</span>
				<div class="office-intro__dots" aria-hidden="true">
					<span
						v-for="(item, index) in steps"
						:key="item.id"
						:class="{
							'office-intro__dot--active': index === currentStepIndex,
							'office-intro__dot--done': index < currentStepIndex,
						}"
						class="office-intro__dot" />
				</div>
			</div>

			<transition name="office-intro-step" mode="out-in">
				<article :key="currentStep.id" class="office-intro__step">
					<div class="office-intro__visual" aria-hidden="true">
						<div v-if="currentStep.id === 'welcome'" class="mini-office">
							<div class="mini-office__room mini-office__room--work">
								💻
							</div>
							<div class="mini-office__room mini-office__room--coffee">
								☕
							</div>
							<div class="mini-office__room mini-office__room--meeting">
								📅
							</div>
							<span class="mini-office__node mini-office__node--one">AL</span>
							<span class="mini-office__node mini-office__node--two">MP</span>
							<span class="mini-office__node mini-office__node--three">JR</span>
							<span class="mini-office__node mini-office__node--four">SG</span>
						</div>

						<div v-else-if="currentStep.id === 'data'" class="data-map">
							<div class="data-map__employee">
								<span>🙂</span>
								<strong>{{ t('empleados', 'Employee') }}</strong>
							</div>
							<div class="data-map__arrow">
								↓
							</div>
							<div class="data-map__signals">
								<span v-for="item in currentStep.items" :key="item.label" class="data-map__signal">
									<b>{{ item.icon }}</b>{{ item.label }}
								</span>
							</div>
						</div>

						<div v-else-if="currentStep.id === 'emergence'" class="emergence">
							<div class="emergence__field emergence__field--loose">
								<span v-for="index in 6" :key="`loose-${index}`" class="emergence__node" />
							</div>
							<div class="emergence__arrow">
								→
							</div>
							<div class="emergence__field emergence__field--grouped">
								<span v-for="index in 6" :key="`group-${index}`" class="emergence__node" />
							</div>
						</div>

						<div v-else-if="currentStep.id === 'states'" class="state-model">
							<div class="state-model__center">
								○
							</div>
							<div
								v-for="(item, index) in currentStep.items"
								:key="item.label"
								:class="`state-model__state--${index + 1}`"
								class="state-model__state">
								<span class="state-model__icon">{{ item.icon }}</span>
								<small>{{ item.label }}</small>
							</div>
						</div>

						<div v-else-if="currentStep.id === 'events'" class="event-board">
							<div class="event-board__column event-board__column--data">
								<strong>{{ currentStep.realTitle }}</strong>
								<span v-for="item in currentStep.realItems" :key="item.label" class="event-board__item">
									<b>{{ item.icon }}</b>{{ item.label }}
								</span>
							</div>
							<div class="event-board__column event-board__column--simulated">
								<strong>{{ currentStep.simulatedTitle }}</strong>
								<span v-for="item in currentStep.simulatedItems" :key="item.label" class="event-board__item">
									<b>{{ item.icon }}</b>{{ item.label }}
								</span>
							</div>
						</div>

						<div v-else class="privacy-visual">
							<div v-for="comparison in currentStep.comparisons" :key="comparison[0]">
								<span>{{ comparison[0] }}</span>
								<strong>≠</strong>
								<span>{{ comparison[1] }}</span>
							</div>
						</div>
					</div>

					<div class="office-intro__copy">
						<h2 ref="stepTitle" tabindex="-1">
							{{ currentStep.title }}
						</h2>
						<p class="office-intro__lead">
							{{ currentStep.description }}
						</p>

						<blockquote v-if="currentStep.principle">
							“{{ currentStep.principle }}”
						</blockquote>

						<ul v-if="currentStep.id === 'welcome'" class="office-intro__tags">
							<li v-for="item in currentStep.items" :key="item">
								{{ item }}
							</li>
						</ul>

						<ul v-if="currentStep.notItems" class="office-intro__not-list">
							<li class="office-intro__not-title">
								{{ currentStep.notTitle }}
							</li>
							<li v-for="item in currentStep.notItems" :key="item">
								<span class="office-intro__not-icon" aria-hidden="true">×</span>{{ item }}
							</li>
						</ul>

						<p v-if="currentStep.detail" class="office-intro__detail">
							{{ currentStep.detail }}
						</p>

						<label v-if="isLastStep && !manual" class="office-intro__confirmation">
							<input v-model="accepted" type="checkbox" :disabled="saving">
							<span>{{ currentStep.confirmation }}</span>
						</label>
					</div>
				</article>
			</transition>

			<footer class="office-intro__actions">
				<NcButton
					v-if="currentStepIndex > 0"
					type="secondary"
					:disabled="saving"
					@click="previousStep">
					{{ t('empleados', 'Previous') }}
				</NcButton>
				<span v-else />

				<NcButton
					type="primary"
					:disabled="primaryDisabled"
					@click="nextStep">
					<template #icon>
						<NcLoadingIcon v-if="saving" :size="20" />
					</template>
					{{ primaryLabel }}
				</NcButton>
			</footer>
		</section>
	</component>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcLoadingIcon, NcModal } from '@nextcloud/vue'
import { completeOfficeSimulationOnboarding } from '../../../services/simulacionOficinaService.js'
import { buildOfficeSimulationDocumentation } from '../../../utils/officeSimulationDocumentation.js'

export default {
	name: 'OfficeSimulationIntro',

	components: {
		NcButton,
		NcLoadingIcon,
		NcModal,
	},

	props: {
		show: {
			type: Boolean,
			default: false,
		},
		version: {
			type: Number,
			required: true,
		},
		manual: {
			type: Boolean,
			default: false,
		},
		embedded: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['complete', 'close', 'error'],

	data() {
		return {
			currentStepIndex: 0,
			accepted: false,
			saving: false,
		}
	},

	computed: {
		steps() {
			return buildOfficeSimulationDocumentation(t).introSteps
		},
		containerComponent() {
			return this.embedded ? 'div' : NcModal
		},
		containerProps() {
			if (this.embedded) {
				return {}
			}
			return {
				name: t('empleados', 'Office Simulation'),
				canClose: this.manual,
				size: 'large',
			}
		},
		currentStep() {
			return this.steps[this.currentStepIndex]
		},
		isLastStep() {
			return this.currentStepIndex === this.steps.length - 1
		},
		primaryDisabled() {
			return this.saving || (this.isLastStep && !this.manual && !this.accepted)
		},
		primaryLabel() {
			if (this.saving) {
				return t('empleados', 'Saving...')
			}
			if (!this.isLastStep) {
				return t('empleados', 'Next')
			}
			return this.manual
				? t('empleados', 'Close')
				: t('empleados', 'Enter the office')
		},
	},

	watch: {
		show(visible) {
			if (visible) {
				this.restart()
			}
		},
	},

	methods: {
		t,
		restart() {
			this.currentStepIndex = 0
			this.accepted = false
			this.saving = false
			this.focusStepTitle()
		},
		focusStepTitle() {
			this.$nextTick(() => this.$refs.stepTitle?.focus())
		},
		previousStep() {
			if (this.saving || this.currentStepIndex === 0) {
				return
			}
			this.currentStepIndex -= 1
			this.focusStepTitle()
		},
		async nextStep() {
			if (this.primaryDisabled) {
				return
			}
			if (!this.isLastStep) {
				this.currentStepIndex += 1
				this.focusStepTitle()
				return
			}
			if (this.manual) {
				this.$emit('close')
				return
			}

			this.saving = true
			try {
				await completeOfficeSimulationOnboarding(this.version)
				this.$emit('complete')
			} catch (err) {
				this.$emit('error', err)
			} finally {
				this.saving = false
			}
		},
		onModalClose() {
			if (this.manual) {
				this.$emit('close')
			}
		},
	},
}
</script>

<style scoped lang="scss">
.office-intro {
	display: flex;
	flex-direction: column;
	box-sizing: border-box;
	width: min(920px, calc(100vw - 48px));
	max-width: 100%;
	min-height: 580px;
	max-height: calc(100vh - 96px);
	padding: 20px 24px 24px;
	overflow: hidden;
}

.office-intro--embedded {
	width: 100%;
	min-height: 0;
	max-height: none;
	padding: 18px 4px 4px;
}

.office-intro-embedded {
	width: 100%;
	min-width: 0;
}

.office-intro__progress {
	display: flex;
	gap: 18px;
	align-items: center;
	justify-content: flex-end;
	margin-bottom: 14px;
	color: var(--color-text-maxcontrast);
	font-size: 0.82rem;
}

.office-intro__dots {
	display: flex;
	gap: 7px;
}

.office-intro__dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	background: var(--color-border-maxcontrast);
	transition: width 0.2s ease, border-radius 0.2s ease, background 0.2s ease;
}

.office-intro__dot--done {
	background: var(--color-primary-element-light);
}

.office-intro__dot--active {
	width: 24px;
	border-radius: 999px;
	background: var(--color-primary-element);
}

.office-intro__step {
	display: grid;
	grid-template-columns: minmax(300px, 1.05fr) minmax(280px, 0.95fr);
	gap: 32px;
	align-items: center;
	flex: 1;
	min-height: 0;
	overflow-y: auto;
}

.office-intro__visual {
	display: grid;
	place-items: center;
	box-sizing: border-box;
	width: 100%;
	min-height: 340px;
	padding: 22px;
	border: 1px solid var(--color-border);
	border-radius: 24px;
	background: var(--color-background-hover);
}

.office-intro__copy h2 {
	margin: 0 0 12px;
	font-size: clamp(1.55rem, 4vw, 2.25rem);
	line-height: 1.08;
}

.office-intro__copy h2:focus {
	outline: none;
}

.office-intro__lead,
.office-intro__detail {
	margin: 0;
	font-size: 1rem;
	line-height: 1.55;
}

.office-intro__lead {
	color: var(--color-main-text);
}

.office-intro__detail {
	margin-top: 16px;
	color: var(--color-text-maxcontrast);
}

.office-intro__copy blockquote {
	margin: 18px 0 0;
	padding: 12px 14px;
	border-inline-start: 4px solid var(--color-primary-element);
	border-radius: 0 10px 10px 0;
	background: var(--color-primary-element-light);
	font-weight: 600;
	line-height: 1.45;
}

.office-intro__tags {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin: 18px 0 0;
	padding: 0;
	list-style: none;
}

.office-intro__tags li {
	padding: 5px 10px;
	border: 1px solid var(--color-border);
	border-radius: 999px;
	background: var(--color-background-hover);
	font-size: 0.84rem;
}

.office-intro__not-list {
	display: grid;
	gap: 7px;
	margin: 18px 0 0;
	padding: 0;
	list-style: none;
}

.office-intro__not-list li {
	display: flex;
	gap: 8px;
	align-items: center;
}

.office-intro__not-icon {
	display: grid;
	place-items: center;
	width: 20px;
	height: 20px;
	border-radius: 50%;
	background: var(--color-error);
	color: var(--color-primary-element-text);
	font-weight: 800;
}

.office-intro__not-list .office-intro__not-title {
	display: block;
	margin-bottom: 2px;
	font-weight: 700;
}

.office-intro__confirmation {
	display: flex;
	gap: 10px;
	align-items: flex-start;
	margin-top: 18px;
	padding: 12px;
	border: 1px solid var(--color-primary-element);
	border-radius: 12px;
	background: var(--color-primary-element-light);
	font-size: 0.9rem;
	line-height: 1.4;
	cursor: pointer;
}

.office-intro__confirmation input {
	flex: 0 0 auto;
	margin-top: 3px;
}

.office-intro__actions {
	display: flex;
	justify-content: space-between;
	padding-top: 18px;
}

.mini-office {
	position: relative;
	width: min(360px, 100%);
	height: 270px;
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: 18px;
	background: var(--color-main-background);
	box-shadow: 0 14px 35px rgba(0, 0, 0, 0.12);
}

.mini-office::before,
.mini-office::after {
	position: absolute;
	background: var(--color-border);
	content: '';
}

.mini-office::before {
	top: 0;
	bottom: 0;
	left: 58%;
	width: 2px;
}

.mini-office::after {
	top: 48%;
	right: 0;
	left: 58%;
	height: 2px;
}

.mini-office__room {
	position: absolute;
	display: grid;
	place-items: center;
	border-radius: 12px;
	background: var(--color-background-hover);
	font-size: 1.7rem;
}

.mini-office__room--work {
	inset: 20px 46% 20px 20px;
}

.mini-office__room--coffee {
	top: 20px;
	right: 20px;
	bottom: 55%;
	left: 64%;
}

.mini-office__room--meeting {
	top: 56%;
	right: 20px;
	bottom: 20px;
	left: 64%;
}

.mini-office__node {
	position: absolute;
	z-index: 2;
	display: grid;
	place-items: center;
	width: 38px;
	height: 38px;
	border: 3px solid var(--color-main-background);
	border-radius: 50%;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-size: 0.68rem;
	font-weight: 800;
	box-shadow: 0 5px 14px rgba(0, 0, 0, 0.2);
	animation: intro-float 3.2s ease-in-out infinite;
}

.mini-office__node--one {
	top: 42px;
	left: 56px;
}

.mini-office__node--two {
	top: 158px;
	left: 130px;
	animation-delay: -0.8s;
}

.mini-office__node--three {
	top: 48px;
	right: 52px;
	animation-delay: -1.6s;
}

.mini-office__node--four {
	right: 82px;
	bottom: 40px;
	animation-delay: -2.4s;
}

.data-map {
	display: grid;
	place-items: center;
	width: 100%;
}

.data-map__employee {
	display: flex;
	gap: 9px;
	align-items: center;
	padding: 10px 18px;
	border: 2px solid var(--color-primary-element);
	border-radius: 999px;
	background: var(--color-main-background);
}

.data-map__employee span {
	font-size: 1.6rem;
}

.data-map__arrow,
.emergence__arrow {
	color: var(--color-primary-element);
	font-size: 2rem;
	font-weight: 700;
	animation: intro-arrow 1.8s ease-in-out infinite;
}

.data-map__signals {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 8px;
	width: 100%;
}

.data-map__signal,
.event-board__item {
	display: flex;
	gap: 8px;
	align-items: center;
	padding: 8px 10px;
	border: 1px solid var(--color-border);
	border-radius: 10px;
	background: var(--color-main-background);
	font-size: 0.78rem;
}

.data-map__signals b,
.event-board__column b {
	font-size: 1.1rem;
}

.emergence {
	display: grid;
	grid-template-columns: 1fr auto 1fr;
	gap: 20px;
	align-items: center;
	width: 100%;
}

.emergence__field {
	position: relative;
	height: 210px;
	border: 1px dashed var(--color-border-maxcontrast);
	border-radius: 18px;
	background: var(--color-main-background);
}

.emergence__node {
	position: absolute;
	width: 20px;
	height: 20px;
	border-radius: 50%;
	background: var(--color-primary-element);
	box-shadow: 0 0 0 5px var(--color-primary-element-light);
}

.emergence__field--grouped .emergence__node {
	animation: intro-gather 2.8s ease-in-out infinite alternate;
}

.emergence__field--loose .emergence__node:nth-child(1) { top: 18%; left: 16%; }
.emergence__field--loose .emergence__node:nth-child(2) { top: 65%; left: 12%; }
.emergence__field--loose .emergence__node:nth-child(3) { top: 35%; left: 48%; }
.emergence__field--loose .emergence__node:nth-child(4) { top: 76%; left: 58%; }
.emergence__field--loose .emergence__node:nth-child(5) { top: 12%; left: 74%; }
.emergence__field--loose .emergence__node:nth-child(6) { top: 52%; left: 80%; }
.emergence__field--grouped .emergence__node:nth-child(1) { top: 28%; left: 30%; }
.emergence__field--grouped .emergence__node:nth-child(2) { top: 46%; left: 22%; }
.emergence__field--grouped .emergence__node:nth-child(3) { top: 55%; left: 45%; }
.emergence__field--grouped .emergence__node:nth-child(4) { top: 33%; left: 53%; }
.emergence__field--grouped .emergence__node:nth-child(5) { top: 63%; left: 67%; }
.emergence__field--grouped .emergence__node:nth-child(6) { top: 24%; left: 76%; }

.state-model {
	position: relative;
	width: min(340px, 100%);
	height: 270px;
}

.state-model::before,
.state-model::after {
	position: absolute;
	top: 50%;
	left: 50%;
	background: var(--color-border-maxcontrast);
	content: '';
	transform: translate(-50%, -50%);
}

.state-model::before {
	width: 72%;
	height: 2px;
}

.state-model::after {
	width: 2px;
	height: 72%;
}

.state-model__center,
.state-model__state {
	position: absolute;
	z-index: 1;
	display: grid;
	place-items: center;
	transform: translate(-50%, -50%);
}

.state-model__center {
	top: 50%;
	left: 50%;
	width: 54px;
	height: 54px;
	border-radius: 50%;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-size: 2rem;
	animation: intro-pulse 2s ease-in-out infinite;
}

.state-model__state {
	gap: 3px;
	min-width: 100px;
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-main-background);
}

.state-model__icon {
	font-size: 1.4rem;
}

.state-model__state small {
	font-size: 0.63rem;
	font-weight: 700;
}

.state-model__state--1 { top: 8%; left: 50%; }
.state-model__state--2 { top: 50%; left: 15%; }
.state-model__state--3 { top: 50%; left: 85%; }
.state-model__state--4 { top: 92%; left: 50%; }

.event-board {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 14px;
	width: 100%;
}

.event-board__column {
	display: grid;
	gap: 7px;
	align-content: start;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: 14px;
	background: var(--color-main-background);
}

.event-board__column > strong {
	margin-bottom: 2px;
	font-size: 0.7rem;
	letter-spacing: 0.05em;
}

.event-board__column--data > strong {
	color: var(--color-success);
}

.event-board__column--simulated > strong {
	color: var(--color-primary-element);
}

.event-board__item {
	animation: intro-appear 3s ease-in-out infinite;
}

.event-board__item:nth-child(3n) { animation-delay: -1s; }
.event-board__item:nth-child(2n) { animation-delay: -2s; }

.privacy-visual {
	display: grid;
	gap: 10px;
	width: 100%;
}

.privacy-visual div {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
	gap: 10px;
	align-items: center;
	padding: 11px;
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-main-background);
	font-size: 0.78rem;
	text-align: center;
}

.privacy-visual strong {
	color: var(--color-error);
	font-size: 1.4rem;
}

.office-intro-step-enter-active,
.office-intro-step-leave-active {
	transition: opacity 0.18s ease, transform 0.18s ease;
}

.office-intro-step-enter,
.office-intro-step-leave-to {
	opacity: 0;
	transform: translateY(8px);
}

@keyframes intro-float {
	0%, 100% { transform: translateY(0); }
	50% { transform: translateY(-7px); }
}

@keyframes intro-arrow {
	0%, 100% { opacity: 0.5; transform: translateY(-2px); }
	50% { opacity: 1; transform: translateY(3px); }
}

@keyframes intro-gather {
	from { transform: translate(-3px, 2px); }
	to { transform: translate(4px, -3px); }
}

@keyframes intro-pulse {
	0%, 100% { box-shadow: 0 0 0 0 var(--color-primary-element-light); }
	50% { box-shadow: 0 0 0 12px transparent; }
}

@keyframes intro-appear {
	0%, 100% { opacity: 0.72; transform: translateY(0); }
	50% { opacity: 1; transform: translateY(-2px); }
}

@media (max-width: 760px) {
	.office-intro {
		width: calc(100vw - 24px);
		min-height: 0;
		max-height: calc(100vh - 32px);
		padding: 14px 16px 18px;
		overflow-y: auto;
	}

	.office-intro--embedded {
		width: 100%;
		max-height: none;
		padding: 12px 0 0;
	}

	.office-intro__step {
		grid-template-columns: 1fr;
		gap: 20px;
		overflow: visible;
	}

	.office-intro__visual {
		min-height: 260px;
		padding: 14px;
	}

	.mini-office {
		height: 230px;
	}

	.event-board {
		grid-template-columns: 1fr;
	}

	.office-intro__actions {
		position: sticky;
		bottom: -18px;
		z-index: 2;
		margin: 12px -16px -18px;
		padding: 12px 16px 18px;
		background: var(--color-main-background);
	}
}

@media (max-width: 420px) {
	.office-intro__progress {
		justify-content: space-between;
	}

	.data-map__signals {
		grid-template-columns: 1fr;
	}

	.emergence {
		gap: 8px;
	}

	.emergence__field {
		height: 170px;
	}

	.privacy-visual div {
		grid-template-columns: 1fr;
		gap: 4px;
	}
}

@media (prefers-reduced-motion: reduce) {
	.office-intro *,
	.office-intro *::before,
	.office-intro *::after {
		animation: none !important;
		transition: none !important;
	}
}
</style>
