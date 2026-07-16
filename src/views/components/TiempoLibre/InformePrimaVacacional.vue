<template>
	<div class="informe-prima">
		<header class="informe-header">
			<span class="informe-eyebrow">{{ t('empleados', 'Vacation bonus ledger') }}</span>
			<h2 class="informe-title">
				{{ t('empleados', 'Vacation Bonus Report') }}
			</h2>
			<p v-if="nombreEmpleado" class="informe-sub">
				{{ nombreEmpleado }}
			</p>
		</header>

		<div v-if="loading" class="informe-loading">
			<NcLoadingIcon :size="32" />
			<span>{{ t('empleados', 'Loading anniversaries...') }}</span>
		</div>

		<div v-else-if="periodos.length === 0" class="informe-empty">
			<p>{{ t('empleados', 'No anniversary periods found for this employee.') }}</p>
		</div>

		<table v-else class="ledger">
			<thead>
				<tr>
					<th scope="col" class="col-aniversario">
						{{ t('empleados', 'Anniversary') }}
					</th>
					<th scope="col" class="col-fecha">
						{{ t('empleados', 'Requested on') }}
					</th>
					<th scope="col" class="col-accion">
						{{ t('empleados', 'Mark payment') }}
					</th>
					<th scope="col" class="col-estado">
						{{ t('empleados', 'Status') }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="periodo in periodos" :key="periodo.numero_aniversario" class="ledger-row">
					<td class="cell-aniversario">
						<span class="seal" :class="{ 'seal--pagado': periodo.pagado }">
							<CheckDecagram v-if="periodo.pagado" :size="16" />
							<span v-else>{{ periodo.numero_aniversario }}</span>
						</span>
						<div class="periodo-info">
							<strong>{{ t('empleados', 'Anniversary {n}', { n: periodo.numero_aniversario }) }}</strong>
							<small>{{ formatFecha(periodo.periodo_inicio) }} – {{ formatFecha(periodo.periodo_fin) }}</small>
						</div>
					</td>

					<td class="cell-fecha">
						<span v-if="periodo.fecha_solicitud">{{ formatFecha(periodo.fecha_solicitud) }}</span>
						<span v-else class="muted">{{ t('empleados', 'No request') }}</span>
					</td>

					<td class="cell-accion">
						<button
							v-if="!periodo.pagado"
							type="button"
							class="btn-prima-vacacional"
							@click="abrirConfirmacion(periodo)">
							{{ t('empleados', 'Mark payment') }}
						</button>
						<NcButton
							v-else
							type="tertiary"
							@click="abrirConfirmacion(periodo)">
							{{ t('empleados', 'Edit') }}
						</NcButton>
					</td>

					<td class="cell-estado">
						<span v-if="periodo.pagado" class="badge badge--pagado">
							{{ t('empleados', 'Paid on {fecha} for {dias} days', { fecha: formatFecha(periodo.fecha_pago), dias: periodo.dias_pagados }) }}
						</span>
						<span v-else class="badge badge--pendiente">
							<ClockOutline :size="14" />
							{{ t('empleados', 'Pending') }}
						</span>
					</td>
				</tr>
			</tbody>
		</table>

		<!-- Modal de confirmación / edición -->
		<NcModal
			v-if="confirmando"
			size="normal"
			:name="periodoSeleccionado && periodoSeleccionado.pagado ? t('empleados', 'Edit payment') : t('empleados', 'Confirm payment')"
			@close="cancelarConfirmacion">
			<div class="confirm-pago">
				<p class="confirm-lead">
					<template v-if="periodoSeleccionado.pagado">
						{{ t('empleados', 'You are editing the payment record for {n}.', { n: t('empleados', 'Anniversary {n}', { n: periodoSeleccionado.numero_aniversario }) }) }}
					</template>
					<template v-else>
						{{ t('empleados', 'You are about to mark the vacation bonus for {n} as paid.', { n: t('empleados', 'Anniversary {n}', { n: periodoSeleccionado.numero_aniversario }) }) }}
					</template>
				</p>

				<div class="confirm-field">
					<label>{{ t('empleados', 'Payment date') }}</label>
					<input v-model="fechaConfirmar" type="date" class="confirm-input confirm-input--date">
				</div>

				<div class="confirm-field">
					<label>{{ t('empleados', 'Days to mark as paid') }}</label>
					<input
						v-model.number="diasConfirmar"
						type="number"
						min="0"
						step="0.5"
						class="confirm-input confirm-input--num">
					<small class="confirm-hint">
						{{ t('empleados', 'Defaults to the period\'s entitled days ({dias}), not the accumulated balance.', { dias: periodoSeleccionado.dias_derecho }) }}
					</small>
				</div>

				<div class="confirm-actions">
					<NcButton type="tertiary" @click="cancelarConfirmacion">
						{{ t('empleados', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" @click="confirmarPago">
						{{ periodoSeleccionado.pagado ? t('empleados', 'Save changes') : t('empleados', 'Confirm payment') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import CheckDecagram from 'vue-material-design-icons/CheckDecagram.vue'
import ClockOutline from 'vue-material-design-icons/ClockOutline.vue'

import { NcButton, NcModal, NcLoadingIcon } from '@nextcloud/vue'

export default {
	name: 'InformePrimaVacacional',

	components: {
		NcButton,
		NcModal,
		NcLoadingIcon,
		CheckDecagram,
		ClockOutline,
	},

	props: {
		idEmpleado: { type: [Number, String], required: true },
		nombreEmpleado: { type: String, default: '' },
		historialCompleto: { type: Array, default: () => [] },
	},

	emits: ['pago-confirmado', 'close'],

	data() {
		return {
			loading: true,
			periodos: [],
			confirmando: false,
			periodoSeleccionado: null,
			fechaConfirmar: '',
			diasConfirmar: 0,
		}
	},

	computed: {
		primasHistoricasEmpleado() {
			return this.historialCompleto.filter(item => {
				if (item.nombre_empleado !== this.nombreEmpleado) return false
				const g = parseInt(item.a_gerente)
				const s = parseInt(item.a_socio)
				if (g === 3 || s === 3 || g === 2 || s === 2) return false // cancelada
				if (parseInt(item.prima_vacacional) !== 1) return false
				return true
			})
		},
	},

	mounted() {
		this.cargarInforme()
	},

	methods: {
		t,

		async cargarInforme() {
			this.loading = true
			try {
				const [resPeriodos, resPagos] = await Promise.all([
					axios.get(
						generateUrl('/apps/empleados/periodos-vacaciones'),
						{ params: { id_empleado: this.idEmpleado } },
					),
					axios.get(
						generateUrl('/apps/empleados/prima-vacacional-pagos'),
						{ params: { id_empleado: this.idEmpleado } },
					),
				])

				const base = resPeriodos?.data?.ocs?.data?.message || resPeriodos?.data?.message || []
				const pagos = resPagos?.data?.ocs?.data?.message || resPagos?.data?.message || []

				this.periodos = base
					.map(p => {
						const anioPeriodo = p.periodo_inicio ? p.periodo_inicio.slice(0, 4) : null
						const registroPrima = anioPeriodo
							? this.primasHistoricasEmpleado.find(r => (r.fecha_de || '').slice(0, 4) === anioPeriodo) || null
							: null
						const pago = pagos.find(pg => Number(pg.numero_aniversario) === Number(p.numero_aniversario)) || null

						return {
							...p,
							fecha_solicitud: registroPrima ? registroPrima.fecha_de : null,
							pagado: !!pago,
							dias_pagados: pago ? pago.dias_pagados : null,
							fecha_pago: pago ? pago.fecha_pago : null,
						}
					})
					.sort((a, b) => b.numero_aniversario - a.numero_aniversario)
			} catch (err) {
				showError(t('empleados', 'Error loading the vacation bonus report'))
			} finally {
				this.loading = false
			}
		},

		async buscarSolicitudPrima(numeroAniversario) {
			try {
				const res = await axios.get(
					generateUrl('/apps/empleados/historial-reporte-aniversario'),
					{
						params: {
							id_empleado: this.idEmpleado,
							numero_aniversario: numeroAniversario,
						},
					},
				)
				const rows = res?.data?.ocs?.data?.message || res?.data?.message || []
				return rows.find(r =>
					Number(r.prima_vacacional) === 1
					&& Number(r.a_gerente) !== 2 && Number(r.a_gerente) !== 3
					&& Number(r.a_socio) !== 2 && Number(r.a_socio) !== 3,
				) || null
			} catch (err) {
				return null
			}
		},

		abrirConfirmacion(periodo) {
			this.periodoSeleccionado = periodo

			if (periodo.pagado) {
				// Editando un pago ya registrado: precarga lo que se guardó.
				this.fechaConfirmar = periodo.fecha_pago
					? periodo.fecha_pago.substring(0, 10)
					: (periodo.fecha_solicitud ? periodo.fecha_solicitud.substring(0, 10) : '')
				this.diasConfirmar = periodo.dias_pagados ?? periodo.dias_derecho
			} else {
				// Primera confirmación: la fecha es la de PAGO (hoy por defecto),
				// no la fecha en la que se solicitó la prima.
				this.fechaConfirmar = new Date().toISOString().slice(0, 10)
				this.diasConfirmar = periodo.dias_derecho
			}

			this.confirmando = true
		},

		cancelarConfirmacion() {
			this.confirmando = false
			this.periodoSeleccionado = null
		},

		async confirmarPago() {
			const periodo = this.periodoSeleccionado
			try {
				await axios.post(generateUrl('/apps/empleados/prima-vacacional-pagos'), {
					id_empleado: this.idEmpleado,
					numero_aniversario: periodo.numero_aniversario,
					fecha_pago: this.fechaConfirmar,
					dias_pagados: this.diasConfirmar,
				})

				periodo.pagado = true
				periodo.dias_pagados = this.diasConfirmar
				periodo.fecha_pago = this.fechaConfirmar

				this.$emit('pago-confirmado', {
					id_empleado: this.idEmpleado,
					numero_aniversario: periodo.numero_aniversario,
					fecha: this.fechaConfirmar,
					dias_pagados: this.diasConfirmar,
				})

				this.confirmando = false
				this.periodoSeleccionado = null
			} catch (err) {
				showError(t('empleados', 'Error saving the vacation bonus payment'))
			}
		},

		formatFecha(fecha) {
			if (!fecha) return ''
			const [y, m, d] = fecha.slice(0, 10).split('-')
			return `${d}/${m}/${y}`
		},
	},
}
</script>

<style scoped>
.informe-prima {
	--prima-accent: #a9762f;
	--prima-accent-soft: rgba(169, 118, 47, 0.14);
	padding: 28px 32px 32px;
	max-height: 82vh;
	overflow-y: auto;
	overflow-x: hidden;
	width: min(1100px, calc(100vw - 48px));
	box-sizing: border-box;
}

/* ── Header ─────────────────────────────────── */
.informe-header {
	margin-bottom: 24px;
	padding-bottom: 18px;
	border-bottom: 1px solid var(--color-border);
}

.informe-eyebrow {
	display: block;
	font-size: 0.72rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.12em;
	color: var(--prima-accent);
	margin-bottom: 6px;
}

.informe-title {
	font-family: Georgia, 'Iowan Old Style', serif;
	font-size: 1.65rem;
	font-weight: 700;
	margin: 0;
	color: var(--color-main-text);
	letter-spacing: -0.01em;
}

.informe-sub {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.95rem;
}

.informe-loading,
.informe-empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 10px;
	padding: 48px 0;
	color: var(--color-text-maxcontrast);
}

/* ── Ledger table ───────────────────────────── */
.ledger {
	width: 100%;
	table-layout: fixed;
	border-collapse: collapse;
}

.col-aniversario { width: 34%; }
.col-fecha { width: 20%; }
.col-accion { width: 22%; }
.col-estado { width: 24%; }

.ledger thead th {
	text-align: left;
	font-size: 0.7rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	color: var(--color-text-maxcontrast);
	padding: 0 12px 10px;
	border-bottom: 2px solid var(--color-border);
	overflow-wrap: break-word;
}

.ledger-row td {
	padding: 14px 12px;
	border-bottom: 1px solid var(--color-border);
	vertical-align: middle;
	overflow-wrap: break-word;
}

.ledger-row:hover td {
	background-color: var(--color-background-hover);
}

/* Aniversario cell: sello + rango */
.cell-aniversario {
	display: flex;
	align-items: center;
	gap: 14px;
}

.seal {
	flex-shrink: 0;
	width: 34px;
	height: 34px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-family: Georgia, serif;
	font-weight: 700;
	font-size: 0.95rem;
	border: 2px dashed var(--color-border);
	color: var(--color-text-maxcontrast);
}

.seal--pagado {
	border-style: solid;
	border-color: var(--prima-accent);
	background: var(--prima-accent-soft);
	color: var(--prima-accent);
}

.periodo-info {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}

.periodo-info strong {
	font-size: 0.95rem;
	color: var(--color-main-text);
}

.periodo-info small {
	font-variant-numeric: tabular-nums;
	color: var(--color-text-maxcontrast);
	font-size: 0.78rem;
	white-space: normal;
}

.cell-fecha {
	font-variant-numeric: tabular-nums;
	font-size: 0.9rem;
}

.muted {
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
}

/* Badges de estado */
.badge {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 5px 12px;
	border-radius: 999px;
	font-size: 0.8rem;
	font-weight: 600;
	white-space: normal;
}

.badge--pagado {
	background: var(--prima-accent-soft);
	color: var(--prima-accent);
}

.badge--pendiente {
	background: var(--color-background-darker, var(--color-background-hover));
	color: var(--color-text-maxcontrast);
}

/* ── Modal de confirmación / edición ──────────────────── */
.confirm-pago {
	padding: 28px 32px 32px;
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.confirm-lead {
	margin: 0;
	font-size: 0.95rem;
	line-height: 1.5;
	color: var(--color-main-text);
}

.confirm-field {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.confirm-field label {
	font-size: 0.8rem;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.confirm-input {
	padding: 12px 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 1rem;
	font-variant-numeric: tabular-nums;
	box-sizing: border-box;
}

.confirm-input:focus {
	outline: none;
	border-color: var(--prima-accent);
	box-shadow: 0 0 0 2px var(--prima-accent-soft);
}

.confirm-input--date {
	width: 100%;
	max-width: 280px;
	min-height: 44px;
}

.confirm-input--num {
	max-width: 180px;
}

.confirm-hint {
	color: var(--color-text-maxcontrast);
	font-size: 0.78rem;
	line-height: 1.4;
}

.confirm-actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 6px;
}

/* ── Responsive ─────────────────────────────── */
@media (max-width: 640px) {
	.ledger thead {
		display: none;
	}
	.ledger, .ledger tbody, .ledger-row, .ledger-row td {
		display: block;
		width: 100%;
	}
	.ledger-row {
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large, 8px);
		margin-bottom: 12px;
		padding: 8px;
	}
	.ledger-row td {
		border-bottom: none;
		padding: 8px 10px;
	}

	.confirm-input--date {
		max-width: 100%;
	}
}

.btn-prima-vacacional {
    background-color: #000;
    color: #fff;
    border: 1px solid #000;
    border-radius: var(--border-radius, 6px);
    padding: 8px 16px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: background-color 0.12s;
}

.btn-prima-vacacional:hover {
    background-color: #3a3a3a;
}

.btn-prima-vacacional:active {
    background-color: #000;
}
</style>
