<template>
	<div class="purchase-detail-view">
		<header class="purchase-detail-header">
			<div class="purchase-title-block">
				<div class="purchase-icon" aria-hidden="true">
					<CartOutline :size="30" />
				</div>
				<div>
					<p>{{ t('empleados', 'Purchase request') }}</p>
					<h2>{{ solicitud.folio || t('empleados', 'No folio') }}</h2>
					<span :class="['status-chip', `is-${solicitud.estado || 'unknown'}`]">
						{{ statusLabel(solicitud.estado) }}
					</span>
				</div>
			</div>

			<div class="purchase-header-actions">
				<NcButton v-if="actions.exportPdf" @click="$emit('export-pdf')">
					<template #icon>
						<FilePdfBox :size="19" />
					</template>
					{{ t('empleados', 'Export PDF') }}
				</NcButton>

				<NcButton v-if="actions.send"
					type="primary"
					:disabled="processing"
					@click="$emit('send')">
					<template #icon>
						<SendOutline :size="19" />
					</template>
					{{ t('empleados', 'Send for approval') }}
				</NcButton>

				<NcButton v-if="actions.reject"
					type="error"
					:disabled="processing"
					@click="$emit('reject')">
					{{ t('empleados', 'Reject') }}
				</NcButton>

				<NcButton v-if="actions.approve"
					type="primary"
					:disabled="processing"
					@click="$emit('approve')">
					{{ t('empleados', 'Approve') }}
				</NcButton>

				<NcActions v-if="hasSecondaryActions" :force-menu="true" :aria-label="t('empleados', 'More actions')">
					<NcActionButton v-if="actions.edit" @click="$emit('edit')">
						<template #icon>
							<PencilOutline :size="19" />
						</template>
						{{ t('empleados', 'Edit') }}
					</NcActionButton>
					<NcActionButton v-if="actions.uploadSigned" @click="$emit('upload-signed')">
						<template #icon>
							<UploadOutline :size="19" />
						</template>
						{{ t('empleados', 'Upload signed document') }}
					</NcActionButton>
					<NcActionButton v-if="actions.cancel" @click="$emit('cancel')">
						<template #icon>
							<Cancel :size="19" />
						</template>
						{{ t('empleados', 'Cancel request') }}
					</NcActionButton>
				</NcActions>

				<NcButton :aria-label="t('empleados', 'Close')" :title="t('empleados', 'Close')" @click="$emit('close')">
					<template #icon>
						<Close :size="20" />
					</template>
				</NcButton>
			</div>
		</header>

		<main class="purchase-detail-content">
			<section class="summary-grid" :aria-label="t('empleados', 'Purchase summary')">
				<div><span>{{ t('empleados', 'Subtotal') }}</span><strong>{{ money(solicitud.total_excl_iva) }}</strong></div>
				<div><span>{{ t('empleados', 'VAT') }}</span><strong>{{ money(solicitud.iva) }}</strong></div>
				<div class="summary-total">
					<span>{{ t('empleados', 'Total') }}</span><strong>{{ money(solicitud.total_incl_iva) }}</strong>
				</div>
				<div><span>{{ t('empleados', 'Priority') }}</span><strong>{{ valueOrDefault(solicitud.prioridad) }}</strong></div>
				<div><span>{{ t('empleados', 'Purchase use') }}</span><strong>{{ valueOrDefault(solicitud.uso_compra) }}</strong></div>
				<div><span>{{ t('empleados', 'Currency') }}</span><strong>{{ valueOrDefault(solicitud.moneda) }}</strong></div>
			</section>

			<section class="detail-section requester-section">
				<SectionHeading :title="t('empleados', 'Requester information')" />
				<div class="requester-card">
					<NcAvatar :user="requesterUid"
						:display-name="requesterName"
						:show-user-status="false"
						:size="52"
						disable-menu />
					<div class="requester-identity">
						<strong>{{ requesterName }}</strong>
						<small>{{ requesterUid || t('empleados', 'No user identifier') }}</small>
					</div>
					<div><span>{{ t('empleados', 'Department') }}</span><strong>{{ valueOrDefault(solicitud.solicitante_depto) }}</strong></div>
					<div><span>{{ t('empleados', 'Position') }}</span><strong>{{ valueOrDefault(solicitud.solicitante_cargo) }}</strong></div>
				</div>
			</section>

			<section class="detail-section">
				<SectionHeading :title="t('empleados', 'Purchase information')" />
				<dl class="information-grid">
					<div><dt>{{ t('empleados', 'Title') }}</dt><dd>{{ valueOrDefault(solicitud.titulo) }}</dd></div>
					<div><dt>{{ t('empleados', 'Purchase type') }}</dt><dd>{{ valueOrDefault(solicitud.tipo_compra) }}</dd></div>
					<div><dt>{{ t('empleados', 'Purchase use') }}</dt><dd>{{ valueOrDefault(solicitud.uso_compra) }}</dd></div>
					<div><dt>{{ t('empleados', 'Priority') }}</dt><dd>{{ valueOrDefault(solicitud.prioridad) }}</dd></div>
					<div><dt>{{ t('empleados', 'Currency') }}</dt><dd>{{ valueOrDefault(solicitud.moneda) }}</dd></div>
					<div><dt>{{ t('empleados', 'Required date') }}</dt><dd>{{ formatDate(solicitud.fecha_requerida) }}</dd></div>
					<div><dt>{{ t('empleados', 'Warranty') }}</dt><dd>{{ warrantyLabel }}</dd></div>
					<div class="span-full">
						<dt>{{ t('empleados', 'Information') }}</dt><dd>{{ valueOrDefault(solicitud.informacion) }}</dd>
					</div>
					<div class="span-full">
						<dt>{{ t('empleados', 'Reason') }}</dt><dd>{{ valueOrDefault(solicitud.motivo) }}</dd>
					</div>
				</dl>
			</section>

			<section class="detail-section">
				<SectionHeading :title="t('empleados', 'Requested items')" :subtitle="itemCountLabel" />
				<div class="table-scroll">
					<table class="items-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Description') }}</th><th>{{ t('empleados', 'Supplier') }}</th>
								<th>{{ t('empleados', 'Delivery') }}</th><th>{{ t('empleados', 'Brand / Model') }}</th>
								<th>{{ t('empleados', 'Quantity') }}</th><th>{{ t('empleados', 'Unit price') }}</th>
								<th>{{ t('empleados', 'VAT') }}</th><th>{{ t('empleados', 'Total') }}</th>
								<th>{{ t('empleados', 'Actions') }}</th>
							</tr>
						</thead>
						<tbody>
							<template v-for="(item, index) in detalles">
								<tr :key="`item-${item.id_detalle || index}`">
									<td><strong>{{ valueOrDefault(item.descripcion) }}</strong></td>
									<td>{{ valueOrDefault(item.proveedor_nombre) }}</td>
									<td>{{ valueOrDefault(item.entrega) }}</td>
									<td>{{ valueOrDefault(item.marca_modelo) }}</td>
									<td>{{ valueOrDefault(item.cantidad) }} {{ item.unidad || '' }}</td>
									<td>{{ money(item.precio_estimado) }}</td>
									<td>{{ money(item.iva) }}</td>
									<td>{{ money(item.total) }}</td>
									<td>
										<NcButton :aria-expanded="isItemExpanded(item, index)"
											:title="t('empleados', 'View item details')"
											:aria-label="t('empleados', 'View item details')"
											@click="toggleItem(item, index)">
											<template #icon>
												<ChevronDown :class="{ 'is-open': isItemExpanded(item, index) }" :size="20" />
											</template>
										</NcButton>
									</td>
								</tr>
								<tr v-if="isItemExpanded(item, index)" :key="`item-detail-${item.id_detalle || index}`" class="item-detail-row">
									<td colspan="9">
										<div class="item-extra-grid">
											<div><span>{{ t('empleados', 'Specifications') }}</span><p>{{ valueOrDefault(item.especificaciones) }}</p></div>
											<div><span>{{ t('empleados', 'Notes') }}</span><p>{{ valueOrDefault(item.notas) }}</p></div>
											<div><span>{{ t('empleados', 'Attention') }}</span><p>{{ valueOrDefault(item.atencion) }}</p></div>
											<div class="span-full">
												<span>{{ t('empleados', 'Related documents') }}</span>
												<p v-if="itemDocuments(item).length === 0">
													{{ t('empleados', 'No related documents') }}
												</p>
												<ul v-else>
													<li v-for="document in itemDocuments(item)" :key="document.file_id || document.nombre_archivo">
														{{ document.nombre_archivo }}
													</li>
												</ul>
											</div>
										</div>
									</td>
								</tr>
							</template>
						</tbody>
					</table>
				</div>
			</section>

			<section class="detail-section">
				<SectionHeading :title="t('empleados', 'Documents')" />
				<div class="document-groups">
					<DocumentGroup :title="t('empleados', 'Generated PDF')" :documents="generatedPdfDocuments" :empty-text="t('empleados', 'No generated PDF')">
						<template #actions="{ document }">
							<NcButton @click="$emit('export-pdf', document)">
								{{ t('empleados', 'View or download') }}
							</NcButton>
							<NcButton v-if="actions.savePdf" @click="$emit('save-pdf')">
								{{ t('empleados', 'Update saved PDF') }}
							</NcButton>
						</template>
					</DocumentGroup>
					<DocumentGroup :title="t('empleados', 'Quotations')" :documents="quotations" :empty-text="t('empleados', 'No quotations associated')" />
					<DocumentGroup :title="t('empleados', 'Signed document')" :documents="signedDocuments" :empty-text="t('empleados', 'No signed document')">
						<template #actions="{ document }">
							<NcButton @click="$emit('view-signed', document)">
								{{ t('empleados', 'View or download') }}
							</NcButton>
						</template>
					</DocumentGroup>
					<DocumentGroup :title="t('empleados', 'Other attachments')" :documents="attachments" :empty-text="t('empleados', 'No other attachments')" />
				</div>
			</section>

			<CompraApprovalFlow v-if="showApprovalFlow"
				:flow="flow"
				:history="history"
				:loading="flowLoading"
				:error="flowError"
				:processing="processing"
				:show-actions="false"
				@approve="$emit('approve')"
				@reject="$emit('reject')" />

			<section class="detail-section history-section">
				<div class="history-heading">
					<SectionHeading :title="t('empleados', 'History')" />
					<NcButton :disabled="historyLoading" @click="toggleHistory">
						{{ showHistory ? t('empleados', 'Hide history') : t('empleados', 'Show history') }}
					</NcButton>
				</div>
				<NcLoadingIcon v-if="historyLoading" :size="28" />
				<NcNoteCard v-else-if="historyError" type="error">
					{{ historyError }}
				</NcNoteCard>
				<template v-else-if="showHistory">
					<NcEmptyContent v-if="history.length === 0" :name="t('empleados', 'No history records found')" />
					<ol v-else class="history-list">
						<li v-for="event in history" :key="event.id_historial">
							<div><strong>{{ actionLabel(event.accion) }}</strong><span>{{ historyActor(event) }} · {{ formatDateTime(event.created_at) }}</span></div>
							<p v-if="event.estado_anterior || event.estado_nuevo">
								{{ statusLabel(event.estado_anterior) }} → {{ statusLabel(event.estado_nuevo) }}
							</p>
							<p v-if="event.comentario">
								{{ event.comentario }}
							</p>
							<a v-if="historyDocument(event)" href="#" @click.prevent="openHistoryDocument(event)">{{ historyDocument(event).name }}</a>
						</li>
					</ol>
					<NcButton v-if="history.length < historyTotal" :disabled="historyLoading" @click="loadHistory(true)">
						{{ t('empleados', 'Load more') }}
					</NcButton>
				</template>
			</section>
		</main>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcActionButton, NcActions, NcAvatar, NcButton, NcEmptyContent, NcLoadingIcon, NcNoteCard } from '@nextcloud/vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import CartOutline from 'vue-material-design-icons/CartOutline.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import Close from 'vue-material-design-icons/Close.vue'
import FilePdfBox from 'vue-material-design-icons/FilePdfBox.vue'
import PencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import SendOutline from 'vue-material-design-icons/SendOutline.vue'
import UploadOutline from 'vue-material-design-icons/UploadOutline.vue'

import { obtenerHistorialSolicitud } from '../../../services/comprasService.js'
import { getPurchaseDetailActions } from '../../../utils/compraDetailView.js'
import CompraApprovalFlow from './CompraApprovalFlow.vue'
import DocumentGroup from './DocumentGroup.vue'
import SectionHeading from './SectionHeading.vue'

export default {
	name: 'CompraSolicitudDetalle',
	components: { Cancel, CartOutline, ChevronDown, Close, CompraApprovalFlow, DocumentGroup, FilePdfBox, NcActionButton, NcActions, NcAvatar, NcButton, NcEmptyContent, NcLoadingIcon, NcNoteCard, PencilOutline, SectionHeading, SendOutline, UploadOutline },
	props: {
		detail: { type: Object, required: true }, flow: { type: Object, default: null }, flowLoading: { type: Boolean, default: false }, flowError: { type: String, default: '' }, processing: { type: Boolean, default: false }, permissions: { type: Object, default: () => ({}) }, currentUserId: { type: String, default: '' },
	},
	data() { return { expandedItems: {}, showHistory: false, history: [], historyTotal: 0, historyLoading: false, historyError: '', historyPageSize: 10 } },
	computed: {
		solicitud() { return this.detail?.solicitud || {} },
		detalles() { return this.detail?.detalles || [] },
		actions() { return getPurchaseDetailActions({ solicitud: this.solicitud, permissions: this.permissions, flow: this.flow, currentUserId: this.currentUserId }) },
		hasSecondaryActions() { return this.actions.edit || this.actions.uploadSigned || this.actions.cancel },
		requesterUid() { return String(this.solicitud.id_user || '') },
		requesterName() { return this.solicitud.solicitante_nombre || this.requesterUid || t('empleados', 'Not specified') },
		warrantyLabel() { return Number(this.solicitud.garantia || 0) === 1 ? t('empleados', 'Yes') : t('empleados', 'No') },
		itemCountLabel() { return t('empleados', '{count} item(s)', { count: this.detalles.length }) },
		showApprovalFlow() { return ['pendiente_autorizacion', 'autorizada', 'rechazada', 'cancelada'].includes(String(this.solicitud.estado || '')) },
		generatedPdfDocuments() { return this.solicitud.pdf_file_id ? [{ file_id: this.solicitud.pdf_file_id, name: this.solicitud.pdf_nombre, type: 'PDF', date: this.solicitud.pdf_generado_at, user: this.solicitud.updated_by }] : [] },
		signedDocuments() { return this.solicitud.firmado_file_id ? [{ file_id: this.solicitud.firmado_file_id, name: this.solicitud.firmado_nombre, type: this.solicitud.firmado_mime, date: this.solicitud.firmado_subido_at, user: this.solicitud.firmado_subido_by }] : [] },
		quotations() { return this.detail?.cotizaciones || [] },
		attachments() { return this.detail?.adjuntos || [] },
	},
	methods: {
		t,
		valueOrDefault(value) { return value === null || value === undefined || String(value).trim() === '' ? t('empleados', 'Not specified') : value },
		money(value) { return new Intl.NumberFormat('es-MX', { style: 'currency', currency: this.solicitud.moneda || 'MXN' }).format(Number(value || 0)) },
		formatDate(value) { if (!value) return t('empleados', 'Not specified'); const date = new Date(`${String(value).slice(0, 10)}T00:00:00`); return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium' }).format(date) },
		formatDateTime(value) { if (!value) return t('empleados', 'Not specified'); const date = new Date(String(value).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(date) },
		statusLabel(state) { return ({ borrador: t('empleados', 'Draft'), pendiente_autorizacion: t('empleados', 'Pending approval'), autorizada: t('empleados', 'Approved'), rechazada: t('empleados', 'Rejected'), cancelada: t('empleados', 'Cancelled') })[state] || this.valueOrDefault(state) },
		itemKey(item, index) { return String(item.id_detalle || index) },
		isItemExpanded(item, index) { return Boolean(this.expandedItems[this.itemKey(item, index)]) },
		toggleItem(item, index) { const key = this.itemKey(item, index); this.$set(this.expandedItems, key, !this.expandedItems[key]) },
		itemDocuments(item) { return item.documentos || item.adjuntos || [] },
		async toggleHistory() { this.showHistory = !this.showHistory; if (this.showHistory && this.history.length === 0) await this.loadHistory(false) },
		async loadHistory(append) { this.historyLoading = true; this.historyError = ''; try { const offset = append ? this.history.length : 0; const response = await obtenerHistorialSolicitud(this.solicitud.id_solicitud, { limit: this.historyPageSize, offset }); const payload = response?.ocs?.data || response; if (!payload.success) throw new Error(payload.message || t('empleados', 'Could not load history.')); const result = payload.data || {}; this.history = append ? [...this.history, ...(result.items || [])] : (result.items || []); this.historyTotal = Number(result.pagination?.total || 0) } catch (error) { this.historyError = error?.response?.data?.ocs?.data?.message || error?.response?.data?.message || error?.message || t('empleados', 'Could not load history.') } finally { this.historyLoading = false } },
		parseMetadata(value) { if (value && typeof value === 'object') return value; try { return value ? JSON.parse(value) : {} } catch (error) { return {} } },
		historyActor(event) { const metadata = this.parseMetadata(event.metadata); return metadata.actor_nombre || event.created_by || t('empleados', 'Unknown actor') },
		historyDocument(event) { const metadata = this.parseMetadata(event.metadata); const name = metadata.file_name || metadata.nombre_archivo; return name ? { name, fileId: metadata.file_id } : null },
		openHistoryDocument(event) { const document = this.historyDocument(event); if (!document) return; if (event.accion === 'documento_firmado_subido') this.$emit('view-signed', document); else this.$emit('export-pdf', document) },
		actionLabel(action) { return String(action || '').replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase()) },
	},
}
</script>

<style scoped>
/* stylelint-disable no-descending-specificity */
.purchase-detail-view { display: flex; max-height: calc(100vh - 72px); flex-direction: column; background: var(--color-main-background); }
.purchase-detail-header { position: sticky; z-index: 3; top: 0; display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 18px 24px; border-bottom: 1px solid var(--color-border); background: var(--color-main-background); }
.purchase-title-block, .purchase-header-actions, .requester-card, .history-heading { display: flex; align-items: center; gap: 12px; }
.purchase-title-block p, .purchase-title-block h2 { margin: 0; }.purchase-title-block p { color: var(--color-text-maxcontrast); font-size: 12px; font-weight: 700; text-transform: uppercase; }
.purchase-icon { display: grid; width: 52px; height: 52px; place-items: center; border-radius: 14px; background: var(--color-primary-element-light); color: var(--color-primary-element); }
.purchase-header-actions { justify-content: flex-end; flex-wrap: wrap; }
.purchase-detail-content { padding: 22px 24px 30px; overflow-y: auto; }
.status-chip { display: inline-flex; margin-top: 5px; padding: 3px 10px; border-radius: 999px; background: var(--color-background-dark); font-size: 12px; font-weight: 700; }.status-chip.is-autorizada { background: var(--color-success-hover); color: var(--color-success-text); }.status-chip.is-rechazada { background: var(--color-error-hover); color: var(--color-error-text); }.status-chip.is-pendiente_autorizacion { background: var(--color-warning-hover); }
.summary-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; }.summary-grid > div { padding: 12px; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); background: var(--color-background-hover); }.summary-grid span, .requester-card span, .item-extra-grid span { display: block; color: var(--color-text-maxcontrast); font-size: 11px; font-weight: 700; text-transform: uppercase; }.summary-grid strong { display: block; margin-top: 5px; overflow-wrap: anywhere; }.summary-total { border-color: var(--color-primary-element) !important; }
.detail-section { margin-top: 22px; }.requester-card { display: grid; grid-template-columns: auto minmax(180px, 1fr) repeat(2, minmax(140px, 0.7fr)); padding: 16px; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); }.requester-card > div:not(.requester-identity) { padding-left: 16px; border-left: 1px solid var(--color-border); }.requester-identity { display: flex; min-width: 0; flex-direction: column; }.requester-identity small { color: var(--color-text-maxcontrast); overflow-wrap: anywhere; }
.information-grid, .item-extra-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 0; }.information-grid > div, .item-extra-grid > div { padding: 13px; border-radius: var(--border-radius); background: var(--color-background-hover); }.information-grid dt { color: var(--color-text-maxcontrast); font-size: 11px; font-weight: 700; text-transform: uppercase; }.information-grid dd { margin: 5px 0 0; white-space: pre-wrap; overflow-wrap: anywhere; }.span-full { grid-column: 1 / -1; }
.table-scroll { width: 100%; overflow-x: auto; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); }.items-table { width: 100%; border-collapse: collapse; }.items-table th, .items-table td { padding: 10px 11px; border-bottom: 1px solid var(--color-border); text-align: left; vertical-align: middle; }.items-table th { background: var(--color-background-hover); font-size: 11px; text-transform: uppercase; white-space: nowrap; }.item-detail-row td { padding: 14px; background: var(--color-background-hover); }.item-extra-grid p { margin: 5px 0 0; white-space: pre-wrap; }.is-open { transform: rotate(180deg); }
.document-groups { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.history-heading { justify-content: space-between; }.history-list { margin: 12px 0; padding: 0; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); list-style: none; }.history-list li { padding: 13px 15px; border-bottom: 1px solid var(--color-border); }.history-list li:last-child { border-bottom: none; }.history-list li > div { display: flex; justify-content: space-between; gap: 12px; }.history-list span { color: var(--color-text-maxcontrast); }.history-list p { margin: 5px 0 0; }
@media (max-width: 900px) { .purchase-detail-header { align-items: flex-start; flex-direction: column; }.purchase-header-actions { justify-content: flex-start; }.summary-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }.requester-card { grid-template-columns: auto minmax(0, 1fr); }.requester-card > div:not(.requester-identity) { grid-column: 1 / -1; padding: 8px 0 0; border-top: 1px solid var(--color-border); border-left: 0; }.information-grid, .item-extra-grid, .document-groups { grid-template-columns: 1fr; } }
@media (max-width: 560px) { .purchase-detail-header, .purchase-detail-content { padding-right: 14px; padding-left: 14px; }.summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.history-list li > div { flex-direction: column; gap: 3px; } }
</style>
