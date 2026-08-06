<template>
	<div class="resumen-general">
		<div v-if="loading" class="state-card">
			{{ t('empleados', 'Loading summary...') }}
		</div>

		<div v-else-if="!resumen" class="state-card">
			{{ t('empleados', 'No data for this period.') }}
		</div>

		<div v-else class="dashboard-shell">
			<section class="summary-grid">
				<div class="summary-card summary-card-accent">
					<div class="summary-label">
						{{ t('empleados', 'Reported hours') }}
					</div>
					<div class="summary-value">
						{{ resumenFmt.horas_reportadas }}
					</div>
					<div class="summary-meta">
						{{ statsFilterCaption }}
					</div>
				</div>

				<div class="summary-card">
					<div class="summary-label">
						{{ t('empleados', 'Client work hours') }}
					</div>
					<div class="summary-value">
						{{ resumenFmt.horas_cliente }}
					</div>
				</div>

				<div class="summary-card">
					<div class="summary-label">
						{{ t('empleados', 'Internal work hours') }}
					</div>
					<div class="summary-value">
						{{ resumenFmt.horas_internas }}
					</div>
					<div class="summary-meta">
						{{ resumenFmt.porcentaje_interno }} {{ t('empleados', 'of reported work') }}
					</div>
				</div>

				<div class="summary-card">
					<div class="summary-label">
						{{ t('empleados', 'Reports') }}
					</div>
					<div class="summary-value">
						{{ resumenFmt.total_reportes }}
					</div>
				</div>

				<div class="summary-card">
					<div class="summary-label">
						{{ t('empleados', 'Projects') }}
					</div>
					<div class="summary-value">
						{{ resumenFmt.proyectos_activos }}
					</div>
				</div>

				<div class="summary-card">
					<div class="summary-label">
						{{ t('empleados', 'Activities') }}
					</div>
					<div class="summary-value">
						{{ resumenFmt.actividades }}
					</div>
				</div>

				<div class="summary-card">
					<div class="summary-label">
						{{ t('empleados', 'Average per report') }}
					</div>
					<div class="summary-value">
						{{ resumenFmt.promedio_horas_reporte }}
					</div>
					<div class="summary-meta">
						{{ t('empleados', 'Average recorded efficiency') }}
					</div>
				</div>
			</section>

			<section class="decision-grid">
				<article class="decision-card">
					<span>{{ t('empleados', 'Company with most hours') }}</span>
					<strong>{{ decisionFmt.topEmpresa }}</strong>
					<small>{{ decisionFmt.topEmpresaDetalle }}</small>
				</article>

				<article class="decision-card">
					<span>{{ t('empleados', 'Top 3 companies') }}</span>
					<strong>{{ decisionFmt.concentracionTop3 }}</strong>
					<small>{{ t('empleados', 'Share of visible reported hours') }}</small>
				</article>

				<article class="decision-card">
					<span>{{ t('empleados', 'Most used activity') }}</span>
					<strong>{{ decisionFmt.topActividad }}</strong>
					<small>{{ decisionFmt.topActividadDetalle }}</small>
				</article>

				<article class="decision-card">
					<span>{{ t('empleados', 'Hidden categories') }}</span>
					<strong>{{ exclusionCount }}</strong>
					<small>{{ t('empleados', 'Companies or activities excluded from the view') }}</small>
				</article>
			</section>

			<section class="chart-controls">
				<div class="chart-controls-bar">
					<div class="period-presets control-group inline">
						<label for="resumen-chart-limit">{{ t('empleados', 'Show') }}</label>
						<select
							id="resumen-chart-limit"
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

					<button
						type="button"
						class="clear-focus"
						:class="{ active: hideAbsences }"
						@click="toggleHideAbsences">
						{{ hideAbsences
							? t('empleados', 'Absences hidden')
							: t('empleados', 'Hide absences') }}
					</button>

					<button
						type="button"
						class="clear-focus"
						:aria-expanded="showChartFilters ? 'true' : 'false'"
						@click="showChartFilters = !showChartFilters">
						{{ t('empleados', 'More filters') }}
						<span v-if="exclusionCount > 0" class="filter-badge">{{ exclusionCount }}</span>
					</button>

					<button
						type="button"
						class="clear-focus"
						:disabled="!canClearFocus"
						@click="clearChartFocus">
						{{ t('empleados', 'Clear focus') }}
					</button>
				</div>

				<div v-if="showChartFilters" class="chart-filters-panel">
					<div class="control-group">
						<label for="resumen-company-focus">{{ t('empleados', 'Focus company') }}</label>
						<select
							id="resumen-company-focus"
							v-model="selectedEmpresa"
							@change="renderGraficas">
							<option :value="null">
								{{ t('empleados', 'All companies') }}
							</option>
							<option
								v-for="empresa in graficaProyectosAll"
								:key="empresa.key"
								:value="empresa.label">
								{{ empresa.label }}
							</option>
						</select>
					</div>

					<div class="control-group">
						<label for="resumen-activity-focus">{{ t('empleados', 'Focus activity') }}</label>
						<select
							id="resumen-activity-focus"
							v-model="selectedActividad"
							@change="renderGraficas">
							<option :value="null">
								{{ t('empleados', 'All activities') }}
							</option>
							<option
								v-for="actividad in graficaActividadesAll"
								:key="actividad.key"
								:value="actividad.label">
								{{ actividad.label }}
							</option>
						</select>
					</div>

					<div class="control-group control-group-wide">
						<span class="control-label">{{ t('empleados', 'Hide companies') }}</span>
						<div class="exclude-list">
							<label
								v-for="empresa in graficaProyectosAll"
								:key="`hide-co-${empresa.key}`"
								class="exclude-item">
								<input
									v-model="excludedEmpresaKeys"
									type="checkbox"
									:value="empresa.key"
									@change="onExclusionChange">
								{{ empresa.label }}
							</label>
						</div>
					</div>

					<div class="control-group control-group-wide">
						<span class="control-label">{{ t('empleados', 'Hide activities') }}</span>
						<div class="exclude-list">
							<label
								v-for="actividad in graficaActividadesAll"
								:key="`hide-act-${actividad.key}`"
								class="exclude-item">
								<input
									v-model="excludedActividadKeys"
									type="checkbox"
									:value="actividad.key"
									@change="onExclusionChange">
								{{ actividad.label }}
							</label>
						</div>
					</div>
				</div>
			</section>

			<section class="charts-grid">
				<article class="panel panel-wide">
					<div class="panel-heading">
						<div>
							<div class="panel-eyebrow">
								{{ t('empleados', 'Distribution') }}
							</div>
							<h3 class="panel-title">
								{{ t('empleados', 'Hours by employee') }}
							</h3>
							<p class="panel-copy">
								{{ t('empleados', 'See who is carrying the operational load.') }}
							</p>
						</div>
						<div class="panel-badge">
							{{ t('empleados', '{count} reports recorded', { count: resumenFmt.total_reportes }) }}
						</div>
					</div>
					<div class="chart-box chart-box-tall">
						<canvas ref="chartHorasEmpleado" />
					</div>
				</article>

				<article class="panel panel-small">
					<div class="panel-heading">
						<div>
							<div class="panel-eyebrow">
								{{ t('empleados', 'Key indicators') }}
							</div>
							<h3 class="panel-title">
								{{ t('empleados', 'Attention points') }}
							</h3>
						</div>
					</div>

					<ul class="insight-list">
						<li class="insight-item">
							<span class="insight-label">{{ t('empleados', 'Employee with highest workload') }}</span>
							<strong class="insight-value">{{ topEmpleado.label }}</strong>
							<span class="insight-meta">{{ topEmpleado.valor }}</span>
						</li>
						<li class="insight-item">
							<span class="insight-label">{{ t('empleados', 'Leading project') }}</span>
							<strong class="insight-value">{{ topProyecto.label }}</strong>
							<span class="insight-meta">{{ topProyecto.valor }}</span>
						</li>
						<li class="insight-item">
							<span class="insight-label">{{ t('empleados', 'Main activity') }}</span>
							<strong class="insight-value">{{ topActividad.label }}</strong>
							<span class="insight-meta">{{ topActividad.valor }}</span>
						</li>
						<li class="insight-item">
							<span class="insight-label">{{ t('empleados', 'Reports') }}</span>
							<strong class="insight-value">{{ resumenFmt.total_reportes }}</strong>
							<span class="insight-meta">{{ statsFilterCaption }}</span>
						</li>
					</ul>
				</article>

				<article class="panel">
					<div class="panel-heading">
						<div>
							<div class="panel-eyebrow">
								{{ t('empleados', 'Composition') }}
							</div>
							<h3 class="panel-title">
								{{ t('empleados', 'Activities') }}
							</h3>
							<p class="panel-copy">
								{{ t('empleados', 'Identify which work categories consume the most hours.') }}
							</p>
						</div>
						<div class="panel-badge">
							{{ t('empleados', '{count} categories', { count: resumenFmt.actividades }) }}
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
								{{ t('empleados', 'Performance') }}
							</div>
							<h3 class="panel-title">
								{{ t('empleados', 'Companies by hours') }}
							</h3>
							<p class="panel-copy">
								{{ t('empleados', 'Compare where team time is spent across companies and projects.') }}
							</p>
						</div>
						<div class="panel-badge">
							{{ t('empleados', '{count} projects', { count: resumenFmt.proyectos_activos }) }}
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
								{{ t('empleados', 'Portfolio') }}
							</div>
							<h3 class="panel-title">
								{{ t('empleados', 'Company hours matrix') }}
							</h3>
							<p class="panel-copy">
								{{ t('empleados', 'Companies farther right with larger bubbles consume more team hours and reports.') }}
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
								{{ t('empleados', 'Trend') }}
							</div>
							<h3 class="panel-title">
								{{ t('empleados', 'Hours per day') }}
							</h3>
						</div>
						<div class="panel-badge">
							{{ t('empleados', 'Time series') }}
						</div>
					</div>
					<div class="chart-box">
						<canvas ref="chartHorasDia" />
					</div>
				</article>

				<article class="panel">
					<div class="panel-heading">
						<div>
							<div class="panel-eyebrow">
								{{ t('empleados', 'Volume') }}
							</div>
							<h3 class="panel-title">
								{{ t('empleados', 'Reports per day') }}
							</h3>
						</div>
						<div class="panel-badge">
							{{ t('empleados', 'Daily frequency') }}
						</div>
					</div>
					<div class="chart-box">
						<canvas ref="chartReportesDia" />
					</div>
				</article>

				<article class="panel panel-full">
					<div class="panel-heading">
						<div>
							<div class="panel-eyebrow">
								{{ t('empleados', 'Operational cross-check') }}
							</div>
							<h3 class="panel-title">
								{{ t('empleados', 'Project vs activity') }}
							</h3>
							<p class="panel-copy">
								{{ t('empleados', 'Understand exactly what each company is consuming from the team.') }}
							</p>
						</div>
						<div class="panel-badge">
							{{ t('empleados', 'Stacked distribution') }}
						</div>
					</div>
					<div class="chart-box chart-box-large">
						<canvas ref="chartProyectoActividad" />
					</div>
				</article>

				<article v-if="rankingEmpresas.length > 0" class="panel panel-full">
					<div class="panel-heading">
						<div>
							<div class="panel-eyebrow">
								{{ t('empleados', 'Executive ranking') }}
							</div>
							<h3 class="panel-title">
								{{ t('empleados', 'Companies by hours') }}
							</h3>
							<p class="panel-copy">
								{{ t('empleados', 'Use this ranking to see which companies consume more of the team time.') }}
							</p>
						</div>
					</div>
					<div class="ranking-table-wrap">
						<table class="ranking-table">
							<thead>
								<tr>
									<th>{{ t('empleados', 'Company') }}</th>
									<th>{{ t('empleados', 'Hours') }}</th>
									<th>{{ t('empleados', 'Share') }}</th>
									<th>{{ t('empleados', 'Reports') }}</th>
									<th>{{ t('empleados', 'Main activity') }}</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="empresa in rankingEmpresas" :key="empresa.key">
									<td><strong>{{ empresa.label }}</strong></td>
									<td>{{ formatNumber(empresa.horas) }} h</td>
									<td>
										<div class="share-cell">
											<span>{{ formatPercent(empresa.porcentaje) }}</span>
											<div class="share-track">
												<div
													class="share-value"
													:style="{ width: `${Math.min(empresa.porcentaje, 100)}%` }" />
											</div>
										</div>
									</td>
									<td>{{ formatInteger(empresa.reportes) }}</td>
									<td>{{ empresa.actividadPrincipal }}</td>
								</tr>
							</tbody>
						</table>
					</div>
				</article>
			</section>
		</div>
	</div>
</template>

<script>
// eslint-disable-next-line import/no-named-as-default
import Chart from 'chart.js/auto'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'AdminResumenGeneral',

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
		actividadesList: {
			type: Array,
			required: false,
			default: () => [],
		},
		proyectosList: {
			type: Array,
			required: false,
			default: () => [],
		},
	},

	data() {
		return {
			chartHorasEmpleadoInstance: null,
			chartProyectosInstance: null,
			chartActividadesInstance: null,
			chartHorasDiaInstance: null,
			chartReportesDiaInstance: null,
			chartProyectoActividadInstance: null,
			chartClienteMatrizInstance: null,
			chartLimit: 10,
			selectedEmpresa: null,
			selectedActividad: null,
			showChartFilters: false,
			hideAbsences: true,
			excludedEmpresaKeys: [],
			excludedActividadKeys: [],
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

		absenceEmpresaKey() {
			return '99999'
		},

		absenceActividadKey() {
			return '99999'
		},

		exclusionCount() {
			let count = this.excludedEmpresaKeys.length + this.excludedActividadKeys.length
			if (this.hideAbsences) {
				count += 1
			}
			return count
		},

		canClearFocus() {
			return Boolean(this.selectedEmpresa)
				|| Boolean(this.selectedActividad)
				|| this.chartLimit !== 10
				|| !this.hideAbsences
				|| this.excludedEmpresaKeys.length > 0
				|| this.excludedActividadKeys.length > 0
		},

		statsFilterCaption() {
			if (this.exclusionCount > 0) {
				return t('empleados', 'With current exclusions')
			}
			return t('empleados', 'All categories visible')
		},

		horasVisiblesTotal() {
			return this.graficaProyectos.reduce((total, item) => total + item.horas, 0)
		},

		resumenFmt() {
			const kpis = this.resumen?.kpis || {}
			const num2 = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 })
			const int = new Intl.NumberFormat('es-MX')
			const horasVisibles = this.horasVisiblesTotal
			const reportesVisibles = this.graficaProyectos.reduce((total, item) => total + item.reportes, 0)

			return {
				horas_reportadas: num2.format(horasVisibles || 0),
				horas_cliente: `${num2.format(kpis.horas_cliente || 0)} h`,
				horas_internas: `${num2.format(kpis.horas_internas || 0)} h`,
				porcentaje_interno: `${num2.format(kpis.porcentaje_interno || 0)}%`,
				total_reportes: int.format(reportesVisibles || kpis.total_reportes || 0),
				proyectos_activos: int.format(this.graficaProyectos.length || 0),
				actividades: int.format(this.graficaActividades.length || 0),
				promedio_horas_reporte: reportesVisibles > 0
					? `${num2.format(horasVisibles / reportesVisibles)} h`
					: `${num2.format(kpis.promedio_horas_reporte || 0)} h`,
			}
		},

		proyectosMap() {
			const map = new Map(
				(this.proyectosList || []).map(p => [
					Number(p.id),
					p.label || p.name || p.nombre || `Proyecto ${p.id}`,
				]),
			)
			map.set(99999, t('empleados', 'Módulo de Ausencia'))
			return map
		},

		actividadesMap() {
			const map = new Map(
				(this.actividadesList || []).map(a => [
					Number(a.id),
					a.label || a.name || a.nombre || `Actividad ${a.id}`,
				]),
			)
			map.set(99999, t('empleados', 'Ausencia'))
			return map
		},

		graficaEmpleados() {
			const empleados = Array.isArray(this.resumen?.empleados)
				? this.resumen.empleados
				: []

			return empleados
				.map(e => {
					const minutos = this.toNum(e.total_tiempo_registrado)
					const horas = minutos / 60

					return {
						label: e.displayname || e.Id_user || e.id_user || `Empleado ${e.Id_empleados || ''}`,
						horas,
					}
				})
				.filter(e => e.horas > 0)
				.sort((a, b) => b.horas - a.horas)
		},

		graficaProyectosAll() {
			const rows = this.resumen?.graficas?.horas_por_proyecto || []

			return rows.map(r => {
				const horas = this.toNum(r.horas)
				return {
					key: String(r.id_cliente ?? 'sin-id'),
					label: this.proyectosMap.get(Number(r.id_cliente)) || `Proyecto ${r.id_cliente}`,
					horas,
					reportes: this.toNum(r.total_reportes),
				}
			}).sort((a, b) => b.horas - a.horas)
		},

		graficaActividadesAll() {
			const rows = this.resumen?.graficas?.horas_por_actividad || []

			return rows.map(r => {
				const horas = this.toNum(r.horas)
				return {
					key: String(r.id_actividad ?? 'sin-id'),
					label: this.actividadesMap.get(Number(r.id_actividad)) || `Actividad ${r.id_actividad}`,
					horas,
					reportes: this.toNum(r.total_reportes),
				}
			}).sort((a, b) => b.horas - a.horas)
		},

		graficaProyectos() {
			const filtered = this.graficaProyectosAll.filter(item => this.isEmpresaVisible(item.key))
			const totalHoras = filtered.reduce((total, item) => total + item.horas, 0)

			return filtered.map(item => ({
				...item,
				porcentaje: totalHoras > 0 ? (item.horas / totalHoras) * 100 : 0,
			}))
		},

		graficaActividades() {
			const filtered = this.graficaActividadesAll.filter(item => this.isActividadVisible(item.key))
			const totalHoras = filtered.reduce((total, item) => total + item.horas, 0)

			return filtered.map(item => ({
				...item,
				porcentaje: totalHoras > 0 ? (item.horas / totalHoras) * 100 : 0,
			}))
		},

		proyectosVisibles() {
			const datos = this.selectedEmpresa
				? this.graficaProyectos.filter(item => item.label === this.selectedEmpresa)
				: this.graficaProyectos

			return this.chartLimit > 0 ? datos.slice(0, this.chartLimit) : datos
		},

		actividadesVisibles() {
			const datos = this.selectedActividad
				? this.graficaActividades.filter(item => item.label === this.selectedActividad)
				: this.graficaActividades

			return this.chartLimit > 0 ? datos.slice(0, this.chartLimit) : datos
		},

		graficaHorasDia() {
			return (this.resumen?.graficas?.horas_por_dia || []).map(r => ({
				label: r.fecha_registro,
				horas: this.toNum(r.horas),
				reportes: this.toNum(r.total_reportes),
			}))
		},

		graficaReportesDia() {
			return (this.resumen?.graficas?.reportes_por_dia || []).map(r => ({
				label: r.fecha_registro,
				reportes: this.toNum(r.total_reportes),
			}))
		},

		graficaProyectoActividad() {
			const rows = this.resumen?.graficas?.proyecto_vs_actividad || []

			const proyectosSet = new Set()
			const actividadesSet = new Set()
			const matriz = new Map()

			for (const r of rows) {
				const empresaKey = String(r.id_cliente ?? 'sin-id')
				const actividadKey = String(r.id_actividad ?? 'sin-id')

				if (!this.isEmpresaVisible(empresaKey) || !this.isActividadVisible(actividadKey)) {
					continue
				}

				const proyecto = this.proyectosMap.get(Number(r.id_cliente)) || `Proyecto ${r.id_cliente}`
				const actividad = this.actividadesMap.get(Number(r.id_actividad)) || `Actividad ${r.id_actividad}`
				const horas = this.toNum(r.horas)

				if (this.selectedEmpresa && proyecto !== this.selectedEmpresa) {
					continue
				}

				if (this.selectedActividad && actividad !== this.selectedActividad) {
					continue
				}

				proyectosSet.add(proyecto)
				actividadesSet.add(actividad)

				const key = `${proyecto}||${actividad}`
				matriz.set(key, (matriz.get(key) || 0) + horas)
			}

			const proyectos = Array.from(proyectosSet)
				.sort((a, b) => {
					const totalA = Array.from(matriz.entries())
						.filter(([key]) => key.startsWith(`${a}||`))
						.reduce((total, [, value]) => total + value, 0)
					const totalB = Array.from(matriz.entries())
						.filter(([key]) => key.startsWith(`${b}||`))
						.reduce((total, [, value]) => total + value, 0)

					return totalB - totalA
				})
				.slice(0, this.chartLimit > 0 ? this.chartLimit : undefined)
			const actividades = Array.from(actividadesSet)
				.slice(0, this.chartLimit > 0 ? this.chartLimit : undefined)

			const datasets = actividades.map(actividad => ({
				label: actividad,
				data: proyectos.map(proyecto => {
					const key = `${proyecto}||${actividad}`
					return Number((matriz.get(key) || 0).toFixed(2))
				}),
			}))

			return {
				proyectos,
				actividades,
				datasets,
			}
		},

		topEmpleado() {
			const top = this.graficaEmpleados[0]
			if (!top) {
				return {
					label: 'Sin registros',
					valor: '0 h',
				}
			}

			return {
				label: top.label,
				valor: `${top.horas.toFixed(2)} h`,
			}
		},

		rankingEmpresas() {
			return this.graficaProyectos.map(empresa => ({
				...empresa,
				actividadPrincipal: this.actividadPrincipalPorEmpresa(empresa.label),
			}))
		},

		decisionFmt() {
			const topEmpresa = this.rankingEmpresas[0] || null
			const topActividad = this.graficaActividades[0] || null
			const top3Horas = this.rankingEmpresas
				.slice(0, 3)
				.reduce((total, empresa) => total + empresa.horas, 0)
			const totalHoras = this.horasVisiblesTotal
			const concentracionTop3 = totalHoras > 0 ? (top3Horas / totalHoras) * 100 : 0

			return {
				topEmpresa: topEmpresa?.label || t('empleados', 'No data'),
				topEmpresaDetalle: topEmpresa
					? t('empleados', '{hours} h · {percent}%', {
						hours: this.formatNumber(topEmpresa.horas),
						percent: this.formatNumber(topEmpresa.porcentaje),
					})
					: t('empleados', 'No company reports in this period.'),
				concentracionTop3: this.formatPercent(concentracionTop3),
				topActividad: topActividad?.label || t('empleados', 'No data'),
				topActividadDetalle: topActividad
					? t('empleados', '{hours} h · {percent}%', {
						hours: this.formatNumber(topActividad.horas),
						percent: this.formatNumber(topActividad.porcentaje),
					})
					: t('empleados', 'No activity reports in this period.'),
			}
		},

		topProyecto() {
			const top = [...this.graficaProyectos].sort((a, b) => b.horas - a.horas)[0]
			if (!top) {
				return {
					label: 'Sin registros',
					valor: '0%',
				}
			}

			return {
				label: top.label,
				valor: `${top.porcentaje.toFixed(1)}% del total`,
			}
		},

		topActividad() {
			const top = [...this.graficaActividades].sort((a, b) => b.horas - a.horas)[0]
			if (!top) {
				return {
					label: 'Sin registros',
					valor: '0%',
				}
			}

			return {
				label: top.label,
				valor: `${top.porcentaje.toFixed(1)}% del total`,
			}
		},
	},

	watch: {
		resumen: {
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

		isEmpresaVisible(key) {
			const empresaKey = String(key)
			if (this.hideAbsences && empresaKey === this.absenceEmpresaKey) {
				return false
			}
			return !this.excludedEmpresaKeys.includes(empresaKey)
		},

		isActividadVisible(key) {
			const actividadKey = String(key)
			if (this.hideAbsences && actividadKey === this.absenceActividadKey) {
				return false
			}
			return !this.excludedActividadKeys.includes(actividadKey)
		},

		toggleHideAbsences() {
			this.hideAbsences = !this.hideAbsences
			this.$nextTick(() => {
				this.renderGraficas()
			})
		},

		onExclusionChange() {
			this.$nextTick(() => {
				this.renderGraficas()
			})
		},

		renderGraficas() {
			this.renderGraficaHorasEmpleado()
			this.renderGraficaProyectos()
			this.renderGraficaActividades()
			this.renderGraficaClienteMatriz()
			this.renderGraficaHorasDia()
			this.renderGraficaReportesDia()
			this.renderGraficaProyectoActividad()
		},

		clearChartFocus() {
			this.chartLimit = 10
			this.selectedEmpresa = null
			this.selectedActividad = null
			this.hideAbsences = true
			this.excludedEmpresaKeys = []
			this.excludedActividadKeys = []
			this.showChartFilters = false
			this.$nextTick(() => {
				this.renderGraficas()
			})
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

		actividadPrincipalPorEmpresa(empresaLabel) {
			const rows = this.resumen?.graficas?.proyecto_vs_actividad || []
			const acc = new Map()

			for (const row of rows) {
				const empresaKey = String(row.id_cliente ?? 'sin-id')
				const actividadKey = String(row.id_actividad ?? 'sin-id')
				const empresa = this.proyectosMap.get(Number(row.id_cliente)) || `Proyecto ${row.id_cliente}`

				if (empresa !== empresaLabel || !this.isEmpresaVisible(empresaKey) || !this.isActividadVisible(actividadKey)) {
					continue
				}

				const actividad = this.actividadesMap.get(Number(row.id_actividad)) || `Actividad ${row.id_actividad}`
				const horas = this.toNum(row.horas)

				acc.set(actividad, (acc.get(actividad) || 0) + horas)
			}

			const top = Array.from(acc.entries()).sort((a, b) => b[1] - a[1])[0]

			return top?.[0] || t('empleados', 'No activity detail')
		},

		renderGraficaHorasEmpleado() {
			if (!this.$refs.chartHorasEmpleado) return

			if (this.chartHorasEmpleadoInstance) {
				this.chartHorasEmpleadoInstance.destroy()
			}

			const datos = this.graficaEmpleados

			this.chartHorasEmpleadoInstance = new Chart(this.$refs.chartHorasEmpleado, {
				type: 'bar',
				data: {
					labels: datos.map(x => x.label),
					datasets: [{
						label: t('empleados', 'Hours by employee'),
						data: datos.map(x => Number(x.horas.toFixed(2))),
						backgroundColor: '#5b6cfa',
						borderRadius: 8,
						borderSkipped: false,
					}],
				},
				options: {
					indexAxis: 'y',
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						tooltip: {
							callbacks: {
								label(context) {
									return t('empleados', 'Hours: {hours}', { hours: context.raw })
								},
							},
						},
					},
					scales: {
						x: {
							beginAtZero: true,
							grid: {
								color: 'rgba(91, 108, 250, 0.12)',
							},
						},
						y: {
							grid: {
								display: false,
							},
						},
					},
				},
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
							data: datos.map(x => Number(x.horas.toFixed(2))),
							backgroundColor: 'rgba(20, 184, 166, 0.72)',
							borderColor: 'rgba(13, 148, 136, 1)',
							borderRadius: 8,
							borderSkipped: false,
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

									return [
										t('empleados', 'Hours: {hours}', { hours: item.horas.toFixed(2) }),
										t('empleados', 'Reports: {reports}', { reports: item.reportes }),
										t('empleados', 'Share: {percent}%', { percent: item.porcentaje.toFixed(2) }),
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

						this.selectedEmpresa = datos[index]?.label || null
						this.$nextTick(() => {
							this.renderGraficas()
						})
					},
					scales: {
						x: {
							beginAtZero: true,
							grid: {
								color: 'rgba(20, 184, 166, 0.14)',
							},
							ticks: {
								callback: value => `${value} h`,
							},
						},
						y: {
							grid: {
								display: false,
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
					datasets: [{
						label: t('empleados', 'Hours by activity'),
						data: datos.map(x => Number(x.horas.toFixed(2))),
						backgroundColor: 'rgba(124, 58, 237, 0.72)',
						borderColor: 'rgba(109, 40, 217, 1)',
						borderRadius: 8,
						borderSkipped: false,
						borderWidth: 1,
					}],
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

									return [
										t('empleados', '{label}: {hours} hours', { label: context.label, hours: context.raw }),
										t('empleados', 'Reports: {reports}', { reports: item.reportes }),
										t('empleados', 'Share: {percent}%', { percent: item.porcentaje.toFixed(2) }),
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

			const datos = this.selectedEmpresa
				? this.rankingEmpresas.filter(item => item.label === this.selectedEmpresa)
				: (this.chartLimit > 0 ? this.rankingEmpresas.slice(0, this.chartLimit) : this.rankingEmpresas)
			const maxReportes = Math.max(...datos.map(x => x.reportes), 1)

			this.chartClienteMatrizInstance = new Chart(this.$refs.chartClienteMatriz, {
				type: 'bubble',
				data: {
					datasets: datos.map((empresa, index) => ({
						label: empresa.label,
						data: [{
							x: Number(empresa.horas.toFixed(2)),
							y: Number(empresa.porcentaje.toFixed(2)),
							r: Math.max(7, Math.min(24, 7 + (empresa.reportes / maxReportes) * 17)),
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
										t('empleados', 'Hours: {hours}', { hours: this.formatNumber(item.horas) }),
										t('empleados', 'Share: {percent}%', { percent: this.formatNumber(item.porcentaje) }),
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
								text: t('empleados', 'Share of hours (%)'),
							},
							ticks: {
								callback: value => `${value}%`,
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

			const datos = this.graficaHorasDia

			this.chartHorasDiaInstance = new Chart(this.$refs.chartHorasDia, {
				type: 'line',
				data: {
					labels: datos.map(x => x.label),
					datasets: [{
						label: t('empleados', 'Hours by day'),
						data: datos.map(x => Number(x.horas.toFixed(2))),
						tension: 0.3,
						fill: true,
						borderColor: '#5b6cfa',
						backgroundColor: 'rgba(91, 108, 250, 0.12)',
						pointBackgroundColor: '#5b6cfa',
						pointRadius: 3,
					}],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						tooltip: {
							callbacks: {
								label(context) {
									const item = datos[context.dataIndex]
									return t('empleados', '{hours} hours in {reports} report(s)', {
										hours: context.raw,
										reports: item.reportes,
									})
								},
							},
						},
					},
					scales: {
						y: {
							beginAtZero: true,
							grid: {
								color: 'rgba(91, 108, 250, 0.12)',
							},
						},
						x: {
							grid: {
								display: false,
							},
						},
					},
				},
			})
		},

		renderGraficaReportesDia() {
			if (!this.$refs.chartReportesDia) return

			if (this.chartReportesDiaInstance) {
				this.chartReportesDiaInstance.destroy()
			}

			const datos = this.graficaReportesDia

			this.chartReportesDiaInstance = new Chart(this.$refs.chartReportesDia, {
				type: 'bar',
				data: {
					labels: datos.map(x => x.label),
					datasets: [{
						label: 'Reportes por día',
						data: datos.map(x => x.reportes),
						backgroundColor: '#f59e0b',
						borderRadius: 8,
						borderSkipped: false,
					}],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					scales: {
						x: {
							grid: {
								display: false,
							},
						},
						y: {
							beginAtZero: true,
							grid: {
								color: 'rgba(245, 158, 11, 0.15)',
							},
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
						},
					},
				},
			})
		},

		destruirGraficas() {
			if (this.chartHorasEmpleadoInstance) {
				this.chartHorasEmpleadoInstance.destroy()
				this.chartHorasEmpleadoInstance = null
			}

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

			if (this.chartReportesDiaInstance) {
				this.chartReportesDiaInstance.destroy()
				this.chartReportesDiaInstance = null
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
.resumen-general {
	width: 99%;
}

.state-card,
.hero-card,
.decision-card,
.summary-card,
.panel {
	background: var(--color-main-background, #fff);
	border: 1px solid var(--color-border, rgba(15, 23, 42, 0.08));
	border-radius: 12px;
	box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
}

.state-card {
	padding: 28px 24px;
	color: var(--color-text-maxcontrast, #6b7280);
	text-align: center;
}

.dashboard-shell {
	display: grid;
	gap: 20px;
}

.hero-grid {
	display: grid;
	grid-template-columns: minmax(0, 1.7fr) minmax(300px, 1fr);
	gap: 20px;
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
.summary-label,
.insight-label {
	font-size: 0.75rem;
	font-weight: 700;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast, #6b7280);
}

.hero-title {
	margin: 8px 0 10px;
	font-size: 1.8rem;
	line-height: 1.15;
	color: var(--color-main-text, #111827);
}

.hero-copy {
	margin: 0;
	max-width: 64ch;
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

.panel-badge {
	padding: 6px 10px;
	border-radius: 999px;
	white-space: nowrap;
	font-size: 0.75rem;
	color: var(--color-text-maxcontrast, #6b7280);
	background: var(--color-background-hover, rgba(15, 23, 42, 0.05));
}

.insight-list {
	list-style: none;
	margin: 0;
	padding: 0;
	display: grid;
	gap: 12px;
}

.insight-item {
	display: grid;
	gap: 4px;
	padding: 14px 16px;
	border-radius: 10px;
	background: var(--color-background-hover, rgba(15, 23, 42, 0.03));
}

.insight-value {
	font-size: 1rem;
	color: var(--color-main-text, #111827);
}

.insight-meta,
.summary-meta {
	font-size: 0.84rem;
	color: var(--color-text-maxcontrast, #6b7280);
}

.summary-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
	gap: 16px;
}

.summary-card {
	padding: 18px;
	min-height: 126px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}

.summary-card-accent {
	background:
		linear-gradient(180deg, rgba(91, 108, 250, 0.09), rgba(91, 108, 250, 0.02)),
		var(--color-main-background, #fff);
	border-color: rgba(91, 108, 250, 0.18);
}

.summary-value {
	font-size: 1.9rem;
	font-weight: 700;
	line-height: 1.15;
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
	font-size: 1.16rem;
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
	flex-direction: column;
	gap: 12px;
	padding: 14px;
	border: 1px solid var(--color-border, rgba(15, 23, 42, 0.08));
	border-radius: 10px;
	background: var(--color-main-background, #fff);
	box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
}

.chart-controls-bar {
	display: flex;
	flex-wrap: wrap;
	align-items: end;
	gap: 10px;
}

.chart-filters-panel {
	display: grid;
	grid-template-columns: repeat(2, minmax(180px, 1fr));
	gap: 12px;
	padding-top: 4px;
	border-top: 1px solid var(--color-border, rgba(15, 23, 42, 0.08));
}

.control-group {
	display: grid;
	gap: 5px;
	min-width: 160px;
}

.control-group.inline {
	align-items: end;
}

.control-group-wide {
	grid-column: 1 / -1;
}

.control-label {
	color: var(--color-text-maxcontrast, #6b7280);
	font-size: 0.76rem;
	font-weight: 700;
	text-transform: uppercase;
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

.exclude-list {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
	gap: 6px 12px;
	max-height: 160px;
	overflow: auto;
	padding: 8px 10px;
	border: 1px solid var(--color-border, rgba(15, 23, 42, 0.12));
	border-radius: 8px;
	background: var(--color-background-hover, rgba(15, 23, 42, 0.03));
}

.exclude-item {
	display: flex;
	align-items: center;
	gap: 8px;
	color: var(--color-main-text, #111827);
	font-size: 0.86rem;
	font-weight: 500;
	text-transform: none;
	cursor: pointer;
}

.filter-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 18px;
	height: 18px;
	margin-left: 6px;
	padding: 0 5px;
	border-radius: 999px;
	background: var(--color-primary-element, #0082c9);
	color: var(--color-primary-element-text, #fff);
	font-size: 11px;
	font-weight: 700;
	line-height: 1;
}

.clear-focus {
	min-height: 36px;
	padding: 0 14px;
	border: 1px solid var(--color-border, rgba(15, 23, 42, 0.12));
	border-radius: 8px;
	background: var(--color-background-hover, rgba(15, 23, 42, 0.05));
	color: var(--color-main-text, #111827);
	font-weight: 700;
	cursor: pointer;
}

.clear-focus.active {
	border-color: var(--color-primary-element, #0082c9);
	background: rgba(0, 130, 201, 0.12);
	color: var(--color-primary-element, #0082c9);
}

.clear-focus:disabled {
	opacity: .55;
	cursor: default;
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

.panel-wide {
	grid-column: span 8;
}

.panel-small {
	grid-column: span 4;
}

.panel-full {
	grid-column: 1 / -1;
}

.chart-box {
	position: relative;
	height: 320px;
}

.chart-box-tall {
	height: 420px;
}

.chart-box-large {
	height: 440px;
}

.panel-copy {
	max-width: 620px;
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast, #6b7280);
	font-size: 0.84rem;
	line-height: 1.4;
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
}

.ranking-table td strong {
	color: var(--color-main-text, #111827);
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
	.hero-grid {
		grid-template-columns: 1fr;
	}

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
	.panel-wide,
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
	.summary-card,
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

	.chart-box-tall,
	.chart-box-large {
		height: 320px;
	}
}

@media (max-width: 560px) {
	.summary-grid {
		grid-template-columns: 1fr;
	}

	.hero-title {
		font-size: 1.45rem;
	}

	.chart-box {
		height: 260px;
	}
}
</style>
