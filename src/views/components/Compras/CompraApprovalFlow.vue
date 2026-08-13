<template>
	<section class="approval-flow" aria-labelledby="purchase-approval-title">
		<header class="approval-flow__header">
			<div>
				<p class="approval-flow__eyebrow">
					{{ t('empleados', 'Approval flow') }}
				</p>
				<h3 id="purchase-approval-title">
					{{ t('empleados', 'Request approval') }}
				</h3>
			</div>
			<span :class="['approval-flow__status', `is-${generalStatus.key}`]">
				{{ generalStatus.label }}
			</span>
		</header>

		<div v-if="loading" class="approval-flow__loading" role="status">
			<NcLoadingIcon :size="28" />
			<span>{{ t('empleados', 'Loading approval flow...') }}</span>
		</div>

		<NcNoteCard v-else-if="error" type="error">
			{{ error }}
		</NcNoteCard>

		<template v-else-if="flow">
			<NcNoteCard v-if="flow.requiere_revision" type="warning">
				<p>{{ t('empleados', 'This request requires review of its approval structure.') }}</p>
				<ul v-if="flow.incidencias && flow.incidencias.length">
					<li v-for="issue in flow.incidencias" :key="issue">
						{{ issue }}
					</li>
				</ul>
			</NcNoteCard>

			<div v-if="flow.aprobador_actual" class="approval-flow__current">
				<AccountClockOutline :size="24" aria-hidden="true" />
				<div>
					<span>{{ t('empleados', 'Current approver') }}</span>
					<strong>{{ approverName(flow.aprobador_actual) }}</strong>
					<small>{{ roleLabel(flow.aprobador_actual.rol) }}</small>
				</div>
			</div>

			<ol v-if="stages.length" class="approval-flow__timeline">
				<li v-for="stage in stages"
					:key="stage.id_autorizacion"
					:class="['approval-stage', `is-${stage.visualState}`]"
					:aria-current="stage.visualState === 'current' ? 'step' : null">
					<div class="approval-stage__marker" aria-hidden="true">
						<CheckCircleOutline v-if="stage.visualState === 'completed'" :size="22" />
						<CloseCircleOutline v-else-if="stage.visualState === 'rejected'" :size="22" />
						<Cancel v-else-if="stage.visualState === 'cancelled'" :size="22" />
						<ClockOutline v-else :size="22" />
					</div>

					<div class="approval-stage__content">
						<div class="approval-stage__heading">
							<div>
								<strong>{{ roleLabel(stage.rol) }}</strong>
								<span>{{ approverName(stage) }}</span>
							</div>
							<span class="approval-stage__badge">
								{{ stageStateLabel(stage.visualState) }}
							</span>
						</div>

						<dl v-if="stage.fecha_autorizacion || stage.comentario" class="approval-stage__decision">
							<template v-if="stage.fecha_autorizacion">
								<dt>{{ t('empleados', 'Decision date') }}</dt>
								<dd>{{ formatDateTime(stage.fecha_autorizacion) }}</dd>
								<dt>{{ t('empleados', 'Decision by') }}</dt>
								<dd>{{ approverName(stage) }}</dd>
							</template>
							<template v-if="stage.comentario">
								<dt>{{ t('empleados', 'Comment') }}</dt>
								<dd>{{ stage.comentario }}</dd>
							</template>
						</dl>
					</div>
				</li>
			</ol>

			<NcNoteCard v-else type="info">
				{{ t('empleados', 'No approval stages are available for this request.') }}
			</NcNoteCard>

			<div v-if="terminalEvent" :class="['approval-flow__terminal', `is-${terminalEvent.type}`]">
				<strong>{{ terminalEvent.label }}</strong>
				<span>{{ terminalEvent.actor }} · {{ formatDateTime(terminalEvent.created_at) }}</span>
				<p v-if="terminalEvent.comentario">
					{{ terminalEvent.comentario }}
				</p>
			</div>

			<div v-if="showActions && (canApprove || canReject)" class="approval-flow__actions">
				<NcButton v-if="canReject"
					type="error"
					:disabled="processing"
					@click="$emit('reject')">
					<template #icon>
						<NcLoadingIcon v-if="processing" :size="18" />
						<CloseCircleOutline v-else :size="18" />
					</template>
					{{ t('empleados', 'Reject') }}
				</NcButton>

				<NcButton v-if="canApprove"
					type="primary"
					:disabled="processing"
					@click="$emit('approve')">
					<template #icon>
						<NcLoadingIcon v-if="processing" :size="18" />
						<CheckCircleOutline v-else :size="18" />
					</template>
					{{ t('empleados', 'Approve') }}
				</NcButton>
			</div>
		</template>
	</section>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcLoadingIcon, NcNoteCard } from '@nextcloud/vue'
import AccountClockOutline from 'vue-material-design-icons/AccountClockOutline.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import ClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import CloseCircleOutline from 'vue-material-design-icons/CloseCircleOutline.vue'

import {
	canApprovePurchaseFlow,
	canRejectPurchaseFlow,
} from '../../../utils/compraApprovalFlow.js'

export default {
	name: 'CompraApprovalFlow',

	components: {
		AccountClockOutline,
		Cancel,
		CheckCircleOutline,
		ClockOutline,
		CloseCircleOutline,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
	},

	props: {
		flow: {
			type: Object,
			default: null,
		},
		history: {
			type: Array,
			default: () => [],
		},
		loading: {
			type: Boolean,
			default: false,
		},
		error: {
			type: String,
			default: '',
		},
		processing: {
			type: Boolean,
			default: false,
		},
		showActions: {
			type: Boolean,
			default: true,
		},
	},

	computed: {
		canApprove() {
			return canApprovePurchaseFlow(this.flow)
		},

		canReject() {
			return canRejectPurchaseFlow(this.flow)
		},

		generalStatus() {
			const states = {
				borrador: { key: 'draft', label: t('empleados', 'Draft') },
				pendiente_autorizacion: { key: 'current', label: t('empleados', 'Pending approval') },
				autorizada: { key: 'completed', label: t('empleados', 'Approved') },
				rechazada: { key: 'rejected', label: t('empleados', 'Rejected') },
				cancelada: { key: 'cancelled', label: t('empleados', 'Cancelled') },
			}

			return states[this.flow?.estado] || { key: 'pending', label: t('empleados', 'Pending') }
		},

		stages() {
			const currentId = Number(this.flow?.aprobador_actual?.id_autorizacion || 0)

			return (this.flow?.etapas || []).map((stage) => {
				let visualState = 'pending'
				if (stage.estado === 'aprobada') {
					visualState = 'completed'
				} else if (stage.estado === 'rechazada') {
					visualState = 'rejected'
				} else if (stage.estado === 'cancelada') {
					visualState = 'cancelled'
				} else if (Number(stage.id_autorizacion) === currentId) {
					visualState = 'current'
				}

				return { ...stage, visualState }
			})
		},

		terminalEvent() {
			const event = [...this.history].reverse().find((item) => {
				return ['rechazada', 'cancelada'].includes(String(item?.accion || ''))
			})

			if (!event) {
				return null
			}

			const metadata = this.parseMetadata(event.metadata)
			const type = event.accion === 'rechazada' ? 'rejected' : 'cancelled'
			return {
				...event,
				type,
				label: type === 'rejected'
					? t('empleados', 'Request rejected')
					: t('empleados', 'Request cancelled'),
				actor: metadata.actor_nombre || event.created_by || t('empleados', 'Unknown actor'),
			}
		},
	},

	methods: {
		t,

		approverName(stage) {
			return stage?.autorizador_nombre
				|| stage?.id_autorizador
				|| t('empleados', 'Unassigned')
		},

		roleLabel(role) {
			const roles = {
				gerente: t('empleados', 'Manager'),
				socio: t('empleados', 'Partner'),
				gerente_socio: t('empleados', 'Manager and partner'),
			}
			return roles[role] || role || t('empleados', 'Approver')
		},

		stageStateLabel(state) {
			const labels = {
				completed: t('empleados', 'Completed'),
				current: t('empleados', 'Current stage'),
				pending: t('empleados', 'Pending'),
				rejected: t('empleados', 'Rejected'),
				cancelled: t('empleados', 'Cancelled'),
			}
			return labels[state] || state
		},

		formatDateTime(value) {
			if (!value) return '-'
			const date = new Date(String(value).replace(' ', 'T'))
			if (Number.isNaN(date.getTime())) return String(value)

			return new Intl.DateTimeFormat('es-MX', {
				dateStyle: 'medium',
				timeStyle: 'short',
			}).format(date)
		},

		parseMetadata(value) {
			if (value && typeof value === 'object') return value
			if (!value) return {}
			try {
				return JSON.parse(value)
			} catch (error) {
				return {}
			}
		},
	},
}
</script>

<style scoped>
.approval-flow {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.approval-flow__header,
.approval-stage__heading,
.approval-flow__actions {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.approval-flow__eyebrow {
	margin: 0 0 3px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.approval-flow__header h3 {
	margin: 0;
}

.approval-flow__status,
.approval-stage__badge {
	display: inline-flex;
	align-items: center;
	min-height: 26px;
	padding: 3px 10px;
	border-radius: 999px;
	background: var(--color-background-dark);
	font-size: 12px;
	font-weight: 700;
}

.approval-flow__status.is-current,
.approval-stage.is-current .approval-stage__badge {
	background: var(--color-warning-hover);
	color: var(--color-warning-text);
}

.approval-flow__status.is-completed,
.approval-stage.is-completed .approval-stage__badge {
	background: var(--color-success-hover);
	color: var(--color-success-text);
}

.approval-flow__status.is-rejected,
.approval-stage.is-rejected .approval-stage__badge {
	background: var(--color-error-hover);
	color: var(--color-error-text);
}

.approval-flow__loading {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 10px;
	min-height: 90px;
	color: var(--color-text-maxcontrast);
}

.approval-flow__current {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 14px;
	border-left: 4px solid var(--color-primary-element);
	border-radius: var(--border-radius);
	background: var(--color-primary-element-light);
}

.approval-flow__current div {
	display: flex;
	flex-direction: column;
}

.approval-flow__current span,
.approval-flow__current small {
	color: var(--color-text-maxcontrast);
}

.approval-flow__timeline {
	margin: 0;
	padding: 0;
	list-style: none;
}

.approval-stage {
	position: relative;
	display: grid;
	grid-template-columns: 38px minmax(0, 1fr);
	gap: 10px;
	padding-bottom: 16px;
}

.approval-stage:not(:last-child)::before {
	position: absolute;
	top: 28px;
	bottom: 0;
	left: 17px;
	width: 2px;
	background: var(--color-border);
	content: '';
}

.approval-stage__marker {
	z-index: 1;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 36px;
	border: 2px solid var(--color-border);
	border-radius: 50%;
	background: var(--color-main-background);
	color: var(--color-text-maxcontrast);
}

.approval-stage.is-current .approval-stage__marker {
	border-color: var(--color-primary-element);
	color: var(--color-primary-element);
}

.approval-stage.is-completed .approval-stage__marker {
	border-color: var(--color-success);
	color: var(--color-success);
}

.approval-stage.is-rejected .approval-stage__marker {
	border-color: var(--color-error);
	color: var(--color-error);
}

.approval-stage__content {
	min-width: 0;
	padding: 8px 12px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}

.approval-flow__terminal span {
	display: block;
	margin-top: 3px;
	color: var(--color-text-maxcontrast);
}

.approval-flow__terminal p {
	margin: 7px 0 0;
	white-space: pre-wrap;
}

.approval-stage__heading > div {
	display: flex;
	min-width: 0;
	flex-direction: column;
}

.approval-stage__heading span:not(.approval-stage__badge) {
	color: var(--color-text-maxcontrast);
	overflow-wrap: anywhere;
}

.approval-stage__decision {
	display: grid;
	grid-template-columns: minmax(110px, auto) minmax(0, 1fr);
	gap: 5px 12px;
	margin: 12px 0 0;
	padding-top: 10px;
	border-top: 1px solid var(--color-border);
}

.approval-stage__decision dt {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
}

.approval-stage__decision dd {
	margin: 0;
	overflow-wrap: anywhere;
}

.approval-flow__terminal {
	padding: 12px 14px;
	border-left: 4px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-background-hover);
}

.approval-flow__terminal.is-rejected {
	border-left-color: var(--color-error);
}

.approval-flow__actions {
	justify-content: flex-end;
	padding-top: 4px;
}

@media (max-width: 600px) {
	.approval-flow__header,
	.approval-stage__heading {
		align-items: flex-start;
		flex-direction: column;
	}

	.approval-stage__decision {
		grid-template-columns: 1fr;
	}
}
</style>
