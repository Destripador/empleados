<template>
	<div class="resumen-general">
		<div v-if="loading" class="loading">
			Cargando resumen...
		</div>

		<div v-else-if="!resumen">
			No hay datos para este periodo.
		</div>

		<div v-else>
			<div class="summary-grid">
				<div class="summary-card">
					<div class="summary-value">
						{{ resumenFmt.horas_reportadas }}
					</div>
					<div class="summary-label">
						Horas reportadas
					</div>
				</div>

				<div class="summary-card">
					<div class="summary-value">
						{{ resumenFmt.costo_total }}
					</div>
					<div class="summary-label">
						Costo total
					</div>
				</div>

				<div class="summary-card">
					<div class="summary-value">
						{{ resumenFmt.empleados_con_reportes }}
					</div>
					<div class="summary-label">
						Empleados con reportes
					</div>
				</div>

				<div class="summary-card">
					<div class="summary-value">
						{{ resumenFmt.total_reportes }}
					</div>
					<div class="summary-label">
						Reportes
					</div>
				</div>

				<div class="summary-card">
					<div class="summary-value">
						{{ resumenFmt.proyectos_activos }}
					</div>
					<div class="summary-label">
						Proyectos
					</div>
				</div>

				<div class="summary-card">
					<div class="summary-value">
						{{ resumenFmt.actividades }}
					</div>
					<div class="summary-label">
						Actividades
					</div>
				</div>

				<div class="summary-card">
					<div class="summary-value">
						{{ resumenFmt.promedio_horas_reporte }}
					</div>
					<div class="summary-label">
						Promedio por reporte
					</div>
				</div>
			</div>
			<div class="acc">
				<details class="acc-item" open>
					<summary class="acc-title">
						Horas por empleado
						<span class="acc-icon" aria-hidden="true" />
					</summary>

					<div class="acc-body">
						<div class="chart-box">
							<canvas ref="chartHorasEmpleado" />
						</div>
					</div>
				</details>
				<details class="acc-item">
					<summary class="acc-title">
						Proyectos / Empresas
						<span class="acc-icon" aria-hidden="true" />
					</summary>
					<div class="acc-body">
						<div class="chart-box">
							<canvas ref="chartProyectos" />
						</div>
					</div>
				</details>

				<details class="acc-item">
					<summary class="acc-title">
						Actividades
						<span class="acc-icon" aria-hidden="true" />
					</summary>
					<div class="acc-body">
						<div class="chart-box">
							<canvas ref="chartActividades" />
						</div>
					</div>
				</details>

				<details class="acc-item">
					<summary class="acc-title">
						Horas por día
						<span class="acc-icon" aria-hidden="true" />
					</summary>
					<div class="acc-body">
						<div class="chart-box">
							<canvas ref="chartHorasDia" />
						</div>
					</div>
				</details>

				<details class="acc-item">
					<summary class="acc-title">
						Reportes por día
						<span class="acc-icon" aria-hidden="true" />
					</summary>
					<div class="acc-body">
						<div class="chart-box">
							<canvas ref="chartReportesDia" />
						</div>
					</div>
				</details>

				<details class="acc-item">
					<summary class="acc-title">
						Proyecto vs actividad
						<span class="acc-icon" aria-hidden="true" />
					</summary>
					<div class="acc-body">
						<div class="chart-box chart-box-large">
							<canvas ref="chartProyectoActividad" />
						</div>
					</div>
				</details>
			</div>
		</div>
	</div>
</template>

<script>
// eslint-disable-next-line import/no-named-as-default
import Chart from 'chart.js/auto'

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
						label: 'Horas por empleado',
						data: datos.map(x => Number(x.horas.toFixed(2))),
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
										`Horas: ${context.raw}`,
										`Costo: ${costo}`,
									]
								},
							},
						},
					},
					scales: {
						x: {
							beginAtZero: true,
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
						label: 'Horas por proyecto',
						data: datos.map(x => Number(x.horas.toFixed(2))),
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
										`Horas: ${context.raw}`,
										`Reportes: ${item.reportes}`,
										`Participación: ${item.porcentaje.toFixed(2)}%`,
									]
								},
							},
						},
					},
					scales: {
						x: {
							beginAtZero: true,
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
						label: 'Horas por actividad',
						data: datos.map(x => Number(x.horas.toFixed(2))),
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
										`${context.label}: ${context.raw} horas`,
										`Reportes: ${item.reportes}`,
										`Participación: ${item.porcentaje.toFixed(2)}%`,
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
						label: 'Horas por día',
						data: datos.map(x => Number(x.horas.toFixed(2))),
						tension: 0.3,
						fill: false,
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
									return `${context.raw} horas en ${item.reportes} reporte(s)`
								},
							},
						},
					},
					scales: {
						y: {
							beginAtZero: true,
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
					}],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
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
	width: 100%;
}

.loading {
	text-align: center;
	padding: 20px;
}

.summary-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 20px;
	margin: 24px 0;
}

.summary-card {
	background: #fff;
	border-radius: 10px;
	padding: 22px 20px;
	text-align: center;
	box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
	border: 1px solid rgba(0, 0, 0, 0.06);
}

.summary-value {
	font-family: "Cormorant Garamond", serif;
	font-size: 2.2rem;
	font-weight: 600;
	color: #555352;
	line-height: 1.1;
}

.summary-label {
	margin-top: 6px;
	font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
	font-size: 0.75rem;
	letter-spacing: 1.5px;
	text-transform: uppercase;
	color: #555352;
}

.acc {
	width: 100%;
	margin-top: 10px;
}

.acc-item {
	background: #fff;
	border: 1px solid rgba(0, 0, 0, 0.08);
	border-radius: 10px;
	box-shadow: 0 6px 18px rgba(0, 0, 0, 0.04);
	margin-bottom: 12px;
	overflow: hidden;
}

.acc-title {
	list-style: none;
	cursor: pointer;
	padding: 14px 16px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	font-weight: 600;
	color: #3c3532;
	user-select: none;
}

.acc-title::-webkit-details-marker {
	display: none;
}

.acc-icon {
	width: 10px;
	height: 10px;
	border-right: 2px solid rgba(0, 0, 0, 0.55);
	border-bottom: 2px solid rgba(0, 0, 0, 0.55);
	transform: rotate(45deg);
	transition: transform 0.2s ease;
	margin-left: 12px;
}

.acc-item[open] .acc-icon {
	transform: rotate(-135deg);
}

.acc-body {
	padding: 0 16px 16px 16px;
	color: rgba(0, 0, 0, 0.68);
	line-height: 1.5;
}

.acc-item[open] .acc-title {
	border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.chart-box {
	position: relative;
	width: 100%;
	height: 360px;
	min-height: 300px;
}

@media (max-width: 768px) {
	.chart-box {
		height: 300px;
	}
}

@media (max-width: 480px) {
	.summary-grid {
		grid-template-columns: 1fr;
	}

	.chart-box {
		height: 260px;
	}
}
.chart-box-large {
	height: 460px;
}

@media (max-width: 768px) {
	.chart-box-large {
		height: 380px;
	}
}
</style>
