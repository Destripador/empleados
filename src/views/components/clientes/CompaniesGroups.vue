<template>
	<NcAppContent :name="t('empleados', 'Companies and groups')">
		<div class="companies-page">
			<List :loading="loading"
				:listas="filteredListas"
				:select="select"
				:show-options="true"
				:show-toggle-estado="true"
				:toggle-estado-label="selectedIsActive ? t('empleados', 'Disable') : t('empleados', 'Enable')"
				:defaultbuttons="false"
				:custom="true">
				<template #custom>
					<div class="empty">
						<div class="areas-empty-state">
							<div class="areas-empty-card">
								<img class="areas-empty-image"
									src="../../../../img/crowesito-think.png"
									alt="Empty area state">

								<h2>{{ t('empleados', 'Companies and groups') }}</h2>
								<h1>{{ t('empleados', 'Select a client for more details') }}</h1>

								<p class="areas-empty-description">
									{{ t('empleados', 'Choose a client, company or group from the list to view its information, assigned collaborators, service fees or edit its details.') }}
								</p>

								<div class="stats-grid">
									<div class="stat-card">
										<div class="stat-icon">
											<OfficeBuilding :size="22" />
										</div>
										<div>
											<span>{{ t('empleados', 'Total records') }}</span>
											<span class="value-text">{{ activeClients.length }}</span>
										</div>
									</div>

									<div class="stat-card">
										<div class="stat-icon">
											<HexagonMultipleOutline :size="22" />
										</div>
										<div>
											<span>{{ t('empleados', 'Main groups') }}</span>
											<span class="value-text">{{ mainGroups.length }}</span>
										</div>
									</div>

									<div class="stat-card">
										<div class="stat-icon">
											<AccountGroup :size="22" />
										</div>
										<div>
											<span>{{ t('empleados', 'Sub-companies') }}</span>
											<span class="value-text">{{ subCompanies.length }}</span>
										</div>
									</div>
								</div>

								<div class="areas-empty-actions">
									<NcButton type="primary" @click="GetCompaniesGroups()">
										{{ t('empleados', 'Refresh') }}
									</NcButton>
								</div>
							</div>
						</div>
					</div>
				</template>
				<template #custombuttons>
					<NcActions :open="button" @click="toggle">
						<template #icon>
							<FilterVariant :size="20" />
						</template>

						<NcActionButton :is-menu="true">
							{{ t('empleados', 'Filters') }}
						</NcActionButton>

						<NcActionInput v-model="sortOrder"
							type="multiselect"
							:label-outside="false"
							:manual-open="true"
							:options="[
								{ label: t('empleados', 'A to Z'), value: 'az' },
								{ label: t('empleados', 'Z to A'), value: 'za' },
							]">
							{{ t('empleados', 'Sort') }}
						</NcActionInput>

						<NcActionCheckbox v-model="onlyParents">
							{{ t('empleados', 'Only Main Groups') }}
						</NcActionCheckbox>
						<NcActionCheckbox v-model="onlySpecial">
							{{ t('empleados', 'Only Special Clients') }}
						</NcActionCheckbox>
						<NcActionCheckbox v-model="showDisabled">
							{{ t('empleados', 'Show disabled') }}
						</NcActionCheckbox>
						<NcActionCheckbox v-model="onlyDisabled">
							{{ t('empleados', 'Only Disabled') }}
						</NcActionCheckbox>

						<NcActionSeparator />

						<NcActionButton :is-menu="true">
							{{ t('empleados', 'Settings') }}
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

					<span
						v-if="(onlyParents ? 1 : 0) + (onlySpecial ? 1 : 0) + (showDisabled ? 1 : 0) + (onlyDisabled ? 1 : 0) > 0"
						class="filter-badge">
						{{ (onlyParents ? 1 : 0) + (onlySpecial ? 1 : 0) + (showDisabled ? 1 : 0) +
							(onlyDisabled ? 1 : 0) }}
					</span>
				</template>
				<template #details>
					<div class="client-details">
						<div>
							<div class="details-header"
								:class="{ 'details-header--especial': selectedClient.especial }">
								<div class="details-icon">
									<HexagonMultipleOutline :size="30" />
								</div>

								<div class="details-title">
									<p class="eyebrow">
										{{ selectedClientType }}
									</p>
									<h2>{{ selectedClient.nombre || t('empleados', 'Without name') }}</h2>
									<p>{{ selectedClient.detalles || t('empleados', 'No description available.') }}</p>
								</div>
							</div>

							<VueTabs v-model="activeCompanyTab"
								class="companies-tabs"
								active-tab-color="var(--color-primary-element)"
								active-text-color="var(--color-primary-element-text)"
								type="grow">
								<VTab :title="t('empleados', 'General Information')">
									<div class="btn-top">
										<div class="info-section">
											<div class="section-head">
												<div>
													<p class="section-label">
														{{ t('empleados', 'Company Information') }}
													</p>
												</div>
											</div>

											<div class="info-grid">
												<div class="detail-card">
													<span>{{ t('empleados', 'Legal Business Name') }}</span>
													<span class="value-text">{{ selectedClient.razon_social || '-'
													}}</span>
												</div>

												<div class="detail-card">
													<span>{{ t('empleados', 'Project Manager') }}</span>

													<div v-if="projectManager" class="pm-info">
														<img :src="projectManager.avatar"
															:alt="projectManager.label"
															class="pm-avatar">

														<span class="value-text">
															{{ projectManager.label }}
														</span>
													</div>

													<span v-else class="value-text">
														-
													</span>
												</div>

												<div class="detail-card">
													<span>{{ t('empleados', 'Primary Contact') }}</span>
													<span class="value-text">{{ selectedClient.nombre_contacto || '-'
													}}</span>
												</div>

												<div class="detail-card">
													<span>{{ t('empleados', 'Phone Number') }}</span>
													<span class="value-text">{{ selectedClient.telefono || '-' }}</span>
												</div>

												<div class="detail-card">
													<span>{{ t('empleados', 'Email Address') }}</span>
													<span class="value-text">{{ selectedClient.correo || '-' }}</span>
												</div>

												<div class="detail-card">
													<span>{{ t('empleados', 'Location') }}</span>
													<span class="value-text">{{ selectedClient.ubicacion || '-'
													}}</span>
												</div>

												<div class="detail-card">
													<span>{{ t('empleados', 'Special Client') }}</span>
													<span class="value-text">{{ Number(selectedClient.especial) ?
														t('empleados',
															'Yes') : t('empleados', 'No') }}</span>
												</div>

												<div class="detail-card">
													<span>{{ t('empleados', 'Status') }}</span>
													<span class="value-text">{{ Number(selectedClient.estado) ?
														t('empleados',
															'Active') : t('empleados', 'Inactive') }}</span>
												</div>
											</div>
										</div>
									</div>

									<div class="btn-top">
										<div class="info-section">
											<div class="section-head">
												<div>
													<p class="section-label">
														{{ t('empleados', 'Group Information') }}
													</p>
												</div>
											</div>

											<div class="details-grid">
												<div class="detail-card">
													<span>{{ t('empleados', 'Parent group') }}</span>
													<span class="value-text">{{ parentName }}</span>
												</div>

												<div class="detail-card">
													<span>{{ t('empleados', 'Sub-companies') }}</span>
													<span class="value-text">{{ childCompanies.length }}</span>
												</div>

												<div class="detail-card detail-card-wide">
													<span>{{ t('empleados', 'Hierarchy') }}</span>
													<div class="breadcrumb">
														<span>{{ parentName }}</span>
														<span class="separator">/</span>
														<span class="value-text">{{ selectedClient.nombre }}</span>
													</div>
												</div>
											</div>
											<!-- Sub-companies -->
											<div class="children-section">
												<div class="section-head">
													<div>
														<p class="section-label">
															{{ t('empleados', 'Sub-companies') }}
														</p>
														<h3>{{ t('empleados', 'Companies inside this group') }}</h3>
													</div>
												</div>

												<div v-if="childCompanies.length > 0" class="children-grid">
													<button v-for="child in childCompanies"
														:key="child.id"
														type="button"
														class="child-card"
														@click="GetCompanieGroup(child.id)">
														<div class="child-icon">
															<OfficeBuilding :size="20" />
														</div>

														<div class="child-info">
															<span class="value-text">{{ child.nombre }}</span>
															<span>{{ child.detalles || t('empleados', 'No description available.')
															}}</span>
														</div>

														<div class="child-count">
															{{ child.child_count || 0 }}
														</div>
													</button>
												</div>

												<NcEmptyContent v-else
													:name="t('empleados', 'No sub-companies')"
													:description="t('empleados', 'This company or group does not have registered sub-companies.')">
													<template #icon>
														<OfficeBuilding />
													</template>
												</NcEmptyContent>
											</div>
										</div>
									</div>
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

													<NcActionButton @click="toggleSelectMode">
														<template #icon>
															<CheckboxMarkedOutline :size="20" />
														</template>
														{{ selectMode ? t('empleados', 'Exit selection') : t('empleados', 'Select') }}
													</NcActionButton>
												</NcActions>

												<span v-if="honorarioFilterCount > 0" class="filter-badge">
													{{ honorarioFilterCount }}
												</span>

												<NcButton type="primary" @click="openHonorarioModal">
													{{ t('empleados', 'New fee') }}
												</NcButton>
											</div>
										</div>

										<div v-if="selectMode" class="select-bar">
											<span>{{ selectedHonorarios.length }} {{ t('empleados', 'selected') }}</span>
											<div class="select-bar__actions">
												<NcButton @click="toggleSelectMode">
													{{ t('empleados', 'Cancel') }}
												</NcButton>
												<NcButton type="primary"
													:disabled="selectedHonorarios.length === 0"
													@click="generarReporteHonorarios">
													{{ t('empleados', 'Generate report') }}
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
														<span class="value-text">{{ honorario.tipo_servicio || t('empleados',
															'Service') }}</span>
														<span class="honorario-date">
															{{ honorario.fecha_inicio }} — {{ honorario.fecha_fin }}
														</span>
													</div>
													<div class="honorario-meta">
														<span class="honorario-amount">
															{{ formatImporte(montoAcumulado(honorario)) }} {{ honorario.tipo_moneda }}
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
															<NcButton v-if="Number(honorario.numero_parcialidades) === 0"
																type="secondary"
																@click="completarHonorarioBorrador(honorario)">
																{{ t('empleados', 'Complete fee') }}
															</NcButton>

															<NcButton v-if="Number(honorario.numero_parcialidades) > 0"
																type="tertiary"
																@click="toggleParcialidades(honorario.id_honorario)">
																{{ t('empleados', 'Installments') }}
															</NcButton>

															<NcActions :force-menu="true">
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
																	{{ t('empleados', 'Report') }}
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
																		v-if="Number(p.pagado) === 1 || Number(p.pagado) === 2"
																		class="parcialidad-toggle"
																		:class="{ open: detalleAbierto[p.id_parcialidad] }"
																		@click="toggleDetalleParcialidad(p.id_parcialidad)">
																		<ChevronDown :size="16" />
																	</span>
																</div>
																<span class="parcialidad-fechas">{{ p.pfecha_inicio }} — {{
																	p.pfecha_fin }}</span>
																<span class="parcialidad-importe">{{
																	formatImporte(p.importe_parcialidad) }} {{
																	honorario.tipo_moneda }}</span>

																<div class="parcialidad-actions">
																	<NcButton v-if="Number(p.pagado) === 0"
																		class="btn-pagar"
																		type="primary"
																		@click="abrirDialogPago(p.id_parcialidad, honorario.id_honorario)">
																		{{ t('empleados', 'Mark as paid') }}
																	</NcButton>

																	<template v-else-if="Number(p.pagado) === 1">
																		<NcButton class="btn-factura"
																			type="secondary"
																			@click="confirmarFactura(p.id_parcialidad, honorario.id_honorario)">
																			{{ t('empleados', 'Mark as invoiced') }}
																		</NcButton>
																	</template>

																	<template v-else-if="Number(p.pagado) === 2">
																		<span class="parcialidad-completada">
																			{{ t('empleados', 'Completed') }} ✓
																		</span>
																	</template>
																</div>
															</div>
															<div v-if="detalleAbierto[p.id_parcialidad]" class="parcialidad-detalle">
																<span v-if="p.fecha_pago" class="parcialidad-detail-text">
																	💳 {{ t('empleados', 'Paid') }}: {{ p.fecha_pago }}
																</span>

																<NcActions v-if="Number(p.pagado) === 1" class="parcialidad-detalle-actions">
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
													</template>
												</div>
											</div>
										</div>
									</div>
								</VTab>
							</VueTabs>
						</div>

						<!-- Modal - Fecha Pago -->
						<NcModal
							v-if="showPagoDialog"
							size="small"
							:name="t('empleados', 'Register payment')"
							@close="showPagoDialog = false">
							<div class="payment-modal">
								<div class="payment-icon-wrapper">
									<div class="payment-icon">
										💳
									</div>
								</div>
								<h2>{{ t('empleados', 'Register payment') }}</h2>
								<p class="payment-subtitle">
									{{ t('empleados', 'Select the payment date for this installment.') }}
								</p>
								<div class="payment-field">
									<NcTextField
										v-model="fechaPago"
										type="date"
										:label="t('empleados', 'Payment date')" />
								</div>
								<div class="payment-actions">
									<NcButton @click="showPagoDialog = false">
										{{ t('empleados', 'Cancel') }}
									</NcButton>
									<NcButton
										type="primary"
										@click="confirmarPago">
										{{ t('empleados', 'Save') }}
									</NcButton>
								</div>
							</div>
						</NcModal>
						<NcDialog v-if="showFacturaDialog"
							:name="t('empleados', 'Invoice date')"
							@close="showFacturaDialog = false">
							<input v-model="fechaFactura" type="date">

							<template #actions>
								<NcButton @click="showFacturaDialog = false">
									{{ t('empleados', 'Cancel') }}
								</NcButton>
								<NcButton type="primary" @click="confirmarFactura">
									{{ t('empleados', 'Save') }}
								</NcButton>
							</template>
						</NcDialog>

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
							:message="t('empleados', 'This will mark the installment as pending again. Continue?')"
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
		<NcModal v-if="modal"
			ref="modalRef"
			:name="modalTitle"
			@close="closeModal">
			<div class="modal-content">
				<div class="modal-header">
					<p class="section-label">
						{{ editing ? t('empleados', 'Edit') : t('empleados', 'Create') }}
					</p>
					<h2>{{ modalTitle }}</h2>
					<p>
						{{ t('empleados', 'Register a main group or link the company to an existing parent group.') }}
					</p>
				</div>

				<div class="form-grid">
					<!-- nombre -->
					<NcTextField required
						class="span-2"
						:value.sync="nombre"
						:label="t('empleados', 'Company or group name')" />

					<!-- detalles -->
					<NcTextArea class="span-2"
						:value.sync="detalles"
						:label="t('empleados', 'Details')"
						:rows="3" />

					<!-- razon_social -->
					<NcTextField :value.sync="razon_social" :label="t('empleados', 'Business name')" />

					<!-- correo -->
					<NcTextField :value.sync="correo" :label="t('empleados', 'Email')" />

					<!-- nombre_contacto -->
					<NcTextField :value.sync="nombre_contacto" :label="t('empleados', 'Primary contact')" />

					<!-- telefono -->
					<NcTextField :value.sync="telefono" :label="t('empleados', 'Phone number')" />

					<!-- ubicacion -->
					<NcTextField class="span-2" :value.sync="ubicacion" :label="t('empleados', 'Location')" />
					<NcSelect v-model="lider_proyecto"
						:input-label="t('empleados', 'Project leader')"
						:options="projectManagers"
						:clearable="true"
						label="label"
						track-by="value" />
					<NcSelect v-model="colaboradores"
						:options="collaboratorOptions"
						:multiple="true"
						label="label"
						track-by="value"
						:placeholder="t('empleados', 'Collaborators')"
						class="aligned-select" />

					<!-- especial -->
					<div class="special-client-card span-2">
						<NcCheckboxRadioSwitch v-model="especial" type="switch" />
						<div class="special-client-info">
							<h3>{{ t('empleados', 'Special Client') }}</h3>
							<p>
								{{ t('empleados', 'Enable this option for special handling clients.') }}
							</p>
						</div>
					</div>

					<!-- estado -->
					<div class="special-client-card span-2">
						<NcCheckboxRadioSwitch v-model="estado" type="switch" />
						<div class="special-client-info">
							<h3>{{ t('empleados', 'Active') }}</h3>
							<p>
								{{ t('empleados', 'Disable to deactivate this client without deleting it.') }}
							</p>
						</div>
					</div>

					<!-- cliente_padre -->
					<NcSelect :key="options.length"
						v-model="cliente_padre"
						class="span-2"
						:input-label="t('empleados', 'Parent group')"
						:options="parentOptions"
						:clearable="true"
						label="label"
						track-by="id" />

					<NcNoteCard type="info" class="span-2">
						{{ t('empleados', 'Leave parent group empty to create a main group. Select a parent to create a sub-company.') }}
					</NcNoteCard>
				</div>

				<div class="modal-actions">
					<NcButton @click="closeModal">
						{{ t('empleados', 'Cancel') }}
					</NcButton>

					<NcButton type="primary" :disabled="!isFormValid || saving" @click="save">
						{{ saving ? t('empleados', 'Saving...') : saveLabel }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- Modal: Filtros Honorarios -->
		<NcModal v-if="honorarioFilterModal"
			size="small"
			:name="t('empleados', 'Filter fees')"
			@close="honorarioFilterModal = false">
			<div class="modal-content">
				<div class="modal-header">
					<p class="section-label">
						{{ t('empleados', 'Billing') }}
					</p>
					<h2>{{ t('empleados', 'Filter fees') }}</h2>
				</div>

				<div class="form-grid">
					<NcTextField class="span-2"
						:value.sync="hf_busqueda"
						:label="t('empleados', 'Search by service name')" />

					<NcSelect v-model="hf_estado"
						class="span-2"
						:options="hf_estadoOptions"
						:placeholder="t('empleados', 'Status')"
						label="label"
						track-by="value"
						:clearable="true" />

					<NcSelect v-model="hf_tipo"
						class="span-2"
						:options="hf_tipoOptions"
						:placeholder="t('empleados', 'Fee type')"
						label="label"
						track-by="value"
						:clearable="true" />

					<div class="special-client-card span-2">
						<NcCheckboxRadioSwitch v-model="hf_soloEspecial" type="switch" />
						<div class="special-client-info">
							<h3>{{ t('empleados', 'Special fees only') }}</h3>
						</div>
					</div>

					<div class="span-2">
						<span class="date-label">{{ t('empleados', 'Date range') }}</span>
					</div>
					<NcTextField :value.sync="hf_desde" type="date" :label="t('empleados', 'From')" />
					<NcTextField :value.sync="hf_hasta" type="date" :label="t('empleados', 'To')" />
				</div>

				<div class="modal-actions">
					<NcButton @click="resetHonorarioFilters">
						{{ t('empleados', 'Clear filters') }}
					</NcButton>
					<NcButton type="primary" @click="honorarioFilterModal = false">
						{{ t('empleados', 'Apply') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- Modal - Reporte-Honorarios -->
		<NcModal v-if="reporteModal"
			size="normal"
			:name="t('empleados', 'Generate report')"
			@close="reporteModal = false">
			<div class="modal-content">
				<div class="modal-header">
					<h2>{{ t('empleados', 'Generate service fee report') }}</h2>
				</div>

				<div class="form-grid">
					<NcSelect v-model="rep_departamento"
						class="span-2"
						:options="rep_departamentoOptions"
						:placeholder="t('empleados', 'Department')"
						label="label"
						track-by="value" />

					<NcTextField class="span-2" :value.sync="rep_asunto" :label="t('empleados', 'Subject')" />
				</div>

				<div class="modal-actions">
					<NcButton @click="reporteModal = false">
						{{ t('empleados', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" :disabled="generandoReporte" @click="generarReporte">
						{{ generandoReporte ? t('empleados', 'Generating...') : t('empleados', 'Generate') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- Modal - Honorarios -->
		<NcModal v-if="honorarioModal" :name="honorarioModalTitle" @close="closeHonorarioModal">
			<div class="modal-content">
				<div class="modal-header">
					<p class="section-label">
						{{ t('empleados', 'Billing') }}
					</p>
					<h2>{{ honorarioModalTitle }}</h2>
				</div>

				<!-- Selector de tipo -->
				<div class="tipo-honorario-selector">
					<button v-for="tipo in tiposHonorario"
						:key="tipo.value"
						class="tipo-btn"
						:class="{ 'tipo-btn--active': h_tipo_honorario === tipo.value }"
						type="button"
						:disabled="isEditingHonorario"
						@click="!isEditingHonorario && (h_tipo_honorario = tipo.value)">
						<span class="tipo-icon">
							<span v-if="tipo.value === 'parcial'">📅</span>
							<span v-else-if="tipo.value === 'iguala'">🔄</span>
							<span v-else>⚡</span>
						</span>
						{{ tipo.label }}
					</button>
				</div>

				<!-- Descripción contextual -->
				<NcNoteCard type="info" class="tipo-desc">
					<span v-if="h_tipo_honorario === 'parcial'">
						{{ t('empleados', 'Fixed period. Installments are calculated automatically by month between start and end date.') }}
					</span>
					<span v-else-if="h_tipo_honorario === 'iguala'">
						{{ t('empleados', 'Indefinite monthly fee. A new installment is generated each month. You can finalize it at any time.') }}
					</span>
					<span v-else>
						{{ t('empleados', 'One-time fee. A single installment is created for the selected month.') }}
					</span>
				</NcNoteCard>

				<!-- Aviso de edición bloqueada -->
				<NcNoteCard v-if="isEditingHonorario" type="warning" class="tipo-desc">
					{{ t('empleados', 'Dates and amount cannot be changed here to avoid regenerating installments. Only service, currency, title date and special flag can be edited.') }}
				</NcNoteCard>

				<div class="form-grid">
					<NcTextField :value.sync="h_tipo_servicio" :label="t('empleados', 'Service type')" />

					<NcSelect v-model="h_titulo_mes"
						:options="meses"
						:placeholder="t('empleados', 'Month')"
						label="label"
						track-by="value"
						:searchable="false" />
					<NcSelect v-model="h_titulo_anio"
						:options="anios"
						:placeholder="t('empleados', 'Year')"
						label="label"
						track-by="value"
						:searchable="false" />

					<NcSelect v-model="h_tipo_moneda"
						:options="currencyOptions"
						label="label"
						track-by="value"
						:searchable="false" />

					<NcTextField type="number"
						class="span-2"
						:value.sync="h_importe_total"
						:disabled="isEditingHonorario"
						:label="t('empleados', 'Total amount')" />

					<div class="special-client-card span-2">
						<NcCheckboxRadioSwitch v-model="h_especial" type="switch" />
						<div class="special-client-info">
							<h3>{{ t('empleados', 'Special fee') }}</h3>
							<p>{{ t('empleados', 'Marks this service fee as special.') }}</p>
						</div>
					</div>

					<!-- Fecha inicio (todos los tipos) -->
					<div class="span-2">
						<span class="date-label">{{ t('empleados', 'Start date') }}</span>
					</div>
					<NcSelect v-model="h_mes_inicio"
						:options="meses"
						:placeholder="t('empleados', 'Month')"
						label="label"
						track-by="value"
						:searchable="false"
						:disabled="isEditingHonorario" />
					<NcSelect v-model="h_anio_inicio"
						:options="anios"
						:placeholder="t('empleados', 'Year')"
						label="label"
						track-by="value"
						:searchable="false"
						:disabled="isEditingHonorario" />

					<!-- Fecha fin solo para parciales -->
					<template v-if="h_tipo_honorario === 'parcial'">
						<div class="span-2">
							<span class="date-label">{{ t('empleados', 'End date') }}</span>
						</div>
						<NcSelect v-model="h_mes_fin"
							:options="meses"
							:placeholder="t('empleados', 'Month')"
							label="label"
							track-by="value"
							:searchable="false"
							:disabled="isEditingHonorario" />
						<NcSelect v-model="h_anio_fin"
							:options="anios"
							:placeholder="t('empleados', 'Year')"
							label="label"
							track-by="value"
							:searchable="false"
							:disabled="isEditingHonorario" />
					</template>

					<!-- Preview -->
					<NcNoteCard v-if="!isEditingHonorario && h_tipo_honorario === 'parcial' && h_numero_parcialidades > 0"
						type="info"
						class="span-2">
						{{ t('empleados', '{n} installment(s) of {amount} {currency}', {
							n: h_numero_parcialidades,
							amount: formatImporte(h_importe_parcialidad),
							currency: h_tipo_moneda ? h_tipo_moneda.value : ''
						}) }}
					</NcNoteCard>
					<NcNoteCard v-else-if="!isEditingHonorario && h_tipo_honorario === 'iguala'" type="info" class="span-2">
						{{ t('empleados', 'Monthly fee of {amount} {currency} starting {mes} {anio}', {
							amount: formatImporte(Number(h_importe_total || 0)),
							currency: h_tipo_moneda ? h_tipo_moneda.value : '',
							mes: h_mes_inicio ? h_mes_inicio.label : '—',
							anio: h_anio_inicio ? h_anio_inicio.value : ''
						}) }}
					</NcNoteCard>
					<NcNoteCard v-else-if="!isEditingHonorario && h_tipo_honorario === 'eventual'" type="info" class="span-2">
						{{ t('empleados', 'Single installment of {amount} {currency}', {
							amount: formatImporte(Number(h_importe_total || 0)),
							currency: h_tipo_moneda ? h_tipo_moneda.value : ''
						}) }}
					</NcNoteCard>
				</div>

				<div class="modal-actions">
					<NcButton @click="closeHonorarioModal">
						{{ t('empleados', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" :disabled="!isHonorarioValid || savingHonorario" @click="handleSaveHonorario">
						{{ honorarioSaveLabel }}
					</NcButton>
				</div>
			</div>
		</NcModal>
		<input ref="file"
			type="file"
			class="file-input"
			accept=".xlsx"
			@change="importar">
	</NcAppContent>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import List from '../Helpers/Lists/List.vue'

import HexagonMultipleOutline from 'vue-material-design-icons/HexagonMultipleOutline.vue'
import OfficeBuilding from 'vue-material-design-icons/OfficeBuilding.vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import CheckboxMarkedOutline from 'vue-material-design-icons/CheckboxMarkedOutline.vue'
// import Cog from 'vue-material-design-icons/Cog.vue'
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
import { VueTabs, VTab } from 'vue-nav-tabs/dist/vue-tabs.js'
import 'vue-nav-tabs/themes/vue-tabs.css'
// import DatabaseCog from 'vue-material-design-icons/DatabaseCog.vue'
// import IconTrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
// import IconOpenInNew from 'vue-material-design-icons/OpenInNew.vue'
// import IconPencilOutline from 'vue-material-design-icons/PencilOutline.vue'

import {
	NcAppContent,
	NcModal,
	NcTextField,
	NcButton,
	NcTextArea,
	NcDialog,
	NcSelect,
	NcEmptyContent,
	NcNoteCard,
	NcActions,
	NcActionButton,
	NcActionSeparator,
	NcActionInput,
	NcActionCheckbox,
} from '@nextcloud/vue'

export default {
	name: 'CompaniesGroups',

	components: {
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
		HexagonMultipleOutline,
		OfficeBuilding,
		// Cog,
		AccountMultiplePlusOutline,
		DatabaseExport,
		Upload,
		FileDocumentOutline,
		ChevronDown,
		VueTabs,
		VTab,
		// IconTrashCanOutline,
		// IconOpenInNew,
		// IconPencilOutline,
		// DatabaseCog,
		AccountGroup,
		NcModal,
		NcTextField,
		NcButton,
		NcTextArea,
		NcSelect,
		NcActions,
		NcActionButton,
		NcActionSeparator,
		NcActionInput,
		NcActionCheckbox,
		FilterVariant,
		NcEmptyContent,
		NcNoteCard,
	},

	data() {
		return {
			activeCompanyTab: t('empleados', 'Fees'),
			projectManagers: [],
			editing: false,
			saving: false,
			loading: true,
			listas: [],
			rawClients: [],
			select: [],
			options: [],
			modal: false,
			/* cliente */
			nombre: '',
			detalles: null,
			razon_social: null,
			nombre_contacto: null,
			telefono: null,
			correo: null,
			ubicacion: null,
			lider_proyecto: null,
			colaboradores: [],
			especial: false,
			estado: true,
			cliente_padre: null,
			sortOrder: [],
			onlyParents: false,
			onlySpecial: false,
			showDisabled: false,
			showFilters: false,
			onlyDisabled: false,
			/* honorarios */
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
			showPagoDialog: false,
			fechaPago: '',
			parcialidadSeleccionada: null,
			honorarioSeleccionado: null,
			showFacturaDialog: false,
			fechaFactura: '',
			detalleAbierto: {},
			honorarioBorradorId: null,
			button: false,
			h_titulo_mes: null,
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
			honorarioParaReporte: null,
			generandoReporte: false,
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
		}
	},

	computed: {
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

		mainGroups() {
			return this.rawClients.filter((client) => Number(client.cliente_padre || 0) === 0 && Number(client.estado ?? 1) === 1)
		},

		subCompanies() {
			return this.rawClients.filter((client) => Number(client.cliente_padre || 0) !== 0 && Number(client.estado ?? 1) === 1)
		},

		activeClients() {
			return this.rawClients.filter((client) => Number(client.estado ?? 1) === 1)
		},

		parentOptions() {
			const currentId = this.selectedClient?.id

			return this.options.filter((option) => {
				return !currentId || Number(option.id) !== Number(currentId)
			})
		},

		isFormValid() {
			return String(this.nombre || '').trim().length > 0
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

		filteredListas() {
			let data = [...this.listas]

			if (!this.showDisabled) {
				data = data.filter(item => Number(item.estado ?? 1) === 1)
			}

			if (this.onlyDisabled) {
				data = data.filter(item => Number(item.estado ?? 1) === 0)
			}

			if (this.onlyParents) {
				data = data.filter(item =>
					Number(item.cliente_padre || 0) === 0,
				)
			}

			if (this.onlySpecial) {
				data = data.filter(item =>
					Number(item.especial || 0) === 1,
				)
			}

			data.sort((a, b) => {
				const nameA = (a.nombre || a.name || '').toLowerCase()
				const nameB = (b.nombre || b.name || '').toLowerCase()

				return this.sortOrder.value === 'za'
					? nameB.localeCompare(nameA)
					: nameA.localeCompare(nameB)
			})

			return data
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

		h_numero_parcialidades() {
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

		h_importe_parcialidad() {
			const total = Number(this.h_importe_total || 0)
			const partes = this.h_numero_parcialidades

			if (!total || !partes) {
				return 0
			}

			return Math.round((total / partes) * 100) / 100
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

		projectManagerName() {
			return this.projectManagers.find(
				(emp) => Number(emp.value) === Number(this.selectedClient?.lider_proyecto),
			)?.label || '-'
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
	},

	watch: {
		'selectedClient.id'(newId) {
			this.honorarios = []
			this.parcialidadesAbiertas = {}
			this.parcialidades = {}
			this.loadingParcialidades = {}

			if (newId) {
				this.GetHonorariosByCliente(newId)
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

		window.addEventListener('keydown', this.onKeyDown)

		this.$root.$on('details', this._onDetails)
		this.$root.$on('new', this._onNew)
		this.$root.$on('delete', this._onDelete)
		this.$root.$on('edit', this._onEdit)
		this.$root.$on('exportlist', this._onExport)
		this.$root.$on('importlist', this._onImport)

		this.GetCompaniesGroups()
		this.GetEmpleadosList()
		this.GetAreasList()
		this._onClickOutside = (e) => {
			const wrap = this.$el.querySelector('.filter-wrap')
			if (wrap && !wrap.contains(e.target)) {
				this.showFilters = false
			}
		}
		document.addEventListener('click', this._onClickOutside)

		this._onToggleEstado = () => this.toggleEstado()
		this.$root.$on('toggleEstado', this._onToggleEstado)
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
		document.removeEventListener('click', this._onClickOutside)
	},

	methods: {
		t, // Exponer i18n a la plantilla

		matchSearch(name) {
			if (this.query.trim() !== '') {
				return name.toString().toLowerCase().includes(this.query.trim().toLowerCase())
			}
			return true
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
				showError(t('empleados', 'Error loading departments: {error}', { error: String(err) }))
			}
		},

		AgregarNuevo() {
			this.toggle()
			this.$root.$emit('new', true)
		},

		toggle() {
			this.button = !this.button
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

			if (this.showFilters) {
				this.showFilters = false
				return
			}

			this.select = []
		},

		openModal() {
			this.editing = false
			this.resetForm()
			this.modal = true
		},

		closeModal() {
			this.modal = false
			this.saving = false
		},

		resetForm() {
			this.nombre = ''
			this.detalles = ''
			this.lider_proyecto = null
			this.colaboradores = []
			this.razon_social = ''
			this.nombre_contacto = ''
			this.telefono = ''
			this.correo = ''
			this.ubicacion = ''
			this.especial = false
			this.cliente_padre = null
			this.estado = true
		},

		triggerImport() {
			this.$refs.file?.click()
		},

		getOcsData(response) {
			return response?.data?.ocs?.data ?? response?.data ?? null
		},

		toggleFilters() {
			this.showFilters = !this.showFilters
		},

		async GetEmpleadosList() {
			try {
				const response = await axios.get(
					generateUrl('/apps/empleados/GetEmpleadosList'),
				)

				const empleados = response?.data?.ocs?.data?.Empleados || []
				// eslint-disable-next-line no-console
				console.log(this.getPayload())

				this.projectManagers = empleados.map((emp) => ({
					value: Number(emp.Id_empleados),
					uid: emp.uid,
					label: emp.displayname,
					avatar: generateUrl(`/avatar/${emp.uid}/64`),
				}))
			} catch (err) {
				showError(
					t('empleados', 'Error loading employees: {error}', {
						error: String(err),
					}),
				)
			}
		},

		async GetCompanieGroup(id) {
			try {
				const response = await axios.post(generateUrl('/apps/empleados/GetCompanieGroup'), {
					id,
				})

				const data = this.getOcsData(response)
				this.select = Array.isArray(data) ? data : [data]
			} catch (err) {
				showError(t('empleados', 'Error loading company: {error}', { error: String(err) }))
			}
		},

		async GetCompaniesGroups() {
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
					...item,
				}))

				this.options = data.map((item) => ({
					value: item.id,
					id: item.id,
					label: item.nombre,
				}))
			} catch (err) {
				showError(t('empleados', 'Error loading companies: {error}', { error: String(err) }))
				this.rawClients = []
				this.listas = []
				this.options = []
			} finally {
				this.loading = false
			}
		},

		save() {
			if (this.editing) {
				return this.modify()
			}

			return this.create()
		},

		getPayload() {
			return {
				nombre: String(this.nombre || '').trim(),
				razon_social: String(this.razon_social || '').trim(),
				lider_proyecto: this.lider_proyecto?.value ?? this.lider_proyecto ?? null,
				colaboradores: JSON.stringify(
					Array.isArray(this.colaboradores)
						? this.colaboradores.map(c => c.value ?? c)
						: [],
				),
				nombre_contacto: String(this.nombre_contacto || '').trim(),
				telefono: String(this.telefono || '').trim(),
				correo: String(this.correo || '').trim(),
				ubicacion: String(this.ubicacion || '').trim(),
				detalles: String(this.detalles || '').trim(),
				especial: this.especial ? 1 : 0,
				cliente_padre: this.cliente_padre?.value ?? this.cliente_padre?.id ?? null,
				estado: this.estado ? 1 : 0,
			}
		},

		async create() {
			if (!this.isFormValid) {
				showError(t('empleados', 'Company or group name is required.'))
				return
			}

			this.saving = true

			try {
				await axios.post(generateUrl('/apps/empleados/crearCliente'), this.getPayload())

				showSuccess(t('empleados', 'Company or group created successfully'))
				await this.GetCompaniesGroups()
				this.closeModal()
			} catch (err) {
				showError(t('empleados', 'Error creating company: {error}', { error: String(err) }))
			} finally {
				this.saving = false
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

		async modify() {
			if (!this.selectedClient?.id) {
				showError(t('empleados', 'Select a company or group first.'))
				return
			}

			if (!this.isFormValid) {
				showError(t('empleados', 'Company or group name is required.'))
				return
			}

			this.saving = true

			try {
				await axios.post(generateUrl('/apps/empleados/modificarCliente'), {
					id: this.selectedClient.id,
					...this.getPayload(),
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

		edit() {
			if (!this.selectedClient?.id) {
				showError(t('empleados', 'Select a company or group first.'))
				return
			}

			this.editing = true
			this.nombre = this.selectedClient.nombre || ''
			this.detalles = this.selectedClient.detalles || ''
			this.razon_social = this.selectedClient.razon_social || ''
			this.nombre_contacto = this.selectedClient.nombre_contacto || ''
			this.telefono = this.selectedClient.telefono || ''
			this.correo = this.selectedClient.correo || ''
			this.ubicacion = this.selectedClient.ubicacion || ''
			this.especial = Boolean(Number(this.selectedClient.especial))
			this.estado = Boolean(Number(this.selectedClient.estado ?? 1))

			this.lider_proyecto = this.projectManagers.find(
				(emp) => Number(emp.value) === Number(this.selectedClient.lider_proyecto),
			) || null

			// colaboradores ya viene como array de ids desde el mapper
			const colabs = Array.isArray(this.selectedClient.colaboradores)
				? this.selectedClient.colaboradores
				: []

			this.colaboradores = this.projectManagers.filter(emp =>
				colabs.includes(emp.value) || colabs.includes(String(emp.value)),
			)

			const parentId = this.selectedClient.cliente_padre || 0
			this.cliente_padre = Number(parentId) === 0
				? null
				: this.options.find(o => Number(o.value) === Number(parentId)) || null

			this.modal = true
		},

		async importar() {
			this.toggle()
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
			this.toggle()
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
			this.h_titulo_mes = null
			this.h_titulo_anio = { label: String(currentYear), value: currentYear }
			this.h_especial = false
		},

		resetHonorarioFilters() {
			this.hf_busqueda = ''
			this.hf_estado = null
			this.hf_tipo = null
			this.hf_soloEspecial = false
			this.hf_desde = ''
			this.hf_hasta = ''
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
				// fecha_fin según tipo
				let fechaFin = ''
				if (this.h_tipo_honorario === 'parcial') {
					fechaFin = this.h_mes_fin && this.h_anio_fin
						? `${this.h_anio_fin.value}-${String(this.h_mes_fin.value).padStart(2, '0')}-01`
						: ''
				} else if (this.h_tipo_honorario === 'eventual') {
					// misma fecha que inicio
					fechaFin = this.h_mes_inicio && this.h_anio_inicio
						? `${this.h_anio_inicio.value}-${String(this.h_mes_inicio.value).padStart(2, '0')}-01`
						: ''
				}
				// iguala: fecha_fin vacía (indefinida)

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
					tipo_servicio: (() => {
						const base = String(this.h_tipo_servicio || '').trim()
						const mes = this.h_titulo_mes?.label || ''
						const anio = this.h_titulo_anio?.value || ''
						const sufijo = (mes && anio) ? ` - ${mes} ${anio}` : ''
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

		editarHonorario(honorario) {
			this.resetHonorarioForm()

			this.h_importe_total = String(honorario.importe_total)
			this.h_tipo_moneda = this.currencyOptions.find(c => c.value === honorario.tipo_moneda) || this.currencyOptions[0]
			this.h_tipo_servicio = honorario.tipo_servicio || ''

			// Parsear fechas a mes/año
			if (honorario.fecha_inicio) {
				const [anio, mes] = honorario.fecha_inicio.split('-')
				this.h_anio_inicio = { label: anio, value: Number(anio) }
				this.h_mes_inicio = this.monthOptions?.find(m => m.value === Number(mes)) || null
			}

			if (honorario.fecha_fin) {
				const [anio, mes] = honorario.fecha_fin.split('-')
				this.h_anio_fin = { label: anio, value: Number(anio) }
				this.h_mes_fin = this.monthOptions?.find(m => m.value === Number(mes)) || null
			}

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

		abrirDialogPago(idParcialidad, idHonorario) {
			this.parcialidadSeleccionada = idParcialidad
			this.honorarioSeleccionado = idHonorario
			this.fechaPago = new Date().toISOString().split('T')[0]
			this.showPagoDialog = true
		},

		async confirmarPago() {
			try {
				await axios.post(
					generateUrl('/apps/empleados/marcarParcialidadPagada'),
					{
						id_parcialidad: this.parcialidadSeleccionada,
						fecha_pago: this.fechaPago,
					},
				)

				this.showPagoDialog = false

				await this.GetParcialidades(
					this.honorarioSeleccionado,
				)

				await this.GetHonorariosByCliente(
					this.selectedClient.id,
				)

				showSuccess(
					t('empleados', 'Installment marked as paid'),
				)

			} catch (err) {
				showError(String(err))
			}
		},

		editarFechaPago(p, idHonorario) {
			this.parcialidadSeleccionada = p.id_parcialidad
			this.honorarioSeleccionado = idHonorario
			this.fechaPago = p.fecha_pago || new Date().toISOString().split('T')[0]
			this.showPagoDialog = true
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

		abrirDialogFactura(idParcialidad, idHonorario) {
			this.parcialidadSeleccionada = idParcialidad
			this.honorarioSeleccionado = idHonorario
			this.fechaFactura = new Date().toISOString().split('T')[0]
			this.showFacturaDialog = true
		},

		async confirmarFactura(idParcialidad, idHonorario) {
			try {
				await axios.post(
					generateUrl('/apps/empleados/marcarParcialidadFacturada'),
					{
						id_parcialidad: idParcialidad,
					},
				)

				await this.GetParcialidades(idHonorario)
				await this.GetHonorariosByCliente(this.selectedClient.id)

				showSuccess(t('empleados', 'Installment marked as invoiced'))

			} catch (err) {
				showError(String(err))
			}
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

		generarReporteHonorarios() {
			// Falta agregar todo el reporte
			showSuccess(
				t('empleados', '{n} fees selected for report', { n: this.selectedHonorarios.length }),
			)
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

			this.h_tipo_moneda = this.currencyOptions.find(c => c.value === honorario.tipo_moneda)
				|| this.currencyOptions[0]

			// Separar el tipo_servicio base del sufijo "- Mes Año" que se concatena al crear
			const raw = String(honorario.tipo_servicio || '')
			const match = raw.match(/^(.*?)(?:\s-\s([A-Za-z]+)\s(\d{4}))?$/)

			this.h_tipo_servicio = match && match[1] ? match[1].trim() : raw

			if (match && match[2] && match[3]) {
				this.h_titulo_mes = this.meses.find(m => m.label === match[2]) || null
				this.h_titulo_anio = { label: match[3], value: Number(match[3]) }
			}

			// Fechas se muestran solo de referencia (bloqueadas)
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
					const mes = this.h_titulo_mes?.label || ''
					const anio = this.h_titulo_anio?.value || ''
					const sufijo = (mes && anio) ? ` - ${mes} ${anio}` : ''
					return base ? `${base}${sufijo}` : (sufijo.trim() || null)
				})()

				await axios.post(generateUrl('/apps/empleados/actualizarMetadatosHonorario'), {
					id_honorario: this.editingHonorarioId,
					tipo_servicio: tipoServicioFinal,
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

		abrirReporteHonorario(honorario) {
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
			} catch (err) {
				showError(t('empleados', 'Error generating report: {error}', { error: String(err) }))
			} finally {
				this.generandoReporte = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.companies-page {
	flex-direction: column;
	gap: 24px;
}

/* ── Header ── */
.companies-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
	margin-left: 37px;

	h2 {
		margin: 4px 0 6px;
		font-size: 1.5rem;
		font-weight: 700;
		color: var(--color-main-text);
	}
}

.header-title {
	display: flex;
	flex-direction: column;
}

.header-actions {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.section-label {
	font-size: 0.75rem;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	color: var(--color-primary-element);
	margin: 0;
}

.section-description {
	font-size: 0.875rem;
	color: var(--color-text-maxcontrast);
	margin: 0;
}

/* ── Stats ── */
.stats-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 12px;

	@media (max-width: 640px) {
		grid-template-columns: 1fr;
	}
}

.stat-card {
	display: inline;
	align-items: center;
	gap: 12px;
	padding: 16px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-soft);
	border: 1px solid var(--color-border);

	div {
		display: flex;
		flex-direction: column;
		gap: 2px;
	}

	span {
		font-size: 0.75rem;
		color: var(--color-text-maxcontrast);
	}

	.value-text {
		font-size: 1.25rem;
		font-weight: 700;
		color: var(--color-main-text);
	}
}

.stat-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 40px;
	height: 40px;
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	flex-shrink: 0;
	margin-inline: 38%;
}

.filter-wrap {
	position: relative;
	display: flex;
	align-items: center;
	gap: 8px;
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

.filter-dropdown {
	position: absolute;
	top: calc(100% + 6px);
	right: 0;
	z-index: 9999;
	width: 190px;
	box-sizing: border-box;
	padding: 6px 0;
	overflow: hidden;
	border: 1px solid rgba(0, 0, 0, 0.28);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}

.filter-section {
	display: flex;
	flex-direction: column;
	gap: 2px;
	box-sizing: border-box;
	width: 100%;
	padding: 3px 10px;
}

.filter-section-label {
	margin: 0 0 4px;
	color: var(--color-text-maxcontrast);
	font-size: 10px;
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.filter-section label {
	display: inline-flex;
	align-items: center;
	gap: 3px;
	min-height: 26px;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1;
}

.filter-section input[type='checkbox'] {
	width: 13px;
	height: 13px;
	margin: 0;
}

.filter-divider {
	margin: 1px 0;
	border: none;
	border-top: 1px solid var(--color-border);
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

.details-header {
	display: flex;
	align-items: flex-start;
	gap: 16px;
}

.details-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 52px;
	height: 52px;
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	flex-shrink: 0;
}

.details-title {
	display: flex;
	flex-direction: column;
	gap: 4px;

	.eyebrow {
		font-size: 0.72rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: var(--color-primary-element);
		margin: 0;
	}

	h2 {
		margin: 0;
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

/* ── Grids ── */
.details-grid,
.info-grid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 10px;

	@media (max-width: 480px) {
		grid-template-columns: 1fr;
	}
}

.detail-card {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 12px 14px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-soft);
	border: 1px solid var(--color-border);

	span {
		font-size: 0.72rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: var(--color-text-maxcontrast);
	}

	.value-text {
		font-size: 0.9rem;
		font-weight: 600;
		color: var(--color-main-text);
		text-transform: none;
		letter-spacing: normal;
	}

	&.detail-card-wide {
		grid-column: span 2;

		@media (max-width: 480px) {
			grid-column: span 1;
		}
	}
}

.breadcrumb {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 0.875rem;
	color: var(--color-main-text);

	.separator {
		color: var(--color-text-maxcontrast);
		font-weight: 400;
		text-transform: none;
		letter-spacing: normal;
	}

	.value-text {
		font-weight: 700;
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

/* ── Children ── */
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

.child-count {
	font-size: 0.75rem;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	flex-shrink: 0;
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
	flex: 1 1 240px;
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
	flex: 1 1 420px;
	align-items: center;
	justify-content: flex-end;
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

.parcialidad-fecha-pago {
	font-size: 0.75rem;
	color: #5ae779;
	font-weight: 1000;
}

/* ── Modal ── */
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

.special-client-card {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 14px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-soft);
	border: 1px solid var(--color-border);
}

.special-client-info {
	display: flex;
	flex-direction: column;
	gap: 2px;

	h3 {
		margin: 0;
		font-size: 0.875rem;
		font-weight: 600;
		color: var(--color-main-text);
	}

	p {
		margin: 0;
		font-size: 0.75rem;
		color: var(--color-text-maxcontrast);
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

.pm-info {
	display: flex;
	align-items: center;
	/* centra verticalmente */
	gap: 10px;
}

.pm-avatar {
	width: 32px;
	height: 32px;
	border-radius: 50%;
	object-fit: cover;
	flex-shrink: 0;
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

.fecha-pago-input {
	display: block;
	width: fit-content;
	margin: 16px auto;
	padding: 7px 30px 30px;
	border: 3px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 1rem;
	font-weight: 700;
	cursor: pointer;
	text-align: center;
	letter-spacing: 0.04em;
}

.fecha-pago-input:focus {
	outline: none;
	border-color: var(--color-primary);
	box-shadow: 0 0 0 2px var(--color-primary-light);
}

.fecha-pago-input:hover {
	border-color: var(--color-primary);
}

.details-header--especial {
	background: linear-gradient(135deg, #6c9cda 10%, #f9f9f9 100%);
	border-radius: 8px;
	padding: 16px 16px 10px 16px;
}

.details-header--especial .eyebrow,
.details-header--especial h2,
.details-header--especial p {
	color: #ffffff;
}

.details-header--especial .details-icon {
	color: #ffffff;
}

.filter-icon-button {
	min-width: unset !important;
	padding-left: 4px !important;
	padding-right: 4px !important;
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

.payment-modal {
	padding: 32px 28px 28px;
	display: flex;
	flex-direction: column;
	align-items: center;
	text-align: center;
}

.payment-icon-wrapper {
	width: 72px;
	height: 72px;
	border-radius: 50%;
	background: linear-gradient(135deg, #43a047, #2e7d32);
	display: flex;
	align-items: center;
	justify-content: center;
	margin-bottom: 16px;
	box-shadow: 0 4px 14px rgba(46, 125, 50, 0.35);
}

.payment-icon {
	font-size: 32px;
	line-height: 1;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	height: 100%;
	transform: translate(0px, -5px);
}

.payment-modal h2 {
	margin: 0 0 8px;
	font-size: 1.3rem;
	font-weight: 600;
	color: var(--color-main-text);
}

.payment-subtitle {
	margin: 0 0 24px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
	line-height: 1.4;
}

.payment-field {
	width: 100%;
	margin-bottom: 28px;
	text-align: left;
}

.payment-actions {
	display: flex;
	justify-content: center;
	gap: 12px;
	width: 100%;
}

.payment-actions :deep(button) {
	min-width: 110px;
	border-radius: 8px;
}

.parcialidad-detalle {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	width: 100%;
	padding: 8px 14px;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	margin-top: 8px;
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

.honorario-checkbox {
	width: 16px;
	height: 16px;
	margin-right: 4px;
	flex-shrink: 0;
	align-self: flex-start;
	margin-top: 4px;
}

.tipo-honorario-selector {
    display: flex;
    gap: 8px;
    margin-bottom: 4px;
}

.tipo-btn {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 10px 8px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    background: var(--color-background-soft);
    cursor: pointer;
    font-size: 0.8rem;
    color: var(--color-text-maxcontrast);
    transition: all 0.15s ease;

    &:hover {
        border-color: var(--color-primary-element);
        background: var(--color-primary-element-light);
    }

    &--active {
        border-color: var(--color-primary-element);
        border-width: 2px;
        background: var(--color-primary-element-light);
        color: var(--color-primary-element);
        font-weight: 600;
    }
}

.tipo-icon {
    font-size: 1.3rem;
}

.tipo-desc {
    margin-bottom: 4px;
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

.top {
	margin-top: 40px;
}
</style>
