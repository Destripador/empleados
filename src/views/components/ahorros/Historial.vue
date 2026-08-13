<template>
	<div v-if="loading">
		<div class="center">
			<NcLoadingIcon :size="64" appearance="dark" name="Loading on light background" />
		</div>
	</div>

	<div v-else class="history-page">
		<section class="history-header">
			<div>
				<p class="section-label">
					{{ t('empleados', 'Savings loans') }}
				</p>
				<h2>
					<Archive :size="22" decorative class="icon" />
					<span>{{ t('ahorrosgossler', 'History') }}</span>
				</h2>
				<p>{{ t('empleados', 'Review your requested loans and their current approval status.') }}</p>
			</div>
			<div class="history-total">
				<span>{{ t('empleados', 'Requests') }}</span>
				<strong>{{ historial.length }}</strong>
			</div>
		</section>

		<section v-if="historial.length > 0" class="history-list">
			<article
				v-for="item in historial"
				:key="item.id"
				class="history-card">
				<div class="history-card__main">
					<span class="status-badge" :class="statusClass(item)">
						<CheckboxBlankCircle :size="10" />
						{{ statusLabel(item) }}
					</span>
					<strong>{{ formatMoney(item.cantidad_solicitada) }}</strong>
					<small>{{ formatDate(item.fecha_solicitud) }}</small>
				</div>

				<p v-if="item.nota" class="history-note">
					{{ item.nota }}
				</p>
				<p v-else class="history-note muted">
					{{ t('empleados', 'No note provided.') }}
				</p>
			</article>
		</section>

		<section v-else class="empty-state">
			<h2>{{ t('ahorrosgossler', 'No movements yet') }}</h2>
			<p>{{ t('empleados', 'Your loan requests will appear here once submitted.') }}</p>
		</section>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import Archive from 'vue-material-design-icons/Archive.vue'
import CheckboxBlankCircle from 'vue-material-design-icons/CheckboxBlankCircle.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { NcLoadingIcon } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'Historial',
	components: {
		NcLoadingIcon,
		Archive,
		CheckboxBlankCircle,
	},
	props: {
		id: { type: Number, required: true },
	},
	data() {
		return {
			loading: true,
			historial: [],
		}
	},
	mounted() {
		this.gethistorial()
	},
	methods: {
		t, // expone t al template

		formatMoney(value) {
			return Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value) || 0)
		},

		statusLabel(item) {
			return Number(item.estado) === 0
				? t('ahorrosgossler', 'Rejected / pending')
				: t('ahorrosgossler', 'Approved')
		},

		statusClass(item) {
			return Number(item.estado) === 0 ? 'status-pending' : 'status-approved'
		},

		async gethistorial() {
			try {
				const response = await axios.get(generateUrl('apps/empleados/getHistorial/' + this.id))
				const data = response?.data?.ocs?.data || []
				this.historial = Array.isArray(data) ? data : []
			} catch (e) {
				showError(t('ahorrosgossler', 'Could not fetch your information'))
			} finally {
				this.loading = false
			}
		},

		formatDate(val) {
			// Si viene ya formateada, la mostramos; si es ISO, la convertimos.
			if (!val) return ''
			// intenta parsear fecha conocida
			const d = new Date(val)
			if (!isNaN(d.getTime())) {
				// Muestra fecha y hora locales (MX)
				return new Intl.DateTimeFormat('es-MX', {
					year: 'numeric',
					month: '2-digit',
					day: '2-digit',
					hour: '2-digit',
					minute: '2-digit',
				}).format(d)
			}
			// si no fue parseable, regresa como viene
			return val
		},
	},
}
</script>

<style scoped>
.center { margin: auto; width: 50%; padding: 10px; }

.history-page {
	display: grid;
	gap: 14px;
	padding: 0 20px 24px;
}

.history-header,
.history-card,
.empty-state {
	border: 1px solid var(--color-border);
	border-radius: 8px;
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
}

.history-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 18px;
}

.section-label {
	margin: 0 0 4px;
	color: var(--color-primary-element);
	font-size: 12px;
	font-weight: 700;
	letter-spacing: .04em;
	text-transform: uppercase;
}

.history-header h2 {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0;
	color: var(--color-main-text);
	font-size: 22px;
}

.history-header p {
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
}

.history-total {
	display: grid;
	justify-items: end;
	gap: 4px;
	min-width: 90px;
}

.history-total span {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.history-total strong {
	color: var(--color-main-text);
	font-size: 28px;
}

.history-list {
	display: grid;
	gap: 10px;
}

.history-card {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 16px;
}

.history-card__main {
	display: grid;
	gap: 5px;
	min-width: 190px;
}

.history-card__main strong {
	color: var(--color-main-text);
	font-size: 22px;
}

.history-card__main small,
.history-note {
	color: var(--color-text-maxcontrast);
}

.history-note {
	max-width: 620px;
	margin: 0;
	line-height: 1.4;
}

.history-note.muted {
	font-style: italic;
}

.status-badge {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	width: fit-content;
	padding: 4px 9px;
	border-radius: 999px;
	font-size: 12px;
	font-weight: 700;
}

.status-pending {
	background: rgba(199, 130, 0, .14);
	color: #9f6500;
}

.status-approved {
	background: rgba(16, 133, 72, .12);
	color: #108548;
}

.empty-state {
	padding: 28px;
	text-align: center;
}

.empty-state h2 {
	margin: 0;
}

.empty-state p {
	margin: 8px 0 0;
	color: var(--color-text-maxcontrast);
}

@media (max-width: 700px) {
	.history-page {
		padding: 0 12px 18px;
	}

	.history-header,
	.history-card {
		align-items: flex-start;
		flex-direction: column;
	}

	.history-total {
		justify-items: start;
	}
}
</style>
