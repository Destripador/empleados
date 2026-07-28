<template>
	<section class="leader-detail">
		<header class="leader-header">
			<img :src="avatarUrl" :alt="leaderName" class="leader-avatar">
			<div>
				<p class="eyebrow">
					{{ t('empleados', 'Project leader') }}
				</p>
				<h2>{{ leaderName }}</h2>
				<p>{{ leader.uid }} · {{ companiesLabel }}</p>
			</div>
		</header>

		<div class="kpi-grid">
			<div v-for="card in cards" :key="card.label" class="kpi-card">
				<span>{{ card.label }}</span>
				<strong>{{ card.value }}</strong>
			</div>
		</div>

		<NcEmptyContent
			v-if="!Number(leader.total_minutos || 0)"
			class="empty-reports"
			:name="t('empleados', 'No time reports for this period')" />

		<div class="table-section">
			<h3>{{ t('empleados', 'Companies and groups') }}</h3>
			<div class="table-scroll">
				<table>
					<thead>
						<tr>
							<th>
								<button type="button" @click="sortBy('nombre_cliente')">
									{{ t('empleados', 'Company') }} {{ sortIndicator('nombre_cliente') }}
								</button>
							</th>
							<th>{{ t('empleados', 'Parent group') }}</th>
							<th>{{ t('empleados', 'Total hours') }}</th>
							<th>
								<button type="button" @click="sortBy('horas_cargables')">
									{{ t('empleados', 'Billable hours') }} {{ sortIndicator('horas_cargables') }}
								</button>
							</th>
							<th>{{ t('empleados', 'Non-billable hours') }}</th>
							<th>
								<button type="button" @click="sortBy('porcentaje_cargable')">
									{{ t('empleados', 'Billable percentage') }} {{ sortIndicator('porcentaje_cargable') }}
								</button>
							</th>
							<th>
								<button type="button" @click="sortBy('costo_cargable_estimado')">
									{{ t('empleados', 'Estimated billable cost') }} {{ sortIndicator('costo_cargable_estimado') }}
								</button>
							</th>
							<th>{{ t('empleados', 'Status') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="company in sortedCompanies" :key="company.id_cliente">
							<td>{{ company.nombre_cliente || '-' }}</td>
							<td>{{ company.nombre_grupo_padre || '-' }}</td>
							<td>{{ number(company.horas_totales) }}</td>
							<td>{{ number(company.horas_cargables) }}</td>
							<td>{{ number(company.horas_no_cargables) }}</td>
							<td>{{ number(company.porcentaje_cargable) }} %</td>
							<td>{{ money(company.costo_cargable_estimado) }}</td>
							<td>
								<span class="status" :class="{ 'status--inactive': !Number(company.estado) }">
									{{ Number(company.estado) ? t('empleados', 'Active') : t('empleados', 'Inactive') }}
								</span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</section>
</template>

<script>
import { NcEmptyContent } from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'CostosDetalle',
	components: {
		NcEmptyContent,
	},
	props: {
		leader: { type: Object, required: true },
	},
	data() {
		return {
			sortKey: 'horas_cargables',
			sortDirection: 'desc',
		}
	},
	computed: {
		leaderName() {
			return this.leader.displayname || this.leader.uid || t('empleados', 'Project leader')
		},
		avatarUrl() {
			return generateUrl('/avatar/{userId}/64', { userId: this.leader.uid })
		},
		companiesLabel() {
			return t('empleados', '{count} companies', { count: Number(this.leader.empresas_count || 0) })
		},
		cards() {
			return [
				{ label: t('empleados', 'Total hours'), value: this.number(this.leader.horas_totales) },
				{ label: t('empleados', 'Billable hours'), value: this.number(this.leader.horas_cargables) },
				{ label: t('empleados', 'Non-billable hours'), value: this.number(this.leader.horas_no_cargables) },
				{ label: t('empleados', 'Billable percentage'), value: `${this.number(this.leader.porcentaje_cargable)} %` },
				{ label: t('empleados', 'Estimated billable cost'), value: this.money(this.leader.costo_cargable_estimado) },
			]
		},
		sortedCompanies() {
			const companies = [...(this.leader.empresas || [])]
			const direction = this.sortDirection === 'asc' ? 1 : -1

			return companies.sort((left, right) => {
				const leftValue = left[this.sortKey]
				const rightValue = right[this.sortKey]

				if (this.sortKey === 'nombre_cliente') {
					return String(leftValue || '').localeCompare(String(rightValue || ''), 'es') * direction
				}

				return (Number(leftValue || 0) - Number(rightValue || 0)) * direction
			})
		},
	},
	methods: {
		t,
		number(value) {
			return new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(Number(value || 0))
		},
		money(value) {
			return new Intl.NumberFormat('es-MX', {
				style: 'currency',
				currency: 'MXN',
			}).format(Number(value || 0))
		},
		sortBy(key) {
			if (this.sortKey === key) {
				this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc'
				return
			}

			this.sortKey = key
			this.sortDirection = key === 'nombre_cliente' ? 'asc' : 'desc'
		},
		sortIndicator(key) {
			if (this.sortKey !== key) {
				return ''
			}

			return this.sortDirection === 'asc' ? '↑' : '↓'
		},
	},
}
</script>

<style scoped lang="scss">
.leader-detail {
	padding: 28px;
	color: var(--color-main-text);
}

.leader-header {
	display: flex;
	align-items: center;
	gap: 16px;
	margin-bottom: 24px;

	h2,
	p {
		margin: 0;
	}

	p:last-child {
		color: var(--color-text-maxcontrast);
	}
}

.leader-avatar {
	width: 64px;
	height: 64px;
	border-radius: 50%;
}

.eyebrow {
	color: var(--color-primary-element);
	font-weight: 600;
}

.kpi-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
	gap: 12px;
}

.kpi-card {
	display: flex;
	min-height: 80px;
	flex-direction: column;
	justify-content: space-between;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);

	span {
		color: var(--color-text-maxcontrast);
	}

	strong {
		font-size: 1.25rem;
	}
}

.empty-reports {
	margin: 20px 0;
}

.table-section {
	margin-top: 28px;
}

.table-scroll {
	overflow-x: auto;
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
	padding: 12px;
	border-bottom: 1px solid var(--color-border);
	text-align: left;
	white-space: nowrap;
}

th {
	color: var(--color-text-maxcontrast);

	button {
		min-height: auto;
		padding: 0;
		border: 0;
		background: transparent;
		color: inherit;
		font: inherit;
		font-weight: inherit;
	}
}

tbody tr:hover {
	background: var(--color-background-hover);
}

.status {
	display: inline-block;
	padding: 3px 9px;
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
}

.status--inactive {
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
}
</style>
