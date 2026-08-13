<template>
	<NcDialog
		:open="open"
		:buttons="buttons"
		:name="t('empleados', 'Crop image')"
		size="large"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<div v-if="previewUrl && open">
				<img
					ref="image"
					:src="previewUrl"
					:alt="t('empleados', 'Image to crop')"
					style="max-width: 100%;">
			</div>
			<div v-else>
				<p>{{ t('empleados', 'Select an image to crop.') }}</p>
			</div>
		</template>
	</NcDialog>
</template>

<script>
import Cropper from 'cropperjs'
import 'cropperjs/dist/cropper.css'
import { NcDialog } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'CropperDialog',
	components: { NcDialog },
	props: {
		open: Boolean,
		previewUrl: {
			type: String,
			default: '',
		},
	},
	emits: ['update:open', 'confirm', 'error'],
	data() {
		return {
			cropper: null,
		}
	},
	computed: {
		buttons() {
			return [
				{ label: this.t('empleados', 'Cancel'), type: 'secondary', callback: this.close },
				{ label: this.t('empleados', 'Crop and upload'), type: 'primary', callback: this.confirmCrop },
			]
		},
	},
	watch: {
		open(newVal) {
			if (newVal && this.previewUrl) {
				this.$nextTick(this.initCropper)
			}
		},
		previewUrl(newVal) {
			if (this.open && newVal) {
				this.$nextTick(this.initCropper)
			}
		},
	},
	methods: {
		t,

		initCropper() {
			const img = this.$refs.image
			if (!img) return setTimeout(this.initCropper, 50)

			const createCropper = () => {
				if (this.cropper) this.cropper.destroy()

				this.cropper = new Cropper(img, {
					aspectRatio: 1,
					viewMode: 1,
					autoCropArea: 1,
					background: false,
					dragMode: 'move',
					cropBoxResizable: false,
					cropBoxMovable: true,
					movable: true,
					zoomable: true,
					scalable: false,
					rotatable: false,
				})
			}

			if (img.complete && img.naturalHeight !== 0) {
				createCropper()
			} else {
				img.onload = createCropper
			}
		},

		async confirmCrop() {
			if (!this.cropper || typeof this.cropper.getCroppedCanvas !== 'function') {
				this.$emit('error', this.t('empleados', 'Cropper not available.'))
				return
			}
			const canvas = this.cropper.getCroppedCanvas({ width: 256, height: 256 })
			const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'))
			this.$emit('confirm', blob)
			this.close()
		},

		close() {
			if (this.cropper) {
				this.cropper.destroy()
				this.cropper = null
			}
			this.$emit('update:open', false)
		},
	},
}
</script>

<style scoped>
img {
	max-height: 400px;
	display: block;
	margin: 0 auto;
}
</style>
