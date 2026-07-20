<!-- eslint-disable object-curly-newline -->
<template>
	<div class="contenedor">
		<section class="hero-grid">
			<div class="hero-card hero-card-main">
				<div class="eyebrow">
					{{ t('empleados', 'Period details') }}
				</div>
				<h2 class="hero-title">
					{{ t('empleados', 'Report analysis and operational distribution') }}
				</h2>
				<p class="hero-copy">
					{{ t('empleados', 'Understand where time is spent, which customers consume the most capacity and where estimated labor cost is concentrated.') }}
				</p>
				<div class="hero-stats">
					<div class="hero-stat">
						<span class="hero-stat-label">{{ t('empleados', 'Hours') }}</span>
						<strong class="hero-stat-value">{{ kpisFmt.horas_reportadas }}</strong>
					</div>
					<div class="hero-stat">
						<span class="hero-stat-label">{{ t('empleados', 'Cost') }}</span>
						<strong class="hero-stat-value">{{ kpisFmt.costo_total }}</strong>
					</div>
					<div class="hero-stat">
						<span class="hero-stat-label">{{ t('empleados', 'Reports') }}</span>
						<strong class="hero-stat-value">{{ kpisFmt.total_reportes }}</strong>
					</div>

					<div class="hero-stat">
						<span class="hero-stat-label">{{ t('empleados', 'Projects (customers)') }}</span>
						<strong class="hero-stat-value">{{ kpisFmt.proyectos_activos }}</strong>
					</div>

					<div class="hero-stat">
						<span class="hero-stat-label">{{ t('empleados', 'Activities') }}</span>
						<strong class="hero-stat-value">{{ kpisFmt.actividades }}</strong>
					</div>

					<div class="hero-stat">
						<span class="hero-stat-label">{{ t('empleados', 'Average per report') }}</span>
						<strong class="hero-stat-value">{{ kpisFmt.promedio_horas_reporte }}</strong>
					</div>

					<div class="hero-stat">
						<span class="hero-stat-label">{{ t('empleados', 'Cost per hour') }}</span>
						<strong class="hero-stat-value">{{ kpisFmt.costo_hora }}</strong>
					</div>

					<div class="hero-stat">
						<span class="hero-stat-label">{{ t('empleados', 'Billable base') }}</span>
						<strong class="hero-stat-value">{{ kpisFmt.horas_cargables }}</strong>
					</div>
				</div>
			</div>
		</section>

		<section class="decision-grid">
			<article class="decision-card">
				<span>{{ t('empleados', 'Highest cost customer') }}</span>
				<strong>{{ decisionFmt.topCliente }}</strong>
				<small>{{ decisionFmt.topClienteDetalle }}</small>
			</article>

			<article class="decision-card">
				<span>{{ t('empleados', 'Top 3 concentration') }}</span>
				<strong>{{ decisionFmt.concentracionTop3 }}</strong>
				<small>{{ t('empleados', 'Share of total estimated cost') }}</small>
			</article>

			<article class="decision-card">
				<span>{{ t('empleados', 'Cost per report') }}</span>
				<strong>{{ decisionFmt.costoPorReporte }}</strong>
				<small>{{ t('empleados', 'Average labor cost by submitted report') }}</small>
			</article>

			<article class="decision-card" :class="{ muted: !hasBillableField }">
				<span>{{ t('empleados', 'Billable classification') }}</span>
				<strong>{{ decisionFmt.cargables }}</strong>
				<small>{{ decisionFmt.cargablesDetalle }}</small>
			</article>
		</section>

		<section class="chart-controls">
			<div class="control-group">
				<label for="detalles-chart-limit">{{ t('empleados', 'Show') }}</label>
				<select
					id="detalles-chart-limit"
					v-model.number="chartLimit"
					@change="renderGraficas">
					<option
						v-for="option in chartLimitOptions"
						:key="option.value"
						:value="option.value">
						{{ option.label }}
					</option>
				</select>
			</div>

			<div class="control-group">
				<label for="detalles-company-focus">{{ t('empleados', 'Company') }}</label>
				<select
					id="detalles-company-focus"
					v-model="selectedCliente"
					@change="renderGraficas">
					<option :value="null">
						{{ t('empleados', 'All companies') }}
					</option>
					<option
						v-for="cliente in graficaProyectos"
						:key="cliente.key"
						:value="cliente.label">
						{{ cliente.label }}
					</option>
				</select>
			</div>

			<div class="control-group">
				<label for="detalles-activity-focus">{{ t('empleados', 'Activity') }}</label>
				<select
					id="detalles-activity-focus"
					v-model="selectedActividad"
					@change="renderGraficas">
					<option :value="null">
						{{ t('empleados', 'All activities') }}
					</option>
					<option
						v-for="actividad in graficaActividades"
						:key="actividad.key"
						:value="actividad.label">
						{{ actividad.label }}
					</option>
				</select>
			</div>

			<button
				type="button"
				class="clear-focus"
				:disabled="!selectedCliente && !selectedActividad && chartLimit === 10"
				@click="clearChartFocus">
				{{ t('empleados', 'Clear focus') }}
			</button>
		</section>

		<section class="charts-grid">
			<article class="panel">
				<div class="panel-heading">
					<div>
						<div class="panel-eyebrow">
							{{ t('empleados', 'Customer value') }}
						</div>
						<h3 class="panel-title">
							{{ t('empleados', 'Cost and hours by company') }}
						</h3>
						<p class="panel-copy">
							{{ t('empleados', 'Compare labor cost, reported hours and share of attention by customer.') }}
						</p>
					</div>
				</div>
				<div class="chart-box">
					<canvas ref="chartProyectos" />
				</div>
			</article>

			<article class="panel">
				<div class="panel-heading">
					<div>
						<div class="panel-eyebrow">
							{{ t('empleados', 'Time spend') }}
						</div>
						<h3 class="panel-title">
							{{ t('empleados', 'Activities consuming capacity') }}
						</h3>
						<p class="panel-copy">
							{{ t('empleados', 'See which type of work is taking the most hours and budget.') }}
						</p>
					</div>
				</div>
				<div class="chart-box">
					<canvas ref="chartActividades" />
				</div>
			</article>

			<article class="panel">
				<div class="panel-heading">
					<div>
						<div class="panel-eyebrow">
							{{ t('empleados', 'Portfolio') }}
						</div>
						<h3 class="panel-title">
							{{ t('empleados', 'Customer decision matrix') }}
						</h3>
						<p class="panel-copy">
							{{ t('empleados', 'Customers farther right and higher up consume more time and estimated cost.') }}
						</p>
					</div>
				</div>
				<div class="chart-box">
					<canvas ref="chartClienteMatriz" />
				</div>
			</article>

			<article class="panel">
				<div class="panel-heading">
					<div>
						<div class="panel-eyebrow">
							{{ t('empleados', 'Operational cross-check') }}
						</div>
						<h3 class="panel-title">
							{{ t('empleados', 'Project vs activity') }}
						</h3>
						<p class="panel-copy">
							{{ t('empleados', 'Identify what each customer is actually consuming: support, operations, implementation or other activities.') }}
						</p>
					</div>
				</div>
				<div class="chart-box chart-box-large">
					<canvas ref="chartProyectoActividad" />
				</div>
			</article>

			<article class="panel">
				<div class="panel-heading">
					<div>
						<div class="panel-eyebrow">
							{{ t('empleados', 'Trend') }}
						</div>
						<h3 class="panel-title">
							{{ t('empleados', 'Hours per day') }}
						</h3>
						<p class="panel-copy">
							{{ t('empleados', 'Detect spikes and recurring workload pressure across the selected period.') }}
						</p>
					</div>
				</div>
				<div class="chart-box">
					<canvas ref="chartHorasDia" />
				</div>
			</article>

			<article v-if="rankingClientes.length > 0" class="panel panel-full">
				<div class="panel-heading">
					<div>
						<div class="panel-eyebrow">
							{{ t('empleados', 'Executive ranking') }}
						</div>
						<h3 class="panel-title">
							{{ t('empleados', 'Customers by estimated labor cost') }}
						</h3>
						<p class="panel-copy">
							{{ t('empleados', 'Use this table to discuss pricing, prioritization and capacity allocation by customer.') }}
						</p>
					</div>
				</div>
				<div class="ranking-table-wrap">
					<table class="ranking-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Customer') }}</th>
								<th>{{ t('empleados', 'Hours') }}</th>
								<th>{{ t('empleados', 'Estimated cost') }}</th>
								<th>{{ t('empleados', 'Share') }}</th>
								<th>{{ t('empleados', 'Reports') }}</th>
								<th>{{ t('empleados', 'Cost/report') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="cliente in rankingClientes" :key="cliente.key">
								<td>
									<strong>{{ cliente.label }}</strong>
									<span>{{ cliente.actividadPrincipal }}</span>
								</td>
								<td>{{ formatNumber(cliente.total) }} h</td>
								<td>{{ formatMoney(cliente.costo) }}</td>
								<td>
									<div class="share-cell">
										<span>{{ formatPercent(cliente.porcentaje) }}</span>
										<div class="share-track">
											<div
												class="share-value"
												:style="{ width: `${Math.min(cliente.porcentaje, 100)}%` }" />
										</div>
									</div>
								</td>
								<td>{{ formatInteger(cliente.reportes) }}</td>
								<td>{{ formatMoney(cliente.costoPorReporte) }}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</article>

			<article v-if="select.length > 0" class="panel panel-full">
				<div class="panel-heading">
					<div>
						<div class="panel-eyebrow">
							{{ t('empleados', 'Transactional detail') }}
						</div>
						<h3 class="panel-title">
							{{ t('empleados', 'Period reports') }}
						</h3>
					</div>
					<div class="panel-badge">
						{{ t('empleados', '{count} records', { count: historial.length }) }}
					</div>
				</div>
				<div class="details-list-wrap">
					<VirtualList
						class="details-list"
						:data-sources="historial"
						:data-key="'id_reporte'"
						:data-component="rowComponent"
						:extra-props="{ proyectosList, actividadesList }" />
				</div>
			</article>
		</section>
	</div>
</template>

<script>

import { translate as t } from '@nextcloud/l10n'
import ReportRow from '../../Helpers/Lists/ReportRow.vue'
import VirtualList from 'vue-virtual-scroll-list'

// eslint-disable-next-line import/no-named-as-default
import Chart from 'chart.js/auto'

import {
// NcTextField,
} from '@nextcloud/vue'

export default {
	name: 'AdminDetalles',

	components: {
		// NcTextField,
		VirtualList,
	},

	props: {
		select: { type: Array, required: true },
		sueldo: { type: Number, required: false, default: 0 },
		actividadesList: { type: Array, required: false, default: () => [] },
		proyectosList: { type: Array, required: false, default: () => [] },
	},

	data() {
		return {
			rowComponent: ReportRow,
			horasreportadas: '',
			costototal: '',
			proyectosactivos: '',
			actividades: '',

			chartProyectosInstance: null,
			chartActividadesInstance: null,
			chartHorasDiaInstance: null,
			chartProyectoActividadInstance: null,
			chartClienteMatrizInstance: null,
			chartLimit: 10,
			selectedCliente: null,
			selectedActividad: null,
		}
	},

	computed: {
		chartLimitOptions() {
			return [
				{ value: 5, label: t('empleados', 'Top 5') },
				{ value: 10, label: t('empleados', 'Top 10') },
				{ value: 15, label: t('empleados', 'Top 15') },
				{ value: 0, label: t('empleados', 'All') },
			]
		},

		historial() {
			const arr = Array.isArray(this.select) ? this.select : []

			const actividadesMap = new Map(
				(this.actividadesList || []).map(c => [Number(c.id), c.name || c.nombre || c.label]),
			)

			const clientesMap = new Map(
				(this.proyectosList || []).map(a => [Number(a.id), a.label || a.nombre || a.name]),
			)

			return arr
				.filter(r => r && typeof r === 'object')
				.map((r, i) => {
					const rawId = r.id_reporte ?? r.idReporte ?? r.Id_reporte ?? r.id ?? i
					const id = String(rawId)

					const idCliente = r.id_cliente ?? r.idCliente ?? r.Id_cliente ?? null
					const idActividad = r.id_actividad ?? r.idActividad ?? r.Id_actividad ?? null

					const esAusencia = Number(idCliente) === 99999 || Number(idActividad) === 99999

					const clienteNombre = esAusencia
						? t('empleados', 'Módulo de Ausencia')
						: (clientesMap.get(Number(idCliente)) || `Cliente ${idCliente ?? ''}`.trim())

					const actividadNombre = esAusencia
						? t('empleados', 'Ausencia')
						: (actividadesMap.get(Number(idActividad)) || `Actividad ${idActividad ?? ''}`.trim())

					return {
						...r,
						id,
						idCliente,
						idActividad,
						clienteNombre,
						actividadNombre,
						esAusencia,
					}
				})
		},

		kpis() {
			const arr = Array.isArray(this.select) ? this.select : []

			const toNum = (v) => {
				if (v === null || v === undefined) return 0
				const s = String(v).trim().replace(',', '.').replace(/[^\d.-]/g, '')
				const x = Number(s)
				return Number.isFinite(x) ? x : 0
			}

			let minutos = 0
			let minutosCargables = 0
			const proyectos = new Set()
			const actividades = new Set()

			for (const it of arr) {
				const itemMinutos = toNum(it?.tiempo_registrado)
				minutos += itemMinutos
				if (this.isBillableReport(it)) {
					minutosCargables += itemMinutos
				}
				if (it?.id_cliente != null) proyectos.add(String(it.id_cliente))
				if (it?.id_actividad != null) actividades.add(String(it.id_actividad))
			}

			const horas = minutos / 60
			const horasCargables = this.hasBillableField ? minutosCargables / 60 : horas
			const sueldoHora = toNum(this.sueldo)
			const costo = horas * sueldoHora

			const totalReportes = arr.length
			const promedioHorasReporte = totalReportes > 0
				? horas / totalReportes
				: 0

			return {
				horas_reportadas: horas,
				costo_total: costo,
				proyectos_activos: proyectos.size,
				actividades: actividades.size,
				total_reportes: totalReportes,
				promedio_horas_reporte: promedioHorasReporte,
				costo_hora: sueldoHora,
				horas_cargables: horasCargables,
				costo_por_reporte: totalReportes > 0 ? costo / totalReportes : 0,
			}
		},

		kpisFmt() {
			const num2 = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 })
			const int = new Intl.NumberFormat('es-MX')
			const money = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' })

			return {
				horas_reportadas: num2.format(this.kpis.horas_reportadas || 0),
				costo_total: money.format(this.kpis.costo_total || 0),
				proyectos_activos: int.format(this.kpis.proyectos_activos || 0),
				actividades: int.format(this.kpis.actividades || 0),
				total_reportes: int.format(this.kpis.total_reportes || 0),
				promedio_horas_reporte: `${num2.format(this.kpis.promedio_horas_reporte || 0)} h`,
				costo_hora: money.format(this.kpis.costo_hora || 0),
				horas_cargables: `${num2.format(this.kpis.horas_cargables || 0)} h`,
			}
		},

		hasBillableField() {
			return this.historial.some(reporte => [
				'cargable',
				'es_cargable',
				'facturable',
				'es_facturable',
				'billable',
				'is_billable',
			].some(field => reporte[field] !== undefined && reporte[field] !== null))
		},

		rankingClientes() {
			return this.graficaProyectos.map((cliente) => {
				const actividadPrincipal = this.actividadPrincipalPorCliente(cliente.key)

				return {
					...cliente,
					actividadPrincipal,
					costoPorReporte: cliente.reportes > 0 ? cliente.costo / cliente.reportes : 0,
				}
			})
		},

		decisionFmt() {
			const money = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' })
			const num2 = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 })
			const percent = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 })
			const topCliente = this.rankingClientes[0] || null
			const top3Costo = this.rankingClientes
				.slice(0, 3)
				.reduce((total, cliente) => total + cliente.costo, 0)
			const concentracionTop3 = this.kpis.costo_total > 0
				? (top3Costo / this.kpis.costo_total) * 100
				: 0

			return {
				topCliente: topCliente?.label || t('empleados', 'No data'),
				topClienteDetalle: topCliente
					? t('empleados', '{hours} h · {cost}', {
						hours: num2.format(topCliente.total || 0),
						cost: money.format(topCliente.costo || 0),
					})
					: t('empleados', 'Select an employee to analyze customer cost.'),
				concentracionTop3: `${percent.format(concentracionTop3)}%`,
				costoPorReporte: money.format(this.kpis.costo_por_reporte || 0),
				cargables: this.hasBillableField
					? `${num2.format(this.kpis.horas_cargables || 0)} h`
					: t('empleados', 'Not classified'),
				cargablesDetalle: this.hasBillableField
					? t('empleados', 'Uses the billable/facturable flag present in reports.')
					: t('empleados', 'All reported hours are shown as decision base until reports include a billable flag.'),
			}
		},

		graficaProyectos() {
			return this.agruparReportes('idCliente', 'clienteNombre')
		},

		graficaActividades() {
			return this.agruparReportes('idActividad', 'actividadNombre')
		},

		proyectosVisibles() {
			const datos = this.selectedCliente
				? this.graficaProyectos.filter(item => item.label === this.selectedCliente)
				: this.graficaProyectos

			return this.chartLimit > 0 ? datos.slice(0, this.chartLimit) : datos
		},

		actividadesVisibles() {
			const datos = this.selectedActividad
				? this.graficaActividades.filter(item => item.label === this.selectedActividad)
				: this.graficaActividades

			return this.chartLimit > 0 ? datos.slice(0, this.chartLimit) : datos
		},

		graficaHorasPorDia() {
			const acc = new Map()

			for (const r of this.historial) {
				const fechaRaw = r.fecha_registro
			?? r.fechaRegistro
			?? r.created_at
			?? null

				const fecha = fechaRaw
					? String(fechaRaw).slice(0, 10)
					: 'Sin fecha'

				const minutos = this.toNum(r.tiempo_registrado)
				const horas = minutos / 60

				if (!acc.has(fecha)) {
					acc.set(fecha, {
						fecha,
						total: 0,
						reportes: 0,
					})
				}

				acc.get(fecha).total += horas
				acc.get(fecha).reportes++
			}

			return Array.from(acc.values())
				.sort((a, b) => String(a.fecha).localeCompare(String(b.fecha)))
		},
		graficaProyectoActividad() {
			const proyectosMap = new Map()
			const actividadesSet = new Set()

			for (const r of this.historial) {
				const proyecto = r.clienteNombre || 'Sin proyecto'
				const actividad = r.actividadNombre || 'Sin actividad'

				const minutos = this.toNum(r.tiempo_registrado)
				const horas = minutos / 60

				if (this.selectedCliente && proyecto !== this.selectedCliente) {
					continue
				}

				if (this.selectedActividad && actividad !== this.selectedActividad) {
					continue
				}

				actividadesSet.add(actividad)

				if (!proyectosMap.has(proyecto)) {
					proyectosMap.set(proyecto, new Map())
				}

				const actividadMap = proyectosMap.get(proyecto)
				actividadMap.set(actividad, (actividadMap.get(actividad) || 0) + horas)
			}

			const proyectos = Array.from(proyectosMap.keys())
				.sort((a, b) => {
					const totalA = Array.from(proyectosMap.get(a)?.values() || [])
						.reduce((total, value) => total + value, 0)
					const totalB = Array.from(proyectosMap.get(b)?.values() || [])
						.reduce((total, value) => total + value, 0)

					return totalB - totalA
				})
				.slice(0, this.chartLimit > 0 ? this.chartLimit : undefined)
			const actividades = Array.from(actividadesSet.values())
				.slice(0, this.chartLimit > 0 ? this.chartLimit : undefined)

			const datasets = actividades.map(actividad => ({
				label: actividad,
				data: proyectos.map(proyecto => {
					const actividadMap = proyectosMap.get(proyecto)
					return Number((actividadMap.get(actividad) || 0).toFixed(2))
				}),
			}))

			return {
				proyectos,
				actividades,
				datasets,
			}
		},
	},

	watch: {
		select: {
			deep: true,
			handler() {
				this.$nextTick(() => {
					this.renderGraficas()
				})
			},
		},

		proyectosList: {
			deep: true,
			handler() {
				this.$nextTick(() => {
					this.renderGraficas()
				})
			},
		},

		actividadesList: {
			deep: true,
			handler() {
				this.$nextTick(() => {
					this.renderGraficas()
				})
			},
		},
	},

	mounted() {
		this.$nextTick(() => {
			this.renderGraficas()
		})
	},

	beforeDestroy() {
		this.destruirGraficas()
	},

	methods: {
		t,
		toNum(v) {
			if (v === null || v === undefined) return 0

			const s = String(v)
				.trim()
				.replace(',', '.')
				.replace(/[^\d.-]/g, '')

			const x = Number(s)
			return Number.isFinite(x) ? x : 0
		},

		agruparReportes(idCampo, nombreCampo) {
			const acc = new Map()
			const sueldoHora = this.toNum(this.sueldo)
			const totalHorasGeneral = this.kpis.horas_reportadas || 0

			for (const r of this.historial) {
				const id = r[idCampo] ?? 'sin-id'
				const label = r[nombreCampo] || `${t('empleados', 'No')} ${nombreCampo}`

				const minutos = this.toNum(r.tiempo_registrado)
				const horas = minutos / 60
				const costo = horas * sueldoHora

				if (!acc.has(String(id))) {
					acc.set(String(id), {
						key: String(id),
						label,
						total: 0,
						costo: 0,
						reportes: 0,
						porcentaje: 0,
					})
				}

				const item = acc.get(String(id))
				item.total += horas
				item.costo += costo
				item.reportes += 1
			}

			const datos = Array.from(acc.values())
				.map(item => ({
					...item,
					porcentaje: totalHorasGeneral > 0
						? (item.total / totalHorasGeneral) * 100
						: 0,
				}))
				.sort((a, b) => b.total - a.total)

			return datos
		},

		actividadPrincipalPorCliente(clienteKey) {
			const acc = new Map()

			for (const r of this.historial) {
				const id = r.idCliente ?? 'sin-id'

				if (String(id) !== String(clienteKey)) {
					continue
				}

				const actividad = r.actividadNombre || t('empleados', 'No activity')
				const horas = this.toNum(r.tiempo_registrado) / 60

				acc.set(actividad, (acc.get(actividad) || 0) + horas)
			}

			const top = Array.from(acc.entries())
				.sort((a, b) => b[1] - a[1])[0]

			if (!top) {
				return t('empleados', 'No activity detail')
			}

			return t('empleados', 'Main activity: {activity}', { activity: top[0] })
		},

		isBillableReport(reporte) {
			const fields = [
				'cargable',
				'es_cargable',
				'facturable',
				'es_facturable',
				'billable',
				'is_billable',
			]

			for (const field of fields) {
				if (reporte?.[field] === undefined || reporte?.[field] === null) {
					continue
				}

				const value = reporte[field]

				if (typeof value === 'boolean') {
					return value
				}

				const normalized = String(value).trim().toLowerCase()

				return ['1', 'true', 'si', 'sí', 'yes', 'y'].includes(normalized)
			}

			return false
		},

		formatNumber(value) {
			return new Intl.NumberFormat('es-MX', {
				maximumFractionDigits: 2,
			}).format(Number(value) || 0)
		},

		formatInteger(value) {
			return new Intl.NumberFormat('es-MX').format(Number(value) || 0)
		},

		formatMoney(value) {
			return new Intl.NumberFormat('es-MX', {
				style: 'currency',
				currency: 'MXN',
			}).format(Number(value) || 0)
		},

		formatPercent(value) {
			return `${new Intl.NumberFormat('es-MX', {
				maximumFractionDigits: 1,
			}).format(Number(value) || 0)}%`
		},

		renderGraficas() {
			this.renderGraficaProyectos()
			this.renderGraficaActividades()
			this.renderGraficaClienteMatriz()
			this.renderGraficaHorasDia()
			this.renderGraficaProyectoActividad()
		},

		clearChartFocus() {
			this.chartLimit = 10
			this.selectedCliente = null
			this.selectedActividad = null
			this.$nextTick(() => {
				this.renderGraficas()
			})
		},

		renderGraficaProyectos() {
			if (!this.$refs.chartProyectos) return

			if (this.chartProyectosInstance) {
				this.chartProyectosInstance.destroy()
			}

			const datos = this.proyectosVisibles

			this.chartProyectosInstance = new Chart(this.$refs.chartProyectos, {
				type: 'bar',
				data: {
					labels: datos.map(x => x.label),
					datasets: [
						{
							label: t('empleados', 'Estimated cost'),
							data: datos.map(x => Number(x.costo.toFixed(2))),
							backgroundColor: 'rgba(37, 99, 235, 0.76)',
							borderColor: 'rgba(37, 99, 235, 1)',
							borderRadius: 6,
							borderWidth: 1,
							xAxisID: 'xCost',
						},
						{
							label: t('empleados', 'Hours'),
							data: datos.map(x => Number(x.total.toFixed(2))),
							backgroundColor: 'rgba(20, 184, 166, 0.62)',
							borderColor: 'rgba(13, 148, 136, 1)',
							borderRadius: 6,
							borderWidth: 1,
							xAxisID: 'xHours',
						},
					],
				},
				options: {
					indexAxis: 'y',
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							display: true,
							position: 'bottom',
						},
						tooltip: {
							callbacks: {
								label(context) {
									const item = datos[context.dataIndex]

									const horas = new Intl.NumberFormat('es-MX', {
										maximumFractionDigits: 2,
									}).format(item.total || 0)

									const costo = new Intl.NumberFormat('es-MX', {
										style: 'currency',
										currency: 'MXN',
									}).format(item.costo || 0)

									const porcentaje = new Intl.NumberFormat('es-MX', {
										maximumFractionDigits: 2,
									}).format(item.porcentaje || 0)

									return [
										t('empleados', 'Hours: {hours}', { hours: horas }),
										t('empleados', 'Cost: {cost}', { cost: costo }),
										t('empleados', 'Reports: {reports}', { reports: item.reportes }),
										t('empleados', 'Share: {percent}%', { percent: porcentaje }),
									]
								},
							},
						},
					},
					onClick: (_event, elements) => {
						const index = elements?.[0]?.index

						if (index === undefined) {
							return
						}

						this.selectedCliente = datos[index]?.label || null
						this.$nextTick(() => {
							this.renderGraficas()
						})
					},
					scales: {
						xCost: {
							position: 'bottom',
							beginAtZero: true,
							grid: {
								color: 'rgba(148, 163, 184, 0.18)',
							},
							ticks: {
								callback: value => this.formatMoney(value),
							},
						},
						xHours: {
							position: 'top',
							beginAtZero: true,
							grid: {
								drawOnChartArea: false,
							},
							ticks: {
								callback: value => `${value} h`,
							},
						},
						y: {
							ticks: {
								autoSkip: false,
							},
						},
					},
				},
			})
		},

		renderGraficaActividades() {
			if (!this.$refs.chartActividades) return

			if (this.chartActividadesInstance) {
				this.chartActividadesInstance.destroy()
			}

			const datos = this.actividadesVisibles

			this.chartActividadesInstance = new Chart(this.$refs.chartActividades, {
				type: 'bar',
				data: {
					labels: datos.map(x => x.label),
					datasets: [
						{
							label: t('empleados', 'Hours by activity'),
							data: datos.map(x => Number(x.total.toFixed(2))),
							backgroundColor: 'rgba(124, 58, 237, 0.72)',
							borderColor: 'rgba(109, 40, 217, 1)',
							borderRadius: 6,
							borderWidth: 1,
						},
					],
				},
				options: {
					indexAxis: 'y',
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							position: 'bottom',
						},
						tooltip: {
							callbacks: {
								label(context) {
									const item = datos[context.dataIndex]
									const costo = new Intl.NumberFormat('es-MX', {
										style: 'currency',
										currency: 'MXN',
									}).format(item.costo || 0)
									const porcentaje = new Intl.NumberFormat('es-MX', {
										maximumFractionDigits: 1,
									}).format(item.porcentaje || 0)

									return [
										t('empleados', 'Hours: {hours}', { hours: context.raw }),
										t('empleados', 'Cost: {cost}', { cost: costo }),
										t('empleados', 'Share: {percent}%', { percent: porcentaje }),
									]
								},
							},
						},
					},
					onClick: (_event, elements) => {
						const index = elements?.[0]?.index

						if (index === undefined) {
							return
						}

						this.selectedActividad = datos[index]?.label || null
						this.$nextTick(() => {
							this.renderGraficas()
						})
					},
					scales: {
						x: {
							beginAtZero: true,
							ticks: {
								callback: value => `${value} h`,
							},
						},
						y: {
							ticks: {
								autoSkip: false,
							},
						},
					},
				},
			})
		},

		renderGraficaClienteMatriz() {
			if (!this.$refs.chartClienteMatriz) return

			if (this.chartClienteMatrizInstance) {
				this.chartClienteMatrizInstance.destroy()
			}

			const datos = this.selectedCliente
				? this.rankingClientes.filter(item => item.label === this.selectedCliente)
				: (this.chartLimit > 0 ? this.rankingClientes.slice(0, this.chartLimit) : this.rankingClientes)
			const maxReportes = Math.max(...datos.map(x => x.reportes), 1)

			this.chartClienteMatrizInstance = new Chart(this.$refs.chartClienteMatriz, {
				type: 'bubble',
				data: {
					datasets: datos.map((cliente, index) => ({
						label: cliente.label,
						data: [{
							x: Number(cliente.total.toFixed(2)),
							y: Number(cliente.costo.toFixed(2)),
							r: Math.max(7, Math.min(24, 7 + (cliente.reportes / maxReportes) * 17)),
						}],
						backgroundColor: `hsla(${(index * 47) % 360}, 72%, 52%, 0.62)`,
						borderColor: `hsla(${(index * 47) % 360}, 72%, 38%, 1)`,
						borderWidth: 1,
					})),
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							position: 'bottom',
							labels: {
								boxWidth: 10,
							},
						},
						tooltip: {
							callbacks: {
								label: (context) => {
									const item = datos[context.datasetIndex]

									return [
										item.label,
										t('empleados', 'Hours: {hours}', { hours: this.formatNumber(item.total) }),
										t('empleados', 'Cost: {cost}', { cost: this.formatMoney(item.costo) }),
										t('empleados', 'Reports: {reports}', { reports: item.reportes }),
									]
								},
							},
						},
					},
					scales: {
						x: {
							beginAtZero: true,
							title: {
								display: true,
								text: t('empleados', 'Reported hours'),
							},
						},
						y: {
							beginAtZero: true,
							title: {
								display: true,
								text: t('empleados', 'Estimated labor cost'),
							},
							ticks: {
								callback: value => this.formatMoney(value),
							},
						},
					},
				},
			})
		},

		renderGraficaHorasDia() {
			if (!this.$refs.chartHorasDia) return

			if (this.chartHorasDiaInstance) {
				this.chartHorasDiaInstance.destroy()
			}

			const datos = this.graficaHorasPorDia

			this.chartHorasDiaInstance = new Chart(this.$refs.chartHorasDia, {
				type: 'line',
				data: {
					labels: datos.map(x => x.fecha),
					datasets: [
						{
							label: 'Horas por día',
							data: datos.map(x => Number(x.total.toFixed(2))),
							tension: 0.3,
							fill: false,
						},
					],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							display: true,
						},
						tooltip: {
							callbacks: {
								label(context) {
									const item = datos[context.dataIndex]
									return `${context.raw} horas en ${item.reportes} reporte(s)`
								},
							},
						},
					},
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								precision: 0,
							},
						},
					},
				},
			})
		},
		renderGraficaProyectoActividad() {
			if (!this.$refs.chartProyectoActividad) return

			if (this.chartProyectoActividadInstance) {
				this.chartProyectoActividadInstance.destroy()
			}

			const datos = this.graficaProyectoActividad

			this.chartProyectoActividadInstance = new Chart(this.$refs.chartProyectoActividad, {
				type: 'bar',
				data: {
					labels: datos.proyectos,
					datasets: datos.datasets,
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							position: 'bottom',
						},
						tooltip: {
							callbacks: {
								label(context) {
									return `${context.dataset.label}: ${context.raw} horas`
								},
							},
						},
					},
					scales: {
						x: {
							stacked: true,
						},
						y: {
							stacked: true,
							beginAtZero: true,
							ticks: {
								precision: 0,
							},
						},
					},
				},
			})
		},

		destruirGraficas() {
			if (this.chartProyectosInstance) {
				this.chartProyectosInstance.destroy()
				this.chartProyectosInstance = null
			}

			if (this.chartActividadesInstance) {
				this.chartActividadesInstance.destroy()
				this.chartActividadesInstance = null
			}
			if (this.chartHorasDiaInstance) {
				this.chartHorasDiaInstance.destroy()
				this.chartHorasDiaInstance = null
			}
			if (this.chartProyectoActividadInstance) {
				this.chartProyectoActividadInstance.destroy()
				this.chartProyectoActividadInstance = null
			}
			if (this.chartClienteMatrizInstance) {
				this.chartClienteMatrizInstance.destroy()
				this.chartClienteMatrizInstance = null
			}
		},
	},
}
</script>

<style scoped>
.contenedor {
	margin: 20px 10px 0;
	display: grid;
	gap: 20px;
}

.hero-card,
.decision-card,
.kpi-card,
.panel {
	background: var(--color-main-background, #fff);
	border: 1px solid var(--color-border, rgba(15, 23, 42, 0.08));
	border-radius: 12px;
	box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
}

.hero-card {
	padding: 24px;
}

.hero-card-main {
	background:
		linear-gradient(135deg, rgba(91, 108, 250, 0.10), rgba(20, 184, 166, 0.08)),
		var(--color-main-background, #fff);
}

.eyebrow,
.panel-eyebrow,
.kpi-label {
	font-size: 0.75rem;
	font-weight: 700;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast, #6b7280);
}

.hero-title {
	margin: 8px 0 10px;
	font-size: 1.75rem;
	line-height: 1.15;
	color: var(--color-main-text, #111827);
}

.hero-copy {
	margin: 0;
	line-height: 1.5;
	color: var(--color-text-maxcontrast, #6b7280);
}

.hero-stats {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
	margin-top: 24px;
}

.hero-stat {
	padding: 14px 16px;
	border-radius: 10px;
	background: rgba(255, 255, 255, 0.74);
	border: 1px solid rgba(91, 108, 250, 0.12);
}

.hero-stat-label {
	display: block;
	margin-bottom: 6px;
	font-size: 0.78rem;
	color: var(--color-text-maxcontrast, #6b7280);
}

.hero-stat-value {
	font-size: 1.15rem;
	color: var(--color-main-text, #111827);
}

.decision-grid {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 14px;
}

.decision-card {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
	padding: 16px;
	border-radius: 10px;
	box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05);
}

.decision-card span {
	color: var(--color-text-maxcontrast, #6b7280);
	font-size: 0.78rem;
	font-weight: 700;
	text-transform: uppercase;
}

.decision-card strong {
	overflow: hidden;
	color: var(--color-main-text, #111827);
	font-size: 1.18rem;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.decision-card small {
	color: var(--color-text-maxcontrast, #6b7280);
	line-height: 1.35;
}

.decision-card.muted {
	background: var(--color-background-hover, rgba(15, 23, 42, 0.05));
}

.chart-controls {
	display: flex;
	flex-wrap: wrap;
	align-items: end;
	gap: 12px;
	padding: 14px;
	border: 1px solid var(--color-border, rgba(15, 23, 42, 0.08));
	border-radius: 10px;
	background: var(--color-main-background, #fff);
	box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
}

.control-group {
	display: grid;
	gap: 5px;
	min-width: 180px;
}

.control-group label {
	color: var(--color-text-maxcontrast, #6b7280);
	font-size: 0.76rem;
	font-weight: 700;
	text-transform: uppercase;
}

.control-group select {
	min-height: 36px;
	padding: 0 34px 0 10px;
	border: 1px solid var(--color-border, rgba(15, 23, 42, 0.12));
	border-radius: 8px;
	background: var(--color-main-background, #fff);
	color: var(--color-main-text, #111827);
}

.clear-focus {
	min-height: 36px;
	padding: 0 14px;
	border: 1px solid var(--color-border, rgba(15, 23, 42, 0.12));
	border-radius: 8px;
	background: var(--color-background-hover, rgba(15, 23, 42, 0.05));
	color: var(--color-main-text, #111827);
	font-weight: 700;
}

.clear-focus:disabled {
	opacity: .55;
}

.kpi-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 16px;
}

.kpi-card {
	padding: 18px;
	min-height: 126px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}

.kpi-value {
	font-size: 1.9rem;
	font-weight: 700;
	line-height: 1.15;
	color: var(--color-main-text, #111827);
}

.charts-grid {
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: 20px;
}

.panel {
	padding: 20px;
	grid-column: span 6;
}

.panel-full {
	grid-column: 1 / -1;
}

.panel-heading {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 18px;
}

.panel-title {
	margin: 4px 0 0;
	font-size: 1rem;
	color: var(--color-main-text, #111827);
}

.panel-copy {
	max-width: 620px;
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast, #6b7280);
	font-size: 0.84rem;
	line-height: 1.4;
}

.panel-badge {
	padding: 6px 10px;
	border-radius: 999px;
	white-space: nowrap;
	font-size: 0.75rem;
	color: var(--color-text-maxcontrast, #6b7280);
	background: var(--color-background-hover, rgba(15, 23, 42, 0.05));
}

.chart-box {
	position: relative;
	height: 320px;
}

.chart-box-large {
	height: 440px;
}

.details-list-wrap {
	max-height: min(58vh, 620px);
	min-height: 280px;
	overflow-y: auto;
	overflow-x: hidden;
	border: 1px solid var(--color-border, rgba(15, 23, 42, 0.08));
	border-radius: 10px;
	background: var(--color-main-background, #fff);
}

.details-list {
	padding: 4px 0;
}

.ranking-table-wrap {
	overflow: auto;
	border: 1px solid var(--color-border, rgba(15, 23, 42, 0.08));
	border-radius: 10px;
}

.ranking-table {
	width: 100%;
	min-width: 760px;
	border-collapse: collapse;
	background: var(--color-main-background, #fff);
}

.ranking-table th,
.ranking-table td {
	padding: 12px 14px;
	border-bottom: 1px solid var(--color-border, rgba(15, 23, 42, 0.08));
	text-align: left;
	vertical-align: middle;
	white-space: nowrap;
}

.ranking-table th {
	color: var(--color-text-maxcontrast, #6b7280);
	font-size: 0.78rem;
	font-weight: 700;
	text-transform: uppercase;
	background: var(--color-background-hover, rgba(15, 23, 42, 0.05));
}

.ranking-table td:first-child {
	min-width: 220px;
	white-space: normal;
}

.ranking-table td strong,
.ranking-table td span {
	display: block;
}

.ranking-table td strong {
	color: var(--color-main-text, #111827);
}

.ranking-table td span {
	margin-top: 3px;
	color: var(--color-text-maxcontrast, #6b7280);
	font-size: 0.82rem;
}

.ranking-table tbody tr:last-child td {
	border-bottom: 0;
}

.share-cell {
	display: grid;
	grid-template-columns: 54px minmax(100px, 1fr);
	gap: 10px;
	align-items: center;
}

.share-track {
	height: 8px;
	overflow: hidden;
	border-radius: 999px;
	background: var(--color-background-darker, rgba(15, 23, 42, 0.12));
}

.share-value {
	height: 100%;
	border-radius: inherit;
	background: var(--color-primary-element, #2563eb);
}

@media (max-width: 1100px) {
	.charts-grid {
		grid-template-columns: 1fr 1fr;
	}

	.decision-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.control-group {
		flex: 1 1 220px;
	}

	.panel,
	.panel-full {
		grid-column: auto;
	}
}

@media (max-width: 768px) {
	.hero-stats,
	.decision-grid,
	.charts-grid {
		grid-template-columns: 1fr;
	}

	.hero-card,
	.kpi-card,
	.panel {
		padding: 18px;
	}

	.chart-controls {
		align-items: stretch;
		flex-direction: column;
	}

	.chart-box {
		height: 300px;
	}

	.chart-box-large {
		height: 320px;
	}

	.details-list-wrap {
		max-height: 60vh;
		min-height: 240px;
	}
}

@media (max-width: 560px) {
	.kpi-grid {
		grid-template-columns: 1fr;
	}

	.hero-title {
		font-size: 1.4rem;
	}

	.chart-box {
		height: 260px;
	}
}
</style>
