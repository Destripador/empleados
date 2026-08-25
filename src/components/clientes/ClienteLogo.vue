<template>
	<span class="cliente-logo" :class="[`cliente-logo--${size}`, { 'cliente-logo--placeholder': !showImage }]">
		<img
			v-if="showImage"
			:src="resolvedSrc"
			:alt="alt"
			class="cliente-logo__image"
			@error="onError">
		<OfficeBuilding v-else :size="iconSize" />
	</span>
</template>

<script>
import OfficeBuilding from 'vue-material-design-icons/OfficeBuilding.vue'
import { clienteLogoUrl } from '../../services/clientesService.js'

export default {
	name: 'ClienteLogo',

	components: {
		OfficeBuilding,
	},

	props: {
		id: {
			type: [Number, String],
			default: null,
		},
		logo: {
			type: [String, Boolean, Number],
			default: null,
		},
		src: {
			type: String,
			default: '',
		},
		bust: {
			type: [String, Number],
			default: null,
		},
		size: {
			type: String,
			default: 'md',
		},
		alt: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			failed: false,
		}
	},

	computed: {
		hasLogo() {
			if (this.src) {
				return true
			}
			if (this.logo === false || this.logo === 0 || this.logo === '0') {
				return false
			}
			return Boolean(this.logo) && Boolean(this.id)
		},

		resolvedSrc() {
			if (this.src) {
				return this.src
			}
			if (!this.hasLogo) {
				return ''
			}
			return clienteLogoUrl(this.id, this.bust || this.logo)
		},

		showImage() {
			return Boolean(this.resolvedSrc) && !this.failed
		},

		iconSize() {
			if (this.size === 'lg') {
				return 28
			}
			if (this.size === 'sm') {
				return 16
			}
			return 22
		},
	},

	watch: {
		resolvedSrc() {
			this.failed = false
		},
	},

	methods: {
		onError() {
			this.failed = true
		},
	},
}
</script>

<style scoped lang="scss">
.cliente-logo {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	overflow: hidden;
	border-radius: var(--border-radius);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
}

.cliente-logo--sm {
	width: 1.5rem;
	height: 1.5rem;
}

.cliente-logo--md {
	width: 2.25rem;
	height: 2.25rem;
}

.cliente-logo--lg {
	width: 3.25rem;
	height: 3.25rem;
	border-radius: var(--border-radius-large);
}

.cliente-logo__image {
	width: 100%;
	height: 100%;
	object-fit: contain;
	background: var(--color-main-background);
}
</style>
