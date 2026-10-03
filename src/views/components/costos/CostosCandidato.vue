<template>
	<article class="candidate-card">
		<header class="candidate-card__header">
			<NcAvatar
				:user="candidateUid"
				:display-name="candidateName"
				:size="48"
				disable-menu />

			<div class="candidate-card__identity">
				<h3 :title="candidateName">
					{{ candidateName }}
				</h3>
				<p v-if="candidateUid" :title="candidateUid">
					{{ candidateUid }}
				</p>
				<div v-if="candidateArea || candidatePosition" class="candidate-card__tags">
					<span v-if="candidateArea" :title="candidateArea">{{ candidateArea }}</span>
					<span v-if="candidatePosition" :title="candidatePosition">{{ candidatePosition }}</span>
				</div>
			</div>

			<span
				class="candidate-card__quality"
				:class="qualityClass"
				:title="t('empleados', 'Data quality: {quality}', { quality: qualityLabel })">
				{{ qualityLabel }}
			</span>
		</header>

		<dl class="candidate-card__metrics">
			<div class="candidate-card__metric">
				<dt class="candidate-card__metric-label">
					{{ t('empleados', 'Estimated fit') }}
				</dt>
				<dd class="candidate-card__metric-value" :title="adjustmentLabel">
					{{ adjustmentLabel }}
				</dd>
			</div>
			<div class="candidate-card__metric">
				<dt class="candidate-card__metric-label">
					{{ t('empleados', 'Estimated availability') }}
				</dt>
				<dd class="candidate-card__metric-value" :title="hoursValue(estimatedAvailability)">
					{{ hoursValue(estimatedAvailability) }}
				</dd>
			</div>
			<div class="candidate-card__metric">
				<dt class="candidate-card__metric-label">
					{{ t('empleados', 'Related experience') }}
				</dt>
				<dd
					class="candidate-card__metric-value candidate-card__metric-value--experience"
					:title="relatedExperienceLabel">
					{{ relatedExperienceLabel }}
				</dd>
			</div>
			<div class="candidate-card__metric">
				<dt class="candidate-card__metric-label">
					{{ t('empleados', 'Hourly cost') }}
				</dt>
				<dd class="candidate-card__metric-value" :title="moneyValue(hourlyCost)">
					{{ moneyValue(hourlyCost) }}
				</dd>
			</div>
		</dl>

		<footer
			class="candidate-card__footer"
			:class="{ 'candidate-card__footer--without-alerts': !translatedRisks.length }">
			<div v-if="translatedRisks.length" class="candidate-card__alerts">
				<span class="candidate-card__alert-count">
					{{ t('empleados', '{count} alerts', { count: translatedRisks.length }) }}
				</span>
				<p :title="translatedRisks[0]">
					{{ translatedRisks[0] }}
				</p>
			</div>
			<NcButton
				type="tertiary"
				:aria-label="t('empleados', 'View details for {employee}', { employee: candidateName })"
				@click="$emit('view-details', candidate)">
				{{ t('empleados', 'View details') }}
			</NcButton>
			<NcButton
				type="primary"
				:disabled="addDisabled"
				:aria-label="addButtonLabel"
				@click="addCandidate">
				{{ addButtonLabel }}
			</NcButton>
		</footer>

		<p v-if="projectFullyAssigned && !alreadySelected" class="candidate-card__disabled-reason">
			{{ t('empleados', 'Project hours are fully assigned.') }}
		</p>
	</article>
</template>

<script>
import {
	NcAvatar,
	NcButton,
} from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'CostosCandidato',
	components: {
		NcAvatar,
		NcButton,
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
		alreadySelected: {
			type: Boolean,
			default: false,
		},
		projectFullyAssigned: {
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
			return String(this.firstValue(this.candidate, ['displayname', 'display_name', 'nombre'])
				|| this.candidateUid
				|| t('empleados', 'Employee'))
		},
		candidateArea() {
			return String(this.firstValue(this.candidate, ['area', 'departamento']) || '')
		},
		candidatePosition() {
			return String(this.firstValue(this.candidate, ['puesto', 'posicion']) || '')
		},
		estimatedAvailability() {
			return this.firstValue(this.candidate, ['disponibilidad_estimada'])
		},
		adjustmentLabel() {
			return this.percentValue(this.candidate.ajuste_estimado)
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
		recentExperienceHours() {
			return this.firstValue(this.experience, ['horas_actividades_12_meses', 'horas_recientes_actividades'])
		},
		relatedExperienceLabel() {
			if (!this.hasNumber(this.recentExperienceHours)) {
				return t('empleados', 'Not available')
			}
			return Number(this.recentExperienceHours) > 0
				? t('empleados', '{hours} recent hours', { hours: this.number(this.recentExperienceHours) })
				: t('empleados', 'No related experience')
		},
		hourlyCost() {
			return this.firstValue(this.candidate, ['costo_hora', 'sueldo_hora'])
		},
		requirementExceedsAvailability() {
			return this.candidate.capacidad_calculable === true
				&& this.hasNumber(this.estimatedAvailability)
				&& this.hasNumber(this.requiredHours)
				&& Number(this.requiredHours) > Number(this.estimatedAvailability)
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
		addDisabled() {
			return this.alreadySelected || this.projectFullyAssigned
		},
		addButtonLabel() {
			if (this.alreadySelected) {
				return t('empleados', 'Already added')
			}
			if (this.projectFullyAssigned) {
				return t('empleados', 'Project fully assigned')
			}
			return t('empleados', 'Add to scenario')
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
			return this.hasNumber(value)
				? t('empleados', '{value} hours', { value: this.number(value) })
				: t('empleados', 'Not available')
		},
		percentValue(value) {
			return this.hasNumber(value)
				? new Intl.NumberFormat('es-MX', {
					style: 'percent',
					maximumFractionDigits: 2,
				}).format(Number(value) / 100)
				: t('empleados', 'Not available')
		},
		moneyValue(value) {
			return this.hasNumber(value)
				? new Intl.NumberFormat('es-MX', {
					style: 'currency',
					currency: 'MXN',
					maximumFractionDigits: 2,
				}).format(Number(value))
				: t('empleados', 'Not configured')
		},
		signalKey(signal) {
			if (typeof signal === 'string') {
				return signal
			}
			return signal && typeof signal === 'object'
				? signal.key || signal.clave || signal.code || ''
				: ''
		},
		translateSignals(signals, labels) {
			return [...new Set((Array.isArray(signals) ? signals : [])
				.map(signal => labels[this.signalKey(signal)])
				.filter(Boolean))]
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
			if (!this.addDisabled) {
				this.$emit('add', this.candidate)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.candidate-card {
	display: flex;
	min-width: 0;
	flex-direction: column;
	gap: 10px;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
	container-type: inline-size;
}

.candidate-card__header {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr) auto;
	align-items: center;
	gap: 10px;
}

.candidate-card__identity {
	min-width: 0;

	h3,
	p {
		overflow: hidden;
		margin: 0;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	h3 {
		font-size: 1rem;
	}

	p {
		color: var(--color-text-maxcontrast);
		font-size: 0.82rem;
	}
}

.candidate-card__tags {
	display: flex;
	min-width: 0;
	gap: 4px;
	margin-top: 4px;

	span {
		overflow: hidden;
		max-width: 50%;
		padding: 1px 6px;
		border: 1px solid var(--color-border);
		border-radius: 999px;
		background: var(--color-background-hover);
		font-size: 0.72rem;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}

.candidate-card__quality,
.candidate-card__alert-count {
	padding: 2px 7px;
	border: 1px solid var(--color-border);
	border-radius: 999px;
	font-size: 0.75rem;
	font-weight: 600;
	white-space: nowrap;
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

.candidate-card__metrics {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 0;
	margin: 0;
	border-block: 1px solid var(--color-border);
}

.candidate-card__metric {
	min-width: 0;
	min-height: 68px;
	padding: 9px 8px;

	&:nth-child(odd) {
		border-inline-end: 1px solid var(--color-border);
	}

	&:nth-child(-n + 2) {
		border-block-end: 1px solid var(--color-border);
	}
}

.candidate-card__metric-label {
	display: block;
	min-width: 0;
	margin-bottom: 5px;
	color: var(--color-text-maxcontrast);
	font-size: 0.78rem;
	line-height: 1.25;
}

.candidate-card__metric-value {
	display: block;
	min-width: 0;
	margin: 0;
	color: var(--color-main-text);
	font-size: 0.9rem;
	font-variant-numeric: tabular-nums;
	font-weight: 700;
	line-height: 1.3;
	overflow-wrap: normal;
	word-break: normal;
}

.candidate-card__metric-value--experience {
	display: -webkit-box;
	overflow: hidden;
	-webkit-box-orient: vertical;
	-webkit-line-clamp: 2;
}

.candidate-card__alerts {
	display: flex;
	min-width: 0;
	align-items: center;
	gap: 8px;

	p {
		overflow: hidden;
		min-width: 0;
		margin: 0;
		color: var(--color-text-maxcontrast);
		font-size: 0.78rem;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}

.candidate-card__alert-count {
	flex: 0 0 auto;
	border-color: var(--color-warning);
}

.candidate-card__footer {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto auto;
	align-items: center;
	gap: 8px;
	margin-top: auto;
}

.candidate-card__footer--without-alerts {
	grid-template-columns: 1fr 1fr;
}

.candidate-card__disabled-reason {
	margin: -4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.76rem;
	text-align: end;
}

@media (max-width: 520px) {
	.candidate-card__header {
		grid-template-columns: auto minmax(0, 1fr);
	}

	.candidate-card__quality {
		grid-column: 2;
		justify-self: start;
	}

	.candidate-card__footer {
		align-items: stretch;
	}
}

@container (max-width: 380px) {
	.candidate-card__footer {
		grid-template-columns: 1fr 1fr;
	}

	.candidate-card__alerts {
		grid-column: 1 / -1;
	}
}
</style>
