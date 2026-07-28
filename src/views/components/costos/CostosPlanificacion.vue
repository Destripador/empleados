<template>
	<section class="planning">
		<header class="planning__header">
			<div>
				<p class="planning__eyebrow">
					{{ scenario.name }}
				</p>
				<h2>{{ t('empleados', 'Project planning') }}</h2>
				<p class="planning__intro">
					{{ t('empleados', 'Explore visible employees and build a tentative team. The final staffing decision remains with you.') }}
				</p>
			</div>

			<div class="planning__totals">
				<span>{{ t('empleados', 'Required hours') }}</span>
				<strong>{{ hours(totalRequiredHours) }}</strong>
			</div>
		</header>

		<ol class="planning__steps" :aria-label="t('empleados', 'Project planning')">
			<li
				v-for="(step, index) in planningSteps"
				:key="step.id"
				:class="{
					'planning__step--current': currentPlanningStep === index + 1,
					'planning__step--complete': currentPlanningStep > index + 1,
				}"
				:aria-current="currentPlanningStep === index + 1 ? 'step' : null">
				<span class="planning__step-index" aria-hidden="true">{{ index + 1 }}</span>
				<strong>{{ step.label }}</strong>
			</li>
		</ol>

		<section class="planning__panel planning__panel--configuration" :aria-labelledby="`${componentId}-project-title`">
			<div class="planning__panel-heading">
				<span class="planning__step-number" aria-hidden="true">1</span>
				<div>
					<h3 :id="`${componentId}-project-title`">
						{{ t('empleados', 'Project analysis') }}
					</h3>
					<p>{{ t('empleados', 'Select an active company, valid dates and at least one activity to continue.') }}</p>
				</div>
			</div>

			<div class="planning__form-grid">
				<div class="planning__field planning__field--wide">
					<NcSelect
						:value="selectedCompany"
						:options="companyOptions"
						label="label"
						:clearable="true"
						:input-label="t('empleados', 'Company or group')"
						:placeholder="t('empleados', 'Select a visible company')"
						@input="setCompany" />
					<p v-if="!companyOptions.length" class="planning__field-help">
						{{ t('empleados', 'No active companies are available in your scope.') }}
					</p>
				</div>

				<div class="planning__field planning__field--wide">
					<NcSelect
						:value="selectedLeader"
						:options="leaderOptions"
						label="label"
						:clearable="true"
						:input-label="t('empleados', 'Optional project leader')"
						:placeholder="t('empleados', 'Select a visible project leader')"
						@input="setLeader" />
				</div>

				<label class="planning__field">
					<span>{{ t('empleados', 'Start date') }}</span>
					<input
						:value="scenario.startDate || ''"
						type="date"
						:max="scenario.endDate || undefined"
						@input="setRequirementField('startDate', $event.target.value)">
				</label>

				<label class="planning__field">
					<span>{{ t('empleados', 'End date') }}</span>
					<input
						:value="scenario.endDate || ''"
						type="date"
						:min="scenario.startDate || undefined"
						@input="setRequirementField('endDate', $event.target.value)">
				</label>

				<label class="planning__field">
					<span>{{ t('empleados', 'Proposed price') }}</span>
					<input
						:value="scenario.price"
						type="number"
						min="0"
						step="0.01"
						inputmode="decimal"
						@input="setNonNegativeField('price', $event.target.value)">
				</label>

				<label class="planning__field">
					<span class="planning__label-with-help">
						{{ t('empleados', 'Contingency') }}
						<HelpHint
							:label="t('empleados', 'About contingency')"
							:text="t('empleados', 'Additional percentage added to estimated personnel cost to account for uncertainty.')" />
					</span>
					<div class="planning__input-suffix">
						<input
							:value="scenario.contingency"
							type="number"
							min="0"
							step="0.01"
							inputmode="decimal"
							@input="setNonNegativeField('contingency', $event.target.value)">
						<span aria-hidden="true">%</span>
					</div>
				</label>
			</div>

			<div class="planning__activities">
				<div class="planning__section-heading">
					<div>
						<h4>{{ t('empleados', 'Required activities') }}</h4>
						<p>{{ t('empleados', 'Select one or more activities and estimate the hours required for each one.') }}</p>
					</div>
					<strong>{{ hours(totalRequiredHours) }}</strong>
				</div>

				<NcSelect
					:value="selectedActivityOptions"
					:options="activityOptions"
					label="label"
					:multiple="true"
					:close-on-select="false"
					:clearable="true"
					:input-label="t('empleados', 'Activities')"
					:placeholder="t('empleados', 'Select activities')"
					@input="setActivities" />

				<div v-if="scenarioActivities.length" class="planning__activity-list">
					<div
						v-for="activity in scenarioActivities"
						:key="activity.id_actividad"
						class="planning__activity-row">
						<div>
							<strong>{{ activity.nombre }}</strong>
							<span class="planning__badge">
								{{ activity.cargable ? t('empleados', 'Billable') : t('empleados', 'Non-billable') }}
							</span>
						</div>

						<label>
							<span>{{ t('empleados', 'Estimated hours') }}</span>
							<input
								:value="activity.horas_estimadas"
								type="number"
								min="0"
								step="0.25"
								inputmode="decimal"
								@input="setActivityHours(activity.id_actividad, $event.target.value)">
						</label>

						<NcButton
							type="tertiary"
							:aria-label="t('empleados', 'Remove {activity}', { activity: activity.nombre })"
							@click="removeActivity(activity.id_actividad)">
							<template #icon>
								<DeleteOutline :size="20" />
							</template>
						</NcButton>
					</div>
				</div>

				<p v-else class="planning__empty-inline">
					{{ t('empleados', 'Select at least one activity to analyze candidates.') }}
				</p>
			</div>

			<div v-if="dateRangeInvalid" class="planning__alert planning__alert--error" role="alert">
				{{ t('empleados', 'The end date must be on or after the start date.') }}
			</div>

			<div class="planning__actions">
				<NcButton
					type="primary"
					:disabled="!canAnalyze || loadingCandidates"
					@click="analyzeCandidates">
					<template #icon>
						<NcLoadingIcon v-if="loadingCandidates" :size="20" />
						<AccountSearchOutline v-else :size="20" />
					</template>
					{{ loadingCandidates ? t('empleados', 'Analyzing candidates') : t('empleados', 'Analyze candidates') }}
				</NcButton>
				<p v-if="!canAnalyze && !dateRangeInvalid">
					{{ t('empleados', 'Select an active company, valid dates and at least one activity to continue.') }}
				</p>
			</div>
		</section>

		<section class="planning__panel planning__panel--candidates" :aria-labelledby="`${componentId}-candidates-title`">
			<div class="planning__section-heading">
				<div class="planning__panel-heading">
					<span class="planning__step-number" aria-hidden="true">2</span>
					<div>
						<h3 :id="`${componentId}-candidates-title`">
							{{ t('empleados', 'Visible candidates') }}
						</h3>
						<p>{{ candidateSummary }}</p>
					</div>
				</div>
				<span class="planning__label-with-help">
					{{ t('empleados', 'Estimated availability') }}
					<HelpHint
						:label="t('empleados', 'About estimated availability')"
						:text="t('empleados', 'Calculated from working days, configured daily hours, approved absences and reported hours. It does not include future project commitments.')" />
				</span>
			</div>

			<div v-if="hasAnalyzed" class="planning__filters">
				<label class="planning__field planning__field--search">
					<span>{{ t('empleados', 'Search employees') }}</span>
					<input
						v-model.trim="candidateSearch"
						type="search"
						:placeholder="t('empleados', 'Name, UID, area or position')">
				</label>

				<details class="planning__advanced-filters">
					<summary>{{ t('empleados', 'Filters') }}</summary>
					<div class="planning__filter-grid">
						<label class="planning__field">
							<span>{{ t('empleados', 'Availability filter') }}</span>
							<select v-model="availabilityFilter">
								<option value="all">
									{{ t('empleados', 'All availability levels') }}
								</option>
								<option value="sufficient">
									{{ t('empleados', 'Enough for required hours') }}
								</option>
								<option value="positive">
									{{ t('empleados', 'With estimated availability') }}
								</option>
								<option value="low">
									{{ t('empleados', 'Low availability') }}
								</option>
								<option value="unknown">
									{{ t('empleados', 'Availability not calculable') }}
								</option>
							</select>
						</label>

						<label class="planning__field">
							<span>{{ t('empleados', 'Experience filter') }}</span>
							<select v-model="experienceFilter">
								<option value="all">
									{{ t('empleados', 'All experience levels') }}
								</option>
								<option value="activities">
									{{ t('empleados', 'Experience in selected activities') }}
								</option>
								<option value="company">
									{{ t('empleados', 'Previous experience with company') }}
								</option>
								<option value="none">
									{{ t('empleados', 'Without related experience') }}
								</option>
							</select>
						</label>

						<label class="planning__field">
							<span>{{ t('empleados', 'Activity experience filter') }}</span>
							<select v-model="activityFilter">
								<option value="all">
									{{ t('empleados', 'All selected activities') }}
								</option>
								<option
									v-for="activity in scenarioActivities"
									:key="activity.id_actividad"
									:value="String(activity.id_actividad)">
									{{ activity.nombre }}
								</option>
							</select>
						</label>
					</div>
				</details>
			</div>

			<div v-if="candidateError" class="planning__alert planning__alert--error" role="alert">
				{{ candidateError }}
				<NcButton type="tertiary" :disabled="loadingCandidates" @click="analyzeCandidates">
					{{ t('empleados', 'Try again') }}
				</NcButton>
			</div>

			<div v-else-if="loadingCandidates" class="planning__loading" role="status">
				<NcLoadingIcon :size="40" />
				<p>{{ t('empleados', 'Analyzing visible employees') }}</p>
			</div>

			<NcEmptyContent
				v-else-if="!hasAnalyzed"
				:name="t('empleados', 'Project requirements are ready to be analyzed')"
				:description="t('empleados', 'Complete the project information and request the candidate analysis.')" />

			<NcEmptyContent
				v-else-if="!candidates.length"
				:name="t('empleados', 'No candidates match the selected project requirements.')"
				:description="t('empleados', 'The analysis did not return visible candidates for this project.')" />

			<NcEmptyContent
				v-else-if="!filteredCandidates.length"
				:name="t('empleados', 'No candidates match the selected filters')"
				:description="t('empleados', 'Adjust the search, availability or experience filters.')" />

			<div v-else class="planning__candidate-list">
				<CostosCandidato
					v-for="candidate in filteredCandidates"
					:key="candidate.id_empleado"
					:candidate="candidate"
					:required-hours="totalRequiredHours"
					:selected-activity-ids="selectedActivityIds"
					:already-selected="isCandidateSelected(candidate.id_empleado)"
					@add="openAddMember" />
			</div>
		</section>

		<section class="planning__panel planning__panel--team" :aria-labelledby="`${componentId}-team-title`">
			<div class="planning__section-heading">
				<div class="planning__panel-heading">
					<span class="planning__step-number" aria-hidden="true">3</span>
					<div>
						<h3 :id="`${componentId}-team-title`">
							{{ t('empleados', 'Tentative team') }}
						</h3>
						<p>{{ t('empleados', 'Assignments in this table are temporary and do not modify employee or company records.') }}</p>
					</div>
				</div>
				<div class="planning__team-total">
					<span>{{ t('empleados', 'Assigned hours') }}</span>
					<strong>{{ hours(totalAssignedHours) }}</strong>
				</div>
			</div>

			<NcEmptyContent
				v-if="!team.length"
				:name="t('empleados', 'No employees have been added to this scenario')"
				:description="t('empleados', 'Analyze candidates and add employees to build a tentative team.')" />

			<div
				v-else
				class="planning__table-scroll"
				role="region"
				tabindex="0"
				:aria-label="t('empleados', 'Tentative team')">
				<table>
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
								{{ t('empleados', 'Estimated availability') }}
							</th>
							<th scope="col">
								{{ t('empleados', 'Estimated cost') }}
							</th>
							<th scope="col">
								{{ t('empleados', 'Alerts') }}
							</th>
							<th scope="col">
								{{ t('empleados', 'Actions') }}
							</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="member in team" :key="member.id_empleado">
							<td>
								<strong>{{ member.displayname || member.uid }}</strong>
								<span>{{ member.uid }}</span>
							</td>
							<td>{{ member.rol || t('empleados', 'Not specified') }}</td>
							<td>{{ memberActivityNames(member) }}</td>
							<td>{{ hours(member.horas_estimadas) }}</td>
							<td>{{ nullableHours(member.disponibilidad_estimada) }}</td>
							<td>{{ memberCost(member) }}</td>
							<td>
								<span v-if="member.snapshot_stale" class="planning__warning-text">
									{{ t('empleados', 'Some information needed for the analysis is incomplete.') }}
								</span>
								<span v-else-if="memberExceedsAvailability(member)" class="planning__warning-text">
									{{ t('empleados', 'Exceeds estimated availability') }}
								</span>
								<span v-else>—</span>
							</td>
							<td>
								<div class="planning__table-actions">
									<NcButton
										type="tertiary"
										:aria-label="t('empleados', 'Edit {employee}', { employee: member.displayname || member.uid })"
										@click="openEditMember(member)">
										<template #icon>
											<PencilOutline :size="20" />
										</template>
									</NcButton>
									<NcButton
										type="tertiary"
										:aria-label="t('empleados', 'Remove {employee}', { employee: member.displayname || member.uid })"
										@click="removeMember(member.id_empleado)">
										<template #icon>
											<DeleteOutline :size="20" />
										</template>
									</NcButton>
								</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div
				v-if="planningAlerts.length"
				class="planning__alert planning__alert--warning"
				role="status">
				<ul class="planning__alert-list">
					<li v-for="alert in planningAlerts" :key="alert">
						{{ alert }}
					</li>
				</ul>
			</div>

			<div class="planning__quote-action">
				<NcButton type="primary" :disabled="!team.length" @click="$emit('go-to-quote')">
					{{ t('empleados', 'Go to quote') }}
				</NcButton>
			</div>
		</section>

		<NcModal
			v-if="memberModalOpen"
			size="normal"
			:name="memberModalTitle"
			@close="closeMemberModal">
			<form class="planning__member-modal" @submit.prevent="saveMember">
				<h2>{{ memberModalTitle }}</h2>
				<p>{{ memberDraft.displayname || memberDraft.uid }}</p>

				<label class="planning__field">
					<span>{{ t('empleados', 'Role in project') }}</span>
					<input
						v-model.trim="memberDraft.rol"
						type="text"
						maxlength="120"
						:placeholder="t('empleados', 'Example: Senior auditor')">
				</label>

				<label class="planning__field">
					<span>{{ t('empleados', 'Assigned hours') }}</span>
					<input
						v-model.number="memberDraft.horas_estimadas"
						type="number"
						min="0"
						step="0.25"
						inputmode="decimal"
						required>
				</label>

				<NcSelect
					v-model="memberDraft.actividades"
					:options="memberActivityOptions"
					label="label"
					:multiple="true"
					:close-on-select="false"
					:clearable="true"
					:input-label="t('empleados', 'Project activities')" />

				<div
					v-if="draftExceedsAvailability"
					class="planning__alert planning__alert--warning"
					role="status">
					{{ t('empleados', 'Assigned hours exceed estimated availability. You can continue, but review this assignment.') }}
				</div>

				<div class="planning__modal-actions">
					<NcButton type="tertiary" @click="closeMemberModal">
						{{ t('empleados', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" native-type="submit" :disabled="!memberDraftValid">
						{{ editingMemberId === null ? t('empleados', 'Add employee') : t('empleados', 'Save changes') }}
					</NcButton>
				</div>
			</form>
		</NcModal>
	</section>
</template>

<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import {
	NcButton,
	NcEmptyContent,
	NcLoadingIcon,
	NcModal,
	NcSelect,
} from '@nextcloud/vue'
import AccountSearchOutline from 'vue-material-design-icons/AccountSearchOutline.vue'
import DeleteOutline from 'vue-material-design-icons/DeleteOutline.vue'
import PencilOutline from 'vue-material-design-icons/PencilOutline.vue'

import HelpHint from '../Helpers/HelpHint.vue'
import CostosCandidato from './CostosCandidato.vue'

let planningComponentId = 0

export default {
	name: 'CostosPlanificacion',
	components: {
		AccountSearchOutline,
		CostosCandidato,
		DeleteOutline,
		HelpHint,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcModal,
		NcSelect,
		PencilOutline,
	},
	props: {
		scenario: {
			type: Object,
			required: true,
		},
		companies: {
			type: Array,
			default: () => [],
		},
		activities: {
			type: Array,
			default: () => [],
		},
		leaders: {
			type: Array,
			default: () => [],
		},
	},
	data() {
		planningComponentId += 1

		return {
			componentId: `costos-planning-${planningComponentId}`,
			candidates: [],
			candidateSearch: '',
			availabilityFilter: 'all',
			experienceFilter: 'all',
			activityFilter: 'all',
			loadingCandidates: false,
			candidateError: '',
			analyzedSignature: '',
			requestSerial: 0,
			requestController: null,
			memberModalOpen: false,
			editingMemberId: null,
			memberCandidateSnapshot: null,
			memberDraft: this.emptyMemberDraft(),
		}
	},
	computed: {
		planningSteps() {
			return [
				{ id: 'project', label: t('empleados', 'Project analysis') },
				{ id: 'candidates', label: t('empleados', 'Visible candidates') },
				{ id: 'team', label: t('empleados', 'Tentative team') },
			]
		},
		currentPlanningStep() {
			if (!this.hasAnalyzed) {
				return 1
			}

			return this.team.length ? 3 : 2
		},
		companyOptions() {
			return this.companies
				.map(company => this.normalizeCompany(company))
				.filter(company => company !== null && company.active)
				.sort((left, right) => left.label.localeCompare(right.label, 'es'))
		},
		selectedCompany() {
			return this.companyOptions.find(
				company => company.id === Number(this.scenario.companyId),
			) || null
		},
		leaderOptions() {
			return this.leaders
				.map(leader => this.normalizeLeader(leader))
				.filter(Boolean)
				.sort((left, right) => left.label.localeCompare(right.label, 'es'))
		},
		selectedLeader() {
			return this.leaderOptions.find(
				leader => leader.id === Number(this.scenario.leaderId),
			) || null
		},
		activityOptions() {
			return this.activities
				.map(activity => this.normalizeActivityOption(activity))
				.filter(Boolean)
				.sort((left, right) => left.label.localeCompare(right.label, 'es'))
		},
		scenarioActivities() {
			return (Array.isArray(this.scenario.activities) ? this.scenario.activities : [])
				.map(activity => this.normalizeScenarioActivity(activity))
				.filter(Boolean)
		},
		selectedActivityOptions() {
			const ids = new Set(this.scenarioActivities.map(activity => activity.id_actividad))
			return this.activityOptions.filter(activity => ids.has(activity.id))
		},
		selectedActivityIds() {
			return this.scenarioActivities.map(activity => activity.id_actividad)
		},
		totalRequiredHours() {
			return this.scenarioActivities.reduce(
				(total, activity) => total + this.nonNegativeNumber(activity.horas_estimadas),
				0,
			)
		},
		dateRangeInvalid() {
			return Boolean(
				this.scenario.startDate
				&& this.scenario.endDate
				&& this.scenario.startDate > this.scenario.endDate,
			)
		},
		canAnalyze() {
			return Boolean(
				this.selectedCompany
				&& this.scenario.startDate
				&& this.scenario.endDate
				&& !this.dateRangeInvalid
				&& this.scenarioActivities.length,
			)
		},
		analysisInputSignature() {
			return JSON.stringify({
				scenario: this.scenario.id,
				companyId: Number(this.scenario.companyId) || null,
				startDate: this.scenario.startDate || '',
				endDate: this.scenario.endDate || '',
				activities: this.scenarioActivities.map(activity => [
					activity.id_actividad,
					activity.horas_estimadas,
				]),
			})
		},
		hasAnalyzed() {
			return Boolean(
				this.analyzedSignature
				&& this.analyzedSignature === this.analysisInputSignature,
			)
		},
		filteredCandidates() {
			const needle = this.candidateSearch.toLocaleLowerCase('es')

			return this.candidates.filter(candidate => {
				if (needle && !this.candidateSearchText(candidate).includes(needle)) {
					return false
				}

				if (!this.matchesAvailability(candidate)) {
					return false
				}

				return this.matchesExperience(candidate)
					&& this.matchesActivityFilter(candidate)
			})
		},
		candidateSummary() {
			if (!this.hasAnalyzed) {
				return t('empleados', 'Candidate analysis has not been requested for the current requirements.')
			}

			return t('empleados', '{visible} of {total} visible candidates', {
				visible: this.filteredCandidates.length,
				total: this.candidates.length,
			})
		},
		team() {
			return Array.isArray(this.scenario.team) ? this.scenario.team : []
		},
		totalAssignedHours() {
			return this.team.reduce(
				(total, member) => total + this.nonNegativeNumber(member.horas_estimadas),
				0,
			)
		},
		teamEstimatedAvailability() {
			if (
				!this.team.length
				|| this.team.some(member => !this.hasNumber(member.disponibilidad_estimada))
			) {
				return null
			}

			return this.team.reduce(
				(total, member) => total + this.nonNegativeNumber(member.disponibilidad_estimada),
				0,
			)
		},
		planningAlertKeys() {
			const alerts = []

			if (this.totalRequiredHours > this.totalAssignedHours) {
				alerts.push('required_hours_unassigned')
			}

			if (this.team.length === 1 && this.totalAssignedHours > 0) {
				alerts.push('single_person_dependency')
			}

			if (
				this.teamEstimatedAvailability !== null
				&& this.totalRequiredHours > this.teamEstimatedAvailability
			) {
				alerts.push('team_availability_shortfall')
			}

			if (this.hasAnalyzed && this.totalRequiredHours > 0) {
				const availableCandidates = this.candidates.filter(candidate => (
					candidate.capacidad_calculable === true
						&& this.hasNumber(candidate.disponibilidad_estimada)
						&& Number(candidate.disponibilidad_estimada) > 0
				))
				const availableHours = availableCandidates.reduce(
					(total, candidate) => total + Number(candidate.disponibilidad_estimada),
					0,
				)

				if (!availableCandidates.length || availableHours < this.totalRequiredHours) {
					alerts.push('insufficient_available_staff')
				}
			}

			return alerts
		},
		planningAlerts() {
			return this.planningAlertKeys.map(key => this.planningAlertLabel(key))
		},
		memberActivityOptions() {
			return this.scenarioActivities.map(activity => ({
				id: activity.id_actividad,
				label: activity.nombre,
				nombre: activity.nombre,
				cargable: activity.cargable,
			}))
		},
		memberModalTitle() {
			return this.editingMemberId === null
				? t('empleados', 'Add employee to tentative team')
				: t('empleados', 'Edit tentative assignment')
		},
		memberDraftValid() {
			const idEmpleado = this.editingMemberId === null
				? Number(this.memberCandidateSnapshot?.id_empleado)
				: Number(this.editingMemberId)

			return Boolean(
				Number.isInteger(idEmpleado)
				&& idEmpleado > 0
				&& this.hasNumber(this.memberDraft.horas_estimadas)
				&& Number(this.memberDraft.horas_estimadas) >= 0,
			)
		},
		draftExceedsAvailability() {
			const availability = this.memberDraft.disponibilidad_estimada
			return this.hasNumber(availability)
				&& Number(this.memberDraft.horas_estimadas) > Number(availability)
		},
	},
	watch: {
		analysisInputSignature(next, previous) {
			if (next !== previous && next !== this.analyzedSignature) {
				this.invalidateAnalysis()
			}
		},
		'scenario.id'() {
			this.candidateSearch = ''
			this.availabilityFilter = 'all'
			this.experienceFilter = 'all'
			this.activityFilter = 'all'
			this.closeMemberModal()
		},
	},
	beforeDestroy() {
		this.cancelCandidateRequest()
	},
	methods: {
		t,
		emitChanges(changes) {
			this.$emit('update-scenario', {
				id: this.scenario.id,
				changes,
			})
		},
		setScenarioField(field, value) {
			this.emitChanges({ [field]: value })
		},
		setRequirementField(field, value) {
			this.emitRequirementChanges({ [field]: value })
		},
		setNonNegativeField(field, value) {
			const normalized = value === '' ? null : this.nonNegativeNumber(value)
			this.emitChanges({ [field]: normalized })
		},
		setCompany(company) {
			this.emitRequirementChanges({
				companyId: company ? company.id : null,
				company: company
					? { id: company.id, nombre: company.label }
					: null,
			})
		},
		setLeader(leader) {
			this.emitChanges({
				leaderId: leader ? leader.id : null,
				leader: leader
					? {
						id: leader.id,
						displayname: leader.displayname,
						uid: leader.uid,
					}
					: null,
			})
		},
		setActivities(options) {
			const current = new Map(
				this.scenarioActivities.map(activity => [activity.id_actividad, activity]),
			)
			const selected = Array.isArray(options) ? options : []
			const next = selected.map(option => {
				const existing = current.get(option.id)
				return {
					id_actividad: option.id,
					nombre: option.nombre,
					cargable: option.cargable,
					horas_estimadas: existing ? existing.horas_estimadas : 0,
				}
			})

			const allowedIds = new Set(next.map(activity => activity.id_actividad))
			this.emitRequirementChanges({ activities: next }, allowedIds)
			if (
				this.activityFilter !== 'all'
					&& !next.some(activity => String(activity.id_actividad) === this.activityFilter)
			) {
				this.activityFilter = 'all'
			}
		},
		setActivityHours(id, value) {
			const hours = this.nonNegativeNumber(value)
			const next = this.scenarioActivities.map(activity => ({
				...activity,
				horas_estimadas: activity.id_actividad === Number(id)
					? hours
					: activity.horas_estimadas,
			}))
			this.emitRequirementChanges({ activities: next })
		},
		removeActivity(id) {
			const next = this.scenarioActivities.filter(
				activity => activity.id_actividad !== Number(id),
			)
			const allowedIds = new Set(next.map(activity => activity.id_actividad))
			this.emitRequirementChanges({ activities: next }, allowedIds)
			if (String(id) === this.activityFilter) {
				this.activityFilter = 'all'
			}
		},
		emitRequirementChanges(changes, allowedActivityIds = null) {
			this.emitChanges({
				...changes,
				analysis: null,
				team: this.team.map(member => this.staleMemberSnapshot(
					member,
					allowedActivityIds,
				)),
			})
		},
		staleMemberSnapshot(member, allowedActivityIds = null) {
			const activities = Array.isArray(member.actividades) ? member.actividades : []

			return {
				...member,
				actividades: allowedActivityIds
					? activities.filter(activity => allowedActivityIds.has(this.activityId(activity)))
					: activities,
				capacidad_calculable: null,
				capacidad_efectiva: null,
				horas_reportadas_periodo: null,
				disponibilidad_estimada: null,
				experiencia: {},
				ajuste_estimado: null,
				calidad_datos: 'baja',
				riesgos: [{ key: 'datos_incompletos' }],
				snapshot_stale: true,
			}
		},
		async analyzeCandidates() {
			if (!this.canAnalyze) {
				return
			}

			this.cancelCandidateRequest()
			const serial = ++this.requestSerial
			const controller = new AbortController()
			const signature = this.analysisInputSignature
			this.requestController = controller
			this.loadingCandidates = true
			this.candidateError = ''

			try {
				const response = await axios.post(
					generateUrl('/apps/empleados/GetCostosCandidatos'),
					{
						id_cliente: this.selectedCompany.id,
						fecha_inicio: this.scenario.startDate,
						fecha_fin: this.scenario.endDate,
						actividades: this.scenarioActivities.map(activity => ({
							id_actividad: activity.id_actividad,
							horas_estimadas: activity.horas_estimadas,
						})),
					},
					{ signal: controller.signal },
				)

				if (serial !== this.requestSerial || signature !== this.analysisInputSignature) {
					return
				}

				const ocs = response?.data?.ocs
				if (ocs?.meta?.status !== 'ok') {
					throw new Error('Invalid OCS response')
				}

				const data = ocs.data || {}
				this.candidates = Array.isArray(data.candidatos) ? data.candidatos : []
				this.analyzedSignature = signature
				const calculableCandidates = this.candidates.filter(candidate => (
					candidate.capacidad_calculable === true
						&& this.hasNumber(candidate.disponibilidad_estimada)
				))
				this.emitChanges({
					analysis: {
						signature,
						candidatesCount: this.candidates.length,
						availableCandidatesCount: calculableCandidates.filter(
							candidate => Number(candidate.disponibilidad_estimada) > 0,
						).length,
						availableHours: calculableCandidates.reduce(
							(total, candidate) => (
								total + this.nonNegativeNumber(candidate.disponibilidad_estimada)
							),
							0,
						),
					},
					team: this.refreshTeamSnapshots(this.candidates),
				})
			} catch (error) {
				if (this.isCanceledRequest(error) || serial !== this.requestSerial) {
					return
				}

				this.candidates = []
				this.analyzedSignature = ''
				this.candidateError = t('empleados', 'Could not analyze candidates. Check the project information and try again.')
			} finally {
				if (serial === this.requestSerial) {
					this.loadingCandidates = false
					this.requestController = null
				}
			}
		},
		refreshTeamSnapshots(candidates) {
			const snapshots = new Map((Array.isArray(candidates) ? candidates : [])
				.map(candidate => [
					Number(candidate.id_empleado),
					this.safeCandidateSnapshot(candidate),
				]))

			return this.team.map(member => {
				const snapshot = snapshots.get(Number(member.id_empleado))
				if (!snapshot) {
					return this.staleMemberSnapshot(member)
				}

				return {
					...member,
					...snapshot,
					rol: member.rol,
					horas_estimadas: member.horas_estimadas,
					actividades: member.actividades,
					snapshot_stale: false,
				}
			})
		},
		cancelCandidateRequest() {
			if (this.requestController) {
				this.requestController.abort()
				this.requestController = null
			}
		},
		invalidateAnalysis() {
			this.cancelCandidateRequest()
			this.requestSerial += 1
			this.loadingCandidates = false
			this.candidates = []
			this.candidateError = ''
			this.analyzedSignature = ''

			if (this.scenario.analysis) {
				this.emitChanges({ analysis: null })
			}
		},
		isCanceledRequest(error) {
			return error?.code === 'ERR_CANCELED'
				|| error?.name === 'CanceledError'
				|| error?.name === 'AbortError'
		},
		candidateSearchText(candidate) {
			return [
				candidate.displayname,
				candidate.uid,
				candidate.area,
				candidate.puesto,
			].filter(Boolean).join(' ').toLocaleLowerCase('es')
		},
		matchesAvailability(candidate) {
			if (this.availabilityFilter === 'all') {
				return true
			}

			const calculable = candidate.capacidad_calculable === true
			const hasAvailability = this.hasNumber(candidate.disponibilidad_estimada)
			const availability = hasAvailability
				? Number(candidate.disponibilidad_estimada)
				: null

			if (this.availabilityFilter === 'unknown') {
				return !calculable || !hasAvailability
			}

			if (!calculable || !hasAvailability) {
				return false
			}

			if (this.availabilityFilter === 'sufficient') {
				return availability >= this.totalRequiredHours
			}

			if (this.availabilityFilter === 'positive') {
				return availability > 0
			}

			return availability <= 0 || Number(candidate.ocupacion_estimada) >= 90
		},
		matchesExperience(candidate) {
			if (this.experienceFilter === 'all') {
				return true
			}

			const experience = candidate.experiencia || {}
			const activityHours = Number(experience.horas_actividades || 0)
			const companyHours = Number(experience.horas_empresa || 0)

			if (this.experienceFilter === 'activities') {
				return activityHours > 0
			}

			if (this.experienceFilter === 'company') {
				return companyHours > 0
			}

			return activityHours <= 0 && companyHours <= 0
		},
		matchesActivityFilter(candidate) {
			if (this.activityFilter === 'all') {
				return true
			}

			const rows = Array.isArray(candidate.experiencia?.actividades)
				? candidate.experiencia.actividades
				: []

			return rows.some(row => (
				String(row.id_actividad) === this.activityFilter
					&& Number(row.horas || 0) > 0
			))
		},
		isCandidateSelected(id) {
			return this.team.some(member => Number(member.id_empleado) === Number(id))
		},
		openAddMember(candidate) {
			if (this.isCandidateSelected(candidate.id_empleado)) {
				return
			}

			this.editingMemberId = null
			this.memberCandidateSnapshot = this.safeCandidateSnapshot(candidate)
			this.memberDraft = {
				...this.emptyMemberDraft(),
				uid: this.memberCandidateSnapshot.uid,
				displayname: this.memberCandidateSnapshot.displayname,
				disponibilidad_estimada: this.memberCandidateSnapshot.disponibilidad_estimada,
				actividades: [...this.memberActivityOptions],
			}
			this.memberModalOpen = true
		},
		openEditMember(member) {
			this.editingMemberId = Number(member.id_empleado)
			this.memberCandidateSnapshot = null
			const selectedIds = new Set(
				(Array.isArray(member.actividades) ? member.actividades : [])
					.map(activity => this.activityId(activity)),
			)
			this.memberDraft = {
				uid: String(member.uid || ''),
				displayname: String(member.displayname || member.uid || ''),
				rol: String(member.rol || ''),
				horas_estimadas: this.nonNegativeNumber(member.horas_estimadas),
				actividades: this.memberActivityOptions.filter(activity => selectedIds.has(activity.id)),
				disponibilidad_estimada: this.nullableNumber(member.disponibilidad_estimada),
			}
			this.memberModalOpen = true
		},
		closeMemberModal() {
			this.memberModalOpen = false
			this.editingMemberId = null
			this.memberCandidateSnapshot = null
			this.memberDraft = this.emptyMemberDraft()
		},
		saveMember() {
			if (!this.memberDraftValid) {
				return
			}

			const assignment = {
				rol: this.memberDraft.rol.trim(),
				horas_estimadas: this.nonNegativeNumber(this.memberDraft.horas_estimadas),
				actividades: this.memberDraft.actividades.map(activity => ({
					id_actividad: activity.id,
					nombre: activity.nombre,
					cargable: Boolean(activity.cargable),
				})),
			}
			let nextTeam

			if (this.editingMemberId === null) {
				nextTeam = [
					...this.team,
					{
						...this.memberCandidateSnapshot,
						...assignment,
					},
				]
			} else {
				nextTeam = this.team.map(member => Number(member.id_empleado) === this.editingMemberId
					? { ...member, ...assignment }
					: member)
			}

			this.emitChanges({ team: nextTeam })
			this.closeMemberModal()
		},
		removeMember(id) {
			this.emitChanges({
				team: this.team.filter(member => Number(member.id_empleado) !== Number(id)),
			})
		},
		safeCandidateSnapshot(candidate) {
			return {
				id_empleado: Number(candidate.id_empleado),
				uid: String(candidate.uid || ''),
				displayname: String(candidate.displayname || candidate.uid || ''),
				costo_hora: this.nullableNumber(candidate.costo_hora),
				capacidad_calculable: candidate.capacidad_calculable === true,
				capacidad_efectiva: this.nullableNumber(candidate.capacidad_efectiva),
				horas_reportadas_periodo: this.nullableNumber(candidate.horas_reportadas_periodo),
				disponibilidad_estimada: this.nullableNumber(candidate.disponibilidad_estimada),
				experiencia: this.safeExperienceSnapshot(candidate.experiencia),
				ajuste_estimado: this.nullableNumber(candidate.ajuste_estimado),
				calidad_datos: ['alta', 'media', 'baja'].includes(candidate.calidad_datos)
					? candidate.calidad_datos
					: 'baja',
				riesgos: this.safeRiskSnapshot(candidate.riesgos),
				snapshot_stale: false,
			}
		},
		safeRiskSnapshot(risks) {
			const allowed = new Set([
				'horas_superan_disponibilidad',
				'ocupacion_supera_100',
				'ocupacion_supera_90',
				'sin_experiencia_actividades',
				'sin_experiencia_empresa',
				'sin_costo_hora',
				'capacidad_no_calculable',
				'datos_incompletos',
			])

			return (Array.isArray(risks) ? risks : []).reduce((result, risk) => {
				const key = typeof risk === 'string'
					? risk
					: risk?.key || risk?.code || ''

				if (allowed.has(key) && !result.some(item => item.key === key)) {
					result.push({ key })
				}

				return result
			}, [])
		},
		safeExperienceSnapshot(experience) {
			const source = experience && typeof experience === 'object' ? experience : {}
			const keys = [
				'horas_actividades',
				'horas_actividades_12_meses',
				'registros_actividades',
				'empresas_actividades',
				'horas_empresa',
				'horas_cargables_empresa',
				'ultimo_reporte_empresa',
				'actividades_empresa',
				'empresas_atendidas',
			]

			const snapshot = keys.reduce((result, key) => {
				if (source[key] !== null && source[key] !== undefined) {
					result[key] = source[key]
				}
				return result
			}, {})

			snapshot.actividades = (Array.isArray(source.actividades) ? source.actividades : [])
				.map(activity => ({
					id_actividad: Number(activity.id_actividad),
					horas: this.nonNegativeNumber(activity.horas),
					horas_12_meses: this.nonNegativeNumber(activity.horas_12_meses),
					registros: this.nonNegativeNumber(activity.registros),
					ultimo_reporte: activity.ultimo_reporte || null,
				}))
				.filter(activity => Number.isInteger(activity.id_actividad) && activity.id_actividad > 0)

			return snapshot
		},
		normalizeCompany(company) {
			const id = Number(company.id ?? company.id_cliente)
			const label = String(company.nombre ?? company.nombre_cliente ?? '')
			if (!Number.isInteger(id) || id <= 0 || !label) {
				return null
			}

			const state = company.estado
			return {
				id,
				label,
				active: state === null
					|| state === undefined
					|| state === true
					|| Number(state) === 1,
			}
		},
		normalizeLeader(leader) {
			const id = Number(leader.id ?? leader.id_empleado)
			const uid = String(leader.uid ?? leader.id_user ?? '')
			const displayname = String(leader.displayname ?? leader.nombre ?? uid)
			if (!Number.isInteger(id) || id <= 0 || !displayname) {
				return null
			}

			return {
				id,
				uid,
				displayname,
				label: uid && uid !== displayname
					? `${displayname} (${uid})`
					: displayname,
			}
		},
		normalizeActivityOption(activity) {
			const id = Number(activity.id_actividad ?? activity.Id_actividad ?? activity.id)
			const nombre = String(activity.nombre ?? activity.Nombre ?? '')
			if (!Number.isInteger(id) || id <= 0 || id === 99999 || !nombre) {
				return null
			}

			return {
				id,
				label: nombre,
				nombre,
				cargable: Boolean(Number(activity.cargable ?? activity.Cargable ?? 0)),
			}
		},
		normalizeScenarioActivity(activity) {
			const option = this.normalizeActivityOption(activity)
			if (!option) {
				return null
			}

			return {
				id_actividad: option.id,
				nombre: option.nombre,
				cargable: option.cargable,
				horas_estimadas: this.nonNegativeNumber(activity.horas_estimadas),
			}
		},
		activityId(activity) {
			return Number(activity?.id_actividad ?? activity?.id ?? activity)
		},
		emptyMemberDraft() {
			return {
				uid: '',
				displayname: '',
				rol: '',
				horas_estimadas: 0,
				actividades: [],
				disponibilidad_estimada: null,
			}
		},
		hasNumber(value) {
			return value !== null
				&& value !== undefined
				&& value !== ''
				&& Number.isFinite(Number(value))
		},
		nullableNumber(value) {
			return this.hasNumber(value) ? Number(value) : null
		},
		nonNegativeNumber(value) {
			const number = Number(value)
			return Number.isFinite(number)
				? Math.min(Number.MAX_SAFE_INTEGER, Math.max(0, number))
				: 0
		},
		memberExceedsAvailability(member) {
			return this.hasNumber(member.disponibilidad_estimada)
				&& Number(member.horas_estimadas) > Number(member.disponibilidad_estimada)
		},
		memberActivityNames(member) {
			const names = (Array.isArray(member.actividades) ? member.actividades : [])
				.map(activity => activity.nombre || activity.label)
				.filter(Boolean)
			return names.length ? names.join(', ') : t('empleados', 'Not specified')
		},
		memberCost(member) {
			if (!this.hasNumber(member.costo_hora)) {
				return t('empleados', 'Not configured')
			}

			return this.money(
				this.nonNegativeNumber(member.horas_estimadas) * Number(member.costo_hora),
			)
		},
		planningAlertLabel(key) {
			const labels = {
				required_hours_unassigned: t('empleados', 'Required hours exceed the hours currently assigned to the tentative team.'),
				single_person_dependency: t('empleados', 'The current plan depends entirely on one person.'),
				team_availability_shortfall: t('empleados', 'Required hours exceed the estimated availability of the tentative team.'),
				insufficient_available_staff: t('empleados', 'There may not be enough visible personnel with estimated availability for the requirement.'),
			}

			return labels[key] || t('empleados', 'Review the tentative staffing plan.')
		},
		hours(value) {
			return t('empleados', '{value} hours', {
				value: new Intl.NumberFormat('es-MX', {
					maximumFractionDigits: 2,
				}).format(this.nonNegativeNumber(value)),
			})
		},
		nullableHours(value) {
			return this.hasNumber(value)
				? this.hours(value)
				: t('empleados', 'Not available')
		},
		money(value) {
			return new Intl.NumberFormat('es-MX', {
				style: 'currency',
				currency: 'MXN',
				maximumFractionDigits: 2,
			}).format(Number(value) || 0)
		},
	},
}
</script>

<style scoped lang="scss">
.planning {
	display: flex;
	flex-direction: column;
	gap: 28px;
	padding: 4px 0 24px;
	color: var(--color-main-text);
}

.planning__header,
.planning__section-heading,
.planning__actions,
.planning__footer,
.planning__quote-action {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
}

.planning__header {
	align-items: flex-end;
	padding: 8px 4px 0;

	h2,
	p {
		margin: 0;
	}
}

.planning__steps {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
	margin: 0;
	padding: 0;
	list-style: none;

	li {
		display: flex;
		min-width: 0;
		align-items: center;
		gap: 10px;
		padding: 12px 14px;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large);
		color: var(--color-text-maxcontrast);
	}

	.planning__step-index {
		display: inline-flex;
		width: 28px;
		height: 28px;
		flex: 0 0 28px;
		align-items: center;
		justify-content: center;
		border: 1px solid var(--color-border);
		border-radius: 50%;
		font-weight: 700;
	}
}

.planning__step--current {
	border-color: var(--color-primary-element) !important;
	color: var(--color-main-text) !important;

	.planning__step-index {
		border-color: var(--color-primary-element) !important;
		background: var(--color-primary-element);
		color: var(--color-primary-element-text);
	}
}

.planning__step--complete .planning__step-index {
	border-color: var(--color-success) !important;
	color: var(--color-success);
}

.planning__header .planning__intro {
	max-width: 760px;
	margin-top: 6px;
	color: var(--color-text-maxcontrast);
}

.planning__eyebrow {
	color: var(--color-primary-element);
	font-weight: 600;
}

.planning__totals,
.planning__team-total {
	display: flex;
	min-width: 150px;
	flex-direction: column;
	align-items: flex-end;

	span {
		color: var(--color-text-maxcontrast);
	}

	strong {
		font-size: 1.2rem;
	}
}

.planning__panel {
	padding: 24px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);

	> h3 {
		margin: 0 0 18px;
	}
}

.planning__panel-heading {
	display: flex;
	align-items: flex-start;
	gap: 12px;

	h3,
	p {
		margin: 0;
	}

	p {
		margin-top: 4px;
		color: var(--color-text-maxcontrast);
	}
}

.planning__step-number {
	display: inline-flex;
	width: 32px;
	height: 32px;
	flex: 0 0 32px;
	align-items: center;
	justify-content: center;
	border-radius: 50%;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-weight: 700;
}

.planning__panel--configuration > .planning__panel-heading {
	margin-bottom: 22px;
}

.planning__form-grid {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 16px;
}

.planning__field {
	display: flex;
	min-width: 0;
	flex-direction: column;
	gap: 6px;

	> span {
		font-weight: 600;
	}

	input,
	select {
		width: 100%;
		min-height: 44px;
		margin: 0;
		padding: 8px 10px;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large);
		background: var(--color-main-background);
		color: var(--color-main-text);
	}

}

.planning__field--wide {
	grid-column: span 2;
}

.planning__field--search {
	flex: 1 1 320px;
}

.planning__field-help {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
}

.planning__label-with-help {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

.planning__input-suffix {
	position: relative;

	input {
		padding-inline-end: 34px;
	}

	span {
		position: absolute;
		top: 50%;
		right: 12px;
		color: var(--color-text-maxcontrast);
		transform: translateY(-50%);
		pointer-events: none;
	}
}

.planning__activities {
	display: flex;
	flex-direction: column;
	gap: 14px;
	margin-top: 22px;
	padding-top: 18px;
	border-top: 1px solid var(--color-border);
}

.planning__section-heading {
	align-items: flex-start;

	h3,
	h4,
	p {
		margin: 0;
	}

	p {
		margin-top: 4px;
		color: var(--color-text-maxcontrast);
	}
}

.planning__activity-list {
	display: grid;
	gap: 10px;
}

.planning__activity-row {
	display: grid;
	grid-template-columns: minmax(220px, 1fr) minmax(150px, 220px) auto;
	align-items: end;
	gap: 12px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);

	> div:first-child {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 8px;
		align-self: center;
	}

	label {
		display: flex;
		flex-direction: column;
		gap: 4px;

		span {
			color: var(--color-text-maxcontrast);
			font-size: 0.85rem;
		}

		input {
			width: 100%;
			min-height: 40px;
			margin: 0;
			padding: 7px 9px;
			border: 1px solid var(--color-border);
			border-radius: var(--border-radius-large);
			background: var(--color-main-background);
			color: var(--color-main-text);
		}
	}
}

.planning__field input:focus-visible,
.planning__field select:focus-visible,
.planning__activity-row input:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 1px;
}

.planning__badge {
	padding: 2px 8px;
	border: 1px solid var(--color-border);
	border-radius: 999px;
	color: var(--color-text-maxcontrast);
	font-size: 0.8rem;
}

.planning__empty-inline {
	margin: 0;
	padding: 12px;
	border: 1px dashed var(--color-border);
	border-radius: var(--border-radius-large);
	color: var(--color-text-maxcontrast);
	text-align: center;
}

.planning__actions {
	margin-top: 18px;

	p {
		margin: 0;
		color: var(--color-text-maxcontrast);
	}
}

.planning__filters {
	display: grid;
	grid-template-columns: minmax(260px, 1fr) minmax(220px, auto);
	align-items: end;
	gap: 16px;
	margin: 18px 0;
}

.planning__advanced-filters {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);

	summary {
		min-height: 44px;
		padding: 11px 14px;
		cursor: pointer;
		font-weight: 600;
	}
}

.planning__filter-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(190px, 1fr));
	gap: 12px;
	padding: 0 14px 14px;
}

.planning__alert {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-top: 16px;
	padding: 11px 12px;
	border: 1px solid var(--color-border);
	border-inline-start-width: 4px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.planning__alert--error {
	border-inline-start-color: var(--color-error);
}

.planning__alert--warning {
	border-inline-start-color: var(--color-warning);
}

.planning__alert-list {
	margin: 0;
	padding-inline-start: 20px;
}

.planning__loading {
	display: flex;
	min-height: 180px;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 10px;

	p {
		margin: 0;
		color: var(--color-text-maxcontrast);
	}
}

.planning__candidate-list {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(min(520px, 100%), 1fr));
	gap: 16px;
	margin-top: 18px;
}

.planning__table-scroll {
	overflow-x: auto;
	margin-top: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

table {
	width: 100%;
	border-collapse: collapse;
	background: var(--color-main-background);
}

th,
td {
	padding: 11px 12px;
	border-bottom: 1px solid var(--color-border);
	text-align: start;
	vertical-align: top;
}

th {
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}

td:first-child {
	min-width: 170px;

	strong,
	span {
		display: block;
	}

	span {
		color: var(--color-text-maxcontrast);
	}
}

.planning__warning-text {
	color: var(--color-warning);
	font-weight: 600;
}

.planning__table-actions {
	display: flex;
	gap: 4px;
}

.planning__quote-action {
	justify-content: flex-end;
	margin-top: 18px;
}

.planning__member-modal {
	display: flex;
	min-width: min(520px, calc(100vw - 48px));
	flex-direction: column;
	gap: 18px;
	padding: 22px;

	h2,
	p {
		margin: 0;
	}

	> p {
		color: var(--color-text-maxcontrast);
	}
}

.planning__modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
}

@media (max-width: 1100px) {
	.planning__form-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.planning__field--wide {
		grid-column: span 2;
	}
}

@media (max-width: 700px) {
	.planning {
		padding: 0 0 16px;
	}

	.planning__header,
	.planning__section-heading,
	.planning__actions {
		align-items: stretch;
		flex-direction: column;
	}

	.planning__totals,
	.planning__team-total {
		align-items: flex-start;
	}

	.planning__panel {
		padding: 14px;
	}

	.planning__steps {
		grid-template-columns: 1fr;
		gap: 8px;
	}

	.planning__form-grid {
		grid-template-columns: 1fr;
	}

	.planning__field--wide {
		grid-column: auto;
	}

	.planning__filters,
	.planning__filter-grid {
		grid-template-columns: 1fr;
	}

	.planning__activity-row {
		grid-template-columns: minmax(0, 1fr) auto;

		label {
			grid-column: 1 / -1;
			grid-row: 2;
		}
	}

	.planning__member-modal {
		min-width: min(440px, calc(100vw - 32px));
		padding: 16px;
	}

	.planning__modal-actions {
		align-items: stretch;
		flex-direction: column-reverse;
	}
}
</style>
