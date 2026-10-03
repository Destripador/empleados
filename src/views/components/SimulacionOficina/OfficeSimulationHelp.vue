<template>
	<NcModal
		v-if="show"
		:name="t('empleados', 'How Office Simulation works')"
		:can-close="true"
		size="large"
		class="office-help-modal"
		@close="requestClose">
		<section class="office-help">
			<header class="office-help__toolbar">
				<div
					class="office-help__tabs"
					role="tablist"
					:aria-label="t('empleados', 'Office Simulation help sections')"
					@keydown.left.prevent="selectRelativeTab(-1)"
					@keydown.right.prevent="selectRelativeTab(1)">
					<button
						v-for="tab in tabs"
						:id="`office-help-tab-${tab.id}`"
						:ref="`tab-${tab.id}`"
						:key="tab.id"
						type="button"
						role="tab"
						:aria-controls="`office-help-panel-${tab.id}`"
						:aria-selected="activeTab === tab.id ? 'true' : 'false'"
						:tabindex="activeTab === tab.id ? 0 : -1"
						:class="{ 'office-help__tab--active': activeTab === tab.id }"
						class="office-help__tab"
						@click="activeTab = tab.id">
						{{ tab.label }}
					</button>
				</div>

				<div class="office-help__tools">
					<span v-if="version > 0" class="office-help__version">
						{{ t('empleados', 'Intro version {version}', { version }) }}
					</span>
					<NcButton
						v-if="activeTab === 'overview'"
						type="tertiary"
						@click="restartIntroduction">
						{{ t('empleados', 'View introduction again') }}
					</NcButton>
				</div>
			</header>

			<div
				v-show="activeTab === 'overview'"
				id="office-help-panel-overview"
				class="office-help__panel office-help__panel--intro"
				role="tabpanel"
				aria-labelledby="office-help-tab-overview">
				<OfficeSimulationIntro
					ref="introduction"
					:show="show"
					:version="version"
					:manual="true"
					:embedded="true"
					@close="requestClose" />
			</div>

			<div
				v-show="activeTab === 'documentation'"
				id="office-help-panel-documentation"
				class="office-help__panel"
				role="tabpanel"
				aria-labelledby="office-help-tab-documentation">
				<OfficeSimulationDocumentation />
			</div>
		</section>
	</NcModal>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcModal } from '@nextcloud/vue'
import OfficeSimulationDocumentation from './OfficeSimulationDocumentation.vue'
import OfficeSimulationIntro from './OfficeSimulationIntro.vue'

export default {
	name: 'OfficeSimulationHelp',

	components: {
		NcButton,
		NcModal,
		OfficeSimulationDocumentation,
		OfficeSimulationIntro,
	},

	props: {
		show: {
			type: Boolean,
			default: false,
		},
		version: {
			type: Number,
			default: 0,
		},
	},

	emits: ['close'],

	data() {
		return {
			activeTab: 'overview',
		}
	},

	computed: {
		tabs() {
			return [
				{ id: 'overview', label: t('empleados', 'How it works') },
				{ id: 'documentation', label: t('empleados', 'Technical documentation') },
			]
		},
	},

	watch: {
		show(visible) {
			if (visible) {
				this.activeTab = 'overview'
				this.$nextTick(() => this.focusActiveTab())
			}
		},
	},

	methods: {
		t,
		requestClose() {
			this.$emit('close')
		},
		restartIntroduction() {
			this.$refs.introduction?.restart()
		},
		focusActiveTab() {
			const ref = this.$refs[`tab-${this.activeTab}`]
			const tab = Array.isArray(ref) ? ref[0] : ref
			tab?.focus()
		},
		selectRelativeTab(offset) {
			const current = this.tabs.findIndex(tab => tab.id === this.activeTab)
			const next = (current + offset + this.tabs.length) % this.tabs.length
			this.activeTab = this.tabs[next].id
			this.$nextTick(() => this.focusActiveTab())
		},
	},
}
</script>

<style scoped lang="scss">
.office-help {
	display: grid;
	grid-template-rows: auto minmax(0, 1fr);
	box-sizing: border-box;
	width: min(1180px, calc(100vw - 48px));
	max-width: 100%;
	height: min(820px, calc(100vh - 92px));
	min-height: 560px;
	overflow: hidden;
}

.office-help__toolbar {
	display: flex;
	gap: 16px;
	align-items: center;
	justify-content: space-between;
	padding: 8px 18px 12px;
	border-bottom: 1px solid var(--color-border);
}

.office-help__tabs {
	display: flex;
	gap: 4px;
	padding: 3px;
	border-radius: 11px;
	background: var(--color-background-hover);
}

.office-help__tab {
	min-height: 36px;
	padding: 5px 13px;
	border: 0;
	border-radius: 8px;
	background: transparent;
	color: var(--color-text-maxcontrast);
	font-weight: 600;
	cursor: pointer;
}

.office-help__tab:hover,
.office-help__tab:focus-visible {
	color: var(--color-main-text);
}

.office-help__tab--active {
	background: var(--color-main-background);
	color: var(--color-primary-element);
	box-shadow: 0 1px 5px rgba(0, 0, 0, 0.12);
}

.office-help__tools {
	display: flex;
	gap: 10px;
	align-items: center;
}

.office-help__version {
	padding: 3px 8px;
	border: 1px solid var(--color-border);
	border-radius: 999px;
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	white-space: nowrap;
}

.office-help__panel {
	min-width: 0;
	min-height: 0;
	overflow: hidden;
}

.office-help__panel--intro {
	padding: 0 18px 18px;
	overflow-x: hidden;
	overflow-y: auto;
}

@media (max-width: 760px) {
	.office-help {
		width: calc(100vw - 24px);
		height: calc(100vh - 34px);
		min-height: 0;
	}

	.office-help__toolbar {
		display: grid;
		gap: 8px;
		padding: 6px 10px 10px;
	}

	.office-help__tabs {
		width: 100%;
	}

	.office-help__tab {
		flex: 1;
		min-width: 0;
		padding-inline: 7px;
		font-size: 0.8rem;
	}

	.office-help__tools {
		justify-content: space-between;
	}

	.office-help__panel--intro {
		padding: 0 10px 10px;
	}
}

@media (max-width: 430px) {
	.office-help__version {
		display: none;
	}
}
</style>
