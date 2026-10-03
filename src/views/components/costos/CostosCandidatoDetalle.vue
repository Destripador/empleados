<template>
	<div class="candidate-detail">
		<header class="candidate-detail__header">
			<div class="candidate-detail__avatar">
				<NcAvatar
					:user="candidateUid"
					:display-name="candidateName"
					:size="64"
					disable-menu />
			</div>
			<div class="candidate-detail__identity">
				<h2>{{ candidateName }}</h2>
				<p>{{ candidateUid }}</p>
				<div class="candidate-detail__tags">
					<span v-if="candidateArea">{{ candidateArea }}</span>
					<span v-if="candidatePosition">{{ candidatePosition }}</span>
				</div>
			</div>
			<dl class="candidate-detail__headline">
				<div>
					<dt>
						{{ t('empleados', 'Estimated fit') }}
						<HelpHint
							:label="t('empleados', 'About estimated fit')"
							:text="t('empleados', 'Comparative indicator based on availability, related experience, previous work with the company, billable history and relative cost. It does not make the final decision.')" />
					</dt>
					<dd>{{ percentValue(candidate.ajuste_estimado) }}</dd>
				</div>
				<div>
					<dt>
						{{ t('empleados', 'Data quality') }}
						<HelpHint
							:label="t('empleados', 'About data quality')"
							:text="t('empleados', 'Indicates how complete the information used in this analysis is. It does not evaluate employee performance.')" />
					</dt>
					<dd>
						<span class="candidate-detail__quality" :class="qualityClass">{{ qualityLabel }}</span>
					</dd>
				</div>
			</dl>
		</header>

		<div class="candidate-detail__sections">
			<section class="candidate-detail__section">
				<h3>
					{{ t('empleados', 'Capacity and availability') }}
					<HelpHint
						:label="t('empleados', 'About estimated availability')"
						:text="t('empleados', 'Calculated from working days, configured daily hours, approved absences and reported hours. It does not include future project commitments.')" />
				</h3>
				<p v-if="candidate.capacidad_calculable !== true" class="candidate-detail__notice">
					{{ t('empleados', 'Daily reference hours are not configured, so availability cannot be calculated.') }}
				</p>
				<dl class="candidate-detail__facts">
					<div v-for="fact in capacityFacts" :key="fact.label">
						<dt>{{ fact.label }}</dt>
						<dd :class="{ 'candidate-detail__long-value': fact.long }">
							{{ fact.value }}
						</dd>
					</div>
				</dl>
			</section>

			<section class="candidate-detail__section">
				<h3>{{ t('empleados', 'Related experience') }}</h3>
				<p class="candidate-detail__context">
					{{ t('empleados', '{count} selected activities', { count: selectedActivityIds.length }) }}
				</p>
				<p v-if="!hasRelatedExperience" class="candidate-detail__empty">
					{{ t('empleados', 'No related experience') }}
				</p>
				<dl class="candidate-detail__facts">
					<div v-for="fact in experienceFacts" :key="fact.label">
						<dt>{{ fact.label }}</dt>
						<dd :class="{ 'candidate-detail__long-value': fact.long }">
							{{ fact.value }}
						</dd>
					</div>
				</dl>
			</section>

			<section class="candidate-detail__section">
				<h3>
					{{ t('empleados', 'Cost and billable history') }}
					<HelpHint
						:label="t('empleados', 'About estimated cost')"
						:text="t('empleados', 'Hourly cost is used only to estimate the temporary scenario cost.')" />
				</h3>
				<dl class="candidate-detail__facts">
					<div v-for="fact in costFacts" :key="fact.label">
						<dt>{{ fact.label }}</dt>
						<dd>{{ fact.value }}</dd>
					</div>
				</dl>
			</section>

			<section class="candidate-detail__section">
				<h3>{{ t('empleados', 'Estimated fit breakdown') }}</h3>
				<dl class="candidate-detail__facts">
					<div v-for="factor in adjustmentBreakdown" :key="factor.key">
						<dt>{{ factor.label }}</dt>
						<dd>{{ factor.value }}</dd>
					</div>
				</dl>
			</section>

			<section class="candidate-detail__section candidate-detail__section--strengths">
				<h3>{{ t('empleados', 'Supporting factors') }}</h3>
				<ul v-if="translatedStrengths.length">
					<li v-for="strength in translatedStrengths" :key="strength">
						{{ strength }}
					</li>
				</ul>
				<p v-else class="candidate-detail__empty">
					{{ t('empleados', 'No supporting factors were identified.') }}
				</p>
			</section>

			<section class="candidate-detail__section candidate-detail__section--alerts">
				<h3>{{ t('empleados', 'Planning alerts') }}</h3>
				<ul v-if="translatedRisks.length">
					<li v-for="risk in translatedRisks" :key="risk">
						{{ risk }}
					</li>
				</ul>
				<p v-else class="candidate-detail__empty">
					{{ t('empleados', 'No alerts') }}
				</p>
			</section>
		</div>

		<footer class="candidate-detail__actions">
			<NcButton type="tertiary" @click="$emit('close')">
				{{ t('empleados', 'Close') }}
			</NcButton>
			<NcButton
				type="primary"
				:disabled="addDisabled"
				:aria-label="addButtonLabel"
				@click="$emit('add', candidate)">
				{{ addButtonLabel }}
			</NcButton>
		</footer>
		<p v-if="projectFullyAssigned && !alreadySelected" class="candidate-detail__disabled-reason">
			{{ t('empleados', 'Project hours are fully assigned. You can still review candidate details.') }}
		</p>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import {
	NcAvatar,
	NcButton,
} from '@nextcloud/vue'

import HelpHint from '../Helpers/HelpHint.vue'

export default {
	name: 'CostosCandidatoDetalle',
	components: {
		HelpHint,
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
		selectedActivityIds: {
			type: Array,
			default: () => [],
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
				'candidate-detail__quality--high': ['alta', 'high'].includes(quality),
				'candidate-detail__quality--medium': ['media', 'medium'].includes(quality),
				'candidate-detail__quality--low': ['baja', 'low'].includes(quality),
			}
		},
		capacityFacts() {
			return [
				{
					label: t('empleados', 'Working hours in period'),
					value: this.hoursValue(this.firstValue(this.candidate, ['horas_periodo', 'horas_laborales_estimadas_periodo'])),
				},
				{
					label: t('empleados', 'Effective capacity'),
					value: this.hoursValue(this.firstValue(this.candidate, ['capacidad_efectiva', 'horas_laborales_estimadas'])),
				},
				{
					label: t('empleados', 'Reported hours in period'),
					value: this.hoursValue(this.firstValue(this.candidate, ['horas_reportadas_periodo', 'horas_reportadas'])),
				},
				{
					label: t('empleados', 'Approved absence hours'),
					value: this.hoursValue(this.firstValue(this.candidate, ['horas_ausencia', 'horas_ausencia_aprobada'])),
				},
				{
					label: t('empleados', 'Estimated availability'),
					value: this.hoursValue(this.candidate.disponibilidad_estimada),
				},
				{
					label: t('empleados', 'Required hours'),
					value: this.hoursValue(this.requiredHours),
				},
			]
		},
		experienceFacts() {
			return [
				{
					label: t('empleados', 'Historical hours in selected activities'),
					value: this.hoursValue(this.firstValue(this.experience, ['horas_actividades', 'horas_historicas_actividades'])),
				},
				{
					label: t('empleados', 'Hours in selected activities over the last 12 months'),
					value: this.hoursValue(this.firstValue(this.experience, ['horas_actividades_12_meses', 'horas_recientes_actividades'])),
				},
				{
					label: t('empleados', 'Related time reports'),
					value: this.integerValue(this.firstValue(this.experience, ['registros_actividades', 'cantidad_registros'])),
				},
				{
					label: t('empleados', 'Companies with related activity experience'),
					value: this.integerValue(this.firstValue(this.experience, ['empresas_actividades', 'empresas_con_actividades'])),
				},
				{
					label: t('empleados', 'Previous hours with company'),
					value: this.hoursValue(this.experience.horas_empresa),
				},
				{
					label: t('empleados', 'Previous billable hours with company'),
					value: this.hoursValue(this.experience.horas_cargables_empresa),
				},
				{
					label: t('empleados', 'Last report with company'),
					value: this.dateValue(this.experience.ultimo_reporte_empresa),
					long: true,
				},
			]
		},
		costFacts() {
			return [
				{
					label: t('empleados', 'Hourly cost'),
					value: this.moneyValue(this.firstValue(this.candidate, ['costo_hora', 'sueldo_hora'])),
				},
				{
					label: t('empleados', 'Historical billable percentage'),
					value: this.percentValue(this.candidate.porcentaje_cargable_historico),
				},
				{
					label: t('empleados', 'Companies served'),
					value: this.integerValue(this.firstValue(this.experience, ['empresas_atendidas', 'empresas_diferentes'])),
				},
				{
					label: t('empleados', 'Activities performed with company'),
					value: this.integerValue(this.firstValue(this.experience, ['actividades_empresa', 'actividades_diferentes_empresa'])),
				},
			]
		},
		hasRelatedExperience() {
			return [
				this.firstValue(this.experience, ['horas_actividades', 'horas_historicas_actividades']),
				this.firstValue(this.experience, ['horas_actividades_12_meses', 'horas_recientes_actividades']),
				this.firstValue(this.experience, ['registros_actividades', 'cantidad_registros']),
				this.experience.horas_empresa,
				this.experience.horas_cargables_empresa,
			].some(value => this.hasNumber(value) && Number(value) > 0)
				|| Boolean(this.experience.ultimo_reporte_empresa)
		},
		adjustmentBreakdown() {
			const breakdown = this.candidate.desglose_ajuste || {}
			return [
				{ key: 'disponibilidad', label: t('empleados', 'Estimated availability') },
				{ key: 'experiencia_actividades', label: t('empleados', 'Experience in activities') },
				{ key: 'experiencia_empresa', label: t('empleados', 'Experience with company') },
				{ key: 'cargabilidad', label: t('empleados', 'Historical billable percentage') },
				{ key: 'costo', label: t('empleados', 'Relative cost') },
			].map(factor => ({
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
			const risks = Array.isArray(this.candidate.riesgos) ? [...this.candidate.riesgos] : []
			if (
				this.candidate.capacidad_calculable === true
				&& this.hasNumber(this.candidate.disponibilidad_estimada)
				&& Number(this.requiredHours) > Number(this.candidate.disponibilidad_estimada)
			) {
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
		integerValue(value) {
			return this.hasNumber(value)
				? new Intl.NumberFormat('es-MX', { maximumFractionDigits: 0 }).format(Number(value))
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
		dateValue(value) {
			if (!value) {
				return t('empleados', 'Not available')
			}
			const date = new Date(`${String(value).slice(0, 10)}T00:00:00`)
			return Number.isNaN(date.getTime())
				? t('empleados', 'Not available')
				: new Intl.DateTimeFormat('es-MX', {
					year: 'numeric',
					month: 'short',
					day: 'numeric',
				}).format(date)
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
	},
}
</script>

<style scoped lang="scss">
.candidate-detail {
	display: flex;
	box-sizing: border-box;
	width: min(1000px, calc(100vw - 48px));
	min-width: 0;
	max-width: 100%;
	max-height: calc(100vh - 120px);
	flex-direction: column;
	gap: 18px;
	padding: 22px;
	overflow-x: hidden;
	overflow-y: auto;
	color: var(--color-main-text);
	scrollbar-gutter: stable;

	> * {
		min-width: 0;
		max-width: 100%;
	}
}

.candidate-detail__header {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr) auto;
	align-items: center;
	gap: 16px;
	min-width: 0;
	max-width: 100%;
}

.candidate-detail__avatar {
	display: flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	min-width: 64px;
	min-height: 64px;
}

.candidate-detail__identity {
	min-width: 0;

	h2,
	p {
		margin: 0;
		overflow-wrap: break-word;
		word-break: normal;
	}

	p {
		color: var(--color-text-maxcontrast);
	}
}

.candidate-detail__tags {
	display: flex;
	min-width: 0;
	flex-wrap: wrap;
	gap: 6px;
	margin-top: 7px;

	span {
		min-width: 0;
		max-width: 100%;
		padding: 2px 8px;
		border: 1px solid var(--color-border);
		border-radius: 999px;
		background: var(--color-background-hover);
		font-size: 0.82rem;
		overflow-wrap: break-word;
		word-break: normal;
	}
}

.candidate-detail__headline {
	display: grid;
	gap: 8px;
	min-width: 0;
	max-width: 280px;
	margin: 0;

	> div {
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto;
		align-items: start;
		gap: 12px;
	}

	dt {
		display: flex;
		min-width: 0;
		align-items: center;
		gap: 2px;
		color: var(--color-text-maxcontrast);
		line-height: 1.35;
		overflow-wrap: break-word;
		word-break: normal;
	}

	dd {
		min-width: 0;
		margin: 0;
		font-weight: 600;
		text-align: end;
		white-space: normal;
	}
}

.candidate-detail__facts {
	display: grid;
	min-width: 0;
	max-width: 100%;
	gap: 0;
	margin: 0;

	> div {
		display: grid;
		grid-template-columns: minmax(0, 1fr) minmax(90px, auto);
		align-items: start;
		gap: 12px;
		min-width: 0;
		padding-block: 8px;
		border-block-end: 1px solid var(--color-border);
	}

	> div:last-child {
		border-block-end: 0;
	}

	dt {
		min-width: 0;
		color: var(--color-text-maxcontrast);
		font-size: 0.85rem;
		line-height: 1.35;
		overflow-wrap: break-word;
		word-break: normal;
	}

	dd,
	.candidate-detail__long-value {
		min-width: 90px;
		max-width: 180px;
		margin: 0;
		font-variant-numeric: tabular-nums;
		font-weight: 600;
		line-height: 1.35;
		text-align: end;
		white-space: normal;
		overflow-wrap: normal;
		word-break: normal;
	}
}

.candidate-detail__quality {
	padding: 2px 8px;
	border: 1px solid var(--color-border);
	border-radius: 999px;
	font-size: 0.8rem;
}

.candidate-detail__quality--high {
	border-color: var(--color-success);
}

.candidate-detail__quality--medium {
	border-color: var(--color-warning);
}

.candidate-detail__quality--low {
	border-color: var(--color-error);
}

.candidate-detail__sections {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(min(340px, 100%), 1fr));
	gap: 12px;
	min-width: 0;
	max-width: 100%;
}

.candidate-detail__section {
	box-sizing: border-box;
	min-width: 0;
	max-width: 100%;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);

	h3 {
		display: flex;
		align-items: center;
		gap: 4px;
		margin: 0 0 12px;
		font-size: 1rem;
	}

	ul {
		margin: 0;
		padding-inline-start: 20px;
	}

	li + li {
		margin-top: 6px;
	}
}

.candidate-detail__section--strengths {
	border-inline-start: 4px solid var(--color-success);
}

.candidate-detail__section--alerts {
	border-inline-start: 4px solid var(--color-warning);
}

.candidate-detail__notice {
	box-sizing: border-box;
	width: 100%;
	margin: 0 0 12px;
	padding: 8px 10px;
	border-inline-start: 3px solid var(--color-warning);
	background: var(--color-background-hover);
	line-height: 1.45;
	overflow-wrap: break-word;
	word-break: normal;
}

.candidate-detail__context,
.candidate-detail__empty,
.candidate-detail__disabled-reason {
	margin: 0 0 10px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}

.candidate-detail__actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	padding-top: 4px;
}

.candidate-detail__disabled-reason {
	margin: -10px 0 0;
	text-align: end;
}

@media (max-width: 700px) {
	.candidate-detail {
		width: min(100%, calc(100vw - 32px));
		padding: 16px;
	}

	.candidate-detail__header {
		grid-template-columns: auto minmax(0, 1fr);
	}

	.candidate-detail__headline {
		grid-column: 1 / -1;
	}

	.candidate-detail__facts > div,
	.candidate-detail__headline > div {
		grid-template-columns: 1fr;
		gap: 2px;

		dd,
		.candidate-detail__long-value {
			min-width: 0;
			max-width: 100%;
			text-align: start;
			white-space: normal;
		}
	}

	.candidate-detail__actions {
		align-items: stretch;
		flex-direction: column-reverse;
	}
}
</style>
