<template>
	<div class="admin-analytics-dashboard">
		<div v-if="loading" class="dashboard-state" role="status">
			<NcLoadingIcon :size="40" />
			<span>{{ t('empleados', 'Loading administrative report...') }}</span>
		</div>

		<div v-else-if="!hasAdministrativeData" class="dashboard-state dashboard-state--empty">
			<strong>{{ t('empleados', 'No reports for this period.') }}</strong>
			<span>{{ t('empleados', 'Try another period or change the report filters.') }}</span>
		</div>

		<div v-else class="dashboard-content">
			<section class="dashboard-section" aria-labelledby="admin-compliance-heading">
				<header class="section-heading">
					<div>
						<p class="section-eyebrow">
							{{ t('empleados', 'Compliance') }}
						</p>
						<h2 id="admin-compliance-heading">
							{{ t('empleados', 'Reported hours against expected hours') }}
						</h2>
					</div>
				</header>

				<div class="compliance-grid">
					<article
						v-for="item in complianceCards"
						:key="item.key"
						class="compliance-card"
						:class="complianceStatusClass(item)">
						<div class="compliance-card__heading">
							<div>
								<h3>{{ item.label }}</h3>
								<p>{{ item.range }}</p>
							</div>
							<strong class="compliance-card__percent">
								{{ formatPercent(item.percentage) }}
							</strong>
						</div>

						<p class="compliance-card__hours">
							<strong>{{ formatHours(item.reported) }}</strong>
							<span>/ {{ formatHours(item.expected) }}</span>
						</p>

						<div
							class="progress-track"
							role="progressbar"
							:aria-label="item.label"
							:aria-valuenow="clampPercentage(item.percentage)"
							aria-valuemin="0"
							aria-valuemax="100">
							<div
								class="progress-value"
								:style="{ width: `${clampPercentage(item.percentage)}%` }" />
						</div>

						<div class="compliance-card__footer">
							<span>{{ t('empleados', 'Reported') }}: {{ formatHours(item.reported) }}</span>
							<span>{{ t('empleados', 'Pending') }}: {{ formatHours(item.pending) }}</span>
						</div>
					</article>
				</div>
			</section>

			<section class="dashboard-section" aria-labelledby="admin-distribution-heading">
				<header class="section-heading">
					<div>
						<p class="section-eyebrow">
							{{ t('empleados', 'Distribution') }}
						</p>
						<h2 id="admin-distribution-heading">
							{{ t('empleados', 'Time distribution') }}
						</h2>
					</div>
				</header>

				<div class="distribution-card">
					<ul v-if="distributionItems.length > 0" class="distribution-list">
						<li
							v-for="item in distributionItems"
							:key="item.key"
							class="distribution-item">
							<div class="distribution-item__heading">
								<div class="distribution-item__label">
									<span class="distribution-dot" :class="`distribution-dot--${item.key}`" />
									<strong>{{ item.label }}</strong>
								</div>
								<span>{{ formatHours(item.hours) }} · {{ formatPercent(item.percentage) }}</span>
							</div>
							<div class="distribution-track">
								<div
									class="distribution-value"
									:class="`distribution-value--${item.key}`"
									:style="{ width: `${clampPercentage(item.percentage)}%` }" />
							</div>
						</li>
					</ul>

					<div v-else-if="distributionEmptyMessages.length === 0" class="inline-state">
						{{ t('empleados', 'No reported time is available for this selection.') }}
					</div>

					<div v-if="distributionEmptyMessages.length > 0" class="distribution-empty-messages">
						<p v-for="message in distributionEmptyMessages" :key="message">
							{{ message }}
						</p>
					</div>
				</div>
			</section>

			<section class="rankings-grid" :aria-label="t('empleados', 'Administrative time rankings')">
				<article v-if="mostrarClientes" class="ranking-card">
					<header class="section-heading section-heading--compact">
						<div>
							<p class="section-eyebrow">
								{{ t('empleados', 'Client work') }}
							</p>
							<h2>{{ t('empleados', 'Time by client') }}</h2>
						</div>
					</header>

					<ol v-if="clientRanking.length > 0" class="ranking-list">
						<li v-for="(item, index) in clientRanking" :key="item.key" class="ranking-item">
							<span class="ranking-position">{{ index + 1 }}</span>
							<div class="ranking-item__content">
								<div class="ranking-item__heading">
									<strong>{{ item.label }}</strong>
									<span>{{ formatHours(item.hours) }}</span>
								</div>
								<div class="ranking-track">
									<div class="ranking-value ranking-value--client" :style="{ width: `${item.relativeWidth}%` }" />
								</div>
								<small>{{ formatReports(item.reports) }}</small>
							</div>
						</li>
					</ol>
					<div v-else class="inline-state">
						{{ t('empleados', 'No client work for this selection.') }}
					</div>
				</article>

				<article class="ranking-card" :class="{ 'ranking-card--wide': !mostrarClientes }">
					<header class="section-heading section-heading--compact">
						<div>
							<p class="section-eyebrow">
								{{ t('empleados', 'Internal work') }}
							</p>
							<h2>{{ t('empleados', 'Time by internal activity') }}</h2>
						</div>
					</header>

					<ol v-if="internalActivityRanking.length > 0" class="ranking-list">
						<li v-for="(item, index) in internalActivityRanking" :key="item.key" class="ranking-item">
							<span class="ranking-position">{{ index + 1 }}</span>
							<div class="ranking-item__content">
								<div class="ranking-item__heading">
									<strong>{{ item.label }}</strong>
									<span>{{ formatHours(item.hours) }}</span>
								</div>
								<div class="ranking-track">
									<div class="ranking-value ranking-value--internal" :style="{ width: `${item.relativeWidth}%` }" />
								</div>
								<small>{{ formatReports(item.reports) }}</small>
							</div>
						</li>
					</ol>
					<div v-else class="inline-state">
						{{ t('empleados', 'No internal work for this selection.') }}
					</div>
				</article>
			</section>

			<section class="dashboard-section dashboard-section--compact" aria-labelledby="admin-employee-compliance-heading">
				<header class="section-heading section-heading--compact section-heading--with-control">
					<div>
						<p class="section-eyebrow">
							{{ t('empleados', 'Employees') }}
						</p>
						<h2 id="admin-employee-compliance-heading">
							{{ t('empleados', 'Compliance by employee') }}
						</h2>
					</div>
					<label class="compliance-mode">
						<span class="visually-hidden">{{ t('empleados', 'Compliance view') }}</span>
						<select v-model="complianceViewMode" class="compliance-mode__select">
							<option value="attention">
								{{ t('empleados', 'Needs attention') }}
							</option>
							<option value="all">
								{{ t('empleados', 'All employees') }}
							</option>
						</select>
					</label>
				</header>

				<div v-if="employeeComplianceVisible.length > 0" class="compliance-list-wrap">
					<ul class="compliance-list" role="list">
						<li
							v-for="employee in employeeComplianceVisible"
							:key="employee.key"
							class="compliance-list__item"
							:class="complianceStatusClass(employee)">
							<div class="compliance-list__meta">
								<strong class="compliance-list__name">{{ employee.name }}</strong>
								<span class="compliance-list__percent">{{ formatPercent(employee.percentage) }}</span>
							</div>
							<div
								class="compliance-list__track"
								role="progressbar"
								:aria-valuenow="clampPercentage(employee.percentage)"
								aria-valuemin="0"
								aria-valuemax="100"
								:aria-label="employee.name">
								<div
									class="compliance-list__value"
									:style="{ width: `${clampPercentage(employee.percentage)}%` }" />
							</div>
						</li>
					</ul>
					<p
						v-if="complianceViewMode === 'attention' && employeeCompliance.length > employeeComplianceVisible.length"
						class="chart-note">
						{{ t('empleados', 'Showing the {shown} employees with the lowest compliance out of {total}.', {
							shown: employeeComplianceVisible.length,
							total: employeeCompliance.length,
						}) }}
					</p>
				</div>
				<div v-else class="inline-state inline-state--compact">
					{{ t('empleados', 'No employee compliance data for this selection.') }}
				</div>
			</section>

			<section class="dashboard-section dashboard-section--compact" aria-labelledby="admin-employees-heading">
				<header class="section-heading section-heading--compact">
					<div>
						<p class="section-eyebrow">
							{{ t('empleados', 'Summary') }}
						</p>
						<h2 id="admin-employees-heading">
							{{ t('empleados', 'Employee summary') }}
						</h2>
					</div>
				</header>

				<div v-if="employeeRows.length > 0" class="employee-table-wrap">
					<table class="employee-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Employee') }}</th>
								<th>{{ t('empleados', 'Expected') }}</th>
								<th>{{ t('empleados', 'Reported') }}</th>
								<th>{{ t('empleados', 'Pending') }}</th>
								<th>{{ t('empleados', 'Compliance') }}</th>
								<th class="employee-table__action-heading">
									<span class="visually-hidden">{{ t('empleados', 'Details') }}</span>
								</th>
							</tr>
						</thead>
						<tbody>
							<template v-for="employee in employeeRows">
								<tr
									:key="employee.key"
									class="employee-row"
									:class="{ 'employee-row--expanded': isEmployeeExpanded(employee.key) }">
									<td :data-label="t('empleados', 'Employee')" class="employee-cell">
										<strong>{{ employee.name }}</strong>
										<small>{{ employee.area }}</small>
									</td>
									<td :data-label="t('empleados', 'Expected')" class="employee-metric">
										{{ formatHours(employee.expected) }}
									</td>
									<td :data-label="t('empleados', 'Reported')" class="employee-metric">
										{{ formatHours(employee.reported) }}
									</td>
									<td :data-label="t('empleados', 'Pending')" class="employee-metric">
										{{ formatHours(employee.pending) }}
									</td>
									<td :data-label="t('empleados', 'Compliance')" class="employee-compliance-cell">
										<div class="mini-compliance" :class="complianceStatusClass(employee)">
											<span class="mini-compliance__percent">
												{{ formatPercent(employee.percentage) }}
											</span>
											<span class="mini-compliance__track" aria-hidden="true">
												<span
													class="mini-compliance__value"
													:style="{ width: `${clampPercentage(employee.percentage)}%` }" />
											</span>
										</div>
									</td>
									<td class="employee-table__action">
										<button
											type="button"
											class="employee-expand-button"
											:aria-expanded="isEmployeeExpanded(employee.key) ? 'true' : 'false'"
											:aria-label="t('empleados', 'Toggle details for {employee}', { employee: employee.name })"
											@click="toggleEmployeeExpand(employee.key)">
											<ChevronUp v-if="isEmployeeExpanded(employee.key)" :size="18" />
											<ChevronDown v-else :size="18" />
										</button>
									</td>
								</tr>
								<tr
									v-if="isEmployeeExpanded(employee.key)"
									:key="`${employee.key}-detail`"
									class="employee-detail-row">
									<td colspan="6">
										<div class="employee-detail">
											<div class="employee-detail__contexts">
												<div class="employee-detail__item">
													<span>{{ t('empleados', 'Selected period') }}</span>
													<strong>{{ formatCompactPercent(employee.contexts.periodo) }}</strong>
												</div>
												<div class="employee-detail__item">
													<span>{{ t('empleados', 'Fortnight') }}</span>
													<strong>{{ formatCompactPercent(employee.contexts.quincena) }}</strong>
												</div>
												<div class="employee-detail__item">
													<span>{{ t('empleados', 'Month') }}</span>
													<strong>{{ formatCompactPercent(employee.contexts.mes) }}</strong>
												</div>
											</div>
											<div class="employee-detail__extras">
												<div v-if="mostrarClientes" class="employee-detail__item">
													<span>{{ t('empleados', 'Client work') }}</span>
													<strong>{{ formatHours(employee.clientHours) }}</strong>
												</div>
												<div class="employee-detail__item">
													<span>{{ t('empleados', 'Internal work') }}</span>
													<strong>{{ formatHours(employee.internalHours) }}</strong>
												</div>
												<div v-if="mostrarClientes" class="employee-detail__item">
													<span>{{ t('empleados', 'Main client') }}</span>
													<strong>{{ employee.mainClient }}</strong>
												</div>
												<div class="employee-detail__item">
													<span>{{ t('empleados', 'Main activity') }}</span>
													<strong>{{ employee.mainActivity }}</strong>
												</div>
											</div>
											<div class="employee-detail__actions">
												<NcButton
													type="tertiary"
													:disabled="employee.id === null"
													@click="$emit('select-employee', employee.id)">
													{{ t('empleados', 'View details') }}
												</NcButton>
											</div>
										</div>
									</td>
								</tr>
							</template>
						</tbody>
					</table>
				</div>
				<div v-else class="inline-state inline-state--compact">
					{{ t('empleados', 'No employee data for this selection.') }}
				</div>
			</section>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'

const ATTENTION_LIMIT = 12

export default {
	name: 'AdminAnalyticsDashboard',

	components: {
		NcButton,
		NcLoadingIcon,
		ChevronDown,
		ChevronUp,
	},

	props: {
		resumen: {
			type: Object,
			required: false,
			default: null,
		},
		loading: {
			type: Boolean,
			required: false,
			default: false,
		},
		mostrarClientes: {
			type: Boolean,
			required: false,
			default: true,
		},
		mostrarAusencias: {
			type: Boolean,
			required: false,
			default: true,
		},
	},

	data() {
		return {
			complianceViewMode: 'attention',
			expandedEmployeeKeys: {},
		}
	},

	computed: {
		complianceCards() {
			return [
				this.normalizeComplianceContext('periodo', t('empleados', 'Selected period')),
				this.normalizeComplianceContext('quincena', t('empleados', 'Fortnight')),
				this.normalizeComplianceContext('mes', t('empleados', 'Month')),
			]
		},

		hasAdministrativeData() {
			if (!this.resumen || typeof this.resumen !== 'object') {
				return false
			}

			const complianceHasData = this.complianceCards.some(item => (
				item.expected > 0 || item.reported > 0 || item.pending > 0
			))
			const distributionHasData = this.distributionItems.length > 0

			return complianceHasData
				|| distributionHasData
				|| (this.mostrarClientes && this.clientRanking.length > 0)
				|| this.internalActivityRanking.length > 0
				|| this.employeeCompliance.length > 0
				|| this.employeeRows.length > 0
		},

		rawDistributionItems() {
			const distribution = this.resumen?.distribucion || {}
			const rawItems = [
				{
					key: 'client',
					label: t('empleados', 'Client work'),
					raw: distribution.client_work,
					visible: this.mostrarClientes,
				},
				{
					key: 'internal',
					label: t('empleados', 'Internal work'),
					raw: distribution.internal_work,
					visible: true,
				},
				{
					key: 'absence',
					label: t('empleados', 'Absences'),
					raw: distribution.ausencias,
					visible: this.mostrarAusencias,
				},
			]
			const visibleHours = rawItems
				.filter(item => item.visible)
				.reduce((total, item) => total + this.toNumber(item.raw?.horas), 0)

			return rawItems.map(item => {
				const hours = this.toNumber(item.raw?.horas)
				const percentage = visibleHours > 0 ? (hours / visibleHours) * 100 : 0

				return {
					key: item.key,
					label: item.label,
					hours,
					percentage,
					visible: item.visible,
				}
			})
		},

		distributionItems() {
			return this.rawDistributionItems.filter(item => item.visible && item.hours > 0)
		},

		distributionEmptyMessages() {
			const messages = []
			const byKey = new Map(this.rawDistributionItems.map(item => [item.key, item]))

			if (this.mostrarClientes && !(byKey.get('client')?.hours > 0)) {
				messages.push(t('empleados', 'No client work for this selection.'))
			}
			if (!(byKey.get('internal')?.hours > 0)) {
				messages.push(t('empleados', 'No internal work for this selection.'))
			}

			return messages
		},

		clientRanking() {
			return this.normalizeRanking(
				this.resumen?.graficas?.horas_por_cliente,
				'id_cliente',
				t('empleados', 'Client'),
			)
		},

		internalActivityRanking() {
			return this.normalizeRanking(
				this.resumen?.graficas?.actividades_internas,
				'id_actividad',
				t('empleados', 'Activity'),
			)
		},

		employeeCompliance() {
			const graphRows = Array.isArray(this.resumen?.graficas?.cumplimiento_empleados)
				? this.resumen.graficas.cumplimiento_empleados
				: []
			const source = graphRows.length > 0
				? graphRows
				: (Array.isArray(this.resumen?.empleados) ? this.resumen.empleados : [])

			return source
				.filter(row => row && typeof row === 'object')
				.map((row, index) => ({
					key: String(row.id_empleado ?? row.id ?? `employee-${index}`),
					id: this.normalizeId(row.id_empleado ?? row.id),
					name: this.safeText(row.nombre, t('empleados', 'Employee')),
					area: this.safeText(row.area, t('empleados', 'No area')),
					percentage: this.toNumber(row.porcentaje_cumplimiento),
					reported: this.toNumber(row.horas_reportadas),
					expected: this.toNumber(row.horas_esperadas),
				}))
				.sort((a, b) => a.percentage - b.percentage || a.name.localeCompare(b.name))
		},

		employeeComplianceVisible() {
			if (this.complianceViewMode === 'all') {
				return this.employeeCompliance
			}
			return this.employeeCompliance.slice(0, ATTENTION_LIMIT)
		},

		employeeRows() {
			const rows = Array.isArray(this.resumen?.empleados) ? this.resumen.empleados : []

			return rows
				.filter(row => row && typeof row === 'object')
				.map((row, index) => {
					const id = this.normalizeId(row.id_empleado ?? row.id)
					const contexts = row.cumplimiento || {}
					const percentage = this.toNumber(row.porcentaje_cumplimiento)
					const periodPercentage = this.extractContextPercentage(contexts.periodo)

					return {
						key: String(id ?? `employee-row-${index}`),
						id,
						name: this.safeText(row.nombre, t('empleados', 'Employee')),
						area: this.safeText(row.area, t('empleados', 'No area')),
						expected: this.toNumber(row.horas_esperadas),
						reported: this.toNumber(row.horas_reportadas),
						pending: this.toNumber(row.horas_pendientes),
						percentage,
						clientHours: this.toNumber(row.horas_cliente),
						internalHours: this.toNumber(row.horas_internas),
						mainClient: this.safeText(row.cliente_principal),
						mainActivity: this.safeText(row.actividad_principal),
						contexts: {
							periodo: periodPercentage ?? percentage,
							quincena: this.extractContextPercentage(contexts.quincena),
							mes: this.extractContextPercentage(contexts.mes),
						},
					}
				})
				.sort((a, b) => a.percentage - b.percentage || a.name.localeCompare(b.name))
		},
	},

	watch: {
		resumen() {
			this.expandedEmployeeKeys = {}
		},
	},

	methods: {
		t,

		isEmployeeExpanded(key) {
			return Boolean(this.expandedEmployeeKeys[key])
		},

		toggleEmployeeExpand(key) {
			this.$set(this.expandedEmployeeKeys, key, !this.expandedEmployeeKeys[key])
		},

		normalizeComplianceContext(key, label) {
			const raw = this.resumen?.cumplimiento?.[key] || {}

			return {
				key,
				label,
				range: this.formatDateRange(raw.fecha_inicio, raw.fecha_fin),
				expected: this.toNumber(raw.horas_esperadas),
				reported: this.toNumber(raw.horas_reportadas),
				pending: this.toNumber(raw.horas_pendientes),
				percentage: this.toNumber(raw.porcentaje_cumplimiento),
			}
		},

		normalizeRanking(rows, idField, fallbackLabel) {
			const source = Array.isArray(rows) ? rows : []
			const normalized = source
				.filter(row => row && typeof row === 'object')
				.map((row, index) => ({
					key: String(row[idField] ?? `${idField}-${index}`),
					label: this.safeText(row.nombre, fallbackLabel),
					hours: this.toNumber(row.horas),
					reports: this.toNumber(row.total_reportes),
				}))
				.filter(item => item.hours > 0 || item.reports > 0)
				.sort((a, b) => b.hours - a.hours || a.label.localeCompare(b.label))
			const maxHours = normalized.reduce((max, item) => Math.max(max, item.hours), 0)

			return normalized.map(item => ({
				...item,
				relativeWidth: maxHours > 0
					? Math.max(2, Math.min(100, (item.hours / maxHours) * 100))
					: 0,
			}))
		},

		extractContextPercentage(context) {
			if (context && typeof context === 'object') {
				return this.hasNumericValue(context.porcentaje_cumplimiento)
					? this.toNumber(context.porcentaje_cumplimiento)
					: null
			}

			return this.hasNumericValue(context) ? this.toNumber(context) : null
		},

		hasNumericValue(value) {
			if (typeof value === 'number') {
				return Number.isFinite(value)
			}
			if (value === null || value === undefined || value === '') {
				return false
			}

			const normalized = String(value)
				.trim()
				.replace(/\s/g, '')
				.replace(',', '.')
				.replace(/[^\d.-]/g, '')

			return normalized !== '' && Number.isFinite(Number(normalized))
		},

		toNumber(value) {
			if (typeof value === 'number') {
				return Number.isFinite(value) ? value : 0
			}
			if (value === null || value === undefined || value === '') {
				return 0
			}

			const normalized = String(value)
				.trim()
				.replace(/\s/g, '')
				.replace(',', '.')
				.replace(/[^\d.-]/g, '')
			const number = Number(normalized)

			return Number.isFinite(number) ? number : 0
		},

		normalizeId(value) {
			if (value === null || value === undefined || value === '') {
				return null
			}

			const number = Number(value)
			return Number.isFinite(number) ? number : String(value)
		},

		safeText(value, fallback = '—') {
			const raw = value && typeof value === 'object'
				? value.nombre ?? value.label ?? value.name ?? ''
				: value
			const text = String(raw ?? '').trim()
			return text || fallback
		},

		formatNumber(value, maximumFractionDigits = 2) {
			return new Intl.NumberFormat('es-MX', {
				maximumFractionDigits,
			}).format(this.toNumber(value))
		},

		formatHours(value) {
			return `${this.formatNumber(value)} h`
		},

		formatPercent(value) {
			return `${this.formatNumber(value, 1)}%`
		},

		formatCompactPercent(value) {
			return this.hasNumericValue(value) ? this.formatPercent(value) : '—'
		},

		formatReports(value) {
			return t('empleados', '{count} reports', {
				count: new Intl.NumberFormat('es-MX').format(Math.max(0, Math.round(this.toNumber(value)))),
			})
		},

		formatDateRange(start, end) {
			const startLabel = this.formatDate(start)
			const endLabel = this.formatDate(end)

			if (startLabel && endLabel) {
				return startLabel === endLabel ? startLabel : `${startLabel} – ${endLabel}`
			}
			return startLabel || endLabel || t('empleados', 'No period available')
		},

		formatDate(value) {
			if (!value) {
				return ''
			}

			const text = String(value)
			const match = text.match(/^(\d{4})-(\d{2})-(\d{2})/)
			const date = match
				? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), 12)
				: new Date(value)

			if (Number.isNaN(date.getTime())) {
				return ''
			}

			return new Intl.DateTimeFormat('es-MX', {
				day: '2-digit',
				month: 'short',
				year: 'numeric',
			}).format(date)
		},

		clampPercentage(value) {
			return Math.min(100, Math.max(0, this.toNumber(value)))
		},

		complianceStatusClass(item) {
			const percentage = this.toNumber(item?.percentage)
			const hasNoGoal = Object.prototype.hasOwnProperty.call(item || {}, 'expected')
				&& this.toNumber(item?.expected) <= 0
				&& this.toNumber(item?.reported) <= 0

			if (hasNoGoal) {
				return 'status-neutral'
			}

			if (percentage >= 100) {
				return 'status-complete'
			}
			if (percentage >= 70) {
				return 'status-warning'
			}
			return 'status-attention'
		},
	},
}
</script>

<style scoped>
.admin-analytics-dashboard {
	width: 100%;
	box-sizing: border-box;
	color: var(--color-main-text);
}

.dashboard-content {
	display: grid;
	gap: 20px;
}

.dashboard-state,
.dashboard-section,
.ranking-card,
.distribution-card {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.dashboard-state {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 12px;
	min-height: 180px;
	padding: 24px;
	color: var(--color-text-maxcontrast);
	text-align: center;
}

.dashboard-state--empty {
	flex-direction: column;
}

.dashboard-state--empty strong {
	color: var(--color-main-text);
	font-size: 1.1rem;
}

.dashboard-section,
.ranking-card {
	min-width: 0;
	padding: 20px;
}

.section-heading {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	margin-bottom: 16px;
}

.section-heading--compact {
	margin-bottom: 12px;
}

.section-heading h2,
.section-heading p {
	margin: 0;
}

.section-heading h2 {
	font-size: 1.15rem;
	line-height: 1.3;
}

.section-eyebrow {
	margin-bottom: 4px !important;
	color: var(--color-text-maxcontrast);
	font-size: .75rem;
	font-weight: 700;
	letter-spacing: .04em;
	text-transform: uppercase;
}

.compliance-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 14px;
}

.compliance-card {
	display: flex;
	flex-direction: column;
	gap: 14px;
	min-width: 0;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-left-width: 4px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.compliance-card.status-complete {
	border-left-color: var(--color-success);
}

.compliance-card.status-warning {
	border-left-color: var(--color-warning);
}

.compliance-card.status-attention {
	border-left-color: var(--color-error);
}

.compliance-card.status-neutral {
	border-left-color: var(--color-text-maxcontrast);
}

.compliance-card__heading {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
}

.compliance-card__heading h3,
.compliance-card__heading p,
.compliance-card__hours {
	margin: 0;
}

.compliance-card__heading h3 {
	font-size: .95rem;
}

.compliance-card__heading p {
	margin-top: 3px;
	color: var(--color-text-maxcontrast);
	font-size: .78rem;
}

.compliance-card__percent {
	font-size: 1.45rem;
	line-height: 1;
}

.compliance-card__hours {
	display: flex;
	align-items: baseline;
	gap: 5px;
}

.compliance-card__hours strong {
	font-size: 1.35rem;
}

.compliance-card__hours span,
.compliance-card__footer {
	color: var(--color-text-maxcontrast);
}

.progress-track,
.distribution-track,
.ranking-track {
	overflow: hidden;
	border-radius: 999px;
	background: var(--color-background-darker);
}

.progress-track {
	height: 9px;
}

.progress-value {
	height: 100%;
	border-radius: inherit;
	background: var(--color-primary-element);
}

.status-complete .progress-value {
	background: var(--color-success);
}

.status-warning .progress-value {
	background: var(--color-warning);
}

.status-attention .progress-value {
	background: var(--color-error);
}

.status-neutral .progress-value {
	background: var(--color-text-maxcontrast);
}

.compliance-card__footer {
	display: flex;
	flex-wrap: wrap;
	justify-content: space-between;
	gap: 6px 12px;
	font-size: .8rem;
}

.distribution-card {
	padding: 18px;
	background: var(--color-background-hover);
}

.distribution-list,
.ranking-list {
	margin: 0;
	padding: 0;
	list-style: none;
}

.distribution-list {
	display: grid;
	gap: 16px;
}

.distribution-item {
	display: grid;
	gap: 7px;
}

.distribution-item__heading,
.distribution-item__label,
.ranking-item,
.ranking-item__heading {
	display: flex;
	align-items: center;
}

.distribution-item__heading,
.ranking-item__heading {
	justify-content: space-between;
	gap: 12px;
}

.distribution-item__label {
	gap: 8px;
}

.distribution-dot {
	width: 10px;
	height: 10px;
	border-radius: 50%;
	background: var(--color-primary-element);
}

.distribution-dot--internal,
.distribution-value--internal {
	background: var(--color-success);
}

.distribution-dot--absence,
.distribution-value--absence {
	background: var(--color-warning);
}

.distribution-track {
	height: 10px;
}

.distribution-value {
	height: 100%;
	border-radius: inherit;
	background: var(--color-primary-element);
}

.distribution-empty-messages {
	display: grid;
	gap: 4px;
	margin-top: 14px;
	color: var(--color-text-maxcontrast);
	font-size: .85rem;
}

.distribution-empty-messages p {
	margin: 0;
}

.section-heading h2 + p {
	margin-top: 5px;
	color: var(--color-text-maxcontrast);
	font-size: .9rem;
}

.rankings-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 20px;
}

.ranking-card--wide {
	grid-column: 1 / -1;
}

.ranking-list {
	display: grid;
	gap: 4px;
	max-height: 520px;
	overflow: auto;
}

.ranking-item {
	align-items: flex-start;
	gap: 10px;
	padding: 10px 0;
	border-bottom: 1px solid var(--color-border);
}

.ranking-item:last-child {
	border-bottom: 0;
}

.ranking-position {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 26px;
	width: 26px;
	height: 26px;
	border-radius: 50%;
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: .78rem;
	font-weight: 700;
}

.ranking-item__content {
	display: grid;
	flex: 1;
	gap: 6px;
	min-width: 0;
}

.ranking-item__heading strong {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.ranking-item__heading span,
.ranking-item small {
	color: var(--color-text-maxcontrast);
}

.ranking-track {
	height: 7px;
}

.ranking-value {
	height: 100%;
	border-radius: inherit;
}

.ranking-value--client {
	background: var(--color-primary-element);
}

.ranking-value--internal {
	background: var(--color-success);
}

.inline-state {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 90px;
	padding: 16px;
	border: 1px dashed var(--color-border);
	border-radius: var(--border-radius-large);
	color: var(--color-text-maxcontrast);
	text-align: center;
}

.inline-state--compact {
	min-height: 56px;
	padding: 12px;
}

.dashboard-section--compact {
	padding-top: 14px;
	padding-bottom: 14px;
}

.section-heading--with-control {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
}

.compliance-mode {
	flex: 0 0 auto;
	margin: 0;
}

.compliance-mode__select {
	min-width: 150px;
	height: 32px;
	padding: 0 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: .82rem;
}

.compliance-list-wrap {
	max-height: 360px;
	overflow: auto;
	padding-right: 2px;
}

.compliance-list {
	display: flex;
	flex-direction: column;
	gap: 4px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.compliance-list__item {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(90px, 140px);
	gap: 8px 12px;
	align-items: center;
	min-height: 32px;
	padding: 4px 6px;
	border-radius: var(--border-radius);
}

.compliance-list__item:hover {
	background: var(--color-background-hover);
}

.compliance-list__meta {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	gap: 10px;
	min-width: 0;
}

.compliance-list__name {
	overflow: hidden;
	font-size: .85rem;
	font-weight: 600;
	line-height: 1.2;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.compliance-list__percent {
	flex: 0 0 auto;
	font-size: .82rem;
	font-weight: 700;
	font-variant-numeric: tabular-nums;
}

.compliance-list__item.status-complete .compliance-list__percent {
	color: var(--color-success);
}

.compliance-list__item.status-warning .compliance-list__percent {
	color: var(--color-warning);
}

.compliance-list__item.status-attention .compliance-list__percent {
	color: var(--color-error);
}

.compliance-list__item.status-neutral .compliance-list__percent {
	color: var(--color-text-maxcontrast);
}

.compliance-list__track,
.mini-compliance__track {
	overflow: hidden;
	border-radius: 999px;
	background: var(--color-background-darker);
}

.compliance-list__track {
	height: 7px;
}

.compliance-list__value,
.mini-compliance__value {
	display: block;
	height: 100%;
	border-radius: inherit;
	background: var(--color-error);
	min-width: 0;
}

.compliance-list__item.status-complete .compliance-list__value {
	background: var(--color-success);
}

.compliance-list__item.status-warning .compliance-list__value {
	background: var(--color-warning);
}

.compliance-list__item.status-attention .compliance-list__value {
	background: var(--color-error);
}

.compliance-list__item.status-neutral .compliance-list__value {
	background: var(--color-text-maxcontrast);
}

.chart-note {
	margin: 8px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: .78rem;
}

.employee-table-wrap {
	max-height: 420px;
	overflow: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}

.employee-table {
	width: 100%;
	min-width: 640px;
	border-collapse: collapse;
	background: var(--color-main-background);
}

.employee-table th,
.employee-table td {
	padding: 6px 10px;
	border-bottom: 1px solid var(--color-border);
	text-align: left;
	vertical-align: middle;
}

.employee-table th {
	position: sticky;
	top: 0;
	z-index: 2;
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
	font-size: .7rem;
	font-weight: 700;
	letter-spacing: .03em;
	text-transform: uppercase;
}

.employee-row td {
	height: 44px;
}

.employee-detail-row td {
	padding: 0 10px 8px;
	background: var(--color-background-hover);
	border-bottom: 1px solid var(--color-border);
	height: auto;
}

.employee-row:last-child td {
	border-bottom: 0;
}

.employee-row:hover {
	background: var(--color-background-hover);
}

.employee-cell {
	min-width: 150px;
	max-width: 220px;
}

.employee-cell strong,
.employee-cell small {
	display: block;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.employee-cell strong {
	font-size: .85rem;
	line-height: 1.2;
}

.employee-cell small {
	margin-top: 1px;
	color: var(--color-text-maxcontrast);
	font-size: .7rem;
	line-height: 1.2;
}

.employee-metric {
	font-size: .82rem;
	font-variant-numeric: tabular-nums;
	white-space: nowrap;
}

.employee-compliance-cell {
	min-width: 110px;
}

.mini-compliance {
	display: flex;
	align-items: center;
	gap: 8px;
	min-width: 0;
}

.mini-compliance__percent {
	flex: 0 0 auto;
	min-width: 2.6em;
	font-size: .82rem;
	font-weight: 700;
	font-variant-numeric: tabular-nums;
}

.mini-compliance.status-complete .mini-compliance__percent {
	color: var(--color-success);
}

.mini-compliance.status-warning .mini-compliance__percent {
	color: var(--color-warning);
}

.mini-compliance.status-attention .mini-compliance__percent {
	color: var(--color-error);
}

.mini-compliance.status-neutral .mini-compliance__percent {
	color: var(--color-text-maxcontrast);
}

.mini-compliance__track {
	flex: 1 1 auto;
	height: 6px;
	min-width: 48px;
}

.mini-compliance.status-complete .mini-compliance__value {
	background: var(--color-success);
}

.mini-compliance.status-warning .mini-compliance__value {
	background: var(--color-warning);
}

.mini-compliance.status-attention .mini-compliance__value {
	background: var(--color-error);
}

.mini-compliance.status-neutral .mini-compliance__value {
	background: var(--color-text-maxcontrast);
}

.employee-table__action-heading,
.employee-table__action {
	width: 40px;
	text-align: right !important;
}

.employee-expand-button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	padding: 0;
	border: 0;
	border-radius: var(--border-radius);
	background: transparent;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
}

.employee-expand-button:hover {
	background: var(--color-background-dark);
	color: var(--color-main-text);
}

.employee-detail {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 8px 4px 4px;
}

.employee-detail__contexts,
.employee-detail__extras {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
	gap: 6px 12px;
}

.employee-detail__item {
	display: flex;
	flex-direction: column;
	gap: 1px;
	min-width: 0;
}

.employee-detail__item span {
	color: var(--color-text-maxcontrast);
	font-size: .68rem;
	font-weight: 700;
	letter-spacing: .03em;
	text-transform: uppercase;
}

.employee-detail__item strong {
	overflow: hidden;
	font-size: .82rem;
	font-weight: 600;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.employee-detail__actions {
	display: flex;
	justify-content: flex-end;
}

.visually-hidden {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	white-space: nowrap;
	border: 0;
}

@media (max-width: 1000px) {
	.compliance-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.compliance-card:last-child {
		grid-column: 1 / -1;
	}
}

@media (max-width: 760px) {
	.rankings-grid,
	.compliance-grid {
		grid-template-columns: 1fr;
	}

	.compliance-card:last-child,
	.ranking-card--wide {
		grid-column: auto;
	}

	.dashboard-section,
	.ranking-card {
		padding: 16px;
	}

	.section-heading--with-control {
		flex-direction: column;
		align-items: stretch;
	}

	.compliance-list__item {
		grid-template-columns: 1fr;
		gap: 4px;
	}

	.employee-table-wrap {
		max-height: none;
		overflow: visible;
		border: 0;
	}

	.employee-table {
		min-width: 0;
	}

	.employee-table,
	.employee-table tbody,
	.employee-table tr,
	.employee-table td {
		display: block;
		width: 100%;
		min-width: 0;
		box-sizing: border-box;
	}

	.employee-table thead {
		display: none;
	}

	.employee-table tbody {
		display: grid;
		gap: 8px;
	}

	.employee-row {
		padding: 6px 10px;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius);
		background: var(--color-main-background);
	}

	.employee-detail-row {
		border: 1px solid var(--color-border);
		border-top: 0;
		border-radius: 0 0 var(--border-radius) var(--border-radius);
		margin-top: -8px;
		background: var(--color-background-hover);
	}

	.employee-detail-row td {
		display: block;
		padding: 8px 10px 10px;
		border: 0;
	}

	.employee-detail-row td::before {
		content: none;
	}

	.employee-row td {
		display: grid;
		grid-template-columns: minmax(90px, .7fr) minmax(0, 1.3fr);
		gap: 8px;
		height: auto;
		padding: 5px 0;
		border-bottom: 1px solid var(--color-border);
		text-align: right;
	}

	.employee-row td::before {
		content: attr(data-label);
		color: var(--color-text-maxcontrast);
		font-size: .7rem;
		font-weight: 700;
		text-align: left;
		text-transform: uppercase;
	}

	.employee-row td:last-child {
		border-bottom: 0;
	}

	.employee-cell,
	.employee-table__action {
		text-align: right !important;
	}

	.employee-table__action::before {
		content: none !important;
	}

	.employee-cell strong,
	.employee-cell small {
		white-space: normal;
	}
}

@media (max-width: 480px) {
	.compliance-card__heading,
	.distribution-item__heading,
	.ranking-item__heading {
		align-items: flex-start;
		flex-direction: column;
	}

	.compliance-card__footer {
		flex-direction: column;
	}
}
</style>
