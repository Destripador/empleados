<template>
	<NcAppContent
		class="inventario-page"
		:name="t('empleados', 'IT Inventory')">
		<div class="inventario-header">
			<div class="inventario-heading">
				<h2>{{ pageTitle }}</h2>
				<p>{{ pageSubtitle }}</p>
			</div>
			<NcButton
				v-if="maintenanceCapabilities.moduleEnabled && maintenanceCapabilities.canViewMaintenance"
				type="secondary"
				@click="$router.push({ name: 'Mantenimientos' })">
				<template #icon>
					<CalendarMonth :size="20" />
				</template>
				{{ t('empleados', 'Maintenance calendar') }}
			</NcButton>

			<NcActions
				class="inventario-actions"
				:force-menu="true"
				:disabled="loading">
				<template #icon>
					<Plus :size="20" />
				</template>

				<NcActionButton
					:disabled="tab === 'soporte' && !selectedEquipo"
					@click="openCreateModal">
					<template #icon>
						<Plus :size="20" />
					</template>
					{{ primaryButtonText }}
				</NcActionButton>

				<NcActionButton @click="downloadImportTemplate">
					<template #icon>
						<Download :size="20" />
					</template>
					{{ t('empleados', 'Download CSV template') }}
				</NcActionButton>

				<NcActionButton
					:disabled="tab === 'soporte' && !selectedEquipo"
					@click="openImportModal">
					<template #icon>
						<Upload :size="20" />
					</template>
					{{ t('empleados', 'Import CSV') }}
				</NcActionButton>

				<NcActionButton
					:disabled="currentRows.length === 0"
					@click="exportCurrentTab">
					<template #icon>
						<Download :size="20" />
					</template>
					{{ t('empleados', 'Export CSV') }}
				</NcActionButton>

				<NcActionButton
					v-if="tab !== 'modelos'"
					@click="openModelos">
					<template #icon>
						<Database :size="20" />
					</template>
					{{ t('empleados', 'Manage models') }}
				</NcActionButton>

				<NcActionButton
					v-if="tab === 'modelos'"
					@click="setTab('equipos')">
					<template #icon>
						<Laptop :size="20" />
					</template>
					{{ t('empleados', 'Back to devices') }}
				</NcActionButton>
			</NcActions>
		</div>

		<div class="inventario-summary" aria-hidden="true">
			<div
				v-for="item in summaryItems"
				:key="item.id"
				class="summary-item">
				<component :is="item.icon" :size="20" />
				<span>{{ item.label }}</span>
				<strong>{{ item.value }}</strong>
			</div>
		</div>

		<div
			class="inventario-tabs"
			role="tablist"
			:aria-label="t('empleados', 'Inventory sections')">
			<button
				v-for="item in tabs"
				:key="item.id"
				type="button"
				role="tab"
				:aria-selected="tab === item.id"
				:class="{ active: tab === item.id }"
				@click="setTab(item.id)">
				<component :is="item.icon" :size="20" />
				<span>{{ item.name }}</span>
			</button>
		</div>

		<div class="inventario-toolbar">
			<div class="search-field">
				<NcTextField
					:value.sync="search"
					:label="t('empleados', 'Search')"
					@update:value="scheduleSearch"
					@keyup.enter="applyFilters">
					<template #icon>
						<Magnify :size="20" />
					</template>
				</NcTextField>
			</div>

			<template v-if="tab === 'equipos'">
				<label class="compact-filter">
					<span>{{ t('empleados', 'Status') }}</span>
					<select v-model="filters.estado" @change="applyFilters">
						<option value="">{{ t('empleados', 'All statuses') }}</option>
						<option value="activo">{{ t('empleados', 'Active') }}</option>
						<option value="asignado">{{ t('empleados', 'Assigned') }}</option>
						<option value="mantenimiento">{{ t('empleados', 'Maintenance') }}</option>
						<option value="inactivo">{{ t('empleados', 'Inactive') }}</option>
						<option value="baja">{{ t('empleados', 'Retired') }}</option>
					</select>
				</label>

				<label class="compact-filter">
					<span>{{ t('empleados', 'Assignment') }}</span>
					<select v-model="filters.asignacion" @change="applyFilters">
						<option value="">{{ t('empleados', 'All devices') }}</option>
						<option value="asignado">{{ t('empleados', 'Assigned') }}</option>
						<option value="sin_asignar">{{ t('empleados', 'Unassigned') }}</option>
					</select>
				</label>

				<label class="compact-filter">
					<span>{{ t('empleados', 'Employee') }}</span>
					<select v-model="filters.idEmpleado" @change="applyFilters">
						<option value="">{{ t('empleados', 'All employees') }}</option>
						<option v-for="empleado in empleadosFiltro"
							:key="empleado.id_empleado"
							:value="String(empleado.id_empleado)">
							{{ empleado.displayname }}
						</option>
					</select>
				</label>

				<label class="compact-filter">
					<span>{{ t('empleados', 'Model') }}</span>
					<select v-model="filters.idModelo" @change="applyFilters">
						<option value="">{{ t('empleados', 'All models') }}</option>
						<option v-for="modelo in modelosCatalogo"
							:key="modelo.id_modelo"
							:value="String(modelo.id_modelo)">
							{{ modeloLabel(modelo) }}
						</option>
					</select>
				</label>

				<NcButton v-if="hasActiveFilters" type="tertiary" @click="clearFilters">
					<template #icon>
						<FilterOff :size="20" />
					</template>
					{{ t('empleados', 'Clear filters') }}
				</NcButton>
			</template>

			<NcButton
				:disabled="loading"
				:aria-label="t('empleados', 'Refresh inventory')"
				@click="reload">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
					<Refresh v-else :size="20" />
				</template>
				{{ t('empleados', 'Refresh') }}
			</NcButton>
		</div>

		<div class="inventario-card">
			<div v-if="loading" class="loading-state">
				<NcLoadingIcon :size="48" />
			</div>

			<template v-else>
				<!-- MODELOS -->
				<div v-if="tab === 'modelos'" class="table-wrap">
					<table v-if="modelos.length > 0" class="inventario-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Brand') }}</th>
								<th>{{ t('empleados', 'Model') }}</th>
								<th>{{ t('empleados', 'CPU') }}</th>
								<th>{{ t('empleados', 'RAM') }}</th>
								<th>{{ t('empleados', 'Storage') }}</th>
								<th>{{ t('empleados', 'Type') }}</th>
								<th>{{ t('empleados', 'Touch') }}</th>
								<th>{{ t('empleados', 'Actions') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr
								v-for="modelo in modelos"
								:key="modelo.id_modelo">
								<td class="strong-cell">
									{{ displayValue(modelo.marca) }}
								</td>
								<td>{{ displayValue(modelo.modelo) }}</td>
								<td>{{ displayValue(modelo.procesador) }}</td>
								<td>{{ displayValue(modelo.ram) }}</td>
								<td>{{ displayValue(modelo.disco_duro) }}</td>
								<td>{{ displayValue(modelo.tipo) }}</td>
								<td>
									<span
										class="boolean-pill"
										:class="{ active: isTruthy(modelo.touch) }">
										<Check v-if="isTruthy(modelo.touch)" :size="16" />
										<Close v-else :size="16" />
										{{ isTruthy(modelo.touch) ? t('empleados', 'Yes') : t('empleados', 'No') }}
									</span>
								</td>
								<td class="actions-cell">
									<NcButton
										size="small"
										:aria-label="t('empleados', 'Edit model')"
										@click="openEditModelo(modelo)">
										<template #icon>
											<Pencil :size="18" />
										</template>
										{{ t('empleados', 'Edit') }}
									</NcButton>
								</td>
							</tr>
						</tbody>
					</table>

					<NcEmptyContent
						v-else
						:name="t('empleados', 'No models found')" />
				</div>

				<!-- EQUIPOS -->
				<div v-if="tab === 'equipos'" class="table-wrap">
					<p class="inventory-focus-announcement" aria-live="polite">
						{{ focusedDeviceId ? t('empleados', 'The requested device is highlighted in the inventory table.') : '' }}
					</p>
					<table v-if="equipos.length > 0" class="inventario-table inventario-table--devices">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Device name') }}</th>
								<th>{{ t('empleados', 'System name') }}</th>
								<th>{{ t('empleados', 'Serial number') }}</th>
								<th>{{ t('empleados', 'Model') }}</th>
								<th>{{ t('empleados', 'Status') }}</th>
								<th>{{ t('empleados', 'Assigned employee') }}</th>
								<th>{{ t('empleados', 'Actions') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr
								v-for="equipo in equipos"
								:key="equipo.id_equipo"
								:data-device-id="equipo.id_equipo"
								:class="{ 'inventory-device-highlight': focusedDeviceId === Number(equipo.id_equipo) }"
								:aria-current="focusedDeviceId === Number(equipo.id_equipo) ? 'true' : null"
								tabindex="-1">
								<td class="strong-cell">
									{{ displayValue(equipo.nombre_dispositivo) }}
								</td>
								<td>{{ displayValue(equipo.nombre_sistema) }}</td>
								<td>{{ displayValue(equipo.numero_serie) }}</td>
								<td>{{ modeloName(equipo) }}</td>
								<td>
									<span
										class="status-pill"
										:class="statusClass(equipo.estado)">
										{{ displayValue(equipo.estado) }}
									</span>
								</td>
								<td>
									<div v-if="equipo.empleado_uid" class="employee-cell">
										<NcAvatar
											:user="equipo.empleado_uid"
											:display-name="empleadoAsignadoName(equipo)"
											:show-user-status="false"
											:show-user-status-compact="false"
											:size="36"
											disable-menu />
										<div>
											<strong>{{ empleadoAsignadoName(equipo) }}</strong>
											<small>{{ equipo.empleado_uid }}</small>
										</div>
									</div>
									<div v-else class="employee-cell employee-cell--empty">
										<span class="neutral-avatar"><AccountOutline :size="22" /></span>
										<strong>{{ t('empleados', 'Unassigned') }}</strong>
									</div>
								</td>
								<td class="actions-cell">
									<div class="row-actions">
										<NcButton
											size="small"
											type="primary"
											:aria-label="t('empleados', 'View device')"
											@click="openViewEquipo(equipo)">
											<template #icon>
												<Eye :size="18" />
											</template>
											{{ t('empleados', 'View') }}
										</NcButton>

										<NcActions :aria-label="t('empleados', 'Device actions')">
											<NcActionButton close-after-click @click="openEditEquipo(equipo)">
												<template #icon>
													<Pencil :size="18" />
												</template>
												{{ t('empleados', 'Edit') }}
											</NcActionButton>
											<NcActionButton close-after-click @click="openHistory(equipo)">
												<template #icon>
													<History :size="18" />
												</template>
												{{ t('empleados', 'History') }}
											</NcActionButton>
											<NcActionButton close-after-click @click="selectEquipoSoporte(equipo)">
												<template #icon>
													<Wrench :size="18" />
												</template>
												{{ t('empleados', 'Register support') }}
											</NcActionButton>
										</NcActions>
									</div>
								</td>
							</tr>
						</tbody>
					</table>

					<div v-if="equipos.length > 0" class="inventory-pagination">
						<span>{{ paginationLabel }}</span>
						<div>
							<NcButton :disabled="loading || pageOffset === 0" @click="previousPage">
								{{ t('empleados', 'Previous') }}
							</NcButton>
							<NcButton :disabled="loading || pageOffset + pageLimit >= totalEquipos" @click="nextPage">
								{{ t('empleados', 'Next') }}
							</NcButton>
						</div>
					</div>

					<NcEmptyContent
						v-else
						:name="hasActiveFilters ? t('empleados', 'No devices match the current filters') : t('empleados', 'No devices have been registered')" />
				</div>

				<!-- SOPORTE -->
				<div v-if="tab === 'soporte'" class="table-wrap">
					<div v-if="selectedEquipo" class="selected-equipo">
						<div>
							<span>{{ t('empleados', 'Selected device') }}</span>
							<strong>{{ displayValue(selectedEquipo.nombre_dispositivo) }}</strong>
						</div>
						<span>{{ displayValue(selectedEquipo.numero_serie) }}</span>
					</div>

					<NcEmptyContent
						v-if="!selectedEquipo"
						:name="t('empleados', 'Select a device to view support history')" />

					<table v-else-if="soporte.length > 0" class="inventario-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Date') }}</th>
								<th>{{ t('empleados', 'Action') }}</th>
								<th>{{ t('empleados', 'Current user') }}</th>
								<th>{{ t('empleados', 'Support user') }}</th>
								<th>{{ t('empleados', 'Details') }}</th>
								<th>{{ t('empleados', 'Duration') }}</th>
								<th>{{ t('empleados', 'Time report') }}</th>
								<th>{{ t('empleados', 'Actions') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr
								v-for="item in soporte"
								:key="item.id_soporte">
								<td>{{ displayValue(item.fecha) }}</td>
								<td class="strong-cell">
									{{ displayValue(item.accion) }}
								</td>
								<td>{{ displayValue(item.usuario_actual) }}</td>
								<td>{{ displayValue(item.usuario_soporte) }}</td>
								<td>{{ displayValue(item.detalles) }}</td>
								<td>{{ supportDurationLabel(item.duracion_minutos) }}</td>
								<td>{{ item.id_reporte ? `#${item.id_reporte}` : t('empleados', 'Pending synchronization') }}</td>
								<td>
									<NcButton :title="t('empleados', 'Edit')" @click="openEditSoporte(item)">
										<template #icon>
											<Pencil :size="18" />
										</template>
									</NcButton>
								</td>
							</tr>
						</tbody>
					</table>

					<NcEmptyContent
						v-else
						:name="t('empleados', 'No support records found')" />
				</div>
			</template>
		</div>

		<!-- MODAL CREAR -->
		<NcModal
			v-if="showModal"
			class="inventario-nc-modal"
			:name="modalTitle"
			@close="closeModal">
			<div class="inventario-modal">
				<!-- Crear modelo -->
				<div v-if="tab === 'modelos'" class="form-grid">
					<NcTextField
						:value.sync="formModelo.marca"
						:label="t('empleados', 'Brand')" />

					<NcTextField
						:value.sync="formModelo.modelo"
						:label="t('empleados', 'Model')" />

					<NcTextField
						:value.sync="formModelo.procesador"
						:label="t('empleados', 'CPU')" />

					<NcTextField
						:value.sync="formModelo.ram"
						:label="t('empleados', 'RAM')" />

					<NcTextField
						:value.sync="formModelo.disco_duro"
						:label="t('empleados', 'Storage')" />

					<NcTextField
						:value.sync="formModelo.tipo"
						:label="t('empleados', 'Type')" />

					<NcCheckboxRadioSwitch v-model="formModelo.touch">
						{{ t('empleados', 'Touch screen') }}
					</NcCheckboxRadioSwitch>
				</div>

				<!-- Crear equipo -->
				<div v-if="tab === 'equipos'" class="form-grid">
					<div class="select-field">
						<label>{{ t('empleados', 'Model') }}</label>

						<select v-model="formEquipo.id_modelo" :disabled="isReadonly">
							<option value="">
								{{ t('empleados', 'Select a model') }}
							</option>

							<option
								v-for="modelo in modelosCatalogo"
								:key="modelo.id_modelo"
								:value="modelo.id_modelo">
								{{ modeloLabel(modelo) }}
							</option>
						</select>
					</div>

					<NcTextField
						:value.sync="formEquipo.nombre_dispositivo"
						:disabled="isReadonly"
						:label="t('empleados', 'Device name')" />

					<NcTextField
						:value.sync="formEquipo.nombre_sistema"
						:disabled="isReadonly"
						:label="t('empleados', 'System name')" />

					<NcTextField
						:value.sync="formEquipo.numero_serie"
						:disabled="isReadonly"
						:label="t('empleados', 'Serial number')" />

					<div class="select-field">
						<label>{{ t('empleados', 'Status') }}</label>

						<select v-model="formEquipo.estado" :disabled="isReadonly">
							<option value="activo">
								{{ t('empleados', 'Active') }}
							</option>
							<option value="asignado">
								{{ t('empleados', 'Assigned') }}
							</option>
							<option value="mantenimiento">
								{{ t('empleados', 'Maintenance') }}
							</option>
							<option value="inactivo">
								{{ t('empleados', 'Inactive') }}
							</option>
							<option value="baja">
								{{ t('empleados', 'Retired') }}
							</option>
						</select>
					</div>

					<NcTextArea
						class="form-field--full"
						:value.sync="formEquipo.info"
						:disabled="isReadonly"
						:label="t('empleados', 'Information')" />

					<div v-if="isReadonly" class="readonly-field form-field--full">
						<span>{{ t('empleados', 'Assigned employee') }}</span>
						<strong>{{ empleadoAsignadoName(editingItem || {}) }}</strong>
						<small v-if="editingItem && editingItem.empleado_uid">{{ editingItem.empleado_uid }}</small>
					</div>
				</div>

				<!-- Crear soporte -->
				<div v-if="tab === 'soporte'" class="form-grid form-grid--single">
					<p v-if="selectedEquipo" class="modal-context">
						{{ displayValue(selectedEquipo.nombre_dispositivo) }} -
						{{ displayValue(selectedEquipo.numero_serie) }}
					</p>

					<label class="native-field">
						<span>{{ t('empleados', 'Category') }}</span>
						<select v-model="formSoporte.categoria">
							<option value="mantenimiento">{{ t('empleados', 'Maintenance') }}</option>
							<option value="reparacion">{{ t('empleados', 'Repair') }}</option>
							<option value="diagnostico">{{ t('empleados', 'Diagnostics') }}</option>
							<option value="configuracion">{{ t('empleados', 'Configuration') }}</option>
							<option value="otro">{{ t('empleados', 'Other') }}</option>
						</select>
					</label>
					<label class="native-field">
						<span>{{ t('empleados', 'Priority') }}</span>
						<select v-model="formSoporte.prioridad">
							<option value="baja">{{ t('empleados', 'Low') }}</option>
							<option value="media">{{ t('empleados', 'Medium') }}</option>
							<option value="alta">{{ t('empleados', 'High') }}</option>
							<option value="critica">{{ t('empleados', 'Critical') }}</option>
						</select>
					</label>
					<label class="native-field">
						<span>{{ t('empleados', 'Support date') }}</span>
						<input v-model="formSoporte.fecha" type="datetime-local">
					</label>
					<SupportDurationFields v-model="formSoporte.duracion_minutos" />

					<div class="readonly-grid">
						<div class="readonly-field">
							<span>{{ t('empleados', 'Current user') }}</span>
							<strong>{{ displayValue(formSoporte.usuario_actual) }}</strong>
						</div>

						<div class="readonly-field">
							<span>{{ t('empleados', 'Support user') }}</span>
							<strong>{{ displayValue(formSoporte.usuario_soporte) }}</strong>
						</div>
					</div>

					<NcTextArea
						:value.sync="formSoporte.detalles"
						:label="t('empleados', 'Details')" />
				</div>

				<div class="inventario-modal-actions">
					<NcButton @click="closeModal">
						{{ isReadonly ? t('empleados', 'Close') : t('empleados', 'Cancel') }}
					</NcButton>

					<NcButton
						v-if="!isReadonly"
						type="primary"
						:disabled="loading || (tab === 'soporte' && !validSupportForm)"
						@click="saveModal">
						{{ editMode ? t('empleados', 'Update') : t('empleados', 'Save') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<NcModal
			v-if="showHistoryModal"
			class="inventario-nc-modal"
			size="large"
			:name="t('empleados', 'Device history')"
			@close="closeHistory">
			<div class="inventario-modal history-modal">
				<div v-if="historyDevice" class="history-device-heading">
					<Laptop :size="24" />
					<div>
						<strong>{{ displayValue(historyDevice.nombre_dispositivo) }}</strong>
						<span>{{ displayValue(historyDevice.numero_serie) }}</span>
					</div>
				</div>

				<div v-if="historyLoading && historyEntries.length === 0" class="loading-state">
					<NcLoadingIcon :size="42" />
				</div>

				<div v-else-if="historyError" class="history-error" role="alert">
					<p>{{ historyError }}</p>
					<NcButton @click="loadHistory(true)">
						{{ t('empleados', 'Try again') }}
					</NcButton>
				</div>

				<NcEmptyContent
					v-else-if="historyEntries.length === 0"
					:name="t('empleados', 'No history records found')" />

				<ol v-else class="history-list">
					<li v-for="entry in historyEntries" :key="entry.id" class="history-entry">
						<div class="history-entry__header">
							<span class="history-type">{{ movementTypeLabel(entry.tipo_movimiento) }}</span>
							<time :datetime="entry.fecha">{{ formatDateTime(entry.fecha) }}</time>
						</div>
						<dl class="history-details">
							<div v-if="entry.actor_nombre || entry.actor_uid">
								<dt>{{ t('empleados', 'Actor') }}</dt>
								<dd>{{ entry.actor_nombre || entry.actor_uid }}</dd>
							</div>
							<div v-if="entry.empleado_anterior_nombre || entry.empleado_anterior_uid">
								<dt>{{ t('empleados', 'Previous employee') }}</dt>
								<dd>{{ entry.empleado_anterior_nombre || entry.empleado_anterior_uid }}</dd>
							</div>
							<div v-if="entry.empleado_nuevo_nombre || entry.empleado_nuevo_uid">
								<dt>{{ t('empleados', 'New employee') }}</dt>
								<dd>{{ entry.empleado_nuevo_nombre || entry.empleado_nuevo_uid }}</dd>
							</div>
							<div v-if="entry.estado_anterior">
								<dt>{{ t('empleados', 'Previous status') }}</dt>
								<dd>{{ entry.estado_anterior }}</dd>
							</div>
							<div v-if="entry.estado_nuevo">
								<dt>{{ t('empleados', 'New status') }}</dt>
								<dd>{{ entry.estado_nuevo }}</dd>
							</div>
						</dl>
						<p v-if="entry.descripcion" class="history-description">
							{{ entry.descripcion }}
						</p>
						<ul v-if="historyChanges(entry).length" class="history-changes">
							<li v-for="change in historyChanges(entry)" :key="change.field">
								<strong>{{ fieldLabel(change.field) }}:</strong>
								<span>{{ displayValue(change.anterior) }} → {{ displayValue(change.nuevo) }}</span>
							</li>
						</ul>
					</li>
				</ol>

				<div v-if="historyEntries.length < historyTotal && !historyError" class="history-load-more">
					<NcButton :disabled="historyLoading" @click="loadHistory(false)">
						<template #icon>
							<NcLoadingIcon v-if="historyLoading" :size="20" />
						</template>
						{{ t('empleados', 'Load more') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- MODAL IMPORTAR -->
		<NcModal
			v-if="showImportModal"
			class="inventario-nc-modal"
			:name="importModalTitle"
			@close="closeImportModal">
			<div class="inventario-modal">
				<div class="import-box">
					<p class="modal-context">
						{{ importHelpText }}
					</p>

					<div class="import-actions">
						<NcButton @click="downloadImportTemplate">
							<template #icon>
								<Download :size="20" />
							</template>
							{{ t('empleados', 'Download template') }}
						</NcButton>

						<label class="file-input-button">
							<input
								type="file"
								accept=".csv,text/csv"
								@change="handleImportFile">
							<span>{{ t('empleados', 'Select CSV file') }}</span>
						</label>
					</div>

					<div v-if="importErrors.length > 0" class="import-errors">
						<strong>{{ t('empleados', 'Import errors') }}</strong>

						<ul>
							<li
								v-for="(error, index) in importErrors"
								:key="index">
								{{ error }}
							</li>
						</ul>
					</div>

					<div v-if="importRows.length > 0" class="import-preview">
						<strong>
							{{ t('empleados', 'Preview') }}:
							{{ importRows.length }}
							{{ t('empleados', 'records') }}
						</strong>

						<div class="table-wrap">
							<table class="inventario-table">
								<thead>
									<tr>
										<th
											v-for="column in importColumns"
											:key="column.key">
											{{ column.label }}
										</th>
									</tr>
								</thead>

								<tbody>
									<tr
										v-for="(row, rowIndex) in importRows.slice(0, 10)"
										:key="rowIndex">
										<td
											v-for="column in importColumns"
											:key="column.key">
											{{ displayValue(row[column.key]) }}
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<p v-if="importRows.length > 10" class="modal-context">
							{{ t('empleados', 'Only the first 10 records are shown.') }}
						</p>
					</div>
				</div>

				<div class="inventario-modal-actions">
					<NcButton @click="closeImportModal">
						{{ t('empleados', 'Cancel') }}
					</NcButton>

					<NcButton
						type="primary"
						:disabled="importing || importRows.length === 0 || importErrors.length > 0"
						@click="saveImport">
						<template #icon>
							<NcLoadingIcon v-if="importing" :size="20" />
							<Upload v-else :size="20" />
						</template>
						{{ t('empleados', 'Import') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</NcAppContent>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import {
	NcAppContent,
	NcButton,
	NcTextField,
	NcTextArea,
	NcModal,
	NcEmptyContent,
	NcLoadingIcon,
	NcCheckboxRadioSwitch,
	NcActions,
	NcActionButton,
	NcAvatar,
} from '@nextcloud/vue'

import Check from 'vue-material-design-icons/Check.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Database from 'vue-material-design-icons/Database.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import History from 'vue-material-design-icons/History.vue'
import Laptop from 'vue-material-design-icons/Laptop.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import Wrench from 'vue-material-design-icons/Wrench.vue'
import Download from 'vue-material-design-icons/Download.vue'
import Upload from 'vue-material-design-icons/Upload.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import AccountOutline from 'vue-material-design-icons/AccountOutline.vue'
import FilterOff from 'vue-material-design-icons/FilterOff.vue'
import CalendarMonth from 'vue-material-design-icons/CalendarMonth.vue'

import inventarioService from '../../../services/inventarioService.js'
import { parseInventoryDeviceId } from '../../../utils/inventoryRoute.js'
import { formatSupportDuration, isValidSupportDate } from '../../../utils/supportDuration.js'
import SupportDurationFields from '../../../components/Inventario/SupportDurationFields.vue'
import permissionsMixin from '../../../mixins/permissions.js'
import { maintenanceCapabilities } from '../../../utils/mantenimientoFormatters.js'

function localDateTimeValue() {
	const now = new Date()
	now.setMinutes(now.getMinutes() - now.getTimezoneOffset())
	return now.toISOString().slice(0, 16)
}

export default {
	name: 'Inventario',

	components: {
		NcAppContent,
		NcButton,
		NcTextField,
		NcTextArea,
		NcModal,
		NcEmptyContent,
		NcLoadingIcon,
		NcCheckboxRadioSwitch,
		NcActions,
		NcActionButton,
		NcAvatar,
		AccountOutline,
		CalendarMonth,
		Check,
		Close,
		Database,
		Download,
		Eye,
		FilterOff,
		History,
		Laptop,
		Magnify,
		Plus,
		Pencil,
		Refresh,
		Upload,
		Wrench,
		SupportDurationFields,
	},
	mixins: [permissionsMixin],
	inject: { configuraciones: { default: () => ({}) } },

	data() {
		return {
			tab: 'equipos',
			search: '',
			loading: false,
			showModal: false,
			editMode: false,
			modalMode: 'create',
			editingItem: null,

			modelos: [],
			modelosCatalogo: [],
			equipos: [],
			soporte: [],
			empleadosFiltro: [],
			totalEquipos: 0,
			pageLimit: 25,
			pageOffset: 0,
			filters: {
				estado: '',
				asignacion: '',
				idEmpleado: '',
				idModelo: '',
			},
			searchTimer: null,

			selectedEquipo: null,
			focusedDeviceId: null,
			lastProcessedDeviceParam: null,
			focusDeviceTimer: null,
			skipNextTabReload: false,
			showHistoryModal: false,
			historyDevice: null,
			historyEntries: [],
			historyTotal: 0,
			historyLimit: 20,
			historyLoading: false,
			historyError: '',

			formModelo: {
				marca: '',
				modelo: '',
				procesador: '',
				ram: '',
				disco_duro: '',
				tipo: '',
				touch: false,
			},

			formEquipo: {
				id_modelo: '',
				nombre_dispositivo: '',
				nombre_sistema: '',
				numero_serie: '',
				estado: 'activo',
				info: '',
			},

			formSoporte: {
				categoria: 'mantenimiento',
				prioridad: 'media',
				detalles: '',
				fecha: localDateTimeValue(),
				duracion_minutos: null,
				usuario_actual: '',
				usuario_soporte: '',
			},
			showImportModal: false,
			importing: false,
			importRows: [],
			importErrors: [],
		}
	},

	computed: {
		maintenanceCapabilities() {
			return maintenanceCapabilities(this.permissions, this.configuraciones)
		},
		validSupportForm() {
			return this.formSoporte.detalles.trim() !== ''
				&& Number.isInteger(this.formSoporte.duracion_minutos)
				&& this.formSoporte.duracion_minutos > 0
				&& isValidSupportDate(this.formSoporte.fecha)
		},
		tabs() {
			return [
				{
					id: 'equipos',
					name: t('empleados', 'Devices'),
					icon: 'Laptop',
				},
				{
					id: 'soporte',
					name: t('empleados', 'Support'),
					icon: 'Wrench',
				},
			]
		},

		summaryItems() {
			return [
				{
					id: 'modelos',
					label: t('empleados', 'Models'),
					value: this.modelos.length,
					icon: 'Database',
				},
				{
					id: 'equipos',
					label: t('empleados', 'Devices'),
					value: this.totalEquipos,
					icon: 'Laptop',
				},
				{
					id: 'soporte',
					label: t('empleados', 'Support'),
					value: this.soporte.length,
					icon: 'History',
				},
			]
		},

		primaryButtonText() {
			if (this.tab === 'modelos') return t('empleados', 'New model')
			if (this.tab === 'equipos') return t('empleados', 'New device')
			return t('empleados', 'New support record')
		},

		modalTitle() {
			if (this.isReadonly) {
				return t('empleados', 'Device details')
			}
			if (!this.editMode) {
				return this.primaryButtonText
			}

			if (this.tab === 'modelos') {
				return t('empleados', 'Edit model')
			}

			if (this.tab === 'equipos') {
				return t('empleados', 'Edit device')
			}

			return t('empleados', 'Edit support record')
		},
		isReadonly() {
			return this.modalMode === 'readonly'
		},
		hasActiveFilters() {
			return this.search.trim() !== ''
				|| Object.values(this.filters).some(value => value !== '')
		},
		paginationLabel() {
			if (this.totalEquipos === 0) return ''
			const start = this.pageOffset + 1
			const end = Math.min(this.pageOffset + this.equipos.length, this.totalEquipos)
			return t('empleados', 'Showing {start}–{end} of {total} devices', {
				start,
				end,
				total: this.totalEquipos,
			})
		},
		currentRows() {
			if (this.tab === 'modelos') {
				return this.modelos
			}

			if (this.tab === 'equipos') {
				return this.equipos
			}

			if (this.tab === 'soporte') {
				return this.soporte
			}

			return []
		},

		importModalTitle() {
			if (this.tab === 'modelos') {
				return t('empleados', 'Import models')
			}

			if (this.tab === 'equipos') {
				return t('empleados', 'Import devices')
			}

			return t('empleados', 'Import support records')
		},

		importHelpText() {
			if (this.tab === 'modelos') {
				return t('empleados', 'Import computer models from a CSV file.')
			}

			if (this.tab === 'equipos') {
				return t('empleados', 'Import devices from a CSV file. You can use id_modelo or marca + modelo.')
			}

			return t('empleados', 'Import support records for the selected device.')
		},

		importColumns() {
			if (this.tab === 'modelos') {
				return [
					{ key: 'marca', label: 'marca', required: true },
					{ key: 'modelo', label: 'modelo', required: true },
					{ key: 'procesador', label: 'procesador', required: false },
					{ key: 'ram', label: 'ram', required: false },
					{ key: 'disco_duro', label: 'disco_duro', required: false },
					{ key: 'tipo', label: 'tipo', required: false },
					{ key: 'touch', label: 'touch', required: false },
				]
			}

			if (this.tab === 'equipos') {
				return [
					{ key: 'id_modelo', label: 'id_modelo', required: false },
					{ key: 'marca', label: 'marca', required: false },
					{ key: 'modelo', label: 'modelo', required: false },
					{ key: 'nombre_dispositivo', label: 'nombre_dispositivo', required: true },
					{ key: 'nombre_sistema', label: 'nombre_sistema', required: false },
					{ key: 'numero_serie', label: 'numero_serie', required: false },
					{ key: 'estado', label: 'estado', required: false },
					{ key: 'info', label: 'info', required: false },
				]
			}

			return [
				{ key: 'categoria', label: 'categoria', required: true },
				{ key: 'prioridad', label: 'prioridad', required: true },
				{ key: 'usuario_actual', label: 'usuario_actual', required: false },
				{ key: 'usuario_soporte', label: 'usuario_soporte', required: false },
				{ key: 'detalles', label: 'detalles', required: true },
				{ key: 'fecha', label: 'fecha', required: true },
				{ key: 'duracion_minutos', label: 'duracion_minutos', required: true },
			]
		},
		pageTitle() {
			if (this.tab === 'modelos') {
				return t('empleados', 'Device models')
			}

			if (this.tab === 'soporte') {
				return t('empleados', 'Support history')
			}

			return t('empleados', 'IT Inventory')
		},

		pageSubtitle() {
			if (this.tab === 'modelos') {
				return t('empleados', 'Manage the model catalog used by inventory devices.')
			}

			if (this.tab === 'soporte') {
				return t('empleados', 'Review and register support records for selected devices.')
			}

			return t('empleados', '{total} devices · Computer equipment, assignments and device status.', {
				total: this.totalEquipos,
			})
		},
	},

	watch: {
		tab() {
			if (this.skipNextTabReload) {
				this.skipNextTabReload = false
				return
			}
			this.reload()
		},
		'$route.query.deviceId'(value) {
			if (value === undefined || value === null || value === '') {
				this.lastProcessedDeviceParam = null
				return
			}
			this.handleRouteDevice(value)
		},
	},

	async mounted() {
		this.restoreFilters()
		await this.reload()
		await this.handleRouteDevice(this.$route.query.deviceId)
	},

	beforeDestroy() {
		if (this.focusDeviceTimer) clearTimeout(this.focusDeviceTimer)
		if (this.searchTimer) clearTimeout(this.searchTimer)
	},

	methods: {
		t,

		async handleRouteDevice(value) {
			if (value === undefined || value === null || value === '') return
			const parameterKey = Array.isArray(value) ? JSON.stringify(value) : String(value)
			if (parameterKey === this.lastProcessedDeviceParam) return
			this.lastProcessedDeviceParam = parameterKey

			const deviceId = parseInventoryDeviceId(value)
			if (!deviceId) {
				showWarning(t('empleados', 'The requested inventory device ID is invalid.'))
				return
			}

			try {
				this.loading = true
				const response = await inventarioService.getEquipo(deviceId)
				const requestedDevice = response?.data ?? response
				if (!requestedDevice || Number(requestedDevice.id_equipo) !== deviceId) {
					showWarning(t('empleados', 'The requested inventory device was not found.'))
					return
				}

				const needsReload = this.tab !== 'equipos'
					|| this.hasActiveFilters
					|| !this.equipos.some(equipo => Number(equipo.id_equipo) === deviceId)
				if (this.tab !== 'equipos') this.skipNextTabReload = true
				this.tab = 'equipos'
				this.search = ''
				this.filters = { estado: '', asignacion: '', idEmpleado: '', idModelo: '' }
				this.pageOffset = 0
				this.persistFilters()
				if (needsReload) await this.reload()
				if (!this.equipos.some(equipo => Number(equipo.id_equipo) === deviceId)) {
					this.equipos = [requestedDevice, ...this.equipos]
				}
				this.highlightDevice(deviceId)
				await this.openViewEquipo(requestedDevice)
			} catch (error) {
				showWarning(t('empleados', 'The requested inventory device could not be opened.'))
			} finally {
				this.loading = false
			}
		},

		highlightDevice(deviceId) {
			if (this.focusDeviceTimer) clearTimeout(this.focusDeviceTimer)
			this.focusedDeviceId = deviceId
			this.$nextTick(() => {
				const row = this.$el.querySelector(`[data-device-id="${deviceId}"]`)
				if (row) {
					row.focus({ preventScroll: true })
					row.scrollIntoView({ behavior: 'smooth', block: 'center' })
				}
			})
			this.focusDeviceTimer = setTimeout(() => {
				this.focusedDeviceId = null
				this.focusDeviceTimer = null
			}, 4000)
		},

		setTab(tab) {
			this.tab = tab
		},

		scheduleSearch() {
			if (this.searchTimer) clearTimeout(this.searchTimer)
			this.searchTimer = setTimeout(() => this.applyFilters(), 450)
		},

		applyFilters() {
			if (this.searchTimer) {
				clearTimeout(this.searchTimer)
				this.searchTimer = null
			}
			this.pageOffset = 0
			this.persistFilters()
			return this.reload()
		},

		clearFilters() {
			this.search = ''
			this.filters = { estado: '', asignacion: '', idEmpleado: '', idModelo: '' }
			return this.applyFilters()
		},

		nextPage() {
			if (this.pageOffset + this.pageLimit >= this.totalEquipos) return
			this.pageOffset += this.pageLimit
			this.persistFilters()
			this.reload()
		},

		previousPage() {
			this.pageOffset = Math.max(0, this.pageOffset - this.pageLimit)
			this.persistFilters()
			this.reload()
		},

		persistFilters() {
			try {
				sessionStorage.setItem('empleados.inventory.filters', JSON.stringify({
					search: this.search,
					filters: this.filters,
					offset: this.pageOffset,
				}))
			} catch (error) {
				// El almacenamiento de sesión puede estar bloqueado; los filtros siguen funcionando en memoria.
			}
		},

		restoreFilters() {
			try {
				const stored = JSON.parse(sessionStorage.getItem('empleados.inventory.filters') || 'null')
				if (!stored || typeof stored !== 'object') return
				this.search = typeof stored.search === 'string' ? stored.search : ''
				this.filters = {
					estado: String(stored.filters?.estado || ''),
					asignacion: String(stored.filters?.asignacion || ''),
					idEmpleado: String(stored.filters?.idEmpleado || ''),
					idModelo: String(stored.filters?.idModelo || ''),
				}
				this.pageOffset = Number.isSafeInteger(stored.offset) && stored.offset >= 0 ? stored.offset : 0
			} catch (error) {
				this.pageOffset = 0
			}
		},

		async reload() {
			try {
				this.loading = true

				if (this.tab === 'modelos') {
					const res = await inventarioService.getModelos({ search: this.search })
					this.modelos = this.normalizeCollection(res)
				}

				if (this.tab === 'equipos') {
					const [equiposRes] = await Promise.all([
						inventarioService.getEquipos({
							search: this.search.trim() || undefined,
							estado: this.filters.estado || undefined,
							asignacion: this.filters.asignacion || undefined,
							id_empleado: this.filters.idEmpleado ? Number(this.filters.idEmpleado) : undefined,
							id_modelo: this.filters.idModelo ? Number(this.filters.idModelo) : undefined,
							limit: this.pageLimit,
							offset: this.pageOffset,
						}),
						this.loadModelosCatalogo(),
					])

					this.equipos = this.normalizeCollection(equiposRes)
					this.totalEquipos = Number(equiposRes?.total ?? this.equipos.length)
					this.empleadosFiltro = Array.isArray(equiposRes?.filter_options?.empleados)
						? equiposRes.filter_options.empleados
						: this.empleadosFiltro
					if (this.pageOffset >= this.totalEquipos && this.pageOffset > 0) {
						this.pageOffset = Math.max(0, Math.floor(Math.max(0, this.totalEquipos - 1) / this.pageLimit) * this.pageLimit)
						return this.reload()
					}
				}

				if (this.tab === 'soporte' && this.selectedEquipo) {
					const res = await inventarioService.getSoporteEquipo(this.selectedEquipo.id_equipo)
					this.soporte = this.normalizeCollection(res)
				}
			} catch (error) {
				showError(t('empleados', 'Error loading inventory: {error}', {
					error: String(error),
				}))
			} finally {
				this.loading = false
			}
		},

		normalizeCollection(response) {
			if (Array.isArray(response)) {
				return response
			}

			if (Array.isArray(response?.data)) {
				return response.data
			}

			return []
		},

		displayValue(value) {
			return value === null || value === undefined || value === '' ? '-' : value
		},

		isTruthy(value) {
			return value === true
				|| value === 'true'
				|| value === 1
				|| value === '1'
		},

		modeloName(equipo) {
			const model = [equipo.marca, equipo.modelo].filter(Boolean).join(' ')

			return model || this.displayValue(equipo.id_modelo)
		},

		statusClass(status) {
			const normalized = String(status || '').toLowerCase()

			return {
				'status-pill--success': ['activo', 'active', 'asignado'].includes(normalized),
				'status-pill--warning': ['mantenimiento', 'support', 'soporte'].includes(normalized),
				'status-pill--muted': !normalized || ['inactivo', 'inactive', 'baja'].includes(normalized),
			}
		},

		async openCreateModal() {
			if (this.tab === 'soporte' && !this.selectedEquipo) {
				showError(t('empleados', 'Select a device first.'))
				return
			}

			this.editMode = false
			this.modalMode = 'create'
			this.editingItem = null

			if (this.tab === 'modelos') {
				this.resetModelo()
			}

			if (this.tab === 'equipos') {
				this.resetEquipo()
				await this.loadModelosCatalogo()

				if (this.modelosCatalogo.length === 0) {
					showError(t('empleados', 'Create a model before registering a device.'))
					return
				}
			}

			if (this.tab === 'soporte') {
				this.resetSoporte()
			}

			this.showModal = true
		},
		closeModal() {
			this.showModal = false
			this.editMode = false
			this.modalMode = 'create'
			this.editingItem = null
		},

		async saveModal() {
			if (this.isReadonly || this.loading) return
			this.loading = true
			try {
				if (this.tab === 'modelos') {
					if (this.editMode && this.editingItem?.id_modelo) {
						await this.updateModelo(this.editingItem.id_modelo, this.formModelo)
						showSuccess(t('empleados', 'Model updated successfully.'))
					} else {
						await inventarioService.crearModelo(this.formModelo)
						showSuccess(t('empleados', 'Model created successfully.'))
					}

					this.resetModelo()
				}

				if (this.tab === 'equipos') {
					const payload = {
						...this.formEquipo,
						id_modelo: this.formEquipo.id_modelo ? Number(this.formEquipo.id_modelo) : null,
					}

					if (this.editMode && this.editingItem?.id_equipo) {
						await this.updateEquipo(this.editingItem.id_equipo, payload)
						showSuccess(t('empleados', 'Device updated successfully.'))
					} else {
						await inventarioService.crearEquipo(payload)
						showSuccess(t('empleados', 'Device created successfully.'))
					}

					this.resetEquipo()
				}

				if (this.tab === 'soporte') {
					this.resetSoporte(false)
					if (this.editMode && this.editingItem?.id_soporte) {
						await inventarioService.actualizarSoporte({
							...this.formSoporte,
							id_soporte: this.editingItem.id_soporte,
						})
						showSuccess(t('empleados', 'Support record updated successfully.'))
					} else {
						await inventarioService.crearSoporte({
							...this.formSoporte,
							id_equipo: this.selectedEquipo.id_equipo,
						})
						showSuccess(t('empleados', 'Support record created successfully.'))
					}
					this.resetSoporte()
				}

				this.closeModal()
				await this.reload()
			} catch (error) {
				showError(t('empleados', 'Error saving inventory data: {error}', {
					error: String(error),
				}))
			} finally {
				this.loading = false
			}
		},
		async selectEquipoSoporte(equipo) {
			this.selectedEquipo = equipo
			this.tab = 'soporte'
			await this.reload()
		},

		openEditModelo(modelo) {
			this.editMode = true
			this.modalMode = 'edit'
			this.editingItem = modelo

			this.formModelo = {
				marca: modelo.marca || '',
				modelo: modelo.modelo || '',
				procesador: modelo.procesador || '',
				ram: modelo.ram || '',
				disco_duro: modelo.disco_duro || '',
				tipo: modelo.tipo || '',
				touch: this.isTruthy(modelo.touch),
			}

			this.showModal = true
		},

		async openEditEquipo(equipo) {
			this.editMode = true
			this.modalMode = 'edit'
			this.editingItem = equipo

			await this.loadModelosCatalogo()
			this.populateEquipoForm(equipo)
			this.showModal = true
		},

		async openViewEquipo(equipo) {
			this.editMode = false
			this.modalMode = 'readonly'
			this.editingItem = equipo
			await this.loadModelosCatalogo()
			this.populateEquipoForm(equipo)
			this.showModal = true
		},

		populateEquipoForm(equipo) {
			this.formEquipo = {
				id_modelo: equipo.id_modelo ? String(equipo.id_modelo) : '',
				nombre_dispositivo: equipo.nombre_dispositivo || '',
				nombre_sistema: equipo.nombre_sistema || '',
				numero_serie: equipo.numero_serie || '',
				estado: equipo.estado || 'activo',
				info: equipo.info || '',
			}
		},

		async openHistory(equipo) {
			this.historyDevice = equipo
			this.historyEntries = []
			this.historyTotal = 0
			this.historyError = ''
			this.showHistoryModal = true
			await this.loadHistory(true)
		},

		closeHistory() {
			this.showHistoryModal = false
			this.historyDevice = null
			this.historyEntries = []
			this.historyTotal = 0
			this.historyError = ''
		},

		async loadHistory(reset = false) {
			if (!this.historyDevice || this.historyLoading) return
			if (reset) {
				this.historyEntries = []
				this.historyTotal = 0
			}
			this.historyLoading = true
			this.historyError = ''
			try {
				const response = await inventarioService.getHistorialEquipo(this.historyDevice.id_equipo, {
					limit: this.historyLimit,
					offset: this.historyEntries.length,
				})
				const entries = this.normalizeCollection(response)
				this.historyEntries = reset ? entries : [...this.historyEntries, ...entries]
				this.historyTotal = Number(response?.total ?? this.historyEntries.length)
			} catch (error) {
				this.historyError = t('empleados', 'The device history could not be loaded. You can try again.')
			} finally {
				this.historyLoading = false
			}
		},

		movementTypeLabel(type) {
			const labels = {
				alta: t('empleados', 'Registered'),
				asignacion: t('empleados', 'Assignment'),
				reasignacion: t('empleados', 'Reassignment'),
				desasignacion: t('empleados', 'Unassignment'),
				cambio_estado: t('empleados', 'Status change'),
				actualizacion: t('empleados', 'Update'),
				mantenimiento: t('empleados', 'Maintenance'),
				reparacion: t('empleados', 'Repair'),
				baja: t('empleados', 'Retirement'),
				nota: t('empleados', 'Note'),
			}
			return labels[type] || type || t('empleados', 'Movement')
		},

		formatDateTime(value) {
			if (!value) return ''
			const date = new Date(String(value).replace(' ', 'T'))
			return Number.isNaN(date.getTime())
				? String(value)
				: new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date)
		},

		historyChanges(entry) {
			if (!entry?.cambios || typeof entry.cambios !== 'object' || Array.isArray(entry.cambios)) return []
			return Object.entries(entry.cambios).map(([field, values]) => ({
				field,
				anterior: values?.anterior,
				nuevo: values?.nuevo,
			}))
		},

		fieldLabel(field) {
			const labels = {
				id_modelo: t('empleados', 'Model'),
				nombre_dispositivo: t('empleados', 'Device name'),
				nombre_sistema: t('empleados', 'System name'),
				numero_serie: t('empleados', 'Serial number'),
				info: t('empleados', 'Information'),
				categoria_soporte: t('empleados', 'Support category'),
				prioridad_soporte: t('empleados', 'Support priority'),
			}
			return labels[field] || field
		},

		resetModelo() {
			this.formModelo = {
				marca: '',
				modelo: '',
				procesador: '',
				ram: '',
				disco_duro: '',
				tipo: '',
				touch: false,
			}
		},

		resetEquipo() {
			this.formEquipo = {
				id_modelo: '',
				nombre_dispositivo: '',
				nombre_sistema: '',
				numero_serie: '',
				estado: 'activo',
				info: '',
			}
		},

		resetSoporte(resetFields = true) {
			this.formSoporte = {
				categoria: resetFields ? 'mantenimiento' : this.formSoporte.categoria,
				prioridad: resetFields ? 'media' : this.formSoporte.prioridad,
				detalles: resetFields ? '' : this.formSoporte.detalles,
				fecha: resetFields ? localDateTimeValue() : this.formSoporte.fecha,
				duracion_minutos: resetFields ? null : this.formSoporte.duracion_minutos,
				usuario_actual: this.getSelectedEquipoUser(),
				usuario_soporte: this.getCurrentSupportUser(),
			}
		},
		openEditSoporte(item) {
			const actionParts = String(item.accion || '').split('·').map(value => value.trim())
			this.editMode = true
			this.modalMode = 'edit'
			this.editingItem = item
			this.formSoporte = {
				categoria: actionParts[0] || 'otro',
				prioridad: actionParts[1] || 'media',
				detalles: item.detalles || '',
				fecha: String(item.fecha || '').replace(' ', 'T').slice(0, 16),
				duracion_minutos: item.duracion_minutos == null ? null : Number(item.duracion_minutos),
				usuario_actual: item.usuario_actual || '',
				usuario_soporte: item.usuario_soporte || '',
			}
			this.showModal = true
		},
		supportDurationLabel(value) {
			return value == null ? t('empleados', 'Duration pending') : formatSupportDuration(Number(value))
		},
		async loadModelosCatalogo(force = false) {
			if (!force && this.modelosCatalogo.length > 0) {
				return
			}

			const res = await inventarioService.getModelos({})
			this.modelosCatalogo = this.normalizeCollection(res)
		},

		modeloLabel(modelo) {
			return [
				modelo.marca,
				modelo.modelo,
				modelo.procesador,
				modelo.ram,
				modelo.disco_duro,
			]
				.filter(Boolean)
				.join(' - ')
		},

		async openImportModal() {
			this.importRows = []
			this.importErrors = []

			if (this.tab === 'equipos') {
				await this.loadModelosCatalogo()
			}

			if (this.tab === 'soporte' && !this.selectedEquipo) {
				showError(t('empleados', 'Select a device first.'))
				return
			}

			this.showImportModal = true
		},

		closeImportModal() {
			this.showImportModal = false
			this.importRows = []
			this.importErrors = []
		},

		handleImportFile(event) {
			const file = event.target.files?.[0]

			if (!file) {
				return
			}

			const reader = new FileReader()

			reader.onload = () => {
				try {
					const rows = this.parseCsv(String(reader.result || ''))
					this.importRows = this.prepareImportRows(rows)
					this.importErrors = this.validateImportRows(this.importRows)
				} catch (error) {
					this.importRows = []
					this.importErrors = [String(error)]
				}
			}

			reader.readAsText(file, 'UTF-8')
		},

		parseCsv(text) {
			const delimiter = this.detectCsvDelimiter(text)
			const rows = []
			let row = []
			let value = ''
			let inQuotes = false

			for (let i = 0; i < text.length; i += 1) {
				const char = text[i]
				const nextChar = text[i + 1]

				if (char === '"' && inQuotes && nextChar === '"') {
					value += '"'
					i += 1
					continue
				}

				if (char === '"') {
					inQuotes = !inQuotes
					continue
				}

				if (char === delimiter && !inQuotes) {
					row.push(value.trim())
					value = ''
					continue
				}

				if ((char === '\n' || char === '\r') && !inQuotes) {
					if (char === '\r' && nextChar === '\n') {
						i += 1
					}

					row.push(value.trim())

					if (row.some(cell => cell !== '')) {
						rows.push(row)
					}

					row = []
					value = ''
					continue
				}

				value += char
			}

			row.push(value.trim())

			if (row.some(cell => cell !== '')) {
				rows.push(row)
			}

			if (rows.length < 2) {
				throw new Error(t('empleados', 'The CSV file does not contain records.'))
			}

			const headers = rows.shift().map(header => this.normalizeCsvKey(header))

			return rows.map(item => {
				const record = {}

				headers.forEach((header, index) => {
					record[header] = item[index] ?? ''
				})

				return record
			})
		},

		detectCsvDelimiter(text) {
			const firstLine = String(text || '').split(/\r?\n/).find(line => line.trim() !== '') || ''
			const commas = (firstLine.match(/,/g) || []).length
			const semicolons = (firstLine.match(/;/g) || []).length

			return semicolons > commas ? ';' : ','
		},

		normalizeCsvKey(value) {
			return String(value || '')
				.trim()
				.toLowerCase()
				.normalize('NFD')
				.replace(/[\u0300-\u036f]/g, '')
				.replace(/\s+/g, '_')
				.replace(/[^a-z0-9_]/g, '')
		},

		prepareImportRows(rows) {
			return rows.map(row => {
				if (this.tab === 'modelos') {
					return {
						marca: row.marca || '',
						modelo: row.modelo || '',
						procesador: row.procesador || row.cpu || '',
						ram: row.ram || '',
						disco_duro: row.disco_duro || row.almacenamiento || row.storage || '',
						tipo: row.tipo || '',
						touch: this.isTruthy(row.touch) ? '1' : '0',
					}
				}

				if (this.tab === 'equipos') {
					return {
						id_modelo: row.id_modelo || this.findModeloId(row),
						marca: row.marca || '',
						modelo: row.modelo || '',
						nombre_dispositivo: row.nombre_dispositivo || row.dispositivo || row.device_name || '',
						nombre_sistema: row.nombre_sistema || row.hostname || row.system_name || '',
						numero_serie: row.numero_serie || row.serial || row.service_tag || '',
						estado: row.estado || 'activo',
						info: row.info || row.informacion || '',
					}
				}

				return {
					accion: row.accion || row.action || '',
					usuario_actual: row.usuario_actual || '',
					usuario_soporte: row.usuario_soporte || '',
					detalles: row.detalles || row.details || '',
				}
			})
		},

		validateImportRows(rows) {
			const errors = []

			rows.forEach((row, index) => {
				const line = index + 2

				this.importColumns
					.filter(column => column.required)
					.forEach(column => {
						if (!row[column.key]) {
							errors.push(t('empleados', 'Line {line}: missing required field {field}', {
								line,
								field: column.key,
							}))
						}
					})

				if (this.tab === 'equipos' && !row.id_modelo) {
					errors.push(t('empleados', 'Line {line}: model not found. Use id_modelo or valid marca + modelo.', {
						line,
					}))
				}
			})

			return errors
		},

		findModeloId(row) {
			const marca = String(row.marca || '').trim().toLowerCase()
			const modelo = String(row.modelo || '').trim().toLowerCase()

			if (!marca || !modelo) {
				return ''
			}

			const found = this.modelosCatalogo.find(item => {
				return String(item.marca || '').trim().toLowerCase() === marca
			&& String(item.modelo || '').trim().toLowerCase() === modelo
			})

			return found?.id_modelo || ''
		},

		async saveImport() {
			try {
				this.importing = true

				if (this.tab === 'modelos') {
					for (const row of this.importRows) {
						await inventarioService.crearModelo({
							marca: row.marca,
							modelo: row.modelo,
							procesador: row.procesador,
							ram: row.ram,
							disco_duro: row.disco_duro,
							tipo: row.tipo,
							touch: this.isTruthy(row.touch),
						})
					}
				}

				if (this.tab === 'equipos') {
					for (const row of this.importRows) {
						await inventarioService.crearEquipo({
							id_modelo: row.id_modelo ? Number(row.id_modelo) : null,
							nombre_dispositivo: row.nombre_dispositivo,
							nombre_sistema: row.nombre_sistema,
							numero_serie: row.numero_serie,
							estado: row.estado || 'activo',
							info: row.info,
						})
					}
				}

				if (this.tab === 'soporte') {
					for (const row of this.importRows) {
						await inventarioService.crearSoporte({
							id_equipo: this.selectedEquipo.id_equipo,
							categoria: row.categoria,
							prioridad: row.prioridad,
							detalles: row.detalles,
							fecha: row.fecha,
							duracion_minutos: Number(row.duracion_minutos),
						})
					}
				}

				showSuccess(t('empleados', 'Import completed successfully.'))

				this.closeImportModal()
				await this.reload()
			} catch (error) {
				showError(t('empleados', 'Error importing inventory data: {error}', {
					error: String(error),
				}))
			} finally {
				this.importing = false
			}
		},

		exportCurrentTab() {
			const columns = this.getExportColumns()
			const csv = this.toCsv(this.currentRows, columns)
			const date = new Date().toISOString().slice(0, 10)

			this.downloadTextFile(csv, `inventario_${this.tab}_${date}.csv`)
		},

		downloadImportTemplate() {
			const columns = this.importColumns.map(column => column.key)
			const example = this.getImportExampleRow()
			const csv = this.toCsv([example], columns)

			this.downloadTextFile(csv, `plantilla_${this.tab}.csv`)
		},

		getExportColumns() {
			if (this.tab === 'modelos') {
				return [
					'id_modelo',
					'marca',
					'modelo',
					'procesador',
					'ram',
					'disco_duro',
					'tipo',
					'touch',
				]
			}

			if (this.tab === 'equipos') {
				return [
					'id_equipo',
					'id_modelo',
					'marca',
					'modelo',
					'nombre_dispositivo',
					'nombre_sistema',
					'numero_serie',
					'estado',
					'empleado_uid',
					'empleado_id',
					'info',
				]
			}

			return [
				'id_soporte',
				'id_equipo',
				'fecha',
				'accion',
				'usuario_actual',
				'usuario_soporte',
				'detalles',
			]
		},

		getImportExampleRow() {
			if (this.tab === 'modelos') {
				return {
					marca: 'Dell',
					modelo: 'Latitude 5420',
					procesador: 'Intel Core i5',
					ram: '16 GB',
					disco_duro: '512 GB SSD',
					tipo: 'Laptop',
					touch: '0',
				}
			}

			if (this.tab === 'equipos') {
				return {
					id_modelo: '',
					marca: 'Dell',
					modelo: 'Latitude 5420',
					nombre_dispositivo: 'LAP-001',
					nombre_sistema: 'CROWE-LAP-001',
					numero_serie: 'ABC123456',
					estado: 'activo',
					info: 'Equipo disponible para asignación',
				}
			}

			return {
				categoria: 'mantenimiento',
				prioridad: 'media',
				usuario_actual: this.getSelectedEquipoUser(),
				usuario_soporte: this.getCurrentSupportUser(),
				detalles: 'Limpieza general y revisión de actualizaciones',
				fecha: localDateTimeValue(),
				duracion_minutos: 60,
			}
		},
		toCsv(rows, columns) {
			const header = columns.join(',')
			const body = rows.map(row => {
				return columns
					.map(column => this.escapeCsvValue(row[column]))
					.join(',')
			})

			return `\uFEFF${[header, ...body].join('\n')}`
		},

		escapeCsvValue(value) {
			const normalized = value === null || value === undefined ? '' : String(value)

			if (/[",\n\r]/.test(normalized)) {
				return `"${normalized.replace(/"/g, '""')}"`
			}

			return normalized
		},

		downloadTextFile(content, filename) {
			const blob = new Blob([content], {
				type: 'text/csv;charset=utf-8;',
			})

			const url = URL.createObjectURL(blob)
			const link = document.createElement('a')

			link.href = url
			link.download = filename
			document.body.appendChild(link)
			link.click()
			document.body.removeChild(link)

			URL.revokeObjectURL(url)
		},

		openModelos() {
			this.tab = 'modelos'
		},

		empleadoAsignadoName(equipo) {
			if (equipo.empleado_displayname) {
				return equipo.empleado_displayname
			}

			if (equipo.displayname) {
				return equipo.displayname
			}

			if (equipo.empleado_uid) {
				return equipo.empleado_uid
			}

			if (equipo.id_user) {
				return equipo.id_user
			}

			if (equipo.Id_user) {
				return equipo.Id_user
			}

			if (equipo.empleado_id) {
				return `#${equipo.empleado_id}`
			}

			if (equipo.id_empleado) {
				return `#${equipo.id_empleado}`
			}

			return t('empleados', 'Unassigned')
		},

		getSelectedEquipoUser() {
			if (!this.selectedEquipo) {
				return ''
			}

			const value = this.empleadoAsignadoName(this.selectedEquipo)

			return value === t('empleados', 'Unassigned') ? '' : value
		},

		getCurrentSupportUser() {
			const currentUser = window?.OC?.getCurrentUser?.()

			return currentUser?.displayName
				|| currentUser?.uid
				|| currentUser?.id
				|| window?.OC?.currentUser
				|| ''
		},

		async updateModelo(idModelo, data) {
			if (typeof inventarioService.actualizarModelo === 'function') {
				return inventarioService.actualizarModelo({ id_modelo: idModelo, ...data })
			}

			if (typeof inventarioService.updateModelo === 'function') {
				return inventarioService.updateModelo(idModelo, data)
			}

			return this.updateByHttp(`/apps/empleados/ActualizarInventarioModelo/${idModelo}`, data)
		},

		async updateEquipo(idEquipo, data) {
			if (typeof inventarioService.actualizarEquipo === 'function') {
				return inventarioService.actualizarEquipo({ id_equipo: idEquipo, ...data })
			}

			if (typeof inventarioService.updateEquipo === 'function') {
				return inventarioService.updateEquipo(idEquipo, data)
			}

			return this.updateByHttp(`/apps/empleados/ActualizarInventarioEquipo/${idEquipo}`, data)
		},

		async updateByHttp(url, data) {
			try {
				const response = await axios.put(generateUrl(url), data)
				return response.data
			} catch (error) {
				if ([404, 405].includes(error?.response?.status)) {
					const response = await axios.post(generateUrl(url), data)
					return response.data
				}

				throw error
			}
		},
	},
}
</script>

<style scoped>
.inventario-page {
	width: 100%;
	overflow: auto;
}

.inventario-header {
	display: flex;
	gap: 16px;
	justify-content: space-between;
	align-items: flex-start;
	padding: 24px 24px 12px;
}

.inventario-heading {
	min-width: 0;
}

.inventario-heading h2 {
	margin: 0 0 4px;
	font-size: 24px;
	font-weight: 700;
	line-height: 1.25;
}

.inventario-heading p {
	margin: 0;
	color: var(--color-text-maxcontrast);
	line-height: 1.4;
}

.inventario-summary {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 8px;
	padding: 0 24px 16px;
}

.summary-item {
	display: grid;
	grid-template-columns: 24px minmax(0, 1fr) auto;
	gap: 8px;
	align-items: center;
	min-height: 44px;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-main-background);
	color: var(--color-text-maxcontrast);
}

.summary-item strong {
	color: var(--color-main-text);
	font-size: 18px;
}

.inventario-tabs {
	display: flex;
	gap: 4px;
	padding: 0 24px;
	border-bottom: 1px solid var(--color-border);
}

.inventario-tabs button {
	display: inline-flex;
	gap: 8px;
	align-items: center;
	min-height: 44px;
	margin: 0;
	border: none;
	border-bottom: 2px solid transparent;
	background: transparent;
	color: var(--color-main-text);
	cursor: pointer;
	font-weight: 600;
	padding: 0 14px;
}

.inventario-tabs button:hover,
.inventario-tabs button:focus-visible {
	background-color: var(--color-background-hover);
}

.inventario-tabs button.active {
	border-bottom-color: var(--color-primary-element);
	color: var(--color-primary-element);
}

.inventario-toolbar {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	align-items: flex-end;
	padding: 16px 24px;
}

.search-field {
	width: min(420px, 100%);
}

.compact-filter {
	display: grid;
	gap: 4px;
	min-width: 150px;
}

.compact-filter span {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 600;
}

.compact-filter select {
	min-height: 44px;
	max-width: 230px;
	padding: 6px 30px 6px 10px;
	border: 1px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}

.inventario-card {
	margin: 0 24px 24px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	overflow: hidden;
}

.table-wrap {
	overflow-x: auto;
}

.loading-state {
	display: grid;
	place-items: center;
	min-height: 240px;
}

.inventario-table {
	width: 100%;
	border-collapse: collapse;
	table-layout: fixed;
}

.inventario-table--devices {
	min-width: 1080px;
}

.inventario-table th,
.inventario-table td {
	padding: 12px;
	border-bottom: 1px solid var(--color-border);
	text-align: left;
	vertical-align: top;
	overflow-wrap: anywhere;
}

.inventario-table th {
	font-weight: 700;
	color: var(--color-text-maxcontrast);
	background-color: var(--color-background-hover);
}

.inventario-table tbody tr:hover {
	background-color: var(--color-background-hover);
}

.inventario-table tbody tr.inventory-device-highlight {
	background-color: var(--color-primary-element-light);
	box-shadow: inset 4px 0 0 var(--color-primary-element);
	transition: background-color 180ms ease, box-shadow 180ms ease;
}

.inventario-table tbody tr:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.inventory-focus-announcement {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	white-space: nowrap;
	border: 0;
}

.inventario-table tr:last-child td {
	border-bottom: none;
}

.strong-cell {
	font-weight: 700;
}

.actions-cell {
	width: 1%;
	white-space: nowrap;
}

.employee-cell {
	display: flex;
	gap: 10px;
	align-items: center;
	min-width: 180px;
}

.employee-cell > div {
	display: grid;
	min-width: 0;
}

.employee-cell strong,
.employee-cell small {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.employee-cell small {
	color: var(--color-text-maxcontrast);
}

.neutral-avatar {
	display: grid;
	place-items: center;
	width: 36px;
	height: 36px;
	border-radius: 50%;
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}

.inventory-pagination {
	display: flex;
	gap: 12px;
	align-items: center;
	justify-content: space-between;
	padding: 12px 16px;
	border-top: 1px solid var(--color-border);
}

.inventory-pagination > div {
	display: flex;
	gap: 8px;
}

.status-pill,
.boolean-pill {
	display: inline-flex;
	gap: 4px;
	align-items: center;
	min-height: 24px;
	padding: 2px 8px;
	border-radius: 999px;
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.status-pill--success,
.boolean-pill.active {
	background-color: var(--color-success);
	color: var(--color-primary-element-text);
}

.status-pill--warning {
	background-color: var(--color-warning);
	color: var(--color-main-text);
}

.status-pill--muted {
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}

.selected-equipo {
	display: flex;
	gap: 12px;
	justify-content: space-between;
	align-items: center;
	padding: 12px 16px;
	border-bottom: 1px solid var(--color-border);
	background-color: var(--color-background-hover);
}

.selected-equipo div {
	display: grid;
	gap: 2px;
}

.selected-equipo span {
	color: var(--color-text-maxcontrast);
}

.inventario-modal {
	box-sizing: border-box;
	width: min(960px, calc(100vw - 64px));
	max-height: calc(100vh - 120px);
	padding: 28px;
	display: flex;
	flex-direction: column;
	gap: 18px;
	overflow-x: hidden;
	overflow-y: auto;
}

.form-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(260px, 1fr));
	gap: 16px;
	align-items: start;
}

.form-grid--single {
	grid-template-columns: 1fr;
}

.modal-context {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.inventario-modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 20px;
}

.history-device-heading {
	display: flex;
	gap: 12px;
	align-items: center;
	padding-bottom: 14px;
	border-bottom: 1px solid var(--color-border);
}

.history-device-heading div {
	display: grid;
}

.history-device-heading span,
.history-entry time {
	color: var(--color-text-maxcontrast);
}

.history-error {
	display: flex;
	gap: 12px;
	align-items: center;
	justify-content: space-between;
	padding: 14px;
	border: 1px solid var(--color-error);
	border-radius: var(--border-radius-large, 8px);
}

.history-error p {
	margin: 0;
}

.history-list {
	display: grid;
	gap: 12px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.history-entry {
	display: grid;
	gap: 10px;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
}

.history-entry__header {
	display: flex;
	gap: 12px;
	align-items: center;
	justify-content: space-between;
}

.history-type {
	display: inline-flex;
	width: fit-content;
	padding: 3px 9px;
	border-radius: 999px;
	background-color: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	font-weight: 700;
}

.history-details {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 8px 16px;
	margin: 0;
}

.history-details div {
	display: grid;
	gap: 2px;
}

.history-details dt {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.history-details dd,
.history-description {
	margin: 0;
}

.history-changes {
	display: grid;
	gap: 5px;
	margin: 0;
	padding: 10px 10px 10px 28px;
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-background-hover);
}

.history-changes li span {
	margin-left: 6px;
}

.history-load-more {
	display: flex;
	justify-content: center;
}

@media (max-width: 900px) {
	.inventario-summary {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 700px) {
	.inventario-header {
		align-items: stretch;
		flex-direction: column;
	}

	.inventario-actions {
		align-self: flex-start;
	}

	.inventario-toolbar,
	.selected-equipo {
		align-items: stretch;
		flex-direction: column;
	}

	.compact-filter,
	.compact-filter select {
		width: 100%;
		max-width: none;
	}

	.inventory-pagination,
	.history-entry__header {
		align-items: stretch;
		flex-direction: column;
	}

	.history-details {
		grid-template-columns: 1fr;
	}

	.inventario-tabs {
		overflow-x: auto;
	}
}

.select-field {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.select-field label {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.select-field select {
	width: 100%;
	min-height: 44px;
	padding: 8px 12px;
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}

.select-field select:focus {
	border-color: var(--color-primary-element);
	outline: none;
}

/* stylelint-disable no-descending-specificity */
.native-field {
	display: grid;
	gap: 4px;
}

.native-field span {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 600;
}

.native-field select,
.native-field input {
	width: 100%;
	min-height: 44px;
	padding: 8px 12px;
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}
/* stylelint-enable no-descending-specificity */
.inventario-nc-modal :deep(.modal-container) {
	width: min(980px, calc(100vw - 48px)) !important;
	max-width: min(980px, calc(100vw - 48px)) !important;
}

.inventario-nc-modal :deep(.modal-container__content) {
	width: 100%;
	max-width: none;
	overflow: visible;
}
.form-grid :deep(.input-field),
.form-grid :deep(.textarea) {
	min-width: 0;
}

.form-grid :deep(textarea) {
	min-height: 120px;
	resize: vertical;
}
.form-field--full {
	grid-column: 1 / -1;
}
.import-box {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.import-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	align-items: center;
}

.file-input-button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-height: 44px;
	padding: 0 16px;
	border-radius: var(--border-radius-pill, 999px);
	background-color: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-weight: 700;
	cursor: pointer;
}

.file-input-button input {
	display: none;
}

.import-errors {
	padding: 12px;
	border: 1px solid var(--color-error);
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-error-hover);
	color: var(--color-main-text);
}

.import-errors ul {
	margin: 8px 0 0;
	padding-left: 20px;
}

.import-preview {
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.inventario-actions {
	flex-shrink: 0;
}

.inventario-header :deep(.button-vue) {
	white-space: nowrap;
}

.row-actions {
	display: inline-flex;
	gap: 8px;
	align-items: center;
	white-space: nowrap;
}

.readonly-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
	grid-column: 1 / -1;
}

.readonly-field {
	display: grid;
	gap: 4px;
	min-height: 44px;
	padding: 10px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-background-hover);
}

.readonly-field.readonly-field span {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 600;
}

.readonly-field strong {
	color: var(--color-main-text);
	font-size: 14px;
	font-weight: 700;
}

@media (max-width: 700px) {
	.readonly-grid {
		grid-template-columns: 1fr;
	}
}
</style>
