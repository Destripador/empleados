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
					{{ t('empleados', 'Review how this employee spent time across companies, activities and work types.') }}
				</p>
				<div class="hero-stats">
					<div class="hero-stat">
						<span class="hero-stat-label">{{ t('empleados', 'Hours') }}</span>
						<strong class="hero-stat-value">{{ kpisFmt.horas_reportadas }}</strong>
					</div>
					<div class="hero-stat">
						<span class="hero-stat-label">{{ t('empleados', 'Reports') }}</span>
						<strong class="hero-stat-value">{{ kpisFmt.total_reportes }}</strong>
					</div>
					<div v-if="mostrarClientes" class="hero-stat">
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
					<div v-if="mostrarClientes" class="hero-stat">
						<span class="hero-stat-label">{{ t('empleados', 'Client hours') }}</span>
						<strong class="hero-stat-value">{{ kpisFmt.horas_cliente }}</strong>
					</div>
					<div class="hero-stat">
						<span class="hero-stat-label">{{ t('empleados', 'Internal hours') }}</span>
						<strong class="hero-stat-value">{{ kpisFmt.horas_internas }}</strong>
					</div>
					<div v-if="mostrarAusencias" class="hero-stat">
						<span class="hero-stat-label">{{ t('empleados', 'Absence hours') }}</span>
						<strong class="hero-stat-value">{{ kpisFmt.horas_ausencia }}</strong>
					</div>
				</div>
			</div>
		</section>

		<section class="decision-grid">
			<article v-if="mostrarClientes" class="decision-card">
				<span>{{ t('empleados', 'Company with most hours') }}</span>
				<strong>{{ decisionFmt.topCliente }}</strong>
				<small>{{ decisionFmt.topClienteDetalle }}</small>
			</article>

			<article v-if="mostrarClientes" class="decision-card">
				<span>{{ t('empleados', 'Top 3 concentration') }}</span>
				<strong>{{ decisionFmt.concentracionTop3 }}</strong>
				<small>{{ t('empleados', 'Share of client hours') }}</small>
			</article>

			<article class="decision-card">
				<span>{{ t('empleados', 'Most used activity') }}</span>
				<strong>{{ decisionFmt.topActividad }}</strong>
				<small>{{ decisionFmt.topActividadDetalle }}</small>
			</article>

			<article class="decision-card">
				<span>{{ t('empleados', 'Dominant work type') }}</span>
				<strong>{{ decisionFmt.tipoDominante }}</strong>
				<small>{{ decisionFmt.tipoDominanteDetalle }}</small>
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

			<div v-if="mostrarClientes" class="control-group">
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
			<article v-if="mostrarClientes" class="panel">
				<div class="panel-heading">
					<div>
						<div class="panel-eyebrow">
							{{ t('empleados', 'Distribution') }}
						</div>
						<h3 class="panel-title">
							{{ t('empleados', 'Hours by company') }}
						</h3>
						<p class="panel-copy">
							{{ t('empleados', 'Compare reported hours and share of attention by customer.') }}
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
							{{ t('empleados', 'Hours by activity') }}
						</h3>
						<p class="panel-copy">
							{{ t('empleados', 'See which type of work is taking the most hours.') }}
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
							{{ t('empleados', 'Composition') }}
						</div>
						<h3 class="panel-title">
							{{ t('empleados', 'Work type mix') }}
						</h3>
						<p class="panel-copy">
							{{ t('empleados', 'Split between client work, internal work and absences.') }}
						</p>
					</div>
				</div>
				<div class="chart-box">
					<canvas ref="chartTipoTrabajo" />
				</div>
			</article>

			<article v-if="mostrarClientes" class="panel">
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

			<article class="panel panel-wide">
				<div class="panel-heading">
					<div>
						<div class="panel-eyebrow">
							{{ t('empleados', 'Trend') }}
						</div>
						<h3 class="panel-title">
							{{ t('empleados', 'Daily hours by work type') }}
						</h3>
						<p class="panel-copy">
							{{ t('empleados', 'Detect spikes and how client, internal and absence hours evolve day by day.') }}
						</p>
					</div>
				</div>
				<div class="chart-box chart-box-tall">
					<canvas ref="chartHorasDia" />
				</div>
			</article>

			<article v-if="mostrarClientes && rankingClientes.length > 0" class="panel panel-full">
				<div class="panel-heading">
					<div>
						<div class="panel-eyebrow">
							{{ t('empleados', 'Executive ranking') }}
						</div>
						<h3 class="panel-title">
							{{ t('empleados', 'Companies by hours') }}
						</h3>
						<p class="panel-copy">
							{{ t('empleados', 'Use this ranking to see which companies consume more of this employee time.') }}
						</p>
					</div>
				</div>
				<div class="ranking-table-wrap">
					<table class="ranking-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Customer') }}</th>
								<th>{{ t('empleados', 'Hours') }}</th>
								<th>{{ t('empleados', 'Share') }}</th>
								<th>{{ t('empleados', 'Reports') }}</th>
								<th>{{ t('empleados', 'Main activity') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="cliente in rankingClientes" :key="cliente.key">
								<td>
									<strong>{{ cliente.label }}</strong>
								</td>
								<td>{{ formatNumber(cliente.total) }} h</td>
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
								<td>{{ cliente.actividadPrincipal }}</td>
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
						:extra-props="{ listas: actividadesList, actividades: proyectosList }" />
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
		mostrarClientes: { type: Boolean, required: false, default: true },
		mostrarAusencias: { type: Boolean, required: false, default: true },
	},

	data() {
		return {
			rowComponent: ReportRow,
			horasreportadas: '',
			proyectosactivos: '',
			actividades: '',

			chartProyectosInstance: null,
			chartActividadesInstance: null,
			chartHorasDiaInstance: null,
			chartProyectoActividadInstance: null,
			chartTipoTrabajoInstance: null,
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

					const tipoTrabajo = r.tipo_trabajo || r.tipoTrabajo
						|| (Number(idCliente) === 99999 || Number(idActividad) === 99999
							? 'ausencia'
							: (idCliente == null || r.origen === 'soporte_ti' ? 'interno' : 'cliente'))
					const esAusencia = tipoTrabajo === 'ausencia'
					const esInterno = tipoTrabajo === 'interno'
					const esSoporte = r.origen === 'soporte_ti'

					const clienteNombre = esInterno
						? t('empleados', 'Internal work')
						: esAusencia
							? t('empleados', 'Módulo de Ausencia')
							: (clientesMap.get(Number(idCliente)) || `Cliente ${idCliente ?? ''}`.trim())

					const actividadNombre = esSoporte
						? (r.actividad_nombre || t('empleados', 'Support TI'))
						: esAusencia
							? t('empleados', 'Ausencia')
							: (actividadesMap.get(Number(idActividad)) || `Actividad ${idActividad ?? ''}`.trim())

					return {
						...r,
						id,
						idCliente,
						idActividad,
						tipoTrabajo,
						clienteNombre,
						actividadNombre,
						esAusencia,
						esInterno,
						esSoporte,
					}
				})
		},

		kpis() {
			const arr = Array.isArray(this.select) ? this.select : []

			let minutos = 0
			let minutosCliente = 0
			let minutosInternos = 0
			let minutosAusencia = 0
			const proyectos = new Set()
			const actividades = new Set()

			for (const it of arr) {
				const itemMinutos = this.toNum(it?.tiempo_registrado)
				minutos += itemMinutos

				const tipoTrabajo = it?.tipo_trabajo
					|| it?.tipoTrabajo
					|| (Number(it?.id_cliente) === 99999 || Number(it?.id_actividad) === 99999
						? 'ausencia'
						: (it?.id_cliente == null || it?.origen === 'soporte_ti' ? 'interno' : 'cliente'))

				if (tipoTrabajo === 'cliente') minutosCliente += itemMinutos
				if (tipoTrabajo === 'interno') minutosInternos += itemMinutos
				if (tipoTrabajo === 'ausencia') minutosAusencia += itemMinutos
				if (tipoTrabajo === 'cliente' && it?.id_cliente != null) proyectos.add(String(it.id_cliente))
				if (it?.id_actividad != null) actividades.add(String(it.id_actividad))
			}

			const horas = minutos / 60
			const totalReportes = arr.length

			return {
				horas_reportadas: horas,
				proyectos_activos: proyectos.size,
				actividades: actividades.size,
				total_reportes: totalReportes,
				promedio_horas_reporte: totalReportes > 0 ? horas / totalReportes : 0,
				horas_cliente: minutosCliente / 60,
				horas_internas: minutosInternos / 60,
				horas_ausencia: minutosAusencia / 60,
			}
		},

		kpisFmt() {
			const num2 = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 })
			const int = new Intl.NumberFormat('es-MX')

			return {
				horas_reportadas: num2.format(this.kpis.horas_reportadas || 0),
				proyectos_activos: int.format(this.kpis.proyectos_activos || 0),
				actividades: int.format(this.kpis.actividades || 0),
				total_reportes: int.format(this.kpis.total_reportes || 0),
				promedio_horas_reporte: `${num2.format(this.kpis.promedio_horas_reporte || 0)} h`,
				horas_cliente: `${num2.format(this.kpis.horas_cliente || 0)} h`,
				horas_internas: `${num2.format(this.kpis.horas_internas || 0)} h`,
				horas_ausencia: `${num2.format(this.kpis.horas_ausencia || 0)} h`,
			}
		},

		rankingClientes() {
			return this.graficaProyectos.map((cliente) => ({
				...cliente,
				actividadPrincipal: this.actividadPrincipalPorCliente(cliente.key),
			}))
		},

		decisionFmt() {
			const num2 = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 })
			const percent = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 })
			const topCliente = this.rankingClientes[0] || null
			const topActividad = this.graficaActividades[0] || null
			const top3Horas = this.rankingClientes
				.slice(0, 3)
				.reduce((total, cliente) => total + cliente.total, 0)
			const totalCliente = this.kpis.horas_cliente || 0
			const concentracionTop3 = totalCliente > 0
				? (top3Horas / totalCliente) * 100
				: 0

			const tipos = this.graficaTipoTrabajo
			const dominante = [...tipos].sort((a, b) => b.horas - a.horas)[0] || null
			const totalHoras = this.kpis.horas_reportadas || 0

			return {
				topCliente: topCliente?.label || t('empleados', 'No data'),
				topClienteDetalle: topCliente
					? t('empleados', '{hours} h · {percent}%', {
						hours: num2.format(topCliente.total || 0),
						percent: num2.format(topCliente.porcentaje || 0),
					})
					: t('empleados', 'No company reports in this period.'),
				concentracionTop3: `${percent.format(concentracionTop3)}%`,
				topActividad: topActividad?.label || t('empleados', 'No data'),
				topActividadDetalle: topActividad
					? t('empleados', '{hours} h · {percent}%', {
						hours: num2.format(topActividad.total || 0),
						percent: num2.format(topActividad.porcentaje || 0),
					})
					: t('empleados', 'No activity reports in this period.'),
				tipoDominante: dominante?.label || t('empleados', 'No data'),
				tipoDominanteDetalle: dominante && totalHoras > 0
					? t('empleados', '{hours} h · {percent}%', {
						hours: num2.format(dominante.horas || 0),
						percent: num2.format((dominante.horas / totalHoras) * 100),
					})
					: t('empleados', 'No data'),
			}
		},

		graficaTipoTrabajo() {
			const buckets = {
				cliente: { key: 'cliente', label: t('empleados', 'Client work'), horas: 0 },
				interno: { key: 'interno', label: t('empleados', 'Internal work'), horas: 0 },
				ausencia: { key: 'ausencia', label: t('empleados', 'Absences'), horas: 0 },
			}

			for (const r of this.historial) {
				const tipo = r.tipoTrabajo || 'cliente'
				const key = buckets[tipo] ? tipo : 'interno'
				buckets[key].horas += this.toNum(r.tiempo_registrado) / 60
			}

			return Object.values(buckets).filter(item => {
				if (item.horas <= 0) {
					return false
				}
				if (item.key === 'cliente' && !this.mostrarClientes) {
					return false
				}
				if (item.key === 'ausencia' && !this.mostrarAusencias) {
					return false
				}
				return true
			})
		},

		graficaProyectos() {
			return this.agruparReportes('idCliente', 'clienteNombre', reporte => reporte.tipoTrabajo === 'cliente')
		},

		graficaActividades() {
			return this.agruparReportes(
				'idActividad',
				'actividadNombre',
				reporte => this.mostrarAusencias || reporte.tipoTrabajo !== 'ausencia',
			)
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

				const horas = this.toNum(r.tiempo_registrado) / 60
				const tipo = r.tipoTrabajo || 'cliente'
				const bucket = ['cliente', 'interno', 'ausencia'].includes(tipo) ? tipo : 'interno'

				if (!acc.has(fecha)) {
					acc.set(fecha, {
						fecha,
						cliente: 0,
						interno: 0,
						ausencia: 0,
						total: 0,
						reportes: 0,
					})
				}

				const day = acc.get(fecha)
				day[bucket] += horas
				day.total += horas
				day.reportes++
			}

			return Array.from(acc.values())
				.sort((a, b) => String(a.fecha).localeCompare(String(b.fecha)))
		},
		graficaProyectoActividad() {
			const proyectosMap = new Map()
			const actividadesSet = new Set()

			for (const r of this.historial) {
				if (r.tipoTrabajo !== 'cliente') continue
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

		agruparReportes(idCampo, nombreCampo, filter = null) {
			const acc = new Map()
			const totalHorasGeneral = this.kpis.horas_reportadas || 0

			for (const r of this.historial) {
				if (filter && !filter(r)) continue
				const id = r[idCampo] ?? 'sin-id'
				const label = r[nombreCampo] || `${t('empleados', 'No')} ${nombreCampo}`

				const minutos = this.toNum(r.tiempo_registrado)
				const horas = minutos / 60

				if (!acc.has(String(id))) {
					acc.set(String(id), {
						key: String(id),
						label,
						total: 0,
						reportes: 0,
						porcentaje: 0,
					})
				}

				const item = acc.get(String(id))
				item.total += horas
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

		formatNumber(value) {
			return new Intl.NumberFormat('es-MX', {
				maximumFractionDigits: 2,
			}).format(Number(value) || 0)
		},

		formatInteger(value) {
			return new Intl.NumberFormat('es-MX').format(Number(value) || 0)
		},

		formatPercent(value) {
			return `${new Intl.NumberFormat('es-MX', {
				maximumFractionDigits: 1,
			}).format(Number(value) || 0)}%`
		},

		renderGraficas() {
			this.renderGraficaActividades()
			this.renderGraficaTipoTrabajo()
			this.renderGraficaHorasDia()

			if (this.mostrarClientes) {
				this.renderGraficaProyectos()
				this.renderGraficaProyectoActividad()
			} else {
				if (this.chartProyectosInstance) {
					this.chartProyectosInstance.destroy()
					this.chartProyectosInstance = null
				}
				if (this.chartProyectoActividadInstance) {
					this.chartProyectoActividadInstance.destroy()
					this.chartProyectoActividadInstance = null
				}
				this.selectedCliente = null
			}
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
							label: t('empleados', 'Hours'),
							data: datos.map(x => Number(x.total.toFixed(2))),
							backgroundColor: 'rgba(20, 184, 166, 0.72)',
							borderColor: 'rgba(13, 148, 136, 1)',
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
									const porcentaje = new Intl.NumberFormat('es-MX', {
										maximumFractionDigits: 2,
									}).format(item.porcentaje || 0)

									return [
										t('empleados', 'Hours: {hours}', { hours: horas }),
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
									const porcentaje = new Intl.NumberFormat('es-MX', {
										maximumFractionDigits: 1,
									}).format(item.porcentaje || 0)

									return [
										t('empleados', 'Hours: {hours}', { hours: context.raw }),
										t('empleados', 'Share: {percent}%', { percent: porcentaje }),
										t('empleados', 'Reports: {reports}', { reports: item.reportes }),
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

		renderGraficaTipoTrabajo() {
			if (!this.$refs.chartTipoTrabajo) return

			if (this.chartTipoTrabajoInstance) {
				this.chartTipoTrabajoInstance.destroy()
			}

			const datos = this.graficaTipoTrabajo
			const colors = {
				cliente: 'rgba(37, 99, 235, 0.78)',
				interno: 'rgba(20, 184, 166, 0.78)',
				ausencia: 'rgba(245, 158, 11, 0.78)',
			}

			this.chartTipoTrabajoInstance = new Chart(this.$refs.chartTipoTrabajo, {
				type: 'doughnut',
				data: {
					labels: datos.map(x => x.label),
					datasets: [{
						data: datos.map(x => Number(x.horas.toFixed(2))),
						backgroundColor: datos.map(x => colors[x.key] || 'rgba(100, 116, 139, 0.75)'),
						borderWidth: 0,
					}],
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
									const item = datos[context.dataIndex]
									return t('empleados', '{label}: {hours} hours', {
										label: item.label,
										hours: item.horas.toFixed(2),
									})
								},
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
			const datasets = []

			if (this.mostrarClientes) {
				datasets.push({
					label: t('empleados', 'Client work'),
					data: datos.map(x => Number(x.cliente.toFixed(2))),
					backgroundColor: 'rgba(37, 99, 235, 0.78)',
					stack: 'day',
				})
			}

			datasets.push({
				label: t('empleados', 'Internal work'),
				data: datos.map(x => Number(x.interno.toFixed(2))),
				backgroundColor: 'rgba(20, 184, 166, 0.78)',
				stack: 'day',
			})

			if (this.mostrarAusencias) {
				datasets.push({
					label: t('empleados', 'Absences'),
					data: datos.map(x => Number(x.ausencia.toFixed(2))),
					backgroundColor: 'rgba(245, 158, 11, 0.78)',
					stack: 'day',
				})
			}

			this.chartHorasDiaInstance = new Chart(this.$refs.chartHorasDia, {
				type: 'bar',
				data: {
					labels: datos.map(x => x.fecha),
					datasets,
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
								footer(items) {
									const index = items?.[0]?.dataIndex
									const day = datos[index]
									if (!day) return ''
									return t('empleados', 'Reports: {reports}', { reports: day.reportes })
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
								callback: value => `${value} h`,
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
			if (this.chartTipoTrabajoInstance) {
				this.chartTipoTrabajoInstance.destroy()
				this.chartTipoTrabajoInstance = null
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

.panel-wide {
	grid-column: span 12;
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

.chart-box-tall {
	height: 380px;
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
