<template>
	<div class="informe-prima">
		<header class="informe-header">
			<div class="informe-identidad">
				<NcAvatar
					v-if="empleadoUid"
					disable-menu
					class="informe-avatar"
					:size="56"
					:user="empleadoUid"
					:display-name="nombreEmpleado" />

				<div v-else class="informe-avatar-fallback">
					{{ empleadoIniciales }}
				</div>

				<div class="informe-heading">
					<span class="informe-eyebrow">
						{{ t('empleados', 'Vacation bonus ledger') }}
					</span>

					<h2 class="informe-title">
						{{ t('empleados', 'Vacation Bonus Report') }}
					</h2>

					<p v-if="nombreEmpleado" class="informe-sub">
						{{ nombreEmpleado }}
					</p>
				</div>
			</div>

			<div
				v-if="!loading && periodos.length > 0"
				class="informe-resumen">
				<div class="resumen-item">
					<span class="resumen-item__label">
						{{ t('empleados', 'Total periods') }}
					</span>
					<strong class="resumen-item__value">
						{{ resumenPeriodos.total }}
					</strong>
				</div>

				<div class="resumen-item resumen-item--paid">
					<span class="resumen-item__label">
						{{ t('empleados', 'Paid') }}
					</span>
					<strong class="resumen-item__value">
						{{ resumenPeriodos.pagados }}
					</strong>
				</div>

				<div class="resumen-item resumen-item--pending">
					<span class="resumen-item__label">
						{{ t('empleados', 'Pending') }}
					</span>
					<strong class="resumen-item__value">
						{{ resumenPeriodos.pendientes }}
					</strong>
				</div>

				<div class="resumen-item resumen-item--days">
					<span class="resumen-item__label">
						{{ t('empleados', 'Paid days') }}
					</span>
					<strong class="resumen-item__value">
						{{ resumenPeriodos.diasPagados }}
					</strong>
				</div>
			</div>
		</header>

		<div v-if="loading" class="informe-state">
			<NcLoadingIcon :size="36" />
			<strong>{{ t('empleados', 'Loading anniversaries...') }}</strong>
			<span>{{ t('empleados', 'Please wait while the payment history is loaded.') }}</span>
		</div>

		<div v-else-if="periodos.length === 0" class="informe-state">
			<span class="informe-state__icon">🏖️</span>
			<strong>{{ t('empleados', 'No anniversary periods found') }}</strong>
			<span>{{ t('empleados', 'No anniversary periods found for this employee.') }}</span>
		</div>

		<div v-else class="ledger-shell">
			<table class="ledger">
				<thead>
					<tr>
						<th scope="col" class="col-aniversario">
							{{ t('empleados', 'Anniversary') }}
						</th>
						<th scope="col" class="col-fecha">
							{{ t('empleados', 'Requested on') }}
						</th>
						<th scope="col" class="col-accion">
							{{ t('empleados', 'Action') }}
						</th>
						<th scope="col" class="col-estado">
							{{ t('empleados', 'Status') }}
						</th>
					</tr>
				</thead>

				<tbody>
					<tr
						v-for="periodo in periodos"
						:key="periodo.numero_aniversario"
						class="ledger-row"
						:class="{ 'ledger-row--paid': periodo.pagado }">
						<td
							class="cell-aniversario"
							:data-label="t('empleados', 'Anniversary')">
							<span
								class="seal"
								:class="{ 'seal--pagado': periodo.pagado }">

								<CheckDecagram
									v-if="periodo.pagado"
									:size="18" />

								<span v-else>
									{{ periodo.numero_aniversario }}
								</span>
							</span>

							<div class="periodo-info">
								<strong>
									{{ t('empleados', 'Anniversary {n}', {
										n: periodo.numero_aniversario,
									}) }}
								</strong>

								<small>
									{{ formatFecha(periodo.periodo_inicio) }}
									<span aria-hidden="true">→</span>
									{{ formatFecha(periodo.periodo_fin) }}
								</small>

								<span
									v-if="periodo.es_actual"
									class="periodo-actual">

									{{ t('empleados', 'Current period') }}
								</span>
							</div>
						</td>

						<td
							class="cell-fecha"
							:data-label="t('empleados', 'Requested on')">
							<span
								v-if="periodo.fecha_solicitud"
								class="fecha-principal">

								{{ formatFecha(periodo.fecha_solicitud) }}
							</span>

							<span v-else class="muted">
								{{ t('empleados', 'No request') }}
							</span>
						</td>

						<td
							class="cell-accion"
							:data-label="t('empleados', 'Action')">
							<button
								type="button"
								class="payment-button"
								:class="{ 'payment-button--edit': periodo.pagado }"
								@click="abrirConfirmacion(periodo)">
								{{ periodo.pagado
									? t('empleados', 'Edit payment')
									: t('empleados', 'Mark payment') }}
							</button>
						</td>

						<td
							class="cell-estado"
							:data-label="t('empleados', 'Status')">
							<div class="estado-stack">
								<span
									v-if="periodo.pagado"
									class="badge badge--pagado">

									<CheckDecagram :size="14" />
									{{ t('empleados', 'Paid') }}
								</span>

								<span v-else class="badge badge--pendiente">
									<ClockOutline :size="14" />
									{{ t('empleados', 'Pending') }}
								</span>

								<small
									v-if="periodo.pagado"
									class="estado-detalle">

									{{ t('empleados', '{fecha} · {dias} days', {
										fecha: formatFecha(periodo.fecha_pago),
										dias: periodo.dias_pagados,
									}) }}
								</small>

								<small
									v-else-if="periodo.fecha_solicitud"
									class="estado-detalle">

									{{ t('empleados', 'Requested and awaiting payment') }}
								</small>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<NcModal
			v-if="confirmando"
			size="normal"
			:name="periodoSeleccionado && periodoSeleccionado.pagado
				? t('empleados', 'Edit payment')
				: t('empleados', 'Confirm payment')"
			@close="cancelarConfirmacion">
			<div class="confirm-pago">
				<div class="confirm-periodo">
					<span
						class="seal confirm-periodo__seal"
						:class="{ 'seal--pagado': periodoSeleccionado.pagado }">

						<CheckDecagram
							v-if="periodoSeleccionado.pagado"
							:size="18" />

						<span v-else>
							{{ periodoSeleccionado.numero_aniversario }}
						</span>
					</span>

					<div class="confirm-periodo__info">
						<strong>
							{{ t('empleados', 'Anniversary {n}', {
								n: periodoSeleccionado.numero_aniversario,
							}) }}
						</strong>

						<small>
							{{ formatFecha(periodoSeleccionado.periodo_inicio) }}
							<span aria-hidden="true">→</span>
							{{ formatFecha(periodoSeleccionado.periodo_fin) }}
						</small>
					</div>
				</div>

				<p class="confirm-lead">
					<template v-if="periodoSeleccionado.pagado">
						{{ t('empleados', 'You are editing the payment record for {n}.', {
							n: t('empleados', 'Anniversary {n}', {
								n: periodoSeleccionado.numero_aniversario,
							}),
						}) }}
					</template>

					<template v-else>
						{{ t('empleados', 'You are about to mark the vacation bonus for {n} as paid.', {
							n: t('empleados', 'Anniversary {n}', {
								n: periodoSeleccionado.numero_aniversario,
							}),
						}) }}
					</template>
				</p>

				<div class="confirm-grid">
					<div class="confirm-field">
						<label for="prima-payment-date">
							{{ t('empleados', 'Payment date') }}
						</label>

						<input
							id="prima-payment-date"
							v-model="fechaConfirmar"
							type="date"
							class="confirm-input">
					</div>

					<div class="confirm-field">
						<label for="prima-payment-days">
							{{ t('empleados', 'Days to mark as paid') }}
						</label>

						<input
							id="prima-payment-days"
							v-model.number="diasConfirmar"
							type="number"
							min="0"
							step="0.5"
							class="confirm-input">

						<small class="confirm-hint">
							{{ t('empleados', 'Entitled days for this period: {dias}', {
								dias: periodoSeleccionado.dias_derecho,
							}) }}
						</small>
					</div>
				</div>

				<div class="confirm-note">
					<ClockOutline :size="18" />

					<span>
						{{ t('empleados', 'The payment date and paid days can be edited later.') }}
					</span>
				</div>

				<div class="confirm-actions">
					<NcButton
						type="tertiary"
						@click="cancelarConfirmacion">
						{{ t('empleados', 'Cancel') }}
					</NcButton>

					<NcButton
						type="primary"
						@click="confirmarPago">
						{{ periodoSeleccionado.pagado
							? t('empleados', 'Save changes')
							: t('empleados', 'Confirm payment') }}
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

import { NcAvatar, NcButton, NcModal, NcLoadingIcon } from '@nextcloud/vue'

export default {
	name: 'InformePrimaVacacional',

	components: {
		NcAvatar,
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

		empleadoRegistro() {
			return this.historialCompleto.find(item =>
				item.nombre_empleado === this.nombreEmpleado,
			) || {}
		},

		empleadoUid() {
			const item = this.empleadoRegistro

			return item.Id_user
				|| item.id_user
				|| item.uid
				|| item.user
				|| item.username
				|| this.nombreEmpleado
				|| ''
		},

		empleadoIniciales() {
			if (!this.nombreEmpleado) return '?'

			return this.nombreEmpleado
				.split(/[\s._-]+/)
				.filter(Boolean)
				.map(parte => parte[0])
				.slice(0, 2)
				.join('')
				.toUpperCase()
		},

		resumenPeriodos() {
			const pagados = this.periodos.filter(periodo => periodo.pagado)

			return {
				total: this.periodos.length,
				pagados: pagados.length,
				pendientes: this.periodos.length - pagados.length,
				diasPagados: pagados.reduce(
					(total, periodo) => total + (Number(periodo.dias_pagados) || 0),
					0,
				),
			}
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
	--prima-accent-dark: #7e541d;
	--prima-accent-soft: rgba(169, 118, 47, 0.12);
	--prima-success: #087f5b;
	--prima-success-soft: #d7f5e8;
	--prima-warning: #9a6700;
	--prima-warning-soft: #fff1c7;
	--prima-border: var(--color-border);
	--prima-surface: var(--color-main-background);
	--prima-surface-soft: var(--color-background-hover);

	box-sizing: border-box;
}

.informe-prima,
.informe-prima * {
	box-sizing: border-box;
}

/* ========================================
 * ENCABEZADO
 * ======================================== */

.informe-header {
	display: flex;
	align-items: center;
	justify-content: space-between;

	padding: 22px 24px;
	gap: 24px;

	background:
		linear-gradient(
			135deg,
			rgba(169, 118, 47, 0.11),
			rgba(169, 118, 47, 0.025) 48%,
			transparent
		);

	border-bottom: 1px solid var(--prima-border);
}

.informe-identidad {
	display: flex;
	align-items: center;

	min-width: 0;
	gap: 14px;
}

.informe-avatar {
	flex: 0 0 auto;

	border: 2px solid rgba(169, 118, 47, 0.32);
	border-radius: 50%;

	box-shadow: 0 4px 12px rgba(99, 65, 18, 0.14);
}

.informe-avatar-fallback {
	display: flex;
	flex: 0 0 56px;
	align-items: center;
	justify-content: center;

	width: 56px;
	height: 56px;

	color: var(--prima-accent-dark);
	font-size: 1rem;
	font-weight: 800;

	background: var(--prima-accent-soft);
	border: 2px solid rgba(169, 118, 47, 0.3);
	border-radius: 50%;
}

.informe-heading {
	min-width: 0;
}

.informe-eyebrow {
	display: block;

	margin-bottom: 4px;

	color: var(--prima-accent);
	font-size: 0.68rem;
	font-weight: 800;
	text-transform: uppercase;
	letter-spacing: 0.11em;
}

.informe-title {
	margin: 0;
	overflow: hidden;

	color: var(--color-main-text);
	font-family: Georgia, 'Iowan Old Style', serif;
	font-size: 1.55rem;
	font-weight: 700;
	line-height: 1.2;
	text-overflow: ellipsis;
	letter-spacing: -0.015em;
	white-space: nowrap;
}

.informe-sub {
	margin: 4px 0 0;
	overflow: hidden;

	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
	font-weight: 600;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* ========================================
 * RESUMEN SUPERIOR
 * ======================================== */

.informe-resumen {
	display: grid;
	flex: 0 0 auto;
	grid-template-columns: repeat(4, minmax(92px, 1fr));

	min-width: 430px;
	gap: 8px;
}

.resumen-item {
	display: flex;
	flex-direction: column;
	justify-content: center;

	min-height: 62px;
	padding: 9px 11px;
	gap: 3px;

	background: var(--prima-surface);
	border: 1px solid var(--prima-border);
	border-radius: 10px;

	box-shadow: 0 2px 7px rgba(0, 0, 0, 0.035);
}

.resumen-item--paid {
	background: var(--prima-success-soft);
	border-color: rgba(8, 127, 91, 0.18);
}

.resumen-item--pending {
	background: var(--prima-warning-soft);
	border-color: rgba(154, 103, 0, 0.18);
}

.resumen-item--days {
	background: var(--prima-accent-soft);
	border-color: rgba(169, 118, 47, 0.2);
}

.resumen-item__label {
	color: var(--color-text-maxcontrast);
	font-size: 0.59rem;
	font-weight: 800;
	text-transform: uppercase;
	letter-spacing: 0.05em;
}

.resumen-item__value {
	color: var(--color-main-text);
	font-size: 1.15rem;
	font-weight: 800;
	line-height: 1;
}

.resumen-item--paid .resumen-item__value {
	color: var(--prima-success);
}

.resumen-item--pending .resumen-item__value {
	color: var(--prima-warning);
}

.resumen-item--days .resumen-item__value {
	color: var(--prima-accent-dark);
}

/* ========================================
 * ESTADOS
 * ======================================== */

.informe-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;

	min-height: 280px;
	padding: 42px 24px;
	gap: 8px;

	color: var(--color-text-maxcontrast);
	text-align: center;
}

.informe-state strong {
	color: var(--color-main-text);
	font-size: 1rem;
}

.informe-state span {
	max-width: 460px;
	font-size: 0.84rem;
}

.informe-state__icon {
	font-size: 2.5rem !important;
}

/* ========================================
 * TABLA
 * ======================================== */

.ledger-shell {
	padding: 18px 20px 22px;
	overflow-x: auto;
}

.ledger {
	width: 100%;
	min-width: 830px;

	color: var(--color-main-text);
	font-size: 0.82rem;

	background: var(--prima-surface);
	border: 1px solid var(--prima-border);
	border-collapse: separate;
	border-spacing: 0;
	border-radius: 12px;

	table-layout: fixed;
}

.col-aniversario {
	width: 34%;
}

.col-fecha {
	width: 17%;
}

.col-accion {
	width: 20%;
}

.col-estado {
	width: 29%;
}

.ledger thead th {
	padding: 10px 14px;

	color: var(--color-text-maxcontrast);
	font-size: 0.64rem;
	font-weight: 800;
	text-align: left;
	text-transform: uppercase;
	letter-spacing: 0.07em;
	white-space: nowrap;

	background: var(--prima-surface-soft);
	border-bottom: 1px solid var(--prima-border);
}

.ledger thead th:first-child {
	border-top-left-radius: 11px;
}

.ledger thead th:last-child {
	border-top-right-radius: 11px;
}

.ledger-row {
	background: var(--prima-surface);

	transition:
		background-color 0.16s ease,
		box-shadow 0.16s ease;
}

.ledger-row td {
	padding: 13px 14px;

	vertical-align: middle;

	border-bottom: 1px solid var(--prima-border);
}

.ledger-row--paid td:first-child {
	box-shadow: inset 3px 0 0 var(--prima-success);
}

.ledger-row:last-child td {
	border-bottom: none;
}

.cell-aniversario {
	display: flex;
	align-items: center;
	gap: 12px;
}

.seal {
	display: inline-flex;
	flex: 0 0 38px;
	align-items: center;
	justify-content: center;

	width: 38px;
	height: 38px;

	color: var(--color-text-maxcontrast);
	font-family: Georgia, serif;
	font-size: 0.92rem;
	font-weight: 800;

	background: var(--prima-surface-soft);
	border: 2px dashed var(--prima-border);
	border-radius: 50%;
}

.seal--pagado {
	color: var(--prima-accent-dark);

	background: var(--prima-accent-soft);
	border-color: var(--prima-accent);
	border-style: solid;
}

.periodo-info {
	display: flex;
	flex-direction: column;

	min-width: 0;
	gap: 3px;
}

.periodo-info strong {
	overflow: hidden;

	color: var(--color-main-text);
	font-size: 0.88rem;
	font-weight: 750;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.periodo-info small {
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	font-variant-numeric: tabular-nums;
	white-space: nowrap;
}

.periodo-actual {
	align-self: flex-start;

	padding: 2px 6px;

	color: var(--prima-accent-dark);
	font-size: 0.58rem;
	font-weight: 800;
	text-transform: uppercase;
	letter-spacing: 0.045em;

	background: var(--prima-accent-soft);
	border-radius: 999px;
}

.cell-fecha {
	font-variant-numeric: tabular-nums;
}

.fecha-principal {
	color: var(--color-main-text);
	font-size: 0.8rem;
	font-weight: 650;
	white-space: nowrap;
}

.muted {
	color: var(--color-text-maxcontrast);
	font-size: 0.77rem;
}

.payment-button {
	min-width: 128px;
	min-height: 36px;
	padding: 7px 12px;

	color: white;
	font-family: inherit;
	font-size: 0.74rem;
	font-weight: 750;

	background: #000;
	border: 1px solid #000;
	border-radius: 8px;

	cursor: pointer;

	transition:
		background-color 0.15s ease,
		border-color 0.15s ease,
		color 0.15s ease,
		transform 0.15s ease;
}

.payment-button--edit {
	color: var(--prima-accent-dark);

	background: var(--prima-accent-soft);
	border-color: rgba(169, 118, 47, 0.35);
}

.estado-stack {
	display: flex;
	flex-direction: column;
	align-items: flex-start;

	gap: 5px;
}

.badge {
	display: inline-flex;
	align-items: center;

	padding: 4px 9px;
	gap: 5px;

	font-size: 0.68rem;
	font-weight: 750;
	white-space: nowrap;

	border-radius: 999px;
}

.badge--pagado {
	color: var(--prima-success);
	background: var(--prima-success-soft);
}

.badge--pendiente {
	color: var(--prima-warning);
	background: var(--prima-warning-soft);
}

.estado-detalle {
	color: var(--color-text-maxcontrast);
	font-size: 0.68rem;
	font-variant-numeric: tabular-nums;
}

/* ========================================
 * MODAL DE CONFIRMACIÓN
 * ======================================== */

.confirm-pago {
	display: flex;
	flex-direction: column;

	padding: 24px 28px 28px;
	gap: 18px;
}

.confirm-periodo {
	display: flex;
	align-items: center;

	padding: 12px 14px;
	gap: 12px;

	background: var(--prima-accent-soft);
	border: 1px solid rgba(169, 118, 47, 0.22);
	border-radius: 10px;
}

.confirm-periodo__seal {
	flex-basis: 40px;
	width: 40px;
	height: 40px;
}

.confirm-periodo__info {
	display: flex;
	flex-direction: column;

	min-width: 0;
	gap: 3px;
}

.confirm-periodo__info strong {
	color: var(--color-main-text);
	font-size: 0.9rem;
}

.confirm-periodo__info small {
	color: var(--color-text-maxcontrast);
	font-size: 0.74rem;
	font-variant-numeric: tabular-nums;
}

.confirm-lead {
	margin: 0;

	color: var(--color-main-text);
	font-size: 0.87rem;
	line-height: 1.5;
}

.confirm-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));

	gap: 14px;
}

.confirm-field {
	display: flex;
	flex-direction: column;

	min-width: 0;
	gap: 6px;
}

.confirm-field label {
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	font-weight: 750;
}

.confirm-input {
	width: 100%;
	min-height: 42px;
	padding: 9px 11px;

	color: var(--color-main-text);
	font-family: inherit;
	font-size: 0.88rem;
	font-variant-numeric: tabular-nums;

	background: var(--color-main-background);
	border: 1px solid var(--color-border-dark);
	border-radius: 8px;

	outline: none;

	transition:
		border-color 0.15s ease,
		box-shadow 0.15s ease;
}

.confirm-hint {
	color: var(--color-text-maxcontrast);
	font-size: 0.67rem;
	line-height: 1.35;
}

.confirm-note {
	display: flex;
	align-items: flex-start;

	padding: 10px 12px;
	gap: 8px;

	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	line-height: 1.4;

	background: var(--prima-surface-soft);
	border-radius: 8px;
}

.confirm-note svg {
	flex: 0 0 auto;
	color: var(--prima-accent);
}

.confirm-actions {
	display: flex;
	align-items: center;
	justify-content: flex-end;

	padding-top: 14px;
	gap: 8px;

	border-top: 1px solid var(--prima-border);
}

/* ========================================
 * RESPONSIVE
 * ======================================== */

@media screen and (max-width: 930px) {
	.informe-header {
		align-items: flex-start;
		flex-direction: column;
	}

	.informe-resumen {
		grid-template-columns: repeat(4, minmax(0, 1fr));
		width: 100%;
		min-width: 0;
	}
}

@media screen and (max-width: 700px) {
	.informe-prima {
		width: calc(100vw - 24px);
		max-height: 88vh;
		border-radius: 10px;
	}

	.informe-header {
		padding: 18px;
	}

	.informe-title {
		font-size: 1.25rem;
	}

	.informe-resumen {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.ledger-shell {
		padding: 14px;
	}

	.ledger {
		min-width: 0;
		border: none;
		background: transparent;
	}

	.ledger thead {
		display: none;
	}

	.ledger,
	.ledger tbody,
	.ledger-row {
		display: block;
		width: 100%;
	}

	.ledger-row {
		margin-bottom: 10px;
		overflow: hidden;

		background: var(--prima-surface);
		border: 1px solid var(--prima-border);
		border-radius: 10px;
	}

	.ledger-row td {
		display: grid;
		grid-template-columns: minmax(100px, 38%) 1fr;

		width: 100%;
		padding: 9px 11px;
		gap: 10px;

		border-bottom: 1px solid var(--prima-border);
	}

	.ledger-row td::before {
		content: attr(data-label);

		color: var(--color-text-maxcontrast);
		font-size: 0.62rem;
		font-weight: 800;
		text-transform: uppercase;
		letter-spacing: 0.045em;
	}

	.ledger-row--paid td:first-child {
		box-shadow: inset 3px 0 0 var(--prima-success);
	}

	.cell-aniversario {
		align-items: center;
		display: grid;
		grid-template-columns: minmax(100px, 38%) 42px 1fr;
	}

	.cell-aniversario::before {
		grid-column: 1;
	}

	.cell-aniversario .seal {
		grid-column: 2;
	}

	.cell-aniversario .periodo-info {
		grid-column: 3;
	}

	.payment-button {
		justify-self: start;
	}

	.confirm-grid {
		grid-template-columns: 1fr;
	}
}

/* ========================================
 * ESTADOS INTERACTIVOS
 * ======================================== */

.ledger-row:hover {
	background: var(--prima-surface-soft);
}

.payment-button:hover {
	background: #3a3a3a;
}

.payment-button--edit:hover {
	color: white;
	background: var(--prima-accent);
	border-color: var(--prima-accent);
}

.payment-button:active {
	transform: scale(0.98);
}

.confirm-input:focus {
	border-color: var(--prima-accent);

	box-shadow: 0 0 0 2px var(--prima-accent-soft);
}
</style>
