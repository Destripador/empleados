<template>
	<section class="cost-quotation">
		<NcEmptyContent
			v-if="scenarioSummaries.length === 0"
			:name="t('empleados', 'No quote scenarios yet')"
			:description="t('empleados', 'Create a planning scenario to estimate costs, profit and margin.')" />

		<template v-else>
			<header class="quotation-toolbar">
				<div>
					<p class="eyebrow">
						{{ t('empleados', 'Costs') }}
					</p>
					<h2>{{ t('empleados', 'Quote scenarios') }}</h2>
				</div>

				<div class="scenario-tabs" role="tablist" :aria-label="t('empleados', 'Quote scenarios')">
					<button
						v-for="item in scenarioSummaries"
						:id="`cost-scenario-tab-${item.key}`"
						:key="item.key"
						type="button"
						role="tab"
						class="scenario-tab"
						:class="{ 'scenario-tab--active': item.isActive }"
						:aria-selected="item.isActive ? 'true' : 'false'"
						:aria-controls="item.isActive ? 'cost-scenario-panel' : null"
						@click="$emit('select-scenario', item.scenario.id)">
						{{ item.name }}
					</button>
				</div>
			</header>

			<div
				v-if="activeScenario && activeSummary"
				id="cost-scenario-panel"
				class="scenario-panel"
				role="tabpanel"
				:aria-labelledby="`cost-scenario-tab-${activeSummary.key}`">
				<div class="scenario-actions">
					<label class="rename-field">
						<span>{{ t('empleados', 'Scenario name') }}</span>
						<input
							type="text"
							:value="activeSummary.name"
							:aria-label="t('empleados', 'Scenario name')"
							@change="renameScenario(activeScenario, $event.target.value)">
					</label>

					<div class="scenario-action-buttons">
						<NcButton
							:disabled="scenarioSummaries.length >= 3"
							:aria-label="t('empleados', 'Duplicate scenario')"
							@click="$emit('duplicate-scenario', activeScenario.id)">
							<template #icon>
								<ContentCopy :size="20" />
							</template>
							{{ t('empleados', 'Duplicate') }}
						</NcButton>
						<NcButton
							type="error"
							:disabled="scenarioSummaries.length <= 1"
							:aria-label="t('empleados', 'Delete scenario')"
							@click="$emit('delete-scenario', activeScenario.id)">
							<template #icon>
								<DeleteOutline :size="20" />
							</template>
							{{ t('empleados', 'Delete') }}
						</NcButton>
					</div>
				</div>

				<div class="scenario-heading">
					<div class="heading-item">
						<span>{{ t('empleados', 'Company or group') }}</span>
						<strong>{{ companyName(activeScenario) }}</strong>
					</div>
					<div class="heading-item">
						<span>{{ t('empleados', 'Period') }}</span>
						<strong>{{ scenarioPeriod(activeScenario) }}</strong>
					</div>
					<div class="heading-item">
						<span>{{ t('empleados', 'Project leader') }}</span>
						<strong>{{ leaderName(activeScenario) }}</strong>
					</div>
					<div class="heading-item">
						<span>{{ t('empleados', 'Required hours') }}</span>
						<strong>{{ formatHours(activeSummary.requiredHours) }}</strong>
					</div>
					<div class="heading-item">
						<span>{{ t('empleados', 'Assigned hours') }}</span>
						<strong>{{ formatHours(activeSummary.assignedHours) }}</strong>
					</div>
				</div>

				<div class="financial-inputs">
					<label>
						<span>{{ t('empleados', 'Proposed price') }}</span>
						<input
							type="number"
							min="0"
							step="0.01"
							:value="activeSummary.price"
							@input="updateFinancialField('price', $event.target.value)">
					</label>
					<label>
						<span class="label-with-help">
							{{ t('empleados', 'Contingency') }}
							<HelpHint
								:label="t('empleados', 'About contingency')"
								:text="t('empleados', 'Additional percentage added to estimated personnel cost to account for uncertainty.')" />
						</span>
						<span class="percentage-input">
							<input
								type="number"
								min="0"
								step="0.01"
								:value="activeSummary.contingencyPercentage"
								@input="updateFinancialField('contingency', $event.target.value)">
							<span aria-hidden="true">%</span>
						</span>
					</label>
				</div>

				<section class="team-section" :aria-labelledby="`team-title-${activeSummary.key}`">
					<div class="section-title">
						<div>
							<h3 :id="`team-title-${activeSummary.key}`">
								{{ t('empleados', 'Tentative team') }}
							</h3>
							<p>{{ t('empleados', 'Edit roles, assigned hours and activities for this temporary scenario.') }}</p>
						</div>
					</div>

					<div v-if="activeSummary.team.length > 0" class="table-scroll">
						<table class="team-table">
							<thead>
								<tr>
									<th scope="col">
										{{ t('empleados', 'Employee') }}
									</th>
									<th scope="col">
										{{ t('empleados', 'Role') }}
									</th>
									<th scope="col">
										{{ t('empleados', 'Activities') }}
									</th>
									<th scope="col">
										{{ t('empleados', 'Assigned hours') }}
									</th>
									<th scope="col">
										<span class="label-with-help">
											{{ t('empleados', 'Estimated availability') }}
											<HelpHint
												:label="t('empleados', 'About estimated availability')"
												:text="t('empleados', 'Calculated from working days, configured daily hours, approved absences and reported hours. It does not include future project commitments.')" />
										</span>
									</th>
									<th scope="col">
										{{ t('empleados', 'Cost per hour') }}
									</th>
									<th scope="col">
										<span class="label-with-help">
											{{ t('empleados', 'Estimated cost') }}
											<HelpHint
												:label="t('empleados', 'About estimated cost')"
												:text="t('empleados', 'Calculated by multiplying assigned hours by the hourly cost configured for the employee.')" />
										</span>
									</th>
									<th scope="col">
										<span class="label-with-help">
											{{ t('empleados', 'Resulting occupancy') }}
											<HelpHint
												:label="t('empleados', 'About estimated occupancy')"
												:text="t('empleados', 'Percentage of effective capacity used by reported and assigned hours in this scenario.')" />
										</span>
									</th>
									<th scope="col">
										{{ t('empleados', 'Alerts') }}
									</th>
									<th scope="col">
										<span class="visually-hidden">{{ t('empleados', 'Remove') }}</span>
									</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="(member, memberIndex) in activeSummary.team" :key="memberKey(member, memberIndex)">
									<td>
										<div class="employee-cell">
											<strong>{{ memberName(member) }}</strong>
											<span class="employee-cell__uid">{{ memberUid(member) }}</span>
										</div>
									</td>
									<td>
										<label class="visually-hidden" :for="`member-role-${memberKey(member, memberIndex)}`">
											{{ t('empleados', 'Role for {employee}', { employee: memberName(member) }) }}
										</label>
										<input
											:id="`member-role-${memberKey(member, memberIndex)}`"
											class="table-input table-input--role"
											type="text"
											:value="memberRole(member)"
											@input="updateMember(memberIndex, 'rol', $event.target.value)">
									</td>
									<td>
										<details v-if="scenarioActivities(activeScenario).length > 0" class="activity-picker">
											<summary>
												{{ t('empleados', '{count} selected', { count: memberActivityCount(member) }) }}
											</summary>
											<div class="activity-options">
												<label
													v-for="(activity, activityIndex) in scenarioActivities(activeScenario)"
													:key="activityId(activity, activityIndex)">
													<input
														type="checkbox"
														:checked="memberHasActivity(member, activity)"
														@change="toggleMemberActivity(memberIndex, activity, $event.target.checked)">
													<span>{{ activityName(activity, activityIndex) }}</span>
												</label>
											</div>
										</details>
										<span v-else>—</span>
									</td>
									<td>
										<label class="visually-hidden" :for="`member-hours-${memberKey(member, memberIndex)}`">
											{{ t('empleados', 'Assigned hours for {employee}', { employee: memberName(member) }) }}
										</label>
										<input
											:id="`member-hours-${memberKey(member, memberIndex)}`"
											class="table-input table-input--number"
											type="number"
											min="0"
											step="0.25"
											:value="memberAssignedHours(member)"
											@input="updateMember(memberIndex, 'horas_estimadas', normalizedNonNegative($event.target.value))">
									</td>
									<td>{{ formatNullableHours(memberAvailability(member)) }}</td>
									<td>{{ formatNullableMoney(memberHourlyCost(member)) }}</td>
									<td>{{ formatNullableMoney(memberEstimatedCost(member)) }}</td>
									<td>{{ formatNullablePercentage(memberResultingOccupancy(member)) }}</td>
									<td>
										<ul v-if="memberAlertKeys(member).length > 0" class="alert-list">
											<li v-for="(alert, alertIndex) in memberAlertKeys(member)" :key="`${alert}-${alertIndex}`">
												<AlertOutline :size="16" aria-hidden="true" />
												<span>{{ alertLabel(alert) }}</span>
											</li>
										</ul>
										<span v-else class="status-inline status-inline--ok">
											<CheckCircleOutline :size="17" aria-hidden="true" />
											{{ t('empleados', 'No alerts') }}
										</span>
									</td>
									<td>
										<NcButton
											type="tertiary-no-background"
											:aria-label="t('empleados', 'Remove {employee} from scenario', { employee: memberName(member) })"
											@click="removeMember(memberIndex)">
											<template #icon>
												<DeleteOutline :size="20" />
											</template>
										</NcButton>
									</td>
								</tr>
							</tbody>
						</table>
					</div>

					<NcEmptyContent
						v-else
						:name="t('empleados', 'No employees in this scenario')"
						:description="t('empleados', 'Add candidates from planning to estimate the team cost.')" />
				</section>

				<section
					v-if="activeSummary.planningAlerts.length"
					class="scenario-alerts"
					:aria-labelledby="`scenario-alerts-title-${activeSummary.key}`">
					<h3 :id="`scenario-alerts-title-${activeSummary.key}`">
						{{ t('empleados', 'Planning alerts') }}
					</h3>
					<ul>
						<li v-for="alert in activeSummary.planningAlerts" :key="alert">
							<AlertOutline :size="18" aria-hidden="true" />
							<span>{{ alertLabel(alert) }}</span>
						</li>
					</ul>
				</section>

				<section class="financial-summary" :aria-labelledby="`financial-title-${activeSummary.key}`">
					<div class="section-title">
						<div>
							<h3 :id="`financial-title-${activeSummary.key}`">
								{{ t('empleados', 'Estimated totals') }}
							</h3>
							<p>{{ t('empleados', 'These values are planning estimates, not final accounting results.') }}</p>
						</div>
					</div>

					<div class="financial-grid">
						<article class="metric-card">
							<span>{{ t('empleados', 'Personnel cost') }}</span>
							<strong>{{ formatMoney(activeSummary.personnelCost) }}</strong>
						</article>
						<article class="metric-card">
							<span class="label-with-help">
								{{ t('empleados', 'Contingency amount') }}
								<HelpHint
									:label="t('empleados', 'About contingency')"
									:text="t('empleados', 'Additional percentage added to estimated personnel cost to account for uncertainty.')" />
							</span>
							<strong>{{ formatMoney(activeSummary.contingencyAmount) }}</strong>
						</article>
						<article class="metric-card">
							<span class="label-with-help">
								{{ t('empleados', 'Total estimated cost') }}
								<HelpHint
									:label="t('empleados', 'About estimated cost')"
									:text="t('empleados', 'Calculated from estimated personnel cost plus contingency.')" />
							</span>
							<strong>{{ formatMoney(activeSummary.totalCost) }}</strong>
						</article>
						<article class="metric-card">
							<span class="label-with-help">
								{{ t('empleados', 'Estimated profit') }}
								<HelpHint
									:label="t('empleados', 'About estimated profit')"
									:text="t('empleados', 'Proposed price minus estimated personnel cost and contingency.')" />
							</span>
							<strong>{{ formatNullableMoney(activeSummary.profit) }}</strong>
						</article>
						<article class="metric-card metric-card--margin">
							<span class="label-with-help">
								{{ t('empleados', 'Estimated margin') }}
								<HelpHint
									:label="t('empleados', 'About estimated margin')"
									:text="t('empleados', 'Percentage of the proposed price remaining after estimated personnel cost and contingency.')" />
							</span>
							<strong>{{ formatNullablePercentage(activeSummary.margin) }}</strong>
							<span class="margin-state" :class="`margin-state--${activeSummary.marginState}`">
								<CheckCircleOutline
									v-if="activeSummary.marginState === 'positive'"
									:size="19"
									aria-hidden="true" />
								<AlertOutline
									v-else-if="activeSummary.marginState === 'low'"
									:size="19"
									aria-hidden="true" />
								<CloseCircleOutline
									v-else-if="activeSummary.marginState === 'negative'"
									:size="19"
									aria-hidden="true" />
								<InformationOutline v-else :size="19" aria-hidden="true" />
								{{ marginStateLabel(activeSummary.marginState) }}
							</span>
						</article>
					</div>
					<p class="margin-guidance">
						{{ t('empleados', 'Margin states are guidance for this MVP: negative below 0%, low from 0% to 15%, and positive above 15%. They are not a formal business minimum.') }}
					</p>
				</section>

				<section class="comparison-section" aria-labelledby="scenario-comparison-title">
					<div class="section-title">
						<div>
							<h3 id="scenario-comparison-title" class="label-with-help">
								{{ t('empleados', 'Scenario comparison') }}
								<HelpHint
									:label="t('empleados', 'About scenario comparison')"
									:text="t('empleados', 'Compare temporary planning estimates. These scenarios are not saved when the page is reloaded.')" />
							</h3>
							<p>{{ t('empleados', 'Compare up to three temporary alternatives without selecting one automatically.') }}</p>
						</div>
					</div>

					<div class="table-scroll">
						<table class="comparison-table">
							<thead>
								<tr>
									<th scope="col">
										{{ t('empleados', 'Metric') }}
									</th>
									<th v-for="item in scenarioSummaries" :key="item.key" scope="col">
										<button type="button" @click="$emit('select-scenario', item.scenario.id)">
											{{ item.name }}
										</button>
									</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<th scope="row">
										{{ t('empleados', 'Total estimated cost') }}
									</th>
									<td v-for="item in scenarioSummaries" :key="item.key">
										{{ formatMoney(item.totalCost) }}
									</td>
								</tr>
								<tr>
									<th scope="row">
										{{ t('empleados', 'Proposed price') }}
									</th>
									<td v-for="item in scenarioSummaries" :key="item.key">
										{{ formatMoney(item.price) }}
									</td>
								</tr>
								<tr>
									<th scope="row">
										{{ t('empleados', 'Estimated profit') }}
									</th>
									<td v-for="item in scenarioSummaries" :key="item.key">
										{{ formatNullableMoney(item.profit) }}
									</td>
								</tr>
								<tr>
									<th scope="row">
										{{ t('empleados', 'Estimated margin') }}
									</th>
									<td v-for="item in scenarioSummaries" :key="item.key">
										<span class="status-inline" :class="`status-inline--${item.marginState}`">
											<CheckCircleOutline
												v-if="item.marginState === 'positive'"
												:size="17"
												aria-hidden="true" />
											<AlertOutline
												v-else-if="item.marginState === 'low'"
												:size="17"
												aria-hidden="true" />
											<CloseCircleOutline
												v-else-if="item.marginState === 'negative'"
												:size="17"
												aria-hidden="true" />
											<InformationOutline v-else :size="17" aria-hidden="true" />
											{{ formatNullablePercentage(item.margin) }}
											<span>· {{ marginStateLabel(item.marginState) }}</span>
										</span>
									</td>
								</tr>
								<tr>
									<th scope="row">
										{{ t('empleados', 'Assigned hours') }}
									</th>
									<td v-for="item in scenarioSummaries" :key="item.key">
										{{ formatHours(item.assignedHours) }}
									</td>
								</tr>
								<tr>
									<th scope="row">
										{{ t('empleados', 'Remaining estimated availability') }}
									</th>
									<td v-for="item in scenarioSummaries" :key="item.key">
										{{ formatNullableHours(item.remainingAvailability) }}
									</td>
								</tr>
								<tr>
									<th scope="row">
										{{ t('empleados', 'Alerts') }}
									</th>
									<td v-for="item in scenarioSummaries" :key="item.key">
										{{ formatInteger(item.alertCount) }}
									</td>
								</tr>
								<tr>
									<th scope="row">
										{{ t('empleados', 'Average team experience') }}
									</th>
									<td v-for="item in scenarioSummaries" :key="item.key">
										{{ formatNullableHours(item.averageExperience) }}
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</section>
			</div>
		</template>
	</section>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcEmptyContent } from '@nextcloud/vue'
import AlertOutline from 'vue-material-design-icons/AlertOutline.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import CloseCircleOutline from 'vue-material-design-icons/CloseCircleOutline.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import DeleteOutline from 'vue-material-design-icons/DeleteOutline.vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'

import HelpHint from '../Helpers/HelpHint.vue'

const numberFormatter = new Intl.NumberFormat('es-MX', {
	maximumFractionDigits: 2,
})

const integerFormatter = new Intl.NumberFormat('es-MX', {
	maximumFractionDigits: 0,
})

const moneyFormatter = new Intl.NumberFormat('es-MX', {
	style: 'currency',
	currency: 'MXN',
	maximumFractionDigits: 2,
})

const dateFormatter = new Intl.DateTimeFormat('es-MX', {
	day: '2-digit',
	month: 'short',
	year: 'numeric',
})

export default {
	name: 'CostosCotizacion',
	components: {
		AlertOutline,
		CheckCircleOutline,
		CloseCircleOutline,
		ContentCopy,
		DeleteOutline,
		HelpHint,
		InformationOutline,
		NcButton,
		NcEmptyContent,
	},
	props: {
		scenarios: {
			type: Array,
			default: () => [],
		},
		activeScenarioId: {
			type: [Number, String],
			default: null,
		},
	},
	computed: {
		activeScenario() {
			return this.scenarios.find(scenario => this.sameId(scenario.id, this.activeScenarioId))
				|| this.scenarios[0]
				|| null
		},
		activeSummary() {
			if (!this.activeScenario) {
				return null
			}

			return this.buildScenarioSummary(this.activeScenario, this.scenarios.indexOf(this.activeScenario))
		},
		scenarioSummaries() {
			return this.scenarios.slice(0, 3).map((scenario, index) => ({
				...this.buildScenarioSummary(scenario, index),
				isActive: this.activeScenario
					? this.sameId(scenario.id, this.activeScenario.id)
					: index === 0,
			}))
		},
	},
	methods: {
		t,
		sameId(first, second) {
			return String(first) === String(second)
		},
		toNumber(value, fallback = 0) {
			const number = Number(value)
			return Number.isFinite(number) ? number : fallback
		},
		toNullableNumber(value) {
			if (value === null || value === undefined || value === '') {
				return null
			}

			const number = Number(value)
			return Number.isFinite(number) ? number : null
		},
		normalizedNonNegative(value) {
			return Math.min(
				Number.MAX_SAFE_INTEGER,
				Math.max(0, this.toNumber(value)),
			)
		},
		scenarioName(scenario, index) {
			return scenario.nombre
				|| scenario.name
				|| t('empleados', 'Scenario {letter}', { letter: String.fromCharCode(65 + index) })
		},
		scenarioTeam(scenario) {
			return Array.isArray(scenario.team)
				? scenario.team
				: Array.isArray(scenario.equipo) ? scenario.equipo : []
		},
		scenarioActivities(scenario) {
			if (Array.isArray(scenario.activities)) {
				return scenario.activities
			}

			if (Array.isArray(scenario.actividades)) {
				return scenario.actividades
			}

			return Array.isArray(scenario.requerimiento?.actividades)
				? scenario.requerimiento.actividades
				: []
		},
		scenarioPrice(scenario) {
			return this.normalizedNonNegative(scenario.price ?? scenario.precio_propuesto ?? scenario.precio)
		},
		scenarioContingency(scenario) {
			return this.normalizedNonNegative(
				scenario.contingency
					?? scenario.contingencia_porcentaje
					?? scenario.contingencia
					?? scenario.contingencyPercentage,
			)
		},
		scenarioRequiredHours(scenario) {
			const explicit = this.toNullableNumber(
				scenario.horas_requeridas ?? scenario.requerimiento?.horas_estimadas,
			)

			if (explicit !== null) {
				return Math.max(0, explicit)
			}

			return this.scenarioActivities(scenario).reduce((total, activity) => (
				total + this.normalizedNonNegative(activity.horas_estimadas ?? activity.horas)
			), 0)
		},
		buildScenarioSummary(scenario, index) {
			const team = this.scenarioTeam(scenario)
			const personnelCost = team.reduce(
				(total, member) => total + (this.memberEstimatedCost(member) ?? 0),
				0,
			)
			const assignedHours = team.reduce((total, member) => total + this.memberAssignedHours(member), 0)
			const contingencyPercentage = this.scenarioContingency(scenario)
			const contingencyAmount = personnelCost * contingencyPercentage / 100
			const totalCost = personnelCost + contingencyAmount
			const price = this.scenarioPrice(scenario)
			const profit = price > 0 ? price - totalCost : null
			const margin = price > 0 ? profit / price * 100 : null
			const planningAlerts = this.scenarioPlanningAlertKeys(
				scenario,
				team,
				this.scenarioRequiredHours(scenario),
				assignedHours,
			)

			return {
				scenario,
				key: scenario.id ?? index,
				name: this.scenarioName(scenario, index),
				team,
				requiredHours: this.scenarioRequiredHours(scenario),
				assignedHours,
				price,
				contingencyPercentage,
				personnelCost,
				contingencyAmount,
				totalCost,
				profit,
				margin,
				planningAlerts,
				marginState: this.marginState(margin),
				remainingAvailability: this.scenarioRemainingAvailability(scenario, team),
				alertCount: this.scenarioAlertCount(team, planningAlerts),
				averageExperience: this.scenarioAverageExperience(scenario, team),
			}
		},
		marginState(margin) {
			if (margin === null) {
				return 'none'
			}
			if (margin < 0) {
				return 'negative'
			}
			if (margin <= 15) {
				return 'low'
			}
			return 'positive'
		},
		marginStateLabel(state) {
			switch (state) {
			case 'positive':
				return t('empleados', 'Positive margin')
			case 'low':
				return t('empleados', 'Low margin')
			case 'negative':
				return t('empleados', 'Negative margin')
			default:
				return t('empleados', 'No proposed price')
			}
		},
		memberSource(member) {
			return member.candidato || member.empleado || member
		},
		memberKey(member, index) {
			const source = this.memberSource(member)
			return source.id_empleado ?? source.id ?? source.uid ?? index
		},
		memberName(member) {
			const source = this.memberSource(member)
			return source.displayname
				|| source.nombre
				|| source.name
				|| source.uid
				|| t('empleados', 'Employee')
		},
		memberUid(member) {
			const source = this.memberSource(member)
			return source.uid || source.id_user || ''
		},
		memberRole(member) {
			return member.rol ?? member.role ?? ''
		},
		memberAssignedHours(member) {
			return this.normalizedNonNegative(
				member.horas_estimadas
					?? member.horas_asignadas
					?? member.assignedHours,
			)
		},
		memberHourlyCost(member) {
			const source = this.memberSource(member)
			const value = this.toNullableNumber(
				member.costo_hora ?? source.costo_hora ?? source.sueldo_hora,
			)
			return value === null ? null : this.normalizedNonNegative(value)
		},
		memberEstimatedCost(member) {
			const cost = this.memberHourlyCost(member)
			return cost === null ? null : this.memberAssignedHours(member) * cost
		},
		memberAvailability(member) {
			const source = this.memberSource(member)
			return this.toNullableNumber(
				member.disponibilidad_estimada ?? source.disponibilidad_estimada,
			)
		},
		memberEffectiveCapacity(member) {
			const source = this.memberSource(member)
			return this.toNullableNumber(
				member.capacidad_efectiva ?? source.capacidad_efectiva,
			)
		},
		memberReportedHours(member) {
			const source = this.memberSource(member)
			return this.normalizedNonNegative(
				member.horas_reportadas_periodo ?? source.horas_reportadas_periodo,
			)
		},
		memberResultingOccupancy(member) {
			const capacity = this.memberEffectiveCapacity(member)
			if (capacity === null || capacity <= 0) {
				return null
			}

			return (this.memberReportedHours(member) + this.memberAssignedHours(member)) / capacity * 100
		},
		memberActivities(member) {
			return Array.isArray(member.actividades)
				? member.actividades
				: Array.isArray(member.activityIds) ? member.activityIds : []
		},
		activityId(activity, fallback = 0) {
			if (activity && typeof activity === 'object') {
				return activity.id_actividad ?? activity.id ?? fallback
			}
			return activity ?? fallback
		},
		activityName(activity, index) {
			if (activity && typeof activity === 'object') {
				return activity.nombre
					|| activity.name
					|| t('empleados', 'Activity {number}', { number: index + 1 })
			}
			return t('empleados', 'Activity {number}', { number: index + 1 })
		},
		memberHasActivity(member, activity) {
			const wantedId = this.activityId(activity)
			return this.memberActivities(member).some(item => this.sameId(this.activityId(item), wantedId))
		},
		memberActivityCount(member) {
			return this.memberActivities(member).length
		},
		memberRawAlerts(member) {
			const source = this.memberSource(member)
			const alerts = [
				...(Array.isArray(member.alertas) ? member.alertas : []),
				...(Array.isArray(member.riesgos) ? member.riesgos : []),
				...(Array.isArray(source.alertas) ? source.alertas : []),
				...(Array.isArray(source.riesgos) ? source.riesgos : []),
			]

			return alerts.map(alert => {
				if (alert && typeof alert === 'object') {
					return alert.key || alert.code || alert.tipo || 'review_assignment'
				}
				return String(alert)
			})
		},
		memberAlertKeys(member) {
			const alerts = this.memberRawAlerts(member)
			const availability = this.memberAvailability(member)
			const assignedHours = this.memberAssignedHours(member)
			const occupancy = this.memberResultingOccupancy(member)
			const source = this.memberSource(member)
			const capacityCalculable = member.capacidad_calculable ?? source.capacidad_calculable

			if (availability !== null && assignedHours > availability) {
				alerts.push('availability_exceeded')
			}
			if (occupancy !== null && occupancy > 100) {
				alerts.push('occupation_over_100')
			} else if (occupancy !== null && occupancy > 90) {
				alerts.push('occupation_over_90')
			}
			if (this.memberHourlyCost(member) === null) {
				alerts.push('missing_hourly_cost')
			}
			if (capacityCalculable === false || this.memberEffectiveCapacity(member) === null) {
				alerts.push('capacity_unavailable')
			}

			return [...new Set(alerts)]
		},
		alertLabel(alert) {
			switch (alert) {
			case 'availability_exceeded':
			case 'assigned_hours_exceed_availability':
			case 'horas_superan_disponibilidad':
				return t('empleados', 'Assigned hours exceed estimated availability.')
			case 'occupation_over_100':
			case 'occupation_above_100':
			case 'ocupacion_supera_100':
				return t('empleados', 'Resulting occupancy exceeds 100%.')
			case 'occupation_over_90':
			case 'occupation_above_90':
			case 'ocupacion_supera_90':
				return t('empleados', 'Resulting occupancy exceeds 90%.')
			case 'no_activity_experience':
			case 'sin_experiencia_actividades':
				return t('empleados', 'No related activity experience is recorded.')
			case 'sin_experiencia_empresa':
				return t('empleados', 'No previous work with this company is recorded.')
			case 'missing_hourly_cost':
			case 'sin_costo_hora':
				return t('empleados', 'Hourly cost is not configured.')
			case 'capacity_unavailable':
			case 'capacidad_no_calculable':
				return t('empleados', 'Capacity cannot be calculated.')
			case 'datos_incompletos':
				return t('empleados', 'Some information needed for the analysis is incomplete.')
			case 'required_hours_unassigned':
				return t('empleados', 'Required hours exceed the hours currently assigned to the tentative team.')
			case 'single_person_dependency':
				return t('empleados', 'The current plan depends entirely on one person.')
			case 'team_availability_shortfall':
				return t('empleados', 'Required hours exceed the estimated availability of the tentative team.')
			case 'insufficient_available_staff':
				return t('empleados', 'There may not be enough visible personnel with estimated availability for the requirement.')
			default:
				return t('empleados', 'Review this assignment.')
			}
		},
		scenarioPlanningAlertKeys(scenario, team, requiredHours, assignedHours) {
			const alerts = (Array.isArray(scenario.alerts) ? scenario.alerts : [])
				.map(alert => typeof alert === 'string'
					? alert
					: alert?.key || alert?.code || '')
				.filter(Boolean)

			if (requiredHours > assignedHours) {
				alerts.push('required_hours_unassigned')
			}

			if (team.length === 1 && assignedHours > 0) {
				alerts.push('single_person_dependency')
			}

			const availabilityValues = team
				.map(member => this.memberAvailability(member))
				.filter(value => value !== null)

			if (
				team.length
				&& availabilityValues.length === team.length
				&& requiredHours > availabilityValues.reduce((total, value) => total + value, 0)
			) {
				alerts.push('team_availability_shortfall')
			}

			const analysis = scenario.analysis
			if (
				analysis
				&& requiredHours > 0
				&& (
					this.toNumber(analysis.availableCandidatesCount) <= 0
					|| this.toNumber(analysis.availableHours) < requiredHours
				)
			) {
				alerts.push('insufficient_available_staff')
			}

			return [...new Set(alerts)]
		},
		scenarioRemainingAvailability(scenario, team) {
			const explicit = this.toNullableNumber(
				scenario.disponibilidad_restante ?? scenario.remainingAvailability,
			)
			if (explicit !== null) {
				return Math.max(0, explicit)
			}

			let hasAvailability = false
			const total = team.reduce((sum, member) => {
				const availability = this.memberAvailability(member)
				if (availability === null) {
					return sum
				}
				hasAvailability = true
				return sum + Math.max(0, availability - this.memberAssignedHours(member))
			}, 0)

			return hasAvailability ? total : null
		},
		scenarioAlertCount(team, planningAlerts) {
			return planningAlerts.length + team.reduce(
				(total, member) => total + this.memberAlertKeys(member).length,
				0,
			)
		},
		scenarioAverageExperience(scenario, team) {
			const explicit = this.toNullableNumber(
				scenario.experiencia_promedio ?? scenario.averageExperience,
			)
			if (explicit !== null) {
				return Math.max(0, explicit)
			}

			const values = team.map(member => {
				const source = this.memberSource(member)
				return this.toNullableNumber(
					member.experiencia?.horas_actividades
						?? source.experiencia?.horas_actividades
						?? member.horas_experiencia,
				)
			}).filter(value => value !== null)

			if (values.length === 0) {
				return null
			}

			return values.reduce((total, value) => total + value, 0) / values.length
		},
		companyName(scenario) {
			const company = scenario.empresa || scenario.company
			if (company && typeof company === 'object') {
				return company.nombre || company.name || t('empleados', 'Not selected')
			}
			return company || scenario.nombre_empresa || t('empleados', 'Not selected')
		},
		leaderName(scenario) {
			const leader = scenario.lider || scenario.leader
			if (leader && typeof leader === 'object') {
				return leader.displayname
					|| leader.nombre
					|| leader.name
					|| leader.uid
					|| t('empleados', 'Not selected')
			}
			return scenario.nombre_lider || t('empleados', 'Not selected')
		},
		scenarioPeriod(scenario) {
			const start = scenario.startDate
				?? scenario.fecha_inicio
				?? scenario.periodo?.fecha_inicio
			const end = scenario.endDate
				?? scenario.fecha_fin
				?? scenario.periodo?.fecha_fin
			if (!start || !end) {
				return t('empleados', 'Not selected')
			}
			return `${this.formatDate(start)} – ${this.formatDate(end)}`
		},
		formatDate(value) {
			const date = new Date(`${String(value).slice(0, 10)}T00:00:00`)
			return Number.isNaN(date.getTime()) ? String(value) : dateFormatter.format(date)
		},
		formatNumber(value) {
			return numberFormatter.format(this.toNumber(value))
		},
		formatInteger(value) {
			return integerFormatter.format(this.toNumber(value))
		},
		formatHours(value) {
			return t('empleados', '{hours} h', { hours: this.formatNumber(value) })
		},
		formatNullableHours(value) {
			return value === null || value === undefined ? '—' : this.formatHours(value)
		},
		formatMoney(value) {
			return moneyFormatter.format(this.toNumber(value))
		},
		formatNullableMoney(value) {
			return value === null || value === undefined ? '—' : this.formatMoney(value)
		},
		formatNullablePercentage(value) {
			return value === null || value === undefined
				? '—'
				: t('empleados', '{percentage}%', { percentage: this.formatNumber(value) })
		},
		emitScenarioChanges(changes) {
			if (!this.activeScenario) {
				return
			}
			this.$emit('update-scenario', {
				id: this.activeScenario.id,
				changes,
			})
		},
		updateFinancialField(field, value) {
			this.emitScenarioChanges({
				[field]: this.normalizedNonNegative(value),
			})
		},
		updateMember(index, field, value) {
			const team = this.scenarioTeam(this.activeScenario).map((member, memberIndex) => (
				memberIndex === index ? { ...member, [field]: value } : member
			))
			this.emitScenarioChanges({ team })
		},
		removeMember(index) {
			const team = this.scenarioTeam(this.activeScenario).filter((member, memberIndex) => memberIndex !== index)
			this.emitScenarioChanges({ team })
		},
		toggleMemberActivity(memberIndex, activity, checked) {
			const member = this.scenarioTeam(this.activeScenario)[memberIndex]
			const selected = this.memberActivities(member)
			const activityId = this.activityId(activity)
			const activities = checked
				? [...selected.filter(item => !this.sameId(this.activityId(item), activityId)), activity]
				: selected.filter(item => !this.sameId(this.activityId(item), activityId))
			this.updateMember(memberIndex, 'actividades', activities)
		},
		renameScenario(scenario, name) {
			const normalizedName = String(name).trim()
			if (!normalizedName) {
				return
			}
			this.$emit('rename-scenario', {
				id: scenario.id,
				name: normalizedName,
			})
		},
	},
}
</script>

<style scoped lang="scss">
.cost-quotation {
	padding: 32px;
	color: var(--color-main-text);
}

.quotation-toolbar,
.scenario-actions,
.section-title {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
}

.quotation-toolbar h2,
.quotation-toolbar p,
.section-title h3,
.section-title p {
	margin: 0;
}

.eyebrow {
	color: var(--color-primary-element);
	font-weight: 600;
}

.scenario-tabs {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.scenario-tab {
	min-height: 38px;
	padding: 6px 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
	cursor: pointer;
}

.scenario-tab:hover,
.scenario-tab:focus-visible {
	background: var(--color-background-hover);
}

.scenario-tab--active {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
}

.scenario-panel {
	margin-top: 24px;
}

.scenario-actions {
	padding-bottom: 20px;
	border-bottom: 1px solid var(--color-border);
}

.rename-field,
.financial-inputs label {
	display: flex;
	flex-direction: column;
	gap: 6px;
	font-weight: 600;
}

.rename-field {
	width: min(360px, 100%);
}

.rename-field input,
.financial-inputs input,
.table-input {
	min-height: 38px;
	padding: 6px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.scenario-action-buttons {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.scenario-heading {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
	gap: 12px;
	margin-top: 20px;
}

.heading-item,
.metric-card {
	display: flex;
	min-height: 92px;
	flex-direction: column;
	justify-content: space-between;
	gap: 12px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.heading-item span,
.metric-card > span:first-child {
	color: var(--color-text-maxcontrast);
}

.financial-inputs {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 280px));
	gap: 16px;
	margin-top: 20px;
}

.percentage-input {
	display: flex;
	align-items: center;
	gap: 8px;
}

.percentage-input input {
	width: 100%;
}

.team-section,
.scenario-alerts,
.financial-summary,
.comparison-section {
	margin-top: 32px;
}

.section-title {
	margin-bottom: 16px;
}

.section-title p {
	margin-top: 4px;
	color: var(--color-text-maxcontrast);
}

.label-with-help {
	display: inline-flex;
	align-items: center;
	gap: 6px;
}

.table-scroll {
	overflow-x: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.team-table,
.comparison-table {
	width: 100%;
	border-collapse: collapse;
	background: var(--color-main-background);
}

.team-table {
	min-width: 1180px;
}

.comparison-table {
	min-width: 680px;
}

.team-table th,
.team-table td,
.comparison-table th,
.comparison-table td {
	padding: 12px;
	border-bottom: 1px solid var(--color-border);
	text-align: left;
	vertical-align: top;
}

.team-table th,
.comparison-table th {
	font-weight: 600;
}

.comparison-table tbody th {
	width: 260px;
	color: var(--color-text-maxcontrast);
}

.team-table tbody tr:last-child td,
.comparison-table tbody tr:last-child th,
.comparison-table tbody tr:last-child td {
	border-bottom: 0;
}

.employee-cell {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.employee-cell__uid {
	color: var(--color-text-maxcontrast);
}

.table-input--role {
	width: 150px;
}

.table-input--number {
	width: 96px;
}

.activity-picker {
	position: relative;
	min-width: 150px;
}

.activity-picker summary {
	cursor: pointer;
}

.activity-options {
	display: flex;
	min-width: 220px;
	flex-direction: column;
	gap: 8px;
	margin-top: 8px;
	padding: 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.activity-options label {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	font-weight: normal;
}

.alert-list,
.scenario-alerts ul {
	display: flex;
	flex-direction: column;
	gap: 6px;
	margin: 0;
	padding: 0;
	color: var(--color-warning);
	list-style: none;
}

.alert-list {
	min-width: 210px;
}

.scenario-alerts {
	padding: 16px;
	border: 1px solid var(--color-border);
	border-inline-start: 4px solid var(--color-warning);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);

	h3 {
		margin: 0 0 10px;
	}
}

.alert-list li,
.scenario-alerts li,
.status-inline,
.margin-state {
	display: inline-flex;
	align-items: center;
	gap: 5px;
}

.financial-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
	gap: 12px;
}

.margin-guidance {
	margin: 12px 0 0;
	color: var(--color-text-maxcontrast);
}

.metric-card strong {
	font-size: 1.35rem;
}

.metric-card--margin {
	gap: 8px;
}

.margin-state {
	width: fit-content;
	font-weight: 600;
}

.margin-state--positive,
.status-inline--positive,
.status-inline--ok {
	color: var(--color-success);
}

.margin-state--low,
.status-inline--low {
	color: var(--color-warning);
}

.margin-state--negative,
.status-inline--negative {
	color: var(--color-error);
}

.margin-state--none,
.status-inline--none {
	color: var(--color-text-maxcontrast);
}

.comparison-table thead button {
	padding: 0;
	border: 0;
	background: transparent;
	color: var(--color-primary-element);
	font: inherit;
	font-weight: 600;
	cursor: pointer;
}

.visually-hidden {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	white-space: nowrap;
	border: 0;
}

@media (max-width: 800px) {
	.cost-quotation {
		padding: 20px;
	}

	.quotation-toolbar,
	.scenario-actions {
		align-items: stretch;
		flex-direction: column;
	}

	.financial-inputs {
		grid-template-columns: 1fr;
	}
}
</style>
