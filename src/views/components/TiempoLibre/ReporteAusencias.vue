<template>
	<div class="reporte-contenido">
		<!-- Header -->
		<div class="reporte-header">
			<div class="reporte-header-left">
				<h2 class="reporte-titulo">
					{{ t('empleados', 'Reporte de Ausencias') }}
				</h2>
				<span v-if="!cargando && registrosVistaActual.length > 0" class="reporte-count">
					{{ registrosVistaActual.length }} {{ t('empleados', 'registro') }}{{ registrosVistaActual.length !== 1 ? 's' : '' }}
				</span>
			</div>
			<NcButton type="tertiary" @click="$emit('close')">
				<template #icon>
					<Close :size="20" />
				</template>
				{{ t('empleados', 'Cerrar') }}
			</NcButton>
		</div>

		<!-- Pestañas de vista -->
		<div v-if="!cargando && haCargadoAlMenos" class="vista-switch">
			<button
				type="button"
				class="vista-switch-btn"
				:class="{ 'vista-switch-btn--activo': vistaActual === 'todos' }"
				@click="vistaActual = 'todos'">
				{{ t('empleados', 'Todos los registros') }}
			</button>
			<button
				type="button"
				class="vista-switch-btn"
				:class="{ 'vista-switch-btn--activo': vistaActual === 'resumen' }"
				@click="vistaActual = 'resumen'">
				{{ t('empleados', 'Resumen por empleado') }}
			</button>
		</div>

		<!-- Filtros de fecha/año + botón filtros -->
		<div class="reporte-filtros">
			<template v-if="vistaActual === 'todos'">
				<div class="filtro-grupo">
					<label class="filtro-label">{{ t('empleados', 'Desde') }}</label>
					<input v-model="filtroDesde" type="date" class="filtro-input">
				</div>
				<div class="filtro-grupo">
					<label class="filtro-label">{{ t('empleados', 'Hasta') }}</label>
					<input v-model="filtroHasta" type="date" class="filtro-input">
				</div>
			</template>

			<div v-else-if="empleadoResumen" class="filtro-grupo">
				<label class="filtro-label">{{ t('empleados', 'Periodo') }}</label>
				<select v-model.number="periodoSeleccionado" class="filtro-input filtro-select">
					<option v-for="p in periodosEmpleado" :key="p.numero_aniversario" :value="p.numero_aniversario">
						{{ t('empleados', 'Aniversario') }} {{ p.numero_aniversario }} ({{ formatFecha(p.periodo_inicio) }} → {{ formatFecha(p.periodo_fin) }})
					</option>
				</select>
			</div>

			<NcButton type="primary" :disabled="cargando" @click="cargarReporte">
				<template #icon>
					<Magnify :size="18" />
				</template>
				{{ cargando ? t('empleados', 'Cargando…') : t('empleados', 'Buscar') }}
			</NcButton>

			<!-- Botón filtros de tabla (solo aplica a "Todos los registros") -->
			<div v-if="!cargando && registros.length > 0 && vistaActual === 'todos'" class="filtros-btn-wrap">
				<NcButton :type="hayFiltrosActivos ? 'primary' : 'secondary'" @click="mostrarFiltros = true">
					<template #icon>
						<FilterVariant :size="18" />
					</template>
					{{ t('empleados', 'Filtros') }}
					<span v-if="contadorFiltros > 0" class="filtros-badge">{{ contadorFiltros }}</span>
				</NcButton>
				<NcButton v-if="hayFiltrosActivos" type="tertiary" @click="limpiarFiltros">
					<template #icon>
						<FilterOff :size="16" />
					</template>
				</NcButton>
			</div>
		</div>

		<!-- Body -->
		<div class="reporte-body">
			<div v-if="cargando" class="reporte-estado">
				<NcLoadingIcon :size="40" />
				<p>{{ t('empleados', 'Cargando registros…') }}</p>
			</div>

			<!-- ─── Vista: Resumen por empleado ─── -->
			<!-- Esta vista se evalúa ANTES del check de "sin registros": el selector de
			     empleados y las tarjetas de resumen deben verse aunque el periodo elegido
			     no tenga ausencias (antes se ocultaban por completo, que era el bug). -->
			<div v-else-if="vistaActual === 'resumen'" class="resumen-vista">
				<div class="resumen-selector">
					<AccountSearch :size="20" class="resumen-selector-icon" />
					<select v-model="empleadoResumen" class="filtro-input filtro-select resumen-selector-input">
						<option value="" disabled>
							{{ t('empleados', 'Selecciona un empleado') }}
						</option>
						<option v-for="emp in opcionesEmpleados" :key="emp" :value="emp">
							{{ emp }}
						</option>
					</select>
				</div>

				<div v-if="!empleadoResumen" class="reporte-estado periodo-vac-vacio">
					<span class="reporte-estado-icon">🏖️</span>
					<p>{{ t('empleados', 'Selecciona un empleado para ver su resumen.') }}</p>
				</div>

				<template v-else>
					<div class="periodo-vac-resumen">
						<div class="resumen-card">
							<span class="resumen-label">{{ t('empleados', 'Días derecho') }}</span>
							<span class="resumen-valor">{{ periodoInfo?.dias_derecho ?? '—' }}</span>
						</div>
						<div class="resumen-card">
							<span class="resumen-label">{{ t('empleados', 'Días disfrutados') }}</span>
							<span class="resumen-valor">{{ periodoInfo?.dias_disfrutados ?? resumenEmpleadoStats.dias }}</span>
						</div>
						<div class="resumen-card">
							<span class="resumen-label">{{ t('empleados', 'Días restantes') }}</span>
							<span class="resumen-valor">{{ periodoInfo?.dias_restantes ?? '—' }}</span>
						</div>
						<div class="resumen-card">
							<span class="resumen-label">{{ t('empleados', 'Registros') }}</span>
							<span class="resumen-valor">{{ resumenEmpleadoStats.total }}</span>
						</div>
						<div class="resumen-card">
							<span class="resumen-label">{{ t('empleados', 'Con prima vac.') }}</span>
							<span class="resumen-valor">{{ resumenEmpleadoStats.conPrima }}</span>
						</div>
						<div class="resumen-card">
							<span class="resumen-label">{{ t('empleados', 'Sin prima vac.') }}</span>
							<span class="resumen-valor">{{ resumenEmpleadoStats.sinPrima }}</span>
						</div>
					</div>

					<div v-if="registrosResumenEmpleado.length === 0" class="reporte-estado periodo-vac-vacio">
						<span class="reporte-estado-icon">🔍</span>
						<p>{{ t('empleados', 'Sin registros para este empleado en el año seleccionado.') }}</p>
					</div>

					<div v-else class="reporte-tabla-wrap">
						<table class="reporte-tabla">
							<thead>
								<tr>
									<th>{{ t('empleados', 'Empleado') }}</th>
									<th>{{ t('empleados', 'Tipo de ausencia') }}</th>
									<th>{{ t('empleados', 'Periodo') }}</th>
									<th class="col-dias cell-center">
										{{ t('empleados', 'Días') }}
									</th>
									<th class="col-prima">
										{{ t('empleados', 'Prima vac.') }}
									</th>
									<th>{{ t('empleados', 'Estado') }}</th>
									<th class="col-aprobacion">
										{{ t('empleados', 'Aprobación') }}
									</th>
									<th class="col-solicitud">
										{{ t('empleados', 'Solicitud') }}
									</th>
								</tr>
							</thead>
							<tbody>
								<tr
									v-for="(item, i) in registrosResumenEmpleado"
									:key="item.id_historial_ausencias || i"
									:class="rowClass(item)">
									<td class="cell-empleado">
										<img
											class="empleado-avatar"
											:src="avatarUrl(item.nombre_empleado)"
											:alt="item.nombre_empleado"
											@error="onAvatarError($event, item.nombre_empleado)">
										<span class="empleado-nombre">{{ item.nombre_empleado }}</span>
									</td>
									<td>
										<span class="badge-tipo" :style="colorTipo(item.tipo_ausencia)">
											{{ item.tipo_ausencia }}
										</span>
									</td>
									<td>
										<span>{{ formatFecha(item.fecha_de) }}</span>
										<span class="periodo-sep">→</span>
										<span>{{ formatFecha(item.fecha_hasta) }}</span>
									</td>
									<td class="col-dias cell-center">
										<strong>{{ item.dias_solicitados ?? '—' }}</strong>
									</td>
									<td class="col-prima cell-center">
										<span v-if="parseInt(item.prima_vacacional) === 1" class="badge-prima">{{ t('empleados', 'Sí') }}</span>
										<span v-else class="badge-prima-no">{{ t('empleados', 'No') }}</span>
									</td>
									<td>
										<span class="chip" :class="chipEstado(item).clase">
											{{ chipEstado(item).texto }}
										</span>
									</td>
									<td class="col-aprobacion">
										<span class="chip-mini" :class="chipAprobacion(item.a_gerente).clase" :title="t('empleados', 'Gerente')">
											{{ t('empleados', 'G:') }} {{ chipAprobacion(item.a_gerente).texto }}
										</span>
										<span class="chip-mini" :class="chipAprobacion(item.a_socio).clase" :title="t('empleados', 'Socio')">
											{{ t('empleados', 'S:') }} {{ chipAprobacion(item.a_socio).texto }}
										</span>
									</td>
									<td class="cell-fecha">
										{{ formatTimestamp(item.timestamp) }}
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</template>
			</div>

			<div v-else-if="registros.length === 0" class="reporte-estado">
				<span class="reporte-estado-icon">📋</span>
				<p>{{ t('empleados', 'Sin registros en el periodo seleccionado.') }}</p>
			</div>

			<div v-else-if="registrosFiltrados.length === 0" class="reporte-estado">
				<span class="reporte-estado-icon">🔍</span>
				<p>{{ t('empleados', 'Ningún registro coincide con los filtros.') }}</p>
				<NcButton type="secondary" @click="limpiarFiltros">
					{{ t('empleados', 'Limpiar filtros') }}
				</NcButton>
			</div>

			<div v-else class="reporte-tabla-wrap">
				<table class="reporte-tabla">
					<thead>
						<tr>
							<th>{{ t('empleados', 'Empleado') }}</th>
							<th>{{ t('empleados', 'Tipo de ausencia') }}</th>
							<th>{{ t('empleados', 'Periodo') }}</th>
							<th class="col-dias cell-center">
								{{ t('empleados', 'Días') }}
							</th>
							<th class="col-prima">
								{{ t('empleados', 'Prima vac.') }}
							</th>
							<th>{{ t('empleados', 'Estado') }}</th>
							<th class="col-aprobacion">
								{{ t('empleados', 'Aprobación') }}
							</th>
							<th class="col-solicitud">
								{{ t('empleados', 'Solicitud') }}
							</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="(item, i) in registrosFiltrados"
							:key="item.id_historial_ausencias || i"
							:class="rowClass(item)">
							<td class="cell-empleado">
								<img
									class="empleado-avatar"
									:src="avatarUrl(item.nombre_empleado)"
									:alt="item.nombre_empleado"
									@error="onAvatarError($event, item.nombre_empleado)">
								<span class="empleado-nombre">{{ item.nombre_empleado }}</span>
							</td>
							<td>
								<span class="badge-tipo" :style="colorTipo(item.tipo_ausencia)">
									{{ item.tipo_ausencia }}
								</span>
							</td>
							<td>
								<span>{{ formatFecha(item.fecha_de) }}</span>
								<span class="periodo-sep">→</span>
								<span>{{ formatFecha(item.fecha_hasta) }}</span>
							</td>
							<td class="col-dias cell-center">
								<strong>{{ item.dias_solicitados ?? '—' }}</strong>
							</td>
							<td class="col-prima cell-center">
								<span v-if="parseInt(item.prima_vacacional) === 1" class="badge-prima">{{ t('empleados', 'Sí') }}</span>
								<span v-else class="badge-prima-no">{{ t('empleados', 'No') }}</span>
							</td>
							<td>
								<span class="chip" :class="chipEstado(item).clase">
									{{ chipEstado(item).texto }}
								</span>
							</td>
							<td class="col-aprobacion">
								<span class="chip-mini" :class="chipAprobacion(item.a_gerente).clase" :title="t('empleados', 'Gerente')">
									{{ t('empleados', 'G:') }} {{ chipAprobacion(item.a_gerente).texto }}
								</span>
								<span class="chip-mini" :class="chipAprobacion(item.a_socio).clase" :title="t('empleados', 'Socio')">
									{{ t('empleados', 'S:') }} {{ chipAprobacion(item.a_socio).texto }}
								</span>
							</td>
							<td class="cell-fecha">
								{{ formatTimestamp(item.timestamp) }}
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Modal de filtros -->
		<NcModal
			v-if="mostrarFiltros"
			size="small"
			:name="t('empleados', 'Filtrar registros')"
			@close="mostrarFiltros = false">
			<div class="filtros-modal">
				<!-- Empleado -->
				<div class="filtro-modal-grupo">
					<label class="filtro-label">{{ t('empleados', 'Empleado') }}</label>
					<select v-model="filtroEmpleado" class="filtro-input filtro-select">
						<option value="">
							{{ t('empleados', 'Todos') }}
						</option>
						<option v-for="emp in opcionesEmpleados" :key="emp" :value="emp">
							{{ emp }}
						</option>
					</select>
				</div>

				<!-- Tipo de ausencia -->
				<div class="filtro-modal-grupo">
					<label class="filtro-label">{{ t('empleados', 'Tipo de ausencia') }}</label>
					<select v-model="filtroTipo" class="filtro-input filtro-select">
						<option value="">
							{{ t('empleados', 'Todos') }}
						</option>
						<option v-for="tipo in opcionesTipos" :key="tipo" :value="tipo">
							{{ tipo }}
						</option>
					</select>
				</div>

				<!-- Estado -->
				<div class="filtro-modal-grupo">
					<label class="filtro-label">{{ t('empleados', 'Estado') }}</label>
					<select v-model="filtroEstado" class="filtro-input filtro-select">
						<option value="">
							{{ t('empleados', 'Todos') }}
						</option>
						<option value="Pendiente">
							{{ t('empleados', 'Pendiente') }}
						</option>
						<option value="En curso">
							{{ t('empleados', 'En curso') }}
						</option>
						<option value="Completada">
							{{ t('empleados', 'Completada') }}
						</option>
						<option value="Cancelada">
							{{ t('empleados', 'Cancelada') }}
						</option>
					</select>
				</div>

				<!-- Aprobación -->
				<div class="filtro-modal-grupo">
					<label class="filtro-label">{{ t('empleados', 'Aprobación') }}</label>
					<select v-model="filtroAprobacion" class="filtro-input filtro-select">
						<option value="">
							{{ t('empleados', 'Todos') }}
						</option>
						<option value="0">
							{{ t('empleados', 'Pendiente') }}
						</option>
						<option value="1">
							{{ t('empleados', 'Aprobado') }}
						</option>
						<option value="2">
							{{ t('empleados', 'Rechazado') }}
						</option>
						<option value="3">
							{{ t('empleados', 'Cancelado') }}
						</option>
					</select>
				</div>

				<!-- Prima vacacional -->
				<div class="filtro-modal-grupo">
					<label class="filtro-check-label">
						<input v-model="filtroPrima" type="checkbox" class="filtro-check">
						{{ t('empleados', 'Solo con prima vacacional') }}
					</label>
				</div>

				<!-- Acciones -->
				<div class="filtros-modal-acciones">
					<NcButton type="tertiary" @click="limpiarFiltros">
						<template #icon>
							<FilterOff :size="16" />
						</template>
						{{ t('empleados', 'Limpiar') }}
					</NcButton>
					<NcButton type="primary" @click="mostrarFiltros = false">
						{{ t('empleados', 'Aplicar') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import Close from 'vue-material-design-icons/Close.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import FilterVariant from 'vue-material-design-icons/FilterVariant.vue'
import FilterOff from 'vue-material-design-icons/FilterOff.vue'
import AccountSearch from 'vue-material-design-icons/AccountSearch.vue'

const PALETA_TIPOS = [
	{ bg: '#dbeafe', color: '#1d4ed8' },
	{ bg: '#fef3c7', color: '#b45309' },
	{ bg: '#d1fae5', color: '#065f46' },
	{ bg: '#ede9fe', color: '#5b21b6' },
	{ bg: '#fee2e2', color: '#b91c1c' },
	{ bg: '#fce7f3', color: '#9d174d' },
	{ bg: '#ccfbf1', color: '#0f766e' },
	{ bg: '#ffedd5', color: '#c2410c' },
]

function hashStr(str) {
	let h = 0
	for (let i = 0; i < str.length; i++) h = (h * 31 + str.charCodeAt(i)) >>> 0
	return h
}

export default {
	name: 'ReporteAusencias',

	components: { NcButton, NcLoadingIcon, NcModal, Close, Magnify, FilterVariant, FilterOff, AccountSearch },

	emits: ['close'],

	data() {
		const hoy = new Date()
		const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1)
		return {
			cargando: false,
			registros: [],
			filtroDesde: primerDia.toISOString().slice(0, 10),
			filtroHasta: hoy.toISOString().slice(0, 10),
			mostrarFiltros: false,
			filtroEmpleado: '',
			filtroTipo: '',
			filtroEstado: '',
			filtroAprobacion: '',
			filtroPrima: false,
			vistaActual: 'todos',
			empleadoResumen: '',
			periodosEmpleado: [],
			periodoSeleccionado: null,
			cargandoPeriodos: false,
			haCargadoAlMenos: false,
			// Catálogo de empleados independiente del periodo/rango consultado.
			// Antes "opcionesEmpleados" salía de "registros", que se reemplaza en
			// cada cargarReporte(); si el periodo elegido no tenía registros, el
			// selector de empleados se quedaba sin opciones (el bug reportado).
			empleadosCatalogo: [],
		}
	},

	computed: {
		opcionesEmpleados() {
			if (this.empleadosCatalogo.length > 0) return this.empleadosCatalogo
			return [...new Set(this.registros.map(r => r.nombre_empleado).filter(Boolean))].sort()
		},

		opcionesTipos() {
			return [...new Set(this.registros.map(r => r.tipo_ausencia).filter(Boolean))].sort()
		},

		hayFiltrosActivos() {
			return !!(this.filtroEmpleado || this.filtroTipo || this.filtroEstado || this.filtroAprobacion || this.filtroPrima)
		},

		contadorFiltros() {
			return [this.filtroEmpleado, this.filtroTipo, this.filtroEstado, this.filtroAprobacion, this.filtroPrima]
				.filter(Boolean).length
		},

		registrosFiltrados() {
			return this.registros.filter(item => {
				if (this.filtroEmpleado && item.nombre_empleado !== this.filtroEmpleado) return false
				if (this.filtroTipo && item.tipo_ausencia !== this.filtroTipo) return false
				if (this.filtroPrima && parseInt(item.prima_vacacional) !== 1) return false
				if (this.filtroEstado && this.chipEstado(item).texto !== this.filtroEstado) return false
				if (this.filtroAprobacion !== '') {
					const v = parseInt(this.filtroAprobacion)
					if (parseInt(item.a_gerente) !== v && parseInt(item.a_socio) !== v) return false
				}
				return true
			})
		},

		registrosVistaActual() {
			return this.vistaActual === 'resumen' ? this.registrosResumenEmpleado : this.registrosFiltrados
		},

		periodoInfo() {
			return this.periodosEmpleado.find(p => p.numero_aniversario === this.periodoSeleccionado) || null
		},

		registrosResumenEmpleado() {
			if (!this.empleadoResumen) return []
			return this.registros.filter(item => {
				if (item.nombre_empleado !== this.empleadoResumen) return false
				if (this.chipEstado(item).texto === t('empleados', 'Cancelada')) return false
				if (parseInt(item.solicitar_prima_vacacional) !== 1) return false
				return true
			}).sort((a, b) => this.parseFecha(a.fecha_de) - this.parseFecha(b.fecha_de))
		},

		resumenEmpleadoStats() {
			const registros = this.registrosResumenEmpleado
			const dias = registros.reduce((acc, r) => acc + (parseInt(r.dias_solicitados) || 0), 0)
			const conPrima = registros.filter(r => parseInt(r.prima_vacacional) === 1).length
			return {
				total: registros.length,
				dias,
				conPrima,
				sinPrima: registros.length - conPrima,
			}
		},
	},

	watch: {
		vistaActual() {
			this.cargarReporte()
		},

		empleadoResumen() {
			this.cargarPeriodosEmpleado()
		},

		periodoSeleccionado() {
			if (this.vistaActual === 'resumen') {
				this.cargarReporte()
			}
		},
	},

	mounted() {
		this.cargarEmpleadosCatalogo()
		this.cargarReporte()
	},

	methods: {
		t,

		limpiarFiltros() {
			this.filtroEmpleado = ''
			this.filtroTipo = ''
			this.filtroEstado = ''
			this.filtroAprobacion = ''
			this.filtroPrima = false
		},

		async cargarReporte() {
			this.cargando = true
			this.registros = []
			this.limpiarFiltros()
			try {
				const url = generateUrl('/apps/empleados/historial-reporte')
				const params = (this.vistaActual === 'resumen' && this.periodoInfo)
					? { desde: this.periodoInfo.periodo_inicio, hasta: this.periodoInfo.periodo_fin }
					: { desde: this.filtroDesde, hasta: this.filtroHasta }
				const { data } = await axios.get(url, { params })
				const mensaje = data?.ocs?.data?.message ?? data?.message ?? []
				this.registros = Array.isArray(mensaje) ? mensaje : []
			} catch (e) {
				console.error('Error cargando reporte:', e)
			} finally {
				this.cargando = false
				this.haCargadoAlMenos = true
				// Alimenta el catálogo de empleados con lo que vaya llegando, por si
				// aún no se cargó con cargarEmpleadosCatalogo() (p. ej. primera carga).
				if (this.empleadosCatalogo.length === 0 && this.registros.length > 0) {
					this.empleadosCatalogo = [...new Set(this.registros.map(r => r.nombre_empleado).filter(Boolean))].sort()
				}
			}
		},

		/**
		 * Carga, una sola vez, el listado completo de empleados con historial
		 * (independiente del rango de fechas/periodo que esté activo en el
		 * reporte), para que el selector de "Resumen por empleado" no dependa
		 * de si el periodo seleccionado trajo registros o no.
		 */
		async cargarEmpleadosCatalogo() {
			try {
				const url = generateUrl('/apps/empleados/historial-reporte')
				const { data } = await axios.get(url, { params: { desde: '1970-01-01', hasta: '2999-12-31' } })
				const mensaje = data?.ocs?.data?.message ?? data?.message ?? []
				const todos = Array.isArray(mensaje) ? mensaje : []
				this.empleadosCatalogo = [...new Set(todos.map(r => r.nombre_empleado).filter(Boolean))].sort()
			} catch (e) {
				console.error('Error cargando catálogo de empleados:', e)
			}
		},

		async cargarVacacionesEmpleado() {
			this.vacacionesInfo = null
			if (!this.empleadoResumen) return

			const item = this.registros.find(r => r.nombre_empleado === this.empleadoResumen)
			const idEmpleado = item?.id_empleado
			if (!idEmpleado) return

			this.cargandoVacaciones = true
			try {
				const url = generateUrl('/apps/empleados/vacaciones-empleado')
				const { data } = await axios.get(url, { params: { id_empleado: idEmpleado } })
				this.vacacionesInfo = data?.ocs?.data?.message ?? data?.message ?? null
			} catch (e) {
				console.error('Error cargando vacaciones:', e)
			} finally {
				this.cargandoVacaciones = false
			}
		},

		async cargarPeriodosEmpleado() {
			this.periodosEmpleado = []
			this.periodoSeleccionado = null
			if (!this.empleadoResumen) return

			const item = this.registros.find(r => r.nombre_empleado === this.empleadoResumen)
			const idEmpleado = item?.id_empleado
			if (!idEmpleado) return

			this.cargandoPeriodos = true
			try {
				const url = generateUrl('/apps/empleados/periodos-vacaciones')
				const { data } = await axios.get(url, { params: { id_empleado: idEmpleado } })
				const periodos = data?.ocs?.data?.message ?? data?.message ?? []
				this.periodosEmpleado = Array.isArray(periodos) ? periodos : []
				const actual = this.periodosEmpleado.find(p => p.es_actual)
				this.periodoSeleccionado = actual ? actual.numero_aniversario : (this.periodosEmpleado[0]?.numero_aniversario ?? null)
			} catch (e) {
				console.error('Error cargando periodos:', e)
			} finally {
				this.cargandoPeriodos = false
			}
		},

		avatarUrl(uid) {
			return generateUrl('/avatar/{uid}/32', { uid })
		},

		onAvatarError(event, nombre) {
			const iniciales = this.iniciales(nombre)
			const paleta = PALETA_TIPOS[hashStr(nombre) % PALETA_TIPOS.length]
			const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32">
				<circle cx="16" cy="16" r="16" fill="${paleta.bg}"/>
				<text x="16" y="21" text-anchor="middle" font-size="13" font-weight="700" font-family="sans-serif" fill="${paleta.color}">${iniciales}</text>
			</svg>`
			event.target.src = 'data:image/svg+xml;utf8,' + encodeURIComponent(svg)
		},

		colorTipo(tipo) {
			if (!tipo) return {}
			const p = PALETA_TIPOS[hashStr(tipo) % PALETA_TIPOS.length]
			return { background: p.bg, color: p.color }
		},

		parseFecha(str) {
			if (!str) return null
			return new Date(str.replace(' ', 'T'))
		},

		rowClass(item) {
			if (parseInt(item.a_gerente) === 3 || parseInt(item.a_socio) === 3) return 'row-cancelado'
			const hoy = new Date(); hoy.setHours(0, 0, 0, 0)
			const hasta = this.parseFecha(item.fecha_hasta)
			return hasta < hoy ? 'row-pasado' : 'row-futuro'
		},

		chipEstado(item) {
			if (parseInt(item.a_gerente) === 3 || parseInt(item.a_socio) === 3) {
				return { texto: t('empleados', 'Cancelada'), clase: 'chip-cancelado' }
			}
			const hoy = new Date(); hoy.setHours(0, 0, 0, 0)
			const hasta = this.parseFecha(item.fecha_hasta)
			const de = this.parseFecha(item.fecha_de)
			if (hasta < hoy) return { texto: t('empleados', 'Completada'), clase: 'chip-completado' }
			if (de <= hoy && hasta >= hoy) return { texto: t('empleados', 'En curso'), clase: 'chip-encurso' }
			return { texto: t('empleados', 'Pendiente'), clase: 'chip-pendiente' }
		},

		chipAprobacion(valor) {
			const v = parseInt(valor)
			if (v === 1) return { texto: t('empleados', 'Aprobado'), clase: 'chip-a-aprobado' }
			if (v === 2) return { texto: t('empleados', 'Rechazado'), clase: 'chip-a-rechazado' }
			if (v === 3) return { texto: t('empleados', 'Cancelado'), clase: 'chip-a-cancelado' }
			return { texto: t('empleados', 'Pendiente'), clase: 'chip-a-pendiente' }
		},

		iniciales(nombre) {
			if (!nombre) return '?'
			return nombre.split(/[\s._-]/).map(p => p[0]).slice(0, 2).join('').toUpperCase()
		},

		formatFecha(fecha) {
			if (!fecha) return '—'
			const [y, m, d] = fecha.slice(0, 10).split('-')
			return `${d}/${m}/${y}`
		},

		formatTimestamp(ts) {
			if (!ts) return '—'
			const d = new Date(ts)
			if (isNaN(d)) return ts
			return d.toLocaleString('es-MX', {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric',
				hour: '2-digit',
				minute: '2-digit',
			})
		},
	},
}
</script>

<style scoped>
.col-aprobacion {
	min-width: 170px;
	width: 170px;
}

.reporte-contenido {
	display: flex;
	flex-direction: column;
	height: 80vh;
	overflow: hidden;
}

/* ── Header ── */
.reporte-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 16px 24px;
	border-bottom: 1px solid var(--color-border);
	flex-shrink: 0;
}

.reporte-header-left {
	display: flex;
	align-items: baseline;
	gap: 12px;
}

.reporte-titulo {
	font-size: 1.15rem;
	font-weight: 700;
	margin: 0;
	color: var(--color-main-text);
}

.reporte-count {
	font-size: 0.8rem;
	color: var(--color-text-maxcontrast);
	background: var(--color-background-dark);
	padding: 2px 8px;
	border-radius: 20px;
}

/* ── Pestañas de vista ── */
.vista-switch {
	display: flex;
	gap: 4px;
	padding: 10px 24px 0;
	flex-shrink: 0;
}

.vista-switch-btn {
	border: 1px solid var(--color-border-dark);
	background: var(--color-main-background);
	color: var(--color-text-maxcontrast);
	border-radius: 20px;
	padding: 7px 16px;
	font-size: 0.85rem;
	font-weight: 600;
	cursor: pointer;
	transition: background 0.12s, color 0.12s, border-color 0.12s;
}

.vista-switch-btn:hover { background: var(--color-background-hover); }

.vista-switch-btn--activo {
	background: var(--color-main-text);
	color: var(--color-main-background);
	border-color: var(--color-main-text);
}

/* ── Filtros de fecha ── */
.reporte-filtros {
	display: flex;
	align-items: flex-end;
	gap: 16px;
	padding: 14px 24px;
	border-bottom: 1px solid var(--color-border);
	flex-shrink: 0;
	flex-wrap: wrap;
}

.filtros-btn-wrap {
	display: flex;
	align-items: center;
	gap: 4px;
	margin-left: auto;
}

.filtros-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	background: white;
	color: var(--color-primary);
	border-radius: 20px;
	font-size: 0.7rem;
	font-weight: 700;
	min-width: 18px;
	height: 18px;
	padding: 0 4px;
	margin-left: 4px;
}

.filtro-grupo {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.filtro-label {
	font-size: 0.75rem;
	color: var(--color-text-maxcontrast);
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.filtro-input {
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	padding: 6px 10px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 0.875rem;
	height: 36px;
}

.filtro-select {
	min-width: 160px;
	cursor: pointer;
}

.filtro-check-label {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 0.875rem;
	color: var(--color-main-text);
	cursor: pointer;
}

.filtro-check { cursor: pointer; }

/* ── Modal de filtros ── */
.filtros-modal {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 20px;
	min-width: 300px;
}

.filtro-modal-grupo {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.filtro-modal-grupo .filtro-select {
	width: 100%;
}

.filtros-modal-acciones {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	padding-top: 8px;
	border-top: 1px solid var(--color-border);
}

/* ── Body ── */
.reporte-body {
	flex: 1;
	overflow-y: auto;
	overflow-x: auto;
	padding: 20px 24px;
	position: relative;
}

.reporte-estado {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	height: 100%;
	gap: 12px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}

.reporte-estado-icon { font-size: 2rem; }

.reporte-tabla-wrap { overflow-x: unset; }

/* ── Tabla ── */
.reporte-tabla {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.875rem;
	table-layout: fixed;
}

.reporte-tabla th {
	text-align: left;
	padding: 10px 12px;
	font-size: 0.72rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.06em;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}

.reporte-tabla thead {
	display: table;
	width: 100%;
	table-layout: fixed;
}

.reporte-tabla thead tr { border-bottom: 2px solid var(--color-border-dark); }

.reporte-tabla thead th {
	position: sticky;
	top: 0;
	z-index: 5;
	background: var(--color-main-background);
	box-shadow: 0 2px 0 var(--color-border);
}

.reporte-tabla tbody {
	display: block;
	overflow-y: auto;
	max-height: calc(80vh - 220px);
}

.reporte-tabla tbody tr {
	display: table;
	width: 100%;
	table-layout: fixed;
	transition: background 0.12s;
}

.reporte-tabla tbody tr:hover { background: var(--color-background-hover); }

.reporte-tabla td {
	padding: 8px 12px;
	vertical-align: middle;
	border-bottom: 1px solid var(--color-border);
}

/* ── Filas por estado ── */
.row-cancelado td:first-child { border-left: 3px solid var(--color-error); }
.row-pasado td:first-child    { border-left: 3px solid var(--color-success); }
.row-futuro td:first-child    { border-left: 3px solid #f0a500; }

/* ── Celda empleado ── */
.cell-empleado {
	display: flex;
	align-items: center;
	gap: 10px;
	min-width: 160px;
}

.empleado-avatar {
	width: 32px;
	height: 32px;
	border-radius: 50%;
	object-fit: cover;
	flex-shrink: 0;
	background: var(--color-background-dark);
}

.empleado-nombre { font-weight: 500; white-space: nowrap; }

/* ── Badge tipo ── */
.badge-tipo {
	display: inline-block;
	padding: 3px 10px;
	border-radius: 20px;
	font-size: 0.78rem;
	font-weight: 700;
	white-space: nowrap;
}

/* ── Celda periodo ── */
.cell-periodo {
	display: flex;
	align-items: center;
	gap: 6px;
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
}

.periodo-sep { color: var(--color-text-maxcontrast); font-size: 0.8rem; }

/* ── Utilidades ── */
.cell-center { text-align: center; }
.col-dias, .col-prima, col-aprobacion { min-width: 70px; width: 70px; }

th.col-dias, td.col-dias { text-align: right; padding-right: 24px; }

/* ── Badge prima ── */
.badge-prima {
	background: #d1fae5;
	color: #065f46;
	padding: 2px 8px;
	border-radius: 20px;
	font-size: 0.75rem;
	font-weight: 700;
}

.badge-prima-no {
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	padding: 2px 8px;
	border-radius: 20px;
	font-size: 0.75rem;
}

/* ── Chips de estado ── */
.chip {
	display: inline-block;
	padding: 3px 10px;
	border-radius: 20px;
	font-size: 0.75rem;
	font-weight: 700;
	white-space: nowrap;
}

.chip-cancelado  { background: #fee2e2; color: #b91c1c; }
.chip-completado { background: #d1fae5; color: #065f46; }
.chip-encurso    { background: #dbeafe; color: #1d4ed8; }
.chip-pendiente  { background: #fef3c7; color: #b45309; }

/* ── Chips de aprobación ── */
.cell-aprobacion {
	display: flex;
	flex-direction: column;
	gap: 4px;
	align-items: flex-start;
	justify-content: center;
}

.chip-mini {
	display: inline-block;
	padding: 2px 6px;
	border-radius: 12px;
	font-size: 0.68rem;
	font-weight: 600;
	white-space: nowrap;
}

.chip-a-aprobado  { background: #d1fae5; color: #065f46; }
.chip-a-rechazado { background: #fee2e2; color: #b91c1c; }
.chip-a-cancelado { background: #fce7f3; color: #9d174d; }
.chip-a-pendiente { background: var(--color-background-dark); color: var(--color-text-maxcontrast); }

/* ── Celda fecha ── */
.cell-fecha {
	font-size: 0.78rem;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
}

/* ── Responsive ── */
@media (max-width: 1024px) {
	.reporte-tabla { font-size: 0.78rem; }
	.reporte-tabla th,
	.reporte-tabla td { padding: 6px 8px; }
	.cell-empleado { min-width: 120px; }
	.empleado-avatar { width: 24px; height: 24px; }
}

@media (max-width: 768px) {
	.reporte-tabla { font-size: 0.72rem; }
	.reporte-tabla th,
	.reporte-tabla td { padding: 5px 6px; }
	.reporte-tabla th:nth-child(5),
	.reporte-tabla td:nth-child(5),
	.reporte-tabla th:nth-child(8),
	.reporte-tabla td:nth-child(8) { display: none; }
	.reporte-filtros { padding: 10px 12px; gap: 10px; }
	.reporte-body { padding: 12px; }
}

@media (max-width: 540px) {
	.reporte-tabla th:nth-child(3),
	.reporte-tabla td:nth-child(3),
	.reporte-tabla th:nth-child(4),
	.reporte-tabla td:nth-child(4),
	.reporte-tabla th:nth-child(7),
	.reporte-tabla td:nth-child(7) { display: none; }
	.cell-empleado { min-width: 90px; }
	.empleado-nombre { max-width: 80px; overflow: hidden; text-overflow: ellipsis; }
}

.periodo-vac-modal {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 20px;
	min-width: 420px;
}

.periodo-vac-vacio {
	height: auto;
	padding: 32px 0;
}

.periodo-vac-resumen {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
	gap: 12px;
}

/* ── Vista resumen inline ── */
.resumen-vista {
	display: flex;
	flex-direction: column;
	gap: 20px;
	width: 100%;
}

.resumen-selector {
	display: flex;
	align-items: center;
	max-width: 480px;
	gap: 10px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);
	padding: 4px 14px;
	background: var(--color-main-background);
}

.resumen-selector-icon { color: var(--color-text-maxcontrast); flex-shrink: 0; }

.resumen-selector-input {
	border: none !important;
	height: 40px;
	flex: 1;
	padding: 0 !important;
	background: transparent;
}

.resumen-lista {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.resumen-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 12px 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.resumen-item-info {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.resumen-item-periodo {
	font-size: 0.8rem;
	color: var(--color-text-maxcontrast);
	font-variant-numeric: tabular-nums;
}

.resumen-card {
	display: flex;
	flex-direction: column;
	gap: 4px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius);
	padding: 10px 14px;
}

.resumen-label {
	font-size: 0.7rem;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.resumen-valor {
	font-size: 1.3rem;
	font-weight: 700;
	color: var(--color-main-text);
}

.periodo-vac-tabla {
	table-layout: auto;
}
</style>
