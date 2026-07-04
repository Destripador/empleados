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

				<div v-if="ausencia.prima_vacacional == 1" class="info-item">
					<span class="info-item__label">{{ t('empleados', 'Vacation bonus') }}</span>
					<strong class="info-item__value">{{ t('empleados', 'Requested') }}</strong>
				</div>

				<div v-if="ausencia.notas" class="info-item info-item--full">
					<span class="info-item__label">{{ t('empleados', 'Comments') }}</span>
					<p class="info-item__value info-item__notes">{{ ausencia.notas }}</p>
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

				<NcButton
					v-if="canCancel"
					:disabled="cancelling"
					class="btn-cancel"
					@click="confirmCancel">
					<template #icon>
						<NcLoadingIcon v-if="cancelling" :size="18" />
						<Cancel v-else :size="18" />
					</template>
					{{ t('empleados', 'Cancel absence') }}
				</NcButton>
			</div>

			<!-- Cancel confirmation inline -->
			<div v-if="showConfirm" class="detalle-ausencia__confirm">
				<NcNoteCard type="warning" :text="t('empleados', 'Are you sure you want to cancel this absence? This action cannot be undone.')" />
				<div class="detalle-ausencia__confirm-actions">
					<NcButton class="btn-cancel" @click="cancelAbsence">
						{{ t('empleados', 'Yes, cancel it') }}
					</NcButton>
					<NcButton type="secondary" @click="showConfirm = false">
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

	emits: ['cancelled', 'edit', 'close'],

	data() {
		return {
			ausencia: null,
			loading: true,
			cancelling: false,
			showConfirm: false,
		}
	},

	computed: {
		canCancel() {
			if (!this.ausencia) return false
			if (Number(this.ausencia.a_gerente) === 3 || Number(this.ausencia.a_socio) === 3) return false
			const fechaInicio = new Date(this.ausencia.fecha_de)
			const hoy = new Date()
			hoy.setHours(0, 0, 0, 0)
			return this.isAdmin || fechaInicio >= hoy
		},

		canEdit() {
			if (!this.ausencia) return false
			const pendiente = Number(this.ausencia.a_gerente) <= 0 && Number(this.ausencia.a_socio) <= 0
			const fechaInicio = new Date(this.ausencia.fecha_de)
			const hoy = new Date()
			hoy.setHours(0, 0, 0, 0)
			return pendiente && (this.isAdmin || fechaInicio >= hoy)
		},

		statusKey() {
			if (!this.ausencia) return 'pending'
			const g = Number(this.ausencia.a_gerente)
			const s = Number(this.ausencia.a_socio)
			if (g === 3 || s === 3) return 'cancelled'
			if (g === 2 || s === 2) return 'rejected'
			if (g === 1 && s === 1) return 'approved'
			return 'pending'
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

		confirmCancel() {
			this.showConfirm = true
		},

		async cancelAbsence() {
			this.cancelling = true
			this.showConfirm = false
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
</style>
