<template>
	<div class="card">
		<div class="card-header">
			<h3 class="card-title">
				{{ t('empleados', 'Tipo de cambio') }}
			</h3>

			<NcButton type="secondary" :disabled="!monedas.length" @click="abrirModalConsulta">
				<template #icon>
					<CloudSearchOutlineIcon :size="18" />
				</template>
				{{ t('empleados', 'Consultar fechas') }}
			</NcButton>
		</div>

		<!-- Filtro siempre visible -->
		<div class="filtro-bar">
			<div class="filtro-campo">
				<label>{{ t('empleados', 'Moneda') }}</label>
				<NcSelect
					v-model="filtro.idMoneda"
					:options="monedas"
					label="tipoMoneda"
					:reduce="m => m.id"
					:clearable="false"
					:disabled="!monedas.length" />
			</div>
			<div class="filtro-campo">
				<label>{{ t('empleados', 'Desde') }}</label>
				<input v-model="filtro.desde" type="date" class="input-fecha">
			</div>
			<div class="filtro-campo">
				<label>{{ t('empleados', 'Hasta') }}</label>
				<input v-model="filtro.hasta" type="date" class="input-fecha">
			</div>
		</div>

		<div class="scroll-container">
			<div v-if="cargando" class="center-screen small">
				<NcLoadingIcon :size="32" appearance="dark" :name="t('empleados', 'Loading...')" />
			</div>

			<p v-else-if="registros.length === 0" class="empty">
				<CashSyncIcon :size="40" fill-color="#c4c4c4" />
				<span>{{ t('empleados', 'No hay registros en ese rango de fechas') }}</span>
				<NcButton type="tertiary" @click="abrirModalConsulta">
					{{ t('empleados', 'Consultar en Banxico') }}
				</NcButton>
			</p>

			<table v-else class="tabla-tc">
				<thead>
					<tr>
						<th>{{ t('empleados', 'Fecha') }}</th>
						<th class="col-valor">
							{{ t('empleados', 'Valor (MXN)') }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="reg in registrosOrdenados" :key="reg.id">
						<td>{{ formatFecha(reg.fecha) }}</td>
						<td class="col-valor">
							{{ formatValor(reg.valor) }}
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Modal: consultar rango manual contra Banxico -->
		<NcModal v-if="modalConsulta" size="small" @close="cerrarModalConsulta">
			<div class="modal-form">
				<div ref="focusTrap" tabindex="-1" class="focus-trap-inicial" />

				<h3 class="modal-titulo">
					{{ t('empleados', 'Consultar rango en Banxico') }}
				</h3>
				<p class="modal-subtitulo">
					{{ t('empleados', 'Úsalo para traer fechas anteriores que aún no estén guardadas.') }}
				</p>

				<div class="filtro-campo">
					<label>{{ t('empleados', 'Moneda') }}</label>
					<NcSelect
						v-model="formConsulta.idMoneda"
						:options="monedas"
						label="tipoMoneda"
						:reduce="m => m.id"
						:clearable="false" />
				</div>

				<div class="filtro-fechas">
					<div class="filtro-campo">
						<label>{{ t('empleados', 'Desde') }}</label>
						<input v-model="formConsulta.fechaInicio" type="date" class="input-fecha">
					</div>
					<div class="filtro-campo">
						<label>{{ t('empleados', 'Hasta') }}</label>
						<input v-model="formConsulta.fechaFin" type="date" class="input-fecha">
					</div>
				</div>

				<p v-if="errorConsulta" class="error-form">
					{{ errorConsulta }}
				</p>
				<p v-if="mensajeConsulta" class="mensaje-ok">
					{{ mensajeConsulta }}
				</p>

				<div class="modal-acciones">
					<NcButton type="tertiary" @click="cerrarModalConsulta">
						{{ t('empleados', 'Cerrar') }}
					</NcButton>
					<NcButton type="primary" :disabled="consultando" @click="consultar">
						{{ consultando ? t('empleados', 'Consultando...') : t('empleados', 'Consultar') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import { NcLoadingIcon, NcModal, NcButton, NcSelect } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import CloudSearchOutlineIcon from 'vue-material-design-icons/CloudSearchOutline.vue'
import CashSyncIcon from 'vue-material-design-icons/CashSync.vue'

/**
 * yyyy-mm-dd de una fecha, en hora local (evita corrimientos de un día por UTC).
 */
function toISODate(date) {
	const y = date.getFullYear()
	const m = String(date.getMonth() + 1).padStart(2, '0')
	const d = String(date.getDate()).padStart(2, '0')
	return `${y}-${m}-${d}`
}

function rangoUltimaSemana() {
	const hoy = new Date()
	const hace7 = new Date()
	hace7.setDate(hoy.getDate() - 6)
	return { desde: toISODate(hace7), hasta: toISODate(hoy) }
}

export default {
	name: 'TipoCambioSettings',
	components: {
		NcLoadingIcon,
		NcModal,
		NcButton,
		NcSelect,
		CloudSearchOutlineIcon,
		CashSyncIcon,
	},
	props: {
		monedas: {
			type: Array,
			default: () => [],
		},
	},
	data() {
		const { desde, hasta } = rangoUltimaSemana()
		return {
			filtro: {
				idMoneda: null,
				desde,
				hasta,
			},
			registros: [],
			cargando: false,

			modalConsulta: false,
			formConsulta: { idMoneda: null, fechaInicio: desde, fechaFin: hasta },
			consultando: false,
			errorConsulta: '',
			mensajeConsulta: '',
		}
	},
	computed: {
		registrosOrdenados() {
			return [...this.registros].sort((a, b) => (a.fecha < b.fecha ? 1 : -1))
		},
	},
	watch: {
		monedas: {
			immediate: true,
			handler(nuevas) {
				if (nuevas.length && this.filtro.idMoneda === null) {
					this.filtro.idMoneda = nuevas[0].id
				}
			},
		},
		'filtro.idMoneda'() {
			this.fetchTipoCambio()
		},
		'filtro.desde'() {
			this.fetchTipoCambio()
		},
		'filtro.hasta'() {
			this.fetchTipoCambio()
		},
	},
	methods: {
		t,
		formatFecha(fecha) {
			const [y, m, d] = fecha.split('-')
			return `${d}/${m}/${y}`
		},
		formatValor(valor) {
			return Number(valor).toLocaleString('es-MX', { minimumFractionDigits: 4, maximumFractionDigits: 4 })
		},
		async fetchTipoCambio() {
			if (!this.filtro.idMoneda || !this.filtro.desde || !this.filtro.hasta) return

			this.cargando = true
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetTipoCambio'), {
					params: {
						idMoneda: this.filtro.idMoneda,
						fechaInicio: this.filtro.desde,
						fechaFin: this.filtro.hasta,
					},
				})
				this.registros = response?.data ?? []
			} catch (err) {
				showError(t('empleados', 'Error al cargar el tipo de cambio'))
			} finally {
				this.cargando = false
			}
		},
		abrirModalConsulta() {
			this.formConsulta = {
				idMoneda: this.filtro.idMoneda,
				fechaInicio: this.filtro.desde,
				fechaFin: this.filtro.hasta,
			}
			this.errorConsulta = ''
			this.mensajeConsulta = ''
			this.modalConsulta = true
		},
		cerrarModalConsulta() {
			this.modalConsulta = false
		},
		async consultar() {
			this.errorConsulta = ''
			this.mensajeConsulta = ''

			if (!this.formConsulta.idMoneda || !this.formConsulta.fechaInicio || !this.formConsulta.fechaFin) {
				this.errorConsulta = t('empleados', 'Selecciona moneda y un rango de fechas válido')
				return
			}

			this.consultando = true
			try {
				const { data } = await axios.post(generateUrl('/apps/empleados/SincronizarTipoCambio'), {
					idMoneda: this.formConsulta.idMoneda,
					fechaInicio: this.formConsulta.fechaInicio,
					fechaFin: this.formConsulta.fechaFin,
				})

				this.mensajeConsulta = t('empleados', '{n} registros sincronizados desde Banxico.', { n: data.insertados })

				// Reflejamos el rango consultado en el filtro principal para ver el resultado en la tabla
				this.filtro = {
					idMoneda: this.formConsulta.idMoneda,
					desde: this.formConsulta.fechaInicio,
					hasta: this.formConsulta.fechaFin,
				}
				await this.fetchTipoCambio()
			} catch (err) {
				this.errorConsulta = err?.response?.data?.error || t('empleados', 'Error al consultar Banxico')
			} finally {
				this.consultando = false
			}
		},
	},
}
</script>

<style scoped>
.card {
	max-width: 700px;
	margin: 24px auto 0;
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-main-background);
	overflow: hidden;
}

.card-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 14px 16px;
	border-bottom: 1px solid var(--color-border);
}

.card-title {
	margin: 0;
	font-size: 1rem;
	font-weight: 700;
	color: var(--color-main-text);
}

/* ---------- Filtro inline ---------- */
.filtro-bar {
	display: flex;
	gap: 16px;
	padding: 14px 16px;
	border-bottom: 1px solid var(--color-border);
	background: var(--color-background-hover);
	flex-wrap: wrap;
}

.filtro-campo {
	display: flex;
	flex-direction: column;
	gap: 4px;
	min-width: 140px;
}

.filtro-campo label {
	font-size: 0.78rem;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.filtro-fechas {
	display: flex;
	gap: 16px;
}

.input-fecha {
	height: 38px;
	border-radius: var(--border-radius, 6px);
	border: 1px solid var(--color-border-maxcontrast);
	background: var(--color-main-background);
	color: var(--color-main-text);
	padding: 0 10px;
	font-size: 0.9rem;
}

.input-fecha:focus {
	border-color: var(--color-primary-element);
	outline: none;
}

/* ---------- Tabla ---------- */
.scroll-container {
	max-height: 420px;
	overflow-y: auto;
	padding: 6px 10px;
}

.tabla-tc {
	width: 100%;
	border-collapse: collapse;
}

.tabla-tc th {
	text-align: left;
	font-size: 0.78rem;
	text-transform: uppercase;
	letter-spacing: 0.03em;
	color: var(--color-text-maxcontrast);
	padding: 10px 12px;
	border-bottom: 1px solid var(--color-border);
	position: sticky;
	top: 0;
	background: var(--color-main-background);
}

.tabla-tc td {
	padding: 10px 12px;
	border-bottom: 1px solid var(--color-border);
	font-size: 0.92rem;
	color: var(--color-main-text);
}

.tabla-tc tr:last-child td {
	border-bottom: none;
}

.tabla-tc tr:hover td {
	background-color: var(--color-background-hover);
}

.col-valor {
	text-align: right;
	font-variant-numeric: tabular-nums;
	font-weight: 600;
}

.empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 10px;
	text-align: center;
	color: var(--color-text-maxcontrast);
	padding: 40px 20px;
	font-size: 0.95rem;
}

.center-screen.small {
	display: flex;
	justify-content: center;
	align-items: center;
	min-height: 120px;
}

/* ---------- Modal ---------- */
.modal-form {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 16px;
	min-width: 340px;
}

.modal-titulo {
	margin: 0;
	font-size: 1.1rem;
	font-weight: 700;
	color: var(--color-main-text);
}

.modal-subtitulo {
	margin: -8px 0 0;
	font-size: 0.85rem;
	color: var(--color-text-maxcontrast);
}

.error-form {
	color: var(--color-error);
	font-size: 0.85rem;
	margin: 0;
}

.mensaje-ok {
	color: var(--color-success);
	font-size: 0.85rem;
	margin: 0;
}

.focus-trap-inicial {
	position: absolute;
	width: 1px;
	height: 1px;
	overflow: hidden;
	outline: none;
}

.modal-acciones {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 8px;
}
</style>
