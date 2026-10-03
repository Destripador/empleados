<template>
	<NcAppContent :name="t('empleados', 'Office simulation')">
		<div class="office-sim">
			<header class="office-sim__header">
				<div>
					<h2>{{ t('empleados', 'Office simulation') }}</h2>
					<p>
						{{ t('empleados', 'An experimental visual simulation of the office based on areas, positions and recent activity.') }}
					</p>
					<small v-if="periodoLabel">{{ periodoLabel }}</small>
				</div>

				<div class="office-sim__controls">
					<NcButton
						ref="helpButton"
						type="tertiary"
						:aria-label="t('empleados', 'How Office Simulation works')"
						:title="t('empleados', 'How Office Simulation works')"
						@click="openHelp">
						<template #icon>
							<HelpCircleOutline :size="20" />
						</template>
						<span class="office-sim__help-label">{{ t('empleados', 'How it works') }}</span>
					</NcButton>

					<NcButton
						v-if="!paused"
						type="secondary"
						:disabled="!ready"
						@click="paused = true">
						{{ t('empleados', 'Pause') }}
					</NcButton>
					<NcButton
						v-else
						type="secondary"
						:disabled="!ready"
						@click="paused = false">
						{{ t('empleados', 'Resume') }}
					</NcButton>

					<NcButton
						type="tertiary"
						:disabled="!ready"
						@click="resetToken += 1">
						{{ t('empleados', 'Reset positions') }}
					</NcButton>

					<label class="office-sim__select">
						<span>{{ t('empleados', 'Speed') }}</span>
						<select v-model="speedMode">
							<option value="calm">{{ t('empleados', 'Calm') }}</option>
							<option value="normal">{{ t('empleados', 'Normal') }}</option>
							<option value="active">{{ t('empleados', 'Lively') }}</option>
						</select>
					</label>

					<label class="office-sim__toggle">
						<input v-model="showNames" type="checkbox">
						<span>{{ t('empleados', 'Show names') }}</span>
					</label>

					<label class="office-sim__toggle">
						<input v-model="showFurniture" type="checkbox">
						<span>{{ t('empleados', 'Show furniture') }}</span>
					</label>
				</div>
			</header>

			<div v-if="onboardingState === 'loading'" class="office-sim__onboarding-loading">
				<NcLoadingIcon :size="36" />
				<p>{{ t('empleados', 'Checking the introduction…') }}</p>
			</div>

			<div v-if="loading" class="office-sim__state">
				<NcLoadingIcon :size="36" />
				<p>{{ t('empleados', 'Loading office simulation…') }}</p>
			</div>

			<div v-else-if="error" class="office-sim__state office-sim__state--error">
				<p>{{ error }}</p>
				<NcButton type="primary" @click="load">
					{{ t('empleados', 'Retry') }}
				</NcButton>
			</div>

			<div v-else-if="!employees.length" class="office-sim__state">
				<p>{{ t('empleados', 'No active employees found for the simulation.') }}</p>
			</div>

			<div v-else class="office-sim__stage">
				<div class="office-sim__meta">
					<div class="office-sim__legend">
						<span>☕ {{ t('empleados', 'Coffee area') }}</span>
						<span>📅 {{ t('empleados', 'Meeting room') }}</span>
						<span>🛋️ {{ t('empleados', 'Lounge / rest') }}</span>
						<span>🖨️ {{ t('empleados', 'Printer / hall') }}</span>
					</div>

					<div v-if="hasTodayEvents" class="office-sim__events">
						<span class="office-sim__events-label">{{ t('empleados', 'Today\'s events') }}:</span>
						<button
							v-if="anniversaryCount > 0"
							type="button"
							class="office-sim__event-chip"
							@click="highlightEvent('work_anniversary')">
							🎉 {{ t('empleados', '{count} work anniversaries', { count: anniversaryCount }) }}
						</button>
						<button
							v-if="birthdayCount > 0"
							type="button"
							class="office-sim__event-chip"
							@click="highlightEvent('birthday')">
							🎂 {{ t('empleados', '{count} birthdays', { count: birthdayCount }) }}
						</button>
					</div>
				</div>
				<OfficeSimulationCanvas
					:employees="employees"
					:paused="simulationPaused"
					:speed-mode="speedMode"
					:show-names="showNames"
					:show-furniture="showFurniture"
					:selected-id="selectedId"
					:follow-selected="followSelected"
					:reset-token="resetToken"
					:highlight-uids="highlightUids"
					@select="onSelect"
					@follow="followSelected = $event" />
			</div>

			<OfficeSimulationIntro
				:show="showIntro && introVersion > 0"
				:version="introVersion"
				@complete="onIntroComplete"
				@error="onIntroError" />

			<OfficeSimulationHelp
				:show="showHelp"
				:version="introVersion"
				@close="closeHelp" />
		</div>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import HelpCircleOutline from 'vue-material-design-icons/HelpCircleOutline.vue'
import {
	getOfficeSimulationOnboardingStatus,
	getSimulacionEmpleados,
} from '../../../services/simulacionOficinaService.js'
import { buildTodayEventsSummary } from '../../../utils/dailyOfficeEvents.js'
import OfficeSimulationCanvas from './OfficeSimulationCanvas.vue'
import OfficeSimulationHelp from './OfficeSimulationHelp.vue'
import OfficeSimulationIntro from './OfficeSimulationIntro.vue'

export default {
	name: 'SimulacionOficina',

	components: {
		NcAppContent,
		NcButton,
		NcLoadingIcon,
		HelpCircleOutline,
		OfficeSimulationCanvas,
		OfficeSimulationHelp,
		OfficeSimulationIntro,
	},

	data() {
		return {
			loading: true,
			error: '',
			employees: [],
			periodo: null,
			eventosHoy: null,
			paused: false,
			speedMode: 'normal',
			showNames: false,
			showFurniture: true,
			selectedId: null,
			followSelected: false,
			resetToken: 0,
			highlightUids: [],
			highlightClearTimer: null,
			onboardingState: 'loading',
			showIntro: false,
			introVersion: 0,
			showHelp: false,
		}
	},

	computed: {
		simulationPaused() {
			return this.paused || this.showIntro || this.showHelp
		},
		ready() {
			return !this.loading && !this.error && this.employees.length > 0
		},
		periodoLabel() {
			if (!this.periodo) {
				return ''
			}
			return t('empleados', 'Period: {start} → {end}', {
				start: this.periodo.inicio,
				end: this.periodo.fin,
			})
		},
		todaySummary() {
			return buildTodayEventsSummary(this.eventosHoy, this.employees)
		},
		anniversaryCount() {
			return this.todaySummary.workAnniversary.length
		},
		birthdayCount() {
			return this.todaySummary.birthday.length
		},
		hasTodayEvents() {
			return this.anniversaryCount > 0 || this.birthdayCount > 0
		},
	},

	beforeDestroy() {
		if (this.highlightClearTimer) {
			clearTimeout(this.highlightClearTimer)
		}
	},

	mounted() {
		this.load()
		this.loadOnboardingStatus()
	},

	methods: {
		t,

		async loadOnboardingStatus() {
			this.onboardingState = 'loading'

			try {
				const status = await getOfficeSimulationOnboardingStatus()
				if (status.requiredVersion < 1) {
					throw new Error('Invalid office simulation onboarding version')
				}

				this.introVersion = status.requiredVersion
				this.onboardingState = status.completed ? 'completed' : 'required'
				this.showIntro = !status.completed
			} catch (err) {
				this.onboardingState = 'error'
				this.showIntro = false
				console.error('Could not load Office Simulation onboarding status', err)
			}
		},

		openHelp() {
			this.showHelp = true
		},

		closeHelp() {
			this.showHelp = false
			this.$nextTick(() => this.$refs.helpButton?.$el?.focus())
		},

		onIntroComplete() {
			this.onboardingState = 'completed'
			this.showIntro = false
		},

		onIntroError(err) {
			console.error('Could not save Office Simulation onboarding progress', err)
			showError(t('empleados', 'Could not save the introduction. Please try again.'))
		},

		highlightEvent(type) {
			const list = type === 'birthday'
				? this.todaySummary.birthday
				: this.todaySummary.workAnniversary
			const uids = list.map((row) => row.uid).filter(Boolean)
			this.highlightUids = [...uids]
			if (this.highlightClearTimer) {
				clearTimeout(this.highlightClearTimer)
			}
			this.highlightClearTimer = setTimeout(() => {
				this.highlightUids = []
			}, 3400)
		},

		onSelect(id) {
			this.selectedId = id
			if (id == null) {
				this.followSelected = false
			}
		},

		async load() {
			this.loading = true
			this.error = ''
			this.selectedId = null
			this.followSelected = false
			this.highlightUids = []

			try {
				const data = await getSimulacionEmpleados({ periodo: 'last_30_days' })
				this.employees = Array.isArray(data?.empleados) ? data.empleados : []
				this.periodo = data?.periodo || null
				this.eventosHoy = data?.eventosHoy || null
			} catch (err) {
				const message = err?.response?.data?.ocs?.data?.error
					|| err?.response?.data?.error
					|| err?.message
					|| t('empleados', 'Could not load the office simulation.')
				this.error = message
				this.employees = []
				this.eventosHoy = null
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.office-sim {
	position: relative;
	display: flex;
	flex-direction: column;
	gap: 14px;
	box-sizing: border-box;
	width: 100%;
	height: calc(100vh - 54px);
	min-height: 480px;
	padding: 16px 18px 18px;
}

.office-sim__onboarding-loading {
	position: absolute;
	inset: 0;
	z-index: 10;
	display: grid;
	place-content: center;
	place-items: center;
	gap: 12px;
	background: var(--color-main-background);
	color: var(--color-text-maxcontrast);
	text-align: center;
}

.office-sim__header {
	display: flex;
	flex-wrap: wrap;
	gap: 16px;
	align-items: flex-start;
	justify-content: space-between;
}

.office-sim__header h2 {
	margin: 0 0 4px;
	font-size: 1.35rem;
}

.office-sim__header p {
	margin: 0;
	max-width: 52ch;
	color: var(--color-text-maxcontrast);
	font-size: 0.92rem;
	line-height: 1.4;
}

.office-sim__header small {
	display: inline-block;
	margin-top: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 0.8rem;
}

.office-sim__controls {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	align-items: center;
}

.office-sim__help-label {
	white-space: nowrap;
}

.office-sim__select {
	display: inline-flex;
	gap: 8px;
	align-items: center;
	font-size: 0.85rem;
}

.office-sim__select select {
	min-width: 110px;
	padding: 4px 8px;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.office-sim__toggle {
	display: inline-flex;
	gap: 6px;
	align-items: center;
	font-size: 0.85rem;
	cursor: pointer;
}

.office-sim__state {
	display: grid;
	place-items: center;
	gap: 12px;
	flex: 1;
	min-height: 280px;
	border: 1px dashed var(--color-border);
	border-radius: 14px;
	color: var(--color-text-maxcontrast);
	text-align: center;
}

.office-sim__state--error {
	color: var(--color-error);
}

.office-sim__stage {
	flex: 1;
	min-height: 420px;
	height: 60vh;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.office-sim__meta {
	display: flex;
	flex-wrap: wrap;
	gap: 10px 18px;
	align-items: center;
	justify-content: space-between;
}

.office-sim__legend {
	display: flex;
	flex-wrap: wrap;
	gap: 10px 14px;
	color: var(--color-text-maxcontrast);
	font-size: 0.8rem;
}

.office-sim__events {
	display: inline-flex;
	flex-wrap: wrap;
	gap: 6px 8px;
	align-items: center;
}

.office-sim__events-label {
	color: var(--color-text-maxcontrast);
	font-size: 0.8rem;
}

.office-sim__event-chip {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 3px 9px;
	border: 1px solid var(--color-border);
	border-radius: 999px;
	background: var(--color-background-dark);
	color: var(--color-main-text);
	font-size: 0.78rem;
	cursor: pointer;
	transition: border-color 0.15s ease, background 0.15s ease;
}

.office-sim__event-chip:hover {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light, var(--color-background-hover));
}

.office-sim__stage :deep(.sim-canvas-wrap) {
	flex: 1;
}

@media (max-width: 800px) {
	.office-sim {
		height: auto;
		min-height: calc(100vh - 54px);
	}

	.office-sim__stage {
		min-height: 420px;
		height: 60vh;
	}

	.office-sim__header h2,
	.office-sim__header p,
	.office-sim__header small {
		text-align: center;
	}

	.office-sim__header small {
		display: block;   // ahora sí puede alinear su contenido
		text-align: center;
	}
}

@media (max-width: 560px) {
	.office-sim__help-label {
		display: none;
	}
}
</style>
