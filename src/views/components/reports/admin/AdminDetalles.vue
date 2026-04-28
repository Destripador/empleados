<!-- eslint-disable object-curly-newline -->
<template>
	<div class="contenedor">
		<div class="kpi-grid">
			<div class="kpi-card">
				<div class="kpi-value">
					{{ kpisFmt.horas_reportadas }}
				</div>
				<div class="kpi-label">
					Horas reportadas
				</div>
			</div>

			<div class="kpi-card">
				<div class="kpi-value">
					{{ kpisFmt.costo_total }}
				</div>
				<div class="kpi-label">
					Costo total
				</div>
			</div>

			<div class="kpi-card">
				<div class="kpi-value">
					{{ kpisFmt.proyectos_activos }}
				</div>
				<div class="kpi-label">
					Proyectos (clientes)
				</div>
			</div>

			<div class="kpi-card">
				<div class="kpi-value">
					{{ kpisFmt.actividades }}
				</div>
				<div class="kpi-label">
					Actividades
				</div>
			</div>

			<div class="kpi-card">
				<div class="kpi-value">
					{{ kpisFmt.total_reportes }}
				</div>
				<div class="kpi-label">
					Reportes
				</div>
			</div>

			<div class="kpi-card">
				<div class="kpi-value">
					{{ kpisFmt.promedio_horas_reporte }}
				</div>
				<div class="kpi-label">
					Promedio por reporte
				</div>
			</div>
		</div>

		<div class="top">
			<div class="acc">
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
						Proyecto vs actividad
						<span class="acc-icon" aria-hidden="true" />
					</summary>
					<div class="acc-body">
						<div class="chart-box chart-box-large">
							<canvas ref="chartProyectoActividad" />
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

				<details v-if="select.length > 0" class="acc-item">
					<summary class="acc-title">
						Reportes del periodo (detalles)
						<span class="acc-icon" aria-hidden="true" />
					</summary>
					<div class="acc-body">
						<VirtualList
							class="list"
							:data-sources="historial"
							:data-key="'id_reporte'"
							:data-component="rowComponent"
							:extra-props="{ proyectosList, actividadesList }" />
					</div>
				</details>
			</div>
		</div>
	</div>
</template>

<script>

// import { generateUrl } from '@nextcloud/router'
// import axios from '@nextcloud/axios'
// import { showError /* showSuccess */ } from '@nextcloud/dialogs'
// import { translate as t } from '@nextcloud/l10n'
import ReportRow from '../../Helpers/Lists/ReportRow.vue'
import VirtualList from 'vue-virtual-scroll-list'

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

<style>
.details {
	padding-left: 10px;
	padding-right: 10px;
}
.box1Inside { flex: 3; }
.flex-center {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	justify-content: center;
	align-items: center;
}
:root {
  --primary-color: #f6eee5;
  --secondary-color: #e8d8c7;
  --accent: #c9a892;
  --dark-accent: #8c7a76;
  --text-color: #3c3532;
  --light-text: #6d6661;
  --white: #ffffff;
}

/* Grid */
.kpi-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 20px;
}

/* Card */
.kpi-card {
  background: var(--white);
  border-radius: 10px;
  padding: 22px 20px;
  text-align: center;
  box-shadow: 0 6px 18px rgba(0,0,0,0.06);
  transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.kpi-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 26px rgba(0,0,0,0.1);
}

/* Number */
.kpi-value {
  font-family: "Cormorant Garamond", serif;
  font-size: 2.4rem;
  font-weight: 600;
  color: #555352;;
  line-height: 1.1;
}

/* Label */
.kpi-label {
  margin-top: 6px;
  font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  font-size: 0.75rem;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: #555352;;
}

/* Responsive */
@media (max-width: 900px) {
  .kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 480px) {
  .kpi-grid {
    grid-template-columns: 1fr;
  }
}
.acc {
  width: 100%;
}

/* item */
.acc-item {
  background: #fff;
  border: 1px solid rgba(0,0,0,0.08);
  border-radius: 10px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.04);
  margin-bottom: 12px;
  overflow: hidden;
}

/* title */
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

/* quita el marcador default */
.acc-title::-webkit-details-marker {
  display: none;
}

/* icon */
.acc-icon {
  width: 10px;
  height: 10px;
  border-right: 2px solid rgba(0,0,0,0.55);
  border-bottom: 2px solid rgba(0,0,0,0.55);
  transform: rotate(45deg);
  transition: transform 0.2s ease;
  margin-left: 12px;
}

/* abierto => flecha hacia arriba */
.acc-item[open] .acc-icon {
  transform: rotate(-135deg);
}

/* body */
.acc-body {
  padding: 0 16px 16px 16px;
  color: rgba(0,0,0,0.68);
  line-height: 1.5;
}

/* separador suave entre title y body */
.acc-item[open] .acc-title {
  border-bottom: 1px solid rgba(0,0,0,0.06);
}

.contenedor {
	margin-right: 10px;
	margin-left: 10px;
	margin-top: 20px;
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
