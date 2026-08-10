<template>
	<NcModal v-if="show"
		size="normal"
		:name="name"
		@close="onDismiss">
		<div class="tutorial-dialog">
			<div class="tutorial-dialog__body">
				<p v-if="hasSteps">{{ currentStepText }}</p>
				<slot v-else />
			</div>

			<div class="tutorial-dialog__actions">
				<span v-if="hasSteps"
					class="tutorial-dialog__progress">
					{{ currentStepIndex + 1 }} / {{ steps.length }}
				</span>
				<NcButton type="primary"
					:disabled="saving"
					@click="onPrimary">
					<template #icon>
						<NcLoadingIcon v-if="saving" :size="20" />
					</template>
					{{ primaryLabel }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { completeTutorial } from '../../services/tutorials.js'

export default {
	name: 'TutorialDialog',

	components: {
		NcModal,
		NcButton,
		NcLoadingIcon,
	},

	props: {
		lessonId: {
			type: String,
			required: true,
		},
		name: {
			type: String,
			required: true,
		},
		show: {
			type: Boolean,
			default: false,
		},
		/**
		 * Optional multi-step content. When provided, the dialog walks
		 * through each string and only completes the tutorial on the last step.
		 */
		steps: {
			type: Array,
			default: () => [],
		},
		completeOnClose: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['complete', 'close', 'error'],

	data() {
		return {
			saving: false,
			currentStepIndex: 0,
		}
	},

	computed: {
		hasSteps() {
			return Array.isArray(this.steps) && this.steps.length > 0
		},

		isLastStep() {
			return !this.hasSteps || this.currentStepIndex >= this.steps.length - 1
		},

		currentStepText() {
			return this.steps[this.currentStepIndex] || ''
		},

		primaryLabel() {
			if (this.saving) {
				return t('empleados', 'Saving...')
			}
			if (!this.isLastStep) {
				return t('empleados', 'Next')
			}
			return this.hasSteps
				? t('empleados', 'Finish tutorial')
				: t('empleados', 'Got it')
		},
	},

	watch: {
		show(visible) {
			if (visible) {
				this.currentStepIndex = 0
			}
		},
	},

	methods: {
		t,

		async onPrimary() {
			if (this.saving) {
				return
			}

			if (!this.isLastStep) {
				this.currentStepIndex += 1
				return
			}

			await this.completeLesson()
		},

		async completeLesson() {
			this.saving = true
			try {
				await completeTutorial(this.lessonId)
				this.$emit('complete')
			} catch (err) {
				this.$emit('error', err)
			} finally {
				this.saving = false
			}
		},

		async onDismiss() {
			if (this.saving) {
				return
			}

			if (this.completeOnClose) {
				await this.completeLesson()
				return
			}

			this.$emit('close')
		},
	},
}
</script>

<style scoped lang="scss">
.tutorial-dialog {
	padding: 1rem 1.25rem 1.25rem;
	display: flex;
	flex-direction: column;
	gap: 1.25rem;
}

.tutorial-dialog__body {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	line-height: 1.5;
}

.tutorial-dialog__actions {
	display: flex;
	justify-content: flex-end;
	align-items: center;
	gap: 1rem;
}

.tutorial-dialog__progress {
	margin-right: auto;
	color: var(--color-text-maxcontrast);
	font-size: 0.875rem;
}
</style>
