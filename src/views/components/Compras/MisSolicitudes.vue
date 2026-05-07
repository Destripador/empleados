<template>
	<NcAppContent :name="t('empleados', 'Purchases')">
		<div class="compras-page">
			<div class="compras-header">
				<div class="header-title">
					<p class="section-label">
						{{ t('empleados', 'Purchases module') }}
					</p>
					<h2>{{ t('empleados', 'Purchases') }}</h2>
					<p class="section-description">
						{{ t('empleados', 'Manage purchase requests, approvals and tracking from one place.') }}
					</p>
				</div>

				<div class="header-actions">
					<NcButton @click="cargarSolicitudes">
						{{ t('empleados', 'Refresh') }}
					</NcButton>

					<NcButton type="primary" @click="toggleForm">
						{{ showForm ? t('empleados', 'Close') : t('empleados', 'New request') }}
					</NcButton>
				</div>
			</div>

			<div class="stats-grid">
				<div class="stat-card">
					<div class="stat-icon">
						<CartOutline :size="22" />
					</div>
					<div>
						<span>{{ t('empleados', 'Total requests') }}</span>
						<strong>{{ solicitudes.length }}</strong>
					</div>
				</div>

				<div class="stat-card">
					<div class="stat-icon">
						<FileChartOutline :size="22" />
					</div>
					<div>
						<span>{{ t('empleados', 'Pending approval') }}</span>
						<strong>{{ totalPendientes }}</strong>
					</div>
				</div>

				<div class="stat-card">
					<div class="stat-icon">
						<FileChartOutline :size="22" />
					</div>
					<div>
						<span>{{ t('empleados', 'Estimated amount') }}</span>
						<strong>{{ formatMoney(totalListado) }}</strong>
					</div>
				</div>
			</div>

			<NcModal
				v-if="showForm"
				class="purchase-request-modal"
				size="large"
				:name="t('empleados', 'New purchase request')"
				@close="closeRequestModal">
				<div class="purchase-modal">
					<div class="modal-header">
						<p class="section-label">
							{{ t('empleados', 'Purchases module') }}
						</p>
						<h2>{{ t('empleados', 'New purchase request') }}</h2>
						<p>
							{{ t('empleados', 'Register the purchase request information and add at least one concept.') }}
						</p>
					</div>

					<div class="form-grid">
						<NcTextField
							required
							class="span-2"
							:value.sync="form.titulo"
							:label="t('empleados', 'Title')" />

						<NcSelect
							v-model="selectedPriority"
							:input-label="t('empleados', 'Priority')"
							:options="priorityOptions"
							:clearable="false" />

						<NcSelect
							v-model="selectedCurrency"
							:input-label="t('empleados', 'Currency')"
							:options="currencyOptions"
							:clearable="false" />

						<label class="native-field">
							<span>{{ t('empleados', 'Required date') }}</span>
							<input v-model="form.fecha_requerida" type="date">
						</label>

						<NcTextArea
							class="span-2"
							resize="vertical"
							:value.sync="form.descripcion"
							:label="t('empleados', 'Description')" />

						<NcTextArea
							class="span-2"
							resize="vertical"
							:value.sync="form.justificacion"
							:label="t('empleados', 'Justification')" />
					</div>

					<div class="modal-section-head">
						<div>
							<p class="section-label">
								{{ t('empleados', 'Concepts') }}
							</p>
							<h3>{{ t('empleados', 'Requested products or services') }}</h3>
						</div>

						<NcButton @click="addDetalle">
							{{ t('empleados', 'Add concept') }}
						</NcButton>
					</div>

					<div class="concepts-list">
						<div
							v-for="(detalle, index) in form.detalles"
							:key="index"
							class="concept-card">
							<div class="concept-number">
								{{ index + 1 }}
							</div>

							<div class="concept-fields">
								<input
									v-model="detalle.descripcion"
									type="text"
									:placeholder="t('empleados', 'Description')">

								<input
									v-model.number="detalle.cantidad"
									type="number"
									min="1"
									step="1"
									:placeholder="t('empleados', 'Quantity')">

								<input
									v-model="detalle.unidad"
									type="text"
									:placeholder="t('empleados', 'Unit')">

								<input
									v-model.number="detalle.precio_estimado"
									type="number"
									min="0"
									step="0.01"
									:placeholder="t('empleados', 'Estimated price')">

								<NcButton
									:disabled="form.detalles.length === 1"
									@click="removeDetalle(index)">
									{{ t('empleados', 'Remove') }}
								</NcButton>
							</div>
						</div>
					</div>

					<NcNoteCard type="info" class="purchase-total-card">
						<div class="purchase-total">
							<span>{{ t('empleados', 'Estimated total') }}</span>
							<strong>{{ formatMoney(totalEstimado) }}</strong>
						</div>
					</NcNoteCard>

					<div class="modal-actions">
						<NcButton @click="closeRequestModal">
							{{ t('empleados', 'Cancel') }}
						</NcButton>

						<NcButton
							type="primary"
							:disabled="loading || !isFormValid"
							@click="crear">
							{{ loading ? t('empleados', 'Saving...') : t('empleados', 'Save request') }}
						</NcButton>
					</div>
				</div>
			</NcModal>

			<section class="panel-card">
				<div class="panel-header">
					<div>
						<p class="section-label">
							{{ t('empleados', 'Tracking') }}
						</p>
						<h3>{{ t('empleados', 'My requests') }}</h3>
						<p>{{ t('empleados', 'Review the status of your purchase requests.') }}</p>
					</div>

					<div class="filters">
						<NcCheckboxRadioSwitch
							:checked="verTodas"
							type="switch"
							@update:checked="onToggleVerTodas">
							{{ t('empleados', 'Show all') }}
						</NcCheckboxRadioSwitch>
					</div>
				</div>

				<div v-if="loading" class="empty-state">
					{{ t('empleados', 'Loading...') }}
				</div>

				<NcEmptyContent
					v-else-if="solicitudes.length === 0"
					:name="t('empleados', 'No purchase requests')"
					:description="t('empleados', 'Create a new request to start the purchase workflow.')">
					<template #icon>
						<CartOutline />
					</template>
				</NcEmptyContent>

				<div v-else class="table-scroll">
					<table class="compras-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Folio') }}</th>
								<th>{{ t('empleados', 'Title') }}</th>
								<th>{{ t('empleados', 'Requester') }}</th>
								<th>{{ t('empleados', 'Amount') }}</th>
								<th>{{ t('empleados', 'Status') }}</th>
								<th>{{ t('empleados', 'Date') }}</th>
								<th>{{ t('empleados', 'Actions') }}</th>
							</tr>
						</thead>

						<tbody>
							<tr v-for="item in solicitudes" :key="item.id_solicitud">
								<td><strong>{{ item.folio }}</strong></td>
								<td>{{ item.titulo }}</td>
								<td>{{ item.id_user }}</td>
								<td>{{ formatMoney(item.monto_estimado) }}</td>
								<td>
									<span :class="['badge', `estado-${item.estado}`]">
										{{ formatEstado(item.estado) }}
									</span>
								</td>
								<td>{{ item.created_at }}</td>
								<td>
									<div class="row-actions">
										<NcButton @click="verDetalle(item.id_solicitud)">
											{{ t('empleados', 'View') }}
										</NcButton>

										<NcButton
											v-if="item.estado === 'borrador'"
											@click="enviar(item.id_solicitud)">
											{{ t('empleados', 'Send') }}
										</NcButton>

										<NcButton
											v-if="item.estado === 'pendiente_autorizacion'"
											type="primary"
											@click="autorizar(item.id_solicitud)">
											{{ t('empleados', 'Approve') }}
										</NcButton>

										<NcButton
											v-if="item.estado === 'pendiente_autorizacion'"
											@click="rechazar(item.id_solicitud)">
											{{ t('empleados', 'Reject') }}
										</NcButton>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>

			<section v-if="detalle" class="panel-card detail-panel">
				<div class="details-header">
					<div class="details-icon">
						<CartOutline :size="30" />
					</div>

					<div class="details-title">
						<p class="eyebrow">
							{{ detalle.solicitud.folio }}
						</p>
						<h2>{{ detalle.solicitud.titulo }}</h2>
						<p>{{ detalle.solicitud.descripcion || t('empleados', 'No description available.') }}</p>
					</div>

					<div class="details-actions">
						<NcButton @click="detalle = null">
							{{ t('empleados', 'Close') }}
						</NcButton>
					</div>
				</div>

				<div class="details-grid">
					<div class="detail-card">
						<span>{{ t('empleados', 'Status') }}</span>
						<strong>
							<span :class="['badge', `estado-${detalle.solicitud.estado}`]">
								{{ formatEstado(detalle.solicitud.estado) }}
							</span>
						</strong>
					</div>

					<div class="detail-card">
						<span>{{ t('empleados', 'Estimated amount') }}</span>
						<strong>{{ formatMoney(detalle.solicitud.monto_estimado) }}</strong>
					</div>

					<div class="detail-card">
						<span>{{ t('empleados', 'Priority') }}</span>
						<strong>{{ detalle.solicitud.prioridad }}</strong>
					</div>

					<div class="detail-card">
						<span>{{ t('empleados', 'Requester') }}</span>
						<strong>{{ detalle.solicitud.id_user }}</strong>
					</div>
				</div>

				<div class="subsection">
					<div class="section-head">
						<div>
							<p class="section-label">
								{{ t('empleados', 'Items') }}
							</p>
							<h3>{{ t('empleados', 'Requested concepts') }}</h3>
						</div>
					</div>

					<div class="table-scroll">
						<table class="compras-table">
							<thead>
								<tr>
									<th>{{ t('empleados', 'Description') }}</th>
									<th>{{ t('empleados', 'Quantity') }}</th>
									<th>{{ t('empleados', 'Unit') }}</th>
									<th>{{ t('empleados', 'Price') }}</th>
									<th>{{ t('empleados', 'Subtotal') }}</th>
								</tr>
							</thead>

							<tbody>
								<tr v-for="concepto in detalle.detalles" :key="concepto.id_detalle">
									<td>{{ concepto.descripcion }}</td>
									<td>{{ concepto.cantidad }}</td>
									<td>{{ concepto.unidad }}</td>
									<td>{{ formatMoney(concepto.precio_estimado) }}</td>
									<td>{{ formatMoney(concepto.subtotal) }}</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<div class="subsection">
					<div class="section-head">
						<div>
							<p class="section-label">
								{{ t('empleados', 'History') }}
							</p>
							<h3>{{ t('empleados', 'Request activity') }}</h3>
						</div>
					</div>

					<ul class="historial-list">
						<li v-for="evento in detalle.historial" :key="evento.id_historial">
							<strong>{{ evento.accion }}</strong>
							<span>{{ evento.estado_anterior || '-' }} → {{ evento.estado_nuevo || '-' }}</span>
							<small>{{ evento.created_by }} · {{ evento.created_at }}</small>
							<p v-if="evento.comentario">
								{{ evento.comentario }}
							</p>
						</li>
					</ul>
				</div>
			</section>
		</div>
	</NcAppContent>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

import CartOutline from 'vue-material-design-icons/CartOutline.vue'
import FileChartOutline from 'vue-material-design-icons/FileChartOutline.vue'

import {
	NcAppContent,
	NcButton,
	NcCheckboxRadioSwitch,
	NcEmptyContent,
	NcModal,
	NcNoteCard,
	NcSelect,
	NcTextArea,
	NcTextField,
} from '@nextcloud/vue'

import {
	autorizarSolicitud,
	crearSolicitud,
	enviarAutorizacion,
	listarSolicitudes,
	obtenerSolicitud,
	rechazarSolicitud,
} from '../../../services/comprasService.js'

export default {
	name: 'MisSolicitudes',

	components: {
		NcAppContent,
		NcButton,
		NcCheckboxRadioSwitch,
		NcEmptyContent,
		NcModal,
		NcNoteCard,
		NcSelect,
		NcTextArea,
		NcTextField,
		CartOutline,
		FileChartOutline,
	},

	data() {
		return {
			loading: false,
			showForm: false,
			verTodas: false,
			solicitudes: [],
			detalle: null,
			form: this.getEmptyForm(),
			priorityOptions: [
				{ id: 'baja', label: t('empleados', 'Low') },
				{ id: 'normal', label: t('empleados', 'Normal') },
				{ id: 'alta', label: t('empleados', 'High') },
				{ id: 'urgente', label: t('empleados', 'Urgent') },
			],
			currencyOptions: [
				{ id: 'MXN', label: 'MXN' },
				{ id: 'USD', label: 'USD' },
			],
		}
	},

	computed: {
		totalEstimado() {
			return this.form.detalles.reduce((total, item) => {
				return total + this.getDetalleSubtotal(item)
			}, 0)
		},

		totalPendientes() {
			return this.solicitudes.filter((item) => item.estado === 'pendiente_autorizacion').length
		},

		totalListado() {
			return this.solicitudes.reduce((total, item) => {
				return total + Number(item.monto_estimado || 0)
			}, 0)
		},
		selectedPriority: {
			get() {
				return this.priorityOptions.find(option => option.id === this.form.prioridad)
			|| this.priorityOptions.find(option => option.id === 'normal')
			},
			set(value) {
				this.form.prioridad = value?.id || 'normal'
			},
		},

		selectedCurrency: {
			get() {
				return this.currencyOptions.find(option => option.id === this.form.moneda)
			|| this.currencyOptions.find(option => option.id === 'MXN')
			},
			set(value) {
				this.form.moneda = value?.id || 'MXN'
			},
		},

		isFormValid() {
			const hasTitle = String(this.form.titulo || '').trim().length > 0
			const hasConcept = this.form.detalles.some((detalle) => {
				return String(detalle.descripcion || '').trim().length > 0
			})

			return hasTitle && hasConcept
		},
	},

	mounted() {
		this.cargarSolicitudes()
	},

	methods: {
		t,

		getEmptyForm() {
			return {
				titulo: '',
				descripcion: '',
				justificacion: '',
				moneda: 'MXN',
				prioridad: 'normal',
				fecha_requerida: '',
				detalles: [
					{
						descripcion: '',
						cantidad: 1,
						unidad: 'pieza',
						precio_estimado: 0,
						notas: '',
					},
				],
			}
		},

		toggleForm() {
			this.showForm = true
		},

		closeRequestModal() {
			if (this.loading) {
				return
			}

			this.showForm = false
		},

		onToggleVerTodas(value) {
			this.verTodas = Boolean(value)
			this.cargarSolicitudes()
		},

		addDetalle() {
			this.form.detalles.push({
				descripcion: '',
				cantidad: 1,
				unidad: 'pieza',
				precio_estimado: 0,
				notas: '',
			})
		},

		removeDetalle(index) {
			if (this.form.detalles.length === 1) {
				return
			}

			this.form.detalles.splice(index, 1)
		},

		getDetalleSubtotal(detalle) {
			const cantidad = Number(detalle?.cantidad || 0)
			const precio = Number(detalle?.precio_estimado || 0)

			return cantidad * precio
		},

		getApiPayload(response) {
			return response?.ocs?.data || response
		},

		async cargarSolicitudes() {
			this.loading = true

			try {
				const response = await listarSolicitudes({
					todas: this.verTodas ? 1 : 0,
				})

				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('empleados', 'Could not load requests.'))
				}

				this.solicitudes = Array.isArray(payload.data) ? payload.data : []
			} catch (error) {
				console.error(error)
				showError(error.message || t('empleados', 'Error loading requests.'))
			} finally {
				this.loading = false
			}
		},

		async crear() {
			this.loading = true

			try {
				const response = await crearSolicitud(this.form)
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('empleados', 'Could not create request.'))
				}

				this.form = this.getEmptyForm()
				this.showForm = false
				await this.cargarSolicitudes()
				this.form = this.getEmptyForm()
				this.showForm = false
				await this.cargarSolicitudes()

				showSuccess(t('empleados', 'Purchase request created successfully'))
			} catch (error) {
				console.error(error)
				showError(error.message || t('empleados', 'Error creating request.'))
			} finally {
				this.loading = false
			}
		},

		async verDetalle(id) {
			this.loading = true

			try {
				const response = await obtenerSolicitud(id)
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('empleados', 'Could not load request details.'))
				}

				this.detalle = payload.data
			} catch (error) {
				console.error(error)
				showError(error.message || t('empleados', 'Error loading request details.'))
			} finally {
				this.loading = false
			}
		},

		async enviar(id) {
			this.loading = true

			try {
				const response = await enviarAutorizacion(id)
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('empleados', 'Could not send request for approval.'))
				}

				await this.cargarSolicitudes()
				await this.verDetalle(id)

				showSuccess(t('empleados', 'Request sent for approval'))
			} catch (error) {
				console.error(error)
				showError(error.message || t('empleados', 'Error sending request.'))
			} finally {
				this.loading = false
			}
		},

		async autorizar(id) {
			const comentario = window.prompt(t('empleados', 'Approval comment'), t('empleados', 'Approved.'))

			this.loading = true

			try {
				const response = await autorizarSolicitud(id, comentario || '')
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('empleados', 'Could not approve request.'))
				}

				await this.cargarSolicitudes()
				await this.verDetalle(id)

				showSuccess(t('empleados', 'Request approved'))
			} catch (error) {
				console.error(error)
				showError(error.message || t('empleados', 'Error approving request.'))
			} finally {
				this.loading = false
			}
		},

		async rechazar(id) {
			const comentario = window.prompt(t('empleados', 'Rejection reason'), t('empleados', 'Rejected.'))

			if (comentario === null) {
				return
			}

			this.loading = true

			try {
				const response = await rechazarSolicitud(id, comentario)
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('empleados', 'Could not reject request.'))
				}

				await this.cargarSolicitudes()
				await this.verDetalle(id)

				showSuccess(t('empleados', 'Request rejected'))
			} catch (error) {
				console.error(error)
				showError(error.message || t('empleados', 'Error rejecting request.'))
			} finally {
				this.loading = false
			}
		},

		formatMoney(value) {
			const number = Number(value || 0)

			return new Intl.NumberFormat('es-MX', {
				style: 'currency',
				currency: 'MXN',
			}).format(number)
		},

		formatEstado(estado) {
			const estados = {
				borrador: t('empleados', 'Draft'),
				pendiente_autorizacion: t('empleados', 'Pending approval'),
				autorizada: t('empleados', 'Approved'),
				rechazada: t('empleados', 'Rejected'),
				cancelada: t('empleados', 'Cancelled'),
			}

			return estados[estado] || estado
		},
	},
}
</script>
<style scoped lang="scss">
.compras-page {
	display: flex;
	flex-direction: column;
	gap: 16px;
	width: 100%;
	padding: 24px;
}

.compras-header {
	display: flex;
	align-items: flex-end;
	justify-content: space-between;
	gap: 16px;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.header-title {
	min-width: 0;
}

.section-label,
.eyebrow {
	margin: 0 0 4px;
	color: var(--color-primary-element);
	font-size: 12px;
	font-weight: 700;
	letter-spacing: .04em;
	text-transform: uppercase;
}

.compras-header h2,
.panel-header h3,
.section-head h3,
.details-title h2 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 24px;
	font-weight: 700;
}

.section-description,
.panel-header p,
.details-title p {
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	line-height: 1.4;
}

.header-actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 8px;
}

.stats-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
}

.stat-card {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.stat-icon,
.details-icon {
	display: inline-flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-primary-element);
}

.stat-icon {
	width: 42px;
	height: 42px;
}

.stat-card span {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
}

.stat-card strong {
	display: block;
	margin-top: 2px;
	color: var(--color-main-text);
	font-size: 22px;
	font-weight: 700;
}

.panel-card {
	width: 100%;
	box-sizing: border-box;
	padding: 22px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
	overflow: hidden;
}

.panel-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	margin-bottom: 18px;
}

.filters,
.row-actions,
.details-actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 8px;
}

.table-scroll {
	width: 100%;
	overflow-x: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.compras-table {
	width: 100%;
	border-collapse: collapse;
}

.compras-table th,
.compras-table td {
	padding: 11px 12px;
	border-bottom: 1px solid var(--color-border);
	color: var(--color-main-text);
	text-align: left;
	vertical-align: middle;
}

.compras-table th {
	background: var(--color-background-hover);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.compras-table tbody tr:hover {
	background: var(--color-background-hover);
}

.compras-table tbody tr:last-child td {
	border-bottom: none;
}

.badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: 24px;
	padding: 4px 10px;
	border-radius: 999px;
	font-size: 12px;
	font-weight: 700;
	white-space: nowrap;
}

.estado-borrador {
	background: #e5e5e5;
	color: #222;
}

.estado-pendiente_autorizacion {
	background: #fff0b3;
	color: #5f4500;
}

.estado-autorizada {
	background: #d5f5d5;
	color: #115511;
}

.estado-rechazada {
	background: #ffd8d8;
	color: #7a1111;
}

.estado-cancelada {
	background: #ececec;
	color: #555;
}

.empty-state {
	padding: 28px;
	color: var(--color-text-maxcontrast);
	text-align: center;
}

.detail-panel {
	margin-bottom: 24px;
}

.details-header {
	display: flex;
	align-items: flex-start;
	gap: 14px;
	margin-bottom: 18px;
	min-width: 0;
}

.details-icon {
	width: 56px;
	height: 56px;
}

.details-title {
	flex: 1 1 auto;
	min-width: 0;
}

.details-title h2 {
	max-width: 100%;
	line-height: 1.2;
	overflow-wrap: anywhere;
}

.details-grid {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 12px;
	width: 100%;
	margin-bottom: 18px;
}

.detail-card {
	min-width: 0;
	box-sizing: border-box;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.detail-card span {
	display: block;
	margin-bottom: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.detail-card strong {
	max-width: 100%;
	margin: 0;
	color: var(--color-main-text);
	font-size: 14px;
	line-height: 1.5;
	overflow-wrap: anywhere;
	word-break: break-word;
}

.subsection {
	margin-top: 18px;
}

.section-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 12px;
}

.section-head h3 {
	font-size: 18px;
}

.historial-list {
	margin: 0;
	padding: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	list-style: none;
	overflow: hidden;
}

.historial-list li {
	padding: 12px 14px;
	border-bottom: 1px solid var(--color-border);
}

.historial-list li:last-child {
	border-bottom: none;
}

.historial-list span,
.historial-list small {
	display: block;
	margin-top: 2px;
	color: var(--color-text-maxcontrast);
}

.historial-list p {
	margin: 6px 0 0;
}
/* Modal de nueva solicitud */
.purchase-request-modal {
	:deep(.modal-container) {
		width: min(1180px, calc(100vw - 48px)) !important;
		max-width: min(1180px, calc(100vw - 48px)) !important;
		margin: 0 auto !important;
		box-sizing: border-box !important;
	}

	:deep(.modal-container__content),
	:deep(.modal__content),
	:deep(.modal-wrapper) {
		width: 100% !important;
		max-width: 100% !important;
		box-sizing: border-box !important;
	}
}

.purchase-modal {
	width: 100%;
	max-height: calc(100vh - 120px);
	box-sizing: border-box;
	padding: 28px;
	overflow-x: hidden;
	overflow-y: auto;
}

.modal-header {
	margin-bottom: 18px;
}

.modal-header h2,
.modal-section-head h3 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 24px;
	font-weight: 700;
}

.modal-header p {
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	line-height: 1.4;
}

.purchase-modal .form-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 14px;
	margin-bottom: 16px;
}

.purchase-modal .span-2 {
	grid-column: 1 / -1;
}

.native-field {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
}

.native-field span {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 700;
}

.native-field input {
	width: 100%;
	min-height: 44px;
	box-sizing: border-box;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.modal-section-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin: 22px 0 12px;
}

.concepts-list {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.concept-card {
	display: flex;
	gap: 12px;
	width: 100%;
	box-sizing: border-box;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.concept-number {
	display: flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	width: 34px;
	height: 34px;
	border-radius: 999px;
	background: var(--color-main-background);
	color: var(--color-primary-element);
	font-size: 13px;
	font-weight: 700;
}

.concept-fields {
	display: grid;
	flex: 1 1 auto;
	grid-template-columns: minmax(260px, 1.5fr) 110px 130px 150px auto;
	gap: 8px;
	min-width: 0;
}

.concept-fields input {
	width: 100%;
	min-width: 0;
	min-height: 38px;
	box-sizing: border-box;
	padding: 8px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.purchase-total-card {
	margin-top: 16px;
}

.purchase-total {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
}

.purchase-total span {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 700;
	text-transform: uppercase;
}

.purchase-total strong {
	color: var(--color-main-text);
	font-size: 22px;
	font-weight: 800;
}

.modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 22px;
}

/* Responsive */
@media (max-width: 980px) {
	.compras-page {
		padding: 14px;
	}

	.compras-header,
	.panel-header,
	.section-head,
	.details-header {
		align-items: stretch;
		flex-direction: column;
	}

	.header-actions,
	.filters,
	.details-actions {
		justify-content: flex-start;
	}

	.stats-grid,
	.details-grid {
		grid-template-columns: 1fr;
	}

	.panel-card {
		padding: 16px;
	}

	.details-icon {
		width: 46px;
		height: 46px;
	}

	.details-title h2 {
		font-size: 20px;
	}
}

@media (max-width: 900px) {
	.purchase-request-modal {
		:deep(.modal-container) {
			width: min(96vw, 1180px) !important;
			max-width: min(96vw, 1180px) !important;
		}
	}

	.purchase-modal {
		max-height: calc(100vh - 80px);
		padding: 16px;
	}

	.purchase-modal .form-grid,
	.concept-fields {
		grid-template-columns: 1fr;
	}

	.purchase-modal .span-2 {
		grid-column: auto;
	}

	.modal-section-head,
	.purchase-total {
		align-items: stretch;
		flex-direction: column;
	}

	.concept-card {
		flex-direction: column;
	}
}
</style>
