<template>
	<NcAppContent name="Loading">
		<div class="">
			<div class="text-center section">
				<div v-if="configuraciones.modulo_ausencias_readonly === 'true'">
					<br>
					<NcNoteCard type="error"
						:heading="t('empleados', 'Attention!!!')"
						:text="t('empleados', 'The module is in read-only mode')" />
					<br>
				</div>
				<section class="layout">
					<div class="grow2">
						<div class="text-center sectionPicker">
							<FullCalendar ref="fullCalendar" :options="calendarOptions" class="my-calendar" />
						</div>
					</div>
					<div class="grow1">
						<div class="cards">
							<div class="headers">
								<div class="btn-top-right">
									<NcActions>
										<NcActionButton @click="showAniversarioModal">
											<template #icon>
												<CalendarQuestionOutline :size="20" />
											</template>
											{{ t('empleados', 'My information') }}
										</NcActionButton>
									</NcActions>
								</div>

								<div class="header-content">
									<h2 class="h2-white">
										{{ t('empleados', 'Vacation') }}
									</h2>

									<div class="vacations">
										<div class="vacations-panel">
											<div
												v-if="Ausencias.dias_acumulados > 0 && Ausencias.fecha_expiracion_acumulados"
												class="panel-row panel-row--accum">
												<AlertOutline :size="16" class="row-icon" />
												<p class="row-text">
													{{ t('empleados', 'Accumulated from previous period:') }}
													<strong>{{ formatearDias(Ausencias.dias_acumulados) }}</strong>
													{{ t('empleados', '— use before {fecha} or they expire. ⚠️', {
														fecha: new Date(Ausencias.fecha_expiracion_acumulados).toLocaleDateString('es-MX')
													}) }}
												</p>
											</div>

											<div v-if="Ausencias.dias_acumulados > 0 && Ausencias.fecha_expiracion_acumulados" class="panel-divider" />

											<div class="panel-row1 panel-row--current">
												<span class="row-label">{{ t('empleados', 'Current period:') }}</span>
												<span v-if="Ausencias.dias_disponibles !== undefined && Ausencias.dias_disponibles !== null" class="row-value">
													{{ formatearDias(Ausencias.dias_disponibles) }}
												</span>
												<NcLoadingIcon v-else :size="20" />
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="infos">
								<!-- Notifications accordion -->
								<div v-if="notificaciones" class="acordeon-item">
									<button class="acordeon-notification" @click="toggle(0)">
										<div class="noti-wrapper">
											<BellOutline class="bell-icon" :class="{ 'bell-shake': isShaking }" />
											<NcCounterBubble :count="notifications_counter" class="noti-badge" />
										</div>
										<span class="noti-text">{{ t('empleados', 'Pending') }}</span>
										<span class="arrow">{{ accordeon[0].abierto ? '-' : '+' }}</span>
									</button>
									<div :class="['acordeon-contenido', { abierto: accordeon[0].abierto }]">
										<div>
											<div class="rst">
												<div style="max-height: 300px; overflow-y: auto;">
													<ul>
														<NcListItem v-for="(item) in notifications_result"
															:key="item.id_historial_ausencias"
															:name="item.displayname ? item.displayname : item.Id_user"
															@click.prevent="abrirDetalleDesdeNotificacion(item)">
															<template #icon>
																<NcAvatar disable-menu
																	:size="44"
																	:user="item.Id_user"
																	:display-name="item.Id_user" />
															</template>
														</NcListItem>
													</ul>
												</div>
											</div>
										</div>
									</div>
								</div>
								<NcButton
									v-if="isAdmin()"
									class="btn-top"
									variant="secondary"
									wide
									@click="mostrarReporte = true">
									{{ t('empleados', 'Show report') }}
								</NcButton>

								<!-- Show only my absences -->
								<NcButton class="btn-top"
									text="center (default)"
									variant="secondary"
									wide
									@click="typePetition = null; $refs.fullCalendar.getApi().refetchEvents()">
									{{ t('empleados', 'Show my absences') }}
								</NcButton>

								<!-- Team view accordion -->
								<div v-if="Object.keys(Equipo).length" class="acordeon-item btn-top">
									<button class="acordeon-titulo" @click="toggle(1)">
										{{ t('empleados', 'Filter by team') }}
										<span>{{ accordeon[1].abierto ? '-' : '+' }}</span>
									</button>
									<div :class="['acordeon-contenido', { abierto: accordeon[1].abierto }]">
										<div>
											<div class="rst-title">
												<div class="title_flex">
													<div class="subtitle_flex">
														<NcAvatar :user="Equipo.Id_jefe_equipo"
															:display-name="Equipo.Id_jefe_equipo"
															:size="20" />
													</div>
													<div class="btn-top-subtitle">
														{{ Equipo.Nombre }}
													</div>
													<div class="flex-to-right">
														<AccountGroup class="pointer"
															@click="typePetition = 'all'; $refs.fullCalendar.getApi().refetchEvents()" />
													</div>
												</div>
											</div>
											<div class="rst">
												<div style="max-height: 300px; overflow-y: auto;">
													<ul>
														<NcListItem v-for="(item) in peopleEquipo.equipo"
															:key="item.Id_empleados"
															:name="item.displayname ? item.displayname : item.Id_user"
															@click.prevent="employees = []; typePetition = 'employee'; selected_user = item; $refs.fullCalendar.getApi().refetchEvents()">
															<template #icon>
																<NcAvatar disable-menu
																	:size="44"
																	:user="item.Id_user"
																	:display-name="item.Id_user" />
															</template>
														</NcListItem>
													</ul>
												</div>
											</div>
										</div>
									</div>
								</div>

								<!-- Administrative view accordion -->
								<div v-if="isAdmin()" class="acordeon-item btn-top">
									<button class="acordeon-titulo" @click="toggle(2)">
										{{ t('empleados', 'Administrative') }}
										<span>{{ accordeon[2].abierto ? '-' : '+' }}</span>
									</button>
									<div :class="['acordeon-contenido', { abierto: accordeon[2].abierto }]">
										<div class="btn-top">
											<NcSelect v-bind="propsEmployees" v-model="employees" @update:model-value="onEmployeesChange" />
										</div>
									</div>
								</div>

								<!-- My subordinates -->
								<div v-if="subordinates.length > 0" class="acordeon-item">
									<button class="acordeon-titulo" @click="toggle(3)">
										{{ t('empleados', 'My subordinates') }} <span>{{ accordeon[3].abierto ? '-' :
											'+' }}</span>
									</button>
									<div :class="['acordeon-contenido', { abierto: accordeon[3].abierto }]">
										<div>
											<div class="rst-title">
												<div class="title_flex">
													<div class="subtitle_flex">
														{{ t('empleados', 'My subordinates') }}
													</div>
													<div class="flex-to-right">
														<AccountGroup class="pointer"
															@click="typePetition = 'all-employees'; $refs.fullCalendar.getApi().refetchEvents()" />
													</div>
												</div>
											</div>
											<div class="rst">
												<div style="max-height: 300px; overflow-y: auto;">
													<ul>
														<NcListItem v-for="(item) in subordinates"
															:key="item.Id_empleados"
															:name="item.displayname ? item.displayname : item.Id_user"
															@click.prevent="employees = []; typePetition = 'employee'; selected_user = item; $refs.fullCalendar.getApi().refetchEvents()">
															<template #icon>
																<NcAvatar disable-menu
																	:size="44"
																	:user="item.Id_user"
																	:display-name="item.Id_user" />
															</template>
														</NcListItem>
													</ul>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="footers">
								<p>
									🔎 {{ vista_actual }}
								</p>
							</div>
						</div>
					</div>
				</section>
			</div>
		</div>

		<!-- ABSENCE REPORT MODAL -->
		<NcModal
			v-if="mostrarReporte"
			size="full"
			:can-close="false"
			:name="t('empleados', 'Absence report')"
			@close="mostrarReporte = false">
			<ReporteAusencias @close="mostrarReporte = false" />
		</NcModal>
		<!-- END ABSENCE REPORT MODAL -->
		<!-- EVENT DETAILS MODAL -->
		<NcModal
			v-if="modalEvento"
			ref="modalEventoRef"
			size="normal"
			:name="t('empleados', 'Absence details')"
			@close="closeModalEvento">
			<DetalleAusencia
				v-if="selectedEventId"
				:id-historial="selectedEventId"
				:is-admin="isAdmin()"
				@cancelled="onAbsenceCancelled"
				@approved="onAbsenceCancelled"
				@rejected="onAbsenceCancelled"
				@edit="onAbsenceEdit" />
		</NcModal>
		<!-- END EVENT DETAILS MODAL -->

		<!-- ABSENCE REQUEST MODAL -->
		<NcModal v-if="modal"
			size="large"
			:name="t('empleados', 'Absence form')"
			@close="closeModal">
			<NuevaSolicitud v-if="modal"
				ref="modalRef"
				:date="date"
				:dias-solicitados="diasSolicitados"
				:dias-disponibles="Ausencias.dias_disponibles"
				:dias-acumulados="Ausencias.dias_acumulados"
				:fecha-expiracion-acumulados="Ausencias.fecha_expiracion_acumulados"
				:prima="Ausencias.prima_vacacional"
				:employees="propsEmployees.options"
				:admin="isAdmin()"
				@close="closeModal" />
		</NcModal>
		<!-- END ABSENCE REQUEST MODAL -->

		<!-- EDIT ABSENCE MODAL -->
		<NcModal
			v-if="modalEditar"
			size="large"
			:name="t('empleados', 'Edit absence')"
			@close="closeModalEditar">
			<EditarAusencia
				v-if="modalEditar && ausenciaEditar"
				:ausencia="ausenciaEditar"
				:dias-disponibles="Ausencias.dias_disponibles"
				:prima="Ausencias.prima_vacacional"
				:employees="propsEmployees.options"
				:admin="isAdmin()"
				@saved="onAbsenceEditSaved"
				@close="closeModalEditar" />
		</NcModal>
		<!-- END EDIT ABSENCE MODAL -->

		<!-- ANNIVERSARIES INFO MODAL -->
		<NcModal v-if="ModalAniversario"
			ref="modalRef"
			size="large"
			:name="t('empleados', 'Anniversary table')"
			@close="closeModalAniversario">
			<div class="table_component" role="region" tabindex="0">
				<div class="modal__content">
					<div class="layout">
						<div class="grow3">
							<TrofeosAniversarios :info="Ausencias" :acumular="configuraciones.acumular_vacaciones" />
							<br>
							<table>
								<caption>
									<span class="caption-title">{{ t('empleados', 'Anniversary table') }}</span>
								</caption>
								<thead>
									<tr>
										<th>{{ t('empleados', 'Anniversary(ies)') }}</th>
										<th>{{ t('empleados', 'Days off') }}</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="(grupo, index) in AniversariosAgrupados" :key="index">
										<td>
											<span v-if="grupo.desde === grupo.hasta">
												{{ grupo.desde }}
											</span>
											<span v-else>
												{{ t('empleados', '{from} to {to}', {
													from: grupo.desde, to: grupo.hasta
												}) }}
											</span>
										</td>
										<td>{{ grupo.dias }}</td>
									</tr>
								</tbody>
							</table>
						</div>
						<div class="grow4">
							<MensajeAniversarios :info="Ausencias" :acumular="configuraciones.acumular_vacaciones" />
						</div>
					</div>
				</div>
			</div>
		</NcModal>
		<!-- END ANNIVERSARIES INFO MODAL -->
	</NcAppContent>
</template>

<script>
import MensajeAniversarios from './MensajeAniversarios.vue'
import TrofeosAniversarios from './TrofeosAniversarios.vue'
import NuevaSolicitud from './Modal/NuevaSolicitud.vue'
import DetalleAusencia from './Modal/DetalleAusencia.vue'
import EditarAusencia from './Modal/EditarAusencia.vue'
import ReporteAusencias from './ReporteAusencias.vue'

import FullCalendar from '@fullcalendar/vue'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import multiMonthPlugin from '@fullcalendar/multimonth'

import { ref } from 'vue'

import usernameToColor from '@nextcloud/vue/functions/usernameToColor'
import { showError, showInfo } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import BellOutline from 'vue-material-design-icons/BellOutline.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import CalendarQuestionOutline from 'vue-material-design-icons/CalendarQuestionOutline.vue'

import {
	NcAppContent,
	NcModal,
	NcActions,
	NcActionButton,
	NcListItem,
	NcAvatar,
	NcButton,
	NcSelect,
	NcCounterBubble,
	NcLoadingIcon,
	NcNoteCard,
} from '@nextcloud/vue'

export default {
	name: 'TiempoLibre',

	components: {
		MensajeAniversarios,
		TrofeosAniversarios,
		NuevaSolicitud,
		DetalleAusencia,
		EditarAusencia,
		NcAppContent,
		NcModal,
		NcActions,
		NcActionButton,
		AccountGroup,
		CalendarQuestionOutline,
		FullCalendar,
		NcListItem,
		NcAvatar,
		NcButton,
		NcSelect,
		NcCounterBubble,
		BellOutline,
		NcLoadingIcon,
		NcNoteCard,
		ReporteAusencias,
	},

	inject: ['employee', 'configuraciones', 'groupuser', 'subordinates'],

	data() {
		return {
			modalRef: ref(null),
			attributes: [],
			FechaInitial: null,
			FechaMaxima: null,
			modal: false,
			modalEvento: false,
			modalEditar: false,
			ModalAniversario: false,
			Ausencias: [],
			Aniversarios: [],
			diasSolicitados: 0,
			date: ref({
				start: new Date(),
				end: null,
			}),
			range: null,
			calendarOptions: {
				headerToolbar: {
					left: 'prev,next today',
					center: 'title',
					right: 'dayGridMonth,multiMonthYear',
				},
				initialView: 'dayGridMonth',
				locale: 'en',
				plugins: [dayGridPlugin, interactionPlugin, multiMonthPlugin],
				events: this.fetchEvents,
				dateClick: this.onDateClick,
				eventClick: this.OnClickEvent,
				select: this.onDateRangeSelect,
				selectable: true,
				fixedWeekCount: false,
				height: 'auto',
				contentHeight: 'auto',
				expandRows: false,
				aspectRatio: 1.2,
				dayMaxEvents: true,
				dayMaxEventRows: 2,
				moreLinkClick: 'popover',
				eventContent(arg) {
					const nombreEmpleado = arg.event.extendedProps.nombre_empleado || 'Unknown employee'
					const imgUrl = `/avatar/${nombreEmpleado}/64`
					return {
						html: `
							<div style="display:flex;align-items:center;">
							<img src="${imgUrl}" style="width:16px;height:16px;border-radius:50%;margin-right:4px;">
							<span>${arg.event.title}</span>
							</div>
						`,
					}
				},
			},
			peopleEquipo: {},
			Equipo: {},
			typePetition: null,
			selected_user: null,
			propsEmployees: {
				inputLabel: t('empleados', 'All employees'),
				userSelect: true,
				multiple: true,
				closeOnSelect: false,
				options: [],
			},
			employees: [],
			infoSelected: null,
			vista_actual: t('empleados', 'My absences'),
			accordeon: [
				{ abierto: false },
				{ abierto: false },
				{ abierto: false },
				{ abierto: false },
			],
			notificaciones: false,
			notifications_counter: 0,
			loading: false,
			notifications_result: [],
			isShaking: false,
			selectedEventId: null,
			ausenciaEditar: null,
			mostrarReporte: false,
		}
	},

	computed: {
		AniversariosAgrupados() {
			const agrupados = []
			let inicio = null
			let fin = null
			let diasActual = null

			this.Aniversarios.forEach((item, index) => {
				const diasNumero = Number(item.dias)
				if (diasActual === null) {
					inicio = item.numero_aniversario
					fin = item.numero_aniversario
					diasActual = diasNumero
				} else if (diasNumero === diasActual) {
					fin = item.numero_aniversario
				} else {
					agrupados.push({ desde: inicio, hasta: fin, dias: diasActual })
					inicio = item.numero_aniversario
					fin = item.numero_aniversario
					diasActual = diasNumero
				}
				if (index === this.Aniversarios.length - 1) {
					agrupados.push({ desde: inicio, hasta: fin, dias: diasActual })
				}
			})

			return agrupados
		},
	},

	mounted() {
		this.$bus.on('close-solicitud', () => {
			this.GetAusencias()

			this.$nextTick(() => {
				this.$refs.fullCalendar?.getApi()?.refetchEvents()
			})

			this.closeModal()
		})
		this.GetAusencias()
		if (this.isAdmin()) {
			this.updateList()
		}
		this.getEquipos()
		this.GetAllEquipo()
		this.checkNotifications()
	},

	methods: {
		t,

		abrirDetalleDesdeNotificacion(item) {
			this.selectedEventId = item.id_historial_ausencias
			this.modalEvento = true
		},

		async checkNotifications() {
			if (this.subordinates.length === 0 && !this.isAdmin()) return
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetNotificationsSubordinates'))
				const data = response?.data?.ocs?.data ?? []
				if (data.length > 0) {
					this.notificaciones = true
					this.notifications_counter = data.length
					this.notifications_result = data
					this.startShaking()
				} else {
					this.notificaciones = false
				}
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [01] [{err}]', { err }))
			}
		},

		startShaking() {
			setInterval(() => {
				this.isShaking = true
				setTimeout(() => { this.isShaking = false }, 900)
			}, 2000)
		},

		toggle(index) {
			this.accordeon = this.accordeon.map((item, i) => ({
				...item,
				abierto: i === index ? !item.abierto : false,
			}))
		},

		showAniversarioModal() { this.getAniversarios() },
		closeModal() { this.modal = false },
		closeModalAniversario() { this.ModalAniversario = false },

		closeModalEvento() {
			this.modalEvento = false
			this.selectedEventId = null
		},

		closeModalEditar() {
			this.modalEditar = false
			this.ausenciaEditar = null
		},

		async GetAusencias() {
			try {
				const response = await axios.post(generateUrl('/apps/empleados/GetAusenciasByUser'), {
					id: this.employee[0].Id_empleados,
				})
				this.Ausencias = response?.data?.ocs?.data[0]
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [03] [{err}]', { err }))
			}
		},

		async getAniversarios() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/Getaniversarios'))
				this.Aniversarios = response?.data?.ocs?.data
				this.ModalAniversario = true
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [01] [{err}]', { err }))
			}
		},

		formatearDias(dias) {
			const entero = Math.floor(dias)
			const decimal = dias % 1
			if (decimal === 0) {
				return `${entero} ${entero === 1 ? t('empleados', 'day') : t('empleados', 'days')}`
			} else if (decimal === 0.5) {
				return `${entero} ${entero === 1 ? t('empleados', 'day') : t('empleados', 'days')} ${t('empleados', 'and a half')}`
			} else {
				return `${dias} ${t('empleados', 'days')}`
			}
		},

		onDatesSet() {
			this.$refs.fullCalendar.getApi().refetchEvents()
		},

		fetchEvents(fetchInfo, success, failure) {
			switch (this.typePetition) {
			case 'all':
				this.getAllAusencias(fetchInfo, success, failure)
				this.vista_actual = t('empleados', 'All my team')
				break
			case 'employee':
				this.getEmployeeAusencias(fetchInfo, success, failure)
				this.vista_actual = t('empleados', 'Selected employee')
				break
			case 'all-employees':
				this.GetAusenciasMyWorkers(fetchInfo, success, failure)
				this.vista_actual = t('empleados', 'All my subordinates')
				break
			default:
				this.accordeon = this.accordeon.map(item => ({ ...item, abierto: false }))
				this.employees = []
				this.getMyAusencias(fetchInfo, success, failure)
				this.vista_actual = t('empleados', 'My absences')
			}
		},

		eventColor(item, fallbackUsername) {
			return this.estiloEventoAusencia(item, fallbackUsername).color
		},

		/**
		 * Calcula el color y la clase CSS de un evento del calendario
		 * según su estado: cancelado (gris), rechazado (rojo) o normal.
		 */
		estiloEventoAusencia(item, fallbackUsername) {
			const g = Number(item.a_gerente)
			const s = Number(item.a_socio)
			const ch = Number(item.a_capital_humano ?? 0)

			const isCancelled = g === 3 || s === 3 || ch === 3
			const isRejected = g === 2 || s === 2 || ch === 2

			if (isCancelled) {
				return { color: '#9e9e9e', classNames: ['event-cancelled'] }
			}
			if (isRejected) {
				return { color: '#c0392b', classNames: ['event-rejected'] }
			}
			return { color: this.color(fallbackUsername), classNames: [] }
		},

		getMyAusencias(fetchInfo, success, failure) {
			axios.post(generateUrl('/apps/empleados/GetAusenciasHistorial'), {
				desde: fetchInfo.startStr,
				hasta: fetchInfo.endStr,
			})
				.then(r => {
					const data = r?.data?.ocs?.data ?? []
					const events = data.map(item => {
						const fechaInicio = new Date(item.fecha_de)
						const fechaHasta = new Date(item.fecha_hasta)
						fechaHasta.setDate(fechaHasta.getDate() + 1)
						const estilo = this.estiloEventoAusencia(item, this.employee[0].Id_user)
						return {
							id: item.id_historial_ausencias,
							title: item.tipo_nombre,
							start: fechaInicio.toISOString(),
							end: fechaHasta.toISOString(),
							allDay: true,
							color: estilo.color,
							classNames: estilo.classNames,
							nombre_empleado: item.nombre_empleado,
						}
					})
					success(events)
				})
				.catch(error => { console.error(error); failure(error) })
		},

		GetAusenciasMyWorkers(fetchInfo, success, failure) {
			axios.post(generateUrl('/apps/empleados/GetAusenciasMyWorkers'), {
				desde: fetchInfo.startStr,
				hasta: fetchInfo.endStr,
			})
				.then(r => {
					const data = r?.data?.ocs?.data?.message || r?.data?.message || []
					const events = data.map(item => {
						const fechaInicio = new Date(item.fecha_de)
						const fechaHasta = new Date(item.fecha_hasta)
						fechaHasta.setDate(fechaHasta.getDate() + 1)
						const estilo = this.estiloEventoAusencia(item, item.nombre_empleado)
						return {
							id: item.id_historial_ausencias,
							title: item.nombre_empleado + ' - ' + item.tipo_nombre,
							start: fechaInicio.toISOString(),
							end: fechaHasta.toISOString(),
							allDay: true,
							color: estilo.color,
							classNames: estilo.classNames,
							nombre_empleado: item.nombre_empleado,
						}
					})
					success(events)
				})
				.catch(error => { console.error(error); failure(error) })
		},

		getAllAusencias(fetchInfo, success, failure) {
			axios.post(generateUrl('/apps/empleados/GetAusenciasHistorialAll'), {
				desde: fetchInfo.startStr,
				hasta: fetchInfo.endStr,
			})
				.then(r => {
					const data = r?.data?.ocs?.data?.message || r?.data?.message || []
					const events = data.map(item => {
						const fechaInicio = new Date(item.fecha_de)
						const fechaHasta = new Date(item.fecha_hasta)
						fechaHasta.setDate(fechaHasta.getDate() + 1)
						const estilo = this.estiloEventoAusencia(item, item.nombre_empleado)
						return {
							id: item.id_historial_ausencias,
							title: item.nombre_empleado + ' - ' + item.tipo_nombre,
							start: fechaInicio.toISOString(),
							end: fechaHasta.toISOString(),
							allDay: true,
							color: estilo.color,
							classNames: estilo.classNames,
							nombre_empleado: item.nombre_empleado,
						}
					})
					success(events)
				})
				.catch(error => { console.error(error); failure(error) })
		},

		getEmployeeAusencias(fetchInfo, success, failure) {
			const usuarios = Array.isArray(this.selected_user)
				? this.selected_user
				: [this.selected_user]

			axios.post(generateUrl('/apps/empleados/GetAusenciasEmployeeHistorial'), {
				id_employee: usuarios,
				desde: fetchInfo.startStr,
				hasta: fetchInfo.endStr,
			})
				.then(r => {
					const data = r?.data?.ocs?.data?.message || r?.data?.message || []
					const events = data.map(item => {
						const fechaInicio = new Date(item.fecha_de)
						const fechaHasta = new Date(item.fecha_hasta)
						fechaHasta.setDate(fechaHasta.getDate() + 1)
						const estilo = this.estiloEventoAusencia(item, item.nombre_empleado)
						return {
							id: item.id_historial_ausencias,
							title: `${item.nombre_empleado} - ${item.tipo_nombre}`,
							start: fechaInicio.toISOString(),
							end: fechaHasta.toISOString(),
							allDay: true,
							color: estilo.color,
							classNames: estilo.classNames,
							nombre_empleado: item.nombre_empleado,
						}
					})
					success(events)
				})
				.catch(error => {
					console.error(error)
					this.selected_user = null
					failure(error)
				})
		},

		onDateClick(arg) {
			if (this.configuraciones.modulo_ausencias_readonly === 'true') {
				showInfo(t('empleados', 'This module is in read-only mode'))
			}
		},

		OnClickEvent(info) {
			this.selectedEventId = info.event.id
			this.modalEvento = true
		},

		onAbsenceCancelled() {
			this.closeModalEvento()
			this.GetAusencias()
			this.checkNotifications()
			this.$refs.fullCalendar.getApi().refetchEvents()
		},

		onAbsenceEdit(ausencia) {
			this.ausenciaEditar = ausencia
			this.closeModalEvento()
			this.$nextTick(() => {
				this.modalEditar = true
			})
		},

		onAbsenceEditSaved() {
			this.closeModalEditar()
			this.GetAusencias()
			this.$refs.fullCalendar.getApi().refetchEvents()
		},

		color(username) {
			const { r, g, b } = usernameToColor(username)
			return `rgb(${r}, ${g}, ${b})`
		},

		isAdmin() {
			return 'admin' in this.groupuser || 'recursos_humanos' in this.groupuser
		},

		async updateList() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetEmpleadosList'))
				const empleados = response?.data?.ocs?.data.Empleados || []
				this.propsEmployees.options = empleados.map(user => ({
					Id_empleados: user.Id_empleados,
					displayName: user.Nombre || user.Id_user,
					isNoUser: false,
					icon: '',
					user: user.Id_user,
					preloadedUserStatus: {
						icon: '',
						status: user.Estatus === 'activo' ? 'online' : 'offline',
						message: user.Estatus === 'activo' ? t('empleados', 'Active') : t('empleados', 'Inactive'),
					},
				}))
			} catch (err) {
				showError(t('empleados', 'An exception has occurred: {err}', { err }))
				console.error(err)
			}
		},

		onDateRangeSelect(selection) {
			if (!this.isAdmin()) {
				if (this.configuraciones.modulo_ausencias_readonly === 'true') return
			}
			const nDate = new Date()
			this.range = selection
			if (!this.range || !this.range.start || !this.range.end) return

			const startDate = new Date(this.range.start)
			const endDate = new Date(this.range.end)
			endDate.setDate(endDate.getDate() - 1)

			if (!this.isAdmin()) {
				if (
					new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate())
					< new Date(nDate.getFullYear(), nDate.getMonth(), nDate.getDate())
				) {
					showError(t('empleados', 'You cannot request absences on past dates'))
					return
				}
			}

			const dia = endDate.getDay()
			const diaInicio = startDate.getDay()
			if (dia === 0 || dia === 6 || diaInicio === 0 || diaInicio === 6) {
				showError(t('empleados', 'You cannot start or end your absence on a weekend'))
				return
			}

			let fecha = new Date(startDate)
			let diasHabiles = 0
			while (fecha <= endDate) {
				const diaSemana = fecha.getDay()
				if (diaSemana !== 0 && diaSemana !== 6) diasHabiles++
				fecha = new Date(fecha.getFullYear(), fecha.getMonth(), fecha.getDate() + 1)
			}

			this.diasSolicitados = diasHabiles
			this.date = { start: startDate, end: endDate }
			this.modal = true
		},

		async GetAllEquipo() {
			try {
				await axios.get(generateUrl('/apps/empleados/GetMyEquipo'))
					.then((response) => {
						this.peopleEquipo = response?.data?.ocs?.data
					})
			} catch (err) {
				// eslint-disable-next-line no-console
				console.log(err)
			}
		},

		async getEquipos() {
			axios.post(generateUrl(generateUrl('/apps/empleados/GetEquipoJefe')), {
				id: this.employee[0].Id_equipo,
			})
				.then(r => {
					const response = r?.data?.ocs?.data || []
					this.Equipo = response[0] || {}
				})
				.catch(error => {
					console.error('Error getting team lead:', error)
				})
		},

		onEmployeesChange(news) {
			if (news !== null && news.length > 0) {
				this.selected_user = news
				this.typePetition = 'employee'
			} else {
				this.selected_user = null
				this.typePetition = null
			}
			this.$nextTick(() => {
				this.$refs.fullCalendar?.getApi()?.refetchEvents()
			})
		},
	},
}
</script>

<style>
/* Global: tachado para eventos cancelados en el calendario */
.event-cancelled .fc-event-title {
	text-decoration: line-through;
	opacity: 0.8;
}

/* Global: tachado para eventos rechazados en el calendario (mismo trato que cancelados, color distinto) */
.event-rejected .fc-event-title {
	text-decoration: line-through;
	opacity: 0.8;
}
</style>

<style scoped>
.layout {
	width: 100%;
	display: flex;
	gap: 16px;
}
.grow1 { flex: 3; }
.grow2 { flex: 7; }
.grow3 { flex: 3; }
.grow4 { flex: 3; }
.cards {
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	border-radius: 0.75rem;
	background-color: white;
	border: 1px solid #cbd5e0;
}

.infos {
	border: none;
	padding: 1.5rem;
	text-align: center;
}
.titles {
	color: rgb(38 50 56);
	font-weight: 600;
	font-size: 1.25rem;
	margin-bottom: 0.5rem;
}
.footers {
	padding: 0.75rem;
	border: 1px solid rgb(236 239 241);
	display: flex;
	align-items: center;
	justify-content: space-between;
	background-color: rgba(0, 140, 255, 0.082);
}

.btn-top-right {
	position: absolute;
	top: 0.5rem;
	right: 0.5rem;
	background-color: white;
	border: none;
	border-radius: 50%;
	padding: 0.5rem;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
	cursor: pointer;
	font-size: 1rem;
	transition: transform 0.2s ease;
}
.btn-top { margin-top: 10px; }
.btn-top-right:hover { transform: scale(1.1); }
.table_component { overflow: auto; width: 100%; }
.table_component table {
	border: 1px solid #dededf;
	width: 100%;
	table-layout: fixed;
	border-collapse: collapse;
	text-align: left;
}
.table_component th,
.table_component td { border: 1px solid #dededf; padding: 5px; }
.table_component th { background-color: #eceff1; color: black; }
.table_component td { background-color: white; color: black; }
.caption-title { font-weight: bold; }
.modal__content { margin: 50px; }
.sectionPicker { height: clamp(520px, 70vh, 780px); }
.my-calendar {
	height: 100%;
	--color-background-dark: transparent !important;
}
.acordeon-item { margin-bottom: 10px; border-radius: 5px; overflow: hidden; }
.acordeon-titulo {
	width: 100%;
	text-align: center;
	border: none;
	justify-content: space-between;
	align-items: center;
}
.acordeon-contenido { max-height: 0; opacity: 0; overflow: hidden; transition: all 0.3s ease-in-out; }
.acordeon-contenido.abierto { max-height: 500px; opacity: 1; }
.flex-to-right { margin-left: auto; margin-right: 5%; cursor: pointer; }
.subtitle_flex { margin-left: 4%; }
.btn-top-subtitle { margin-top: 3px; }
.pointer { cursor: pointer; }
.acordeon-notification {
	width: 100%;
	border: none;
	display: flex;
	align-items: center;
	justify-content: space-between;
	position: relative;
}
.noti-wrapper {
	position: initial;
	width: 24px;
	height: 24px;
	display: flex;
	align-items: center;
	justify-content: center;
}
.noti-badge { position: absolute; top: -5px; right: -5px; }
.noti-text { text-align: left; }
.arrow { font-weight: bold; color: #666; }
@keyframes shake {
	0%   { transform: rotate(0deg); }
	15%  { transform: rotate(-15deg); }
	30%  { transform: rotate(15deg); }
	45%  { transform: rotate(-10deg); }
	60%  { transform: rotate(10deg); }
	75%  { transform: rotate(-5deg); }
	90%  { transform: rotate(5deg); }
	100% { transform: rotate(0deg); }
}
.bell-shake { animation: shake 0.8s ease; }

.h2-white {
	color: white;
	margin: 0;
	font-size: 1.4rem;
	letter-spacing: 0.3px;
}

.gl {
	display: flex;
	justify-content: center;
}

.headers {
	position: relative;
	margin-top: 1.5rem;
	margin-left: 1rem;
	margin-right: 1rem;
	border-radius: 1rem;
	background: linear-gradient(135deg, rgb(33 150 243), rgb(25 118 210));
	background-clip: border-box;
	box-shadow: 0 10px 25px -5px rgba(33, 150, 243, .45), 0 4px 6px -4px rgba(33, 150, 243, .3);
	min-height: 8rem;
	padding: 1.5rem 1.25rem 1.25rem;
	text-align: center;
	display: flex;
	align-items: center;
	justify-content: center;
}

.header-content {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 0.9rem;
	width: 100%;
}

.dias-wrapper {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 4px;
	color: white;
}

.dias-label {
	font-size: 0.7rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.06em;
	color: rgba(255, 255, 255, 0.829);
}

.dias-solo {
	font-size: 1.1rem;
	font-weight: 700;
	letter-spacing: -0.01em;
}

.dias-acumulados {
	display: inline-block;
	padding: 2px 8px;
	margin-left: 4px;
	border-radius: 999px;
	background: #2563eb;
	color: white;
	font-weight: 800;
	font-size: .88rem;
	letter-spacing: .02em;
	box-shadow: 0 2px 6px rgba(37,99,235,.35);
}

.vacations {
	display: flex;
	flex-direction: column;
	align-items: center;
	width: 100%;
}

.vacations-panel {
	width: 100%;
	max-width: 320px;
	background: rgba(255, 255, 255, 0.97);
	border-radius: 14px;
	box-shadow: 0 6px 18px rgba(15, 23, 42, 0.12);
	overflow: hidden;
}

.panel-row {
	padding: 12px 16px 3px;
}

.panel-row1 {
	padding: 3px 16px 12px;
}

.panel-divider {
	height: 1px;
	background: rgba(15, 23, 42, 0.08);
	margin: 0 16px;
}

/* — Accumulated (top) — */
.panel-row--accum {
	display: flex;
	align-items: flex-start;
	gap: 10px;
	text-align: left;
}

.row-icon {
	flex-shrink: 0;
	margin-top: 2px;
	color: #b45309;
}

.row-text {
	margin: 0;
	font-size: 0.78rem;
	line-height: 1.55;
	color: #57534e;
}

.row-text strong {
	color: #1c1917;
	font-weight: 700;
}

/* — Current period (bottom) — */
.panel-row--current {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	gap: 12px;
}

.row-label {
	font-size: 0.7rem;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: #78716c;
}

.row-value {
	font-size: 1.15rem;
	font-weight: 700;
	color: #1c1917;
	letter-spacing: -0.01em;
}
</style>
