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

		<!-- Filtros de la vista general -->
		<div v-if="vistaActual === 'todos'" class="reporte-filtros">
			<div class="filtro-grupo">
				<label class="filtro-label">{{ t('empleados', 'Desde') }}</label>
				<input v-model="filtroDesde" type="date" class="filtro-input">
			</div>

			<div class="filtro-grupo">
				<label class="filtro-label">{{ t('empleados', 'Hasta') }}</label>
				<input v-model="filtroHasta" type="date" class="filtro-input">
			</div>

			<NcButton type="primary" :disabled="cargando" @click="cargarReporte">
				<template #icon>
					<Magnify :size="18" />
				</template>
				{{ cargando ? t('empleados', 'Cargando…') : t('empleados', 'Buscar') }}
			</NcButton>

			<div v-if="!cargando && registros.length > 0" class="filtros-btn-wrap">
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
			<div v-else-if="vistaActual === 'resumen'" class="resumen-vista">
				<div class="resumen-toolbar">
					<div class="resumen-selector">
						<AccountSearch :size="20" class="resumen-selector-icon" />

						<img
							v-if="empleadoResumen"
							class="resumen-empleado-avatar"
							:src="avatarUrl(empleadoResumenUid, 40)"
							:alt="empleadoResumen"
							@error="onAvatarError($event, empleadoResumen, 40)">

						<select v-model="empleadoResumen" class="resumen-selector-input">
							<option value="" disabled>
								{{ t('empleados', 'Selecciona un empleado') }}
							</option>
							<option v-for="emp in opcionesEmpleados" :key="emp" :value="emp">
								{{ emp }}
							</option>
						</select>

						<button
							v-if="empleadoResumen"
							type="button"
							class="btn-prima-vacacional"
							@click="abrirInformePrima">
							{{ t('empleados', 'Reporte Prima Vacacional') }}
						</button>
						<button
							v-if="empleadoResumen && empleadoIdPorNombre[empleadoResumen]"
							type="button"
							class="btn-prima-vacacional btn-excel-periodos"
							@click="descargarExcelPeriodos">
							<FileExcelOutline :size="16" />
							{{ t('empleados', 'Descargar Excel') }}
						</button>
					</div>

					<div class="resumen-periodo-actions">
						<div class="filtro-grupo resumen-periodo-grupo">
							<label class="filtro-label">{{ t('empleados', 'Periodo') }}</label>
							<select
								v-model.number="periodoSeleccionado"
								class="filtro-input filtro-select"
								:disabled="!empleadoResumen || cargandoPeriodos">
								<option v-if="!empleadoResumen" :value="null">
									{{ t('empleados', 'Selecciona un empleado') }}
								</option>
								<option
									v-for="p in periodosEmpleado"
									:key="p.numero_aniversario"
									:value="p.numero_aniversario">
									{{ t('empleados', 'Aniversario') }} {{ p.numero_aniversario }} ({{ formatFecha(p.periodo_inicio) }} → {{ formatFecha(p.periodo_fin) }})
								</option>
							</select>
						</div>

						<NcButton
							type="primary"
							:disabled="cargando || !empleadoResumen || periodoSeleccionado === null"
							@click="cargarReporte">
							<template #icon>
								<Magnify :size="18" />
							</template>
							{{ cargando ? t('empleados', 'Cargando…') : t('empleados', 'Buscar') }}
						</NcButton>
					</div>
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
						<div class="resumen-card" :class="{ 'resumen-card--prima-si': resumenEmpleadoStats.primaSolicitada }">
							<span class="resumen-label">{{ t('empleados', 'Prima vacacional') }}</span>
							<span class="resumen-valor resumen-valor-prima">
								<template v-if="resumenEmpleadoStats.primaSolicitada">
									{{ t('empleados', 'Solicitado en: {fecha}', { fecha: formatFecha(resumenEmpleadoStats.primaFecha) }) }}
								</template>
								<template v-else>
									{{ t('empleados', 'No solicitado aún.') }}
								</template>
							</span>
						</div>
						<div class="resumen-card">
							<span class="resumen-label">{{ t('empleados', 'Registros') }}</span>
							<span class="resumen-valor">{{ resumenEmpleadoStats.total }}</span>
						</div>
					</div>

					<div v-if="registrosResumenEmpleado.length === 0" class="reporte-estado periodo-vac-vacio">
						<span class="reporte-estado-icon">🔍</span>
						<p>{{ t('empleados', 'Sin registros para este empleado en el año seleccionado.') }}</p>
					</div>

					<div v-else class="reporte-tabla-wrap">
						<table class="reporte-tabla reporte-tabla--resumen">
							<thead>
								<tr>
									<th class="col-periodo-resumen">
										{{ t('empleados', 'Periodo') }}
									</th>
									<th class="col-dias cell-center">
										{{ t('empleados', 'Días') }}
									</th>
									<th class="col-prima">
										{{ t('empleados', 'Prima vac.') }}
									</th>
									<th class="col-estado-resumen">
										{{ t('empleados', 'Estado') }}
									</th>
									<th class="col-aprobacion-resumen">
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
									<td class="col-periodo-resumen">
										<span
											:class="{ 'fecha-tardia': parseFloat(item.dias_de_acumulado) > 0 || item.es_tardia, 'fecha-temprana': item.es_temprana }"
											:title="item.es_temprana ? t('empleados', 'Vacación anticipada — descuenta del periodo siguiente') : ((parseFloat(item.dias_de_acumulado) > 0 || item.es_tardia) ? t('empleados', 'Fuera del periodo normal') : '')">
											{{ formatFecha(item.fecha_de) }}
										</span>
										<span class="periodo-sep">→</span>
										<span
											:class="{ 'fecha-tardia': parseFloat(item.dias_de_acumulado) > 0 || item.es_tardia, 'fecha-temprana': item.es_temprana }"
											:title="item.es_temprana ? t('empleados', 'Vacación anticipada — descuenta del periodo siguiente') : ((parseFloat(item.dias_de_acumulado) > 0 || item.es_tardia) ? t('empleados', 'Fuera del periodo normal') : '')">
											{{ formatFecha(item.fecha_hasta) }}
										</span>
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
									<td class="col-aprobacion-resumen">
										<div class="aprobacion-chips">
											<span
												class="chip-mini"
												:class="chipAprobacion(item.a_socio).clase"
												:title="t('empleados', 'Socio')">
												{{ t('empleados', 'S:') }}
												{{ chipAprobacion(item.a_socio).texto }}
											</span>

											<span
												class="chip-mini"
												:class="chipAprobacion(item.a_gerente).clase"
												:title="t('empleados', 'Gerente')">
												{{ t('empleados', 'G:') }}
												{{ chipAprobacion(item.a_gerente).texto }}
											</span>

											<span
												class="chip-mini"
												:class="chipAprobacion(item.a_capital_humano).clase"
												:title="t('empleados', 'Capital Humano')">
												{{ t('empleados', 'RH:') }}
												{{ chipAprobacion(item.a_capital_humano).texto }}
											</span>
										</div>
									</td>
									<td class="cell-fecha col-solicitud">
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
									:src="avatarUrl(getEmpleadoUid(item), 36)"
									:alt="item.nombre_empleado"
									@error="onAvatarError($event, item.nombre_empleado, 36)">
								<span class="empleado-nombre">{{ item.nombre_empleado }}</span>
							</td>
							<td>
								<span class="badge-tipo" :style="colorTipo(item.tipo_ausencia)">
									{{ item.tipo_ausencia }}
								</span>
							</td>
							<td>
								<span
									:class="{ 'fecha-tardia': parseFloat(item.dias_de_acumulado) > 0 || item.es_tardia, 'fecha-temprana': item.es_temprana }"
									:title="item.es_temprana ? t('empleados', 'Vacación anticipada — descuenta del periodo siguiente') : ((parseFloat(item.dias_de_acumulado) > 0 || item.es_tardia) ? t('empleados', 'Fuera del periodo normal') : '')">
									{{ formatFecha(item.fecha_de) }}
								</span>
								<span class="periodo-sep">→</span>
								<span
									:class="{ 'fecha-tardia': parseFloat(item.dias_de_acumulado) > 0 || item.es_tardia, 'fecha-temprana': item.es_temprana }"
									:title="item.es_temprana ? t('empleados', 'Vacación anticipada — descuenta del periodo siguiente') : ((parseFloat(item.dias_de_acumulado) > 0 || item.es_tardia) ? t('empleados', 'Fuera del periodo normal') : '')">
									{{ formatFecha(item.fecha_hasta) }}
								</span>
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
		<NcModal
			v-if="mostrarInformePrima"
			size="large"
			class="informe-prima-modal"
			:name="t('empleados', 'Reporte Prima Vacacional')"
			@close="mostrarInformePrima = false">
			<InformePrimaVacacional
				:id-empleado="empleadoIdPorNombre[empleadoResumen]"
				:nombre-empleado="empleadoResumen"
				:historial-completo="historialCompleto"
				@close="mostrarInformePrima = false" />
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
import InformePrimaVacacional from './InformePrimaVacacional.vue'
import FileExcelOutline from 'vue-material-design-icons/FileExcelOutline.vue'

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

	components: { NcButton, NcLoadingIcon, NcModal, Close, Magnify, FilterVariant, FilterOff, AccountSearch, FileExcelOutline, InformePrimaVacacional },

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
			empleadosCatalogo: [],
			empleadoIdPorNombre: {},
			mostrarInformePrima: false,
			historialCompleto: [],
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

		empleadoResumenRegistro() {
			if (!this.empleadoResumen) return null
			return this.registros.find(item => item.nombre_empleado === this.empleadoResumen)
				|| this.historialCompleto.find(item => item.nombre_empleado === this.empleadoResumen)
				|| null
		},

		empleadoResumenUid() {
			return this.getEmpleadoUid(this.empleadoResumenRegistro) || this.empleadoResumen
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

		// Primas del empleado en TODO su historial (no solo en el periodo/aniversario
		// seleccionado). Es necesario porque una prima queda anclada en la BD al
		// aniversario vigente cuando se solicitó, aunque por año calendario le
		// corresponda mostrarse en el periodo siguiente (ej. se pide en ene 2027
		// pero la BD la guarda bajo el aniversario que arrancó en jun 2026).
		primasHistoricasEmpleado() {
			if (!this.empleadoResumen) return []
			return this.historialCompleto.filter(item => {
				if (item.nombre_empleado !== this.empleadoResumen) return false
				if (this.chipEstado(item).texto === t('empleados', 'Cancelada')) return false
				if (parseInt(item.prima_vacacional) !== 1) return false
				return true
			})
		},

		resumenEmpleadoStats() {
			const registros = this.registrosResumenEmpleado
			const dias = registros.reduce((acc, r) => acc + (parseInt(r.dias_solicitados) || 0), 0)
			const anioPeriodo = this.periodoInfo?.periodo_inicio ? this.periodoInfo.periodo_inicio.slice(0, 4) : null
			const registroPrima = anioPeriodo
				? (this.primasHistoricasEmpleado.find(r => (r.fecha_de || '').slice(0, 4) === anioPeriodo) || null)
				: (registros.find(r => parseInt(r.prima_vacacional) === 1) || null)
			return {
				total: registros.length,
				dias,
				primaSolicitada: !!registroPrima,
				primaFecha: registroPrima ? registroPrima.fecha_de : null,
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

		abrirInformePrima() {
			if (!this.empleadoResumen) return
			this.mostrarInformePrima = true
		},

		descargarExcelPeriodos() {
			const idEmpleado = this.empleadoIdPorNombre[this.empleadoResumen]
			if (!idEmpleado) return
			window.location.href = generateUrl('/apps/empleados/reporte-periodos-excel') + '?id_empleado=' + idEmpleado
		},

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
				let data
				if (this.vistaActual === 'resumen' && this.periodoInfo) {
					// Filtra por id_aniversario, no por rango de fechas
					const url = generateUrl('/apps/empleados/historial-reporte-aniversario')
					const params = {
						id_empleado: this.periodoInfo.id_empleado,
						numero_aniversario: this.periodoInfo.numero_aniversario,
					}
					;({ data } = await axios.get(url, { params }))
				} else {
					const url = generateUrl('/apps/empleados/historial-reporte')
					const params = { desde: this.filtroDesde, hasta: this.filtroHasta }
					;({ data } = await axios.get(url, { params }))
				}
				const mensaje = data?.ocs?.data?.message ?? data?.message ?? []
				this.registros = Array.isArray(mensaje) ? mensaje : []
			} catch (e) {
				console.error('Error cargando reporte:', e)
			} finally {
				this.cargando = false
				this.haCargadoAlMenos = true
				// Alimenta el catálogo de empleados con lo que vaya llegando.
				if (this.empleadosCatalogo.length === 0 && this.registros.length > 0) {
					this.empleadosCatalogo = [...new Set(this.registros.map(r => r.nombre_empleado).filter(Boolean))].sort()
				}
				this.registros.forEach(r => {
					if (r.nombre_empleado && r.id_empleado) {
						this.empleadoIdPorNombre[r.nombre_empleado] = r.id_empleado
					}
				})
			}
		},

		/**
		 * Carga, una sola vez, el listado completo de empleados con historial
		 */
		async cargarEmpleadosCatalogo() {
			try {
				const url = generateUrl('/apps/empleados/historial-reporte')
				const { data } = await axios.get(url, { params: { desde: '1970-01-01', hasta: '2999-12-31' } })
				const mensaje = data?.ocs?.data?.message ?? data?.message ?? []
				const todos = Array.isArray(mensaje) ? mensaje : []
				this.empleadosCatalogo = [...new Set(todos.map(r => r.nombre_empleado).filter(Boolean))].sort()
				this.historialCompleto = todos
				todos.forEach(r => {
					if (r.nombre_empleado && r.id_empleado) {
						this.empleadoIdPorNombre[r.nombre_empleado] = r.id_empleado
					}
				})
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

			const idEmpleado = this.empleadoIdPorNombre[this.empleadoResumen]
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

		getEmpleadoUid(item) {
			return item?.Id_user
				|| item?.id_user
				|| item?.uid
				|| item?.user
				|| item?.username
				|| item?.nombre_empleado
				|| ''
		},

		avatarUrl(uid, size = 36) {
			const safeUid = uid || 'unknown-user'
			return generateUrl('/avatar/{uid}/{size}', {
				uid: safeUid,
				size,
			})
		},

		onAvatarError(event, nombre, size = 36) {
			const iniciales = this.iniciales(nombre)
			const paleta = PALETA_TIPOS[hashStr(nombre || '') % PALETA_TIPOS.length]
			const center = size / 2
			const fontSize = Math.max(12, Math.round(size * 0.36))
			const textY = Math.round(center + fontSize * 0.35)
			const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}">
				<circle cx="${center}" cy="${center}" r="${center}" fill="${paleta.bg}"/>
				<text x="${center}" y="${textY}" text-anchor="middle" font-size="${fontSize}" font-weight="700" font-family="sans-serif" fill="${paleta.color}">${iniciales}</text>
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
			const g = parseInt(item.a_gerente)
			const s = parseInt(item.a_socio)
			if (g === 3 || s === 3 || g === 2 || s === 2) return 'row-cancelado'
			const hoy = new Date(); hoy.setHours(0, 0, 0, 0)
			const hasta = this.parseFecha(item.fecha_hasta)
			return hasta < hoy ? 'row-pasado' : 'row-futuro'
		},

		chipEstado(item) {
			const g = parseInt(item.a_gerente)
			const s = parseInt(item.a_socio)
			if (g === 3 || s === 3 || g === 2 || s === 2) {
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
/* ========================================
 * CONTENEDOR GENERAL
 * ======================================== */

.reporte-contenido {
	--reporte-radius: 12px;
	--reporte-radius-small: 8px;
	--reporte-shadow:
		0 8px 24px rgba(0, 0, 0, 0.08),
		0 2px 6px rgba(0, 0, 0, 0.04);

	display: flex;
	flex-direction: column;

	box-sizing: border-box;
	width: 100%;
	height: calc(100dvh - 100px);
	max-height: calc(100dvh - 100px);
	min-height: 520px;

	overflow: hidden;

	color: var(--color-main-text);
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--reporte-radius);
	box-shadow: var(--reporte-shadow);
}

.reporte-contenido,
.reporte-contenido * {
	box-sizing: border-box;
}

/* ========================================
 * ENCABEZADO
 * ======================================== */

.reporte-header {
	display: flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: space-between;

	min-height: 48px;
	padding: 8px 16px;
	gap: 10px;

	background: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);
}

.reporte-header-left {
	display: flex;
	align-items: center;

	min-width: 0;
	gap: 12px;
}

.reporte-titulo {
	margin: 0;

	overflow: hidden;

	color: var(--color-main-text);
	font-size: 1.22rem;
	font-weight: 700;
	line-height: 1.25;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.reporte-count {
	display: inline-flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;

	min-height: 24px;
	padding: 3px 9px;

	color: var(--color-text-maxcontrast);
	font-size: 0.75rem;
	font-weight: 600;

	background: var(--color-background-dark);
	border: 1px solid var(--color-border);
	border-radius: 999px;
}

/* ========================================
 * PESTAÑAS
 * ======================================== */

.vista-switch {
	display: flex;
	flex: 0 0 auto;
	align-items: center;

	padding: 10px 14px 0;
	gap: 6px;

	background: var(--color-main-background);
}

.vista-switch-btn {
	min-height: 30px;
	padding: 5px 12px;

	color: var(--color-text-maxcontrast);
	font-family: inherit;
	font-size: 0.82rem;
	font-weight: 600;

	background: var(--color-main-background);
	border: 1px solid var(--color-border-dark);
	border-radius: 999px;

	cursor: pointer;

	transition:
		background-color 0.16s ease,
		border-color 0.16s ease,
		color 0.16s ease,
		transform 0.16s ease;
}

.vista-switch-btn--activo {
	color: var(--color-main-background);
	background: var(--color-main-text);
	border-color: var(--color-main-text);
}

/* ========================================
 * BARRA DE FILTROS PRINCIPAL
 * ======================================== */

.reporte-filtros {
	display: flex;
	flex: 0 0 auto;
	align-items: flex-end;
	flex-wrap: wrap;

	padding: 10px 14px;
	gap: 10px;

	background: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);
}

.filtro-grupo {
	display: flex;
	flex-direction: column;

	min-width: 150px;
	gap: 5px;
}

.filtro-label {
	color: var(--color-text-maxcontrast);
	font-size: 0.68rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.045em;
}

.filtro-input {
	width: 100%;
	height: 38px;
	padding: 6px 10px;

	color: var(--color-main-text);
	font-family: inherit;
	font-size: 0.84rem;

	background: var(--color-main-background);
	border: 1px solid var(--color-border-dark);
	border-radius: var(--reporte-radius-small);

	outline: none;

	transition:
		border-color 0.16s ease,
		box-shadow 0.16s ease,
		background-color 0.16s ease;
}

.filtro-select {
	min-width: 190px;
	cursor: pointer;
}

.filtros-btn-wrap {
	display: flex;
	align-items: center;

	margin-left: auto;
	gap: 5px;
}

.filtros-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;

	min-width: 18px;
	height: 18px;
	margin-left: 5px;
	padding: 0 5px;

	color: var(--color-primary);
	font-size: 0.68rem;
	font-weight: 700;

	background: white;
	border-radius: 999px;
}

/* ========================================
 * CUERPO Y SCROLL
 * ======================================== */

.reporte-body {
	position: relative;

	display: flex;
	flex: 1 1 auto;
	flex-direction: column;

	min-height: 0;
	padding: 10px 12px 14px;

	overflow-x: hidden;
	overflow-y: auto;

	background: var(--color-main-background);

	scrollbar-width: thin;
	scrollbar-color: var(--color-border-dark) transparent;
}

.reporte-body::-webkit-scrollbar {
	width: 7px;
	height: 7px;
}

.reporte-body::-webkit-scrollbar-track {
	background: transparent;
}

.reporte-body::-webkit-scrollbar-thumb {
	background: var(--color-border-dark);
	border-radius: 999px;
}

/* ========================================
 * ESTADOS VACÍOS Y CARGANDO
 * ======================================== */

.reporte-estado {
	display: flex;
	flex: 1;
	flex-direction: column;
	align-items: center;
	justify-content: center;

	min-height: 260px;
	padding: 32px;
	gap: 12px;

	color: var(--color-text-maxcontrast);
	font-size: 0.88rem;
	text-align: center;

	background: var(--color-background-hover);
	border: 1px dashed var(--color-border-dark);
	border-radius: var(--reporte-radius);
}

.reporte-estado p {
	max-width: 480px;
	margin: 0;
}

.reporte-estado-icon {
	font-size: 2.2rem;
	line-height: 1;
}

/* ========================================
 * CONTENEDOR DE TABLAS
 * ======================================== */

.reporte-tabla-wrap {
	width: 100%;

	overflow-x: auto;
	overflow-y: visible;

	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--reporte-radius);

	box-shadow:
		0 3px 10px rgba(0, 0, 0, 0.04);

	scrollbar-width: thin;
	scrollbar-color: var(--color-border-dark) transparent;
}

.reporte-tabla-wrap::-webkit-scrollbar {
	width: 7px;
	height: 7px;
}

.reporte-tabla-wrap::-webkit-scrollbar-track {
	background: transparent;
}

.reporte-tabla-wrap::-webkit-scrollbar-thumb {
	background: var(--color-border-dark);
	border-radius: 999px;
}

/* ========================================
 * TABLA PRINCIPAL
 * ======================================== */

.reporte-tabla {
	width: 100%;
	min-width: 1050px;

	color: var(--color-main-text);
	font-size: 0.82rem;

	background: var(--color-main-background);
	border-collapse: separate;
	border-spacing: 0;
	table-layout: auto;
}

.reporte-tabla thead {
	background: var(--color-main-background);
}

.reporte-tabla thead th {
	position: sticky;
	top: 0;
	z-index: 5;

	padding: 8px 10px;

	color: var(--color-text-maxcontrast);
	font-size: 0.68rem;
	font-weight: 700;
	text-align: left;
	text-transform: uppercase;
	letter-spacing: 0.055em;
	white-space: nowrap;

	background: var(--color-main-background);
	border-bottom: 2px solid var(--color-border-dark);

	box-shadow: 0 2px 0 var(--color-border);
}

.reporte-tabla tbody tr {
	background: var(--color-main-background);

	transition:
		background-color 0.14s ease,
		box-shadow 0.14s ease;
}

.reporte-tabla td {
	padding: 7px 10px;

	vertical-align: middle;

	border-bottom: 1px solid var(--color-border);
}
/* ========================================
 * FILAS POR ESTADO
 * ======================================== */

.row-cancelado td:first-child {
	border-left: 3px solid var(--color-error);
}

.row-pasado td:first-child {
	border-left: 3px solid var(--color-success);
}

.row-futuro td:first-child {
	border-left: 3px solid #f0a500;
}

.reporte-tabla tbody tr:last-child td {
	border-bottom: none;
}

/* ========================================
 * EMPLEADO
 * ======================================== */

.cell-empleado {
	display: flex;
	align-items: center;

	min-width: 190px;
	gap: 9px;
}

.empleado-avatar {
	flex: 0 0 auto;

	width: 34px;
	height: 34px;

	object-fit: cover;

	background: var(--color-background-dark);
	border: 1px solid var(--color-border);
	border-radius: 50%;
}

.empleado-nombre {
	max-width: 210px;
	overflow: hidden;

	font-weight: 600;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* ========================================
 * TIPOS Y PERIODOS
 * ======================================== */

.badge-tipo {
	display: inline-flex;
	align-items: center;

	max-width: 190px;
	padding: 4px 9px;

	overflow: hidden;

	font-size: 0.73rem;
	font-weight: 700;
	text-overflow: ellipsis;
	white-space: nowrap;

	border-radius: 999px;
}

.periodo-sep {
	margin: 0 4px;

	color: var(--color-text-maxcontrast);
	font-size: 0.75rem;
}

.fecha-tardia {
	color: #b60909;
	font-weight: 600;
}

.fecha-temprana {
	color: #f85a1c;
	font-weight: 600;
}

/* ========================================
 * COLUMNAS
 * ======================================== */

.cell-center {
	text-align: center;
}

.col-dias {
	width: 70px;
	min-width: 70px;
	text-align: center;
}

.col-prima {
	width: 95px;
	min-width: 95px;
	text-align: center;
}

.col-aprobacion {
	width: 180px;
	min-width: 180px;
}

.col-solicitud {
	width: 145px;
	min-width: 145px;
}

/* ========================================
 * PRIMA VACACIONAL
 * ======================================== */

.badge-prima,
.badge-prima-no {
	display: inline-flex;
	align-items: center;
	justify-content: center;

	min-width: 38px;
	padding: 3px 8px;

	font-size: 0.7rem;
	font-weight: 700;

	border-radius: 999px;
}

.badge-prima {
	color: #065f46;
	background: #d1fae5;
}

.badge-prima-no {
	color: var(--color-text-maxcontrast);
	background: var(--color-background-dark);
}

/* ========================================
 * ESTADOS
 * ======================================== */

.chip {
	display: inline-flex;
	align-items: center;

	padding: 4px 9px;

	font-size: 0.7rem;
	font-weight: 700;
	white-space: nowrap;

	border-radius: 999px;
}

.chip-cancelado {
	color: #b91c1c;
	background: #fee2e2;
}

.chip-completado {
	color: #065f46;
	background: #d1fae5;
}

.chip-encurso {
	color: #1d4ed8;
	background: #dbeafe;
}

.chip-pendiente {
	color: #b45309;
	background: #fef3c7;
}

/* ========================================
 * APROBACIONES
 * ======================================== */

 .aprobacion-chips {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 4px;
}

.chip-mini {
	display: inline-flex;
	align-items: center;

	padding: 3px 6px;

	font-size: 0.63rem;
	font-weight: 650;
	white-space: nowrap;

	border-radius: 999px;
}

.chip-a-aprobado {
	color: #065f46;
	background: #d1fae5;
}

.chip-a-rechazado {
	color: #b91c1c;
	background: #fee2e2;
}

.chip-a-cancelado {
	color: #9d174d;
	background: #fce7f3;
}

.chip-a-pendiente {
	color: var(--color-text-maxcontrast);
	background: var(--color-background-dark);
}

/* ========================================
 * FECHA DE SOLICITUD
 * ======================================== */

.cell-fecha {
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	font-variant-numeric: tabular-nums;
	white-space: nowrap;
}

/* ========================================
 * VISTA RESUMEN
 * ======================================== */

.resumen-vista {
	display: flex;
	flex-direction: column;

	width: 100%;
	min-height: 0;
	gap: 10px;
}

.resumen-toolbar {
	display: grid;
	grid-template-columns: minmax(340px, 1fr) auto;
	align-items: end;

	width: 100%;
	gap: 14px;
}

.resumen-selector {
	display: flex;
	align-items: center;

	min-width: 0;
	min-height: 44px;
	padding: 5px 8px;
	gap: 8px;

	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	border-radius: 9px;
}

.resumen-selector-icon {
	flex: 0 0 auto;
	color: var(--color-text-maxcontrast);
}

.resumen-empleado-avatar {
	flex: 0 0 auto;

	width: 40px;
	height: 40px;

	object-fit: cover;

	background: var(--color-background-dark);
	border: 1px solid var(--color-border-dark);
	border-radius: 50%;
}

.resumen-selector-input {
	flex: 1 1 auto;

	width: 100%;
	min-width: 120px;
	height: 38px;
	padding: 5px 8px;

	color: var(--color-main-text);
	font-family: inherit;
	font-size: 0.82rem;

	background: transparent;
	border: 0;
	border-radius: 7px;
	outline: none;
	cursor: pointer;
}

.resumen-periodo-actions {
	display: flex;
	align-items: end;

	gap: 8px;
}

.resumen-periodo-grupo {
	width: min(360px, 32vw);
	min-width: 260px;
}

.btn-prima-vacacional {
	flex: 0 0 auto;

	min-height: 36px;
	padding: 7px 13px;

	color: white;
	font-family: inherit;
	font-size: 0.74rem;
	font-weight: 700;
	white-space: nowrap;

	background: #000;
	border: 1px solid #000;
	border-radius: var(--reporte-radius-small);

	cursor: pointer;

	transition:
		background-color 0.14s ease,
		transform 0.14s ease;
}

.periodo-vac-vacio {
	flex: 0 0 auto;
	min-height: 220px;
	height: auto;
}

.periodo-vac-resumen {
	display: grid;
	grid-template-columns: repeat(5, minmax(130px, 1fr));

	width: 100%;
	gap: 10px;
}

.resumen-card {
	position: relative;

	display: flex;
	flex-direction: column;
	justify-content: center;

	min-height: 62px;
	padding: 9px 12px;
	gap: 4px;

	background: var(--color-background-dark);
	border: 1px solid var(--color-border);
	border-radius: var(--reporte-radius);

	transition:
		transform 0.16s ease,
		box-shadow 0.16s ease;
}

.resumen-label {
	color: var(--color-text-maxcontrast);
	font-size: 0.66rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.045em;
}

.resumen-valor {
	color: var(--color-main-text);
	font-size: 1.2rem;
	font-weight: 750;
	line-height: 1.15;
}

.resumen-valor-prima {
	font-size: 0.7rem;
	line-height: 1.3;
}

.resumen-card--prima-si {
	background: #d1fae5;
	border-color: rgba(6, 95, 70, 0.18);
}

.resumen-card--prima-si .resumen-valor-prima {
	color: #065f46;
}

/* ========================================
 * TABLA DEL RESUMEN
 * ======================================== */

.reporte-tabla--resumen {
	width: 100%;
	min-width: 850px;
	table-layout: fixed;
}

.reporte-tabla--resumen .col-periodo-resumen {
	width: 22%;
}

.reporte-tabla--resumen .col-dias {
	width: 7%;
	min-width: 60px;
}

.reporte-tabla--resumen .col-prima {
	width: 10%;
	min-width: 85px;
	text-align: center;
}

.reporte-tabla--resumen .col-estado-resumen {
	width: 12%;
}

.reporte-tabla--resumen .col-aprobacion-resumen {
	width: 31%;
}

.reporte-tabla--resumen .col-solicitud {
	width: 18%;
	text-align: right;
}

.reporte-tabla--resumen td.col-solicitud {
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	white-space: nowrap;
}

/* ========================================
 * MODAL DE FILTROS
 * ======================================== */

.filtros-modal {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));

	min-width: 320px;
	padding: 22px;
	gap: 16px;
}

.filtro-modal-grupo {
	display: flex;
	flex-direction: column;

	min-width: 0;
	gap: 6px;
}

.filtro-modal-grupo .filtro-select {
	width: 100%;
	min-width: 0;
}

.filtro-check-label {
	display: flex;
	align-items: center;

	min-height: 38px;
	gap: 8px;

	color: var(--color-main-text);
	font-size: 0.82rem;

	cursor: pointer;
}

.filtro-check {
	cursor: pointer;
}

.filtros-modal-acciones {
	display: flex;
	grid-column: 1 / -1;
	align-items: center;
	justify-content: flex-end;

	margin-top: 4px;
	padding-top: 14px;
	gap: 8px;

	border-top: 1px solid var(--color-border);
}

/* ========================================
 * MODAL DE PRIMA
 * ======================================== */

.informe-prima-modal :deep(.modal-container) {
	width: min(1300px, calc(100vw - 48px)) !important;
	max-width: min(1300px, calc(100vw - 48px)) !important;

	overflow-x: hidden !important;
}

.informe-prima-modal :deep(.modal-wrapper) {
	overflow-x: hidden !important;
}

.btn-excel-periodos {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background-color: #1F4E79;
    border-color: #1F4E79;
}
.btn-excel-periodos:hover { background-color: #2b6aa3; }

/* ========================================
 * RESPONSIVE
 * ======================================== */

@media screen and (max-width: 1200px) {
	.periodo-vac-resumen {
		grid-template-columns: repeat(3, minmax(130px, 1fr));
	}
}

@media screen and (max-width: 900px) {
	.reporte-contenido {
		height: calc(100dvh - 70px);
		max-height: calc(100dvh - 70px);
		min-height: 0;

		border-radius: 8px;
	}

	.reporte-header {
		padding-inline: 14px;
	}

	.vista-switch {
		padding-inline: 14px;
	}

	.reporte-filtros {
		padding: 12px 14px;
	}

	.reporte-body {
		padding: 14px;
	}

	.filtros-btn-wrap {
		margin-left: 0;
	}

	.resumen-toolbar {
		grid-template-columns: 1fr;
	}

	.resumen-periodo-actions {
		justify-content: flex-end;
	}

	.resumen-periodo-grupo {
		width: min(420px, 100%);
	}

	.periodo-vac-resumen {
		grid-template-columns: repeat(2, minmax(120px, 1fr));
	}
}

@media screen and (max-width: 600px) {
	.reporte-header {
		align-items: flex-start;
	}

	.reporte-header-left {
		flex-direction: column;
		align-items: flex-start;
		gap: 5px;
	}

	.reporte-titulo {
		font-size: 1.05rem;
	}

	.vista-switch {
		overflow-x: auto;
		padding-bottom: 4px;
	}

	.vista-switch-btn {
		flex: 0 0 auto;
	}

	.reporte-filtros {
		align-items: stretch;
	}

	.filtro-grupo {
		flex: 1 1 100%;
	}

	.filtro-select {
		min-width: 0;
	}

	.filtros-btn-wrap {
		width: 100%;
	}

	.resumen-toolbar {
		grid-template-columns: 1fr;
	}

	.resumen-selector {
		flex-wrap: wrap;
	}

	.resumen-selector-input {
		flex: 1 1 calc(100% - 76px);
		min-width: 160px;
	}

	.resumen-periodo-actions {
		align-items: stretch;
		flex-direction: column;
	}

	.resumen-periodo-grupo {
		width: 100%;
		min-width: 0;
	}

	.btn-prima-vacacional {
		width: 100%;
	}

	.periodo-vac-resumen {
		grid-template-columns: 1fr 1fr;
	}

	.filtros-modal {
		grid-template-columns: 1fr;
		min-width: 0;
		padding: 18px;
	}

	.filtros-modal-acciones {
		grid-column: 1;
	}

	.empleado-avatar {
		width: 28px;
		height: 28px;
	}
}

/* ========================================
 * ESTADOS INTERACTIVOS
 * ======================================== */

.vista-switch-btn:hover {
	color: var(--color-main-text);
	background: var(--color-background-hover);
	border-color: var(--color-main-text);
}

.vista-switch-btn--activo:hover {
	color: var(--color-main-background);
	background: var(--color-main-text);
}

.vista-switch-btn:active {
	transform: scale(0.98);
}

.filtro-input:hover {
	border-color: var(--color-main-text);
}

.filtro-input:focus {
	border-color: var(--color-primary);

	box-shadow:
		0 0 0 2px rgba(0, 130, 201, 0.14);
}

.reporte-tabla tbody tr:hover {
	background: var(--color-background-hover);
}

.resumen-card:hover {
	box-shadow: 0 5px 14px rgba(0, 0, 0, 0.07);
	transform: translateY(-1px);
}

.btn-prima-vacacional:hover {
	background: #3a3a3a;
}

.btn-prima-vacacional:active {
	background: #000;
	transform: scale(0.98);
}
</style>
