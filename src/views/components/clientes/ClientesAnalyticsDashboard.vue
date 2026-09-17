<template>
	<div class="clientes-analytics-dashboard">
		<div v-if="loading" class="dashboard-state" role="status">
			<NcLoadingIcon :size="40" />
			<span>{{ t('empleados', 'Loading customers dashboard...') }}</span>
		</div>

		<div v-else-if="error" class="dashboard-state dashboard-state--empty">
			<strong>{{ t('empleados', 'The customers dashboard could not be loaded.') }}</strong>
			<span>{{ error }}</span>
		</div>

		<div v-else-if="!hasCatalog" class="dashboard-state dashboard-state--empty">
			<strong>{{ t('empleados', 'No customers for this selection.') }}</strong>
			<span>{{ t('empleados', 'Try another period or change the customer filters.') }}</span>
		</div>

		<div v-else class="dashboard-content">
			<section class="dashboard-section" aria-labelledby="clientes-kpis-heading">
				<header class="section-heading">
					<div>
						<h2 id="clientes-kpis-heading">
							{{ t('empleados', 'Customer overview') }}
						</h2>
					</div>
				</header>

				<div class="compliance-grid">
					<article v-for="item in catalogCards"
						:key="item.key"
						class="compliance-card"
						:class="item.status">
						<div class="compliance-card__heading">
							<div class="compliance-card__title">
								<h3>{{ item.label }}</h3>
								<p>{{ item.hint }}</p>
							</div>
							<strong class="compliance-card__percent">{{ item.value }}</strong>
						</div>
					</article>
				</div>
			</section>

			<section class="dashboard-section" aria-labelledby="clientes-fees-heading">
				<header class="section-heading">
					<div>
						<p class="section-eyebrow">
							{{ t('empleados', 'Fees') }}
						</p>
						<h2 id="clientes-fees-heading">
							{{ t('empleados', 'Outstanding fees') }}
						</h2>
					</div>
					<label v-if="currencyOptions.length > 1" class="compliance-mode">
						<span class="visually-hidden">{{ t('empleados', 'Currency') }}</span>
						<select v-model="selectedCurrency" class="compliance-mode__select">
							<option v-for="option in currencyOptions" :key="option" :value="option">
								{{ option }}
							</option>
						</select>
					</label>
				</header>

				<div v-if="activeCurrency" class="compliance-grid">
					<article class="compliance-card status-neutral">
						<div class="compliance-card__heading">
							<div class="compliance-card__title">
								<h3>{{ t('empleados', 'Total fees') }}</h3>
								<p>{{ activeCurrency.moneda }}</p>
							</div>
							<strong class="compliance-card__percent">{{ formatMoney(activeCurrency.total, activeCurrency.moneda) }}</strong>
						</div>
						<p class="compliance-card__hours">
							<span>{{ t('empleados', 'Generated installments') }}</span>
						</p>
					</article>

					<article class="compliance-card status-complete">
						<div class="compliance-card__heading">
							<div class="compliance-card__title">
								<h3>{{ t('empleados', 'Collected') }}</h3>
								<p>{{ activeCurrency.moneda }}</p>
							</div>
							<strong class="compliance-card__percent">{{ formatMoney(activeCurrency.pagado, activeCurrency.moneda) }}</strong>
						</div>
						<div class="progress-track"
							role="progressbar"
							:aria-valuenow="collectedPercent"
							aria-valuemin="0"
							aria-valuemax="100">
							<div class="progress-value" :style="{ width: `${collectedPercent}%` }" />
						</div>
					</article>

					<article class="compliance-card status-warning">
						<div class="compliance-card__heading">
							<div class="compliance-card__title">
								<h3>{{ t('empleados', 'Outstanding fees') }}</h3>
								<p>{{ activeCurrency.moneda }}</p>
							</div>
							<strong class="compliance-card__percent">{{ formatMoney(activeCurrency.pendiente, activeCurrency.moneda) }}</strong>
						</div>
						<div class="progress-track"
							role="progressbar"
							:aria-valuenow="pendingPercent"
							aria-valuemin="0"
							aria-valuemax="100">
							<div class="progress-value" :style="{ width: `${pendingPercent}%` }" />
						</div>
						<div class="compliance-card__footer">
							<span>{{ t('empleados', 'Pending share') }}: {{ formatPercent(activeCurrency.porcentaje_pendiente) }}</span>
							<span>{{ t('empleados', 'Customers with outstanding balance') }}: {{ activeCurrency.clientes_con_pendiente }}</span>
						</div>
					</article>
				</div>

				<div v-else class="inline-state">
					{{ t('empleados', 'No fee installments were found for this selection.') }}
				</div>

				<p class="chart-note">
					{{ t('empleados', 'Outstanding fees are unpaid installments. Overdue receivables are not shown because there is no due date in the current data.') }}
				</p>
			</section>

			<section class="rankings-grid" :aria-label="t('empleados', 'Customer fee rankings')">
				<article class="ranking-card">
					<header class="section-heading section-heading--compact">
						<div>
							<p class="section-eyebrow">
								{{ t('empleados', 'Outstanding fees') }}
							</p>
							<h2>{{ t('empleados', 'Customers with the highest outstanding balance') }}</h2>
						</div>
					</header>

					<ol v-if="pendingRanking.length > 0" class="ranking-list">
						<li
							v-for="(item, index) in pendingRanking"
							:key="item.id"
							class="ranking-item ranking-item--clickable"
							@click="selectClient(item.id)">
							<span class="ranking-position">{{ index + 1 }}</span>
							<ClienteLogo :id="item.id"
								:logo="item.logo"
								size="sm"
								:alt="item.nombre" />
							<div class="ranking-item__content">
								<div class="ranking-item__heading">
									<strong>{{ item.nombre }}</strong>
									<span>{{ formatMoney(item.pendiente) }}</span>
								</div>
								<div class="ranking-track">
									<div class="ranking-value ranking-value--client" :style="{ width: `${item.relativeWidth}%` }" />
								</div>
								<small>{{ t('empleados', 'Collected') }}: {{ formatMoney(item.pagado) }}</small>
							</div>
						</li>
					</ol>
					<div v-else class="inline-state inline-state--compact">
						{{ t('empleados', 'No outstanding fees for this selection.') }}
					</div>
				</article>

				<article class="ranking-card">
					<header class="section-heading section-heading--compact">
						<div>
							<p class="section-eyebrow">
								{{ t('empleados', 'Services') }}
							</p>
							<h2>{{ t('empleados', 'Fees by service type') }}</h2>
						</div>
						<label v-if="serviceYearOptions.length > 0" class="compliance-mode">
							<span class="visually-hidden">{{ t('empleados', 'Year') }}</span>
							<select v-model="selectedServiceYear" class="compliance-mode__select">
								<option value="all">
									{{ t('empleados', 'All years') }}
								</option>
								<option v-for="year in serviceYearOptions" :key="year" :value="String(year)">
									{{ year }}
								</option>
							</select>
						</label>
					</header>

					<ul v-if="serviceItemsDisplay.length > 0" class="distribution-list">
						<li v-for="item in serviceItemsDisplay" :key="item.servicio" class="distribution-item">
							<div class="distribution-item__heading">
								<div class="distribution-item__label">
									<span class="distribution-dot" :class="{ 'distribution-dot--complete': item.es_fijo }" />
									<strong>{{ item.servicio }}</strong>
								</div>
								<span>{{ formatMoney(item.displayTotal, activeServiceGroup ? activeServiceGroup.moneda : '') }}</span>
							</div>
							<div class="distribution-track">
								<div class="distribution-value"
									:class="{ 'distribution-value--complete': item.es_fijo }"
									:style="{ width: `${serviceTotalSum > 0 ? Math.min(100, (item.displayTotal / serviceTotalSum) * 100) : 0}%` }" />
							</div>
						</li>
					</ul>
					<div v-else class="inline-state inline-state--compact">
						{{ t('empleados', 'No fees found for this selection.') }}
					</div>
				</article>
			</section>

			<section v-if="evolucion.length > 0" class="dashboard-section" aria-labelledby="clientes-trend-heading">
				<header class="section-heading">
					<div>
						<p class="section-eyebrow">
							{{ t('empleados', 'Trend') }}
						</p>
						<h2 id="clientes-trend-heading">
							{{ t('empleados', 'Generated vs collected by month') }}
						</h2>
					</div>
				</header>

				<ul class="distribution-list">
					<li v-for="item in evolucion" :key="item.mes" class="distribution-item">
						<div class="distribution-item__heading">
							<strong>{{ item.mes }}</strong>
							<span>{{ formatMoney(item.generado) }} / {{ formatMoney(item.cobrado) }}</span>
						</div>
						<div class="distribution-track">
							<div class="distribution-value distribution-value--client" :style="{ width: `${item.generatedWidth}%` }" />
						</div>
						<div class="distribution-track">
							<div class="distribution-value distribution-value--complete" :style="{ width: `${item.collectedWidth}%` }" />
						</div>
					</li>
				</ul>
			</section>

			<section class="dashboard-section" aria-labelledby="clientes-table-heading">
				<header class="section-heading section-heading--with-control">
					<div>
						<p class="section-eyebrow">
							{{ t('empleados', 'Detail') }}
						</p>
						<h2 id="clientes-table-heading">
							{{ t('empleados', 'Fees by customer') }}
						</h2>
					</div>
					<label class="compliance-mode">
						<span class="visually-hidden">{{ t('empleados', 'Sort') }}</span>
						<select v-model="sortKey" class="compliance-mode__select">
							<option value="nombre">
								{{ t('empleados', 'Name (grouped)') }}
							</option>
							<option value="pendiente">
								{{ t('empleados', 'Outstanding balance') }}
							</option>
							<option value="total">
								{{ t('empleados', 'Total fees') }}
							</option>
						</select>
					</label>
				</header>

				<!-- Vista agrupada: Groups / Individual companies, con padres + hijos con sangría -->
				<div v-if="sortKey === 'nombre'" class="employee-grid" role="table">
					<div class="employee-grid__head" role="row">
						<span role="columnheader">{{ t('empleados', 'Customer') }}</span>
						<span role="columnheader">{{ t('empleados', 'Total') }}</span>
						<span role="columnheader">{{ t('empleados', 'Collected') }}</span>
						<span role="columnheader">{{ t('empleados', 'Outstanding') }}</span>
						<span role="columnheader" class="visually-hidden">{{ t('empleados', 'Details') }}</span>
					</div>

					<template v-for="group in groupedSections">
						<div :key="'header-' + group.key"
							class="employee-grid__row employee-grid__row--section"
							role="row"
							@click="toggleSeccion(group.key)">
							<span role="cell" class="employee-section-header">
								<span class="employee-section-icon" :class="{ 'employee-section-icon--open': !group.collapsed }">▸</span>
								{{ group.label }} ({{ group.count }})
							</span>
						</div>

						<div :key="'wrap-' + group.key"
							class="employee-collapse"
							:class="{ 'employee-collapse--open': !group.collapsed }">
							<div class="employee-collapse__inner">
								<div v-for="item in group.items"
									:key="item.id"
									class="employee-grid__row employee-grid__row--clickable"
									role="row"
									@click="selectClient(item.id)">
									<span role="cell" class="employee-cell" :style="{ paddingLeft: `${item.level * 1.25}rem` }">
										<span v-if="item.level > 0" class="employee-indent-marker">›</span>
										<strong>{{ item.nombre }}</strong>
									</span>
									<span role="cell" class="employee-metric">{{ formatMoney(item.total) }}</span>
									<span role="cell" class="employee-metric">{{ formatMoney(item.pagado) }}</span>
									<span role="cell" class="employee-metric">{{ formatMoney(item.pendiente) }}</span>
									<span role="cell" class="employee-table__action">
										<NcButton type="tertiary" @click.stop="selectClient(item.id)">
											{{ t('empleados', 'Details') }}
										</NcButton>
									</span>
								</div>
							</div>
						</div>
					</template>
				</div>

				<!-- Vista plana: cuando se ordena por Total u Outstanding -->
				<div v-else-if="sortedTable.length > 0" class="employee-table-wrap">
					<table class="employee-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Customer') }}</th>
								<th>{{ t('empleados', 'Total') }}</th>
								<th>{{ t('empleados', 'Collected') }}</th>
								<th>{{ t('empleados', 'Outstanding') }}</th>
								<th class="employee-table__action-heading">
									<span class="visually-hidden">{{ t('empleados', 'Details') }}</span>
								</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="row in sortedTable"
								:key="row.id"
								class="employee-row employee-row--clickable"
								@click="selectClient(row.id)">
								<td :data-label="t('empleados', 'Customer')" class="employee-cell">
									<strong>{{ row.nombre }}</strong>
								</td>
								<td :data-label="t('empleados', 'Total')" class="employee-metric">
									{{ formatMoney(row.total) }}
								</td>
								<td :data-label="t('empleados', 'Collected')" class="employee-metric">
									{{ formatMoney(row.pagado) }}
								</td>
								<td :data-label="t('empleados', 'Outstanding')" class="employee-metric">
									{{ formatMoney(row.pendiente) }}
								</td>
								<td class="employee-table__action">
									<NcButton type="tertiary" @click.stop="selectClient(row.id)">
										{{ t('empleados', 'Details') }}
									</NcButton>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div v-else class="inline-state">
					{{ t('empleados', 'No customers match the current filters.') }}
				</div>
			</section>
		</div>
	</div>
</template>

<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import ClienteLogo from '../../../components/clientes/ClienteLogo.vue'

export default {
	name: 'ClientesAnalyticsDashboard',

	components: {
		NcButton,
		NcLoadingIcon,
		ClienteLogo,
	},

	props: {
		resumen: {
			type: Object,
			default: () => ({}),
		},
		loading: {
			type: Boolean,
			default: false,
		},
		error: {
			type: String,
			default: '',
		},
	},

	emits: ['select-client'],

	data() {
		return {
			selectedCurrency: '',
			selectedServiceYear: 'all',
			sortKey: 'nombre',
			seccionesColapsadas: {
				grupos: false,
				individuales: false,
			},
		}
	},

	computed: {
		hasCatalog() {
			return Number(this.resumen?.catalogo?.total || 0) > 0
		},

		catalog() {
			return this.resumen?.catalogo || {}
		},

		catalogCards() {
			return [
				{
					key: 'total',
					label: t('empleados', 'Total records'),
					hint: t('empleados', 'Companies and groups'),
					value: this.catalog.total || 0,
					status: 'status-neutral',
				},
				{
					key: 'activos',
					label: t('empleados', 'Active'),
					hint: t('empleados', 'Enabled customers'),
					value: this.catalog.activos || 0,
					status: 'status-complete',
				},
				{
					key: 'grupos',
					label: t('empleados', 'Main groups'),
					hint: t('empleados', 'Parent companies'),
					value: this.catalog.grupos || 0,
					status: 'status-neutral',
				},
				{
					key: 'sub',
					label: t('empleados', 'Sub-companies'),
					hint: t('empleados', 'Linked companies'),
					value: this.catalog.subempresas || 0,
					status: 'status-neutral',
				},
			]
		},

		monedas() {
			return Array.isArray(this.resumen?.monedas) ? this.resumen.monedas : []
		},

		currencyOptions() {
			return this.monedas.map((row) => row.moneda).filter(Boolean)
		},

		activeCurrency() {
			if (this.monedas.length === 0) {
				return null
			}
			return this.monedas.find((row) => row.moneda === this.selectedCurrency) || this.monedas[0]
		},

		servicios() {
			return Array.isArray(this.resumen?.servicios) ? this.resumen.servicios : []
		},

		activeServiceGroup() {
			if (this.servicios.length === 0) {
				return null
			}
			return this.servicios.find((row) => row.moneda === this.selectedCurrency) || this.servicios[0]
		},

		serviceYearOptions() {
			const years = new Set()
			;(this.activeServiceGroup?.items || []).forEach((item) => {
				(item.anios || []).forEach((a) => {
					if (a.anio) years.add(a.anio)
				})
			})
			return Array.from(years).sort((a, b) => b - a)
		},

		serviceItemsDisplay() {
			const items = this.activeServiceGroup?.items || []
			const year = this.selectedServiceYear

			return items
				.map((item) => {
					let total = item.total
					if (year !== 'all') {
						const found = (item.anios || []).find((a) => String(a.anio) === String(year))
						total = found ? found.total : 0
					}
					return { ...item, displayTotal: total }
				})
				.filter((item) => year === 'all' || item.displayTotal > 0)
		},

		serviceTotalSum() {
			return this.serviceItemsDisplay.reduce((sum, item) => sum + Number(item.displayTotal || 0), 0)
		},

		pendingPercent() {
			return this.clampPercentage(this.activeCurrency?.porcentaje_pendiente || 0)
		},

		collectedPercent() {
			const total = Number(this.activeCurrency?.total || 0)
			if (total <= 0) {
				return 0
			}
			return this.clampPercentage((Number(this.activeCurrency?.pagado || 0) / total) * 100)
		},

		pendingRanking() {
			const rows = Array.isArray(this.resumen?.ranking_pendiente) ? this.resumen.ranking_pendiente : []
			const max = Math.max(...rows.map((row) => Number(row.pendiente || 0)), 0)
			return rows.map((row) => ({
				...row,
				relativeWidth: max > 0 ? Math.max(6, (Number(row.pendiente || 0) / max) * 100) : 0,
			}))
		},

		evolucion() {
			const rows = Array.isArray(this.resumen?.evolucion) ? this.resumen.evolucion : []
			const max = Math.max(...rows.flatMap((row) => [Number(row.generado || 0), Number(row.cobrado || 0)]), 0)
			return rows.map((row) => ({
				...row,
				generatedWidth: max > 0 ? (Number(row.generado || 0) / max) * 100 : 0,
				collectedWidth: max > 0 ? (Number(row.cobrado || 0) / max) * 100 : 0,
			}))
		},

		sortedTable() {
			const rows = [...(this.resumen?.tabla || [])]
			const key = this.sortKey
			rows.sort((a, b) => {
				if (key === 'nombre') {
					return String(a.nombre || '').localeCompare(String(b.nombre || ''), undefined, { sensitivity: 'base' })
				}
				return Number(b[key] || 0) - Number(a[key] || 0)
			})
			return rows
		},

		/** Agrupa clientes en Groups / Individual companies, con jerarquía padre-hijo
		 *  y totales acumulados (padre = suma de sí mismo + todos sus descendientes). */
		groupedSections() {
			const rows = [...(this.resumen?.tabla || [])]
			const idsInSet = new Set(rows.map((r) => Number(r.id)))
			const byParent = new Map()
			const byId = new Map(rows.map((r) => [Number(r.id), r]))

			rows.forEach((item) => {
				const rawParent = Number(item.cliente_padre || 0)
				const parentId = idsInSet.has(rawParent) ? rawParent : 0
				if (!byParent.has(parentId)) byParent.set(parentId, [])
				byParent.get(parentId).push(item)
			})

			const compareNames = (a, b) =>
				String(a.nombre || '').localeCompare(String(b.nombre || ''), undefined, { sensitivity: 'base' })

			const sumSubtree = (id) => {
				const own = byId.get(Number(id)) || {}
				const children = byParent.get(Number(id)) || []
				return children.reduce((acc, child) => {
					const childSum = sumSubtree(child.id)
					return {
						total: acc.total + childSum.total,
						pagado: acc.pagado + childSum.pagado,
						pendiente: acc.pendiente + childSum.pendiente,
					}
				}, {
					total: Number(own.total || 0),
					pagado: Number(own.pagado || 0),
					pendiente: Number(own.pendiente || 0),
				})
			}

			const buildRow = (item, level) => {
				const hasChildren = (byParent.get(Number(item.id)) || []).length > 0
				const values = hasChildren
					? sumSubtree(item.id)
					: {
						total: Number(item.total || 0),
						pagado: Number(item.pagado || 0),
						pendiente: Number(item.pendiente || 0),
					}
				return { ...item, ...values, level }
			}

			const flatten = (parentId, level) => {
				const children = (byParent.get(parentId) || []).slice().sort(compareNames)
				const out = []
				children.forEach((child) => {
					out.push(buildRow(child, level))
					out.push(...flatten(Number(child.id), level + 1))
				})
				return out
			}

			const roots = byParent.get(0) || []
			const gruposRoots = roots.filter((r) => (byParent.get(Number(r.id)) || []).length > 0)
			const individualesRoots = roots.filter((r) => (byParent.get(Number(r.id)) || []).length === 0)

			gruposRoots.sort(compareNames)
			individualesRoots.sort(compareNames)

			const gruposItems = gruposRoots.flatMap((root) => [
				buildRow(root, 0),
				...flatten(Number(root.id), 1),
			])

			const individualesItems = individualesRoots.map((root) => buildRow(root, 0))

			return [
				{
					key: 'grupos',
					label: t('empleados', 'Groups'),
					count: gruposRoots.length,
					collapsed: this.seccionesColapsadas.grupos,
					items: this.seccionesColapsadas.grupos ? [] : gruposItems,
				},
				{
					key: 'individuales',
					label: t('empleados', 'Individual companies'),
					count: individualesRoots.length,
					collapsed: this.seccionesColapsadas.individuales,
					items: this.seccionesColapsadas.individuales ? [] : individualesItems,
				},
			]
		},
	},

	watch: {
		currencyOptions: {
			immediate: true,
			handler(options) {
				if (!options.includes(this.selectedCurrency)) {
					this.selectedCurrency = options[0] || ''
				}
			},
		},
		serviceYearOptions(options) {
			if (this.selectedServiceYear !== 'all' && !options.map(String).includes(this.selectedServiceYear)) {
				this.selectedServiceYear = 'all'
			}
		},
	},

	methods: {
		t,

		selectClient(id) {
			const clientId = Number(id)
			if (!Number.isFinite(clientId) || clientId <= 0) {
				return
			}
			this.$emit('select-client', clientId)
		},

		formatMoney(value, currency = '') {
			const amount = Number(value || 0).toLocaleString('es-MX', {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			})
			return currency ? `${amount} ${currency}` : amount
		},

		formatPercent(value) {
			return `${Number(value || 0).toLocaleString('es-MX', {
				minimumFractionDigits: 1,
				maximumFractionDigits: 1,
			})}%`
		},

		clampPercentage(value) {
			const number = Number(value || 0)
			if (Number.isNaN(number) || number < 0) {
				return 0
			}
			return Math.min(100, number)
		},

		toggleSeccion(key) {
			this.seccionesColapsadas = {
				...this.seccionesColapsadas,
				[key]: !this.seccionesColapsadas[key],
			}
		},
	},
}
</script>

<style scoped>
.clientes-analytics-dashboard {
	--dash-radius: var(--border-radius-large);
	--dash-gap: 1.25rem;
	--dash-card-padding: 1.125rem;
	width: 100%;
	box-sizing: border-box;
	color: var(--color-main-text);
}

.clientes-analytics-dashboard,
.clientes-analytics-dashboard *,
.clientes-analytics-dashboard *::before,
.clientes-analytics-dashboard *::after {
	box-sizing: border-box;
}

.dashboard-content {
	display: grid;
	gap: var(--dash-gap);
}

.dashboard-state,
.dashboard-section,
.ranking-card {
	min-width: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--dash-radius);
	background: var(--color-main-background);
}

.dashboard-state {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 0.75rem;
	min-height: 11rem;
	padding: 1.5rem;
	color: var(--color-text-maxcontrast);
	text-align: center;
}

.dashboard-state--empty {
	flex-direction: column;
}

.dashboard-section,
.ranking-card {
	padding: var(--dash-card-padding);
}

.section-heading {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-start;
	justify-content: space-between;
	gap: 0.75rem 1rem;
	margin-bottom: 1rem;
}

.section-heading--compact,
.section-heading--with-control {
	margin-bottom: 0.75rem;
}

.section-heading h2,
.section-heading p {
	margin: 0;
}

.section-heading h2 {
	font-size: clamp(1rem, 0.85rem + 0.4vw, 1.2rem);
	line-height: 1.3;
}

.section-eyebrow {
	margin: 0 0 0.25rem !important;
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	font-weight: 700;
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.compliance-grid,
.rankings-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
	gap: 0.875rem;
}

.compliance-card,
.ranking-card {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	min-width: 0;
}

.compliance-card {
	padding: 1.125rem;
	border: 1px solid var(--color-border);
	border-left-width: 4px;
	border-radius: var(--dash-radius);
	background: var(--color-background-hover);
}

.status-complete { border-left-color: var(--color-success); }
.status-warning { border-left-color: var(--color-warning); }
.status-neutral { border-left-color: var(--color-text-maxcontrast); }

.compliance-card__heading {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 0.75rem;
}

.compliance-card__heading h3 {
	margin: 0;
	font-size: 0.92rem;
}

.compliance-card__percent {
	font-size: clamp(1rem, 0.9rem + 0.4vw, 1.3rem);
	line-height: 1.1;
	text-align: right;
}

.compliance-card__hours,
.compliance-card__footer,
.chart-note,
.inline-state {
	color: var(--color-text-maxcontrast);
	font-size: 0.82rem;
}

.compliance-card__footer {
	display: flex;
	flex-wrap: wrap;
	justify-content: space-between;
	gap: 0.4rem 0.75rem;
}

.progress-track,
.distribution-track,
.ranking-track {
	overflow: hidden;
	height: 0.5rem;
	border-radius: 999px;
	background: var(--color-background-darker);
}

.progress-value,
.distribution-value,
.ranking-value {
	height: 100%;
	border-radius: inherit;
	background: var(--color-primary-element);
}

.status-complete .progress-value,
.distribution-value--complete {
	background: var(--color-success);
}

.status-warning .progress-value {
	background: var(--color-warning);
}

.ranking-list,
.distribution-list {
	display: flex;
	flex-direction: column;
	gap: 0.65rem;
	margin: 0;
	padding: 0;
	list-style: none;
}

.ranking-item {
	display: grid;
	grid-template-columns: 1.5rem auto minmax(0, 1fr);
	gap: 0.6rem;
	align-items: center;
}

.ranking-item--clickable {
	cursor: pointer;
	padding: 0.25rem;
	border-radius: var(--border-radius);
}

.ranking-item--clickable:hover {
	background: var(--color-background-hover);
}

.ranking-item__heading {
	display: flex;
	justify-content: space-between;
	gap: 0.5rem;
	font-size: 0.85rem;
}

.ranking-position {
	color: var(--color-text-maxcontrast);
	font-weight: 700;
}

.distribution-item__heading {
	display: flex;
	justify-content: space-between;
	gap: 0.75rem;
	margin-bottom: 0.35rem;
	font-size: 0.85rem;
}

.distribution-item__label {
	display: flex;
	align-items: center;
	gap: 0.4rem;
}

.distribution-dot {
	width: 0.55rem;
	height: 0.55rem;
	border-radius: 50%;
	background: var(--color-primary-element);
}

.distribution-dot--complete,
.distribution-value--complete {
	background: #ff9100;
}

.compliance-mode__select {
	min-width: 9.375rem;
	height: 2rem;
	padding: 0 0.5rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.visually-hidden {
	position: absolute;
	width: 1px;
	height: 1px;
	overflow: hidden;
	clip: rect(0 0 0 0);
}

/* ── Vista plana (tabla real, orden por Total / Outstanding) ── */
.employee-table-wrap {
	overflow: auto;
}

.employee-table {
	width: 100%;
	border-collapse: collapse;
}

.employee-table th,
.employee-table td {
	padding: 0.65rem 0.5rem;
	border-bottom: 1px solid var(--color-border);
	text-align: left;
	font-size: 0.85rem;
}

.employee-metric {
	font-variant-numeric: tabular-nums;
	white-space: nowrap;
}

.employee-row--clickable {
	cursor: pointer;
}

.employee-row--clickable:hover {
	background: var(--color-background-hover);
}

.employee-cell {
	display: flex;
	align-items: center;
}

@media (max-width: 720px) {
	.employee-table-wrap {
		overflow: visible;
	}

	.employee-table {
		display: block;
		width: 100%;
		border-collapse: separate;
		border-spacing: 0;
	}

	.employee-table thead {
		display: none;
	}

	.employee-table tbody {
		display: flex;
		flex-direction: column;
		gap: 0.7rem;
	}

	.employee-row {
		display: flex !important;
		align-items: center;
		justify-content: space-between;
		gap: 0.85rem;
		padding: 1rem 1.1rem;
		border: 1px solid var(--color-border);
		border-radius: 14px;
		background: var(--color-background-hover);
		transition: border-color 0.15s ease, transform 0.15s ease;
	}

	.employee-row--clickable:active {
		transform: scale(0.99);
	}

	.employee-row:hover {
		border-color: var(--color-primary-element);
	}

	.employee-table td {
		padding: 0;
		border: 0;
	}

	.employee-table td.employee-metric,
	.employee-table th:not(:first-child):not(.employee-table__action-heading) {
		display: none !important;
	}

	.employee-cell strong {
		display: block;
		font-size: 1rem;
		font-weight: 600;
		letter-spacing: 0.01em;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.employee-table__action {
		flex: 0 0 auto;
	}

	.employee-table__action :deep(button) {
		white-space: nowrap;
		font-weight: 600;
		color: #000000;
	}
}

/* ── Vista agrupada: Groups / Individual companies con jerarquía ── */
.employee-grid {
	display: flex;
	flex-direction: column;
}

.employee-grid__head,
.employee-grid__row {
	display: grid;
	grid-template-columns: minmax(0, 2fr) 1fr 1fr 1fr auto;
	align-items: center;
	gap: 0.5rem;
	padding: 0.65rem 0.5rem;
	border-bottom: 1px solid var(--color-border);
	font-size: 0.85rem;
}

.employee-grid__head {
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.employee-grid__row--section {
	cursor: pointer;
	background: var(--color-background-hover);
	grid-template-columns: 1fr;
}

.employee-grid__row--section:hover {
	background: var(--color-background-darker);
}

.employee-grid__row--clickable {
	cursor: pointer;
}

.employee-grid__row--clickable:hover {
	background: var(--color-background-hover);
}

.employee-section-header {
	display: flex;
	align-items: center;
	gap: 0.4rem;
	font-size: 0.78rem;
	font-weight: 700;
	letter-spacing: 0.03em;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
}

.employee-section-icon {
	display: inline-block;
	transition: transform 0.2s ease;
}

.employee-section-icon--open {
	transform: rotate(90deg);
}

.employee-indent-marker {
	display: inline-block;
	margin-right: 0.3rem;
	color: var(--color-text-maxcontrast);
}

/* Transición limpia al colapsar/expandir, sin medir alturas en JS */
.employee-collapse {
	display: grid;
	grid-template-rows: 0fr;
	transition: grid-template-rows 0.25s ease;
}

.employee-collapse--open {
	grid-template-rows: 1fr;
}

.employee-collapse__inner {
	overflow: hidden;
	min-height: 0;
}

@media (max-width: 720px) {
	.employee-grid__head {
		display: none;
	}

	.employee-grid__row--clickable {
		grid-template-columns: 1fr auto;
		border: 1px solid var(--color-border);
		border-radius: 14px;
		background: var(--color-background-hover);
		margin-bottom: 0.5rem;
		padding: 1rem 1.1rem;
	}

	.employee-grid__row--clickable [role='cell'].employee-metric {
		display: none;
	}
}
</style>
