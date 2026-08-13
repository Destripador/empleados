<template>
	<div class="detalle-ausencia">
		<!-- Loading state -->
		<div v-if="loading" class="detalle-ausencia__loading">
			<NcLoadingIcon :size="40" />
		</div>

		<!-- Content -->
		<template v-else-if="ausencia">
			<!-- Status badge -->
			<div class="detalle-ausencia__status">
				<span :class="['status-badge', `status-badge--${statusKey}`]">
					{{ statusLabel }}
				</span>
			</div>

			<!-- Main info grid -->
			<div class="detalle-ausencia__grid">
				<div class="info-item">
					<span class="info-item__label">{{ t('empleados', 'Absence type') }}</span>
					<strong class="info-item__value">{{ ausencia.tipo_nombre }}</strong>
				</div>

				<div class="info-item">
					<span class="info-item__label">{{ t('empleados', 'From') }}</span>
					<strong class="info-item__value">{{ formatDate(ausencia.fecha_de) }}</strong>
				</div>

				<div class="info-item">
					<span class="info-item__label">{{ t('empleados', 'To') }}</span>
					<strong class="info-item__value">{{ formatDate(ausencia.fecha_hasta) }}</strong>
				</div>

				<div class="info-item">
					<span class="info-item__label">{{ t('empleados', 'Days requested') }}</span>
					<strong class="info-item__value">{{ ausencia.dias_solicitados ?? '—' }}</strong>
				</div>

				<div v-if="ausencia.turno" class="info-item">
					<span class="info-item__label">{{ t('empleados', 'Shift') }}</span>
					<strong class="info-item__value">{{ turnoLabel }}</strong>
				</div>

				<div v-if="ausencia.prima_vacacional == 1" class="info-item">
					<span class="info-item__label">{{ t('empleados', 'Vacation bonus') }}</span>
					<strong class="info-item__value">{{ t('empleados', 'Requested') }}</strong>
				</div>

				<div v-if="ausencia.notas" class="info-item info-item--full">
					<span class="info-item__label">{{ t('empleados', 'Comments from requester') }}</span>
					<p class="info-item__value info-item__notes">
						{{ ausencia.notas }}
					</p>
				</div>
			</div>

			<!-- Motivo de rechazo, solo si la ausencia fue rechazada -->
			<div v-if="statusKey === 'rejected'" class="detalle-ausencia__motivo-rechazo">
				<span class="info-item__label">{{ t('empleados', 'Comments from the approver:') }}</span>
				<p class="info-item__value info-item__notes">
					{{ ausencia.motivo_rechazo || '' }}
				</p>
			</div>

			<!-- Estatus de aprobación por rol -->
			<div class="detalle-ausencia__aprobaciones">
				<button class="aprobaciones__toggle" @click="showAprobaciones = !showAprobaciones">
					<ChevronDown :size="18" :class="{ 'is-open': showAprobaciones }" />
					{{ t('empleados', 'Approval status') }}
				</button>

				<div v-if="showAprobaciones" class="aprobaciones__body">
					<div v-for="rol in estadosAprobacion" :key="rol.key" class="aprobaciones__row">
						<span class="aprobaciones__rol">{{ rol.label }}</span>
						<span :class="['aprobaciones__estado', `aprobaciones__estado--${rol.estado}`]">
							{{ rol.texto }}
						</span>
					</div>
				</div>
			</div>

			<!-- Actions -->
			<div class="detalle-ausencia__actions">
				<NcButton
					v-if="canEdit"
					type="secondary"
					@click="handleEdit">
					<template #icon>
						<Pencil :size="18" />
					</template>
					{{ t('empleados', 'Edit') }}
				</NcButton>

				<!-- Botón único: cancelar (dueño/admin) o rechazar (jefe con aprobación pendiente) -->
				<NcButton
					v-if="canCancel"
					:disabled="cancelling || procesando"
					class="btn-cancel"
					@click="confirmCancel">
					<template #icon>
						<NcLoadingIcon v-if="cancelling || procesando" :size="18" />
						<Cancel v-else :size="18" />
					</template>
					{{ esRechazoDeJefe ? t('empleados', 'Reject') : t('empleados', 'Cancel absence') }}
				</NcButton>

				<NcButton v-if="puedeAprobar"
					type="primary"
					:disabled="procesando"
					@click="aprobar(rolPrincipalAprobar)">
					{{ t('empleados', 'Approve') }}
				</NcButton>

				<NcButton v-if="puedeAprobarComoSocioRH"
					type="primary"
					:disabled="procesando"
					@click="aprobar('capital_humano_como_socio')">
					{{ t('empleados', 'Approve on behalf of partner') }}
				</NcButton>
			</div>

			<!-- Cancelar/Rechazar confirmación (mismo diálogo, texto según quién lo ejecuta) -->
			<div v-if="showConfirm" class="detalle-ausencia__confirm">
				<NcNoteCard
					type="warning"
					:text="esRechazoDeJefe
						? t('empleados', 'Are you sure you want to reject this absence?')
						: t('empleados', 'Are you sure you want to cancel this absence? This action cannot be undone.')" />

				<!-- Solo al rechazar: mostramos el comentario original del solicitante y permitimos agregar un motivo -->
				<template v-if="esRechazoDeJefe">
					<div v-if="ausencia.notas" class="confirm-motivo__original">
						<span class="info-item__label">{{ t('empleados', 'Comments from requester') }}</span>
						<p class="info-item__notes">
							{{ ausencia.notas }}
						</p>
					</div>

					<div class="confirm-motivo">
						<label class="info-item__label" for="motivo-rechazo">
							{{ t('empleados', 'Add reason (optional)') }}
						</label>
						<textarea
							id="motivo-rechazo"
							v-model="motivoRechazo"
							class="confirm-motivo__textarea"
							rows="3"
							:placeholder="t('empleados', 'Explain why this absence is being rejected...')" />
					</div>
				</template>

				<div class="detalle-ausencia__confirm-actions">
					<NcButton class="btn-cancel" @click="ejecutarCancelacion">
						{{ esRechazoDeJefe ? t('empleados', 'Yes, reject it') : t('empleados', 'Yes, cancel it') }}
					</NcButton>
					<NcButton type="secondary" @click="cancelarConfirm">
						{{ t('empleados', 'Go back') }}
					</NcButton>
				</div>
			</div>
		</template>

		<!-- Error -->
		<div v-else class="detalle-ausencia__empty">
			<NcNoteCard type="error" :text="t('empleados', 'Could not load absence details.')" />
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import Cancel from 'vue-material-design-icons/Cancel.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'

import {
	NcButton,
	NcLoadingIcon,
	NcNoteCard,
} from '@nextcloud/vue'

export default {
	name: 'DetalleAusencia',

	components: {
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		Cancel,
		Pencil,
		ChevronDown,
	},

	props: {
		idHistorial: {
			type: [Number, String],
			required: true,
		},
		isAdmin: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['cancelled', 'edit', 'close', 'approved', 'rejected'],

	data() {
		return {
			ausencia: null,
			loading: true,
			cancelling: false,
			showConfirm: false,
			procesando: false,
			showAprobaciones: false,
			motivoRechazo: '',
		}
	},

	computed: {
		esRechazoDeJefe() {
			if (!this.ausencia) return false
			return this.statusKey === 'pending'
				&& (this.ausencia.es_gerente || this.ausencia.es_socio || this.ausencia.es_supervisor)
		},

		canCancel() {
			if (!this.ausencia) return false
			if (this.statusKey === 'cancelled' || this.statusKey === 'rejected') return false

			// Jefe con aprobación pendiente: el botón único también sirve para rechazar
			if (this.esRechazoDeJefe) return true

			// Dueño/admin cancelando su propia solicitud
			const fechaInicio = new Date(this.ausencia.fecha_de)
			const hoy = new Date()
			hoy.setHours(0, 0, 0, 0)
			return this.isAdmin || fechaInicio >= hoy
		},

		canEdit() {
			if (!this.ausencia) return false
			const pendiente = Number(this.ausencia.a_gerente) <= 0
				&& Number(this.ausencia.a_socio) <= 0
				&& Number(this.ausencia.a_supervisor ?? 0) <= 0
			const fechaInicio = new Date(this.ausencia.fecha_de)
			const hoy = new Date()
			hoy.setHours(0, 0, 0, 0)
			return pendiente && (this.isAdmin || fechaInicio >= hoy)
		},

		statusKey() {
			if (!this.ausencia) return 'pending'
			const g = Number(this.ausencia.a_gerente)
			const s = Number(this.ausencia.a_socio)
			const sup = Number(this.ausencia.a_supervisor ?? 0)
			const ch = Number(this.ausencia.a_capital_humano ?? 0)
			if (g === 3 || s === 3 || sup === 3) return 'cancelled'
			if (g === 2 || s === 2 || sup === 2 || ch === 2) return 'rejected'
			if (g === 1 && s === 1 && sup === 1 && ch === 1) return 'approved'
			return 'pending'
		},

		puedeAprobar() {
			return this.puedeAprobarGerente || this.puedeAprobarSocio || this.puedeAprobarSupervisor || this.puedeAprobarCapitalHumano
		},
		rolPrincipalAprobar() {
			if (this.puedeAprobarGerente) return 'gerente'
			if (this.puedeAprobarSocio) return 'socio'
			if (this.puedeAprobarSupervisor) return 'supervisor'
			if (this.puedeAprobarCapitalHumano) return 'capital_humano'
			return null
		},
		puedeAprobarSupervisor() {
			return this.ausencia?.es_supervisor && Number(this.ausencia.a_supervisor ?? 0) === 0
		},

		puedeAprobarGerente() {
			return this.ausencia?.es_gerente && Number(this.ausencia.a_gerente) === 0
		},
		puedeAprobarSocio() {
			return this.ausencia?.es_socio && Number(this.ausencia.a_socio) === 0
		},
		puedeAprobarCapitalHumano() {
			if (!this.ausencia?.es_privilegiado) return false
			if (Number(this.ausencia.a_capital_humano ?? 0) !== 0) return false
			if (this.ausencia?.es_gerente || this.ausencia?.es_socio || this.ausencia?.es_supervisor) return true
			return Number(this.ausencia.a_gerente) === 1
				&& Number(this.ausencia.a_socio) === 1
				&& Number(this.ausencia.a_supervisor ?? 0) === 1
		},
		puedeAprobarComoSocioRH() {
			if (!this.ausencia?.es_privilegiado) return false
			if (this.ausencia?.es_socio) return false
			if (Number(this.ausencia.a_socio) === 1) return false

			const supervisorOk = !this.ausencia.tiene_supervisor || Number(this.ausencia.a_supervisor ?? 0) === 1
			return Number(this.ausencia.a_gerente) === 1 && supervisorOk
		},

		statusLabel() {
			const labels = {
				pending: t('empleados', 'Pending approval'),
				approved: t('empleados', 'Approved'),
				rejected: t('empleados', 'Rejected'),
				cancelled: t('empleados', 'Cancelled'),
			}
			return labels[this.statusKey] ?? t('empleados', 'Unknown')
		},

		estadosAprobacion() {
			if (!this.ausencia) return []

			const roles = [
				{ key: 'socio', label: t('empleados', 'Partner'), estado: Number(this.ausencia.a_socio), nombre: this.ausencia.nombre_socio },
				{ key: 'gerente', label: t('empleados', 'Manager'), estado: Number(this.ausencia.a_gerente), nombre: this.ausencia.nombre_gerente },
				{ key: 'supervisor', label: t('empleados', 'Supervisor'), estado: Number(this.ausencia.a_supervisor ?? 0), nombre: this.ausencia.nombre_supervisor },
				{ key: 'capital_humano', label: t('empleados', 'Human resources'), estado: Number(this.ausencia.a_capital_humano ?? 0), nombre: this.ausencia.nombre_capital_humano },
			]

			return roles.map((rol) => {
				let texto
				let estadoKey
				if (rol.estado === 1) {
					estadoKey = 'aprobado'
					texto = rol.nombre ? t('empleados', 'Approved by {nombre}', { nombre: rol.nombre }) : t('empleados', 'Approved')
				} else if (rol.estado === 2) {
					estadoKey = 'rechazado'
					texto = t('empleados', 'Rejected')
				} else if (rol.estado === 3) {
					estadoKey = 'cancelado'
					texto = t('empleados', 'Cancelled')
				} else {
					estadoKey = 'pendiente'
					texto = t('empleados', 'Not approved yet')
				}
				return { key: rol.key, label: rol.label, estado: estadoKey, texto }
			})
		},

		turnoLabel() {
			if (!this.ausencia?.turno) return ''
			return this.ausencia.turno === 'manana'
				? t('empleados', 'Morning')
				: t('empleados', 'Afternoon')
		},
	},

	mounted() {
		this.fetchDetalle()
	},

	methods: {
		t,

		async fetchDetalle() {
			this.loading = true
			try {
				const response = await axios.get(
					generateUrl('/apps/empleados/GetDetalleAusencia'),
					{ params: { id: this.idHistorial } },
				)
				this.ausencia = response?.data?.ocs?.data ?? null
			} catch (err) {
				showError(t('empleados', 'Error loading absence details: {error}', { error: String(err) }))
			} finally {
				this.loading = false
			}
		},

		formatDate(dateStr) {
			if (!dateStr) return '—'
			return new Date(dateStr).toLocaleDateString(undefined, {
				day: 'numeric',
				month: 'long',
				year: 'numeric',
			})
		},

		handleEdit() {
			this.$emit('edit', this.ausencia)
		},

		async aprobar(rol) {
			this.procesando = true
			try {
				const response = await axios.post(generateUrl('/apps/empleados/AprobarAusencia'), { id: this.idHistorial, rol })
				if (response.data?.ocs?.data?.success) {
					showSuccess(t('empleados', 'Approved successfully'))
					await this.fetchDetalle()
					this.$emit('approved')
				} else {
					showError(response.data?.ocs?.data?.message || t('empleados', 'Could not approve'))
				}
			} catch (err) {
				showError(t('empleados', 'Error approving: {error}', { error: String(err) }))
			} finally {
				this.procesando = false
			}
		},

		async rechazar() {
			const rol = this.ausencia.es_gerente
				? 'gerente'
				: this.ausencia.es_socio
					? 'socio'
					: this.ausencia.es_supervisor
						? 'supervisor'
						: 'capital_humano'
			const motivo = this.motivoRechazo.trim()

			this.procesando = true
			try {
				const response = await axios.post(generateUrl('/apps/empleados/RechazarAusencia'), { id: this.idHistorial, rol, motivo })
				if (response.data?.ocs?.data?.success) {
					showSuccess(t('empleados', 'Absence rejected'))
					this.$emit('rejected')
				} else {
					showError(response.data?.ocs?.data?.message || t('empleados', 'Could not reject'))
				}
			} catch (err) {
				showError(t('empleados', 'Error rejecting: {error}', { error: String(err) }))
			} finally {
				this.procesando = false
			}
		},

		confirmCancel() {
			this.motivoRechazo = ''
			this.showConfirm = true
		},

		cancelarConfirm() {
			this.showConfirm = false
			this.motivoRechazo = ''
		},

		// Decide qué endpoint disparar según quién esté ejecutando la acción
		async ejecutarCancelacion() {
			this.showConfirm = false
			if (this.esRechazoDeJefe) {
				await this.rechazar()
			} else {
				await this.cancelAbsence()
			}
			this.motivoRechazo = ''
		},

		async cancelAbsence() {
			this.cancelling = true
			try {
				const response = await axios.post(
					generateUrl('/apps/empleados/CancelarAusencia'),
					{ id: this.idHistorial },
				)
				if (response.data?.ocs?.data?.success) {
					showSuccess(t('empleados', 'Absence cancelled successfully'))
					this.$emit('cancelled')
				} else {
					showError(t('empleados', 'Could not cancel the absence'))
				}
			} catch (err) {
				showError(t('empleados', 'Error cancelling absence: {error}', { error: String(err) }))
			} finally {
				this.cancelling = false
			}
		},
	},
}
</script>

<style scoped>
.detalle-ausencia {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.detalle-ausencia__loading {
	display: flex;
	justify-content: center;
	padding: 48px 0;
}

.detalle-ausencia__status {
	display: flex;
	justify-content: flex-end;
}

.status-badge {
	display: inline-block;
	padding: 4px 12px;
	border-radius: 12px;
	font-size: 0.8rem;
	font-weight: 600;
	letter-spacing: 0.03em;
}

.status-badge--pending   { background: var(--color-warning-light, #dfae0c); color: var(--color-warning, #ffffff); }
.status-badge--approved  { background: var(--color-success-light, #d1e7dd); color: var(--color-success, #0a3622); }
.status-badge--rejected  { background: var(--color-error-light,   #f8d7da); color: var(--color-error,   #58151c); }
.status-badge--cancelled { background: var(--color-background-darker, #e9ecef); color: var(--color-text-maxcontrast); }

/* ── Info grid ───────────────────────────────── */
.detalle-ausencia__grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 16px;
}

.info-item {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.info-item--full {
	grid-column: 1 / -1;
}

.info-item__label {
	font-size: 0.78rem;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.05em;
}

.info-item__value {
	font-size: 1rem;
	color: var(--color-main-text);
}

.info-item__notes {
	font-size: 0.9rem;
	margin: 0;
	white-space: pre-wrap;
	color: var(--color-text-lighter);
}

.detalle-ausencia__motivo-rechazo {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

/* ── Actions ─────────────────────────────────── */
.detalle-ausencia__actions {
	display: flex;
	gap: 12px;
	justify-content: flex-end;
	flex-wrap: wrap;
}

/* Botón cancelar en rojo */
.btn-cancel {
	background-color: #c0392b !important;
	color: #fff !important;
	border-color: #c0392b !important;
}
.btn-cancel:hover {
	background-color: #a93226 !important;
	border-color: #a93226 !important;
}

/* ── Confirm ─────────────────────────────────── */
.detalle-ausencia__confirm {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.detalle-ausencia__confirm-actions {
	display: flex;
	gap: 10px;
	justify-content: flex-end;
}

.confirm-motivo__original {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 10px 12px;
	background: var(--color-background-hover);
	border-radius: var(--border-radius, 8px);
}

.confirm-motivo {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.confirm-motivo__textarea {
	width: 100%;
	resize: vertical;
	padding: 8px 10px;
	border: 1px solid var(--color-border-dark, var(--color-border));
	border-radius: var(--border-radius, 8px);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-family: inherit;
	font-size: 0.9rem;
}

.confirm-motivo__textarea:focus {
	outline: none;
	border-color: var(--color-primary-element, #0082c9);
}

.detalle-ausencia__aprobaciones {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius, 8px);
	overflow: hidden;
}

.aprobaciones__toggle {
	width: 100%;
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 10px 14px;
	background: var(--color-background-hover);
	border: none;
	cursor: pointer;
	font-weight: 600;
	font-size: 0.9rem;
	color: var(--color-main-text);
}

.aprobaciones__toggle svg {
	transition: transform 0.15s ease;
}
.aprobaciones__toggle svg.is-open {
	transform: rotate(180deg);
}

.aprobaciones__body {
	display: flex;
	flex-direction: column;
}

.aprobaciones__row {
	display: flex;
	justify-content: space-between;
	padding: 8px 14px;
	border-top: 1px solid var(--color-border);
	font-size: 0.88rem;
}

.aprobaciones__estado--aprobado  { color: #488d48; font-weight: 800; }
.aprobaciones__estado--rechazado { color: #972c2cfa; font-weight: 800; }
.aprobaciones__estado--cancelado { color: var(--color-text-maxcontrast); font-weight: 800; }
.aprobaciones__estado--pendiente { color: #ccad3d; font-weight: 800; }
</style>
