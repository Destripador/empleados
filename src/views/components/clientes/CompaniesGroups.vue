<template>
	<NcAppContent :name="t('empleados', 'Companies and groups')">
		<div class="companies-page">
			<List :loading="loading"
				:listas="filteredListas"
				:select="select"
				:show-options="canAdminCustomers"
				:show-toggle-estado="canAdminCustomers"
				:toggle-estado-label="selectedIsActive ? t('empleados', 'Disable') : t('empleados', 'Enable')"
				:defaultbuttons="false"
				:custom="true">
				<template #custom>
					<div class="companies-main">
						<header class="companies-toolbar">
							<NcActions
								v-if="canAdminCustomers"
								:open.sync="settingsMenuOpen"
								:aria-label="t('empleados', 'Settings')"
								class="companies-toolbar__settings">
								<template #icon>
									<Cog :size="20" />
								</template>
								<NcActionButton @click="AbrirImportarModal()">
									<template #icon>
										<Upload :size="20" />
									</template>
									{{ t('empleados', 'Import from Customer System') }}
								</NcActionButton>
								<NcActionButton @click="AgregarNuevo()">
									<template #icon>
										<AccountMultiplePlusOutline :size="20" />
									</template>
									{{ t('empleados', 'Add new') }}
								</NcActionButton>
								<NcActionButton @click="Exportar()">
									<template #icon>
										<DatabaseExport :size="20" />
									</template>
									{{ t('empleados', 'Export list') }}
								</NcActionButton>
								<NcActionButton @click="triggerImport()">
									<template #icon>
										<Upload :size="20" />
									</template>
									{{ t('empleados', 'Import data from template') }}
								</NcActionButton>
							</NcActions>
						</header>

						<ClientesDashboard
							embedded
							@select-client="openCompanyFromDashboard" />
					</div>
				</template>
				<template #custombuttons>
					<div class="filter-trigger">
						<NcButton type="tertiary" class="filter-icon-button" @click="showListFilterModal = true">
							<template #icon>
								<FilterVariant :size="20" />
							</template>
						</NcButton>
						<span v-if="listFilterCount > 0" class="filter-badge">
							{{ listFilterCount }}
						</span>
					</div>
				</template>
				<template #details>
					<div class="client-details">
						<header class="companies-toolbar companies-toolbar--details">
							<NcButton type="tertiary" @click="closeCompanyDetails">
								<template #icon>
									<ArrowLeft :size="20" />
								</template>
								{{ t('empleados', 'Back to customers dashboard') }}
							</NcButton>
						</header>
						<div>
							<ClientDetailsHeader
								:client="selectedClient"
								:client-type="selectedClientType"
								:logo-bust="logoBust"
								:can-edit-logo="canEditClientLogo"
								@trigger-logo-upload="triggerLogoUpload"
								@remove-logo="removeClientLogo" />

							<VueTabs :key="selectedClient.id"
								class="companies-tabs"
								active-tab-color="var(--color-primary-element)"
								active-text-color="var(--color-primary-element-text)"
								type="grow">
								<VTab :title="t('empleados', 'General Information')">
									<ClientGeneralTab
										:client="selectedClient"
										:project-manager="projectManager"
										:parent-name="parentName"
										:child-companies="childCompanies"
										@select-child="GetCompanieGroup" />
								</VTab>
								<VTab :title="t('empleados', 'Fees')">
									<div class="info-section separator-top">
										<div class="section-head">
											<div>
												<p class="section-label">
													{{ t('empleados', 'Team') }}
												</p>
												<h3>{{ t('empleados', 'Collaborators') }}</h3>
											</div>
										</div>

										<div class="collaborators-block">
											<!-- Líder de proyecto -->
											<div class="collaborator-row">
												<span class="collaborator-role">{{ t('empleados', 'Project Leader') }}</span>
												<div v-if="projectManager" class="collaborator-item">
													<img :src="projectManager.avatar"
														:alt="projectManager.label"
														class="collaborator-avatar">
													<span class="value-text">{{ projectManager.label }}</span>
												</div>
												<span v-else class="value-text">
													{{ t('empleados', 'No one has registered yet') }}
												</span>
											</div>

											<!-- Colaboradores -->
											<div class="collaborator-row">
												<span class="collaborator-role">{{ t('empleados', 'Collaborators') }}</span>
												<div v-if="selectedClientCollaborators.length > 0" class="collaborator-list">
													<div v-for="emp in selectedClientCollaborators"
														:key="emp.value"
														class="collaborator-item">
														<img :src="emp.avatar" :alt="emp.label" class="collaborator-avatar">
														<span class="value-text">{{ emp.label }}</span>
													</div>
												</div>
												<span v-else class="value-text">
													{{ t('empleados', 'No one has registered yet') }}
												</span>
											</div>
										</div>
									</div>
									<!-- Honorarios -->
									<div class="info-section billing-section">
										<div class="section-head billing-head">
											<div class="billing-title">
												<span class="billing-title__icon">
													<FileDocumentOutline :size="20" />
												</span>
												<div>
													<p class="section-label">
														{{ t('empleados', 'Billing') }}
													</p>
													<h3>{{ t('empleados', 'Service Fees') }}</h3>
												</div>
											</div>
											<div class="billing-actions">
												<NcActions>
													<template #icon>
														<DotsHorizontal :size="20" />
													</template>

													<NcActionButton @click="honorarioFilterModal = true">
														<template #icon>
															<FilterVariant :size="20" />
														</template>
														{{ t('empleados', 'Filters') }}
													</NcActionButton>

													<NcActionButton v-if="canAdminCustomers" @click="toggleSelectMode">
														<template #icon>
															<CheckboxMarkedOutline :size="20" />
														</template>
														{{ selectMode ? t('empleados', 'Exit selection') : t('empleados', 'Select') }}
													</NcActionButton>
												</NcActions>

												<span v-if="honorarioFilterCount > 0" class="filter-badge">
													{{ honorarioFilterCount }}
												</span>

												<NcButton v-if="canAdminCustomers" type="primary" @click="openHonorarioModal">
													{{ t('empleados', 'New fee') }}
												</NcButton>
											</div>
										</div>

										<div v-if="selectMode" class="select-bar">
											<div class="select-all">
												<NcCheckboxRadioSwitch
													:checked="allFilteredSelected"
													:indeterminate="someFilteredSelected"
													@update:checked="toggleSelectAll">
													{{ t('empleados', 'Select all') }}
												</NcCheckboxRadioSwitch>
											</div>

											<span>{{ selectedHonorarios.length }} {{ t('empleados', 'selected') }}</span>
											<div class="select-bar__actions">
												<NcButton @click="toggleSelectMode">
													{{ t('empleados', 'Cancel') }}
												</NcButton>
												<NcButton type="primary"
													:disabled="selectedHonorarios.length === 0"
													@click="abrirReporteMultiple">
													{{ t('empleados', 'Generate request') }}
												</NcButton>
											</div>
										</div>

										<NcEmptyContent v-if="!filteredHonorarios.length"
											:name="t('empleados', 'No fees registered')"
											:description="t('empleados', 'Register a service fee for this company.')">
											<template #icon>
												<OfficeBuilding />
											</template>
										</NcEmptyContent>

										<div v-else class="honorarios-list">
											<div v-for="honorario in filteredHonorarios"
												:key="honorario.id_honorario"
												class="honorario-card"
												:class="{
													'honorario-especial': Number(honorario.especial) === 1,
													'honorario-card--selected': selectedHonorarios.includes(honorario.id_honorario)
												}">
												<div class="honorario-header">
													<input v-if="selectMode"
														type="checkbox"
														:checked="selectedHonorarios.includes(honorario.id_honorario)"
														class="honorario-checkbox"
														@change="toggleSeleccionHonorario(honorario.id_honorario)">

													<div class="honorario-info">
														<span class="value-text">{{ honorario.tipo_servicio || t('empleados', 'Service') }}</span>
														<span class="honorario-date">
															{{ honorario.fecha_inicio }} — {{ honorario.fecha_fin }}
														</span>
														<span v-if="honorario.descripcion" class="honorario-descripcion">
															{{ honorario.descripcion }}
														</span>
													</div>
													<div class="honorario-meta">
														<span class="honorario-amount">
															{{ formatImporte(montoAcumulado(honorario)) }} {{ honorario.tipo_moneda }}
															<span v-if="montoTotalMXN(honorario) !== null" class="honorario-amount-mxn">
																— {{ formatImporte(montoTotalMXN(honorario)) }} MXN
															</span>
														</span>
														<span class="honorario-badge"
															:class="Number(honorario.activo) ? 'badge-active' : 'badge-done'">
															{{ Number(honorario.activo) ? t('empleados', 'Active') : t('empleados', 'Completed') }}
														</span>

														<!-- badge de tipo -->
														<span class="honorario-badge badge-tipo"
															:class="'badge-tipo-' + (honorario.tipo_honorario || 'parcial')">
															{{ formatTipoHonorario(honorario.tipo_honorario) }}
														</span>
														<!-- Botón reactivar — solo igualas completadas -->
														<div class="honorario-right-actions">
															<NcButton v-if="canAdminCustomers && Number(honorario.numero_parcialidades) === 0"
																type="secondary"
																@click="completarHonorarioBorrador(honorario)">
																{{ t('empleados', 'Complete fee') }}
															</NcButton>

															<NcButton v-if="canAdminCustomers && !Number(honorario.solicitud_generada)"
																type="secondary"
																class="btn-solicitar"
																@click="abrirReporteHonorario(honorario)">
																{{ t('empleados', 'Request') }}
															</NcButton>

															<NcButton v-if="Number(honorario.numero_parcialidades) > 0"
																type="tertiary"
																@click="toggleParcialidades(honorario.id_honorario)">
																{{ t('empleados', 'Installments') }}
															</NcButton>

															<NcActions v-if="canAdminCustomers" :force-menu="true">
																<template #icon>
																	<DotsHorizontal :size="20" />
																</template>

																<NcActionButton
																	v-if="honorario.tipo_honorario === 'iguala' && Number(honorario.activo) === 1"
																	@click="agregarParcialidadIguala(honorario.id_honorario)">
																	<template #icon>
																		<CalendarPlus :size="20" />
																	</template>
																	{{ t('empleados', '+ Month') }}
																</NcActionButton>

																<NcActionButton
																	v-if="honorario.tipo_honorario === 'iguala' && Number(honorario.activo) === 1"
																	class="action-danger"
																	@click="askFinalizarHonorario(honorario.id_honorario)">
																	<template #icon>
																		<CloseCircleOutline :size="20" />
																	</template>
																	{{ t('empleados', 'Finalize') }}
																</NcActionButton>

																<NcActionButton
																	v-if="honorario.tipo_honorario === 'iguala' && Number(honorario.activo) === 0"
																	@click="reactivarHonorario(honorario.id_honorario)">
																	<template #icon>
																		<Restore :size="20" />
																	</template>
																	{{ t('empleados', 'Reactivate') }}
																</NcActionButton>

																<NcActionSeparator
																	v-if="honorario.tipo_honorario === 'iguala'" />

																<NcActionButton @click="abrirModificarHonorario(honorario)">
																	<template #icon>
																		<PencilOutline :size="20" />
																	</template>
																	{{ t('empleados', 'Modify') }}
																</NcActionButton>

																<NcActionButton @click="abrirReporteHonorario(honorario)">
																	<template #icon>
																		<FileDocumentOutline :size="20" />
																	</template>
																	{{ t('empleados', 'Request') }}
																</NcActionButton>

																<NcActionButton @click="askDeleteHonorario(honorario.id_honorario)">
																	<template #icon>
																		<TrashCanOutline :size="20" />
																	</template>
																	{{ t('empleados', 'Delete') }}
																</NcActionButton>
															</NcActions>
														</div>
													</div>
												</div>

												<div v-if="parcialidadesAbiertas[honorario.id_honorario]"
													class="parcialidades-list">
													<div class="parcialidades-head">
														<span>{{ t('empleados', 'Installments') }}</span>
														<span>
															{{ (parcialidades[honorario.id_honorario] || []).length }}
														</span>
													</div>
													<div v-if="loadingParcialidades[honorario.id_honorario]"
														class="parcialidades-loading">
														{{ t('empleados', 'Loading...') }}
													</div>
													<template v-else>
														<div v-for="p in (parcialidades[honorario.id_honorario] || [])"
															:key="p.id_parcialidad"
															class="parcialidad-row"
															:class="{
																'parcialidad-pagada': Number(p.pagado) === 1,
																'parcialidad-facturada': Number(p.pagado) === 2
															}">
															<div class="parcialidad-main">
																<div class="parcialidad-num-wrapper">
																	<span class="parcialidad-num">#{{ p.numero_parcialidad
																	}}</span>

																	<span
																		v-if="Number(p.pagado) >= 1"
																		class="parcialidad-toggle"
																		:class="{ open: detalleAbierto[p.id_parcialidad] }"
																		@click="toggleDetalleParcialidad(p.id_parcialidad)">
																		<ChevronDown :size="16" />
																	</span>
																</div>
																<span class="parcialidad-fechas">{{ p.pfecha_inicio }} — {{
																	p.pfecha_fin }}</span>
																<span class="parcialidad-importe parcialidad-importe--stacked">
																	<span class="parcialidad-importe-principal">
																		{{ formatImporte(p.importe_parcialidad) }} {{ honorario.tipo_moneda }}
																	</span>
																	<span v-if="Number(p.pagado) === 2 && montoMXN(p) !== null" class="parcialidad-importe-mxn">
																		{{ formatImporte(montoMXN(p)) }} MXN
																	</span>
																</span>
																<div class="parcialidad-actions">
																	<NcButton v-if="canAdminCustomers && Number(p.pagado) === 0"
																		class="btn-pagar"
																		type="primary"
																		@click="abrirFacturaModal(p, honorario.id_honorario)">
																		{{ t('empleados', 'Mark as invoiced') }}
																	</NcButton>

																	<NcButton v-else-if="canAdminCustomers && Number(p.pagado) === 1"
																		class="btn-factura"
																		type="secondary"
																		@click="abrirPagoModal(p, honorario.id_honorario)">
																		{{ t('empleados', 'Mark as paid') }}
																	</NcButton>

																	<span v-else-if="Number(p.pagado) === 2" class="parcialidad-completada">
																		{{ t('empleados', 'Completed') }} ✓
																	</span>
																</div>
															</div>
															<div v-if="detalleAbierto[p.id_parcialidad]" class="parcialidad-detalle">
																<div v-if="p.fecha_factura" class="parcialidad-detalle-row">
																	<span class="parcialidad-detail-text">
																		🧾 {{ t('empleados', 'Invoice date') }}: {{ p.fecha_factura }}
																		<template v-if="p.id_cliente_pagador">
																			—
																			<button type="button" class="pagador-link" @click="irAClientePagador(p.id_cliente_pagador)">
																				{{ p.pagador_nombre || t('empleados', 'Another company') }}
																			</button>
																		</template>
																	</span>

																	<NcActions v-if="canAdminCustomers && Number(p.pagado) === 1" class="parcialidad-detalle-actions">
																		<template #icon>
																			<DotsHorizontal :size="18" />
																		</template>
																		<NcActionButton @click="editarFechaFactura(p, honorario.id_honorario)">
																			<template #icon>
																				<PencilOutline :size="20" />
																			</template>
																			{{ t('empleados', 'Edit invoice date') }}
																		</NcActionButton>
																		<NcActionButton @click="askCancelarFactura(p, honorario.id_honorario)">
																			<template #icon>
																				<CloseCircleOutline :size="20" />
																			</template>
																			{{ t('empleados', 'Cancel invoice') }}
																		</NcActionButton>
																	</NcActions>
																</div>

																<div v-if="p.fecha_pago" class="parcialidad-detalle-row">
																	<span class="parcialidad-detail-text">
																		💳 {{ t('empleados', 'Payment date') }}: {{ p.fecha_pago }}
																	</span>

																	<NcActions v-if="canAdminCustomers && Number(p.pagado) === 2" class="parcialidad-detalle-actions">
																		<template #icon>
																			<DotsHorizontal :size="18" />
																		</template>
																		<NcActionButton @click="editarFechaPago(p, honorario.id_honorario)">
																			<template #icon>
																				<PencilOutline :size="20" />
																			</template>
																			{{ t('empleados', 'Edit payment date') }}
																		</NcActionButton>
																		<NcActionButton @click="askCancelarPago(p, honorario.id_honorario)">
																			<template #icon>
																				<CloseCircleOutline :size="20" />
																			</template>
																			{{ t('empleados', 'Cancel payment') }}
																		</NcActionButton>
																	</NcActions>
																</div>
															</div>
														</div>
													</template>
												</div>
											</div>
										</div>
									</div>
									<div v-if="pagosRealizados.length > 0" class="info-section billing-section">
										<div class="section-head">
											<div>
												<p class="section-label">
													{{ t('empleados', 'Billing') }}
												</p>
												<h3>{{ t('empleados', 'Invoices generated for other companies') }}</h3>
											</div>
										</div>

										<div class="children-grid">
											<button v-for="pago in pagosRealizados"
												:key="pago.id_parcialidad"
												type="button"
												class="child-card"
												@click="irAClienteOriginal(pago.id_cliente)">
												<div class="child-icon">
													<OfficeBuilding :size="20" />
												</div>

												<div class="child-info">
													<span class="value-text">{{ pago.cliente_nombre }} — {{ pago.tipo_servicio || t('empleados', 'Service') }}</span>
													<span>#{{ pago.numero_parcialidad }} · {{ formatImporte(pago.importe_parcialidad) }} {{ pago.tipo_moneda }} · {{ pago.fecha_pago }}</span>
												</div>
											</button>
										</div>
									</div>
								</VTab>
							</VueTabs>
						</div>

						<!-- Modal - Registrar Factura (paso 1: pendiente -> facturada) -->
						<ModalFactura
							:open="showFacturaModal"
							:fecha.sync="fechaFactura"
							:show-advanced.sync="showAdvancedFactura"
							:cliente-pagador.sync="facturaClientePagador"
							:clientes-pagador-options="clientesPagadorOptions"
							@close="showFacturaModal = false"
							@save="confirmarFacturaModal" />

						<!-- Modal - Registrar Pago (paso 2: facturada -> pagada) -->
						<ModalPago
							:open="showPagoModal"
							:fecha.sync="fechaPago"
							@close="showPagoModal = false"
							@save="confirmarPagoModal" />

						<NcDialog :open.sync="showDeleteHonorarioDialog"
							:name="t('empleados', 'Confirm')"
							:message="t(
								'empleados',
								'Do you want to delete this service fee and all its installments?'
							)
							"
							:buttons="deleteHonorarioButtons" />
						<NcDialog :open.sync="showCancelarPagoDialog"
							:name="t('empleados', 'Confirm')"
							:message="t('empleados', 'This will revert the installment to invoiced status. Continue?')"
							:buttons="[
								{ label: t('empleados', 'Cancel'), callback: () => { showCancelarPagoDialog = false } },
								{ label: t('empleados', 'Cancel payment'), type: 'primary', callback: () => { confirmarCancelarPago() } },
							]" />
						<NcDialog :open.sync="showCancelarFacturaDialog"
							:name="t('empleados', 'Confirm')"
							:message="t('empleados', 'This will mark the installment as pending again and clear the invoice date. Continue?')"
							:buttons="[
								{ label: t('empleados', 'Cancel'), callback: () => { showCancelarFacturaDialog = false } },
								{ label: t('empleados', 'Cancel invoice'), type: 'primary', callback: () => { confirmarCancelarFactura() } },
							]" />
						<NcDialog :open.sync="showCancelarPagoDialog"
							:name="t('empleados', 'Confirm')"
							:message="t('empleados', 'This will revert the installment to invoiced status and clear the payment date. Continue?')"
							:buttons="[
								{ label: t('empleados', 'Cancel'), callback: () => { showCancelarPagoDialog = false } },
								{ label: t('empleados', 'Cancel payment'), type: 'primary', callback: () => { confirmarCancelarPago() } },
							]" />
						<NcDialog :open.sync="showFinalizarDialog"
							:name="t('empleados', 'Finalize fee')"
							:message="t('empleados', 'This will mark the fee as completed. No new installments will be generated. Continue?')"
							:buttons="[
								{ label: t('empleados', 'Cancel'), callback: () => { showFinalizarDialog = false } },
								{ label: t('empleados', 'Finalize'), type: 'primary', callback: () => { confirmarFinalizarHonorario() } },
							]" />
					</div>
				</template>
			</List>
		</div>

		<!-- Modal: Cliente -->
		<ModalCliente
			v-if="modal && canAdminCustomers"
			:open="modal"
			:editing="editing"
			:saving="saving"
			:modal-title="modalTitle"
			:save-label="saveLabel"
			:selected-client="selectedClient"
			:can-edit-logo="canEditClientLogo"
			:logo-bust="logoBust"
			:project-managers="projectManagers"
			:parent-options="parentOptions"
			@save="handleSaveCliente"
			@close="closeModal"
			@trigger-logo-upload="triggerLogoUpload"
			@remove-logo="removeClientLogo" />

		<!-- Modal: Filtros de clientes -->
		<ModalFiltrosLista
			:open="showListFilterModal"
			:sort-order.sync="sortOrder"
			:tipo-filtro.sync="tipoFiltro"
			:estado-filtro.sync="estadoFiltro"
			:only-special.sync="onlySpecial"
			@reset="resetListFilters"
			@close="showListFilterModal = false" />

		<!-- Modal: Filtros Honorarios -->
		<ModalFiltrosHonorarios
			:open="honorarioFilterModal"
			:busqueda.sync="hf_busqueda"
			:estado.sync="hf_estado"
			:tipo.sync="hf_tipo"
			:solo-especial.sync="hf_soloEspecial"
			:desde.sync="hf_desde"
			:hasta.sync="hf_hasta"
			@reset="resetHonorarioFilters"
			@close="honorarioFilterModal = false" />

		<!-- Modal - Solicitud de recibo -->
		<ModalReporteHonorario
			v-if="canAdminCustomers"
			:open="reporteModal"
			:departamento.sync="rep_departamento"
			:departamento-options="rep_departamentoOptions"
			:asunto.sync="rep_asunto"
			:generating="generandoReporte"
			:sending="enviandoReporte"
			@close="reporteModal = false"
			@generate="generarReporte"
			@send="enviarSolicitudHonorario" />

		<!-- Modal - Solicitudes múltiples -->
		<ModalReporteMultiple
			v-if="canAdminCustomers"
			:open="reporteMultipleModal"
			:honorarios="honorariosSeleccionadosDetalle"
			:downloading="descargandoMultiple"
			:notifying="notificandoMultiple"
			@close="reporteMultipleModal = false"
			@download="descargarSolicitudesMultiples"
			@notify="notificarHonorariosPendientes" />

		<!-- Modal: Importar -->
		<ModalClientes v-if="showImportarModal" @close="showImportarModal = false" />

		<!-- Modal - Honorarios -->
		<ModalHonorario
			v-if="canAdminCustomers"
			:open="honorarioModal"
			:title="honorarioModalTitle"
			:save-label="honorarioSaveLabel"
			:saving="savingHonorario"
			:is-editing="isEditingHonorario"
			:is-valid="isHonorarioValid"
			:tipos-honorario="tiposHonorario"
			:meses="meses"
			:anios="anios"
			:currency-options="currencyOptions"
			:periodicidad-options="periodicidadOptions"
			:period-breakdown="periodBreakdown"
			:period-amounts="periodAmounts"
			:format-importe="formatImporte"
			:tipo-honorario.sync="h_tipo_honorario"
			:especial.sync="h_especial"
			:tipo-servicio.sync="h_tipo_servicio"
			:titulo-anio.sync="h_titulo_anio"
			:tipo-moneda.sync="h_tipo_moneda"
			:importe-total.sync="h_importe_total"
			:descripcion.sync="h_descripcion"
			:mes-inicio.sync="h_mes_inicio"
			:anio-inicio.sync="h_anio_inicio"
			:mes-fin.sync="h_mes_fin"
			:anio-fin.sync="h_anio_fin"
			:periodicidad.sync="h_periodicidad"
			@close="closeHonorarioModal"
			@save="handleSaveHonorario" />
		<input v-if="canAdminCustomers"
			ref="file"
			type="file"
			class="file-input"
			accept=".xlsx"
			@change="importar">
		<input v-if="canAdminCustomers"
			ref="logoFile"
			type="file"
			class="file-input"
			accept="image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp"
			@change="onLogoSelected">
	</NcAppContent>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import ModalClientes from './ModalClientes.vue'
import ModalCliente from './Modals/ModalCliente.vue'
import ModalFiltrosLista from './Modals/ModalFiltrosLista.vue'
import ModalFiltrosHonorarios from './Modals/ModalFiltrosHonorarios.vue'
import ModalFactura from './Modals/ModalFactura.vue'
import ModalPago from './Modals/ModalPago.vue'
import ModalHonorario from './Modals/ModalHonorario.vue'
import ModalReporteHonorario from './Modals/ModalReporteHonorario.vue'
import ModalReporteMultiple from './Modals/ModalReporteMultiple.vue'

import ClientDetailsHeader from './Details/ClientDetailsHeader.vue'
import ClientGeneralTab from './Details/ClientGeneralTab.vue'

import List from '../Helpers/Lists/List.vue'
import permissionsMixin from '../../../mixins/permissions.js'
import clientesService, { clienteLogoUrl } from '../../../services/clientesService.js'

import OfficeBuilding from 'vue-material-design-icons/OfficeBuilding.vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import CheckboxMarkedOutline from 'vue-material-design-icons/CheckboxMarkedOutline.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import AccountMultiplePlusOutline from 'vue-material-design-icons/AccountMultiplePlusOutline.vue'
import DatabaseExport from 'vue-material-design-icons/DatabaseExport.vue'
import Upload from 'vue-material-design-icons/Upload.vue'
import FilterVariant from 'vue-material-design-icons/FilterVariant.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import PencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import CloseCircleOutline from 'vue-material-design-icons/CloseCircleOutline.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import CalendarPlus from 'vue-material-design-icons/CalendarPlus.vue'
import Restore from 'vue-material-design-icons/Restore.vue'
import FileDocumentOutline from 'vue-material-design-icons/FileDocumentOutline.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import { VueTabs, VTab } from 'vue-nav-tabs/dist/vue-tabs.js'
import 'vue-nav-tabs/themes/vue-tabs.css'

import {
	NcAppContent,
	NcButton,
	NcDialog,
	NcEmptyContent,
	NcActions,
	NcActionButton,
	NcActionSeparator,
} from '@nextcloud/vue'

const ClientesDashboard = () => import(
	'./ClientesDashboard.vue'
)

export default {
	name: 'CompaniesGroups',

	components: {
		ModalClientes,
		ModalCliente,
		ModalFiltrosLista,
		ModalFiltrosHonorarios,
		TrashCanOutline,
		Restore,
		CalendarPlus,
		CheckboxMarkedOutline,
		DotsHorizontal,
		PencilOutline,
		CloseCircleOutline,
		NcAppContent,
		NcDialog,
		List,
		NcCheckboxRadioSwitch,
		OfficeBuilding,
		ClientesDashboard,
		Cog,
		AccountMultiplePlusOutline,
		DatabaseExport,
		Upload,
		FileDocumentOutline,
		ChevronDown,
		ArrowLeft,
		VueTabs,
		VTab,
		NcButton,
		NcActions,
		NcActionButton,
		NcActionSeparator,
		FilterVariant,
		NcEmptyContent,
		ModalFactura,
		ModalPago,
		ModalHonorario,
		ModalReporteHonorario,
		ModalReporteMultiple,
		ClientDetailsHeader,
		ClientGeneralTab,
	},
	mixins: [permissionsMixin],

	data() {
		return {
			projectManagers: [],
			editing: false,
			saving: false,
			loading: true,
			settingsMenuOpen: false,
			listas: [],
			rawClients: [],
			select: [],
			options: [],
			modal: false,
			/* cliente */
			logoBust: Date.now(),
			sortOrder: { label: t('empleados', 'A to Z'), value: 'az' },
			tipoFiltro: { label: t('empleados', 'All customers'), value: 'todos' },
			estadoFiltro: { label: t('empleados', 'Active'), value: 'activos' },
			onlySpecial: false,
			/* honorarios */
			showListFilterModal: false,
			honorarios: [],
			loadingHonorarios: false,
			honorarioModal: false,
			savingHonorario: false,
			h_tipo_servicio: '',
			currencyOptions: [
				{ label: 'MXN', value: 'MXN' },
				{ label: 'USD', value: 'USD' },
				{ label: 'EUR', value: 'EUR' },
			],
			h_tipo_moneda: null,
			h_importe_total: '',
			h_fecha_inicio: '',
			h_fecha_fin: '',
			h_periodicidad: { label: t('empleados', 'Monthly'), value: 1 },
			periodicidadOptions: [
				{ label: t('empleados', 'Monthly'), value: 1 },
				{ label: t('empleados', 'Bimonthly'), value: 2 },
				{ label: t('empleados', 'Quarterly'), value: 3 },
				{ label: t('empleados', 'Annual'), value: 12 },
			],
			parcialidadesAbiertas: {},
			loadingParcialidades: {},
			parcialidades: {},
			meses: [
				{ label: t('empleados', 'January'), value: 1 },
				{ label: t('empleados', 'February'), value: 2 },
				{ label: t('empleados', 'March'), value: 3 },
				{ label: t('empleados', 'April'), value: 4 },
				{ label: t('empleados', 'May'), value: 5 },
				{ label: t('empleados', 'June'), value: 6 },
				{ label: t('empleados', 'July'), value: 7 },
				{ label: t('empleados', 'August'), value: 8 },
				{ label: t('empleados', 'September'), value: 9 },
				{ label: t('empleados', 'October'), value: 10 },
				{ label: t('empleados', 'November'), value: 11 },
				{ label: t('empleados', 'December'), value: 12 },
			],
			anios: Array.from({ length: 2070 - 2000 + 1 }, (_, i) => {
				const year = 2000 + i
				return {
					label: String(year),
					value: year,
				}
			}),
			h_mes_inicio: null,
			h_anio_inicio: { label: String(new Date().getFullYear()), value: new Date().getFullYear() },
			h_mes_fin: null,
			h_anio_fin: { label: String(new Date().getFullYear()), value: new Date().getFullYear() },
			showDeleteHonorarioDialog: false,
			honorarioToDelete: null,
			showFacturaModal: false,
			showPagoModal: false,
			fechaFactura: '',
			fechaPago: '',
			parcialidadSeleccionada: null,
			honorarioSeleccionado: null,
			detalleAbierto: {},
			showAdvancedFactura: false,
			facturaClientePagador: null,
			showCancelarFacturaDialog: false,
			parcialidadACancelarFactura: null,
			honorarioBorradorId: null,
			button: false,
			h_descripcion: '',
			h_titulo_anio: { label: String(new Date().getFullYear()), value: new Date().getFullYear() },
			h_especial: false,
			honorarioFilterModal: false,
			hf_busqueda: '',
			hf_estado: null,
			hf_estadoOptions: [
				{ label: t('empleados', 'Active'), value: 'activo' },
				{ label: t('empleados', 'Completed'), value: 'completado' },
			],
			hf_soloEspecial: false,
			hf_desde: '',
			hf_hasta: '',
			showCancelarPagoDialog: false,
			parcialidadACancelar: null,
			selectMode: false,
			selectedHonorarios: [],
			h_tipo_honorario: 'parcial', // 'parcial' | 'iguala' | 'eventual'
			tiposHonorario: [
				{ label: t('empleados', 'Installments'), value: 'parcial' },
				{ label: t('empleados', 'Retainer fee'), value: 'iguala' },
				{ label: t('empleados', 'One-time'), value: 'eventual' },
			],
			honorarioToFinalizar: null,
			showFinalizarDialog: false,
			hf_tipo: null,
			hf_tipoOptions: [
				{ label: t('empleados', 'Installments'), value: 'parcial' },
				{ label: t('empleados', 'Retainer fee'), value: 'iguala' },
				{ label: t('empleados', 'One-time'), value: 'eventual' },
			],
			editingHonorarioId: null,
			reporteModal: false,
			showImportarModal: false,
			honorarioParaReporte: null,
			generandoReporte: false,
			enviandoReporte: false,
			reporteMultipleModal: false,
			descargandoMultiple: false,
			notificandoMultiple: false,
			rep_departamento: null,
			rep_departamentoOptions: [],
			rep_asunto: '',
			rep_quienSolicita: '',
			rep_claveGerenteJunior: '',
			rep_nombreGerenteJunior: '',
			rep_claveSupervisorSenior: '',
			rep_nombreSupervisorSenior: '',
			rep_claveSupervisorJunior: '',
			rep_nombreSupervisorJunior: '',
			rep_claveOtro: '',
			rep_nombreOtro: '',
			rep_nombreGerente: '',
			rep_nombreSocio: '',
			showAdvancedPago: false,
			pagoClientePagador: null,
			pagosRealizados: [],
			loadingPagosRealizados: false,
			seccionesColapsadas: {
				grupos: false,
				individuales: false,
			},
			monedas: [],
		}
	},

	computed: {
		canAdminCustomers() {
			return this.canSee('clientes.admin')
		},

		canEditClientLogo() {
			return this.canAdminCustomers && this.editing && Boolean(this.selectedClient?.id)
		},

		/* ----------- Select Cliente ----------- */
		selectedClient() {
			return this.select?.[0] || {}
		},

		hasSelectedClient() {
			return Boolean(this.selectedClient?.id)
		},

		selectedClientType() {
			return Number(this.selectedClient?.cliente_padre || 0) === 0
				? t('empleados', 'Main group')
				: t('empleados', 'Sub-company')
		},

		selectedIsActive() {
			return Boolean(Number(this.selectedClient?.estado ?? 1))
		},

		parentName() {
			const parentId = this.selectedClient?.cliente_padre

			if (!parentId || Number(parentId) === 0) {
				return t('empleados', 'Main group')
			}

			return this.rawClients.find((client) => Number(client.id) === Number(parentId))?.nombre
				|| t('empleados', 'Not found')
		},

		childCompanies() {
			if (!this.selectedClient?.id) {
				return []
			}

			return this.rawClients.filter((client) => {
				return Number(client.cliente_padre || 0) === Number(this.selectedClient.id)
			})
		},

		parentOptions() {
			const currentId = this.selectedClient?.id

			return this.options.filter((option) => {
				return !currentId || Number(option.id) !== Number(currentId)
			})
		},
		modalTitle() {
			return this.editing
				? t('empleados', 'Edit company or group')
				: t('empleados', 'New company or group')
		},

		saveLabel() {
			return this.editing
				? t('empleados', 'Save changes')
				: t('empleados', 'Create')
		},

		sortOrderOptions() {
			return [
				{ label: t('empleados', 'A to Z'), value: 'az' },
				{ label: t('empleados', 'Z to A'), value: 'za' },
			]
		},

		tipoFiltroOptions() {
			return [
				{ label: t('empleados', 'All customers'), value: 'todos' },
				{ label: t('empleados', 'Only Main Groups'), value: 'grupos' },
				{ label: t('empleados', 'Only subsidiaries'), value: 'subsidiarias' },
			]
		},

		estadoFiltroOptions() {
			return [
				{ label: t('empleados', 'Active'), value: 'activos' },
				{ label: t('empleados', 'Only Disabled'), value: 'inactivos' },
				{ label: t('empleados', 'All'), value: 'todos' },
			]
		},

		listFilterCount() {
			return [
				(this.tipoFiltro?.value || 'todos') !== 'todos',
				(this.estadoFiltro?.value || 'activos') !== 'activos',
				this.onlySpecial,
			].filter(Boolean).length
		},

		filteredListas() {
			let data = [...this.listas]

			const estado = this.estadoFiltro?.value || 'activos'
			if (estado === 'activos') {
				data = data.filter(item => Number(item.estado ?? 1) === 1)
			} else if (estado === 'inactivos') {
				data = data.filter(item => Number(item.estado ?? 1) === 0)
			}

			const tipo = this.tipoFiltro?.value || 'todos'
			if (tipo === 'grupos') {
				data = data.filter(item =>
					Number(item.cliente_padre || 0) === 0,
				)
			} else if (tipo === 'subsidiarias') {
				data = data.filter(item =>
					Number(item.cliente_padre || 0) !== 0,
				)
			}

			if (this.onlySpecial) {
				data = data.filter(item =>
					Number(item.especial || 0) === 1,
				)
			}

			const direction = this.sortOrder?.value === 'za' ? -1 : 1
			const compareNames = (a, b) => {
				const nameA = (a.nombre || a.name || '').toLowerCase()
				const nameB = (b.nombre || b.name || '').toLowerCase()
				return nameA.localeCompare(nameB) * direction
			}

			if (tipo !== 'todos') {
				return [...data].sort(compareNames)
			}

			return this.buildHierarchy(data, compareNames)
		},
		/* --------------- Honorarios --------------- */
		deleteHonorarioButtons() {
			return [
				{
					label: t('empleados', 'Cancel'),
					callback: () => {
						this.showDeleteHonorarioDialog = false
					},
				},
				{
					label: t('empleados', 'Delete'),
					type: 'primary',
					callback: () => {
						this.confirmDeleteHonorario()
					},
				},
			]
		},

		totalMesesRango() {
			if (!this.h_mes_inicio || !this.h_anio_inicio || !this.h_mes_fin || !this.h_anio_fin) {
				return 0
			}
			const inicioYear = this.h_anio_inicio.value
			const inicioMes = this.h_mes_inicio.value
			const finYear = this.h_anio_fin.value
			const finMes = this.h_mes_fin.value

			if (finYear < inicioYear || (finYear === inicioYear && finMes < inicioMes)) {
				return 0
			}
			return (finYear - inicioYear) * 12 + (finMes - inicioMes) + 1
		},

		periodBreakdown() {
			const totalMeses = this.totalMesesRango
			const periodo = this.h_periodicidad?.value || 1
			if (!totalMeses || !periodo) return []

			const grupos = []
			let restante = totalMeses
			while (restante > 0) {
				grupos.push(Math.min(periodo, restante))
				restante -= periodo
			}
			return grupos
		},

		h_numero_parcialidades() {
			return this.periodBreakdown.length
		},

		periodAmounts() {
			const total = Number(this.h_importe_total || 0)
			const totalMeses = this.totalMesesRango
			if (!total || !totalMeses) return []

			const porMes = total / totalMeses
			return this.periodBreakdown.map(meses => Math.round(porMes * meses * 100) / 100)
		},

		isHonorarioValid() {
			if (this.editingHonorarioId) {
				return Boolean(this.h_tipo_moneda)
			}

			const base = Number(this.h_importe_total) > 0
				&& Boolean(this.h_tipo_moneda)
				&& Boolean(this.h_mes_inicio)
				&& Boolean(this.h_anio_inicio)

			if (this.h_tipo_honorario === 'parcial') {
				return base && Boolean(this.h_mes_fin) && Boolean(this.h_anio_fin)
			}

			return base
		},

		projectManager() {
			return this.projectManagers.find(
				(emp) => Number(emp.value) === Number(this.selectedClient?.lider_proyecto),
			) || null
		},

		selectedClientCollaborators() {
			const colabs = Array.isArray(this.selectedClient?.colaboradores)
				? this.selectedClient.colaboradores
				: []

			return this.projectManagers.filter(emp =>
				colabs.includes(emp.value) || colabs.includes(Number(emp.value)),
			)
		},

		collaboratorOptions() {
			if (!this.lider_proyecto) {
				return this.projectManagers
			}

			const leaderId = Number(this.lider_proyecto.value ?? this.lider_proyecto)

			return this.projectManagers.filter(
				emp => Number(emp.value) !== leaderId,
			)
		},

		filteredHonorarios() {
			let data = [...this.honorarios]

			if (this.hf_busqueda.trim()) {
				const q = this.hf_busqueda.trim().toLowerCase()
				data = data.filter(h => (h.tipo_servicio || '').toLowerCase().includes(q))
			}

			if (this.hf_estado) {
				data = data.filter(h => {
					const activo = Number(h.activo) === 1
					return this.hf_estado.value === 'activo' ? activo : !activo
				})
			}

			if (this.hf_tipo) {
				data = data.filter(h => (h.tipo_honorario || 'parcial') === this.hf_tipo.value)
			}

			if (this.hf_soloEspecial) {
				data = data.filter(h => Number(h.especial) === 1)
			}

			if (this.hf_desde) {
				data = data.filter(h => h.fecha_inicio >= this.hf_desde)
			}

			if (this.hf_hasta) {
				data = data.filter(h => h.fecha_fin <= this.hf_hasta)
			}

			return data
		},

		honorarioFilterCount() {
			return (this.hf_busqueda.trim() ? 1 : 0)
				+ (this.hf_estado ? 1 : 0)
				+ (this.hf_tipo ? 1 : 0)
				+ (this.hf_soloEspecial ? 1 : 0)
				+ (this.hf_desde ? 1 : 0)
				+ (this.hf_hasta ? 1 : 0)
		},

		isEditingHonorario() {
			return Boolean(this.editingHonorarioId)
		},

		honorarioModalTitle() {
			return this.editingHonorarioId
				? t('empleados', 'Edit service fee')
				: t('empleados', 'New service fee')
		},

		honorarioSaveLabel() {
			if (this.savingHonorario) {
				return t('empleados', 'Saving...')
			}
			return this.editingHonorarioId
				? t('empleados', 'Save changes')
				: t('empleados', 'Create fee')
		},

		clientesPagadorOptions() {
			const currentId = this.selectedClient?.id
			return this.options.filter(o => !currentId || Number(o.value) !== Number(currentId))
		},

		honorariosSeleccionadosDetalle() {
			return this.honorarios.filter(h => this.selectedHonorarios.includes(h.id_honorario))
		},

		allFilteredSelected() {
			return this.filteredHonorarios.length > 0
				&& this.filteredHonorarios.every(h => this.selectedHonorarios.includes(h.id_honorario))
		},

		someFilteredSelected() {
			return this.filteredHonorarios.some(h => this.selectedHonorarios.includes(h.id_honorario))
				&& !this.allFilteredSelected
		},
	},

	watch: {
		'selectedClient.id'(newId) {
			this.editing = false
			this.honorarios = []
			this.parcialidadesAbiertas = {}
			this.parcialidades = {}
			this.loadingParcialidades = {}
			this.pagosRealizados = []

			if (newId) {
				this.GetHonorariosByCliente(newId)
				this.GetPagosRealizadosPorCliente(newId)
			}
		},

	},

	mounted() {
		this.$nextTick(() => {
			const content = document.querySelector('#app-content-vue')
				|| document.querySelector('.app-content')
				|| document.querySelector('main')

			if (content) {
				content.scrollTop = 0
			}
		})
		this._onDetails = (id) => this.GetCompanieGroup(id)
		this._onNew = () => this.openModal()
		this._onDelete = () => this.delete()
		this._onEdit = () => this.edit()
		this._onExport = () => this.Exportar()
		this._onImport = () => this.triggerImport()
		this.loadRequiredCustomerData()
		this.GetMonedas()

		window.addEventListener('keydown', this.onKeyDown)

		this.$root.$on('details', this._onDetails)
		if (this.canAdminCustomers) {
			this.$root.$on('new', this._onNew)
			this.$root.$on('delete', this._onDelete)
			this.$root.$on('edit', this._onEdit)
			this.$root.$on('exportlist', this._onExport)
			this.$root.$on('importlist', this._onImport)
		}

		this.loadRequiredCustomerData()

		this._onToggleEstado = () => this.toggleEstado()
		if (this.canAdminCustomers) {
			this.$root.$on('toggleEstado', this._onToggleEstado)
		}
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.onKeyDown)

		this.$root.$off('details', this._onDetails)
		this.$root.$off('new', this._onNew)
		this.$root.$off('delete', this._onDelete)
		this.$root.$off('edit', this._onEdit)
		this.$root.$off('exportlist', this._onExport)
		this.$root.$off('importlist', this._onImport)
		this.$root.$off('toggleEstado', this._onToggleEstado)
	},

	methods: {
		t, // Exponer i18n a la plantilla

		closeCompanyDetails() {
			this.select = []
			this.settingsMenuOpen = false
		},

		async GetMonedas() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetMonedas'))
				this.monedas = this.getOcsData(response) || []
			} catch (err) {
				this.monedas = []
			}
		},

		idMonedaPorTipo(tipoMoneda) {
			if (!tipoMoneda || tipoMoneda === 'MXN') return null
			const moneda = this.monedas.find(m => m.tipoMoneda === tipoMoneda)
			return moneda ? moneda.id : null
		},

		openCompanyFromDashboard(id) {
			this.GetCompanieGroup(id)
		},

		closeMenus() {
			this.button = false
			this.settingsMenuOpen = false
		},

		async loadRequiredCustomerData() {
			try {
				await Promise.all([
					this.GetCompaniesGroups(false),
					this.GetClientesEmpleadosLookup(false),
				])
				const routeId = Number(this.$route?.query?.id || 0)
				if (routeId > 0) {
					await this.GetCompanieGroup(routeId)
				}
			} catch (err) {
				showError(
					t('empleados', 'Error loading customer data: {error}', {
						error: String(err),
					}),
				)
			}
		},

		async GetAreasList() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetAreasList'))
				const areas = this.getOcsData(response) || []

				this.rep_departamentoOptions = areas.map((a) => ({
					label: a.Nombre,
					value: a.Nombre,
				}))
			} catch (err) {
				this.rep_departamentoOptions = []
				showError(t('empleados', 'Error loading departments: {error}', { error: String(err) }))
			}
		},

		AgregarNuevo() {
			this.closeMenus()
			this.$root.$emit('new', true)
		},

		onKeyDown(e) {
			if (e.key === 'Escape') {
				this.onEsc()
			}
		},

		formatImporte(valor) {
			return Number(valor).toLocaleString('es-MX', {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			})
		},

		triggerLogoUpload() {
			this.$refs.logoFile?.click()
		},

		async onLogoSelected(event) {
			const file = event.target?.files?.[0]
			event.target.value = ''
			if (!file || !this.selectedClient?.id) {
				return
			}

			try {
				const result = await clientesService.uploadLogo(this.selectedClient.id, file)
				const logo = result?.data?.logo || true
				this.logoBust = Date.now()
				this.patchClientLogo(this.selectedClient.id, logo)
				showSuccess(t('empleados', 'Logo saved successfully.'))
			} catch (err) {
				const message = err?.response?.data?.ocs?.data?.message
					|| err?.response?.data?.message
					|| String(err)
				showError(t('empleados', 'The logo could not be saved.') + ' ' + message)
			}
		},

		async removeClientLogo() {
			if (!this.selectedClient?.id) {
				return
			}

			try {
				await clientesService.deleteLogo(this.selectedClient.id)
				this.logoBust = Date.now()
				this.patchClientLogo(this.selectedClient.id, null)
				showSuccess(t('empleados', 'Logo deleted successfully.'))
			} catch (err) {
				showError(t('empleados', 'The logo could not be deleted.'))
			}
		},

		patchClientLogo(id, logo) {
			const apply = (item) => Number(item.id) === Number(id)
				? { ...item, logo, logoUrl: logo ? clienteLogoUrl(id, this.logoBust) : null }
				: item

			this.rawClients = this.rawClients.map(apply)
			this.listas = this.listas.map(apply)
			if (this.select?.[0] && Number(this.select[0].id) === Number(id)) {
				this.select = [{ ...this.select[0], logo, logoUrl: logo ? clienteLogoUrl(id, this.logoBust) : null }]
			}
		},
		formatTipoHonorario(tipo) {
			const labels = {
				parcial: t('empleados', 'Installments'),
				iguala: t('empleados', 'Retainer fee'),
				eventual: t('empleados', 'One-time'),
			}

			return labels[tipo || 'parcial'] || tipo || '-'
		},

		montoAcumulado(honorario) {
			const lista = this.parcialidades[honorario.id_honorario]

			if (Array.isArray(lista) && lista.length > 0) {
				return lista.reduce((sum, p) => sum + Number(p.importe_parcialidad || 0), 0)
			}

			// Aún no se han cargado las parcialidades en el front: usar el
			// valor que ya trajo el backend, o el importe_total como último recurso.
			const acumuladoBackend = Number(honorario.monto_acumulado || 0)
			return acumuladoBackend > 0 ? acumuladoBackend : Number(honorario.importe_total || 0)
		},

		onEsc() {
			if (this.modal) {
				this.closeModal()
				return
			}

			this.select = []
		},

		openModal() {
			this.editing = false
			this.modal = true
		},

		closeModal() {
			this.modal = false
			this.saving = false
		},

		edit() {
			if (!this.selectedClient?.id) {
				showError(t('empleados', 'Select a company or group first.'))
				return
			}
			this.editing = true
			this.modal = true
		},

		handleSaveCliente(payload) {
			return this.editing ? this.modify(payload) : this.create(payload)
		},

		async create(payload) {
			this.saving = true

			try {
				await axios.post(generateUrl('/apps/empleados/crearCliente'), payload)

				showSuccess(t('empleados', 'Company or group created successfully'))
				await this.GetCompaniesGroups()
				this.closeModal()
			} catch (err) {
				showError(t('empleados', 'Error creating company: {error}', { error: String(err) }))
			} finally {
				this.saving = false
			}
		},

		async modify(payload) {
			if (!this.selectedClient?.id) {
				showError(t('empleados', 'Select a company or group first.'))
				return
			}

			this.saving = true

			try {
				await axios.post(generateUrl('/apps/empleados/modificarCliente'), {
					id: this.selectedClient.id,
					...payload,
				})

				showSuccess(t('empleados', 'Company or group updated successfully'))
				await this.GetCompanieGroup(this.selectedClient.id)
				await this.GetCompaniesGroups()
				this.closeModal()
			} catch (err) {
				showError(t('empleados', 'Error updating company: {error}', { error: String(err) }))
			} finally {
				this.saving = false
			}
		},

		AbrirImportarModal() {
			this.closeMenus()
			this.showImportarModal = true
		},

		triggerImport() {
			this.closeMenus()
			this.$refs.file?.click()
		},

		getOcsData(response) {
			return response?.data?.ocs?.data ?? response?.data ?? null
		},

		async GetClientesEmpleadosLookup(showFailure = true) {
			try {
				const response = await axios.get(
					generateUrl('/apps/empleados/GetClientesEmpleadosLookup'),
				)

				const empleados = response?.data?.ocs?.data || []

				this.projectManagers = empleados.map((employee) => ({
					value: Number(employee.id_empleado),
					uid: employee.uid,
					label: employee.displayname || employee.uid,
					avatar: generateUrl(`/avatar/${employee.uid}/64`),
				}))
			} catch (err) {
				this.projectManagers = []
				if (showFailure) {
					showError(
						t('empleados', 'Error loading employees: {error}', {
							error: String(err),
						}),
					)
				} else {
					throw err
				}
			}
		},

		async GetCompanieGroup(id) {
			const clientId = Number(id)

			if (clientId < 0) {
				this.toggleSeccion(clientId === -1 ? 'grupos' : 'individuales')
				return
			}

			if (!Number.isFinite(clientId) || clientId <= 0) {
				return
			}

			const local = this.listas.find((item) => Number(item.id) === clientId)
				|| this.rawClients.find((item) => Number(item.id) === clientId)

			if (local && Number(local.estado ?? 1) === 0) {
				this.revealInactiveClients()
			}

			if (local) {
				this.select = [this.normalizeSelectedClient(local, clientId)]
				return
			}

			try {
				const response = await axios.post(generateUrl('/apps/empleados/GetCompanieGroup'), {
					id: clientId,
				})

				const data = this.getOcsData(response)
				const client = Array.isArray(data) ? data[0] : data
				if (!client || !Number(client.id)) {
					showError(t('empleados', 'Error loading company: {error}', { error: t('empleados', 'Client not found') }))
					return
				}

				if (Number(client.estado ?? 1) === 0) {
					this.revealInactiveClients()
				}

				this.select = [this.normalizeSelectedClient(client, Number(client.id))]
			} catch (err) {
				showError(t('empleados', 'Error loading company: {error}', { error: String(err) }))
			}
		},

		/** Un cliente inactivo abierto desde el tablero debe seguir visible en la lista. */
		revealInactiveClients() {
			if ((this.estadoFiltro?.value || 'activos') === 'activos') {
				this.estadoFiltro = this.estadoFiltroOptions.find((item) => item.value === 'todos')
			}
		},

		normalizeSelectedClient(client, clientId) {
			const id = Number(clientId || client.id)
			return {
				...client,
				id,
				name: client.name || client.nombre,
				count: client.count || client.child_count || 0,
				logoUrl: client.logoUrl || (client.logo ? clienteLogoUrl(id, this.logoBust) : null),
			}
		},

		buildHierarchy(data, compareNames) {
			const idsInSet = new Set(data.map(item => Number(item.id)))
			const byParent = new Map()

			data.forEach(item => {
				const rawParent = Number(item.cliente_padre || 0)
				const parentId = idsInSet.has(rawParent) ? rawParent : 0

				if (!byParent.has(parentId)) {
					byParent.set(parentId, [])
				}
				byParent.get(parentId).push(item)
			})

			const roots = byParent.get(0) || []
			const gruposRoots = roots.filter(r => (byParent.get(Number(r.id)) || []).length > 0)
			const individualesRoots = roots.filter(r => (byParent.get(Number(r.id)) || []).length === 0)

			gruposRoots.sort(compareNames)
			individualesRoots.sort(compareNames)

			const result = []

			const appendChildren = (parentId, level) => {
				const children = (byParent.get(parentId) || []).slice().sort(compareNames)

				children.forEach(child => {
					result.push({
						...child,
						name: this.indentedName(child.nombre || child.name, level),
					})
					appendChildren(Number(child.id), level + 1)
				})
			}

			// --- Sección: Groups ---
			result.push(this.buildSectionHeader('grupos', t('empleados', 'Groups'), gruposRoots.length))
			if (!this.seccionesColapsadas.grupos) {
				gruposRoots.forEach(root => {
					result.push({ ...root, name: root.nombre || root.name })
					appendChildren(Number(root.id), 1)
				})
			}

			// --- Sección: Individual companies ---
			result.push(this.buildSectionHeader('individuales', t('empleados', 'Individual companies'), individualesRoots.length))
			if (!this.seccionesColapsadas.individuales) {
				individualesRoots.forEach(root => {
					result.push({ ...root, name: root.nombre || root.name })
				})
			}

			return result
		},

		buildSectionHeader(key, label, count) {
			const colapsada = this.seccionesColapsadas[key]
			const icono = colapsada ? '▸' : '▾'

			return {
				id: this.sectionHeaderId(key),
				estado: 1,
				especial: 0,
				child_count: 0,
				esSeccion: true,
				name: `${icono}  —— ${label} (${count}) ——`,
			}
		},

		sectionHeaderId(key) {
			return key === 'grupos' ? -1 : -2
		},

		indentedName(nombre, level) {
			if (level <= 0) {
				return nombre
			}
			const indent = '\u00A0\u00A0\u00A0'.repeat(level - 1)
			return `${indent}› ${nombre}`
		},

		toggleSeccion(key) {
			this.seccionesColapsadas = {
				...this.seccionesColapsadas,
				[key]: !this.seccionesColapsadas[key],
			}
		},

		async GetCompaniesGroups(showFailure = true) {
			this.loading = true

			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetCompaniesGroups'))

				const data = Array.isArray(response?.data?.ocs?.data)
					? response.data.ocs.data
					: []

				this.rawClients = data

				this.listas = data.map((item) => ({
					id: item.id,
					name: item.nombre,
					count: item.child_count || 0,
					logoUrl: item.logo ? clienteLogoUrl(item.id, item.logo) : null,
					...item,
				}))

				this.options = data.map((item) => ({
					value: item.id,
					id: item.id,
					label: item.nombre,
				}))
			} catch (err) {
				this.rawClients = []
				this.listas = []
				this.options = []
				if (showFailure) {
					showError(t('empleados', 'Error loading companies: {error}', { error: String(err) }))
				} else {
					throw err
				}
			} finally {
				this.loading = false
			}
		},

		async delete() {
			if (!this.selectedClient?.id) {
				showError(t('empleados', 'Select a company or group first.'))
				return
			}

			this.loading = true

			try {
				await axios.post(generateUrl('/apps/empleados/deleteCliente'), {
					id: this.selectedClient.id,
				})

				showSuccess(t('empleados', 'Company or group deleted successfully'))
				this.select = []
				await this.GetCompaniesGroups()

			} catch (err) {
				showError(t('empleados', 'Error deleting company: {error}', {
					error: String(err),
				}))
			} finally {
				this.loading = false
			}
		},

		async importar() {
			this.closeMenus()
			const file = this.$refs.file?.files?.[0]

			if (!file) {
				return
			}

			const formData = new FormData()
			formData.append('clientesfileXLSX', file)

			this.loading = true

			try {
				await axios.post(generateUrl('/apps/empleados/importarClientes'), formData, {
					headers: { 'Content-Type': 'multipart/form-data' },
				})

				showSuccess(t('empleados', 'Companies imported successfully'))
				await this.GetCompaniesGroups()
			} catch (err) {
				showError(t('empleados', 'Error importing companies: {error}', { error: String(err) }))
			} finally {
				this.loading = false

				if (this.$refs.file) {
					this.$refs.file.value = ''
				}
			}
		},

		async Exportar() {
			this.closeMenus()
			try {
				const response = await axios.get(generateUrl('/apps/empleados/Exportarclientes'), {
					responseType: 'blob',
				})

				const url = URL.createObjectURL(new Blob([response.data], {
					type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				}))

				const link = document.createElement('a')
				link.href = url
				link.setAttribute('download', 'clientes.xlsx')
				document.body.appendChild(link)
				link.click()
				link.remove()
				URL.revokeObjectURL(url)
			} catch (err) {
				showError(t('empleados', 'Error exporting companies: {error}', { error: String(err) }))
			}
		},

		openHonorarioModal() {
			if (!this.hasSelectedClient) {
				showError(t('empleados', 'Select a company or group first.'))
				return
			}

			this.resetHonorarioForm()
			this.honorarioModal = true
		},

		closeHonorarioModal() {
			this.honorarioModal = false
			this.savingHonorario = false
			this.honorarioBorradorId = null
			this.editingHonorarioId = null
		},

		resetHonorarioForm() {
			const currentYear = new Date().getFullYear()
			this.h_tipo_servicio = ''
			this.h_tipo_moneda = this.currencyOptions[0]
			this.h_importe_total = ''
			this.h_fecha_inicio = ''
			this.h_fecha_fin = ''
			this.h_mes_inicio = null
			this.h_anio_inicio = { label: String(currentYear), value: currentYear }
			this.h_mes_fin = null
			this.h_anio_fin = { label: String(currentYear), value: currentYear }
			this.h_titulo_anio = { label: String(currentYear), value: currentYear }
			this.h_descripcion = ''
			this.h_especial = false
			this.h_periodicidad = this.periodicidadOptions[0]
		},

		resetHonorarioFilters() {
			this.hf_busqueda = ''
			this.hf_estado = null
			this.hf_tipo = null
			this.hf_soloEspecial = false
			this.hf_desde = ''
			this.hf_hasta = ''
		},

		resetListFilters() {
			this.sortOrder = this.sortOrderOptions[0]
			this.tipoFiltro = this.tipoFiltroOptions[0]
			this.estadoFiltro = this.estadoFiltroOptions.find(o => o.value === 'activos') || this.estadoFiltroOptions[0]
			this.onlySpecial = false
		},

		async GetHonorariosByCliente(idCliente) {
			if (!idCliente) {
				this.honorarios = []
				return
			}

			this.loadingHonorarios = true

			try {
				const response = await axios.post(
					generateUrl('/apps/empleados/findHonorariosByCliente'),
					{ id_cliente: idCliente },
				)

				const data = this.getOcsData(response)
				this.honorarios = Array.isArray(data) ? data : []
			} catch (err) {
				showError(t('empleados', 'Error loading fees: {error}', { error: String(err) }))
				this.honorarios = []
			} finally {
				this.loadingHonorarios = false
			}
		},

		async crearHonorario() {
			if (!this.hasSelectedClient) {
				showError(t('empleados', 'Select a company or group first.'))
				return
			}

			if (!this.isHonorarioValid) {
				showError(t('empleados', 'Fill in amount, currency and dates.'))
				return
			}

			this.savingHonorario = true

			try {
				let fechaFin = ''
				if (this.h_tipo_honorario === 'parcial') {
					fechaFin = this.h_mes_fin && this.h_anio_fin
						? `${this.h_anio_fin.value}-${String(this.h_mes_fin.value).padStart(2, '0')}-01`
						: ''
				} else if (this.h_tipo_honorario === 'eventual') {
					fechaFin = this.h_mes_inicio && this.h_anio_inicio
						? `${this.h_anio_inicio.value}-${String(this.h_mes_inicio.value).padStart(2, '0')}-01`
						: ''
				}

				const payload = {
					id_cliente: this.selectedClient.id,
					importe_total: Number(this.h_importe_total),
					tipo_moneda: this.h_tipo_moneda?.value || 'MXN',
					especial: this.h_especial ? 1 : 0,
					tipo_honorario: this.h_tipo_honorario,
					fecha_inicio: this.h_mes_inicio && this.h_anio_inicio
						? `${this.h_anio_inicio.value}-${String(this.h_mes_inicio.value).padStart(2, '0')}-01`
						: '',
					fecha_fin: fechaFin,
					periodicidad_parcialidad: this.h_periodicidad?.value || 1,
					descripcion: String(this.h_descripcion || '').trim(),
					tipo_servicio: (() => {
						const base = String(this.h_tipo_servicio || '').trim()
						const anio = this.h_titulo_anio?.value || ''
						const sufijo = anio ? ` - ${anio}` : ''
						return base ? `${base}${sufijo}` : (sufijo.trim() || null)
					})(),
				}

				if (this.honorarioBorradorId) {
					await axios.post(generateUrl('/apps/empleados/completarHonorario'), {
						...payload,
						id_honorario: this.honorarioBorradorId,
					})
				} else {
					await axios.post(generateUrl('/apps/empleados/crearHonorario'), payload)
				}

				showSuccess(t('empleados', 'Service fee created successfully'))
				await this.GetHonorariosByCliente(this.selectedClient.id)
				this.closeHonorarioModal()
			} catch (err) {
				showError(t('empleados', 'Error creating fee: {error}', { error: String(err) }))
			} finally {
				this.savingHonorario = false
			}
		},

		askDeleteHonorario(idHonorario) {
			this.honorarioToDelete = idHonorario
			this.showDeleteHonorarioDialog = true
		},

		async confirmDeleteHonorario() {
			if (!this.honorarioToDelete) {
				return
			}

			try {
				await axios.post(generateUrl('/apps/empleados/deleteHonorario'), {
					id_honorario: this.honorarioToDelete,
				})

				showSuccess(
					t('empleados', 'Service fee deleted successfully'),
				)

				delete this.parcialidadesAbiertas[this.honorarioToDelete]
				delete this.parcialidades[this.honorarioToDelete]

				await this.GetHonorariosByCliente(
					this.selectedClient.id,
				)
			} catch (err) {
				showError(
					t('empleados', 'Error deleting fee: {error}', {
						error: String(err),
					}),
				)
			} finally {
				this.showDeleteHonorarioDialog = false
				this.honorarioToDelete = null
			}
		},

		async toggleParcialidades(idHonorario) {
			const abierto = !this.parcialidadesAbiertas[idHonorario]

			this.parcialidadesAbiertas = {
				...this.parcialidadesAbiertas,
				[idHonorario]: abierto,
			}

			if (abierto && !this.parcialidades[idHonorario]) {
				await this.GetParcialidades(idHonorario)
			}
		},

		completarHonorarioBorrador(honorario) {
			this.resetHonorarioForm()

			// Precargar el importe que ya viene del excel
			this.h_importe_total = String(honorario.importe_total)

			// Guardar referencia para saber que es edición, no creación
			this.honorarioBorradorId = honorario.id_honorario

			this.honorarioModal = true
		},

		async GetParcialidades(idHonorario) {
			this.loadingParcialidades = {
				...this.loadingParcialidades,
				[idHonorario]: true,
			}

			try {
				const response = await axios.post(
					generateUrl('/apps/empleados/findParcialidadesByHonorario'),
					{ id_honorario: idHonorario },
				)

				const data = this.getOcsData(response)

				this.parcialidades = {
					...this.parcialidades,
					[idHonorario]: Array.isArray(data) ? data : [],
				}
			} catch (err) {
				showError(t('empleados', 'Error loading installments: {error}', { error: String(err) }))
			} finally {
				this.loadingParcialidades = {
					...this.loadingParcialidades,
					[idHonorario]: false,
				}
			}
		},

		abrirFacturaModal(p, idHonorario) {
			this.parcialidadSeleccionada = p.id_parcialidad
			this.honorarioSeleccionado = idHonorario
			this.fechaFactura = new Date().toISOString().split('T')[0]
			this.showAdvancedFactura = false
			this.facturaClientePagador = null
			this.showFacturaModal = true
		},

		async confirmarFacturaModal() {
			try {
				await axios.post(
					generateUrl('/apps/empleados/marcarParcialidadFacturada'),
					{
						id_parcialidad: this.parcialidadSeleccionada,
						fecha_factura: this.fechaFactura,
						id_cliente_pagador: this.facturaClientePagador?.value ?? null,
					},
				)

				this.showFacturaModal = false
				await this.GetParcialidades(this.honorarioSeleccionado)
				await this.GetHonorariosByCliente(this.selectedClient.id)
				showSuccess(t('empleados', 'Installment marked as invoiced'))
			} catch (err) {
				showError(String(err))
			}
		},

		editarFechaFactura(p, idHonorario) {
			this.parcialidadSeleccionada = p.id_parcialidad
			this.honorarioSeleccionado = idHonorario
			this.fechaFactura = p.fecha_factura || new Date().toISOString().split('T')[0]
			this.showAdvancedFactura = Boolean(p.id_cliente_pagador)
			this.facturaClientePagador = p.id_cliente_pagador
				? this.options.find(o => Number(o.value) === Number(p.id_cliente_pagador)) || null
				: null
			this.showFacturaModal = true
		},

		askCancelarFactura(p, idHonorario) {
			this.parcialidadACancelarFactura = p.id_parcialidad
			this.honorarioSeleccionado = idHonorario
			this.showCancelarFacturaDialog = true
		},

		async confirmarCancelarFactura() {
			try {
				await axios.post(
					generateUrl('/apps/empleados/cancelarFacturaParcialidad'),
					{ id_parcialidad: this.parcialidadACancelarFactura },
				)

				await this.GetParcialidades(this.honorarioSeleccionado)
				await this.GetHonorariosByCliente(this.selectedClient.id)

				showSuccess(t('empleados', 'Invoice cancelled'))
			} catch (err) {
				showError(t('empleados', 'Error cancelling invoice: {error}', { error: String(err) }))
			} finally {
				this.showCancelarFacturaDialog = false
				this.parcialidadACancelarFactura = null
			}
		},

		abrirPagoModal(p, idHonorario) {
			this.parcialidadSeleccionada = p.id_parcialidad
			this.honorarioSeleccionado = idHonorario
			this.fechaPago = new Date().toISOString().split('T')[0]
			this.showPagoModal = true
		},

		async confirmarPagoModal() {
			try {
				const honorario = this.honorarios.find(h => h.id_honorario === this.honorarioSeleccionado)
				const idMoneda = this.idMonedaPorTipo(honorario?.tipo_moneda)

				await axios.post(
					generateUrl('/apps/empleados/marcarParcialidadPagada'),
					{
						id_parcialidad: this.parcialidadSeleccionada,
						fecha_pago: this.fechaPago,
						id_moneda: idMoneda,
					},
				)

				this.showPagoModal = false
				await this.GetParcialidades(this.honorarioSeleccionado)
				await this.GetHonorariosByCliente(this.selectedClient.id)
				showSuccess(t('empleados', 'Installment marked as paid'))
			} catch (err) {
				showError(String(err))
			}
		},

		montoMXN(p) {
			if (p.cambio_moneda === null || p.cambio_moneda === undefined) return null
			return Number(p.importe_parcialidad) * Number(p.cambio_moneda)
		},

		montoTotalMXN(honorario) {
			if (Number(honorario.activo) !== 0) return null
			if (honorario.cambio_moneda === null || honorario.cambio_moneda === undefined) return null
			return Number(honorario.cambio_moneda)
		},

		editarFechaPago(p, idHonorario) {
			this.parcialidadSeleccionada = p.id_parcialidad
			this.honorarioSeleccionado = idHonorario
			this.fechaPago = p.fecha_pago || new Date().toISOString().split('T')[0]
			this.showPagoModal = true
		},

		askCancelarPago(p, idHonorario) {
			this.parcialidadACancelar = p.id_parcialidad
			this.honorarioSeleccionado = idHonorario
			this.showCancelarPagoDialog = true
		},

		async confirmarCancelarPago() {
			try {
				await axios.post(
					generateUrl('/apps/empleados/cancelarPagoParcialidad'),
					{ id_parcialidad: this.parcialidadACancelar },
				)

				await this.GetParcialidades(this.honorarioSeleccionado)
				await this.GetHonorariosByCliente(this.selectedClient.id)

				showSuccess(t('empleados', 'Payment cancelled'))
			} catch (err) {
				showError(t('empleados', 'Error cancelling payment: {error}', { error: String(err) }))
			} finally {
				this.showCancelarPagoDialog = false
				this.parcialidadACancelar = null
			}
		},

		toggleDetalleParcialidad(idParcialidad) {
			this.$set(
				this.detalleAbierto,
				idParcialidad,
				!this.detalleAbierto[idParcialidad],
			)
		},

		async toggleEstado() {
			if (!this.selectedClient?.id) {
				showError(t('empleados', 'Select a company or group first.'))
				return
			}

			try {
				await axios.post(generateUrl('/apps/empleados/modificarCliente'), {
					id: this.selectedClient.id,
					nombre: this.selectedClient.nombre,
					razon_social: this.selectedClient.razon_social || '',
					lider_proyecto: this.selectedClient.lider_proyecto || null,
					colaboradores: JSON.stringify(
						Array.isArray(this.selectedClient.colaboradores)
							? this.selectedClient.colaboradores
							: [],
					),
					nombre_contacto: this.selectedClient.nombre_contacto || '',
					telefono: this.selectedClient.telefono || '',
					correo: this.selectedClient.correo || '',
					rfc: this.selectedClient.rfc || '',
					ubicacion: this.selectedClient.ubicacion || '',
					detalles: this.selectedClient.detalles || '',
					especial: Number(this.selectedClient.especial) || 0,
					cliente_padre: this.selectedClient.cliente_padre || null,
					estado: this.selectedIsActive ? 0 : 1,
				})

				showSuccess(
					this.selectedIsActive
						? t('empleados', 'Company disabled successfully')
						: t('empleados', 'Company enabled successfully'),
				)

				await this.GetCompaniesGroups()
				await this.GetCompanieGroup(this.selectedClient.id)
			} catch (err) {
				showError(t('empleados', 'Error updating status: {error}', { error: String(err) }))
			}
		},

		toggleSelectMode() {
			this.selectMode = !this.selectMode
			this.selectedHonorarios = []
		},

		toggleSeleccionHonorario(id) {
			const idx = this.selectedHonorarios.indexOf(id)
			if (idx === -1) {
				this.selectedHonorarios.push(id)
			} else {
				this.selectedHonorarios.splice(idx, 1)
			}
		},

		toggleSelectAll(checked) {
			const idsFiltrados = this.filteredHonorarios.map(h => h.id_honorario)

			if (checked) {
				const nuevos = idsFiltrados.filter(id => !this.selectedHonorarios.includes(id))
				this.selectedHonorarios = [...this.selectedHonorarios, ...nuevos]
			} else {
				this.selectedHonorarios = this.selectedHonorarios.filter(id => !idsFiltrados.includes(id))
			}
		},

		abrirReporteMultiple() {
			if (this.selectedHonorarios.length === 0) {
				return
			}
			this.reporteMultipleModal = true
		},

		async descargarSolicitudesMultiples() {
			if (this.selectedHonorarios.length === 0) {
				return
			}

			this.descargandoMultiple = true

			try {
				const response = await axios.post(
					generateUrl('/apps/empleados/descargarSolicitudesMultiples'),
					{ ids: this.selectedHonorarios },
					{ responseType: 'blob' },
				)

				if (response.data.type === 'application/json') {
					const text = await response.data.text()
					const parsed = JSON.parse(text)
					throw new Error(parsed?.ocs?.data?.message || parsed?.message || 'Error desconocido')
				}

				const url = URL.createObjectURL(new Blob([response.data], { type: 'application/zip' }))

				const link = document.createElement('a')
				link.href = url
				link.setAttribute('download', `Solicitudes_Recibo_${new Date().toISOString().split('T')[0]}.zip`)
				document.body.appendChild(link)
				link.click()
				link.remove()
				URL.revokeObjectURL(url)

				this.reporteMultipleModal = false
				this.selectMode = false
				this.selectedHonorarios = []
			} catch (err) {
				showError(t('empleados', 'Error downloading requests: {error}', { error: String(err) }))
			} finally {
				this.descargandoMultiple = false
			}
		},

		async notificarHonorariosPendientes() {
			if (this.selectedHonorarios.length === 0) {
				return
			}

			this.notificandoMultiple = true

			try {
				const response = await axios.post(
					generateUrl('/apps/empleados/notificarHonorariosPendientes'),
					{ ids: this.selectedHonorarios },
				)

				const data = this.getOcsData(response) || response.data

				showSuccess(
					t('empleados', 'Notification sent to {n} recipient(s)', { n: data?.sent ?? 0 }),
				)

				this.reporteMultipleModal = false
				this.selectMode = false
				this.selectedHonorarios = []
			} catch (err) {
				showError(t('empleados', 'Error sending notification: {error}', { error: String(err) }))
			} finally {
				this.notificandoMultiple = false
			}
		},

		async agregarParcialidadIguala(idHonorario) {
			try {
				await axios.post(
					generateUrl('/apps/empleados/agregarParcialidadIguala'),
					{ id_honorario: idHonorario },
				)
				await this.GetParcialidades(idHonorario)
				showSuccess(t('empleados', 'Installment added'))
			} catch (err) {
				showError(t('empleados', 'Error adding installment: {error}', { error: String(err) }))
			}
		},

		askFinalizarHonorario(idHonorario) {
			this.honorarioToFinalizar = idHonorario
			this.showFinalizarDialog = true
		},

		async confirmarFinalizarHonorario() {
			try {
				await axios.post(
					generateUrl('/apps/empleados/finalizarHonorario'),
					{ id_honorario: this.honorarioToFinalizar },
				)
				await this.GetHonorariosByCliente(this.selectedClient.id)
				showSuccess(t('empleados', 'Fee finalized'))
			} catch (err) {
				showError(t('empleados', 'Error finalizing fee: {error}', { error: String(err) }))
			} finally {
				this.showFinalizarDialog = false
				this.honorarioToFinalizar = null
			}
		},

		async reactivarHonorario(idHonorario) {
			try {
				await axios.post(
					generateUrl('/apps/empleados/reactivarHonorario'),
					{ id_honorario: idHonorario },
				)
				await this.GetHonorariosByCliente(this.selectedClient.id)
				showSuccess(t('empleados', 'Fee reactivated'))
			} catch (err) {
				showError(t('empleados', 'Error reactivating fee: {error}', { error: String(err) }))
			}
		},

		abrirModificarHonorario(honorario) {
			this.resetHonorarioForm()

			this.editingHonorarioId = honorario.id_honorario
			this.h_tipo_honorario = honorario.tipo_honorario || 'parcial'
			this.h_especial = Boolean(Number(honorario.especial))
			this.h_importe_total = String(honorario.importe_total)
			this.h_descripcion = honorario.descripcion || ''

			this.h_tipo_moneda = this.currencyOptions.find(c => c.value === honorario.tipo_moneda)
				|| this.currencyOptions[0]

			const raw = String(honorario.tipo_servicio || '')
			const match = raw.match(/^(.*?)(?:\s-\s(\d{4}))?$/)

			this.h_tipo_servicio = match && match[1] ? match[1].trim() : raw

			if (match && match[2]) {
				this.h_titulo_anio = { label: match[2], value: Number(match[2]) }
			}

			if (honorario.fecha_inicio) {
				const [anio, mes] = honorario.fecha_inicio.split('-')
				this.h_anio_inicio = { label: anio, value: Number(anio) }
				this.h_mes_inicio = this.meses.find(m => m.value === Number(mes)) || null
			}

			if (honorario.fecha_fin) {
				const [anio, mes] = honorario.fecha_fin.split('-')
				this.h_anio_fin = { label: anio, value: Number(anio) }
				this.h_mes_fin = this.meses.find(m => m.value === Number(mes)) || null
			}

			this.honorarioModal = true
		},

		handleSaveHonorario() {
			return this.editingHonorarioId
				? this.guardarModificacionHonorario()
				: this.crearHonorario()
		},

		async guardarModificacionHonorario() {
			this.savingHonorario = true

			try {
				const tipoServicioFinal = (() => {
					const base = String(this.h_tipo_servicio || '').trim()
					const anio = this.h_titulo_anio?.value || ''
					const sufijo = anio ? ` - ${anio}` : ''
					return base ? `${base}${sufijo}` : (sufijo.trim() || null)
				})()

				await axios.post(generateUrl('/apps/empleados/actualizarMetadatosHonorario'), {
					id_honorario: this.editingHonorarioId,
					tipo_servicio: tipoServicioFinal,
					descripcion: String(this.h_descripcion || '').trim(),
					tipo_moneda: this.h_tipo_moneda?.value || 'MXN',
					especial: this.h_especial ? 1 : 0,
				})

				showSuccess(t('empleados', 'Service fee updated successfully'))
				await this.GetHonorariosByCliente(this.selectedClient.id)
				this.closeHonorarioModal()
			} catch (err) {
				showError(t('empleados', 'Error updating fee: {error}', { error: String(err) }))
			} finally {
				this.savingHonorario = false
			}
		},

		async abrirReporteHonorario(honorario) {
			if (this.rep_departamentoOptions.length === 0) {
				await this.GetAreasList()
			}

			this.honorarioParaReporte = honorario
			this.rep_departamento = null
			this.rep_asunto = honorario.tipo_servicio || ''
			this.rep_quienSolicita = ''
			this.rep_claveGerenteJunior = ''
			this.rep_nombreGerenteJunior = ''
			this.rep_claveSupervisorSenior = ''
			this.rep_nombreSupervisorSenior = ''
			this.rep_claveSupervisorJunior = ''
			this.rep_nombreSupervisorJunior = ''
			this.rep_claveOtro = ''
			this.rep_nombreOtro = ''
			this.rep_nombreGerente = ''
			this.rep_nombreSocio = ''
			this.reporteModal = true

			this.$nextTick(() => {
				if (document.activeElement && typeof document.activeElement.blur === 'function') {
					document.activeElement.blur()
				}
			})
		},

		async generarReporte() {
			if (!this.honorarioParaReporte?.id_honorario) {
				showError(t('empleados', 'No fee selected for the report.'))
				return
			}

			this.generandoReporte = true

			try {
				const response = await axios.get(
					generateUrl('/apps/empleados/generarSolicitudRecibo'),
					{
						responseType: 'blob',
						params: {
							id_honorario: this.honorarioParaReporte.id_honorario,
							departamento: this.rep_departamento?.value || null,
							asunto: this.rep_asunto || null,
							quienSolicita: this.rep_quienSolicita || null,
							claveGerenteJunior: this.rep_claveGerenteJunior || null,
							nombreGerenteJunior: this.rep_nombreGerenteJunior || null,
							claveSupervisorSenior: this.rep_claveSupervisorSenior || null,
							nombreSupervisorSenior: this.rep_nombreSupervisorSenior || null,
							claveSupervisorJunior: this.rep_claveSupervisorJunior || null,
							nombreSupervisorJunior: this.rep_nombreSupervisorJunior || null,
							claveOtro: this.rep_claveOtro || null,
							nombreOtro: this.rep_nombreOtro || null,
							nombreGerente: this.rep_nombreGerente || null,
							nombreSocio: this.rep_nombreSocio || null,
						},
					},
				)

				// Si el backend regresó un error, el blob real es JSON, no xlsx
				if (response.data.type === 'application/json') {
					const text = await response.data.text()
					const parsed = JSON.parse(text)
					throw new Error(parsed?.ocs?.data?.message || parsed?.message || 'Error desconocido')
				}

				const url = URL.createObjectURL(new Blob([response.data], {
					type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				}))

				const nombreCliente = (this.selectedClient?.nombre || 'cliente')
					.replace(/[^A-Za-z0-9_]+/g, '_')

				const link = document.createElement('a')
				link.href = url
				link.setAttribute('download', `Solicitud_Recibo_${nombreCliente}.xlsx`)
				document.body.appendChild(link)
				link.click()
				link.remove()
				URL.revokeObjectURL(url)

				this.reporteModal = false
				await this.GetHonorariosByCliente(this.selectedClient.id)
			} catch (err) {
				showError(t('empleados', 'Error generating report: {error}', { error: String(err) }))
			} finally {
				this.generandoReporte = false
			}
		},

		async enviarSolicitudHonorario() {
			if (!this.honorarioParaReporte?.id_honorario) {
				showError(t('empleados', 'No fee selected for the request.'))
				return
			}

			this.enviandoReporte = true

			try {
				const response = await axios.post(
					generateUrl('/apps/empleados/enviarSolicitudRecibo'),
					{
						id_honorario: this.honorarioParaReporte.id_honorario,
						departamento: this.rep_departamento?.value || null,
						asunto: this.rep_asunto || null,
						quienSolicita: this.rep_quienSolicita || null,
						claveGerenteJunior: this.rep_claveGerenteJunior || null,
						nombreGerenteJunior: this.rep_nombreGerenteJunior || null,
						claveSupervisorSenior: this.rep_claveSupervisorSenior || null,
						nombreSupervisorSenior: this.rep_nombreSupervisorSenior || null,
						claveSupervisorJunior: this.rep_claveSupervisorJunior || null,
						nombreSupervisorJunior: this.rep_nombreSupervisorJunior || null,
						claveOtro: this.rep_claveOtro || null,
						nombreOtro: this.rep_nombreOtro || null,
						nombreGerente: this.rep_nombreGerente || null,
						nombreSocio: this.rep_nombreSocio || null,
					},
				)

				const data = this.getOcsData(response) || response.data

				showSuccess(
					t('empleados', 'Request sent to {n} recipient(s)', { n: data?.sent ?? 0 }),
				)

				this.reporteModal = false
				await this.GetHonorariosByCliente(this.selectedClient.id)
			} catch (err) {
				showError(t('empleados', 'Error sending request: {error}', { error: String(err) }))
			} finally {
				this.enviandoReporte = false
			}
		},

		async GetPagosRealizadosPorCliente(idCliente) {
			this.loadingPagosRealizados = true
			try {
				const response = await axios.post(
					generateUrl('/apps/empleados/findParcialidadesPagadasPorCliente'),
					{ id_cliente: idCliente },
				)
				const data = this.getOcsData(response)
				this.pagosRealizados = Array.isArray(data) ? data : []
			} catch (err) {
				showError(t('empleados', 'Error loading payments made: {error}', { error: String(err) }))
				this.pagosRealizados = []
			} finally {
				this.loadingPagosRealizados = false
			}
		},

		irAClientePagador(idCliente) {
			this.GetCompanieGroup(idCliente)
		},

		irAClienteOriginal(idCliente) {
			this.GetCompanieGroup(idCliente)
		},
	},
}
</script>

<style scoped lang="scss">
.companies-page {
	flex-direction: column;
	gap: 24px;
}

.companies-main {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 16px;
	box-sizing: border-box;
}

.companies-toolbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	flex-wrap: wrap;
}

.companies-toolbar--details {
	padding-right: 56px;
}

.companies-toolbar__settings {
	margin-left: auto;
}

.section-label {
	font-size: 0.75rem;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	color: var(--color-primary-element);
	margin: 0;
}

.filter-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 18px;
	height: 18px;
	padding: 0 5px;
	border-radius: 999px;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-size: 0.7rem;
	font-weight: 700;
	margin-left: 4px;
}

.filter-section input[type='checkbox'] {
	width: 13px;
	height: 13px;
	margin: 0;
}

.filter-section select {
	width: 100%;
	height: 28px;
	box-sizing: border-box;
	padding: 1px 22px 1px 7px;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 12px;
}

/* ── Detail panel ── */
.client-details {
	display: flex;
	flex-direction: column;
	gap: 24px;
	padding: 16px;
}

.logo-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 0.35rem;
	margin-top: 0.5rem;
}

.logo-editor {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 0.75rem 1rem;
	margin: 0 0 1.25rem;
	padding: 0.75rem 0;
	border-bottom: 1px solid var(--color-border);
}

@media (max-width: 720px) {
	.companies-toolbar,
	.companies-toolbar--details {
		padding-right: 0;
	}

	.companies-toolbar__settings {
		margin-left: 0;
	}
}

/* ── Section heads ── */
.info-section,
.children-section {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin-bottom: 18px;
}

.section-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;

	h3 {
		margin: 2px 0 0;
		font-size: 1rem;
		font-weight: 600;
		color: var(--color-main-text);
	}
}

/* ── Honorarios ── */
.billing-section {
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.billing-head {
	padding-bottom: 12px;
	border-bottom: 1px solid var(--color-border);
}

.billing-title,
.billing-actions,
.select-bar__actions {
	display: flex;
	align-items: center;
	gap: 8px;
}

.billing-title {
	gap: 12px;
	min-width: 0;
}

.billing-title__icon {
	display: inline-flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	width: 40px;
	height: 40px;
	border-radius: 50%;
	background: var(--color-background-hover);
	color: var(--color-primary-element);
}

.billing-actions {
	flex-wrap: wrap;
	justify-content: flex-end;
}

.honorarios-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.honorario-card {
	border-radius: var(--border-radius-large);
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	overflow: hidden;
	transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;

	&:hover {
		border-color: color-mix(in srgb, var(--color-primary-element) 35%, var(--color-border));
		box-shadow: 0 2px 10px rgb(0 0 0 / 5%);
	}
}

.honorario-card--selected {
	border-color: var(--color-primary-element);
	box-shadow: 0 0 0 1px var(--color-primary-element);
}

.honorario-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 12px 14px;
	flex-wrap: wrap;
}

.honorario-info {
	display: flex;
	flex: 1 1 100%;
	flex-direction: column;
	gap: 2px;
	min-width: 0;

	.value-text {
		font-size: 0.9rem;
		font-weight: 600;
		color: var(--color-main-text);
	}

	span {
		font-size: 0.75rem;
		color: var(--color-text-maxcontrast);
	}
}

.honorario-date {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.honorario-meta {
	display: flex;
	flex: 1 1 100%;
	align-items: center;
	justify-content: flex-start;
	gap: 8px;
	flex-wrap: wrap;
}

.honorario-amount {
	margin-right: 4px;
	font-size: 0.9rem;
	font-weight: 700;
	color: var(--color-main-text);
}

.honorario-badge {
	display: inline-flex;
	align-items: center;
	padding: 2px 10px;
	border-radius: 999px;
	font-size: 0.72rem;
	font-weight: 600;

	&.badge-active {
		background: var(--color-primary-element-light);
		color: var(--color-primary-element);
	}

	&.badge-done {
		background: var(--color-background-hover);
		color: var(--color-text-maxcontrast);
	}
}

/* ── Parcialidades ── */
.parcialidades-list {
	border-top: 1px solid var(--color-border);
	display: flex;
	flex-direction: column;
	background: var(--color-background-soft);
}

.parcialidades-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
	padding: 8px 14px;
	border-bottom: 1px solid var(--color-border);
	color: var(--color-text-maxcontrast);
	font-size: 0.75rem;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.parcialidades-loading {
	padding: 12px 14px;
	font-size: 0.875rem;
	color: var(--color-text-maxcontrast);
}

.parcialidad-main {
	display: flex;
	align-items: center;
	gap: 12px;
	width: 100%;
}

.parcialidad-num-wrapper {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

.parcialidad-toggle {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	color: var(--color-text-maxcontrast, #666);
	transition: transform 0.2s ease;
	user-select: none;
}

/* cuando está abierto */
.parcialidad-toggle.open {
	transform: rotate(180deg);
}

.parcialidad-toggle:hover {
	color: var(--color-primary-element);
}

.parcialidad-row {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px 14px;
	border-bottom: 1px solid var(--color-border);
	flex-wrap: wrap;
	background: var(--color-main-background);

	&:last-child {
		border-bottom: none;
	}

	&.parcialidad-pagada {
		background: var(--color-background-hover);
	}
}

.parcialidad-num {
	font-size: 0.8rem;
	font-weight: 700;
	color: var(--color-primary-element);
	min-width: 28px;
}

.parcialidad-fechas {
	font-size: 0.8rem;
	color: var(--color-text-maxcontrast);
	flex: 1;
}

.parcialidad-importe {
	font-size: 0.875rem;
	font-weight: 600;
	color: var(--color-main-text);
	margin-left: auto;
}

.modal-content {
	display: flex;
	flex-direction: column;
	gap: 24px;
	padding: 24px;
}

.modal-header {
	display: flex;
	flex-direction: column;
	gap: 4px;

	h2 {
		margin: 4px 0;
		font-size: 1.25rem;
		font-weight: 700;
		color: var(--color-main-text);
	}

	p {
		margin: 0;
		font-size: 0.875rem;
		color: var(--color-text-maxcontrast);
	}
}

.form-grid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 12px;
	align-items: start;

	.span-2 {
		grid-column: span 2;
	}

	.aligned-select {
		align-self: end;
	}

	@media (max-width: 480px) {
		grid-template-columns: 1fr;

		.span-2 {
			grid-column: span 1;
		}
	}
}

.modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}

/* ── Misc ── */
.file-input {
	display: none;
}

.collaborators-block {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.collaborator-row {
	display: flex;
	align-items: flex-start;
	gap: 12px;
}

.collaborator-role {
	min-width: 130px;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	padding-top: 6px;
}

.collaborator-list {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
}

.collaborator-item {
	display: flex;
	align-items: center;
	gap: 8px;
	background: var(--color-background-hover);
	border-radius: 20px;
	padding: 4px 12px 4px 4px;
}

.collaborator-avatar {
	width: 28px;
	height: 28px;
	border-radius: 50%;
	object-fit: cover;
}

.date-label {
	font-size: 0.8rem;
	color: var(--color-text-maxcontrast);
	padding-left: 2px;
}

.btn-factura,
.btn-pagar {
	min-width: 120px !important;
	width: 120px !important;
	height: 28px !important;
	font-size: 0.75rem !important;
	justify-content: center !important;
}

.btn-factura {
	background-color: #21ba44 !important;
	color: #fff !important;
	border: none !important;
}

.btn-factura:hover {
	background-color: #1f973b !important;
}

.dialog-content {
	padding: 10px 0;
}

.parcialidad-detail-text {
	font-size: 0.8rem;
	color: var(--color-text-maxcontrast, #000000);
	line-height: 1.2;
}

.parcialidad-completada {
	color: #21ba44;
	font-weight: bold;
	font-size: 0.7rem;
}

.filter-icon-button {
	min-width: unset !important;
	padding-left: 4px !important;
	padding-right: 4px !important;
}

.filter-trigger {
	display: inline-flex;
	align-items: center;
	position: relative;
}

.companies-tabs {
	width: 100%;
	margin: 16px 0 24px;
}

.companies-tabs :deep(.tab-content) {
	padding-top: 16px;
}

.honorario-especial {
	border-left: 3px solid var(--color-primary-element);
	background: linear-gradient(
		90deg,
		var(--color-primary-element-light) 0%,
		var(--color-main-background) 34%
	);
}

.parcialidad-detalle {
	display: flex;
	flex-direction: column;
	gap: 6px;
	width: 100%;
	margin-top: 8px;
}

.parcialidad-detalle-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	width: 100%;
	padding: 8px 14px;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	background: var(--color-main-background);
}

.parcialidad-detalle-actions {
	margin-left: auto;
}

.select-bar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	background: var(--color-primary-element-light);
	border: 1px solid color-mix(in srgb, var(--color-primary-element) 30%, var(--color-border));
	border-radius: var(--border-radius-large);
	padding: 10px 14px;
	margin-bottom: 10px;
	font-size: 0.875rem;
	font-weight: 600;
	flex-wrap: wrap;
}

.select-all {
	display: flex;
	align-items: center;
	margin-right: auto;
}

.honorario-checkbox {
	width: 16px;
	height: 16px;
	margin-right: 4px;
	flex-shrink: 0;
	align-self: flex-start;
	margin-top: 4px;
}

.badge-tipo {
    background: #f3e8ff;
    color: #7c3aed;
    text-transform: capitalize;
}

.honorario-right-actions {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-left: auto;
}

.action-danger :deep(button) {
	color: #a82222 !important;
}

.action-danger :deep(.action-button__icon) {
	color: var(--color-error) !important;
}

.badge-tipo-parcial {
	background-color: #f3e8ff;
	color: #6b21a8;
}

.badge-tipo-iguala {
	background-color: #dcfce7;
	color: #15803d;
}

.badge-tipo-eventual {
	background-color: #fef9c3;
	color: #92400e;
}
.separator-top {
	margin-bottom: 20px;
}

.pagador-link {
	border: none;
	background: none;
	padding: 0;
	color: var(--color-primary-element);
	font-weight: 600;
	cursor: pointer;
	text-decoration: underline;
}

.honorario-descripcion {
	font-size: 0.75rem;
	color: var(--color-text-maxcontrast);
	white-space: normal;
	overflow-wrap: break-word;
	word-break: break-word;
}

.btn-solicitar :deep(button) {
	background-color: var(--color-primary-element-light) !important;
	color: var(--color-primary-element) !important;
	border: 1px solid color-mix(in srgb, var(--color-primary-element) 40%, transparent) !important;
}

.btn-solicitar :deep(button:hover) {
	background-color: color-mix(in srgb, var(--color-primary-element-light) 70%, var(--color-primary-element)) !important;
}

.parcialidad-importe--stacked {
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	line-height: 1.2;
}

.parcialidad-importe-principal {
	font-size: 0.95rem;
	font-weight: 600;
	color: #272727;
}

.parcialidad-importe-mxn {
	font-size: 0.85rem;
	font-weight: 500;
	color: #272727;
}

.honorario-amount-mxn {
	font-size: 0.85rem;
	font-weight: 500;
	color: #272727;
}

.children-grid {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.child-card {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 14px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-soft);
	border: 1px solid var(--color-border);
	cursor: pointer;
	text-align: left;
	width: 100%;
	transition: background 0.15s ease, border-color 0.15s ease;

	&:hover {
		background: var(--color-background-hover);
		border-color: var(--color-primary-element);
	}
}

.child-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 36px;
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	flex-shrink: 0;
}

.child-info {
	display: flex;
	flex-direction: column;
	gap: 2px;
	flex: 1;
	min-width: 0;

	.value-text {
		font-size: 0.875rem;
		font-weight: 600;
		color: var(--color-main-text);
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	span {
		font-size: 0.75rem;
		color: var(--color-text-maxcontrast);
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
}
</style>
