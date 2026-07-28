<template>
	<article class="candidate-card">
		<header class="candidate-card__header">
			<NcAvatar
				:user="candidateUid"
				:display-name="candidateName"
				:size="56"
				disable-menu />

			<div class="candidate-card__identity">
				<h3>{{ candidateName }}</h3>
				<p v-if="candidateUid">
					{{ candidateUid }}
				</p>
				<div v-if="candidateArea || candidatePosition" class="candidate-card__tags">
					<span v-if="candidateArea">{{ candidateArea }}</span>
					<span v-if="candidatePosition">{{ candidatePosition }}</span>
				</div>
			</div>

			<div class="candidate-card__quality" :class="qualityClass">
				<span class="candidate-card__label-with-help">
					{{ t('empleados', 'Data quality') }}
					<HelpHint
						:label="t('empleados', 'About data quality')"
						:text="t('empleados', 'Indicates how complete the information used in this analysis is. It does not evaluate employee performance.')" />
				</span>
				<strong>{{ qualityLabel }}</strong>
			</div>
		</header>

		<section class="candidate-card__scores" :aria-label="t('empleados', 'Estimated fit and occupation')">
			<div class="candidate-card__score">
				<div class="candidate-card__metric-heading">
					<span class="candidate-card__label-with-help">
						{{ t('empleados', 'Estimated fit') }}
						<HelpHint
							:label="t('empleados', 'About estimated fit')"
							:text="t('empleados', 'Comparative indicator based on availability, related experience, previous work with the company, billable history and relative cost. It does not make the final decision.')" />
					</span>
					<strong>{{ adjustmentLabel }}</strong>
				</div>
				<div
					class="candidate-card__progress"
					:aria-label="t('empleados', 'Estimated fit: {value}', { value: adjustmentLabel })">
					<NcProgressBar :value="adjustmentProgress" size="medium" />
				</div>
			</div>

			<div class="candidate-card__score">
				<div class="candidate-card__metric-heading">
					<span class="candidate-card__label-with-help">
						{{ t('empleados', 'Estimated occupation') }}
						<HelpHint
							:label="t('empleados', 'About estimated occupancy')"
							:text="t('empleados', 'Percentage of effective capacity already used by reported hours during the selected period.')" />
					</span>
					<strong>{{ occupationLabel }}</strong>
				</div>
				<div
					v-if="hasOccupation"
					class="candidate-card__progress"
					:aria-label="t('empleados', 'Estimated occupation: {value}', { value: occupationLabel })">
					<NcProgressBar :value="occupationProgress" size="medium" />
				</div>
				<p v-else class="candidate-card__missing">
					{{ t('empleados', 'Not available') }}
				</p>
			</div>
		</section>

		<details class="candidate-card__details">
			<summary>{{ t('empleados', 'Capacity and experience details') }}</summary>
			<div class="candidate-card__groups">
				<section class="candidate-card__group">
					<h4>
						<span class="candidate-card__label-with-help">
							{{ t('empleados', 'Estimated availability') }}
							<HelpHint
								:label="t('empleados', 'About estimated availability')"
								:text="t('empleados', 'Calculated from working days, configured daily hours, approved absences and reported hours. It does not include future project commitments.')" />
						</span>
					</h4>

					<p v-if="!capacityCalculable" class="candidate-card__notice">
						{{ t('empleados', 'Daily reference hours are not configured, so availability cannot be calculated.') }}
					</p>

					<dl class="candidate-card__facts">
						<div>
							<dt>{{ t('empleados', 'Working hours in period') }}</dt>
							<dd>{{ hoursValue(periodHours) }}</dd>
						</div>
						<div>
							<dt>{{ t('empleados', 'Effective capacity') }}</dt>
							<dd>{{ hoursValue(effectiveCapacity) }}</dd>
						</div>
						<div>
							<dt>{{ t('empleados', 'Reported hours in period') }}</dt>
							<dd>{{ hoursValue(reportedHours) }}</dd>
						</div>
						<div>
							<dt>{{ t('empleados', 'Approved absence hours') }}</dt>
							<dd>{{ hoursValue(absenceHours) }}</dd>
						</div>
						<div>
							<dt>{{ t('empleados', 'Estimated availability') }}</dt>
							<dd>{{ hoursValue(estimatedAvailability) }}</dd>
						</div>
						<div v-if="requiredHours > 0">
							<dt>{{ t('empleados', 'Required hours') }}</dt>
							<dd>{{ hoursValue(requiredHours) }}</dd>
						</div>
					</dl>

					<p v-if="requirementExceedsAvailability" class="candidate-card__warning">
						{{ t('empleados', 'Required hours exceed this employee’s estimated availability.') }}
					</p>
				</section>

				<section class="candidate-card__group">
					<h4>{{ t('empleados', 'Related experience') }}</h4>
					<p class="candidate-card__context">
						{{ selectedActivitiesLabel }}
					</p>

					<p v-if="!hasRelatedExperience" class="candidate-card__missing">
						{{ t('empleados', 'No related time report history was found for this employee.') }}
					</p>

					<dl class="candidate-card__facts">
						<div>
							<dt>{{ t('empleados', 'Historical hours in selected activities') }}</dt>
							<dd>{{ hoursValue(experienceHours) }}</dd>
						</div>
						<div>
							<dt>{{ t('empleados', 'Hours in selected activities over the last 12 months') }}</dt>
							<dd>{{ hoursValue(recentExperienceHours) }}</dd>
						</div>
						<div>
							<dt>{{ t('empleados', 'Related time reports') }}</dt>
							<dd>{{ integerValue(experienceReports) }}</dd>
						</div>
						<div>
							<dt>{{ t('empleados', 'Companies with related activity experience') }}</dt>
							<dd>{{ integerValue(activityCompanies) }}</dd>
						</div>
						<div>
							<dt>{{ t('empleados', 'Previous hours with company') }}</dt>
							<dd>{{ hoursValue(companyHours) }}</dd>
						</div>
						<div>
							<dt>{{ t('empleados', 'Previous billable hours with company') }}</dt>
							<dd>{{ hoursValue(companyBillableHours) }}</dd>
						</div>
						<div>
							<dt>{{ t('empleados', 'Last report with company') }}</dt>
							<dd>{{ dateValue(lastCompanyReport) }}</dd>
						</div>
					</dl>
				</section>

				<section class="candidate-card__group">
					<h4>{{ t('empleados', 'Cost and work history') }}</h4>
					<dl class="candidate-card__facts">
						<div>
							<dt>{{ t('empleados', 'Hourly cost') }}</dt>
							<dd>{{ moneyValue(hourlyCost) }}</dd>
						</div>
						<div>
							<dt>{{ t('empleados', 'Historical billable percentage') }}</dt>
							<dd>{{ percentValue(historicalBillablePercentage) }}</dd>
						</div>
						<div>
							<dt>{{ t('empleados', 'Companies served') }}</dt>
							<dd>{{ integerValue(companiesServed) }}</dd>
						</div>
						<div>
							<dt>{{ t('empleados', 'Activities performed with company') }}</dt>
							<dd>{{ integerValue(companyActivities) }}</dd>
						</div>
					</dl>
				</section>
			</div>
		</details>

		<details class="candidate-card__details">
			<summary>{{ t('empleados', 'Estimated fit breakdown') }}</summary>
			<section class="candidate-card__breakdown">
				<dl class="candidate-card__breakdown-grid">
					<div v-for="factor in adjustmentBreakdown" :key="factor.key">
						<dt>{{ factor.label }}</dt>
						<dd>{{ factor.value }}</dd>
					</div>
				</dl>
			</section>
		</details>

		<details v-if="translatedStrengths.length" class="candidate-card__details">
			<summary>{{ t('empleados', 'Supporting factors') }}</summary>
			<section class="candidate-card__signal candidate-card__signal--strength">
				<ul>
					<li v-for="strength in translatedStrengths" :key="strength">
						{{ strength }}
					</li>
				</ul>
			</section>
		</details>

		<details v-if="translatedRisks.length" class="candidate-card__details" open>
			<summary>{{ t('empleados', 'Planning alerts') }}</summary>
			<section class="candidate-card__signal candidate-card__signal--risk">
				<ul>
					<li v-for="risk in translatedRisks" :key="risk">
						{{ risk }}
					</li>
				</ul>
			</section>
		</details>

		<footer class="candidate-card__footer">
			<p>{{ t('empleados', 'This analysis supports planning and does not make the final staffing decision.') }}</p>
			<NcButton type="primary" :disabled="alreadySelected" @click="addCandidate">
				{{ alreadySelected ? t('empleados', 'Already added') : t('empleados', 'Add to scenario') }}
			</NcButton>
		</footer>
	</article>
</template>

<script>
import {
	NcAvatar,
	NcButton,
	NcProgressBar,
} from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

import HelpHint from '../Helpers/HelpHint.vue'

export default {
	name: 'CostosCandidato',
	components: {
		HelpHint,
		NcAvatar,
		NcButton,
		NcProgressBar,
	},
	props: {
		candidate: {
			type: Object,
			required: true,
		},
		requiredHours: {
			type: Number,
			default: 0,
		},
		selectedActivityIds: {
			type: Array,
			default: () => [],
		},
		alreadySelected: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		experience() {
			return this.candidate.experiencia || {}
		},
		candidateUid() {
			return String(this.firstValue(this.candidate, ['uid', 'id_user', 'Id_user']) || '')
		},
		candidateName() {
			return String(this.firstValue(this.candidate, ['displayname', 'display_name', 'nombre']) || this.candidateUid || t('empleados', 'Employee'))
		},
		candidateArea() {
			return String(this.firstValue(this.candidate, ['area', 'departamento']) || '')
		},
		candidatePosition() {
			return String(this.firstValue(this.candidate, ['puesto', 'posicion']) || '')
		},
		capacityCalculable() {
			return this.candidate.capacidad_calculable === true
		},
		periodHours() {
			return this.firstValue(this.candidate, ['horas_periodo', 'horas_laborales_estimadas_periodo'])
		},
		effectiveCapacity() {
			return this.firstValue(this.candidate, ['capacidad_efectiva', 'horas_laborales_estimadas'])
		},
		reportedHours() {
			return this.firstValue(this.candidate, ['horas_reportadas_periodo', 'horas_reportadas'])
		},
		absenceHours() {
			return this.firstValue(this.candidate, ['horas_ausencia', 'horas_ausencia_aprobada'])
		},
		estimatedAvailability() {
			return this.firstValue(this.candidate, ['disponibilidad_estimada'])
		},
		hasOccupation() {
			return this.hasNumber(this.candidate.ocupacion_estimada)
		},
		occupationLabel() {
			return this.percentValue(this.candidate.ocupacion_estimada)
		},
		occupationProgress() {
			return this.progressValue(this.candidate.ocupacion_estimada)
		},
		adjustmentLabel() {
			return this.percentValue(this.candidate.ajuste_estimado)
		},
		adjustmentProgress() {
			return this.progressValue(this.candidate.ajuste_estimado)
		},
		qualityLabel() {
			const labels = {
				alta: t('empleados', 'High'),
				high: t('empleados', 'High'),
				media: t('empleados', 'Medium'),
				medium: t('empleados', 'Medium'),
				baja: t('empleados', 'Low'),
				low: t('empleados', 'Low'),
			}

			return labels[String(this.candidate.calidad_datos || '').toLowerCase()]
				|| t('empleados', 'Not available')
		},
		qualityClass() {
			const quality = String(this.candidate.calidad_datos || '').toLowerCase()
			return {
				'candidate-card__quality--high': ['alta', 'high'].includes(quality),
				'candidate-card__quality--medium': ['media', 'medium'].includes(quality),
				'candidate-card__quality--low': ['baja', 'low'].includes(quality),
			}
		},
		selectedActivitiesLabel() {
			const count = this.selectedActivityIds.length
			return t('empleados', '{count} selected activities', { count })
		},
		experienceHours() {
			return this.firstValue(this.experience, ['horas_actividades', 'horas_historicas_actividades'])
		},
		recentExperienceHours() {
			return this.firstValue(this.experience, ['horas_actividades_12_meses', 'horas_recientes_actividades'])
		},
		experienceReports() {
			return this.firstValue(this.experience, ['registros_actividades', 'cantidad_registros'])
		},
		activityCompanies() {
			return this.firstValue(this.experience, ['empresas_actividades', 'empresas_con_actividades'])
		},
		companyHours() {
			return this.firstValue(this.experience, ['horas_empresa'])
		},
		companyBillableHours() {
			return this.firstValue(this.experience, ['horas_cargables_empresa'])
		},
		lastCompanyReport() {
			return this.firstValue(this.experience, ['ultimo_reporte_empresa'])
		},
		companiesServed() {
			return this.firstValue(this.experience, ['empresas_atendidas', 'empresas_diferentes'])
		},
		companyActivities() {
			return this.firstValue(this.experience, ['actividades_empresa', 'actividades_diferentes_empresa'])
		},
		hourlyCost() {
			return this.firstValue(this.candidate, ['costo_hora', 'sueldo_hora'])
		},
		historicalBillablePercentage() {
			return this.firstValue(this.candidate, ['porcentaje_cargable_historico'])
		},
		hasRelatedExperience() {
			return [
				this.experienceHours,
				this.recentExperienceHours,
				this.experienceReports,
				this.companyHours,
				this.companyBillableHours,
			].some(value => this.hasNumber(value) && Number(value) > 0)
				|| Boolean(this.lastCompanyReport)
		},
		requirementExceedsAvailability() {
			return this.capacityCalculable
				&& this.hasNumber(this.estimatedAvailability)
				&& this.hasNumber(this.requiredHours)
				&& Number(this.requiredHours) > Number(this.estimatedAvailability)
		},
		adjustmentBreakdown() {
			const breakdown = this.candidate.desglose_ajuste || {}
			const factors = [
				{ key: 'disponibilidad', label: t('empleados', 'Estimated availability') },
				{ key: 'experiencia_actividades', label: t('empleados', 'Experience in activities') },
				{ key: 'experiencia_empresa', label: t('empleados', 'Experience with company') },
				{ key: 'cargabilidad', label: t('empleados', 'Historical billable percentage') },
				{ key: 'costo', label: t('empleados', 'Relative cost') },
			]

			return factors.map(factor => ({
				...factor,
				value: this.hasNumber(breakdown[factor.key])
					? t('empleados', '{value} points', { value: this.number(breakdown[factor.key]) })
					: t('empleados', 'Not available'),
			}))
		},
		translatedStrengths() {
			return this.translateSignals(this.candidate.fortalezas, this.strengthLabels())
		},
		translatedRisks() {
			const risks = Array.isArray(this.candidate.riesgos)
				? [...this.candidate.riesgos]
				: []

			if (this.requirementExceedsAvailability) {
				risks.push('horas_superan_disponibilidad')
			}

			return this.translateSignals(risks, this.riskLabels())
		},
	},
	methods: {
		t,
		firstValue(source, keys) {
			for (const key of keys) {
				if (source[key] !== null && source[key] !== undefined) {
					return source[key]
				}
			}

			return null
		},
		hasNumber(value) {
			return value !== null
				&& value !== undefined
				&& value !== ''
				&& Number.isFinite(Number(value))
		},
		number(value) {
			return new Intl.NumberFormat('es-MX', {
				maximumFractionDigits: 2,
			}).format(Number(value))
		},
		hoursValue(value) {
			if (!this.hasNumber(value)) {
				return t('empleados', 'Not available')
			}

			return t('empleados', '{value} hours', { value: this.number(value) })
		},
		integerValue(value) {
			if (!this.hasNumber(value)) {
				return t('empleados', 'Not available')
			}

			return new Intl.NumberFormat('es-MX', {
				maximumFractionDigits: 0,
			}).format(Number(value))
		},
		percentValue(value) {
			if (!this.hasNumber(value)) {
				return t('empleados', 'Not available')
			}

			return new Intl.NumberFormat('es-MX', {
				style: 'percent',
				maximumFractionDigits: 2,
			}).format(Number(value) / 100)
		},
		moneyValue(value) {
			if (!this.hasNumber(value)) {
				return t('empleados', 'Not configured')
			}

			return new Intl.NumberFormat('es-MX', {
				style: 'currency',
				currency: 'MXN',
				maximumFractionDigits: 2,
			}).format(Number(value))
		},
		dateValue(value) {
			if (!value) {
				return t('empleados', 'Not available')
			}

			const date = new Date(`${String(value).slice(0, 10)}T00:00:00`)
			if (Number.isNaN(date.getTime())) {
				return t('empleados', 'Not available')
			}

			return new Intl.DateTimeFormat('es-MX', {
				year: 'numeric',
				month: 'short',
				day: 'numeric',
			}).format(date)
		},
		progressValue(value) {
			if (!this.hasNumber(value)) {
				return 0
			}

			return Math.max(0, Math.min(100, Number(value)))
		},
		signalKey(signal) {
			if (typeof signal === 'string') {
				return signal
			}

			if (signal && typeof signal === 'object') {
				return signal.key || signal.clave || signal.code || ''
			}

			return ''
		},
		translateSignals(signals, labels) {
			if (!Array.isArray(signals)) {
				return []
			}

			return [...new Set(signals
				.map(signal => labels[this.signalKey(signal)])
				.filter(Boolean))]
		},
		strengthLabels() {
			return {
				experiencia_reciente_actividades: t('empleados', 'Has recent experience in the selected activities.'),
				experiencia_actividades: t('empleados', 'Has recorded experience in the selected activities.'),
				experiencia_empresa: t('empleados', 'Has previously worked with this company.'),
				capacidad_suficiente: t('empleados', 'Has sufficient estimated capacity for the requirement.'),
				disponibilidad_suficiente: t('empleados', 'Has sufficient estimated availability for the requirement.'),
				cargabilidad_alta: t('empleados', 'Has a strong historical billable percentage.'),
				costo_relativo_favorable: t('empleados', 'Has a favorable relative cost within the visible candidate set.'),
			}
		},
		riskLabels() {
			return {
				horas_superan_disponibilidad: t('empleados', 'Required hours exceed estimated availability.'),
				ocupacion_supera_100: t('empleados', 'Resulting estimated occupation would exceed 100%.'),
				ocupacion_supera_90: t('empleados', 'Resulting estimated occupation would exceed 90%.'),
				sin_experiencia_actividades: t('empleados', 'No experience is recorded in the selected activities.'),
				sin_experiencia_empresa: t('empleados', 'No previous work with this company is recorded.'),
				sin_costo_hora: t('empleados', 'Hourly cost is not configured.'),
				capacidad_no_calculable: t('empleados', 'Capacity cannot be calculated with the current configuration.'),
				datos_incompletos: t('empleados', 'Some information needed for the analysis is incomplete.'),
			}
		},
		addCandidate() {
			if (!this.alreadySelected) {
				this.$emit('add', this.candidate)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.candidate-card {
	display: flex;
	flex-direction: column;
	gap: 20px;
	padding: 20px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.candidate-card__header {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr) auto;
	align-items: center;
	gap: 14px;
}

.candidate-card__identity {
	min-width: 0;

	h3,
	p {
		margin: 0;
	}

	h3 {
		overflow: hidden;
		font-size: 1.15rem;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	p {
		overflow: hidden;
		margin-top: 2px;
		color: var(--color-text-maxcontrast);
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}

.candidate-card__tags {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin-top: 8px;

	span {
		padding: 2px 8px;
		border: 1px solid var(--color-border);
		border-radius: 999px;
		background: var(--color-background-hover);
		font-size: 0.82rem;
	}
}

.candidate-card__quality {
	display: flex;
	min-width: 110px;
	flex-direction: column;
	align-items: flex-end;
	gap: 3px;
	padding: 8px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);

	strong {
		font-size: 0.95rem;
	}
}

.candidate-card__quality--high {
	border-color: var(--color-success);
}

.candidate-card__quality--medium {
	border-color: var(--color-warning);
}

.candidate-card__quality--low {
	border-color: var(--color-error);
}

.candidate-card__label-with-help {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

.candidate-card__scores {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 16px;
}

.candidate-card__score,
.candidate-card__group,
.candidate-card__breakdown,
.candidate-card__signal {
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.candidate-card__metric-heading {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 8px;

	span {
		color: var(--color-text-maxcontrast);
	}
}

.candidate-card__progress {
	overflow: hidden;
	border-radius: var(--border-radius-large);
}

.candidate-card__groups {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 14px;
}

.candidate-card__group,
.candidate-card__breakdown,
.candidate-card__signal {
	h4 {
		margin: 0 0 12px;
		font-size: 1rem;
	}
}

.candidate-card__context,
.candidate-card__missing,
.candidate-card__notice,
.candidate-card__warning {
	margin: 0 0 12px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}

.candidate-card__notice,
.candidate-card__warning {
	padding: 9px 10px;
	border-inline-start: 3px solid var(--color-warning);
	background: var(--color-background-hover);
	color: var(--color-main-text);
}

.candidate-card__facts,
.candidate-card__breakdown-grid {
	display: grid;
	gap: 9px;
	margin: 0;

	div {
		display: flex;
		justify-content: space-between;
		gap: 12px;
	}

	dt {
		color: var(--color-text-maxcontrast);
	}

	dd {
		margin: 0;
		font-weight: 600;
		text-align: end;
	}
}

.candidate-card__breakdown-grid {
	grid-template-columns: repeat(5, minmax(0, 1fr));

	div {
		flex-direction: column;
		justify-content: flex-start;
		gap: 4px;
	}

	dd {
		text-align: start;
	}
}

.candidate-card__signals {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 14px;
}

.candidate-card__signal {
	border-inline-start-width: 4px;

	ul {
		margin: 0;
		padding-inline-start: 20px;
	}

	li + li {
		margin-top: 6px;
	}
}

.candidate-card__signal--strength {
	border-inline-start-color: var(--color-success);
}

.candidate-card__signal--risk {
	border-inline-start-color: var(--color-warning);
}

.candidate-card__details {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);

	> summary {
		min-height: 44px;
		padding: 11px 14px;
		cursor: pointer;
		font-weight: 600;
	}

	&[open] > summary {
		border-bottom: 1px solid var(--color-border);
	}

	> .candidate-card__groups {
		padding: 14px;
	}

	> .candidate-card__breakdown,
	> .candidate-card__signal {
		border: 0;
		border-radius: 0 0 var(--border-radius-large) var(--border-radius-large);
	}
}

.candidate-card__footer {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;

	p {
		max-width: 680px;
		margin: 0;
		color: var(--color-text-maxcontrast);
		font-size: 0.9rem;
	}
}

@media (max-width: 1100px) {
	.candidate-card__groups {
		grid-template-columns: 1fr;
	}

	.candidate-card__breakdown-grid {
		grid-template-columns: repeat(3, minmax(0, 1fr));
	}
}

@media (max-width: 700px) {
	.candidate-card {
		padding: 14px;
	}

	.candidate-card__header {
		grid-template-columns: auto minmax(0, 1fr);
	}

	.candidate-card__quality {
		grid-column: 1 / -1;
		align-items: flex-start;
	}

	.candidate-card__scores,
	.candidate-card__signals {
		grid-template-columns: 1fr;
	}

	.candidate-card__breakdown-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.candidate-card__footer {
		align-items: stretch;
		flex-direction: column;
	}
}
</style>
