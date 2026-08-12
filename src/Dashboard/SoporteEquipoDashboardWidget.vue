<template>
	<div class="support-widget-card">
		<p class="support-widget-card__intro">
			{{ t('empleados', 'Search a device to register support or open its inventory record.') }}
		</p>

		<div class="support-widget-search">
			<NcTextField
				ref="searchField"
				:value.sync="search"
				:label="t('empleados', 'Search device')"
				@update:value="scheduleSearch">
				<template #icon>
					<Magnify :size="20" />
				</template>
			</NcTextField>

			<div v-if="searching" class="support-widget-state" role="status">
				<NcLoadingIcon :size="20" />
				<span>{{ t('empleados', 'Searching devices') }}</span>
			</div>

			<div
				v-else-if="searchCompleted && !selectedDevice && results.length === 0"
				class="support-widget-state">
				{{ t('empleados', 'No results') }}
			</div>

			<div v-else-if="!selectedDevice && results.length" class="support-widget-results">
				<button
					v-for="device in results"
					:key="device.id_equipo"
					type="button"
					class="support-widget-result"
					@click="selectDevice(device)">
					<Laptop :size="18" />
					<span>
						<strong>{{ deviceTitle(device) }}</strong>
						<small>{{ deviceSecondary(device) }}</small>
					</span>
				</button>
			</div>
		</div>

		<div v-if="selectedDevice" class="support-widget-selected">
			<div class="support-widget-selected__head">
				<div class="support-widget-selected__info">
					<strong>{{ deviceTitle(selectedDevice) }}</strong>
					<small>{{ deviceSecondary(selectedDevice) }}</small>
				</div>
				<NcButton
					type="tertiary"
					:aria-label="t('empleados', 'Clear selected device')"
					@click="clearDevice">
					<template #icon>
						<Close :size="18" />
					</template>
				</NcButton>
			</div>

			<div class="support-widget-actions">
				<NcButton
					type="primary"
					wide
					@click="openSupportModal">
					<template #icon>
						<Wrench :size="18" />
					</template>
					{{ t('empleados', 'Register support') }}
				</NcButton>
				<NcButton
					type="secondary"
					wide
					@click="openRegistry">
					<template #icon>
						<Laptop :size="18" />
					</template>
					{{ t('empleados', 'Open registry') }}
				</NcButton>
			</div>
		</div>

		<small v-else class="support-widget-hint">
			{{ t('empleados', 'The time will be added automatically to your reports as a non-billable activity.') }}
		</small>

		<NcModal
			v-if="modalOpen"
			:name="t('empleados', 'Register device support')"
			size="large"
			class="support-widget-modal"
			@close="closeModal">
			<div class="support-widget-modal__content">
				<RegistrarSoporteForm
					ref="supportForm"
					:initial-device="selectedDevice"
					:hide-device-search="true"
					@cancel="closeModal"
					@success="handleSuccess" />
			</div>
		</NcModal>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { NcButton, NcLoadingIcon, NcModal, NcTextField } from '@nextcloud/vue'
import Close from 'vue-material-design-icons/Close.vue'
import Laptop from 'vue-material-design-icons/Laptop.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Wrench from 'vue-material-design-icons/Wrench.vue'

import RegistrarSoporteForm from '../components/Inventario/RegistrarSoporteForm.vue'
import inventarioService from '../services/inventarioService.js'

export default {
	name: 'SoporteEquipoDashboardWidget',

	components: {
		Close,
		Laptop,
		Magnify,
		NcButton,
		NcLoadingIcon,
		NcModal,
		NcTextField,
		RegistrarSoporteForm,
		Wrench,
	},

	data() {
		return {
			search: '',
			searching: false,
			searchCompleted: false,
			results: [],
			searchTimer: null,
			searchSequence: 0,
			selectedDevice: null,
			modalOpen: false,
		}
	},

	beforeDestroy() {
		this.cancelSearch()
	},

	methods: {
		t,

		deviceTitle(device) {
			return device?.nombre_dispositivo
				|| device?.nombre_sistema
				|| t('empleados', 'Device')
		},

		deviceSecondary(device) {
			return [
				device?.nombre_sistema,
				device?.numero_serie,
				device?.empleado_displayname || device?.empleado_uid,
			].filter(Boolean).join(' · ')
		},

		normalizeDevices(value) {
			if (Array.isArray(value)) {
				return value
			}
			if (value && typeof value === 'object') {
				return Object.values(value)
			}
			return []
		},

		scheduleSearch() {
			if (this.searchTimer) {
				clearTimeout(this.searchTimer)
			}

			const query = this.search.trim()
			if (query.length < 2) {
				this.cancelSearch()
				this.searchCompleted = false
				this.results = []
				return
			}

			const sequence = ++this.searchSequence
			this.searchTimer = setTimeout(() => this.searchDevices(query, sequence), 400)
		},

		async searchDevices(query, sequence) {
			if (sequence !== this.searchSequence || query !== this.search.trim()) {
				return
			}

			this.searching = true
			this.searchCompleted = false

			try {
				const response = await inventarioService.getEquipos({
					search: query,
					limit: 8,
					offset: 0,
				})
				if (sequence !== this.searchSequence) {
					return
				}
				this.results = this.normalizeDevices(response?.data).slice(0, 8)
				this.searchCompleted = true
			} catch (error) {
				if (sequence !== this.searchSequence) {
					return
				}
				this.results = []
				this.searchCompleted = true
				showError(t('empleados', 'Devices could not be loaded.'))
			} finally {
				if (sequence === this.searchSequence) {
					this.searching = false
				}
			}
		},

		cancelSearch() {
			if (this.searchTimer) {
				clearTimeout(this.searchTimer)
			}
			this.searchTimer = null
			this.searchSequence += 1
			this.searching = false
		},

		selectDevice(device) {
			this.cancelSearch()
			this.selectedDevice = { ...device }
			this.results = []
			this.searchCompleted = false
			this.search = this.deviceTitle(device)
		},

		clearDevice() {
			this.selectedDevice = null
			this.search = ''
			this.results = []
			this.searchCompleted = false
			this.$nextTick(() => {
				this.$refs.searchField?.$el?.querySelector('input')?.focus()
			})
		},

		openSupportModal() {
			if (!this.selectedDevice) {
				return
			}
			this.modalOpen = true
		},

		closeModal() {
			this.modalOpen = false
		},

		handleSuccess() {
			// Keep modal open with success state until the user closes it.
		},

		openRegistry() {
			if (!this.selectedDevice?.id_equipo) {
				return
			}
			const url = `${generateUrl('/apps/empleados/')}#/Inventario?deviceId=${encodeURIComponent(this.selectedDevice.id_equipo)}`
			window.location.href = url
		},
	},
}
</script>

<style scoped lang="scss">
.support-widget-card {
	display: grid;
	gap: 12px;
	padding: 8px;
	min-width: 0;
}

.support-widget-card__intro {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	line-height: 1.4;
}

.support-widget-search,
.support-widget-selected,
.support-widget-actions {
	display: grid;
	gap: 8px;
	min-width: 0;
}

.support-widget-state {
	display: flex;
	gap: 8px;
	align-items: center;
	min-height: 36px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.support-widget-results {
	display: grid;
	gap: 4px;
	max-height: 180px;
	padding: 4px;
	overflow-y: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.support-widget-result {
	display: flex;
	gap: 8px;
	align-items: center;
	width: 100%;
	min-height: 44px;
	padding: 8px;
	border: 0;
	border-radius: var(--border-radius-large);
	background: transparent;
	color: var(--color-main-text);
	cursor: pointer;
	text-align: start;
}

.support-widget-result:hover,
.support-widget-result:focus-visible {
	background: var(--color-background-hover);
	outline: 2px solid var(--color-primary-element);
}

.support-widget-result span,
.support-widget-result strong,
.support-widget-result small,
.support-widget-selected__info strong,
.support-widget-selected__info small {
	display: block;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.support-widget-result span {
	flex: 1;
	min-width: 0;
}

.support-widget-result small,
.support-widget-selected__info small,
.support-widget-hint {
	color: var(--color-text-maxcontrast);
}

.support-widget-selected {
	padding: 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.support-widget-selected__head {
	display: flex;
	gap: 8px;
	align-items: flex-start;
}

.support-widget-selected__info {
	flex: 1;
	min-width: 0;
}

.support-widget-hint {
	display: block;
	font-size: 12px;
	line-height: 1.35;
}

.support-widget-modal__content {
	box-sizing: border-box;
	width: 100%;
	max-width: 100%;
	max-height: calc(100vh - 140px);
	padding: 8px 4px 12px;
	overflow-x: hidden;
	overflow-y: auto;
}

.support-widget-modal :deep(.modal-container) {
	width: min(640px, calc(100vw - 24px)) !important;
	max-width: min(640px, calc(100vw - 24px)) !important;
}

.support-widget-modal :deep(.modal-container__content) {
	overflow-x: hidden;
	max-width: 100%;
}

@media (max-width: 520px) {
	.support-widget-modal__content {
		max-height: calc(100vh - 96px);
		padding: 4px 0 8px;
	}
}
</style>
