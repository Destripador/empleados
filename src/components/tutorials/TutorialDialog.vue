<template>
	<NcModal v-if="show && !isAnniversaryStep"
		:size="modalSize"
		:name="name"
		@close="onDismiss">
		<div class="tutorial-dialog">
			<div class="tutorial-dialog__body">
				<template v-if="hasSteps">
					<p v-if="currentStepText">{{ currentStepText }}</p>
					<div v-if="currentStepVideo"
						class="tutorial-dialog__video">
						<iframe
							:src="currentStepVideo"
							:title="name"
							frameborder="0"
							allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
							allowfullscreen
							referrerpolicy="strict-origin-when-cross-origin" />
					</div>
				</template>
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

/**
 * Convert a YouTube watch/share URL into an embeddable URL.
 * Leaves already-embed URLs and non-YouTube links untouched.
 *
 * @param {string} url
 * @return {string}
 */
function toEmbedUrl(url) {
	if (!url || typeof url !== 'string') {
		return ''
	}

	const trimmed = url.trim()
	if (!trimmed) {
		return ''
	}

	try {
		const parsed = new URL(trimmed)
		const host = parsed.hostname.replace(/^www\./, '')

		if (host === 'youtu.be') {
			const id = parsed.pathname.replace(/^\//, '').split('/')[0]
			return id ? `https://www.youtube-nocookie.com/embed/${id}` : trimmed
		}

		if (host === 'youtube.com' || host === 'm.youtube.com' || host === 'youtube-nocookie.com') {
			if (parsed.pathname.startsWith('/embed/')) {
				// Prefer privacy-enhanced host while keeping an already-valid embed path.
				if (host === 'youtube.com' || host === 'm.youtube.com') {
					return `https://www.youtube-nocookie.com${parsed.pathname}${parsed.search}`
				}
				return trimmed
			}
			const id = parsed.searchParams.get('v')
			if (id) {
				return `https://www.youtube-nocookie.com/embed/${id}`
			}
		}
	} catch (e) {
		return trimmed
	}

	return trimmed
}

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
		 * Optional multi-step content. Each entry may be a string or an object
		 * `{ text, video }` / `{ type: 'anniversary' }`. When provided, the dialog
		 * walks through each step and only completes the tutorial on the last one
		 * (unless the last step is handled externally, e.g. anniversary).
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

	emits: ['complete', 'close', 'error', 'anniversary-step'],

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

		currentStep() {
			return this.steps[this.currentStepIndex] || null
		},

		isAnniversaryStep() {
			const step = this.currentStep
			return !!(step && typeof step === 'object' && step.type === 'anniversary')
		},

		currentStepText() {
			const step = this.currentStep
			if (step && typeof step === 'object') {
				return step.text || ''
			}
			return step || ''
		},

		currentStepVideo() {
			const step = this.currentStep
			if (!step || typeof step !== 'object' || !step.video) {
				return ''
			}
			return toEmbedUrl(step.video)
		},

		hasAnyVideo() {
			return this.steps.some(step => typeof step === 'object' && step?.video)
		},

		modalSize() {
			return this.hasAnyVideo ? 'large' : 'normal'
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
				this.$nextTick(() => this.emitAnniversaryStepIfNeeded())
			}
		},

		currentStepIndex() {
			this.emitAnniversaryStepIfNeeded()
		},
	},

	methods: {
		t,

		emitAnniversaryStepIfNeeded() {
			if (this.show && this.isAnniversaryStep) {
				this.$emit('anniversary-step')
			}
		},

		async onPrimary() {
			if (this.saving) {
				return
			}

			if (!this.isLastStep) {
				this.currentStepIndex += 1
				return
			}

			if (this.isAnniversaryStep) {
				this.$emit('anniversary-step')
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

.tutorial-dialog__video {
	position: relative;
	width: 100%;
	aspect-ratio: 16 / 9;
	overflow: hidden;
	border-radius: var(--border-radius-large, 8px);
	background: var(--color-background-dark, #000);

	iframe {
		position: absolute;
		inset: 0;
		width: 100%;
		height: 100%;
		border: 0;
	}
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
