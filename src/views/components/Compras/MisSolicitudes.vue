<template>
	<NcAppContent :name="t('empleados', 'Purchases')">
		<div class="compras-page">
			<NcModal v-if="showForm"
				class="purchase-request-modal"
				size="large"
				:name="requestModalTitle"
				@close="closeRequestModal">
				<div class="purchase-modal">
					<div class="modal-header">
						<p class="section-label">
							{{ t('empleados', 'Purchases module') }}
						</p>
						<h2>{{ requestModalTitle }}</h2>
						<p>
							{{ t('empleados', 'Register the purchase request information and add at least one concept.')
							}}
						</p>
					</div>

					<div class="modal-block">
						<p class="section-label">
							{{ t('empleados', 'Requester data') }}
						</p>

						<NcNoteCard type="info" class="section-note">
							{{ t('empleados', 'Select the requester to complete the name, department, position and direct manager automatically.') }}
						</NcNoteCard>

						<div class="form-grid">
							<NcSelect v-if="canSelectRequester"
								v-model="selectedRequester"
								class="span-2"
								:input-label="t('empleados', 'Requester')"
								:options="requesterOptions"
								:clearable="true"
								@input="fillRequesterData"
								@option:selected="fillRequesterData" />

							<div v-else class="requester-locked-card span-2">
								<NcAvatar :user="currentRequester?.uid || ''"
									:display-name="currentRequester?.displayname || form.solicitante_nombre || ''"
									:size="44"
									:show-user-status="false"
									:show-user-status-compact="false" />

								<div class="requester-locked-info">
									<strong>{{ form.solicitante_nombre || t('empleados', 'Current user') }}</strong>
									<span>{{ t('empleados', 'This request will be created using your employee profile.')
									}}</span>
								</div>
							</div>

							<NcTextField :value.sync="form.solicitante_nombre"
								:disabled="contextLoaded && !canSelectRequester"
								:label="t('empleados', 'Name')" />

							<NcTextField :value.sync="form.solicitante_depto"
								:disabled="contextLoaded && !canSelectRequester"
								:label="t('empleados', 'Department')" />

							<NcTextField :value.sync="form.solicitante_cargo"
								:disabled="contextLoaded && !canSelectRequester"
								:label="t('empleados', 'Position')" />

							<div class="manager-preview">
								<span class="field-label">
									{{ t('empleados', 'Direct manager') }}
								</span>

								<div class="manager-card" :class="{ 'manager-card--empty': !form.jefe_directo_uid }">
									<NcAvatar v-if="form.jefe_directo_uid"
										:user="form.jefe_directo_uid"
										:display-name="form.jefe_directo_nombre || form.jefe_directo_uid"
										:size="44"
										:show-user-status="false"
										:show-user-status-compact="false" />

									<NcAvatar v-else
										display-name="?"
										:size="44"
										:show-user-status="false"
										:show-user-status-compact="false" />

									<div class="manager-info">
										<strong>{{ form.jefe_directo_nombre || t('empleados', 'No direct manager selected') }}</strong>
										<span v-if="form.jefe_directo_uid">@{{ form.jefe_directo_uid }}</span>
										<span v-else>{{ t('empleados', 'Select a requester first') }}</span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="modal-block">
						<p class="section-label">
							{{ t('empleados', 'Purchase data') }}
						</p>

						<NcNoteCard type="info" class="section-note">
							{{ t('empleados', 'Describe what will be purchased, when it is needed and the business reason for the request.') }}
						</NcNoteCard>

						<div class="form-grid">
							<NcTextField required
								class="span-2"
								:value.sync="form.titulo"
								:label="t('empleados', 'Title')" />

							<NcSelect v-model="selectedTipoCompra"
								:input-label="t('empleados', 'Purchase type')"
								:options="tipoCompraOptions"
								:clearable="false" />

							<NcSelect v-model="selectedUsoCompra"
								:input-label="t('empleados', 'Purchase use')"
								:options="usoCompraOptions"
								:clearable="false" />

							<NcSelect v-model="selectedPriority"
								:input-label="t('empleados', 'Priority')"
								:options="priorityOptions"
								:clearable="false" />

							<NcSelect v-model="selectedCurrency"
								:input-label="t('empleados', 'Currency')"
								:options="currencyOptions"
								:clearable="false" />

							<div class="date-field">
								<span class="field-label">
									{{ t('empleados', 'Required date') }}
								</span>
								<NcDateTimePicker v-model="requiredDateValue"
									type="date"
									:placeholder="t('empleados', 'Select a required date')" />
							</div>

							<div class="switch-field">
								<span>{{ t('empleados', 'Warranty') }}</span>
								<NcCheckboxRadioSwitch :checked="Boolean(form.garantia)"
									type="switch"
									@update:checked="form.garantia = Boolean($event)">
									{{ form.garantia ? t('empleados', 'Yes') : t('empleados', 'No') }}
								</NcCheckboxRadioSwitch>
							</div>

							<NcTextArea class="span-2"
								resize="vertical"
								:value.sync="form.informacion"
								:label="t('empleados', 'Information')" />

							<NcTextArea class="span-2"
								resize="vertical"
								:value.sync="form.motivo"
								:label="t('empleados', 'Reason')" />
						</div>
					</div>

					<div class="modal-section-head">
						<div>
							<p class="section-label">
								{{ t('empleados', 'Requisition') }}
							</p>
							<h3>{{ t('empleados', 'Requested products or services') }}</h3>
							<p class="section-description">
								{{ t('empleados', 'Each requested product can include its supplier, delivery and technical specifications.') }}
							</p>
						</div>

						<NcButton @click="addDetalle">
							{{ t('empleados', 'Add concept') }}
						</NcButton>
					</div>

					<NcNoteCard type="info" class="concepts-note">
						{{ t('empleados', 'Add one card per product or service. VAT is calculated automatically at 16% based on the subtotal.') }}
					</NcNoteCard>

					<div class="concepts-list">
						<div v-for="(concepto, index) in form.detalles" :key="index" class="concept-card">
							<div class="concept-card-header">
								<div class="concept-heading">
									<div class="concept-number">
										{{ index + 1 }}
									</div>

									<div>
										<strong>{{ t('empleados', 'Concept') }} {{ index + 1 }}</strong>
										<span>{{ formatMoney(getDetalleTotal(concepto)) }}</span>
									</div>
								</div>

								<NcButton :disabled="form.detalles.length === 1" @click="removeDetalle(index)">
									{{ t('empleados', 'Remove') }}
								</NcButton>
							</div>

							<div class="concept-fields">
								<NcTextField class="span-2"
									:value.sync="concepto.descripcion"
									:label="t('empleados', 'Description')" />

								<NcTextField :value.sync="concepto.cantidad"
									type="number"
									min="1"
									step="1"
									:label="t('empleados', 'Quantity')" />

								<NcTextField :value.sync="concepto.unidad" :label="t('empleados', 'Unit')" />

								<NcTextField :value.sync="concepto.precio_estimado"
									type="number"
									min="0"
									step="0.01"
									:label="t('empleados', 'Price without VAT')" />

								<NcTextField :value="formatMoney(getDetalleIva(concepto))"
									:label="t('empleados', 'VAT (16%)')"
									:disabled="true" />

								<NcTextField :value.sync="concepto.proveedor_nombre"
									:label="t('empleados', 'Supplier')" />

								<NcTextField :value.sync="concepto.atencion" :label="t('empleados', 'Attention')" />

								<NcTextField :value.sync="concepto.entrega" :label="t('empleados', 'Delivery')" />

								<NcTextField :value.sync="concepto.marca_modelo"
									:label="t('empleados', 'Brand / Model')" />

								<NcTextArea class="span-2"
									resize="vertical"
									:value.sync="concepto.especificaciones"
									:label="t('empleados', 'Specifications')" />

								<div class="concept-summary span-2">
									<div>
										<span>{{ t('empleados', 'Subtotal') }}</span>
										<strong>{{ formatMoney(getDetalleSubtotal(concepto)) }}</strong>
									</div>

									<div>
										<span>{{ t('empleados', 'Total') }}</span>
										<strong>{{ formatMoney(getDetalleTotal(concepto)) }}</strong>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="modal-block">
						<p class="section-label">
							{{ t('empleados', 'Administration') }}
						</p>

						<div class="form-grid">
							<NcTextField :value.sync="form.oficina_pct" :label="t('empleados', 'Office %')" />

							<NcTextField :value.sync="form.empleado_pct" :label="t('empleados', 'Employee %')" />

							<NcSelect v-model="selectedTipoPago"
								:input-label="t('empleados', 'Payment type')"
								:options="tipoPagoOptions"
								:clearable="true" />

							<NcTextField :value.sync="form.quincenas" :label="t('empleados', 'Fortnights')" />

							<NcTextArea class="span-2"
								resize="vertical"
								:value.sync="form.comentarios_admin"
								:label="t('empleados', 'Administration comments')" />
						</div>
					</div>

					<NcNoteCard type="info" class="purchase-total-card">
						<div class="purchase-total">
							<div>
								<span>{{ t('empleados', 'Subtotal') }}</span>
								<strong>{{ formatMoney(totalEstimado) }}</strong>
							</div>

							<div>
								<span>{{ t('empleados', 'VAT') }}</span>
								<strong>{{ formatMoney(totalIva) }}</strong>
							</div>

							<div>
								<span>{{ t('empleados', 'Total') }}</span>
								<strong>{{ formatMoney(totalInclIva) }}</strong>
							</div>
						</div>
					</NcNoteCard>

					<div class="modal-actions">
						<NcButton @click="closeRequestModal">
							{{ t('empleados', 'Cancel') }}
						</NcButton>

						<NcButton type="primary" :disabled="loading || !isFormValid" @click="crear">
							{{ loading ? t('empleados', 'Saving...') : t('empleados', 'Save request') }}
						</NcButton>
					</div>
				</div>
			</NcModal>

			<div class="compras-layout">
				<section class="panel-card requests-panel">
					<div class="panel-header">
						<div>
							<p class="section-label">
								{{ t('empleados', 'Tracking') }}
							</p>
							<h3>{{ t('empleados', 'My requests') }}</h3>
							<p>{{ t('empleados', 'Review the status of your purchase requests.') }}</p>
						</div>

						<div class="filters">
							<NcSelect v-model="selectedEstadoFiltro"
								class="status-filter"
								:input-label="t('empleados', 'Status filter')"
								:options="estadoFiltroOptions"
								:clearable="false" />

							<NcCheckboxRadioSwitch v-if="canToggleShowOnlyMine"
								:checked="showOnlyMine"
								type="switch"
								@update:checked="onToggleShowOnlyMine">
								{{ t('empleados', 'Show only my requests') }}
							</NcCheckboxRadioSwitch>
						</div>
					</div>

					<div v-if="loading" class="empty-state">
						{{ t('empleados', 'Loading...') }}
					</div>

					<div v-else class="request-sections">
						<section v-for="section in requestSections"
							:key="section.id"
							class="request-section"
							:class="{ 'request-section--pending': section.highlight }">
							<div class="request-section-header">
								<div>
									<p class="section-label">
										{{ section.title }}
									</p>
									<h4>{{ section.items.length }} {{ t('empleados', 'request(s)') }}</h4>
									<p>{{ section.description }}</p>
								</div>
							</div>

							<NcEmptyContent v-if="section.items.length === 0"
								:name="section.emptyName"
								:description="section.emptyDescription">
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
										<tr v-for="item in section.items" :key="item.id_solicitud">
											<td><strong>{{ item.folio }}</strong></td>

											<td>{{ item.titulo }}</td>

											<td>{{ formatRequesterLabel(item) }}</td>

											<td>{{ formatMoney(item.monto_estimado) }}</td>

											<td>
												<div class="status-stack">
													<span :class="['badge', `estado-${item.estado}`]">
														{{ formatEstado(item.estado) }}
													</span>

													<span v-if="getEstadoDocumental(item) === 'completo'"
														class="badge badge-document-ok">
														{{ t('empleados', 'Complete') }}
													</span>

													<span v-else-if="getEstadoDocumental(item) === 'pendiente_firmado'"
														class="badge badge-document-pending">
														{{ t('empleados', 'Pending signed document') }}
													</span>

													<span v-else-if="getEstadoDocumental(item) === 'pendiente_pdf'"
														class="badge badge-document-missing">
														{{ t('empleados', 'Pending PDF') }}
													</span>
												</div>
											</td>

											<td>{{ formatDateTime(item.created_at) }}</td>

											<td class="col-actions">
												<div class="row-actions table-actions">
													<NcButton :aria-label="t('empleados', 'View request')"
														:title="t('empleados', 'View request')"
														@click="verDetalle(item.id_solicitud)">
														<template #icon>
															<EyeOutline :size="20" />
														</template>
													</NcButton>

													<NcActions :aria-label="t('empleados', 'More actions')"
														:force-menu="true">
														<NcActionButton v-if="canEditRequest(item)"
															@click="editar(item.id_solicitud)">
															<template #icon>
																<PencilOutline :size="20" />
															</template>
															{{ t('empleados', 'Edit') }}
														</NcActionButton>

														<NcActionButton v-if="canCancelRequest(item)"
															@click="cancelar(item.id_solicitud)">
															<template #icon>
																<DeleteOutline :size="20" />
															</template>
															{{ t('empleados', 'Delete') }}
														</NcActionButton>

														<NcActionButton v-if="canSendRequest(item)"
															@click="enviar(item.id_solicitud)">
															<template #icon>
																<SendOutline :size="20" />
															</template>
															{{ t('empleados', 'Send') }}
														</NcActionButton>

														<NcActionButton v-if="canApproveRequest(item)"
															@click="autorizar(item.id_solicitud)">
															<template #icon>
																<CheckCircleOutline :size="20" />
															</template>
															{{ t('empleados', 'Approve') }}
														</NcActionButton>

														<NcActionButton v-if="canRejectRequest(item)"
															@click="rechazar(item.id_solicitud)">
															<template #icon>
																<CloseCircleOutline :size="20" />
															</template>
															{{ t('empleados', 'Reject') }}
														</NcActionButton>
													</NcActions>
												</div>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</section>
					</div>
				</section>
				<aside class="purchases-side-panel">
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

							<NcButton v-if="canCreatePurchaseRequest" type="primary" @click="toggleForm">
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
								<strong>{{ totalSolicitudesVisibles }}</strong>
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
				</aside>
			</div>
			<NcModal v-if="detalle"
				class="purchase-detail-modal"
				size="large"
				:name="detalle.solicitud.folio || t('empleados', 'Purchase request detail')"
				@close="detalle = null">
				<div class="detail-modal">
					<div class="details-header">
						<div class="details-icon">
							<CartOutline :size="30" />
						</div>
						<div class="details-actions">
							<NcButton @click="abrirDocumento(detalle.solicitud.id_solicitud)">
								{{ t('empleados', 'View PDF') }}
							</NcButton>

							<NcButton :disabled="loading || !canSaveOfficialPdf(detalle.solicitud)"
								@click="guardarDocumento(detalle.solicitud.id_solicitud)">
								{{ detalle.solicitud.pdf_file_id ? t('empleados', 'Update saved PDF') : t('empleados', 'Save PDF') }}
							</NcButton>

							<NcButton :disabled="loading || !canUploadSignedDocument(detalle.solicitud)"
								@click="seleccionarFirmado(detalle.solicitud.id_solicitud)">
								{{ detalle.solicitud.firmado_file_id ? t('empleados', 'Replace signed document') :
									t('empleados', 'Upload signed document') }}
							</NcButton>

							<NcButton v-if="canViewSignedDocument(detalle.solicitud)"
								@click="abrirDocumentoFirmado(detalle.solicitud.id_solicitud)">
								{{ t('empleados', 'View signed document') }}
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
							<strong>{{ formatRequesterLabel(detalle.solicitud) }}</strong>
						</div>

						<div class="detail-card">
							<span>{{ t('empleados', 'Department') }}</span>
							<strong>{{ detalle.solicitud.solicitante_depto || '-' }}</strong>
						</div>

						<div class="detail-card">
							<span>{{ t('empleados', 'Position') }}</span>
							<strong>{{ detalle.solicitud.solicitante_cargo || '-' }}</strong>
						</div>

						<div class="detail-card">
							<span>{{ t('empleados', 'Direct manager') }}</span>
							<strong>{{ detalle.solicitud.jefe_directo_nombre || '-' }}</strong>
						</div>

						<div class="detail-card">
							<span>{{ t('empleados', 'Purchase use') }}</span>
							<strong>{{ detalle.solicitud.uso_compra || '-' }}</strong>
						</div>
						<div class="detail-card">
							<span>{{ t('empleados', 'Generated PDF') }}</span>
							<strong>
								{{ detalle.solicitud.pdf_file_id ? t('empleados', 'Yes') : t('empleados', 'No') }}
							</strong>
						</div>

						<div class="detail-card">
							<span>{{ t('empleados', 'PDF generated at') }}</span>
							<strong>
								{{ detalle.solicitud.pdf_generado_at ? formatDateTime(detalle.solicitud.pdf_generado_at)
									: '-' }}
							</strong>
						</div>

						<div class="detail-card">
							<span>{{ t('empleados', 'Signed document') }}</span>
							<strong>
								{{ detalle.solicitud.firmado_file_id ? t('empleados', 'Uploaded') : t('empleados','Pending') }}
							</strong>
						</div>

						<div class="detail-card">
							<span>{{ t('empleados', 'Signed uploaded at') }}</span>
							<strong>
								{{ detalle.solicitud.firmado_subido_at ?
									formatDateTime(detalle.solicitud.firmado_subido_at) : '-' }}
							</strong>
						</div>
						<div class="detail-card">
							<span>{{ t('empleados', 'Document status') }}</span>
							<strong>
								{{ formatEstadoDocumental(detalle.solicitud) }}
							</strong>
						</div>
						<div class="detail-card">
							<span>{{ t('empleados', 'Saved PDF') }}</span>
							<strong>
								{{ detalle.solicitud.pdf_nombre || t('empleados', 'Not generated') }}
							</strong>
						</div>

						<div class="detail-card">
							<span>{{ t('empleados', 'Signed document') }}</span>
							<strong>
								{{ detalle.solicitud.firmado_nombre || t('empleados', 'Not uploaded') }}
							</strong>
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
										<th>{{ t('empleados', 'Supplier') }}</th>
										<th>{{ t('empleados', 'Delivery') }}</th>
										<th>{{ t('empleados', 'Brand / Model') }}</th>
										<th>{{ t('empleados', 'Quantity') }}</th>
										<th>{{ t('empleados', 'Price') }}</th>
										<th>{{ t('empleados', 'VAT') }}</th>
										<th>{{ t('empleados', 'Total') }}</th>
									</tr>
								</thead>

								<tbody>
									<tr v-for="concepto in detalle.detalles" :key="concepto.id_detalle">
										<td>
											<strong>{{ concepto.descripcion }}</strong>
											<p class="table-muted">
												{{ concepto.especificaciones || '' }}
											</p>
										</td>
										<td>{{ concepto.proveedor_nombre || '-' }}</td>
										<td>{{ concepto.entrega || '-' }}</td>
										<td>{{ concepto.marca_modelo || '-' }}</td>
										<td>{{ concepto.cantidad }} {{ concepto.unidad }}</td>
										<td>{{ formatMoney(concepto.precio_estimado) }}</td>
										<td>{{ formatMoney(concepto.iva) }}</td>
										<td>{{ formatMoney(concepto.total || concepto.subtotal) }}</td>
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
				</div>
			</NcModal>
		</div>
		<NcModal v-if="actionModal.show"
			class="purchase-action-modal"
			:name="actionModal.title"
			@close="closeActionModal">
			<div class="action-modal">
				<div class="action-modal-header">
					<p class="section-label">
						{{ t('empleados', 'Purchase action') }}
					</p>

					<h2>{{ actionModal.title }}</h2>

					<p>
						{{ actionModal.description }}
					</p>
				</div>

				<NcNoteCard :type="actionModal.noteType" class="action-modal-note">
					{{ actionModal.note }}
				</NcNoteCard>

				<NcTextArea class="action-modal-comment"
					resize="vertical"
					:value.sync="actionModal.comentario"
					:label="actionModal.commentLabel" />

				<p v-if="actionModal.requireComment && actionModalIsInvalid" class="action-modal-error">
					{{ t('empleados', 'A comment is required for this action.') }}
				</p>

				<div class="action-modal-actions">
					<NcButton :disabled="actionModal.loading" @click="closeActionModal">
						{{ t('empleados', 'Cancel') }}
					</NcButton>

					<NcButton :type="actionModal.confirmType"
						:disabled="actionModal.loading || actionModalIsInvalid"
						@click="submitActionModal">
						{{ actionModal.loading ? t('empleados', 'Processing...') : actionModal.confirmLabel }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</NcAppContent>
</template>

<script>
import EyeOutline from 'vue-material-design-icons/EyeOutline.vue'
import PencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import DeleteOutline from 'vue-material-design-icons/DeleteOutline.vue'
import SendOutline from 'vue-material-design-icons/SendOutline.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import CloseCircleOutline from 'vue-material-design-icons/CloseCircleOutline.vue'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

import CartOutline from 'vue-material-design-icons/CartOutline.vue'
import FileChartOutline from 'vue-material-design-icons/FileChartOutline.vue'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import {
	NcActionButton,
	NcActions,
	NcAppContent,
	NcAvatar,
	NcButton,
	NcCheckboxRadioSwitch,
	NcDateTimePicker,
	NcEmptyContent,
	NcModal,
	NcNoteCard,
	NcSelect,
	NcTextArea,
	NcTextField,
} from '@nextcloud/vue'

import {
	actualizarSolicitud,
	autorizarSolicitud,
	cancelarSolicitud,
	crearSolicitud,
	enviarAutorizacion,
	listarSolicitudes,
	obtenerSolicitud,
	rechazarSolicitud,
	obtenerContextoCompras,
	guardarDocumentoSolicitud,
	subirDocumentoFirmadoSolicitud,
} from '../../../services/comprasService.js'

const IVA_RATE = 0.16
const SHOW_ONLY_MINE_KEY = 'empleados.compras.showOnlyMine'

function roundMoney(value) {
	return Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100
}

export default {
	name: 'MisSolicitudes',

	components: {
		NcActionButton,
		NcActions,
		NcAppContent,
		NcAvatar,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDateTimePicker,
		NcEmptyContent,
		NcNoteCard,
		NcSelect,
		NcTextArea,
		NcTextField,
		CartOutline,
		FileChartOutline,
		EyeOutline,
		PencilOutline,
		DeleteOutline,
		SendOutline,
		CheckCircleOutline,
		CloseCircleOutline,
		NcModal,
	},

	data() {
		return {
			loading: false,
			showForm: false,
			showOnlyMine: true,
			hasSavedShowOnlyMinePreference: false,
			contextLoaded: false,
			currentUserId: '',
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
			tipoCompraOptions: [
				{ id: 'refaccion', label: t('empleados', 'Spare part') },
				{ id: 'equipo', label: t('empleados', 'Equipment') },
				{ id: 'servicio', label: t('empleados', 'Service') },
				{ id: 'software', label: t('empleados', 'Software') },
				{ id: 'otro', label: t('empleados', 'Other') },
			],
			usoCompraOptions: [
				{ id: 'empresa', label: t('empleados', 'Company') },
				{ id: 'personal', label: t('empleados', 'Personal') },
			],
			tipoPagoOptions: [
				{ id: 'contado', label: t('empleados', 'Cash') },
				{ id: 'nomina', label: t('empleados', 'Payroll discount') },
				{ id: 'transferencia', label: t('empleados', 'Bank transfer') },
				{ id: 'otro', label: t('empleados', 'Other') },
			],
			requesterOptions: [],
			selectedRequesterUid: '',
			areasCatalog: [],
			puestosCatalog: [],
			empleadosCatalog: [],
			estadoFiltroId: 'todos',
			estadoFiltroOptions: [
				{ id: 'todos', label: t('empleados', 'All statuses') },
				{ id: 'borrador', label: t('empleados', 'Draft') },
				{ id: 'pendiente_autorizacion', label: t('empleados', 'Pending approval') },
				{ id: 'autorizada', label: t('empleados', 'Approved') },
				{ id: 'rechazada', label: t('empleados', 'Rejected') },
				{ id: 'cancelada', label: t('empleados', 'Cancelled') },
			],
			actionModal: this.getEmptyActionModal(),
			editingSolicitudId: null,
			canSelectRequester: false,
			currentRequester: null,
			purchasePermissions: {
				can_create: false,
				can_view_all: false,
				can_approve: false,
				can_process_purchase: false,
				can_select_requester: false,
			},
			firmadoSolicitudId: null,
		}
	},

	computed: {
		canCreatePurchaseRequest() {
			return this.purchasePermissions.can_create
		},

		canApprovePurchaseRequest() {
			return this.purchasePermissions.can_approve
		},

		canProcessPurchaseRequest() {
			return this.purchasePermissions.can_process_purchase
		},
		totalEstimado() {
			return this.form.detalles.reduce((total, item) => {
				return total + this.getDetalleSubtotal(item)
			}, 0)
		},

		totalIva() {
			return this.form.detalles.reduce((total, item) => {
				return total + this.getDetalleIva(item)
			}, 0)
		},

		totalInclIva() {
			return this.totalEstimado + this.totalIva
		},

		isFormValid() {
			const hasTitle = String(this.form.titulo || '').trim().length > 0
			const hasConcept = this.form.detalles.some((detalle) => {
				return String(detalle.descripcion || '').trim().length > 0
			})

			return hasTitle && hasConcept
		},

		isEditingRequest() {
			return Boolean(this.editingSolicitudId)
		},

		requestModalTitle() {
			return this.isEditingRequest
				? t('empleados', 'Edit purchase request')
				: t('empleados', 'New purchase request')
		},

		selectedRequester: {
			get() {
				return this.requesterOptions.find((option) => {
					return option.uid === this.selectedRequesterUid
				}) || null
			},

			set(value) {
				this.selectedRequesterUid = value?.uid || ''
			},
		},

		selectedPriority: {
			get() {
				return this.priorityOptions.find((option) => {
					return option.id === this.form.prioridad
				}) || this.priorityOptions.find((option) => {
					return option.id === 'normal'
				})
			},

			set(value) {
				this.form.prioridad = value?.id || 'normal'
			},
		},

		selectedCurrency: {
			get() {
				return this.currencyOptions.find((option) => {
					return option.id === this.form.moneda
				}) || this.currencyOptions.find((option) => {
					return option.id === 'MXN'
				})
			},

			set(value) {
				this.form.moneda = value?.id || 'MXN'
			},
		},

		selectedTipoCompra: {
			get() {
				return this.tipoCompraOptions.find((option) => {
					return option.id === this.form.tipo_compra
				}) || this.tipoCompraOptions[0]
			},

			set(value) {
				this.form.tipo_compra = value?.id || 'refaccion'
			},
		},

		selectedUsoCompra: {
			get() {
				return this.usoCompraOptions.find((option) => {
					return option.id === this.form.uso_compra
				}) || this.usoCompraOptions[0]
			},

			set(value) {
				this.form.uso_compra = value?.id || 'empresa'
			},
		},

		selectedTipoPago: {
			get() {
				return this.tipoPagoOptions.find((option) => {
					return option.id === this.form.tipo_pago
				}) || null
			},

			set(value) {
				this.form.tipo_pago = value?.id || ''
			},
		},

		requiredDateValue: {
			get() {
				if (!this.form.fecha_requerida) {
					return null
				}

				const parsed = new Date(`${this.form.fecha_requerida}T00:00:00`)
				return Number.isNaN(parsed.getTime()) ? null : parsed
			},

			set(value) {
				if (!value) {
					this.form.fecha_requerida = ''
					return
				}

				const date = value instanceof Date ? value : new Date(value)

				if (Number.isNaN(date.getTime())) {
					this.form.fecha_requerida = ''
					return
				}

				this.form.fecha_requerida = date.toISOString().slice(0, 10)
			},
		},

		actionModalIsInvalid() {
			return this.actionModal.requireComment
				&& String(this.actionModal.comentario || '').trim().length === 0
		},
		selectedEstadoFiltro: {
			get() {
				return this.estadoFiltroOptions.find((option) => {
					return option.id === this.estadoFiltroId
				}) || this.estadoFiltroOptions[0]
			},

			set(value) {
				this.estadoFiltroId = value?.id || 'todos'
			},
		},
		canToggleShowOnlyMine() {
			return Boolean(
				this.purchasePermissions.can_view_all
				|| this.purchasePermissions.can_approve
				|| this.purchasePermissions.can_process_purchase
				|| this.purchasePermissions.can_select_requester,
			)
		},

		isShowingOthers() {
			return this.canToggleShowOnlyMine && !this.showOnlyMine
		},

		solicitudesPorAlcance() {
			if (!this.canToggleShowOnlyMine || this.showOnlyMine) {
				return this.solicitudes.filter((item) => this.isMyRequest(item))
			}

			return this.solicitudes.filter((item) => !this.isMyRequest(item))
		},

		solicitudesSinCanceladas() {
			return this.solicitudesPorAlcance.filter((item) => {
				return String(item.estado || '') !== 'cancelada'
			})
		},

		solicitudesPendientes() {
			return this.solicitudesSinCanceladas.filter((item) => {
				return String(item.estado || '') === 'pendiente_autorizacion'
			})
		},

		solicitudesFiltradas() {
			if (this.estadoFiltroId === 'todos') {
				return this.solicitudesSinCanceladas
			}

			return this.solicitudesPorAlcance.filter((item) => {
				return String(item.estado || '') === this.estadoFiltroId
			})
		},

		requestSections() {
			const sectionMap = {
				todos: {
					id: 'all',
					title: t('empleados', 'All statuses'),
					description: t('empleados', 'All active requests except cancelled requests.'),
					highlight: false,
				},
				borrador: {
					id: 'draft',
					title: t('empleados', 'Draft'),
					description: t('empleados', 'Requests that have not been sent for approval yet.'),
					highlight: false,
				},
				pendiente_autorizacion: {
					id: 'pending',
					title: t('empleados', 'Pending approval'),
					description: this.pendingApprovalDescription,
					highlight: true,
				},
				autorizada: {
					id: 'approved',
					title: t('empleados', 'Approved'),
					description: t('empleados', 'Requests that have already been approved.'),
					highlight: false,
				},
				rechazada: {
					id: 'rejected',
					title: t('empleados', 'Rejected'),
					description: t('empleados', 'Requests that were rejected during approval.'),
					highlight: false,
				},
				cancelada: {
					id: 'cancelled',
					title: t('empleados', 'Cancelled requests'),
					description: t('empleados', 'Cancelled requests are shown only when this status is selected.'),
					highlight: false,
				},
			}

			const section = sectionMap[this.estadoFiltroId] || sectionMap.todos

			return [
				{
					...section,
					items: this.solicitudesFiltradas,
					emptyName: t('empleados', 'No purchase requests found'),
					emptyDescription: t('empleados', 'Try changing the status filter or create a new request.'),
				},
			]
		},

		totalPendientes() {
			return this.solicitudesPendientes.length
		},

		totalListado() {
			return this.solicitudesSinCanceladas
				.filter((item) => !['rechazada'].includes(String(item.estado || '')))
				.reduce((total, item) => {
					return total + Number(item.monto_estimado || 0)
				}, 0)
		},

		totalSolicitudesVisibles() {
			return this.solicitudesSinCanceladas.length
		},

		pendingApprovalDescription() {
			if (!this.canToggleShowOnlyMine || this.showOnlyMine) {
				return t('empleados', 'Your requests waiting for approval.')
			}

			return t('empleados', 'Other users requests waiting for approval.')
		},
	},

	async mounted() {
		this.showOnlyMine = this.getSavedShowOnlyMine()

		const canAccess = await this.cargarContextoCompras()

		if (!canAccess) {
			return
		}

		await this.cargarCatalogosEmpleado()

		if (!this.canToggleShowOnlyMine) {
			this.showOnlyMine = true
		}

		if (this.canSelectRequester) {
			await this.cargarEmpleadosParaSolicitud()
		}

		await this.cargarSolicitudes()
	},

	methods: {
		t,

		getTodayDate() {
			const date = new Date()
			const year = date.getFullYear()
			const month = String(date.getMonth() + 1).padStart(2, '0')
			const day = String(date.getDate()).padStart(2, '0')

			return `${year}-${month}-${day}`
		},

		async duplicarDetalleActual() {
			if (!this.detalle?.solicitud) {
				showError(t('empleados', 'No request selected to duplicate.'))
				return
			}

			if (!this.canCreatePurchaseRequest) {
				showError(t('empleados', 'You do not have permission to create purchase requests.'))
				return
			}

			const solicitud = { ...this.detalle.solicitud }
			const detalles = Array.isArray(this.detalle.detalles)
				? this.detalle.detalles.map((detalle) => ({ ...detalle }))
				: []

			const duplicatedForm = {
				...this.getEmptyForm(),
				id_empleado: solicitud.id_empleado || null,

				titulo: solicitud.titulo || '',
				descripcion: solicitud.descripcion || '',
				justificacion: solicitud.justificacion || '',
				moneda: solicitud.moneda || 'MXN',
				prioridad: solicitud.prioridad || 'normal',

				// Fecha actual para la nueva solicitud duplicada
				fecha_requerida: this.getTodayDate(),

				solicitante_nombre: solicitud.solicitante_nombre || '',
				solicitante_depto: solicitud.solicitante_depto || '',
				solicitante_cargo: solicitud.solicitante_cargo || '',
				jefe_directo_nombre: solicitud.jefe_directo_nombre || '',
				jefe_directo_uid: solicitud.jefe_directo_uid || '',

				tipo_compra: solicitud.tipo_compra || 'refaccion',
				garantia: Boolean(Number(solicitud.garantia || 0)),
				uso_compra: solicitud.uso_compra || 'empresa',
				informacion: solicitud.informacion || solicitud.descripcion || '',
				motivo: solicitud.motivo || solicitud.justificacion || '',

				oficina_pct: solicitud.oficina_pct || '',
				empleado_pct: solicitud.empleado_pct || '',
				tipo_pago: solicitud.tipo_pago || '',
				quincenas: solicitud.quincenas || '',
				comentarios_admin: solicitud.comentarios_admin || '',

				detalles: detalles.length > 0
					? detalles.map((detalle) => ({
						descripcion: detalle.descripcion || '',
						cantidad: Number(detalle.cantidad || 1),
						unidad: detalle.unidad || 'pieza',
						precio_estimado: Number(detalle.precio_estimado || 0),
						notas: detalle.notas || '',
						proveedor_nombre: detalle.proveedor_nombre || '',
						atencion: detalle.atencion || '',
						entrega: detalle.entrega || '',
						marca_modelo: detalle.marca_modelo || '',
						especificaciones: detalle.especificaciones || '',
					}))
					: this.getEmptyForm().detalles,
			}

			// Importante: primero cerrar el modal de detalle.
			this.detalle = null

			// Esperar a que Vue quite el NcModal anterior del DOM.
			await this.$nextTick()

			// Esperar un frame extra por las transiciones/portal de NcModal.
			await new Promise((resolve) => requestAnimationFrame(resolve))

			// Ahora sí abrir el formulario como NUEVA solicitud.
			this.editingSolicitudId = null
			this.form = duplicatedForm
			this.showForm = true

			showSuccess(t('empleados', 'Purchase request duplicated. Review it before saving.'))
		},

		getEmptyForm() {
			return {
				id_empleado: null,

				titulo: '',
				descripcion: '',
				justificacion: '',
				moneda: 'MXN',
				prioridad: 'normal',
				fecha_requerida: '',

				solicitante_nombre: '',
				solicitante_depto: '',
				solicitante_cargo: '',
				jefe_directo_nombre: '',
				jefe_directo_uid: '',

				tipo_compra: 'refaccion',
				garantia: false,
				uso_compra: 'empresa',
				informacion: '',
				motivo: '',

				oficina_pct: '',
				empleado_pct: '',
				tipo_pago: '',
				quincenas: '',
				comentarios_admin: '',

				detalles: [
					{
						descripcion: '',
						cantidad: 1,
						unidad: 'pieza',
						precio_estimado: 0,
						notas: '',
						proveedor_nombre: '',
						atencion: '',
						entrega: '',
						marca_modelo: '',
						especificaciones: '',
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
			this.editingSolicitudId = null
			this.form = this.getEmptyForm()
		},

		addDetalle() {
			this.form.detalles.push({
				descripcion: '',
				cantidad: 1,
				unidad: 'pieza',
				precio_estimado: 0,
				notas: '',
				proveedor_nombre: '',
				atencion: '',
				entrega: '',
				marca_modelo: '',
				especificaciones: '',
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

			return roundMoney(cantidad * precio)
		},

		getDetalleIva(detalle) {
			return roundMoney(this.getDetalleSubtotal(detalle) * IVA_RATE)
		},

		getDetalleTotal(detalle) {
			return roundMoney(this.getDetalleSubtotal(detalle) + this.getDetalleIva(detalle))
		},

		getApiPayload(response) {
			return response?.ocs?.data || response
		},

		async cargarSolicitudes() {
			this.loading = true

			try {
				const response = await listarSolicitudes({
					todas: this.canToggleShowOnlyMine ? 1 : 0,
				})

				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('empleados', 'Could not load requests.'))
				}

				this.solicitudes = Array.isArray(payload.data) ? payload.data : []
			} catch (error) {
				console.error(error)
				showError(this.getErrorMessage(error, t('empleados', 'Error loading requests.')))
			} finally {
				this.loading = false
			}
		},

		async crear() {
			this.loading = true

			const wasEditing = this.isEditingRequest

			try {
				let response

				if (wasEditing) {
					response = await actualizarSolicitud(this.editingSolicitudId, this.getRequestPayload())
				} else {
					response = await crearSolicitud(this.getRequestPayload())
				}

				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('empleados', 'Could not save request.'))
				}

				this.form = this.getEmptyForm()
				this.showForm = false
				this.editingSolicitudId = null

				await this.cargarSolicitudes()

				showSuccess(
					wasEditing
						? t('empleados', 'Purchase request updated successfully')
						: t('empleados', 'Purchase request created successfully'),
				)
			} catch (error) {
				console.error(error)
				showError(this.getErrorMessage(error, t('empleados', 'Error saving request.')))
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
				showError(this.getErrorMessage(error, t('empleados', 'Error loading request details.')))
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
				showError(this.getErrorMessage(error, t('empleados', 'Error sending request.')))
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

		formatDateTime(value) {
			if (!value) {
				return '-'
			}

			const normalized = String(value).replace(' ', 'T')
			const date = new Date(normalized)

			if (Number.isNaN(date.getTime())) {
				return String(value)
			}

			return new Intl.DateTimeFormat('es-MX', {
				dateStyle: 'medium',
				timeStyle: 'short',
			}).format(date)
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

		formatRequesterLabel(item) {
			return item?.solicitante_nombre
				|| item?.requester_name
				|| item?.displayname
				|| item?.id_user
				|| '-'
		},

		getRequestPayload() {
			const firstDetalle = this.form.detalles[0] || {}
			const detalles = this.form.detalles.map((detalle) => {
				return {
					...detalle,
					iva: this.getDetalleIva(detalle),
					total: this.getDetalleTotal(detalle),
				}
			})

			return {
				...this.form,
				detalles,
				fecha_requerida: this.form.fecha_requerida || null,
				descripcion: this.form.informacion || this.form.descripcion,
				justificacion: this.form.motivo || this.form.justificacion,

				proveedor_nombre: firstDetalle.proveedor_nombre || '',
				atencion: firstDetalle.atencion || '',
				entrega: firstDetalle.entrega || '',
				marca_modelo: firstDetalle.marca_modelo || '',
				especificaciones: firstDetalle.especificaciones || '',

				total_excl_iva: this.totalEstimado,
				iva: this.totalIva,
				total_incl_iva: this.totalInclIva,
			}
		},
		async cargarEmpleadosParaSolicitud() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetUserLists'))
				const data = response?.data?.ocs?.data || {}

				const empleados = Array.isArray(data.Empleados) ? data.Empleados : []
				const desactivados = Array.isArray(data.Desactivados) ? data.Desactivados : []
				const users = Array.isArray(data.Users) ? data.Users : []

				this.empleadosCatalog = [...empleados, ...desactivados]

				const empleadosOptions = empleados.map((empleado) => {
					return this.normalizarEmpleadoOption(empleado, false)
				})

				const desactivadosOptions = desactivados.map((empleado) => {
					return this.normalizarEmpleadoOption(empleado, true)
				})

				const usersOptions = users.map((user) => {
					let displayname = user.uid

					try {
						displayname = JSON.parse(user.data)?.displayname?.value || user.uid
					} catch (e) {
						displayname = user.displayname || user.uid
					}

					return {
						uid: user.uid,
						id_empleado: null,
						label: displayname,
						displayname,
						departamento: '',
						cargo: '',
						jefe_directo: '',
						disabled: false,
						raw: user,
					}
				})

				const seen = {}

				this.requesterOptions = [...empleadosOptions, ...desactivadosOptions, ...usersOptions]
					.filter((item) => {
						if (!item.uid || seen[item.uid]) {
							return false
						}

						seen[item.uid] = true
						return true
					})
			} catch (error) {
				console.error(error)
				showError(t('empleados', 'Could not load employees for requester data.'))
			}
		},

		async cargarCatalogosEmpleado() {
			try {
				const [areasResponse, puestosResponse] = await Promise.all([
					axios.get(generateUrl('/apps/empleados/GetAreasFix')),
					axios.get(generateUrl('/apps/empleados/GetPuestosFix')),
				])

				this.areasCatalog = areasResponse?.data?.ocs?.data || []
				this.puestosCatalog = puestosResponse?.data?.ocs?.data || []
			} catch (error) {
				console.error(error)
				showError(t('empleados', 'Could not load departments and positions.'))
			}
		},

		normalizarEmpleadoOption(empleado, disabled = false) {
			const uid = empleado.Id_user || empleado.id_user || empleado.uid || ''
			const displayname = empleado.displayname
				|| empleado.DisplayName
				|| empleado.nombre_completo
				|| empleado.Nombre
				|| uid

			const departamento = this.getAreaLabel(
				empleado.Id_departamento
				|| empleado.id_departamento
				|| empleado.departamento
				|| empleado.Departamento,
			)

			const cargo = this.getPuestoLabel(
				empleado.Id_puesto
				|| empleado.id_puesto
				|| empleado.puesto
				|| empleado.Puesto,
			)

			const gerenteUid = empleado.Id_gerente
				|| empleado.id_gerente
				|| empleado.gerente
				|| empleado.Gerente
				|| ''

			const jefeDirecto = empleado.jefe_directo_nombre
				|| empleado.jefe_directo
				|| this.getEmpleadoDisplayNameByUid(gerenteUid)

			return {
				uid,
				id_empleado: empleado.Id_empleados || empleado.id_empleados || empleado.id_empleado || null,
				label: disabled ? `${displayname} (${t('empleados', 'Disabled')})` : displayname,
				displayname,
				departamento,
				cargo,
				jefe_directo: jefeDirecto,
				jefe_directo_uid: gerenteUid,
				gerente_uid: gerenteUid,
				disabled,
				raw: empleado,
			}
		},

		getAreaLabel(value) {
			if (value === null || value === undefined || value === '') {
				return ''
			}

			const area = this.areasCatalog.find((item) => {
				return String(item.value) === String(value)
					|| String(item.id) === String(value)
					|| String(item.label) === String(value)
			})

			return area?.label || String(value)
		},

		getPuestoLabel(value) {
			if (value === null || value === undefined || value === '') {
				return ''
			}

			const puesto = this.puestosCatalog.find((item) => {
				return String(item.value) === String(value)
					|| String(item.id) === String(value)
					|| String(item.label) === String(value)
			})

			return puesto?.label || String(value)
		},

		getEmpleadoDisplayNameByUid(uid) {
			if (!uid) {
				return ''
			}

			const empleado = this.empleadosCatalog.find((item) => {
				return String(item.Id_user || item.id_user || item.uid || '') === String(uid)
			})

			return empleado?.displayname
				|| empleado?.DisplayName
				|| empleado?.nombre_completo
				|| empleado?.Nombre
				|| uid
		},

		fillRequesterData(value) {
			const requester = value || this.selectedRequester

			if (!requester) {
				this.selectedRequesterUid = ''
				this.form.id_empleado = null
				this.form.solicitante_nombre = ''
				this.form.solicitante_depto = ''
				this.form.solicitante_cargo = ''
				this.form.jefe_directo_nombre = ''
				this.form.jefe_directo_uid = ''
				return
			}

			this.applyRequesterData(requester)
		},
		abrirDocumento(id) {
			const url = generateUrl('/apps/empleados/compras/solicitudes/{id}/documento', { id })
			window.open(url, '_blank', 'noopener,noreferrer')
		},
		canCancelRequest(item) {
			const estado = String(item?.estado || '')

			if (!['borrador', 'pendiente_autorizacion'].includes(estado)) {
				return false
			}

			if (this.purchasePermissions.can_approve) {
				return true
			}

			return this.isMyRequest(item)
		},

		getEmptyActionModal() {
			return {
				show: false,
				type: '',
				id: null,
				title: '',
				description: '',
				note: '',
				noteType: 'info',
				commentLabel: '',
				confirmLabel: '',
				confirmType: 'primary',
				comentario: '',
				requireComment: false,
				loading: false,
			}
		},

		openActionModal(type, id) {
			const configs = {
				approve: {
					title: t('empleados', 'Approve purchase request'),
					description: t('empleados', 'You are about to approve this purchase request.'),
					note: t('empleados', 'This will move the request to approved status and record the action in the history.'),
					noteType: 'info',
					commentLabel: t('empleados', 'Approval comment'),
					confirmLabel: t('empleados', 'Approve'),
					confirmType: 'primary',
					comentario: t('empleados', 'Approved.'),
					requireComment: false,
				},
				reject: {
					title: t('empleados', 'Reject purchase request'),
					description: t('empleados', 'You are about to reject this purchase request.'),
					note: t('empleados', 'The rejection reason will be saved in the request history.'),
					noteType: 'warning',
					commentLabel: t('empleados', 'Rejection reason'),
					confirmLabel: t('empleados', 'Reject'),
					confirmType: 'error',
					comentario: '',
					requireComment: true,
				},
				cancel: {
					title: t('empleados', 'Cancel purchase request'),
					description: t('empleados', 'You are about to cancel this purchase request.'),
					note: t('empleados', 'The request will not be physically deleted. It will be marked as cancelled for audit/history purposes.'),
					noteType: 'warning',
					commentLabel: t('empleados', 'Cancellation comment'),
					confirmLabel: t('empleados', 'Cancel request'),
					confirmType: 'error',
					comentario: t('empleados', 'Request cancelled from purchases module.'),
					requireComment: false,
				},
			}

			const config = configs[type]

			if (!config) {
				return
			}

			this.actionModal = {
				...this.getEmptyActionModal(),
				...config,
				type,
				id,
				show: true,
			}
		},

		closeActionModal() {
			if (this.actionModal.loading) {
				return
			}

			this.actionModal = this.getEmptyActionModal()
		},

		async submitActionModal() {
			if (this.actionModalIsInvalid) {
				showError(t('empleados', 'A comment is required for this action.'))
				return
			}

			const id = this.actionModal.id
			const type = this.actionModal.type
			const comentario = String(this.actionModal.comentario || '').trim()

			this.actionModal.loading = true
			this.loading = true

			try {
				let response
				let successMessage

				if (type === 'approve') {
					response = await autorizarSolicitud(id, comentario || t('empleados', 'Approved.'))
					successMessage = t('empleados', 'Request approved')
				} else if (type === 'reject') {
					response = await rechazarSolicitud(id, comentario)
					successMessage = t('empleados', 'Request rejected')
				} else if (type === 'cancel') {
					response = await cancelarSolicitud(
						id,
						comentario || t('empleados', 'Request cancelled from purchases module.'),
					)
					successMessage = t('empleados', 'Purchase request cancelled')
				} else {
					throw new Error(t('empleados', 'Invalid action.'))
				}

				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('empleados', 'Could not complete action.'))
				}

				if (type === 'cancel' && this.detalle?.solicitud?.id_solicitud === id) {
					this.detalle = null
				}

				await this.cargarSolicitudes()

				if (type !== 'cancel' && this.detalle?.solicitud?.id_solicitud === id) {
					await this.verDetalle(id)
				}

				this.actionModal = this.getEmptyActionModal()
				showSuccess(successMessage)
			} catch (error) {
				console.error(error)
				showError(this.getErrorMessage(error, t('empleados', 'Error completing action.')))
			} finally {
				this.actionModal.loading = false
				this.loading = false
			}
		},
		autorizar(id) {
			this.openActionModal('approve', id)
		},

		rechazar(id) {
			this.openActionModal('reject', id)
		},

		cancelar(id) {
			this.openActionModal('cancel', id)
		},
		async editar(id) {
			this.loading = true

			try {
				const response = await obtenerSolicitud(id)
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('empleados', 'Could not load request details.'))
				}

				const solicitud = payload.data.solicitud || {}
				const detalles = Array.isArray(payload.data.detalles) ? payload.data.detalles : []

				this.editingSolicitudId = id

				this.form = {
					...this.getEmptyForm(),
					id_empleado: solicitud.id_empleado || null,

					titulo: solicitud.titulo || '',
					descripcion: solicitud.descripcion || '',
					justificacion: solicitud.justificacion || '',
					moneda: solicitud.moneda || 'MXN',
					prioridad: solicitud.prioridad || 'normal',
					fecha_requerida: solicitud.fecha_requerida || '',

					solicitante_nombre: solicitud.solicitante_nombre || '',
					solicitante_depto: solicitud.solicitante_depto || '',
					solicitante_cargo: solicitud.solicitante_cargo || '',
					jefe_directo_nombre: solicitud.jefe_directo_nombre || '',
					jefe_directo_uid: solicitud.jefe_directo_uid || '',

					tipo_compra: solicitud.tipo_compra || 'refaccion',
					garantia: Boolean(Number(solicitud.garantia || 0)),
					uso_compra: solicitud.uso_compra || 'empresa',
					informacion: solicitud.informacion || solicitud.descripcion || '',
					motivo: solicitud.motivo || solicitud.justificacion || '',

					oficina_pct: solicitud.oficina_pct || '',
					empleado_pct: solicitud.empleado_pct || '',
					tipo_pago: solicitud.tipo_pago || '',
					quincenas: solicitud.quincenas || '',
					comentarios_admin: solicitud.comentarios_admin || '',

					detalles: detalles.length > 0
						? detalles.map((detalle) => ({
							descripcion: detalle.descripcion || '',
							cantidad: Number(detalle.cantidad || 1),
							unidad: detalle.unidad || 'pieza',
							precio_estimado: Number(detalle.precio_estimado || 0),
							notas: detalle.notas || '',
							proveedor_nombre: detalle.proveedor_nombre || '',
							atencion: detalle.atencion || '',
							entrega: detalle.entrega || '',
							marca_modelo: detalle.marca_modelo || '',
							especificaciones: detalle.especificaciones || '',
						}))
						: this.getEmptyForm().detalles,
				}

				this.showForm = true
			} catch (error) {
				console.error(error)
				showError(this.getErrorMessage(error, t('empleados', 'Error loading request for editing.')))
			} finally {
				this.loading = false
			}
		},

		async cargarContextoCompras() {
			try {
				const response = await obtenerContextoCompras()
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('empleados', 'Could not load purchase context.'))
				}

				const permissions = payload.data?.permissions || {}

				const canSelectRequester = permissions.can_select_requester
					?? payload.data?.can_select_requester
					?? false
				const canApprove = permissions.can_approve
					?? payload.data?.can_approve
					?? false
				const canProcessPurchase = permissions.can_process_purchase
					?? payload.data?.can_process_purchase
					?? false
				const canViewAll = permissions.can_view_all
					?? payload.data?.can_view_all
					?? canSelectRequester
					?? canApprove
					?? canProcessPurchase
				const canCreate = permissions.can_create
					?? payload.data?.can_create
					?? true

				this.purchasePermissions = {
					can_create: Boolean(canCreate),
					can_view_all: Boolean(canViewAll),
					can_approve: Boolean(canApprove),
					can_process_purchase: Boolean(canProcessPurchase),
					can_select_requester: Boolean(canSelectRequester),
				}

				this.canSelectRequester = this.purchasePermissions.can_select_requester
				this.contextLoaded = true
				this.currentUserId = payload.data?.uid || payload.data?.user_id || ''

				if (!this.canToggleShowOnlyMine) {
					this.showOnlyMine = true
				} else if (!this.hasSavedShowOnlyMinePreference) {
					this.showOnlyMine = false
				}

				const requesterData = payload.data?.requester || null

				if (requesterData) {
					const requester = this.normalizarEmpleadoOption({
						...requesterData.raw,
						uid: requesterData.uid,
						Id_user: requesterData.uid,
						Id_empleados: requesterData.id_empleado,
						Id_departamento: requesterData.id_departamento,
						Id_puesto: requesterData.id_puesto,
						Id_gerente: requesterData.jefe_directo_uid,
						displayname: requesterData.solicitante_nombre,
						jefe_directo_nombre: requesterData.jefe_directo_nombre,
					}, false)

					this.currentRequester = requester

					if (!this.canSelectRequester) {
						this.applyRequesterData(requester)
					}
				}

				return true
			} catch (error) {
				this.contextLoaded = true

				const status = error?.response?.status
				const message = this.getErrorMessage(
					error,
					t('empleados', 'You do not have permission to access the purchases module.'),
				)

				showError(message)

				if (status === 403) {
					this.redirectToDashboard()
					return false
				}

				console.error(error)
				return false
			}
		},
		applyRequesterData(requester) {
			this.selectedRequesterUid = requester.uid || ''
			this.form.id_empleado = requester.id_empleado || null
			this.form.solicitante_nombre = requester.displayname || ''
			this.form.solicitante_depto = requester.departamento || ''
			this.form.solicitante_cargo = requester.cargo || ''
			this.form.jefe_directo_nombre = requester.jefe_directo || ''
			this.form.jefe_directo_uid = requester.jefe_directo_uid || requester.gerente_uid || ''
		},
		getSavedShowOnlyMine() {
			this.hasSavedShowOnlyMinePreference = false

			if (typeof window === 'undefined') {
				return true
			}

			try {
				const value = window.localStorage.getItem(SHOW_ONLY_MINE_KEY)

				if (value === null) {
					return true
				}

				this.hasSavedShowOnlyMinePreference = true
				return value === 'true'
			} catch (error) {
				return true
			}
		},

		saveShowOnlyMine(value) {
			if (typeof window === 'undefined') {
				return
			}

			try {
				window.localStorage.setItem(SHOW_ONLY_MINE_KEY, String(Boolean(value)))
			} catch (error) {
				// localStorage puede fallar en modo privado o contextos restringidos.
			}
		},

		onToggleShowOnlyMine(value) {
			this.showOnlyMine = Boolean(value)
			this.hasSavedShowOnlyMinePreference = true
			this.saveShowOnlyMine(this.showOnlyMine)
			this.cargarSolicitudes()
		},

		isMyRequest(item) {
			const currentUserId = String(this.currentUserId || '')

			if (!currentUserId) {
				return true
			}

			const candidates = [
				item?.id_user,
				item?.created_by,
				item?.created_by_uid,
				item?.requester_uid,
				item?.solicitante_uid,
				item?.solicitante_id_user,
				item?.id_user_solicitante,
				item?.usuario_solicitante,
				item?.owner_uid,
				item?.uid,
				item?.user_id,
			].map((value) => String(value || ''))

			return candidates.includes(currentUserId)
		},
		redirectToDashboard() {
			if (this.$router && this.$router.currentRoute?.name !== 'Home') {
				this.$router.replace({ name: 'Home' })
				return
			}

			window.location.hash = '#/'
		},

		getErrorMessage(error, fallback) {
			return error?.response?.data?.ocs?.data?.message
				|| error?.response?.data?.message
				|| error?.message
				|| fallback
		},
		canEditRequest(item) {
			return String(item?.estado || '') === 'borrador'
				&& (this.purchasePermissions.can_select_requester || this.isMyRequest(item))
		},

		canSendRequest(item) {
			return String(item?.estado || '') === 'borrador'
				&& (this.purchasePermissions.can_select_requester || this.isMyRequest(item))
		},

		canApproveRequest(item) {
			return this.purchasePermissions.can_approve
				&& String(item?.estado || '') === 'pendiente_autorizacion'
		},

		canRejectRequest(item) {
			return this.purchasePermissions.can_approve
				&& String(item?.estado || '') === 'pendiente_autorizacion'
		},

		async guardarDocumento(id) {
			this.loading = true

			try {
				const response = await guardarDocumentoSolicitud(id)
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('empleados', 'Could not save PDF.'))
				}

				showSuccess(payload.message || t('empleados', 'PDF saved successfully.'))

				await this.cargarSolicitudes()
				await this.verDetalle(id)
			} catch (error) {
				console.error(error)
				showError(this.getErrorMessage(error, t('empleados', 'Error saving PDF.')))
			} finally {
				this.loading = false
			}
		},
		seleccionarFirmado(id) {
			this.firmadoSolicitudId = id
			this.$refs.firmadoInput.value = ''
			this.$refs.firmadoInput.click()
		},

		async onFirmadoSelected(event) {
			const file = event.target.files?.[0] || null

			if (!file || !this.firmadoSolicitudId) {
				return
			}

			this.loading = true

			try {
				const response = await subirDocumentoFirmadoSolicitud(this.firmadoSolicitudId, file)
				const payload = this.getApiPayload(response)

				if (!payload.success) {
					throw new Error(payload.message || t('empleados', 'Could not upload signed document.'))
				}

				showSuccess(payload.message || t('empleados', 'Signed document uploaded successfully.'))

				await this.cargarSolicitudes()
				await this.verDetalle(this.firmadoSolicitudId)
			} catch (error) {
				console.error(error)
				showError(this.getErrorMessage(error, t('empleados', 'Error uploading signed document.')))
			} finally {
				this.loading = false
				this.firmadoSolicitudId = null
			}
		},

		abrirDocumentoFirmado(id) {
			const url = generateUrl('/apps/empleados/compras/solicitudes/{id}/documento/firmado', { id })
			window.open(url, '_blank', 'noopener,noreferrer')
		},
		getEstadoDocumental(solicitud) {
			if (!solicitud?.pdf_file_id) {
				return 'pendiente_pdf'
			}

			if (!solicitud?.firmado_file_id) {
				return 'pendiente_firmado'
			}

			return 'completo'
		},

		formatEstadoDocumental(solicitud) {
			const estado = this.getEstadoDocumental(solicitud)

			const labels = {
				pendiente_pdf: t('empleados', 'Pending PDF'),
				pendiente_firmado: t('empleados', 'Pending signed document'),
				completo: t('empleados', 'Complete'),
			}

			return labels[estado] || estado
		},

		canSaveOfficialPdf(solicitud) {
			return String(solicitud?.estado || '') === 'autorizada'
		},

		canUploadSignedDocument(solicitud) {
			return String(solicitud?.estado || '') === 'autorizada'
		},

		canViewSignedDocument(solicitud) {
			return Boolean(solicitud?.firmado_file_id)
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

.compras-layout {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 380px;
	gap: 16px;
	align-items: start;
	width: 100%;
}

.requests-panel {
	min-width: 0;
}

.purchases-side-panel {
	display: flex;
	flex-direction: column;
	gap: 16px;
	min-width: 0;
}

.compras-header {
	display: flex;
	flex-direction: column;
	align-items: stretch;
	justify-content: space-between;
	gap: 18px;
	min-height: 220px;
	padding: 22px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.header-title {
	min-width: 0;
}

.compras-header h2 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 30px;
	font-weight: 800;
	line-height: 1.15;
}

.header-actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-start;
	gap: 8px;
}

.stats-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 12px;
}

.stat-card {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 16px;
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
	width: 46px;
	height: 46px;
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
	font-weight: 800;
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

.panel-header h3 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 28px;
	font-weight: 800;
	line-height: 1.15;
}

.row-actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 8px;
}

.table-actions {
	flex-wrap: nowrap;
	align-items: center;
	justify-content: flex-end;
	gap: 6px;
}

.col-actions {
	width: 96px;
	text-align: right;
	white-space: nowrap;
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

.request-sections {
	display: flex;
	flex-direction: column;
	gap: 18px;
}

.request-section {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.request-section-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	padding: 14px 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.request-section-header h4 {
	margin: 2px 0 0;
	color: var(--color-main-text);
	font-size: 20px;
	font-weight: 800;
}

.request-section-header p {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.request-section--pending .request-section-header {
	border-color: var(--color-warning);
	background: var(--color-warning-hover);
}

.request-section--pending .compras-table {
	border-left: 4px solid var(--color-warning);
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

.modal-block {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin-bottom: 18px;
}

.purchase-modal .span-2 {
	grid-column: 1 / -1;
}

.section-note,
.concepts-note {
	margin: 0;
}

.date-field,
.switch-field {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
}

.field-label,
.switch-field span {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 700;
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
	width: 100%;
	box-sizing: border-box;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.concept-card-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 14px;
}

.concept-heading {
	display: flex;
	align-items: center;
	gap: 12px;
}

.concept-heading strong,
.concept-heading span {
	display: block;
}

.concept-heading span {
	margin-top: 2px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
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
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
	min-width: 0;
}

.concept-summary {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.concept-summary span {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.concept-summary strong {
	display: block;
	margin-top: 4px;
	font-size: 16px;
}

.purchase-total-card {
	margin-top: 16px;
}

.purchase-total {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 16px;
}

.purchase-total div {
	padding: 4px 0;
}

.purchase-total span {
	display: block;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 700;
	text-transform: uppercase;
}

.purchase-total strong {
	display: block;
	margin-top: 2px;
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

.manager-preview {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
}

.manager-card {
	display: flex;
	align-items: center;
	gap: 12px;
	min-height: 52px;
	padding: 8px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.manager-card--empty {
	background: var(--color-background-hover);
}

.manager-info {
	display: flex;
	flex-direction: column;
	min-width: 0;
	line-height: 1.25;
}

.manager-info strong {
	overflow: hidden;
	color: var(--color-main-text);
	font-size: 14px;
	font-weight: 700;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.manager-info span {
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.requester-locked-card {
	display: flex;
	align-items: center;
	gap: 12px;
	min-height: 58px;
	padding: 10px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.requester-locked-info {
	display: flex;
	flex-direction: column;
	min-width: 0;
	line-height: 1.3;
}

.requester-locked-info strong {
	overflow: hidden;
	color: var(--color-main-text);
	font-size: 14px;
	font-weight: 700;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.requester-locked-info span {
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* Modal de detalle de solicitud */
.purchase-detail-modal {
	:deep(.modal-container) {
		width: min(1240px, calc(100vw - 56px)) !important;
		max-width: min(1240px, calc(100vw - 56px)) !important;
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

.detail-modal {
	display: flex;
	flex-direction: column;
	gap: 22px;
	width: 100%;
	max-height: calc(100vh - 110px);
	box-sizing: border-box;
	padding: 30px;
	overflow-x: hidden;
	overflow-y: auto;
}

.detail-modal .details-header {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr) auto;
	align-items: flex-start;
	gap: 18px;
	padding: 18px;
	margin-bottom: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.detail-modal .details-icon {
	width: 60px;
	height: 60px;
	background: var(--color-main-background);
	color: var(--color-primary-element);
}

.detail-modal .details-title {
	display: flex;
	flex-direction: column;
	gap: 4px;
	min-width: 0;
}

.action-modal-header p {
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	line-height: 1.45;
}

.action-modal-header h2 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 22px;
	font-weight: 800;
}

.detail-modal .details-title h2 {
	margin: 0;
	color: var(--color-main-text);
	font-size: 24px;
	font-weight: 800;
	line-height: 1.2;
	overflow-wrap: anywhere;
}

.detail-modal .details-title p {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	line-height: 1.45;
}

.detail-modal .details-actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 8px;
}

.detail-modal .details-grid {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 14px;
	margin-bottom: 0;
}

.detail-modal .detail-card {
	min-height: 88px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.detail-modal .detail-card span {
	display: block;
	margin-bottom: 8px;
	color: var(--color-text-maxcontrast);
	font-size: 11px;
	font-weight: 800;
	letter-spacing: .03em;
	text-transform: uppercase;
}

.detail-modal .detail-card strong {
	display: block;
	color: var(--color-main-text);
	font-size: 15px;
	font-weight: 700;
	line-height: 1.45;
	overflow-wrap: anywhere;
	word-break: break-word;
}

.detail-modal .subsection {
	padding: 18px;
	margin-top: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.detail-modal .section-head {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 14px;
}

.detail-modal .section-head h3 {
	margin: 0;
	font-size: 19px;
	font-weight: 800;
}

.detail-modal .table-scroll {
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.detail-modal .table-scroll .compras-table thead tr th,
.detail-modal .table-scroll .compras-table tbody tr td {
	padding: 13px 14px;
	vertical-align: top;
}

.detail-modal .table-scroll .compras-table thead tr th {
	font-size: 11px;
	letter-spacing: .03em;
}

.table-muted {
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.4;
}

.detail-modal .historial-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
	border: 0;
	border-radius: 0;
	background: transparent;
}

.detail-modal .historial-list li {
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.detail-modal .historial-list li:last-child {
	border-bottom: 1px solid var(--color-border);
}

.detail-modal .subsection .historial-list li strong {
	display: block;
	margin-bottom: 4px;
	color: var(--color-main-text);
	font-size: 14px;
}

.detail-modal .table-scroll .compras-table tbody tr td strong {
	display: block;
	margin-bottom: 4px;

}

.detail-modal .historial-list span,
.detail-modal .historial-list small {
	display: block;
	margin-top: 3px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.detail-modal .historial-list p {
	margin: 8px 0 0;
	color: var(--color-main-text);
	line-height: 1.4;
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
	.compras-table {
		min-width: 980px;
	}

	.compras-layout {
		grid-template-columns: 1fr;
	}

	.purchases-side-panel {
		order: -1;
	}

	.compras-header {
		min-height: auto;
	}

	.stats-grid {
		grid-template-columns: 1fr;
	}

	.panel-header {
		flex-direction: column;
	}

	.filters {
		justify-content: flex-start;
		width: 100%;
	}

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

	.concept-card-header,
	.concept-heading {
		align-items: flex-start;
		flex-direction: column;
	}

	.concept-summary {
		grid-template-columns: 1fr;
	}

	.purchase-total {
		grid-template-columns: 1fr;
	}

	.purchase-detail-modal {
		:deep(.modal-container) {
			width: min(96vw, 1240px) !important;
			max-width: min(96vw, 1240px) !important;
		}
	}

	.detail-modal {
		max-height: calc(100vh - 80px);
		padding: 16px;
		gap: 16px;
	}

	.detail-modal .details-header {
		grid-template-columns: 1fr;
		gap: 12px;
		padding: 14px;
	}

	.detail-modal .details-actions {
		justify-content: flex-start;
	}

	.detail-modal .details-grid {
		grid-template-columns: 1fr;
	}

	.detail-modal .subsection {
		padding: 14px;
	}

	.detail-modal .section-head {
		flex-direction: column;
	}

	.detail-modal .table-scroll .compras-table thead tr th,
	.detail-modal .table-scroll .compras-table tbody tr td {
		padding: 10px 12px;
	}
}

.status-filter {
	min-width: 230px;
}

.filters {
	align-items: center;
}

.purchase-action-modal {
	:deep(.modal-container) {
		width: min(560px, calc(100vw - 48px)) !important;
		max-width: min(560px, calc(100vw - 48px)) !important;
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

.action-modal {
	display: flex;
	flex-direction: column;
	gap: 16px;
	width: 100%;
	box-sizing: border-box;
	padding: 24px;
}

.action-modal-note {
	margin: 0;
}

.action-modal-comment {
	min-height: 120px;
}

.action-modal-error {
	margin: -4px 0 0;
	color: var(--color-error);
	font-size: 13px;
	font-weight: 700;
}

.action-modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 4px;
}

@media (max-width: 900px) {
	.purchase-action-modal {
		:deep(.modal-container) {
			width: min(96vw, 560px) !important;
			max-width: min(96vw, 560px) !important;
		}
	}

	.action-modal {
		padding: 16px;
	}

	.action-modal-actions {
		flex-direction: column-reverse;
	}

	.pending-approval-summary {
		display: grid;
		grid-template-columns: 52px minmax(0, 1fr) auto;
		gap: 12px;
		align-items: center;
		padding: 16px;
		border: 1px solid #e6b800;
		border-radius: var(--border-radius-large);
		background: #fff8d6;
	}

	.pending-approval-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 52px;
		height: 52px;
		border-radius: var(--border-radius-large);
		background: var(--color-main-background);
		color: #7a5a00;
	}

	.pending-approval-content {
		display: flex;
		flex-direction: column;
		min-width: 0;
	}

	.pending-approval-content strong {
		color: var(--color-main-text);
		font-size: 30px;
		font-weight: 800;
		line-height: 1;
	}

	.pending-approval-content span {
		overflow: hidden;
		margin-top: 4px;
		color: var(--color-text-maxcontrast);
		font-size: 13px;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}

.badge-document-ok {
	background: rgba(22, 163, 74, 0.12);
	color: #15803d;
	border: 1px solid rgba(22, 163, 74, 0.25);
}

.badge-document-pending {
	background: rgba(234, 179, 8, 0.14);
	color: #a16207;
	border: 1px solid rgba(234, 179, 8, 0.28);
}

.badge-document-missing {
	background: rgba(100, 116, 139, 0.14);
	color: #475569;
	border: 1px solid rgba(100, 116, 139, 0.25);
}
</style>
