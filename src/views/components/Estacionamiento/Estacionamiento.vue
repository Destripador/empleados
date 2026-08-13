<template>
	<NcAppContent name="Ejemplo – Dashboard">
		<div class="wrap">
			<div class="main-content">
				<div class="toolbar toolbar-mobile">
					<button type="button" class="zoom-btn" @click="zoomOut">
						-
					</button>
					<span class="zoom-label">{{ Math.round(effectiveScale * 100) }}%</span>
					<button type="button" class="zoom-btn" @click="zoomIn">
						+
					</button>
					<button type="button" class="zoom-btn" @click="resetView">
						Reset
					</button>
				</div>

				<div class="legend-container">
					<h3 class="legend-title">
						{{ t('empleados', 'Estado de Espacios') }}
					</h3>
					<div class="legend-item">
						<span class="legend-color color-occupied" />
						<span>{{ t('empleados', 'Asignado') }}</span>
					</div>
					<div class="legend-item">
						<span class="legend-color color-liberated" />
						<span>{{ t('empleados', 'Liberado (Hoy)') }}</span>
					</div>
					<div class="legend-item">
						<span class="legend-color color-empty" />
						<span>{{ t('empleados', 'Vacío / Disponible') }}</span>
					</div>
				</div>

				<div ref="viewport" class="parking-viewport">
					<div
						ref="parkingContainer"
						class="parking-stage"
						:class="{ dragging: isDragging }">
						<div v-if="!loading && spaces.length > 0"
							ref="parking"
							class="parking"
							:style="parkingStyle">
							<div class="side">
								<div class="slot medium empty top space"
									:class="{
										'is-occupied': spaces[0].nombre,
										'not-available': spaces[0].estado === 'No Disponible',
										'empty': !spaces[0].nombre && spaces[0].estado === 'vacio',
										'is-temporary': spaces[0].esTemporal
									}"
									@click.stop="handleSlotClick(1)">
									{{ spaces[0].nombre || spaces[0].estado }}
								</div>
								<div class="slot small"
									:class="{
										'is-occupied': spaces[1].nombre,
										'not-available': spaces[1].estado === 'No Disponible',
										'empty': !spaces[1].nombre && spaces[1].estado === 'vacio',
										'is-temporary': spaces[1].esTemporal
									}"
									@click.stop="handleSlotClick(2)">
									{{ spaces[1].nombre || spaces[1].estado }}
								</div>
								<div class="slot small space"
									:class="{
										'is-occupied': spaces[2].nombre,
										'not-available': spaces[2].estado === 'No Disponible',
										'empty': !spaces[2].nombre && spaces[2].estado === 'vacio',
										'is-temporary': spaces[2].esTemporal
									}"
									@click.stop="handleSlotClick(3)">
									{{ spaces[2].nombre || spaces[2].estado }}
								</div>
								<div class="slot small"
									:class="{
										'is-occupied': spaces[3].nombre,
										'not-available': spaces[3].estado === 'No Disponible',
										'empty': !spaces[3].nombre && spaces[3].estado === 'vacio',
										'is-temporary': spaces[3].esTemporal
									}"
									@click.stop="handleSlotClick(4)">
									{{ spaces[3].nombre || spaces[3].estado }}
								</div>
								<div class="slot small space"
									:class="{
										'is-occupied': spaces[4].nombre,
										'not-available': spaces[4].estado === 'No Disponible',
										'empty': !spaces[4].nombre && spaces[4].estado === 'vacio',
										'is-temporary': spaces[4].esTemporal
									}"
									@click.stop="handleSlotClick(5)">
									{{ spaces[4].nombre || spaces[4].estado }}
								</div>
								<div class="slot small"
									:class="{
										'is-occupied': spaces[5].nombre,
										'not-available': spaces[5].estado === 'No Disponible',
										'empty': !spaces[5].nombre && spaces[5].estado === 'vacio',
										'is-temporary': spaces[5].esTemporal
									}"
									@click.stop="handleSlotClick(6)">
									{{ spaces[5].nombre || spaces[5].estado }}
								</div>
								<div class="slot small"
									:class="{
										'is-occupied': spaces[6].nombre,
										'not-available': spaces[6].estado === 'No Disponible',
										'empty': !spaces[6].nombre && spaces[6].estado === 'vacio',
										'is-temporary': spaces[6].esTemporal
									}"
									@click.stop="handleSlotClick(7)">
									{{ spaces[6].nombre || spaces[6].estado }}
								</div>
								<div class="slot big space-big"
									:class="{
										'is-occupied': spaces[7].nombre,
										'not-available': spaces[7].estado === 'No Disponible',
										'empty': !spaces[7].nombre && spaces[7].estado === 'vacio',
										'is-temporary': spaces[7].esTemporal
									}"
									@click.stop="handleSlotClick(8)">
									{{ spaces[7].nombre || spaces[7].estado }}
								</div>
								<div class="slot small"
									:class="{
										'is-occupied': spaces[8].nombre,
										'not-available': spaces[8].estado === 'No Disponible',
										'empty': !spaces[8].nombre && spaces[8].estado === 'vacio',
										'is-temporary': spaces[8].esTemporal
									}"
									@click.stop="handleSlotClick(9)">
									{{ spaces[8].nombre || spaces[8].estado }}
								</div>
								<div class="slot small space"
									:class="{
										'is-occupied': spaces[9].nombre,
										'not-available': spaces[9].estado === 'No Disponible',
										'empty': !spaces[9].nombre && spaces[9].estado === 'vacio',
										'is-temporary': spaces[9].esTemporal
									}"
									@click.stop="handleSlotClick(10)">
									{{ spaces[9].nombre || spaces[9].estado }}
								</div>
								<div class="slot small"
									:class="{
										'is-occupied': spaces[10].nombre,
										'not-available': spaces[10].estado === 'No Disponible',
										'empty': !spaces[10].nombre && spaces[10].estado === 'vacio',
										'is-temporary': spaces[10].esTemporal
									}"
									@click.stop="handleSlotClick(11)">
									{{ spaces[10].nombre || spaces[10].estado }}
								</div>
								<div class="slot small"
									:class="{
										'is-occupied': spaces[11].nombre,
										'not-available': spaces[11].estado === 'No Disponible',
										'empty': !spaces[11].nombre && spaces[11].estado === 'vacio',
										'is-temporary': spaces[11].esTemporal
									}"
									@click.stop="handleSlotClick(12)">
									{{ spaces[11].nombre || spaces[11].estado }}
								</div>
							</div>

							<div class="center">
								<div class="top-grid">
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[12].nombre,
											'not-available': spaces[12].estado === 'No Disponible',
											'empty': !spaces[12].nombre && spaces[12].estado === 'vacio',
											'is-temporary': spaces[12].esTemporal
										}"
										@click.stop="handleSlotClick(13)">
										{{ spaces[12].nombre || spaces[12].estado }}
									</div>
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[19].nombre,
											'not-available': spaces[19].estado === 'No Disponible',
											'empty': !spaces[19].nombre && spaces[19].estado === 'vacio',
											'is-temporary': spaces[19].esTemporal
										}"
										@click.stop="handleSlotClick(20)">
										{{ spaces[19].nombre || spaces[19].estado }}
									</div>
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[22].nombre,
											'not-available': spaces[22].estado === 'No Disponible',
											'empty': !spaces[22].nombre && spaces[22].estado === 'vacio',
											'is-temporary': spaces[22].esTemporal
										}"
										@click.stop="handleSlotClick(23)">
										{{ spaces[22].nombre || spaces[22].estado }}
									</div>
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[13].nombre,
											'not-available': spaces[13].estado === 'No Disponible',
											'empty': !spaces[13].nombre && spaces[13].estado === 'vacio',
											'is-temporary': spaces[13].esTemporal
										}"
										@click.stop="handleSlotClick(14)">
										{{ spaces[13].nombre || spaces[13].estado }}
									</div>
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[20].nombre,
											'not-available': spaces[20].estado === 'No Disponible',
											'empty': !spaces[20].nombre && spaces[20].estado === 'vacio',
											'is-temporary': spaces[20].esTemporal
										}"
										@click.stop="handleSlotClick(21)">
										{{ spaces[20].nombre || spaces[20].estado }}
									</div>
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[23].nombre,
											'not-available': spaces[23].estado === 'No Disponible',
											'empty': !spaces[23].nombre && spaces[23].estado === 'vacio',
											'is-temporary': spaces[23].esTemporal
										}"
										@click.stop="handleSlotClick(24)">
										{{ spaces[23].nombre || spaces[23].estado }}
									</div>
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[14].nombre,
											'not-available': spaces[14].estado === 'No Disponible',
											'empty': !spaces[14].nombre && spaces[14].estado === 'vacio',
											'is-temporary': spaces[14].esTemporal
										}"
										@click.stop="handleSlotClick(15)">
										{{ spaces[14].nombre || spaces[14].estado }}
									</div>
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[21].nombre,
											'not-available': spaces[21].estado === 'No Disponible',
											'empty': !spaces[21].nombre && spaces[21].estado === 'vacio',
											'is-temporary': spaces[21].esTemporal
										}"
										@click.stop="handleSlotClick(22)">
										{{ spaces[21].nombre || spaces[21].estado }}
									</div>
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[24].nombre,
											'not-available': spaces[24].estado === 'No Disponible',
											'empty': !spaces[24].nombre && spaces[24].estado === 'vacio',
											'is-temporary': spaces[24].esTemporal
										}"
										@click.stop="handleSlotClick(25)">
										{{ spaces[24].nombre || spaces[24].estado }}
									</div>
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[15].nombre,
											'not-available': spaces[15].estado === 'No Disponible',
											'empty': !spaces[15].nombre && spaces[15].estado === 'vacio',
											'is-temporary': spaces[15].esTemporal
										}"
										@click.stop="handleSlotClick(16)">
										{{ spaces[15].nombre || spaces[15].estado }}
									</div>
									<div class="slot empty hidden-spot">
						&nbsp;
									</div>
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[25].nombre,
											'not-available': spaces[25].estado === 'No Disponible',
											'empty': !spaces[25].nombre && spaces[25].estado === 'vacio',
											'is-temporary': spaces[25].esTemporal
										}"
										@click.stop="handleSlotClick(26)">
										{{ spaces[25].nombre || spaces[25].estado }}
									</div>
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[16].nombre,
											'not-available': spaces[16].estado === 'No Disponible',
											'empty': !spaces[16].nombre && spaces[16].estado === 'vacio',
											'is-temporary': spaces[16].esTemporal
										}"
										@click.stop="handleSlotClick(17)">
										{{ spaces[16].nombre || spaces[16].estado }}
									</div>
									<div class="slot empty hidden-spot" />
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[26].nombre,
											'not-available': spaces[26].estado === 'No Disponible',
											'empty': !spaces[26].nombre && spaces[26].estado === 'vacio',
											'is-temporary': spaces[26].esTemporal
										}"
										@click.stop="handleSlotClick(27)">
										{{ spaces[26].nombre || spaces[26].estado }}
									</div>
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[17].nombre,
											'not-available': spaces[17].estado === 'No Disponible',
											'empty': !spaces[17].nombre && spaces[17].estado === 'vacio',
											'is-temporary': spaces[17].esTemporal
										}"
										@click.stop="handleSlotClick(18)">
										{{ spaces[17].nombre || spaces[17].estado }}
									</div>
									<div class="slot empty hidden-spot" />
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[27].nombre,
											'not-available': spaces[27].estado === 'No Disponible',
											'empty': !spaces[27].nombre && spaces[27].estado === 'vacio',
											'is-temporary': spaces[27].esTemporal
										}"
										@click.stop="handleSlotClick(28)">
										{{ spaces[27].nombre || spaces[27].estado }}
									</div>
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[18].nombre,
											'not-available': spaces[18].estado === 'No Disponible',
											'empty': !spaces[18].nombre && spaces[18].estado === 'vacio',
											'is-temporary': spaces[18].esTemporal
										}"
										@click.stop="handleSlotClick(19)">
										{{ spaces[18].nombre || spaces[18].estado }}
									</div>
									<div class="slot empty hidden-spot" />
									<div class="slot empty"
										:class="{
											'is-occupied': spaces[28].nombre,
											'not-available': spaces[28].estado === 'No Disponible',
											'empty': !spaces[28].nombre && spaces[28].estado === 'vacio',
											'is-temporary': spaces[28].esTemporal
										}"
										@click.stop="handleSlotClick(29)">
										{{ spaces[28].nombre || spaces[28].estado }}
									</div>
								</div>

								<div class="road">
									<div class="car">
										entrada
									</div>
								</div>
							</div>

							<div class="side">
								<div class="slot medium empty top space"
									:class="{
										'is-occupied': spaces[29].nombre,
										'not-available': spaces[29].estado === 'No Disponible',
										'empty': !spaces[29].nombre && spaces[29].estado === 'vacio',
										'is-temporary': spaces[29].esTemporal
									}"
									@click.stop="handleSlotClick(30)">
									{{ spaces[29].nombre || spaces[29].estado }}
								</div>
								<div class="slot small empty"
									:class="{
										'is-occupied': spaces[30].nombre,
										'not-available': spaces[30].estado === 'No Disponible',
										'empty': !spaces[30].nombre && spaces[30].estado === 'vacio',
										'is-temporary': spaces[30].esTemporal
									}"
									@click.stop="handleSlotClick(31)">
									{{ spaces[30].nombre || spaces[30].estado }}
								</div>
								<div class="slot small space"
									:class="{
										'is-occupied': spaces[31].nombre,
										'not-available': spaces[31].estado === 'No Disponible',
										'empty': !spaces[31].nombre && spaces[31].estado === 'vacio',
										'is-temporary': spaces[31].esTemporal
									}"
									@click.stop="handleSlotClick(32)">
									{{ spaces[31].nombre || spaces[31].estado }}
								</div>
								<div class="slot small"
									:class="{
										'is-occupied': spaces[32].nombre,
										'not-available': spaces[32].estado === 'No Disponible',
										'empty': !spaces[32].nombre && spaces[32].estado === 'vacio',
										'is-temporary': spaces[32].esTemporal
									}"
									@click.stop="handleSlotClick(33)">
									{{ spaces[32].nombre || spaces[32].estado }}
								</div>
								<div class="slot small space"
									:class="{
										'is-occupied': spaces[33].nombre,
										'not-available': spaces[33].estado === 'No Disponible',
										'empty': !spaces[33].nombre && spaces[33].estado === 'vacio',
										'is-temporary': spaces[33].esTemporal
									}"
									@click.stop="handleSlotClick(34)">
									{{ spaces[33].nombre || spaces[33].estado }}
								</div>
								<div class="slot small"
									:class="{
										'is-occupied': spaces[34].nombre,
										'not-available': spaces[34].estado === 'No Disponible',
										'empty': !spaces[34].nombre && spaces[34].estado === 'vacio',
										'is-temporary': spaces[34].esTemporal
									}"
									@click.stop="handleSlotClick(35)">
									{{ spaces[34].nombre || spaces[34].estado }}
								</div>
								<div class="slot small space"
									:class="{
										'is-occupied': spaces[35].nombre,
										'not-available': spaces[35].estado === 'No Disponible',
										'empty': !spaces[35].nombre && spaces[35].estado === 'vacio',
										'is-temporary': spaces[35].esTemporal
									}"
									@click.stop="handleSlotClick(36)">
									{{ spaces[35].nombre || spaces[35].estado }}
								</div>
								<div class="slot small"
									:class="{
										'is-occupied': spaces[36].nombre,
										'not-available': spaces[36].estado === 'No Disponible',
										'empty': !spaces[36].nombre && spaces[36].estado === 'vacio',
										'is-temporary': spaces[36].esTemporal
									}"
									@click.stop="handleSlotClick(37)">
									{{ spaces[36].nombre || spaces[36].estado }}
								</div>
								<div class="slot small space"
									:class="{
										'is-occupied': spaces[37].nombre,
										'not-available': spaces[37].estado === 'No Disponible',
										'empty': !spaces[37].nombre && spaces[37].estado === 'vacio',
										'is-temporary': spaces[37].esTemporal
									}"
									@click.stop="handleSlotClick(38)">
									{{ spaces[37].nombre || spaces[37].estado }}
								</div>
								<div class="slot small"
									:class="{
										'is-occupied': spaces[38].nombre,
										'not-available': spaces[38].estado === 'No Disponible',
										'empty': !spaces[38].nombre && spaces[38].estado === 'vacio',
										'is-temporary': spaces[38].esTemporal
									}"
									@click.stop="handleSlotClick(39)">
									{{ spaces[38].nombre || spaces[38].estado }}
								</div>
								<div class="slot small space"
									:class="{
										'is-occupied': spaces[39].nombre,
										'not-available': spaces[39].estado === 'No Disponible',
										'empty': !spaces[39].nombre && spaces[39].estado === 'vacio',
										'is-temporary': spaces[39].esTemporal
									}"
									@click.stop="handleSlotClick(40)">
									{{ spaces[39].nombre || spaces[39].estado }}
								</div>
								<div class="slot small"
									:class="{
										'is-occupied': spaces[40].nombre,
										'not-available': spaces[40].estado === 'No Disponible',
										'empty': !spaces[40].nombre && spaces[40].estado === 'vacio',
										'is-temporary': spaces[40].esTemporal
									}"
									@click.stop="handleSlotClick(41)">
									{{ spaces[40].nombre || spaces[40].estado }}
								</div>
								<div class="slot small"
									:class="{
										'is-occupied': spaces[41].nombre,
										'not-available': spaces[41].estado === 'No Disponible',
										'empty': !spaces[41].nombre && spaces[41].estado === 'vacio',
										'is-temporary': spaces[41].esTemporal
									}"
									@click.stop="handleSlotClick(42)">
									{{ spaces[41].nombre || spaces[41].estado }}
								</div>
							</div>
						</div>
						<NcLoadingIcon v-else name="Cargando mapa..." />
					</div>
				</div>
			</div>

			<div class="side-navigator">
				<div class="toolbar">
					<button type="button" class="zoom-btn" @click="zoomOut">
						-
					</button>
					<span class="zoom-label">{{ Math.round(effectiveScale * 100) }}%</span>
					<button type="button" class="zoom-btn" @click="zoomIn">
						+
					</button>
					<button type="button" class="zoom-btn" @click="resetView">
						Reset
					</button>
				</div>
				<p class="helper-text">
					Mapa fijo del estacionamiento. En móvil se escala completo sin alterar proporciones.
				</p>
			</div>
		</div>
		<NcModal v-if="showModal"
			:name="t('empleados', 'Gestionar disponibilidad de mi espacio')"
			size="large"
			@close="showModal = false">
			<div class="availability-container">
				<form class="availability-modal" @submit.prevent="saveAvailability">
					<h3>{{ t('empleados', 'Liberar nuevas fechas') }}</h3>
					<p class="availability-modal__description">
						{{ t('empleados', 'Indica cuándo estará disponible tu lugar.') }}
					</p>
					<div class="availability-modal__section">
						<div class="availability-modal__grid">
							<NcTextField
								:value.sync="form.fechaInicio"
								:label="t('empleados', 'Fecha inicial')"
								type="date"
								required />
							<NcTextField
								:value.sync="form.fechaFin"
								:label="t('empleados', 'Fecha final')"
								type="date"
								required />
						</div>
					</div>

					<div class="availability-modal__section availability-modal__switch">
						<NcCheckboxRadioSwitch :checked.sync="form.todoDia">
							{{ t('empleados', 'Todo el día') }}
						</NcCheckboxRadioSwitch>
					</div>

					<div v-if="!form.todoDia" class="availability-modal__section">
						<div class="availability-modal__grid availability-modal__time-grid">
							<NcTextField
								:value.sync="form.horaInicio"
								:label="t('empleados', 'Hora inicial')"
								type="time"
								required />
							<NcTextField
								:value.sync="form.horaFin"
								:label="t('empleados', 'Hora final')"
								type="time"
								required />
						</div>
					</div>

					<div class="availability-modal__actions">
						<NcButton @click="showModal = false">
							{{ t('empleados', 'Cancelar') }}
						</NcButton>
						<NcButton
							type="primary"
							native-type="submit"
							:disabled="!isAvailabilityFormValid || loadingAction">
							{{ t('empleados', 'Confirmar disponibilidad') }}
						</NcButton>
					</div>
				</form>

				<div class="availability-history">
					<h3>{{ t('empleados', 'Mis días liberados') }}</h3>
					<div v-if="history.length === 0" class="empty-history">
						<p>{{ t('empleados', 'No tienes registros previos.') }}</p>
					</div>
					<ul v-else class="history-list">
						<li v-for="item in history" :key="item.id_emp_esp_disp">
							<div class="history-item-info">
								<strong>{{ formatDateDisplay(item.fecha) }}</strong>
								<span v-if="item.todo_dia">{{ t('empleados', 'Todo el día') }}</span>
								<span v-else>{{ item.hora_inicial }} - {{ item.hora_final }}</span>
							</div>
							<NcButton
								type="error"
								:aria-label="t('empleados', 'Eliminar')"
								@click="deleteAvailability(item.id_emp_esp_disp)">
								<template #icon>
									<span class="icon-delete" />
								</template>
							</NcButton>
						</li>
					</ul>
				</div>
			</div>
		</NcModal>
		<NcModal v-if="showPublicModal"
			:name="t('empleados', 'Disponibilidad de Espacio')"
			size="normal"
			@close="showPublicModal = false">
			<div class="public-availability">
				<div class="public-availability__header">
					<div class="space-badge">
						{{ selectedSpaceNumber }}
					</div>
					<div>
						<h3 class="owner-name">
							{{ selectedSpaceOwner }}
						</h3>
						<p class="subtitle">
							{{ t('empleados', 'Próximos días liberados') }}
						</p>
					</div>
				</div>

				<div class="public-availability__content">
					<div v-if="publicHistory.length === 0" class="empty-state">
						<p>{{ t('empleados', 'No hay más fechas programadas próximamente.') }}</p>
					</div>
					<table v-else class="availability-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Fecha') }}</th>
								<th>{{ t('empleados', 'Horario') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="day in publicHistory" :key="day.id_emp_esp_disp">
								<td class="date-cell">
									{{ formatDateDisplay(day.fecha) }}
								</td>
								<td class="time-cell">
									<span v-if="day.todo_dia" class="badge-all-day">{{ t('empleados', 'Todo el día') }}</span>
									<span v-else>{{ day.hora_inicial }} - {{ day.hora_final }}</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
			<template #actions>
				<NcButton type="primary" @click="showPublicModal = false">
					{{ t('empleados', 'Entendido') }}
				</NcButton>
			</template>
		</NcModal>
	</NcAppContent>
</template>

<script>
import { NcAppContent, NcLoadingIcon, NcModal, NcButton, NcCheckboxRadioSwitch, NcTextField } from '@nextcloud/vue'
import { t } from '@nextcloud/l10n'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

const MAP_WIDTH = 760
const MAP_HEIGHT = 1120

export default {
	name: 'Estacionamiento',
	components: {
		NcAppContent,
		NcLoadingIcon,
		NcModal,
		NcButton,
		NcCheckboxRadioSwitch,
		NcTextField,
	},
	data() {
		return {
			loading: true,
			zoomLevel: 1,
			maxZoom: 3,
			minZoom: 0.6,
			baseScale: 1,
			translateX: 0,
			translateY: 0,
			isDragging: false,
			startX: 0,
			startY: 0,
			dragStartX: 0,
			dragStartY: 0,
			activePointerId: null,
			resizeObserver: null,
			spaces: [],
			currentUser: null,
			myAsignacionId: null, // Guardará el id_espacio_empleado del usuario
			showModal: false,
			// Datos para el formulario
			form: {
				fechaInicio: '',
				fechaFin: '',
				todoDia: true,
				horaInicio: '09:00',
				horaFin: '19:00',
			},
			history: [],
			loadingAction: false,
			showPublicModal: false,
			publicHistory: [],
			selectedSpaceNumber: null,
			selectedSpaceOwner: '',
		}
	},
	computed: {
		effectiveScale() {
			return this.baseScale * this.zoomLevel
		},
		parkingStyle() {
			return {
				width: `${MAP_WIDTH}px`,
				height: `${MAP_HEIGHT}px`,
				transform: `translate(${this.translateX}px, ${this.translateY}px) scale(${this.effectiveScale})`,
				transformOrigin: 'center center',
			}
		},
		isAvailabilityFormValid() {
			if (!this.form.fechaInicio || !this.form.fechaFin) {
				return false
			}

			if (!this.form.todoDia && (!this.form.horaInicio || !this.form.horaFin)) {
				return false
			}

			return true
		},
	},
	mounted() {
		this.bindDragEvents()
		this.setupResizeObserver()
		this.updateBaseScale()
		this.fetchEstacionamientoDatos()
	},
	beforeUnmount() {
		this.unbindDragEvents()
		if (this.resizeObserver) {
			this.resizeObserver.disconnect()
		}
	},
	methods: {
		async fetchEstacionamientoDatos() {
			this.loading = true
			try {
				// 1. Obtenemos los espacios base y los empleados asignados en paralelo
				const [respEspacios, respAsignaciones, respUser, respLiberados] = await Promise.all([
					axios.get(generateUrl('/apps/empleados/GetEspacios')),
					axios.get(generateUrl('/apps/empleados/espacios/GetEmpleadosConEspacio')),
					axios.get(generateUrl('/apps/empleados/espacios/GetUser')),
					axios.get(generateUrl('/apps/empleados/espacios/liberados-hoy')),
				])

				const baseEspacios = respEspacios.data?.ocs?.data.Espacio || []
				const asignaciones = respAsignaciones.data?.ocs?.data.empleados || []
				const liberadosHoy = respLiberados.data?.ocs?.data.espacios || []
				this.currentUser = respUser.data?.ocs?.data.uid

				// 2. Mapeamos los 42 espacios
				// Asumimos que baseEspacios trae los 42 registros de la tabla 'espacio'
				this.spaces = baseEspacios.map(esp => {
					const misAsignaciones = asignaciones.filter(a => parseInt(a.id_espacio) === parseInt(esp.id_espacio))
					// Si el usuario actual está en esta lista, guardamos su ID de asignación
					const miRegistro = misAsignaciones.find(a => a.uid === this.currentUser)
					if (miRegistro) {
						this.myAsignacionId = miRegistro.id_espacio_empleado
					}
					// Buscamos si este espacio tiene empleados en la lista de asignaciones
					const empleadosEnEsteEspacio = misAsignaciones.map(a => a.uid)
					const estaLiberado = liberadosHoy.includes(esp.id_espacio)

					let nombreDisplay = ''
					let estadoDisplay = ''

					if (esp.disponible === 0) {
						// Caso: No Disponible
						estadoDisplay = 'No Disponible'
					} else {
						// Mantenemos el nombre de los dueños siempre
						if (empleadosEnEsteEspacio.length > 0) {
							nombreDisplay = empleadosEnEsteEspacio.join(' | ')
						} else {
							estadoDisplay = 'vacio'
						}
					}

					return {
						id: esp.id_espacio,
						numero: esp.numero,
						nombre: nombreDisplay,
						estado: estadoDisplay,
						disponible: esp.disponible,
						esTemporal: estaLiberado,
					}
				})

			} catch (error) {
				console.error('Error cargando datos del estacionamiento', error)
				showError('Error cargando datos del estacionamiento')
			} finally {
				this.loading = false
			}
		},
		t,
		handleSlotClick(index) {
			const space = this.spaces[index - 1]
			const asignaciones = space.nombre.split(' | ')

			if (asignaciones.includes(this.currentUser)) {
				// Es mi espacio: abrir modal de gestión
				this.openAvailabilityModal()
			} else if (space.esTemporal) {
				// Es espacio ajeno pero está liberado: abrir modal de información
				this.openPublicAvailabilityModal(space)
			} else {
				showSuccess(`Espacio ${space.numero}: ${space.nombre || space.estado}`)
			}
		},
		openAvailabilityModal() {
			this.showModal = true
			this.fetchUserHistory()
		},
		async fetchUserHistory() {
			try {
				const resp = await axios.get(generateUrl(`/apps/empleados/espacios/historial/${this.myAsignacionId}`))
				this.history = resp.data.ocs.data.historial
			} catch (e) {
				console.error('Error ', e)
				showError(t('empleados', 'Error al obtener historial'))
			}
		},
		async deleteAvailability(id) {
			if (!confirm(t('empleados', '¿Estás seguro de eliminar esta disponibilidad?'))) return
			try {
				console.error('Id: ', id)
				const respuesta = await axios.delete(generateUrl(`/apps/empleados/espacios/liberar/${id}`))
				if (respuesta.data?.ocs?.data?.status !== 'success') {
					throw new Error(respuesta.data?.ocs?.data?.message || 'Desconocido')
				}
				showSuccess(t('empleados', 'Eliminado correctamente'))
				this.fetchUserHistory() // Refrescar lista
				this.fetchEstacionamientoDatos() // Refrescar mapa
			} catch (e) {
				showError(t('empleados', e.message))
			}
		},
		async saveAvailability() {
			if (!this.isAvailabilityFormValid) {
				showError('Completa las fechas antes de confirmar')
				return
			}

			try {
				const payload = {
					id_espacio_empleado: this.myAsignacionId,
					fecha_inicio: this.form.fechaInicio,
					fecha_fin: this.form.fechaFin,
					todo_dia: this.form.todoDia,
					hora_inicial: this.form.todoDia ? null : this.form.horaInicio,
					hora_final: this.form.todoDia ? null : this.form.horaFin,
				}

				const respuesta = await axios.post(generateUrl('/apps/empleados/espacios/liberar'), payload)
				if (respuesta.data?.ocs?.data?.status !== 'success') {
					if (respuesta.data?.ocs?.data?.message) {
						showError(respuesta.data?.ocs?.data?.message || 'Error al guardar disponibilidad')
					} else {
						throw new Error('Error desconocido al guardar disponibilidad')
					}
				} else {
					showSuccess('Tu espacio ha sido marcado como disponible')
					this.showModal = false
				}
			} catch (e) {
				showError('No se pudo guardar la configuración')
			}
			this.fetchUserHistory()
		},
		async openPublicAvailabilityModal(space) {
			this.selectedSpaceNumber = space.numero
			this.selectedSpaceOwner = space.nombre
			this.showPublicModal = true
			try {
				// Reutilizamos la lógica de buscar disponibilidad, pero necesitamos el id_espacio_empleado
				const resp = await axios.get(generateUrl(`/apps/empleados/espacios/historial-publico/${space.id}`))
				this.publicHistory = resp.data.ocs.data.historial
			} catch (e) {
				showError(t('empleados', 'No se pudo cargar la información de disponibilidad'))
			}
		},
		setupResizeObserver() {
			const viewport = this.$refs.viewport
			if (!viewport || typeof ResizeObserver === 'undefined') {
				window.addEventListener('resize', this.updateBaseScale)
				return
			}

			this.resizeObserver = new ResizeObserver(() => {
				this.updateBaseScale()
			})
			this.resizeObserver.observe(viewport)
		},
		updateBaseScale() {
			const viewport = this.$refs.viewport
			if (!viewport) {
				return
			}

			const padding = 24
			const availableWidth = Math.max(viewport.clientWidth - padding, 200)
			const availableHeight = Math.max(viewport.clientHeight - padding, 200)
			const widthScale = availableWidth / MAP_WIDTH
			const heightScale = availableHeight / MAP_HEIGHT
			this.baseScale = Math.min(widthScale, heightScale, 1)
		},
		bindDragEvents() {
			const parkingContainer = this.$refs.parkingContainer
			if (!parkingContainer) {
				return
			}

			parkingContainer.addEventListener('pointerdown', this.onPointerDown, { passive: false })
			document.addEventListener('pointermove', this.onPointerMove, { passive: false })
			document.addEventListener('pointerup', this.onPointerUp)
			document.addEventListener('pointercancel', this.onPointerUp)
		},
		unbindDragEvents() {
			const parkingContainer = this.$refs.parkingContainer
			if (parkingContainer) {
				parkingContainer.removeEventListener('pointerdown', this.onPointerDown)
			}

			document.removeEventListener('pointermove', this.onPointerMove)
			document.removeEventListener('pointerup', this.onPointerUp)
			document.removeEventListener('pointercancel', this.onPointerUp)
			window.removeEventListener('resize', this.updateBaseScale)
		},
		onPointerDown(e) {
			const parking = this.$refs.parking
			const parkingContainer = this.$refs.parkingContainer

			if (!parking || !parking.contains(e.target)) {
				return
			}

			// Si se hizo click en un slot, deja que el click funcione normal
			if (e.target.closest('.slot')) {
				return
			}

			if (e.pointerType === 'mouse' && e.button !== 0) {
				return
			}

			e.preventDefault()
			this.isDragging = true
			this.activePointerId = e.pointerId
			this.startX = e.clientX
			this.startY = e.clientY
			this.dragStartX = this.translateX
			this.dragStartY = this.translateY

			if (parkingContainer && parkingContainer.setPointerCapture) {
				parkingContainer.setPointerCapture(e.pointerId)
			}
		},
		onPointerMove(e) {
			if (!this.isDragging || this.activePointerId !== e.pointerId) {
				return
			}

			e.preventDefault()
			this.translateX = this.dragStartX + (e.clientX - this.startX)
			this.translateY = this.dragStartY + (e.clientY - this.startY)
		},
		onPointerUp(e) {
			if (this.activePointerId !== null && e.pointerId !== this.activePointerId) {
				return
			}

			this.isDragging = false
			this.activePointerId = null
		},
		zoomIn() {
			if (this.zoomLevel < this.maxZoom) {
				this.zoomLevel = Math.min(this.zoomLevel + 0.2, this.maxZoom)
			}
		},
		zoomOut() {
			if (this.zoomLevel > this.minZoom) {
				this.zoomLevel = Math.max(this.zoomLevel - 0.2, this.minZoom)
			}
		},
		resetView() {
			this.zoomLevel = 1
			this.translateX = 0
			this.translateY = 0
			this.updateBaseScale()
		},
		formatDateDisplay(dateString) {
			if (!dateString) return ''
			// Asumiendo que dateString viene como "YYYY-MM-DD"
			const [year, month, day] = dateString.split('-')
			return `${day}/${month}/${year}`
		},
	},
}
</script>

<style scoped lang="scss">
.wrap {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 280px;
	gap: 20px;
	width: 100%;
}

.main-content,
.side-navigator {
	background: var(--color-background-darker);
	border: 1px solid var(--color-border);
	border-radius: 12px;
}

.main-content {
	padding: 16px;
	min-height: calc(100vh - 140px);
	overflow: hidden;
}

.side-navigator {
	padding: 20px;
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.toolbar {
	display: flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
}

.toolbar-mobile {
	display: none;
	margin-bottom: 12px;
}

.zoom-btn {
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	background: var(--color-main-background);
	cursor: pointer;
	min-width: 44px;
}

.zoom-label {
	font-weight: 600;
	min-width: 56px;
	text-align: center;
}

.helper-text {
	margin: 0;
	font-size: 14px;
	line-height: 1.4;
	color: var(--color-text-maxcontrast);
}

.parking-viewport {
	position: relative;
	width: 100%;
	height: calc(100vh - 190px);
	min-height: 420px;
	overflow: hidden;
	border-radius: 12px;
	background: var(--color-main-background);
}

.parking-stage {
	position: absolute;
	inset: 0;
	display: flex;
	justify-content: center;
	align-items: center;
	user-select: none;
	touch-action: none;
	cursor: grab;
	overflow: hidden;
}

.parking-stage.dragging {
	cursor: grabbing;
}

.parking {
	background: #dcdcdc;
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 20px;
	padding: 10px;
	box-sizing: border-box;
	will-change: transform;
}

.side {
	width: 170px;
	display: flex;
	flex-direction: column;
	gap: 3.5px;
}

.slot {
	background: #efefef;
	border: 1px solid #222;
	box-sizing: border-box;
	display: flex;
	align-items: center;
	justify-content: center;
	text-align: center;
	color: #000;
	font-size: 18px;
	min-height: 78px;

	/* Nuevas propiedades para mejorar legibilidad */
    padding: 4px;
    word-break: break-all;
    line-height: 1.1;

    /* REGLA: Selectores más específicos van dentro o después */
    &.small {
        min-height: 70px;
    }
    &.medium {
        min-height: 90px;
    }
    &.big {
        min-height: 120px;
    }
    &.tall {
        min-height: 120px;
    }

    /* Estado: Vacío (el que ya tenías) */
    &.empty {
        color: #d10000;
        font-size: 22px;
        font-weight: 600;
    }

    /* Estado: Ocupado (Reemplaza a .slot:not(.empty)) */
    &.is-occupied {
        background: #c2e1ff; // Azul para ocupados
        font-size: 14px;
    }

    /* ESTADO LIBERADO: Mantiene el nombre pero cambia el color a verde */
    &.is-temporary {
        background: #ccffcd !important; // Verde claro
        border: 2px solid #2e7d32 !important;
        color: #1b5e20;
    }

    &.not-available {
        background: var(--color-text-maxcontrast) !important;
        color: var(--color-main-background) !important;
    }
}

/* Estilos para la leyenda */
.legend-container {
    padding: 15px;
    background: var(--color-main-background);
    border-radius: 8px;
    border: 1px solid var(--color-border);
    margin-bottom: 10px;
}

.legend-title {
    font-size: 14px;
    font-weight: bold;
    margin-bottom: 10px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
    font-size: 13px;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid #222;

    &.color-occupied { background: #c2e1ff; }
    &.color-liberated { background: #ccffcd; border-color: #2e7d32; }
    &.color-empty { background: #efefef; }
}

.space{
	margin-bottom: 5px;
}
.space-big{
	margin-bottom: 15px;
	margin-top: 15px;
}
.hidden-spot{
	visibility: hidden;
}
.center {
	flex: 1;
	min-height: 1100px;
	display: flex;
	flex-direction: column;
	align-items: center;
}

.top-grid {
	display: grid;
	grid-template-columns: repeat(3, 80px);
	grid-template-rows: repeat(3, 140px);
	gap: 4px;
	margin-top: 0;
}

.top-grid .slot {
	min-height: auto;
	height: 140px;
	width: 80px;
}

.road {
	flex: 1;
	width: 100%;
	position: relative;
}

.car {
	position: absolute;
	bottom: 40px;
	left: 50%;
	transform: translateX(-50%);
	font-size: 40px;
	line-height: 1;
}

@media (max-width: 1024px) {
	.wrap {
		grid-template-columns: 1fr;
	}

	.side-navigator {
		display: none;
	}

	.toolbar-mobile {
		display: flex;
	}

	.main-content {
		min-height: calc(100vh - 110px);
		padding: 12px;
	}

	.parking-viewport {
		height: calc(100vh - 180px);
		min-height: 360px;
	}
}

@media (max-width: 640px) {
	.main-content {
		padding: 10px;
	}

	.parking-viewport {
		min-height: 300px;
		height: calc(100vh - 170px);
	}

	.toolbar {
		gap: 8px;
	}

	.zoom-btn {
		padding: 10px 12px;
	}
}

.availability-modal {
	display: flex;
	flex-direction: column;
	gap: 18px;
	width: min(100vw - 32px, 560px);
	padding: 20px 24px 24px;
}

.availability-modal__description {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	line-height: 1.5;
}

.availability-modal__section {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.availability-modal__grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
	align-items: start;
}

.availability-modal__switch {
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.availability-modal__actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	padding-top: 4px;
}

@media (max-width: 640px) {
	.availability-modal {
		width: calc(100vw - 24px);
		padding: 16px;
	}

	.availability-modal__grid {
		grid-template-columns: 1fr;
	}

	.availability-modal__actions {
		flex-direction: column-reverse;
	}
}

.availability-container {
    display: grid;
    grid-template-columns: 1fr 1fr; // Dos columnas
    gap: 30px;
    padding: 20px;
    min-width: 700px;
}

.availability-history {
    border-left: 1px solid var(--color-border);
    padding-left: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.history-list {
    list-style: none;
    padding: 0;
    li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
        border-bottom: 1px solid var(--color-border);
        &:hover {
            background: var(--color-background-hover);
        }
    }
}

.history-item-info {
    display: flex;
    flex-direction: column;
    font-size: 13px;
}

@media (max-width: 768px) {
    .availability-container {
        grid-template-columns: 1fr; // Una columna en móvil
        min-width: auto;
    }
    .availability-history {
        border-left: none;
        border-top: 1px solid var(--color-border);
        padding-left: 0;
        padding-top: 20px;
    }
}

.public-availability {
    padding: 24px;
    &__header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 24px;
        border-bottom: 1px solid var(--color-border);
        padding-bottom: 16px;
    }

    .space-badge {
        background: #2e7d32;
        color: white;
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: bold;
    }

    .owner-name {
        margin: 0;
        font-size: 18px;
    }

    .subtitle {
        margin: 0;
        color: var(--color-text-maxcontrast);
        font-size: 14px;
    }
}

.availability-table {
    width: 100%;
    border-collapse: collapse;
    th {
        text-align: left;
        padding: 12px;
        color: var(--color-text-maxcontrast);
        font-size: 12px;
        text-transform: uppercase;
        border-bottom: 2px solid var(--color-border);
    }

    td {
        padding: 12px;
        border-bottom: 1px solid var(--color-border);
        font-size: 14px;
    }

    .date-cell {
        font-weight: 600;
    }

    .badge-all-day {
        background: var(--color-success-light);
        color: var(--color-success);
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: bold;
    }
}

</style>
