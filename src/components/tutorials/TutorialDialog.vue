<template>
	<NcModal v-if="show"
		size="normal"
		:name="name"
		@close="onDismiss">
		<div class="tutorial-dialog">
			<div class="tutorial-dialog__body">
				<slot />
			</div>

			<div class="tutorial-dialog__actions">
				<NcButton type="primary"
					:disabled="saving"
					@click="onConfirm">
					<template #icon>
						<NcLoadingIcon v-if="saving" :size="20" />
					</template>
					{{ saving ? t('empleados', 'Saving...') : t('empleados', 'Got it') }}
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
		completeOnClose: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['complete', 'close', 'error'],

	data() {
		return {
			saving: false,
		}
	},

	methods: {
		t,

		async onConfirm() {
			if (this.saving) {
				return
			}

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
				this.saving = true
				try {
					await completeTutorial(this.lessonId)
					this.$emit('complete')
				} catch (err) {
					this.$emit('error', err)
				} finally {
					this.saving = false
				}
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
}
</style>
