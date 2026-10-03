<template>
	<div class="well">
		<header class="boarding-header">
			<div>
				<h2>
					{{ boardingOn === 1 ? t('empleados', 'OnBoarding') : t('empleados', 'OffBoarding') }}
				</h2>
				<p v-if="!boardingLoading && boardingItemsFiltered.length" class="boarding-progress-text">
					{{ t('empleados', '{completed} of {total} completed', {
						completed: completedCount,
						total: boardingItemsFiltered.length,
					}) }}
				</p>
			</div>

			<div class="boarding-toggle" :class="{ 'boarding-toggle--off': boardingOn === 0 }">
				<span class="boarding-toggle-thumb" aria-hidden="true" />
				<button
					type="button"
					class="boarding-toggle-btn"
					:class="{ active: boardingOn === 1 }"
					:aria-pressed="boardingOn === 1 ? 'true' : 'false'"
					:disabled="boardingLoading"
					@click="setBoardingOn(1)">
					{{ t('empleados', 'On') }}
				</button>
				<button
					type="button"
					class="boarding-toggle-btn"
					:class="{ active: boardingOn === 0 }"
					:aria-pressed="boardingOn === 0 ? 'true' : 'false'"
					:disabled="boardingLoading"
					@click="setBoardingOn(0)">
					{{ t('empleados', 'Off') }}
				</button>
			</div>
		</header>

		<div
			v-if="!boardingLoading && boardingItemsFiltered.length"
			class="boarding-progress"
			role="progressbar"
			:aria-valuemin="0"
			:aria-valuemax="boardingItemsFiltered.length"
			:aria-valuenow="completedCount">
			<span class="boarding-progress__bar" :style="{ width: progressPercent + '%' }" />
		</div>

		<NcEmptyContent v-if="boardingLoading" :name="t('empleados', 'Loading')">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>

		<template v-else>
			<ul v-if="boardingItemsFiltered.length" class="onboarding-checklist">
				<li
					v-for="item in boardingItemsFiltered"
					:key="item.id_empleado_boarding"
					class="onboarding-item"
					:class="{ 'onboarding-item--done': isChecked(item) }">
					<NcCheckboxRadioSwitch
						:checked="isChecked(item)"
						:disabled="boardingSavingId === item.id_empleado_boarding"
						@update:checked="value => toggleItemStatus(item, value)">
						{{ item.nombre }}
					</NcCheckboxRadioSwitch>
					<NcLoadingIcon v-if="boardingSavingId === item.id_empleado_boarding" :size="16" />
				</li>
			</ul>
			<p v-else class="boarding-empty">
				{{ t('empleados', 'No items in this checklist yet.') }}
			</p>
		</template>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import {
	NcCheckboxRadioSwitch,
	NcEmptyContent,
	NcLoadingIcon,
} from '@nextcloud/vue'

export default {
	name: 'BoardingTab',

	components: {
		NcCheckboxRadioSwitch,
		NcEmptyContent,
		NcLoadingIcon,
	},

	props: {
		data: { type: Object, required: true },
	},

	data() {
		return {
			boardingOn: 1,
			boardingItems: [],
			boardingLoading: false,
			boardingSavingId: null,
		}
	},

	computed: {
		boardingItemsFiltered() {
			return this.boardingItems.filter(item => Number(item.on) === this.boardingOn)
		},
		completedCount() {
			return this.boardingItemsFiltered.filter(item => this.isChecked(item)).length
		},
		progressPercent() {
			if (!this.boardingItemsFiltered.length) return 0
			return Math.round((this.completedCount / this.boardingItemsFiltered.length) * 100)
		},
	},

	watch: {
		'data.Id_empleados': {
			immediate: true,
			handler(idEmpleado) {
				this.boardingItems = []
				if (idEmpleado) {
					this.cargarBoardingChecklist()
				}
			},
		},
	},

	methods: {
		t,

		setBoardingOn(on) {
			this.boardingOn = on
		},

		isChecked(item) {
			return Number(item.status) === 1
		},

		async cargarBoardingChecklist() {
			const idEmpleado = this.data?.Id_empleados
			if (!idEmpleado) return

			this.boardingLoading = true
			try {
				await Promise.all([
					axios.post(generateUrl('/apps/empleados/generarChecklistEmpleado'), { id_empleado: idEmpleado, on: 1 }),
					axios.post(generateUrl('/apps/empleados/generarChecklistEmpleado'), { id_empleado: idEmpleado, on: 0 }),
				])

				const response = await axios.post(generateUrl('/apps/empleados/getChecklistEmpleado'), {
					id_empleado: idEmpleado,
				})
				const data = response?.data?.ocs?.data
				this.boardingItems = Array.isArray(data) ? data : []
			} catch (err) {
				showError(t('empleados', 'No se pudo cargar el checklist [{error}]', { error: String(err), close: true }))
			} finally {
				this.boardingLoading = false
			}
		},

		async toggleItemStatus(item, checked) {
			const nuevoStatus = checked ? 1 : 0
			this.boardingSavingId = item.id_empleado_boarding
			try {
				await axios.post(generateUrl('/apps/empleados/marcarStatusBoarding'), {
					id_empleado_boarding: item.id_empleado_boarding,
					status: nuevoStatus,
				})
				item.status = nuevoStatus
			} catch (err) {
				showError(t('empleados', 'No se pudo actualizar el ítem [{error}]', { error: String(err), close: true }))
			} finally {
				this.boardingSavingId = null
			}
		},
	},
}
</script>

<style scoped>
.boarding-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 14px;
}

.boarding-header h2 {
	margin: 0;
	font-size: 18px;
	font-weight: 700;
	line-height: 1.3;
	color: var(--color-main-text);
}

.boarding-progress-text {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.boarding-progress {
	height: 6px;
	margin: 0 0 16px;
	overflow: hidden;
	border-radius: 999px;
	background: var(--color-background-darker, var(--color-background-hover));
}

.boarding-progress__bar {
	display: block;
	height: 100%;
	background: var(--color-primary-element);
	transition: width 180ms ease;
}

.boarding-toggle {
	position: relative;
	display: inline-flex !important;
	flex-shrink: 0;
	width: 76px !important;
	height: 20px !important;
	padding: 2px !important;
	margin: 4px 0 0 !important;
	background: var(--color-background-darker, var(--color-background-hover)) !important;
	border: 1px solid var(--color-border) !important;
	border-radius: 999px !important;
	box-sizing: border-box;
}

.boarding-toggle-thumb {
	position: absolute;
	top: 2px;
	left: 2px;
	width: calc(50% - 2px);
	height: calc(100% - 4px);
	border-radius: 999px;
	background: var(--color-primary-element);
	transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
	pointer-events: none;
}

.boarding-toggle--off .boarding-toggle-thumb {
	transform: translateX(100%);
}

.boarding-toggle-btn,
.boarding-toggle-btn:hover,
.boarding-toggle-btn:focus,
.boarding-toggle-btn:focus-visible,
.boarding-toggle-btn:active {
	all: unset;
	position: relative;
	z-index: 1;
	box-sizing: border-box;
	display: flex !important;
	flex: 1 1 0;
	align-items: center;
	justify-content: center;
	height: 100% !important;
	min-height: 0 !important;
	padding: 0 !important;
	margin: 0 !important;
	background: transparent !important;
	border: 0 !important;
	border-radius: 999px !important;
	outline: 0 !important;
	box-shadow: none !important;
	font-size: 9.5px !important;
	font-weight: 700 !important;
	line-height: 1 !important;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	-webkit-appearance: none;
	appearance: none;
	transition: color 0.18s ease;
}

.boarding-toggle-btn:disabled {
	cursor: not-allowed;
	opacity: 0.6;
}

.boarding-toggle-btn:hover:not(:disabled):not(.active) {
	color: var(--color-main-text) !important;
}

.boarding-toggle-btn.active,
.boarding-toggle-btn.active:hover,
.boarding-toggle-btn.active:focus {
	color: var(--color-primary-element-text, #fff) !important;
}

.onboarding-checklist {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 0;
	margin: 0;
	list-style: none;
}

.onboarding-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
	padding: 4px 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-main-text);
	font-size: 14px;
	transition: background-color 120ms ease, border-color 120ms ease;
}

.onboarding-item--done {
	background: var(--color-background-dark);
	border-color: var(--color-border);
}

.onboarding-item--done :deep(.checkbox-radio-switch__label) {
	color: var(--color-text-maxcontrast);
	text-decoration: line-through;
}

.boarding-empty {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

@media (max-width: 600px) {
	.boarding-header {
		flex-direction: column;
		align-items: stretch;
	}

	.boarding-toggle {
		align-self: flex-end;
	}
}
</style>
