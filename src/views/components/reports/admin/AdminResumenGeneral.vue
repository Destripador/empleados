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
				</div>

				<div class="summary-card">
					<div class="summary-label">
						{{ t('empleados', 'Total cost') }}
					</div>
					<div class="summary-value">
						{{ resumenFmt.costo_total }}
					</div>
				</div>

				<div class="summary-card">
					<div class="summary-label">
						{{ t('empleados', 'Employees with reports') }}
					</div>
					<div class="summary-value">
						{{ resumenFmt.empleados_con_reportes }}
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
						</div>
						<div class="panel-badge">
							{{ t('empleados', '{count} active', { count: resumenFmt.empleados_con_reportes }) }}
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
							<span class="insight-label">{{ t('empleados', 'Operational coverage') }}</span>
							<strong class="insight-value">{{ t('empleados', '{count} employees', { count: resumenFmt.empleados_con_reportes }) }}</strong>
							<span class="insight-meta">{{ t('empleados', '{count} reports recorded', { count: resumenFmt.total_reportes }) }}</span>
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
								{{ t('empleados', 'Projects / companies') }}
							</h3>
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
						</div>
						<div class="panel-badge">
							{{ t('empleados', 'Stacked distribution') }}
						</div>
					</div>
					<div class="chart-box chart-box-large">
						<canvas ref="chartProyectoActividad" />
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
		}
	},

	computed: {
		resumenFmt() {
			const kpis = this.resumen?.kpis || {}

			const num2 = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 })
			const int = new Intl.NumberFormat('es-MX')
			const money = new Intl.NumberFormat('es-MX', {
				style: 'currency',
				currency: 'MXN',
			})

			return {
				horas_reportadas: num2.format(kpis.horas_reportadas || 0),
				costo_total: money.format(kpis.costo_total || 0),
				empleados_con_reportes: int.format(kpis.empleados_con_reportes || 0),
				total_reportes: int.format(kpis.total_reportes || 0),
				proyectos_activos: int.format(kpis.proyectos_activos || 0),
				actividades: int.format(kpis.actividades || 0),
				promedio_horas_reporte: `${num2.format(kpis.promedio_horas_reporte || 0)} h`,
			}
		},

		proyectosMap() {
			return new Map(
				(this.proyectosList || []).map(p => [
					Number(p.id),
					p.label || p.name || p.nombre || `Proyecto ${p.id}`,
				]),
			)
		},

		actividadesMap() {
			return new Map(
				(this.actividadesList || []).map(a => [
					Number(a.id),
					a.label || a.name || a.nombre || `Actividad ${a.id}`,
				]),
			)
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
						costo: this.toNum(e.costo_total),
					}
				})
				.filter(e => e.horas > 0)
				.sort((a, b) => b.horas - a.horas)
		},

		graficaProyectos() {
			const rows = this.resumen?.graficas?.horas_por_proyecto || []
			const totalHoras = this.toNum(this.resumen?.kpis?.horas_reportadas)

			return rows.map(r => {
				const horas = this.toNum(r.horas)
				return {
					label: this.proyectosMap.get(Number(r.id_cliente)) || `Proyecto ${r.id_cliente}`,
					horas,
					reportes: this.toNum(r.total_reportes),
					porcentaje: totalHoras > 0 ? (horas / totalHoras) * 100 : 0,
				}
			})
		},

		graficaActividades() {
			const rows = this.resumen?.graficas?.horas_por_actividad || []
			const totalHoras = this.toNum(this.resumen?.kpis?.horas_reportadas)

			return rows.map(r => {
				const horas = this.toNum(r.horas)
				return {
					label: this.actividadesMap.get(Number(r.id_actividad)) || `Actividad ${r.id_actividad}`,
					horas,
					reportes: this.toNum(r.total_reportes),
					porcentaje: totalHoras > 0 ? (horas / totalHoras) * 100 : 0,
				}
			})
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
				const proyecto = this.proyectosMap.get(Number(r.id_cliente)) || `Proyecto ${r.id_cliente}`
				const actividad = this.actividadesMap.get(Number(r.id_actividad)) || `Actividad ${r.id_actividad}`
				const horas = this.toNum(r.horas)

				proyectosSet.add(proyecto)
				actividadesSet.add(actividad)

				const key = `${proyecto}||${actividad}`
				matriz.set(key, (matriz.get(key) || 0) + horas)
			}

			const proyectos = Array.from(proyectosSet)
			const actividades = Array.from(actividadesSet)

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

		renderGraficas() {
			this.renderGraficaHorasEmpleado()
			this.renderGraficaProyectos()
			this.renderGraficaActividades()
			this.renderGraficaHorasDia()
			this.renderGraficaReportesDia()
			this.renderGraficaProyectoActividad()
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
									const item = datos[context.dataIndex]
									const costo = new Intl.NumberFormat('es-MX', {
										style: 'currency',
										currency: 'MXN',
									}).format(item.costo || 0)

									return [
										t('empleados', 'Hours: {hours}', { hours: context.raw }),
										t('empleados', 'Cost: {cost}', { cost: costo }),
									]
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

			const datos = this.graficaProyectos

			this.chartProyectosInstance = new Chart(this.$refs.chartProyectos, {
				type: 'bar',
				data: {
					labels: datos.map(x => x.label),
					datasets: [{
						label: t('empleados', 'Hours by project'),
						data: datos.map(x => Number(x.horas.toFixed(2))),
						backgroundColor: '#14b8a6',
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
									const item = datos[context.dataIndex]

									return [
										t('empleados', 'Hours: {hours}', { hours: context.raw }),
										t('empleados', 'Reports: {reports}', { reports: item.reportes }),
										t('empleados', 'Share: {percent}%', { percent: item.porcentaje.toFixed(2) }),
									]
								},
							},
						},
					},
					scales: {
						x: {
							beginAtZero: true,
							grid: {
								color: 'rgba(20, 184, 166, 0.14)',
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

			const datos = this.graficaActividades

			this.chartActividadesInstance = new Chart(this.$refs.chartActividades, {
				type: 'doughnut',
				data: {
					labels: datos.map(x => x.label),
					datasets: [{
						label: t('empleados', 'Hours by activity'),
						data: datos.map(x => Number(x.horas.toFixed(2))),
						backgroundColor: [
							'#5b6cfa',
							'#14b8a6',
							'#f59e0b',
							'#ef4444',
							'#8b5cf6',
							'#0ea5e9',
							'#84cc16',
						],
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

									return [
										t('empleados', '{label}: {hours} hours', { label: context.label, hours: context.raw }),
										t('empleados', 'Reports: {reports}', { reports: item.reportes }),
										t('empleados', 'Share: {percent}%', { percent: item.porcentaje.toFixed(2) }),
									]
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

@media (max-width: 1100px) {
	.hero-grid {
		grid-template-columns: 1fr;
	}

	.charts-grid {
		grid-template-columns: 1fr 1fr;
	}

	.panel,
	.panel-wide,
	.panel-full {
		grid-column: auto;
	}
}

@media (max-width: 768px) {
	.hero-stats,
	.charts-grid {
		grid-template-columns: 1fr;
	}

	.hero-card,
	.summary-card,
	.panel {
		padding: 18px;
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
