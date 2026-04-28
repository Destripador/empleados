<!-- eslint-disable object-curly-newline -->
<template>
	<div class="contenedor">
		<section class="hero-grid">
			<div class="hero-card hero-card-main">
				<div class="eyebrow">
					Detalle del periodo
				</div>
				<h2 class="hero-title">
					Análisis de reportes y distribución operativa
				</h2>
				<p class="hero-copy">
					Concentra la carga por proyecto, actividad y días de registro con un listado navegable de detalle.
				</p>
				<div class="hero-stats">
					<div class="hero-stat">
						<span class="hero-stat-label">Horas</span>
						<strong class="hero-stat-value">{{ kpisFmt.horas_reportadas }}</strong>
					</div>
					<div class="hero-stat">
						<span class="hero-stat-label">Costo</span>
						<strong class="hero-stat-value">{{ kpisFmt.costo_total }}</strong>
					</div>
					<div class="hero-stat">
						<span class="hero-stat-label">Reportes</span>
						<strong class="hero-stat-value">{{ kpisFmt.total_reportes }}</strong>
					</div>

					<div class="hero-stat">
						<span class="hero-stat-label">Proyectos (clientes)</span>
						<strong class="hero-stat-value">{{ kpisFmt.proyectos_activos }}</strong>
					</div>

					<div class="hero-stat">
						<span class="hero-stat-label">Actividades</span>
						<strong class="hero-stat-value">{{ kpisFmt.actividades }}</strong>
					</div>

					<div class="hero-stat">
						<span class="hero-stat-label">Promedio por reporte</span>
						<strong class="hero-stat-value">{{ kpisFmt.promedio_horas_reporte }}</strong>
					</div>
				</div>
			</div>
		</section>

		<section class="charts-grid">
			<article class="panel">
				<div class="panel-heading">
					<div>
						<div class="panel-eyebrow">
							Rendimiento
						</div>
						<h3 class="panel-title">
							Proyectos / Empresas
						</h3>
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
							Composición
						</div>
						<h3 class="panel-title">
							Actividades
						</h3>
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
							Cruce operativo
						</div>
						<h3 class="panel-title">
							Proyecto vs actividad
						</h3>
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
							Tendencia
						</div>
						<h3 class="panel-title">
							Horas por día
						</h3>
					</div>
				</div>
				<div class="chart-box">
					<canvas ref="chartHorasDia" />
				</div>
			</article>

			<article v-if="select.length > 0" class="panel panel-full">
				<div class="panel-heading">
					<div>
						<div class="panel-eyebrow">
							Detalle transaccional
						</div>
						<h3 class="panel-title">
							Reportes del periodo
						</h3>
					</div>
					<div class="panel-badge">
						{{ historial.length }} registros
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

// import { generateUrl } from '@nextcloud/router'
// import axios from '@nextcloud/axios'
// import { showError /* showSuccess */ } from '@nextcloud/dialogs'
// import { translate as t } from '@nextcloud/l10n'
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
		}
	},

	computed: {
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

					const clienteNombre = clientesMap.get(Number(idCliente)) || `Cliente ${idCliente ?? ''}`.trim()
					const actividadNombre = actividadesMap.get(Number(idActividad)) || `Actividad ${idActividad ?? ''}`.trim()

					return {
						...r,
						id,
						idCliente,
						idActividad,
						clienteNombre,
						actividadNombre,
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
			const proyectos = new Set()
			const actividades = new Set()

			for (const it of arr) {
				minutos += toNum(it?.tiempo_registrado)
				if (it?.id_cliente != null) proyectos.add(String(it.id_cliente))
				if (it?.id_actividad != null) actividades.add(String(it.id_actividad))
			}

			const horas = minutos / 60
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
			}
		},
		graficaProyectos() {
			return this.agruparReportes('idCliente', 'clienteNombre')
		},

		graficaActividades() {
			return this.agruparReportes('idActividad', 'actividadNombre')
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

				actividadesSet.add(actividad)

				if (!proyectosMap.has(proyecto)) {
					proyectosMap.set(proyecto, new Map())
				}

				const actividadMap = proyectosMap.get(proyecto)
				actividadMap.set(actividad, (actividadMap.get(actividad) || 0) + horas)
			}

			const proyectos = Array.from(proyectosMap.keys())
			const actividades = Array.from(actividadesSet.values())

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
				const label = r[nombreCampo] || `Sin ${nombreCampo}`

				const minutos = this.toNum(r.tiempo_registrado)
				const horas = minutos / 60
				const costo = horas * sueldoHora

				if (!acc.has(String(id))) {
					acc.set(String(id), {
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

		renderGraficas() {
			this.renderGraficaProyectos()
			this.renderGraficaActividades()
			this.renderGraficaHorasDia()
			this.renderGraficaProyectoActividad()
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
					datasets: [
						{
							label: 'Horas por proyecto',
							data: datos.map(x => Number(x.total.toFixed(2))),
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
										`Horas: ${horas}`,
										`Costo: ${costo}`,
										`Reportes: ${item.reportes}`,
										`Participación: ${porcentaje}%`,
									]
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
					datasets: [
						{
							label: 'Horas por actividad',
							data: datos.map(x => Number(x.total.toFixed(2))),
						},
					],
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
									return `${context.label}: ${context.raw} horas`
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

@media (max-width: 1100px) {
	.charts-grid {
		grid-template-columns: 1fr 1fr;
	}

	.panel,
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
	.kpi-card,
	.panel {
		padding: 18px;
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
