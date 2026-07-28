<template>
	<span
		class="help-hint"
		:class="`help-hint--${placement}`"
		@mouseenter="onMouseEnter"
		@mouseleave="onMouseLeave">
		<button
			ref="trigger"
			type="button"
			class="help-hint__trigger"
			:aria-label="accessibleLabel"
			:aria-expanded="String(open)"
			:aria-controls="tooltipId"
			:aria-describedby="open ? tooltipId : null"
			@click="togglePinned"
			@focus="onFocus"
			@blur="onBlur"
			@keydown.esc.stop="closeHint">
			<HelpCircleOutline :size="16" />
		</button>

		<span
			v-show="open"
			:id="tooltipId"
			ref="tooltip"
			class="help-hint__tooltip"
			role="tooltip"
			:style="tooltipStyle">
			{{ text }}
		</span>
	</span>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import HelpCircleOutline from 'vue-material-design-icons/HelpCircleOutline.vue'

let helpHintId = 0

export default {
	name: 'HelpHint',
	components: {
		HelpCircleOutline,
	},
	props: {
		text: {
			type: String,
			required: true,
		},
		label: {
			type: String,
			default: '',
		},
		placement: {
			type: String,
			default: 'top',
			validator: value => ['top', 'bottom'].includes(value),
		},
	},
	data() {
		helpHintId += 1

		return {
			open: false,
			pinned: false,
			hovered: false,
			focused: false,
			tooltipId: `empleados-help-hint-${helpHintId}`,
			tooltipStyle: {},
		}
	},
	computed: {
		accessibleLabel() {
			return this.label || t('empleados', 'More information')
		},
	},
	mounted() {
		window.addEventListener('resize', this.onViewportChange)
		window.addEventListener('scroll', this.onViewportChange, true)
	},
	beforeDestroy() {
		window.removeEventListener('resize', this.onViewportChange)
		window.removeEventListener('scroll', this.onViewportChange, true)
	},
	methods: {
		onMouseEnter() {
			this.hovered = true
			this.openHint()
		},
		onMouseLeave() {
			this.hovered = false

			if (!this.pinned && !this.focused) {
				this.open = false
			}
		},
		onFocus() {
			this.focused = true
			this.openHint()
		},
		onBlur() {
			this.focused = false
			this.pinned = false
			this.open = false
		},
		togglePinned() {
			if (this.pinned) {
				this.closeHint()
				return
			}

			this.pinned = true
			this.openHint()
		},
		openHint() {
			this.open = true
			this.$nextTick(() => this.positionTooltip())
		},
		closeHint() {
			this.pinned = false
			this.open = false
		},
		onViewportChange() {
			if (this.open) {
				this.positionTooltip()
			}
		},
		positionTooltip() {
			const trigger = this.$refs.trigger
			const tooltip = this.$refs.tooltip

			if (!trigger || !tooltip) {
				return
			}

			const viewportPadding = 12
			const gap = 6
			const triggerRect = trigger.getBoundingClientRect()
			const maxWidth = Math.min(320, window.innerWidth - (viewportPadding * 2))

			this.tooltipStyle = {
				left: `${viewportPadding}px`,
				top: `${viewportPadding}px`,
				width: `${maxWidth}px`,
			}

			this.$nextTick(() => {
				const tooltipRect = tooltip.getBoundingClientRect()
				const preferredTop = this.placement === 'bottom'
					? triggerRect.bottom + gap
					: triggerRect.top - tooltipRect.height - gap
				const alternateTop = this.placement === 'bottom'
					? triggerRect.top - tooltipRect.height - gap
					: triggerRect.bottom + gap
				const topFits = preferredTop >= viewportPadding
					&& preferredTop + tooltipRect.height <= window.innerHeight - viewportPadding
				const top = topFits ? preferredTop : alternateTop
				const centeredLeft = triggerRect.left + (triggerRect.width / 2) - (tooltipRect.width / 2)
				const left = Math.min(
					Math.max(viewportPadding, centeredLeft),
					window.innerWidth - tooltipRect.width - viewportPadding,
				)
				const clampedTop = Math.min(
					Math.max(viewportPadding, top),
					window.innerHeight - tooltipRect.height - viewportPadding,
				)

				this.tooltipStyle = {
					left: `${left}px`,
					top: `${Math.max(viewportPadding, clampedTop)}px`,
					width: `${maxWidth}px`,
				}
			})
		},
	},
}
</script>

<style scoped lang="scss">
.help-hint {
	position: relative;
	display: inline-flex;
	flex: 0 0 auto;
	align-items: center;
	vertical-align: middle;
}

.help-hint__trigger {
	display: inline-flex;
	width: 24px;
	height: 24px;
	align-items: center;
	justify-content: center;
	margin: 0;
	padding: 0;
	border: 0;
	border-radius: 50%;
	background: transparent;
	color: var(--color-text-maxcontrast);
	cursor: help;
}

.help-hint__trigger:hover,
.help-hint__trigger:focus-visible,
.help-hint__trigger[aria-expanded='true'] {
	background: var(--color-background-hover);
	color: var(--color-main-text);
}

.help-hint__trigger:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 1px;
}

.help-hint__tooltip {
	position: fixed;
	z-index: 10000;
	box-sizing: border-box;
	width: min(320px, calc(100vw - 24px));
	padding: 8px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 0.8125rem;
	font-weight: 400;
	line-height: 1.4;
	text-align: left;
	white-space: normal;
}

@media (max-width: 480px) {
	.help-hint__tooltip {
		max-width: calc(100vw - 24px);
	}
}
</style>
