<template>
	<NcAppContent :name="t('empleados', 'Companies and groups')">
		<div class="companies-page">
			<div class="companies-header">
				<div class="header-title">
					<p class="section-label">
						{{ t('empleados', 'Customers module') }}
					</p>
					<h2>{{ t('empleados', 'Companies and groups') }}</h2>
					<p class="section-description">
						{{ t('empleados', 'Manage customer groups, companies and sub-companies used by the time reportsmodule.') }}
					</p>
				</div>
			</div>
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

								<h2>{{ t('empleados', 'Select a client for more details') }}</h2>

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
							:options="[{ label: 'A to Z', value: 'az' }, { label: 'Z to A', value: 'za' }]">
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

							<div class="acordeon-item btn-top">
								<button class="acordeon-titulo" @click="toggleAccordeon(0)">
									{{ t('empleados', 'General Information') }}
									<span>{{ accordeon[0].abierto ? '-' : '+' }}</span>
								</button>
								<div :class="['acordeon-contenido', { abierto: accordeon[0].abierto }]">
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
								</div>
							</div>

							<div class="acordeon-item btn-top">
								<button class="acordeon-titulo" @click="toggleAccordeon(1)">
									{{ t('empleados', 'Group Information') }}
									<span>{{ accordeon[1].abierto ? '-' : '+' }}</span>
								</button>
								<div :class="['acordeon-contenido', { abierto: accordeon[1].abierto }]">
									<div class="btn-top">
										<div class="info-section">
											<div class="">
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
								</div>
							</div>

							<div class="info-section">
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
										<span v-else class="value-text">No one has registered yet</span>
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
										<span v-else class="value-text">No one has registered yet</span>
									</div>
								</div>
							</div>

							<!-- Honorarios -->
							<div class="info-section">
								<div class="section-head">
									<div>
										<p class="section-label">
											{{ t('empleados', 'Billing') }}
										</p>
										<h3>{{ t('empleados', 'Service Fees') }}</h3>
									</div>
									<NcButton type="primary" @click="openHonorarioModal">
										{{ t('empleados', 'New fee') }}
									</NcButton>
								</div>

								<NcEmptyContent v-if="!honorarios.length"
									:name="t('empleados', 'No fees registered')"
									:description="t('empleados', 'Register a service fee for this company.')">
									<template #icon>
										<OfficeBuilding />
									</template>
								</NcEmptyContent>

								<div v-else class="honorarios-list">
									<div v-for="honorario in honorarios"
										:key="honorario.id_honorario"
										class="honorario-card">
										<div class="honorario-header">
											<div class="honorario-info">
												<span class="value-text">{{ honorario.tipo_servicio || t('empleados',
													'Service') }}</span>
												<span>{{ honorario.fecha_inicio }} — {{ honorario.fecha_fin }}</span>
											</div>
											<div class="honorario-meta">
												<span class="honorario-amount">
													{{ formatImporte(honorario.importe_total) }} {{
														honorario.tipo_moneda }}
												</span>
												<span class="honorario-badge"
													:class="Number(honorario.activo) ? 'badge-active' : 'badge-done'">
													{{ Number(honorario.activo) ? t('empleados', 'Active') :
														t('empleados', 'Completed') }}
												</span>
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
												<NcButton type="tertiary-no-background"
													@click="askDeleteHonorario(honorario.id_honorario)">
													{{ t('empleados', 'Delete') }}
												</NcButton>
											</div>
										</div>

										<div v-if="parcialidadesAbiertas[honorario.id_honorario]"
											class="parcialidades-list">
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
																▾
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
													<div v-if="detalleAbierto[p.id_parcialidad]"
														class="parcialidad-detalle">
														<span v-if="p.fecha_pago" class="parcialidad-detail-text">
															💳 {{ t('empleados', 'Paid') }}: {{ p.fecha_pago }}
														</span>
													</div>
												</div>
											</template>
										</div>
									</div>
								</div>
							</div>
						</div>

						<NcDialog v-if="showPagoDialog"
							:name="t('empleados', 'Payment date')"
							:open.sync="showPagoDialog"
							@close="showPagoDialog = false">
							<input v-model="fechaPago" class="fecha-pago-input" type="date">

							<template #actions>
								<NcButton @click="showPagoDialog = false">
									{{ t('empleados', 'Cancel') }}
								</NcButton>
								<NcButton type="primary" @click="confirmarPago">
									{{ t('empleados', 'Save') }}
								</NcButton>
							</template>
						</NcDialog>

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
						{{ t('empleados', 'Leave parent group empty to create a main group. Select a parent to create asub - company.') }}
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

		<!-- Modal: Honorario -->
		<NcModal v-if="honorarioModal" :name="t('empleados', 'New service fee')" @close="closeHonorarioModal">
			<div class="modal-content">
				<div class="modal-header">
					<p class="section-label">
						{{ t('empleados', 'Billing') }}
					</p>
					<h2>{{ t('empleados', 'New service fee') }}</h2>
					<p>{{ t('empleados', 'The installments will be calculated automatically by month.') }}</p>
				</div>

				<div class="form-grid">
					<NcTextField :value.sync="h_tipo_servicio" :label="t('empleados', 'Service type')" />

					<NcSelect v-model="h_tipo_moneda"
						:options="currencyOptions"
						label="label"
						track-by="value"
						:searchable="false" />

					<NcTextField type="number"
						class="span-2"
						:value.sync="h_importe_total"
						:label="t('empleados', 'Total amount')" />
					<div class="span-2">
						<span class="date-label">{{ t('empleados', 'Start date') }}</span>
					</div>
					<NcSelect v-model="h_mes_inicio"
						:options="meses"
						:placeholder="t('empleados', 'Month')"
						label="label"
						track-by="value"
						:searchable="false" />
					<NcSelect v-model="h_anio_inicio"
						:options="anios"
						:placeholder="t('empleados', 'Year')"
						label="label"
						track-by="value"
						:searchable="false" />

					<div class="span-2">
						<span class="date-label">{{ t('empleados', 'End date') }}</span>
					</div>
					<NcSelect v-model="h_mes_fin"
						:options="meses"
						:placeholder="t('empleados', 'Month')"
						label="label"
						track-by="value"
						:searchable="false" />
					<NcSelect v-model="h_anio_fin"
						:options="anios"
						:placeholder="t('empleados', 'Year')"
						label="label"
						track-by="value"
						:searchable="false" />

					<NcNoteCard v-if="h_numero_parcialidades > 0" type="info" class="span-2">
						{{ t('empleados', '{n} installment(s) of {amount} {currency}', {
							n: h_numero_parcialidades,
							amount: formatImporte(h_importe_parcialidad),
							currency: h_tipo_moneda ? h_tipo_moneda.value : ''
						}) }}
					</NcNoteCard>
				</div>

				<div class="modal-actions">
					<NcButton @click="closeHonorarioModal">
						{{ t('empleados', 'Cancel') }}
					</NcButton>
					<NcButton type="primary" :disabled="!isHonorarioValid || savingHonorario" @click="crearHonorario">
						{{ savingHonorario ? t('empleados', 'Saving...') : t('empleados', 'Create fee') }}
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
// import Cog from 'vue-material-design-icons/Cog.vue'
import AccountMultiplePlusOutline from 'vue-material-design-icons/AccountMultiplePlusOutline.vue'
import DatabaseExport from 'vue-material-design-icons/DatabaseExport.vue'
import Upload from 'vue-material-design-icons/Upload.vue'
import FilterVariant from 'vue-material-design-icons/FilterVariant.vue'
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
				{ label: 'January', value: 1 },
				{ label: 'February', value: 2 },
				{ label: 'March', value: 3 },
				{ label: 'April', value: 4 },
				{ label: 'May', value: 5 },
				{ label: 'June', value: 6 },
				{ label: 'July', value: 7 },
				{ label: 'August', value: 8 },
				{ label: 'September', value: 9 },
				{ label: 'October', value: 10 },
				{ label: 'November', value: 11 },
				{ label: 'December', value: 12 },
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
			accordeon: [
				{ abierto: false },
				{ abierto: false },
				{ abierto: false },
				{ abierto: false },
			],
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
			return Number(this.h_importe_total) > 0
				&& Boolean(this.h_tipo_moneda)
				&& Boolean(this.h_mes_inicio)
				&& Boolean(this.h_anio_inicio)
				&& Boolean(this.h_mes_fin)
				&& Boolean(this.h_anio_fin)
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

		AgregarNuevo() {
			this.toggle()
			this.$root.$emit('new', true)
		},

		toggle() {
			this.button = !this.button
		},
		toggleAccordeon(index) {
			this.accordeon = this.accordeon.map((item, i) => ({
				...item,
				abierto: i === index ? !item.abierto : false,
			}))
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
				const payload = {
					id_cliente: this.selectedClient.id,
					importe_total: Number(this.h_importe_total),
					tipo_moneda: this.h_tipo_moneda?.value || 'MXN',
					fecha_inicio: this.h_mes_inicio && this.h_anio_inicio
						? `${this.h_anio_inicio.value}-${String(this.h_mes_inicio.value).padStart(2, '0')}-01`
						: '',
					fecha_fin: this.h_mes_fin && this.h_anio_fin
						? `${this.h_anio_fin.value}-${String(this.h_mes_fin.value).padStart(2, '0')}-01`
						: '',
					tipo_servicio: String(this.h_tipo_servicio || '').trim() || null,
				}

				if (this.honorarioBorradorId) {
					// Es un borrador del import — actualizar y generar parcialidades
					await axios.post(generateUrl('/apps/empleados/completarHonorario'), {
						...payload,
						id_honorario: this.honorarioBorradorId,
					})
				} else {
					// Flujo normal — crear nuevo
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
	},
}
</script>

<style scoped lang="scss">
.companies-page {
	flex-direction: column;
	gap: 24px;
	padding: 24px;
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
.honorarios-list {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.honorario-card {
	border-radius: var(--border-radius-large);
	border: 1px solid var(--color-border);
	background: var(--color-background-soft);
	overflow: hidden;
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
	flex-direction: column;
	gap: 2px;

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

.honorario-meta {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.honorario-amount {
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
		background: #dbeafe;
		color: #1d4ed8;
	}

	&.badge-done {
		background: #dcfce7;
		color: #166534;
	}
}

/* ── Parcialidades ── */
.parcialidades-list {
	border-top: 1px solid var(--color-border);
	display: flex;
	flex-direction: column;
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
	cursor: pointer;
	font-size: 0.9rem;
	color: var(--color-text-maxcontrast, #666);
	transition: transform 0.2s ease;
	user-select: none;
}

/* cuando está abierto */
.parcialidad-toggle.open {
	transform: rotate(180deg);
}

.parcialidad-toggle:hover {
	color: var(--color-primary, #18b13c);
}

.parcialidad-row {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px 14px;
	border-bottom: 1px solid var(--color-border);
	flex-wrap: wrap;

	&:last-child {
		border-bottom: none;
	}

	&.parcialidad-pagada {
		background: var(--color-background-soft);
		opacity: 0.7;
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
	background: linear-gradient(135deg, #6c9cda 20%, #0c254b 100%);
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
.acordeon-item {
	margin-bottom: 10px;
	border-radius: 5px;
	overflow: hidden;
}
.acordeon-titulo {
	width: 100%;
	text-align: center;
	border: none;
	justify-content: space-between;
	align-items: center;
}

.acordeon-contenido {
	max-height: 0;
	opacity: 0;
	overflow: hidden;
	transition: all 0.3s ease-in-out;
}

.acordeon-contenido.abierto {
	max-height: 500px;
	opacity: 1;
}
</style>
