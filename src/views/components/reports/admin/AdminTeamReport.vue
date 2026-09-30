<template>
	<div class="team-report">
		<div v-if="loading" class="team-state" role="status">
			<NcLoadingIcon :size="40" />
			<span>{{ t('empleados', 'Loading team report...') }}</span>
		</div>
		<div v-else-if="!report || !report.equipo" class="team-state">
			<strong>{{ t('empleados', 'Team report is not available.') }}</strong>
		</div>
		<template v-else>
			<section class="team-hero">
				<div class="team-hero__body">
					<div class="team-hero__heading">
						<div>
							<p class="eyebrow">
								{{ t('empleados', 'Team summary') }}
							</p>
							<h2>{{ report.equipo.nombre || t('empleados', 'Unnamed team') }}</h2>
							<p class="team-hero__meta">
								{{ t('empleados', 'Lead') }}: {{ report.equipo.nombre_lider || t('empleados', 'Not assigned') }}
								<span aria-hidden="true"> · </span>
								{{ t('empleados', '{count} members', { count: number(report.equipo.cantidad_empleados) }) }}
							</p>
						</div>
						<strong
							class="compliance-pill"
							:class="statusClass(periodCompliance.porcentaje_cumplimiento)"
							:title="t('empleados', 'Compliance is accounted hours divided by expected hours.')">
							{{ formatPercent(periodCompliance.porcentaje_cumplimiento) }}
						</strong>
					</div>
					<div class="team-hero__members">
						<span>{{ t('empleados', 'Team members') }}</span>
						<MemberPreview
							:members="teamPreviewMembers"
							:total="number(report.equipo.cantidad_empleados)"
							:size="32"
							variant="strip" />
					</div>
				</div>
			</section>

			<section class="period-grid" :aria-label="t('empleados', 'Compliance periods')">
				<article v-for="period in periods" :key="period.key" class="period-card">
					<span>{{ period.label }}</span>
					<strong>{{ formatPercent(period.data.porcentaje_cumplimiento) }}</strong>
					<p>
						{{ t('empleados', 'Accounted') }}: {{ formatHours(period.data.horas_contabilizadas) }}
						/ {{ t('empleados', 'Expected') }}: {{ formatHours(period.data.horas_esperadas) }}
					</p>
					<small>
						{{ t('empleados', 'Reported work hours') }}: {{ formatHours(period.data.horas_reportadas) }}
					</small>
					<small>{{ formatRange(period.data) }}</small>
				</article>
			</section>

			<section class="metrics-grid" :aria-label="t('empleados', 'Team time metrics')">
				<article v-for="metric in metrics" :key="metric.key" class="metric-card">
					<span>{{ metric.label }}</span>
					<strong>{{ formatHours(metric.value) }}</strong>
				</article>
			</section>

			<section class="report-section">
				<header class="section-heading">
					<div>
						<p class="eyebrow">
							{{ t('empleados', 'Distribution') }}
						</p>
						<h3>{{ t('empleados', 'Team time mix') }}</h3>
					</div>
					<small>{{ t('empleados', 'Percentages use accounted hours as denominator.') }}</small>
				</header>
				<TimeBar
					:client-hours="number(periodCompliance.horas_cliente)"
					:internal-hours="number(periodCompliance.horas_internas)"
					:absence-hours="number(periodCompliance.horas_ausencia)"
					:mostrar-clientes="mostrarClientes"
					:mostrar-ausencias="mostrarAusencias" />
				<ul class="distribution-list">
					<li v-for="item in distribution" :key="item.key">
						<div>
							<strong>{{ item.label }}</strong>
							<span>{{ formatHours(item.hours) }} · {{ formatPercent(item.percentage) }}</span>
						</div>
					</li>
				</ul>
			</section>

			<section class="ranking-grid">
				<article v-if="mostrarClientes" class="report-section">
					<h3>{{ t('empleados', 'Top clients') }}</h3>
					<p class="denominator">
						{{ t('empleados', 'Percentage of client work.') }}
					</p>
					<RankingList :items="clientRanking" />
				</article>
				<article class="report-section">
					<h3>{{ t('empleados', 'Top internal activities') }}</h3>
					<p class="denominator">
						{{ t('empleados', 'Percentage of internal work.') }}
					</p>
					<RankingList :items="internalRanking" />
				</article>
				<article v-if="mostrarAusencias" class="report-section">
					<h3>{{ t('empleados', 'Top absences') }}</h3>
					<p class="denominator">
						{{ t('empleados', 'Percentage of absence hours.') }}
					</p>
					<RankingList :items="absenceRanking" />
				</article>
			</section>

			<section class="report-section report-section--member-distribution">
				<header class="section-heading">
					<div>
						<p class="eyebrow">
							{{ t('empleados', 'People') }}
						</p>
						<h3>{{ t('empleados', 'Distribution by member') }}</h3>
					</div>
					<small>{{ t('empleados', 'Compliance is accounted vs expected. The bar is the mix of accounted hours.') }}</small>
				</header>

				<div v-if="sortedMembers.length" class="team-members-grid">
					<article
						v-for="member in sortedMembers"
						:key="member.id_empleado"
						class="member-card"
						:class="{
							'member-card--expanded': expandedMemberId === member.id_empleado,
							'member-card--inactive': member.inactivo_desde,
						}">
						<header class="member-card__header">
							<div class="member-identity">
								<div class="member-card__avatar">
									<NcAvatar
										disable-menu
										aria-hidden="true"
										:user="member.id_user || member.Id_user || ''"
										:display-name="member.nombre"
										:size="36"
										:show-user-status="false"
										:show-user-status-compact="false" />
								</div>
								<span class="member-card__name-wrapper">
									<strong class="member-card__name">{{ member.nombre }}</strong>
									<span v-if="member.inactivo_desde" class="member-card__badge">
										{{ t('empleados', 'Inactivo') }}
									</span>
									<small v-if="memberArea(member)">{{ memberArea(member) }}</small>
								</span>
							</div>
							<strong
								class="compliance-pill compliance-pill--compact"
								:class="statusClass(member.porcentaje_cumplimiento)"
								:title="t('empleados', 'Compliance is accounted hours divided by expected hours.')">
								{{ formatPercent(member.porcentaje_cumplimiento) }}
							</strong>
						</header>

						<dl v-if="hasMemberAccountedTime(member)" class="member-card__metrics">
							<!--<div class="member-card__metric">
								<dt>{{ t('empleados', 'Expected') }}</dt>
								<dd>{{ formatHours(member.horas_esperadas) }}</dd>
							</div>-->
							<div class="member-card__metric">
								<dt>{{ t('empleados', 'Accounted') }}</dt>
								<dd>{{ formatHours(member.horas_contabilizadas) }}</dd>
							</div>
							<div class="member-card__metric">
								<dt>{{ t('empleados', 'Pending') }}</dt>
								<dd>{{ formatHours(member.horas_pendientes) }}</dd>
							</div>
						</dl>
						<dl v-else class="member-card__empty-metrics">
							<!-- <div class="member-card__empty-metric">
								<dt>{{ t('empleados', 'Expected') }}</dt>
								<dd>{{ formatHours(member.horas_esperadas) }}</dd>
							</div>-->
							<div class="member-card__empty-metric">
								<dt>{{ t('empleados', 'Pending') }}</dt>
								<dd>{{ formatHours(member.horas_pendientes) }}</dd>
							</div>
						</dl>

						<div v-if="hasMemberAccountedTime(member)" class="member-card__mix">
							<p class="member-card__caption">
								{{ t('empleados', 'Time mix of accounted hours') }}
							</p>
							<TimeBar
								compact
								show-hours
								:client-hours="number(member.horas_cliente)"
								:internal-hours="number(member.horas_internas)"
								:absence-hours="number(member.horas_ausencia)"
								:mostrar-clientes="mostrarClientes"
								:mostrar-ausencias="mostrarAusencias" />
						</div>
						<p v-else class="member-card__empty">
							{{ t('empleados', 'No accounted time for this period.') }}
						</p>

						<footer class="member-card__footer">
							<NcButton
								v-if="hasMemberDetails(member)"
								variant="tertiary"
								:aria-expanded="expandedMemberId === member.id_empleado ? 'true' : 'false'"
								:aria-controls="memberDetailsId(member)"
								@click="toggleMember(member.id_empleado)">
								{{ expandedMemberId === member.id_empleado
									? t('empleados', 'Hide distribution')
									: t('empleados', 'View distribution') }}
							</NcButton>
							<NcButton
								variant="primary"
								alignment="center-reverse"
								@click="$emit('select-employee', member.id_empleado)">
								{{ t('empleados', 'View details') }}
								<template #icon>
									<ChevronRight :size="20" />
								</template>
							</NcButton>
						</footer>

						<div
							v-if="expandedMemberId === member.id_empleado && hasMemberDetails(member)"
							:id="memberDetailsId(member)"
							class="member-card__detail">
							<section v-if="mostrarClientes">
								<h4>{{ t('empleados', 'Top clients') }}</h4>
								<RankingList :items="memberRanking(member.clientes_principales)" />
							</section>
							<section>
								<h4>{{ t('empleados', 'Top internal activities') }}</h4>
								<RankingList :items="memberRanking(member.actividades_internas_principales)" />
							</section>
							<section v-if="mostrarAusencias">
								<h4>{{ t('empleados', 'Top absences') }}</h4>
								<RankingList :items="memberRanking(member.ausencias_principales)" />
							</section>
						</div>
					</article>
				</div>
				<p v-else class="empty-state">
					{{ t('empleados', 'This team has no members for the current selection.') }}
				</p>
			</section>

			<section v-if="dependentTeams.length" class="report-section">
				<header class="section-heading">
					<div>
						<p class="eyebrow">
							{{ t('empleados', 'Hierarchy') }}
						</p>
						<h3>{{ t('empleados', 'Dependent teams') }}</h3>
					</div>
				</header>
				<div class="dependent-list">
					<article v-for="team in dependentTeams"
						:key="team.id_equipo"
						class="dependent-card">
						<div class="dependent-card__heading">
							<strong>{{ team.nombre || t('empleados', 'Unnamed team') }}</strong>
							<small>{{ t('empleados', '{count} members', { count: number(team.cantidad_empleados) }) }}</small>
						</div>
						<span class="dependent-card__leader">
							{{ t('empleados', 'Lead') }}: {{ team.nombre_lider || t('empleados', 'Not assigned') }}
						</span>
						<MemberPreview
							:members="team.integrantes_preview || []"
							:total="number(team.cantidad_empleados)"
							:size="30"
							variant="strip" />
						<div class="dependent-card__action">
							<NcButton type="tertiary" @click="$emit('select-team', team.id_equipo)">
								{{ t('empleados', 'View team') }}
							</NcButton>
						</div>
					</article>
				</div>
			</section>
		</template>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import MemberPreview from './AdminMemberPreview.vue'
import RankingList from './AdminRankingList.vue'
import TimeBar from './AdminTimeDistributionBar.vue'

export default {
	name: 'AdminTeamReport',
	components: { ChevronRight, MemberPreview, NcAvatar, NcButton, NcLoadingIcon, RankingList, TimeBar },
	props: {
		report: { type: Object, default: null },
		loading: { type: Boolean, default: false },
		mostrarClientes: { type: Boolean, default: true },
		mostrarAusencias: { type: Boolean, default: true },
	},
	data() {
		return {
			expandedMemberId: null,
		}
	},
	computed: {
		periodCompliance() { return this.report?.cumplimiento?.periodo || {} },
		teamPreviewMembers() {
			const preview = this.report?.equipo?.integrantes_preview
			return Array.isArray(preview) ? preview : this.members.slice(0, 5)
		},
		periods() {
			const compliance = this.report?.cumplimiento || {}
			return [
				{ key: 'periodo', label: t('empleados', 'Selected period'), data: compliance.periodo || {} },
				{ key: 'quincena', label: t('empleados', 'Fortnight'), data: compliance.quincena || {} },
				{ key: 'mes', label: t('empleados', 'Month'), data: compliance.mes || {} },
			]
		},
		metrics() {
			const period = this.periodCompliance
			return [
				{ key: 'expected', label: t('empleados', 'Expected hours'), value: period.horas_esperadas },
				{ key: 'reported', label: t('empleados', 'Reported work hours'), value: period.horas_reportadas },
				{ key: 'accounted', label: t('empleados', 'Accounted hours'), value: period.horas_contabilizadas },
				{ key: 'pending', label: t('empleados', 'Pending hours'), value: period.horas_pendientes },
				...(this.mostrarClientes ? [{ key: 'client', label: t('empleados', 'Client work'), value: period.horas_cliente }] : []),
				{ key: 'internal', label: t('empleados', 'Internal work'), value: period.horas_internas },
				...(this.mostrarAusencias ? [{ key: 'absence', label: t('empleados', 'Absences'), value: period.horas_ausencia }] : []),
			]
		},
		distribution() {
			const items = [
				{ key: 'client', label: t('empleados', 'Client work'), hours: this.periodCompliance.horas_cliente, visible: this.mostrarClientes },
				{ key: 'internal', label: t('empleados', 'Internal work'), hours: this.periodCompliance.horas_internas, visible: true },
				{ key: 'absence', label: t('empleados', 'Absences'), hours: this.periodCompliance.horas_ausencia, visible: this.mostrarAusencias },
			].filter(item => item.visible)
			const denominator = this.number(this.periodCompliance.horas_contabilizadas)
			return items.map(item => ({
				...item,
				hours: this.number(item.hours),
				percentage: denominator > 0 ? (this.number(item.hours) / denominator) * 100 : 0,
			}))
		},
		clientRanking() { return this.ranking(this.report?.graficas?.horas_por_cliente, 'id_cliente') },
		internalRanking() { return this.ranking(this.report?.graficas?.actividades_internas, 'id_actividad') },
		absenceRanking() { return this.ranking(this.report?.graficas?.ausencias_por_tipo, 'nombre') },
		dependentTeams() { return Array.isArray(this.report?.equipos_dependientes) ? this.report.equipos_dependientes : [] },
		members() { return Array.isArray(this.report?.integrantes) ? this.report.integrantes : [] },
		sortedMembers() {
			return [...this.members].sort((left, right) => {
				const complianceDelta = this.number(left.porcentaje_cumplimiento) - this.number(right.porcentaje_cumplimiento)
				if (complianceDelta !== 0) return complianceDelta
				return String(left.nombre || '').localeCompare(String(right.nombre || ''), 'es', { sensitivity: 'base' })
			})
		},
	},
	watch: {
		'report.equipo.id_equipo'() {
			this.expandedMemberId = null
		},
	},
	methods: {
		t,
		number(value) { const parsed = Number(value); return Number.isFinite(parsed) ? parsed : 0 },
		clamp(value) { return Math.min(100, Math.max(0, this.number(value))) },
		memberArea(member) {
			const area = String(member?.area || '').trim()
			if (!area || area.toLowerCase() === 'area') {
				return ''
			}
			return area
		},
		hasMemberAccountedTime(member) {
			return this.number(member?.horas_contabilizadas) > 0
		},
		hasMemberDetails(member) {
			if (!this.hasMemberAccountedTime(member)) return false
			return (this.mostrarClientes && this.hasRankingData(member?.clientes_principales))
				|| this.hasRankingData(member?.actividades_internas_principales)
				|| (this.mostrarAusencias && this.hasRankingData(member?.ausencias_principales))
		},
		hasRankingData(rows) {
			return Array.isArray(rows) && rows.some(row => this.number(row?.horas) > 0)
		},
		memberDetailsId(member) {
			return `member-distribution-${Number(member?.id_empleado) || 0}`
		},
		formatHours(value) { return `${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 }).format(this.number(value))} h` },
		formatPercent(value) { return `${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 }).format(this.number(value))}%` },
		formatRange(data) {
			if (!data?.fecha_inicio || !data?.fecha_fin) return '—'
			return `${this.formatDate(data.fecha_inicio)} – ${this.formatDate(data.fecha_fin)}`
		},
		formatDate(value) {
			const [year, month, day] = String(value).split('-').map(Number)
			if (!year || !month || !day) return String(value || '')
			return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(year, month - 1, day, 12))
		},
		statusClass(value) {
			if (this.number(value) >= 100) return 'compliance-pill--ok'
			if (this.number(value) >= 70) return 'compliance-pill--warning'
			return 'compliance-pill--danger'
		},
		toggleMember(id) {
			const memberId = Number(id)
			this.expandedMemberId = this.expandedMemberId === memberId ? null : memberId
		},
		memberRanking(rows) {
			return this.ranking(rows, 'nombre')
		},
		ranking(rows, keyField) {
			const source = Array.isArray(rows) ? rows : []
			const normalized = source.map((row, index) => ({
				key: String(row?.[keyField] ?? index),
				label: String(row?.nombre || row?.cliente_nombre || row?.actividad_nombre || t('empleados', 'No data')),
				hours: this.number(row?.horas),
			})).filter(item => item.hours > 0).sort((a, b) => b.hours - a.hours)
			const total = normalized.reduce((sum, item) => sum + item.hours, 0)
			return normalized.map(item => {
				const percentage = total > 0 ? (item.hours / total) * 100 : 0
				return {
					...item,
					hoursText: this.formatHours(item.hours),
					percentageText: this.formatPercent(percentage),
					width: this.clamp(percentage),
				}
			})
		},
	},
}
</script>

<style scoped>
.team-report { display: grid; gap: 16px; min-width: 0; }
.team-state { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 48px; color: var(--color-text-maxcontrast); }
.team-hero,
.report-section,
.period-card,
.metric-card,
.member-card { border: 1px solid var(--color-border); border-radius: var(--border-radius-large); background: var(--color-main-background); }
.team-hero { padding: 16px; }
.team-hero__body { display: grid; gap: 12px; min-width: 0; }
.team-hero__heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.team-hero__heading > div { min-width: 0; }
.team-hero__members { display: grid; gap: 6px; }
.team-hero__members > span { color: var(--color-text-maxcontrast); font-size: .76rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
.team-hero h2,
.team-hero p,
.section-heading h3,
.section-heading p,
.period-card p,
.report-section h3,
.denominator { margin: 0; }
.team-hero__meta,
.denominator,
.period-card small,
.section-heading small { color: var(--color-text-maxcontrast); }
.eyebrow { color: var(--color-text-maxcontrast); font-size: .76rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
.compliance-pill { align-self: flex-start; min-width: 74px; padding: 7px 12px; border-radius: var(--border-radius-pill, 999px); font-variant-numeric: tabular-nums; text-align: center; }
.compliance-pill--compact { min-width: 3.5rem; padding: 2px 8px; font-size: .875rem; line-height: 1.4; }
.compliance-pill--ok { background: var(--color-success-hover); color: var(--color-success-text); }
.compliance-pill--warning { background: var(--color-warning-hover); color: var(--color-warning-text); }
.compliance-pill--danger { background: var(--color-error-hover); color: var(--color-error-text); }
.period-grid,
.metrics-grid,
.ranking-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.period-card,
.metric-card { display: grid; gap: 5px; padding: 14px; background: var(--color-background-hover); }
.period-card > span,
.metric-card span { color: var(--color-text-maxcontrast); font-size: .76rem; font-weight: 700; text-transform: uppercase; }
.period-card strong { font-size: 1.5rem; }
.metric-card strong { font-size: 1.1rem; }
.report-section { display: grid; gap: 14px; padding: 16px; }
.section-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; }
.distribution-list { display: grid; gap: 6px; margin: 0; padding: 0; list-style: none; }
.distribution-list li > div { display: flex; justify-content: space-between; gap: 12px; }
.report-section--member-distribution { container-type: inline-size; }
.dependent-list,
.team-members-grid { display: grid; gap: 12px; }
.dependent-list { grid-template-columns: repeat(auto-fit, minmax(min(100%, 17rem), 1fr)); }
.team-members-grid { grid-template-columns: minmax(0, 1fr); align-items: start; }
.dependent-card { display: flex; flex-direction: column; gap: 12px; min-width: 0; padding: 16px; background: var(--color-background-hover); }
.member-card { display: grid; align-self: start; gap: 8px; min-width: 0; padding: 12px; background: var(--color-background-hover); }
.member-card--expanded { grid-column: 1 / -1; }
.dependent-card__heading { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; }
.dependent-card__heading strong { overflow-wrap: anywhere; }
.dependent-card__heading small,
.dependent-card__leader { color: var(--color-text-maxcontrast); }
.dependent-card__leader { font-size: .82rem; overflow-wrap: anywhere; }
.dependent-card__action { display: flex; justify-content: flex-end; margin-top: auto; }
.member-card__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
.member-identity { display: flex; align-items: center; gap: 8px; min-width: 0; }
.member-identity > span { display: grid; gap: 2px; min-width: 0; }
.member-identity strong { font-size: .95rem; font-weight: 600; line-height: 1.25; overflow-wrap: anywhere; }
.member-identity small,
.empty-state { color: var(--color-text-maxcontrast); }
.member-card__caption {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: .75rem;
	font-weight: 600;
	letter-spacing: .04em;
	text-transform: uppercase;
}
.member-card__metrics {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 0;
	margin: 0;
	padding-block: 7px;
	border-block: 1px solid var(--color-border);
}
.member-card__metric { display: grid; gap: 3px; min-width: 0; padding-inline: 9px; }
.member-card__metric:first-child { padding-inline-start: 0; }
.member-card__metric:last-child { padding-inline-end: 0; }
.member-card__metric + .member-card__metric { border-inline-start: 1px solid var(--color-border); }
.member-card__metrics dt {
	color: var(--color-text-maxcontrast);
	font-size: .72rem;
	font-weight: 600;
	line-height: 1.2;
}
.member-card__metrics dd {
	margin: 0;
	font-size: 1rem;
	font-variant-numeric: tabular-nums;
	font-weight: 700;
	line-height: 1.2;
	white-space: nowrap;
}
.member-card__empty-metrics {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	gap: 3px 9px;
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: .82rem;
}
.member-card__empty-metric { display: inline-flex; align-items: baseline; gap: 4px; }
.member-card__empty-metric + .member-card__empty-metric::before { margin-inline-end: 5px; content: "·"; }
.member-card__empty-metrics dd { margin: 0; color: var(--color-main-text); font-variant-numeric: tabular-nums; font-weight: 700; white-space: nowrap; }
.member-card__mix { display: grid; gap: 6px; min-width: 0; }
.member-card__empty { margin: 0; color: var(--color-text-maxcontrast); font-size: .82rem; }
.member-card__footer {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: flex-end;
	gap: 6px;
}
.member-card__detail { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr)); gap: 12px; padding-top: 10px; border-top: 1px solid var(--color-border); }
.member-card__detail h4 { margin: 0 0 6px; font-size: .86rem; }
@container (min-width: 48rem) {
	.team-members-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@container (max-width: 30rem) {
	.member-card__footer { justify-content: stretch; }
	.member-card__footer :deep(.button-vue) { flex: 1 1 auto; }
}
@container (max-width: 22rem) {
	.member-card__metrics { grid-template-columns: 1fr; padding-block: 4px; }
	.member-card__metric { grid-template-columns: minmax(0, 1fr) auto; align-items: baseline; gap: 8px; padding: 6px 0; }
	.member-card__metric + .member-card__metric { border-block-start: 1px solid var(--color-border); border-inline-start: 0; }
}
@media (max-width: 900px) {
	.period-grid,
	.metrics-grid,
	.ranking-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 600px) {
	.team-hero__heading,
	.section-heading { flex-direction: column; }
	.period-grid,
	.metrics-grid,
	.ranking-grid { grid-template-columns: 1fr; }
}

.member-card--inactive {
	background: var(--color-background-dark);
}

.member-card--inactive .member-card__name {
	font-style: italic;
	color: var(--color-text-maxcontrast);
}

.member-card--inactive .member-card__metrics,
.member-card--inactive .member-card__mix {
	filter: grayscale(1);
}

.member-card__avatar {
	display: flex;
	flex: 0 0 auto;
}

.member-card--inactive .member-card__avatar {
	filter: grayscale(1);
	opacity: 0.65;
}

.member-card__name-wrapper {
	display: grid;
	gap: 2px;
	min-width: 0;
}

.member-card__badge {
	width: fit-content;
	padding: 2px 8px;
	border-radius: 999px;
	background: var(--color-background-darker);
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	font-weight: 600;
	line-height: 1.4;
}
</style>
