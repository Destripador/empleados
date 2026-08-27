<template>
	<section class="teams-section" aria-labelledby="responsible-teams-heading">
		<header class="teams-heading">
			<div>
				<p>{{ t('empleados', 'Teams') }}</p>
				<h2 id="responsible-teams-heading">
					{{ t('empleados', 'Teams under my responsibility') }}
				</h2>
			</div>
			<span v-if="!loading" class="teams-count">
				{{ t('empleados', '{count} teams', { count: teams.length }) }}
			</span>
		</header>

		<div v-if="loading" class="teams-state" role="status">
			<NcLoadingIcon :size="32" />
			<span>{{ t('empleados', 'Loading teams...') }}</span>
		</div>

		<div v-else-if="teams.length === 0" class="teams-state">
			<strong>{{ t('empleados', 'No teams under your responsibility.') }}</strong>
			<span>{{ t('empleados', 'The organizational hierarchy does not assign a visible team to you.') }}</span>
		</div>

		<div v-else class="teams-grid">
			<article
				v-for="team in teams"
				:key="team.id_equipo"
				class="team-card"
				:class="{ 'team-card--empty': !hasAccountedTime(team) }">
				<header class="team-card__header">
					<div class="team-card__title">
						<h3>{{ team.nombre || t('empleados', 'Unnamed team') }}</h3>
						<strong
							class="team-card__compliance"
							:class="statusClass(team.porcentaje_cumplimiento)"
							:title="t('empleados', 'Compliance is accounted hours divided by expected hours.')">
							{{ formatPercent(team.porcentaje_cumplimiento) }}
						</strong>
					</div>
					<p class="team-card__meta">
						{{ t('empleados', 'Lead') }}:
						{{ team.nombre_lider || t('empleados', 'Not assigned') }}
						<span aria-hidden="true"> · </span>
						{{ t('empleados', '{count} members', { count: number(team.cantidad_empleados) }) }}
					</p>
				</header>

				<div class="team-card__people">
					<MemberPreview
						v-if="hasMembers(team)"
						:members="team.integrantes_preview || []"
						:total="number(team.cantidad_empleados)"
						:limit="3"
						:size="24"
						variant="compact" />
					<p v-else class="team-card__empty">
						{{ t('empleados', 'No members assigned.') }}
					</p>
				</div>

				<div class="team-card__distribution">
					<p class="team-card__caption">
						{{ t('empleados', 'Time mix of accounted hours') }}
					</p>
					<TimeBar
						v-if="hasAccountedTime(team)"
						compact
						show-hours
						:client-hours="number(team.horas_cliente)"
						:internal-hours="number(team.horas_internas)"
						:absence-hours="number(team.horas_ausencia)"
						:mostrar-clientes="mostrarClientes"
						:mostrar-ausencias="mostrarAusencias" />
					<p v-else class="team-card__empty">
						{{ t('empleados', 'No accounted time for this period.') }}
					</p>
				</div>

				<dl class="team-card__metrics">
					<div class="team-card__metric">
						<dt>{{ t('empleados', 'Expected') }}</dt>
						<dd>{{ formatHours(team.horas_esperadas) }}</dd>
					</div>
					<div class="team-card__metric">
						<dt>{{ t('empleados', 'Accounted') }}</dt>
						<dd>{{ formatHours(team.horas_contabilizadas) }}</dd>
					</div>
					<div class="team-card__metric">
						<dt>{{ t('empleados', 'Pending') }}</dt>
						<dd>{{ formatHours(team.horas_pendientes) }}</dd>
					</div>
				</dl>

				<footer class="team-card__footer">
					<NcButton
						variant="primary"
						alignment="center-reverse"
						@click="$emit('select-team', team.id_equipo)">
						{{ t('empleados', 'View team') }}
						<template #icon>
							<ChevronRight :size="20" />
						</template>
					</NcButton>
				</footer>
			</article>
		</div>
	</section>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import MemberPreview from './AdminMemberPreview.vue'
import TimeBar from './AdminTimeDistributionBar.vue'

export default {
	name: 'AdminTeamsOverview',
	components: { ChevronRight, MemberPreview, NcButton, NcLoadingIcon, TimeBar },
	props: {
		teams: { type: Array, default: () => [] },
		loading: { type: Boolean, default: false },
		mostrarClientes: { type: Boolean, default: true },
		mostrarAusencias: { type: Boolean, default: true },
	},
	methods: {
		t,
		number(value) {
			const parsed = Number(value)
			return Number.isFinite(parsed) ? parsed : 0
		},
		hasMembers(team) {
			return this.number(team.cantidad_empleados) > 0
		},
		hasAccountedTime(team) {
			return this.number(team.horas_contabilizadas) > 0
		},
		formatHours(value) {
			return `${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 }).format(this.number(value))} h`
		},
		formatPercent(value) {
			return `${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 }).format(this.number(value))}%`
		},
		statusClass(value) {
			const percentage = this.number(value)
			if (percentage >= 100) return 'team-card__compliance--ok'
			if (percentage >= 70) return 'team-card__compliance--warning'
			return 'team-card__compliance--danger'
		},
	},
}
</script>

<style scoped>
.teams-section {
	--team-space: var(--default-grid-baseline);
	display: grid;
	container-type: inline-size;
	gap: calc(var(--team-space) * 3);
	min-width: 0;
	padding: calc(var(--team-space) * 3.5);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.teams-heading {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: calc(var(--team-space) * 2);
}

.teams-heading h2,
.teams-heading p,
.team-card h3,
.team-card p,
.team-card dl,
.team-card dd {
	margin: 0;
}

.teams-heading h2 {
	font-size: 1.2rem;
	font-weight: 600;
	line-height: 1.3;
}

.teams-heading p,
.team-card__caption {
	color: var(--color-text-maxcontrast);
	font-size: 0.75rem;
	font-weight: 600;
	letter-spacing: 0.04em;
	line-height: 1.3;
	text-transform: uppercase;
}

.teams-count {
	flex: 0 0 auto;
	color: var(--color-text-maxcontrast);
	font-size: 0.875rem;
}

.teams-grid {
	display: grid;
	align-items: start;
	grid-template-columns: minmax(0, 1fr);
	gap: calc(var(--team-space) * 3);
}

.team-card {
	display: grid;
	align-self: start;
	gap: calc(var(--team-space) * 2.5);
	min-width: 0;
	padding: calc(var(--team-space) * 3.5);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.team-card--empty {
	gap: calc(var(--team-space) * 2);
}

.team-card__header {
	display: grid;
	gap: var(--team-space);
	min-width: 0;
}

.team-card__title {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: calc(var(--team-space) * 2);
}

.team-card__title h3 {
	min-width: 0;
	font-size: 1rem;
	font-weight: 600;
	line-height: 1.3;
	overflow-wrap: anywhere;
}

.team-card__meta,
.team-card__empty {
	color: var(--color-text-maxcontrast);
	font-size: 0.875rem;
	font-weight: 400;
	line-height: 1.4;
	overflow-wrap: anywhere;
}

.team-card__compliance {
	flex: 0 0 auto;
	min-width: 3.5rem;
	padding: calc(var(--team-space) * 0.5) calc(var(--team-space) * 2);
	border-radius: var(--border-radius-pill, 999px);
	font-size: 0.875rem;
	font-variant-numeric: tabular-nums;
	font-weight: 700;
	line-height: 1.4;
	text-align: center;
}

.team-card__compliance--ok {
	background: var(--color-success-hover);
	color: var(--color-success-text);
}

.team-card__compliance--warning {
	background: var(--color-warning-hover);
	color: var(--color-warning-text);
}

.team-card__compliance--danger {
	background: var(--color-error-hover);
	color: var(--color-error-text);
}

.team-card__people,
.team-card__distribution {
	display: grid;
	gap: calc(var(--team-space) * 1.5);
	min-width: 0;
}

.team-card__metrics {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 0;
	min-width: 0;
	padding-block: calc(var(--team-space) * 2);
	border-block: 1px solid var(--color-border);
}

.team-card__metric {
	display: grid;
	gap: var(--team-space);
	min-width: 0;
	padding-inline: calc(var(--team-space) * 2.5);
}

.team-card__metric:first-child {
	padding-inline-start: 0;
}

.team-card__metric:last-child {
	padding-inline-end: 0;
}

.team-card__metric + .team-card__metric {
	border-inline-start: 1px solid var(--color-border);
}

.team-card__metrics dt {
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	font-weight: 600;
	line-height: 1.2;
}

.team-card__metrics dd {
	font-size: 1rem;
	font-variant-numeric: tabular-nums;
	font-weight: 700;
	line-height: 1.2;
	white-space: nowrap;
}

.team-card__footer {
	display: flex;
	align-items: center;
	justify-content: flex-end;
}

.teams-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: calc(var(--team-space) * 2);
	padding: calc(var(--team-space) * 6);
	color: var(--color-text-maxcontrast);
	text-align: center;
}

@container (min-width: 48rem) {
	.teams-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@container (max-width: 22rem) {
	.team-card__metrics {
		grid-template-columns: 1fr;
		padding-block: var(--team-space);
	}

	.team-card__metric {
		grid-template-columns: minmax(0, 1fr) auto;
		align-items: baseline;
		gap: calc(var(--team-space) * 2);
		padding: calc(var(--team-space) * 1.5) 0;
	}

	.team-card__metric + .team-card__metric {
		border-block-start: 1px solid var(--color-border);
		border-inline-start: 0;
	}
}

@media (max-width: 32rem) {
	.teams-heading {
		flex-direction: column;
	}
}
</style>
