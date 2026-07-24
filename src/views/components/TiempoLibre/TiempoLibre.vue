<template>
	<NcAppContent name="Loading">
		<div class="">
			<div class="text-center section-calendar">
				<div v-if="configuraciones.modulo_ausencias_readonly === 'true'">
					<br>
					<NcNoteCard type="error"
						:heading="t('empleados', 'Attention!!!')"
						:text="t('empleados', 'The module is in read-only mode')" />
					<br>
				</div>
				<section class="layout">
					<div class="grow2">
						<div ref="calendarViewport" class="text-center sectionPicker">
							<FullCalendar
								ref="fullCalendar"
								:options="calendarOptions"
								class="my-calendar" />
						</div>
					</div>
					<div class="grow1">
						<div ref="sidebar" class="cards">
							<div class="headers">
								<div class="header-content">
									<h2 class="h2-white">
										{{ t('empleados', 'Vacation') }}
									</h2>
									<div class="vacations">
										<div
											class="vacations-grid"
											:class="{ 'vacations-grid--single': !tieneVacacionesAcumuladas }">
											<!-- Periodo actual -->
											<div class="vacation-card">
												<span class="vacation-card__title">
													{{ t('empleados', 'Current period') }}
												</span>

												<NcLoadingIcon
													v-if="Ausencias.dias_disponibles === undefined || Ausencias.dias_disponibles === null"
													:size="22" />

												<template v-else>
													<strong class="vacation-card__value">
														{{ Ausencias.dias_disponibles }}
													</strong>

													<span class="vacation-card__subtitle">
														{{ t('empleados', 'Days available') }}
													</span>
												</template>
											</div>

											<!-- Periodo anterior acumulado -->
											<div
												v-if="tieneVacacionesAcumuladas"
												class="vacation-card vacation-card--accumulated">
												<span class="vacation-card__title">
													{{ t('empleados', 'Previous period') }}
												</span>

												<strong class="vacation-card__value">
													{{ Ausencias.dias_acumulados }}
												</strong>

												<span class="vacation-card__subtitle">
													{{ t('empleados', 'Accumulated days') }}
												</span>

												<div class="vacation-card__warning">
													<AlertOutline :size="13" />

													<span>
														{{ t('empleados', 'Use before {fecha} or they expire', {
															fecha: new Date(
																Ausencias.fecha_expiracion_acumulados,
															).toLocaleDateString('es-MX'),
														}) }}
													</span>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="infos">
								<!-- Notificaciones pendientes -->
								<div
									v-if="notificaciones"
									class="acordeon-item acordeon-item--warning">
									<button
										type="button"
										class="acordeon-notification"
										@click="toggle(0)">
										<div class="noti-wrapper">
											<BellOutline
												class="bell-icon"
												:class="{ 'bell-shake': isShaking }" />

											<NcCounterBubble
												:count="notifications_counter"
												class="noti-badge" />
										</div>

										<span class="noti-text">
											{{ t('empleados', 'Pending') }}
										</span>

										<span class="arrow">
											{{ accordeon[0].abierto ? '−' : '+' }}
										</span>
									</button>

									<div
										:class="[
											'acordeon-contenido',
											{ abierto: accordeon[0].abierto },
										]">
										<ul class="accordion-user-list">
											<li
												v-for="item in notifications_result"
												:key="item.id_historial_ausencias">
												<button
													type="button"
													class="accordion-option"
													@click="abrirDetalleDesdeNotificacion(item)">
													<NcAvatar
														disable-menu
														:size="36"
														:user="item.Id_user"
														:display-name="item.Id_user" />

													<span class="accordion-option__text">
														<strong>
															{{ item.displayname || item.Id_user }}
														</strong>

														<small>
															{{ t('empleados', 'Pending request') }}
														</small>
													</span>
												</button>
											</li>
										</ul>
									</div>
								</div>

								<!-- Administración -->
								<div
									v-if="isAdmin()"
									class="acordeon-item acordeon-item--admin">
									<button
										type="button"
										class="acordeon-titulo"
										@click="toggle(2)">
										<span class="accordion-title">
											<span class="accordion-title__label">
												{{ t('empleados', 'Administrative') }}
											</span>

											<span class="accordion-title__description">
												{{ t('empleados', 'Reports and employee filters') }}
											</span>
										</span>

										<span class="arrow">
											{{ accordeon[2].abierto ? '−' : '+' }}
										</span>
									</button>

									<div
										:class="[
											'acordeon-contenido',
											{ abierto: accordeon[2].abierto },
										]">
										<div class="accordion-menu accordion-menu--select">
											<NcButton
												class="accordion-action-button"
												variant="secondary"
												wide
												@click="mostrarReporte = true">
												{{ t('empleados', 'Show report') }}
											</NcButton>

											<div class="accordion-field">
												<span class="accordion-field__label">
													{{ t('empleados', 'Filter by employee') }}
												</span>

												<NcSelect
													v-bind="propsEmployees"
													v-model="employees"
													@update:model-value="onEmployeesChange" />
												<NcButton
													class="accordion-action-button accordion-action-button--all"
													variant="secondary"
													wide
													@click="mostrarTodosAdministrativo">
													{{ t('empleados', 'Show all employees') }}
												</NcButton>
											</div>
										</div>
									</div>
								</div>

								<!-- Filtrar por equipo -->
								<div
									v-if="Object.keys(Equipo).length"
									class="acordeon-item">
									<button
										type="button"
										class="acordeon-titulo"
										@click="toggle(1)">
										<span class="accordion-title">
											<span class="accordion-title__label">
												{{ t('empleados', 'Filter by team') }}
											</span>

											<span class="accordion-title__description">
												{{ Equipo.Nombre }}
											</span>
										</span>

										<span class="arrow">
											{{ accordeon[1].abierto ? '−' : '+' }}
										</span>
									</button>

									<div
										:class="[
											'acordeon-contenido',
											{ abierto: accordeon[1].abierto },
										]">
										<div class="accordion-menu">
											<button
												type="button"
												class="accordion-option accordion-option--group"
												@click="
													typePetition = 'all';
													$refs.fullCalendar.getApi().refetchEvents()
												">
												<NcAvatar
													:user="Equipo.Id_jefe_equipo"
													:display-name="Equipo.Id_jefe_equipo"
													:size="34" />

												<span class="accordion-option__text">
													<strong>{{ Equipo.Nombre }}</strong>

													<small>
														{{ t('empleados', 'Show the entire team') }}
													</small>
												</span>

												<AccountGroup
													:size="21"
													class="accordion-option__icon" />
											</button>

											<ul class="accordion-user-list">
												<li
													v-for="item in peopleEquipo.equipo"
													:key="item.Id_empleados">
													<button
														type="button"
														class="accordion-option"
														@click="
															employees = [];
															typePetition = 'employee';
															selected_user = item;
															$refs.fullCalendar.getApi().refetchEvents()
														">
														<NcAvatar
															disable-menu
															:size="36"
															:user="item.Id_user"
															:display-name="item.Id_user" />

														<span class="accordion-option__text">
															<strong>
																{{ item.displayname || item.Id_user }}
															</strong>

															<small>{{ item.Id_user }}</small>
														</span>
													</button>
												</li>
											</ul>
										</div>
									</div>
								</div>

								<!-- Mis empleados -->
								<div
									v-if="subordinates.length > 0"
									class="acordeon-item">
									<button
										type="button"
										class="acordeon-titulo"
										@click="toggle(3)">
										<span class="accordion-title">
											<span class="accordion-title__label">
												{{ t('empleados', 'My subordinates') }}
											</span>

											<span class="accordion-title__description">
												{{ subordinates.length }}
												{{ t('empleados', 'employees') }}
											</span>
										</span>

										<span class="arrow">
											{{ accordeon[3].abierto ? '−' : '+' }}
										</span>
									</button>

									<div
										:class="[
											'acordeon-contenido',
											{ abierto: accordeon[3].abierto },
										]">
										<div class="accordion-menu">
											<button
												type="button"
												class="accordion-option accordion-option--group"
												@click="
													typePetition = 'all-employees';
													$refs.fullCalendar.getApi().refetchEvents()
												">
												<AccountGroup :size="34" />

												<span class="accordion-option__text">
													<strong>
														{{ t('empleados', 'My subordinates') }}
													</strong>

													<small>
														{{ t('empleados', 'Show all my subordinates') }}
													</small>
												</span>

												<AccountGroup
													:size="21"
													class="accordion-option__icon" />
											</button>

											<ul class="accordion-user-list">
												<li
													v-for="item in subordinates"
													:key="item.Id_empleados">
													<button
														type="button"
														class="accordion-option"
														@click="
															employees = [];
															typePetition = 'employee';
															selected_user = item;
															$refs.fullCalendar.getApi().refetchEvents()
														">
														<NcAvatar
															disable-menu
															:size="36"
															:user="item.Id_user"
															:display-name="item.Id_user" />

														<span class="accordion-option__text">
															<strong>
																{{ item.displayname || item.Id_user }}
															</strong>

															<small>{{ item.Id_user }}</small>
														</span>
													</button>
												</li>
											</ul>
										</div>
									</div>
								</div>

								<!-- Restablecer vista -->
								<div class="sidebar-reset">
									<NcButton
										class="sidebar-button"
										variant="secondary"
										wide
										@click="
											typePetition = null;
											selected_user = null;
											employees = [];
											$refs.fullCalendar.getApi().refetchEvents()
										">
										{{ t('empleados', 'Show my absences') }}
									</NcButton>
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
				:fecha-limite-periodo-actual="Ausencias.fecha_limite_periodo_actual"
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
				:username-empleado="usuarioAusenciaSeleccionada"
				:dias-disponibles="Ausencias.dias_disponibles"
				:fecha-limite-periodo-actual="Ausencias.fecha_limite_periodo_actual"
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
		<div class="floating-help-button">
			<NcActions>
				<NcActionButton @click="showAniversarioModal">
					<template #icon>
						<CalendarQuestionOutline :size="24" />
					</template>

					{{ t('empleados', 'My information') }}
				</NcActionButton>
			</NcActions>
		</div>
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
					left: '',
					center: 'title',
					right: 'multiMonthYear,dayGridMonth,today,prev,next',
				},
				initialView: 'dayGridMonth',
				locale: 'es',
				plugins: [dayGridPlugin, interactionPlugin, multiMonthPlugin],
				events: this.fetchEvents,
				dateClick: this.onDateClick,
				eventClick: this.OnClickEvent,
				select: this.onDateRangeSelect,
				selectable: true,
				fixedWeekCount: false,
				dayMaxEvents: true,
				dayMaxEventRows: 10,
				multiMonthMaxColumns: 4,
				multiMonthMinWidth: 225,
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
			usuarioAusenciaSeleccionada: null,
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
		tieneVacacionesAcumuladas() {
			return Number(this.Ausencias?.dias_acumulados ?? 0) > 0
				&& Boolean(this.Ausencias?.fecha_expiracion_acumulados)
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
		this.$nextTick(() => {
			this.ajustarAlturaCalendario()
			window.addEventListener('resize', this.ajustarAlturaCalendario)
		})
	},

	beforeDestroy() {
		window.removeEventListener('resize', this.ajustarAlturaCalendario)
	},

	methods: {
		t,

		abrirDetalleDesdeNotificacion(item) {
			this.selectedEventId = item.id_historial_ausencias
			this.usuarioAusenciaSeleccionada = item.Id_user || null
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
			case 'all-admin':
				this.getEmployeeAusencias(fetchInfo, success, failure)
				this.vista_actual = t('empleados', 'All employees')
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
			this.usuarioAusenciaSeleccionada = info.event.extendedProps.nombre_empleado || null // ← NUEVO
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
		ajustarAlturaCalendario() {
			this.$nextTick(() => {
				const calendarContainer = this.$refs.calendarViewport
				const sidebar = this.$refs.sidebar
				const calendar = this.$refs.fullCalendar?.getApi()

				if (!calendarContainer || !calendar) return

				const top = calendarContainer.getBoundingClientRect().top
				const margenInferior = 30

				const alturaDisponible = Math.max(
					300,
					Math.floor(window.innerHeight - top - margenInferior),
				)

				calendar.setOption('height', alturaDisponible)
				calendar.updateSize()

				if (sidebar) {
					sidebar.style.height = `${alturaDisponible}px`
					sidebar.style.maxHeight = `${alturaDisponible}px`
				}
			})
		},
		mostrarTodosAdministrativo() {
			const todosLosEmpleados = this.propsEmployees.options ?? []

			if (todosLosEmpleados.length === 0) {
				showInfo(t('empleados', 'No employees were found'))
				return
			}

			/*
	 * No llenamos `employees` para evitar que NcSelect muestre
	 * decenas de etiquetas seleccionadas.
	 */
			this.employees = []
			this.selected_user = [...todosLosEmpleados]
			this.typePetition = 'all-admin'

			this.$nextTick(() => {
				this.$refs.fullCalendar?.getApi()?.refetchEvents()
			})
		},
	},
}
</script>
<style>
/* Eventos cancelados */
.event-cancelled .fc-event-title {
	text-decoration: line-through;
	opacity: 0.8;
}

/* Eventos rechazados */
.event-rejected .fc-event-title {
	text-decoration: line-through;
	opacity: 0.8;
}
</style>

<style scoped>
/* ========================================
 * ESTRUCTURA GENERAL
 * ======================================== */

.layout {
	display: flex;
	align-items: stretch;
	width: 100%;
	gap: 16px;
}

.grow1 {
	display: flex;
	flex: 3;
	min-width: 260px;
	min-height: 0;
}

.grow2 {
	flex: 7;
	min-width: 0;
}

.grow3 {
	flex: 3;
	min-width: 0;
}

.grow4 {
	flex: 3;
	min-width: 0;
}

.section-calendar {
	padding-top: 25px;
	padding-inline: 30px;
}

.sectionPicker {
	width: 100%;
	min-width: 0;
}

.my-calendar {
	width: 100%;
	min-width: 0;

	--color-background-dark: transparent !important;
}

/* ========================================
 * SIDEBAR
 * ======================================== */

.cards {
	--sidebar-primary: #2389d7;
	--sidebar-primary-dark: #1468a8;
	--sidebar-primary-soft: #e7f3fb;
	--sidebar-soft: #edf6fc;
	--sidebar-soft-hover: #dceefa;
	--sidebar-border: #d4e2ec;
	--sidebar-border-strong: #b9d5e6;
	--sidebar-text: #17354d;
	--sidebar-muted: #66798a;
	--sidebar-warning: #b45309;
	--sidebar-warning-soft: #fff7e8;

	display: flex;
	flex: 1;
	flex-direction: column;

	width: 100%;
	height: 100%;
	max-height: 100%;
	min-width: 0;
	min-height: 0;

	overflow: hidden;

	color: var(--sidebar-text);
	background: #f8fbfd;
	border: 1px solid var(--sidebar-border);
	border-radius: 16px;

	box-shadow:
		0 8px 24px rgba(15, 47, 74, 0.08),
		0 2px 5px rgba(15, 47, 74, 0.05);
}

/* ========================================
 * ENCABEZADO DE VACACIONES
 * ======================================== */

.headers {
	position: relative;

	display: flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;

	margin: 12px 12px 10px;
	padding: 18px 14px;

	text-align: center;

	background:
		linear-gradient(
			145deg,
			var(--sidebar-primary),
			var(--sidebar-primary-dark)
		);

	border-radius: 14px;

	box-shadow:
		0 10px 22px rgba(31, 127, 195, 0.25),
		0 3px 7px rgba(31, 127, 195, 0.14);
}

.header-content {
	display: flex;
	flex-direction: column;
	align-items: center;

	width: 100%;
	gap: 12px;
}

.h2-white {
	margin: 0;

	color: white;
	font-size: 1.15rem;
	font-weight: 700;
	letter-spacing: 0.02em;
}

/* ========================================
 * TARJETAS DE VACACIONES
 * ======================================== */

.vacations {
	width: 100%;
}

.vacations-grid {
	display: flex;
	flex-direction: row;
	align-items: stretch;

	width: 100%;
	overflow: hidden;

	background: white;
	border: 1px solid rgba(15, 23, 42, 0.12);
	border-radius: 10px;

	box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
}

.vacation-card {
	display: flex;
	flex: 1 1 50%;
	flex-direction: column;
	align-items: center;
	justify-content: center;

	box-sizing: border-box;
	width: 50%;
	min-width: 0;
	min-height: 105px;
	padding: 10px 6px;

	text-align: center;
	background: white;
}

.vacation-card + .vacation-card {
	border-top: 0;
	border-left: 1px solid rgba(15, 23, 42, 0.12);
}

.vacations-grid--single .vacation-card {
	flex-basis: 100%;
	width: 100%;
}

.vacation-card__title {
	max-width: 100%;
	margin-bottom: 3px;
	overflow: hidden;

	color: #334155;
	font-size: 0.62rem;
	font-weight: 700;
	text-overflow: ellipsis;
	text-transform: uppercase;
	letter-spacing: 0.03em;
	white-space: nowrap;
}

.vacation-card__value {
	color: #8b477f;
	font-size: 1.45rem;
	font-weight: 800;
	line-height: 1;
}

.vacation-card__subtitle {
	max-width: 100%;
	margin-top: 4px;
	overflow: hidden;

	color: #64748b;
	font-size: 0.54rem;
	font-weight: 600;
	text-overflow: ellipsis;
	text-transform: uppercase;
	letter-spacing: 0.03em;
	white-space: nowrap;
}

.vacation-card--accumulated {
	background: #fffaf0;
}

.vacation-card__warning {
	display: flex;
	align-items: flex-start;
	justify-content: center;

	width: 100%;
	margin-top: 7px;
	padding-top: 6px;
	gap: 3px;

	color: #92400e;
	font-size: 0.55rem;
	line-height: 1.2;

	border-top: 1px solid rgba(146, 64, 14, 0.14);
}

.vacation-card__warning svg {
	flex-shrink: 0;
}

/* ========================================
 * CONTENIDO DESPLAZABLE DEL SIDEBAR
 * ======================================== */

.infos {
	flex: 1 1 auto;
	min-height: 0;
	padding: 4px 12px 12px;

	overflow-x: hidden;
	overflow-y: auto;

	text-align: center;
	overscroll-behavior: contain;

	scrollbar-width: thin;
	scrollbar-color: #a9c7da transparent;
}

.infos::-webkit-scrollbar {
	width: 6px;
}

.infos::-webkit-scrollbar-track {
	background: transparent;
}

.infos::-webkit-scrollbar-thumb {
	background: #a9c7da;
	border-radius: 999px;
}

.infos::-webkit-scrollbar-thumb:hover {
	background: #82aec9;
}

/* ========================================
 * BOTONES PRINCIPALES
 * ======================================== */

.sidebar-button {
	width: 100% !important;
	min-height: 40px !important;
	margin-top: 8px !important;
	padding: 7px 12px !important;

	color: var(--sidebar-text) !important;
	font-size: 0.82rem !important;
	font-weight: 700 !important;

	background: var(--sidebar-soft) !important;
	border: 1px solid transparent !important;
	border-radius: 10px !important;

	box-shadow: none !important;

	transition:
		background-color 0.18s ease,
		border-color 0.18s ease,
		transform 0.18s ease,
		box-shadow 0.18s ease;
}

.btn-top {
	margin-top: 8px;
}

/* ========================================
 * ACORDEONES
 * ======================================== */

.acordeon-item {
	flex: 0 0 auto;

	box-sizing: border-box;
	width: 100%;
	margin-top: 8px;
	margin-bottom: 0;

	overflow: hidden;

	background: white;
	border: 1px solid var(--sidebar-border);
	border-radius: 10px;

	transition:
		border-color 0.18s ease,
		box-shadow 0.18s ease;
}

:is(.acordeon-titulo, .acordeon-notification) {
	display: flex;
	align-items: center;
	justify-content: space-between;

	box-sizing: border-box;
	width: 100%;
	min-height: 40px;
	padding: 8px 10px;

	color: var(--sidebar-text);
	font-family: inherit;
	font-size: 0.82rem;
	font-weight: 700;
	text-align: left;

	background: var(--sidebar-soft);
	border: none;

	cursor: pointer;

	transition:
		background-color 0.18s ease,
		color 0.18s ease;
}

.acordeon-notification {
	color: #8a3d08;
	background: var(--sidebar-warning-soft);
}

.acordeon-contenido {
	box-sizing: border-box;

	max-height: 0;
	padding: 0 8px;

	overflow: hidden;
	opacity: 0;

	background: white;

	transition:
		max-height 0.28s ease,
		padding 0.28s ease,
		opacity 0.2s ease;
}

.acordeon-contenido.abierto {
	max-height: min(44dvh, 390px);
	padding: 8px;

	overflow: hidden;
	opacity: 1;

	border-top: 1px solid #e5edf3;
}

/* ========================================
 * INDICADOR + / −
 * ======================================== */

.arrow {
	display: inline-flex;
	flex: 0 0 22px;
	align-items: center;
	justify-content: center;

	width: 22px;
	height: 22px;
	margin-left: 8px;

	color: var(--sidebar-primary-dark);
	font-size: 1rem;
	font-weight: 700;
	line-height: 1;

	background: rgba(31, 127, 195, 0.11);
	border-radius: 50%;
}

/* ========================================
 * NOTIFICACIONES
 * ======================================== */

.noti-wrapper {
	position: relative;

	display: flex;
	flex: 0 0 24px;
	align-items: center;
	justify-content: center;

	width: 24px;
	height: 24px;

	color: var(--sidebar-warning);
}

.noti-badge {
	position: absolute;
	top: -9px;
	right: -11px;

	transform: scale(0.82);
	transform-origin: center;
}

.noti-text {
	flex: 1;
	min-width: 0;
	margin-left: 9px;
	overflow: hidden;

	text-align: left;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* ========================================
 * MENÚ INTERNO DE ACORDEONES
 * ======================================== */

.accordion-menu {
	display: flex;
	flex-direction: column;

	box-sizing: border-box;
	width: 100%;
	min-width: 0;
	gap: 6px;
}

.accordion-menu--select {
	padding: 3px 0;
}

.accordion-user-list {
	box-sizing: border-box;
	width: 100%;
	max-height: 245px;
	margin: 0;
	padding: 0 3px 0 0;

	overflow-x: hidden;
	overflow-y: auto;

	list-style: none;
	overscroll-behavior: contain;

	scrollbar-width: thin;
	scrollbar-color: #b8cede transparent;
}

.accordion-user-list::-webkit-scrollbar {
	width: 5px;
}

.accordion-user-list::-webkit-scrollbar-track {
	background: transparent;
}

.accordion-user-list::-webkit-scrollbar-thumb {
	background: #b8cede;
	border-radius: 999px;
}

.accordion-user-list > li {
	margin: 0;
	padding: 0;
}

.accordion-user-list > li + li {
	margin-top: 4px;
}

/* ========================================
 * OPCIONES DE EQUIPO Y USUARIOS
 * ======================================== */

.accordion-option {
	display: flex;
	align-items: center;

	box-sizing: border-box;
	width: 100%;
	min-width: 0;
	min-height: 48px;
	padding: 6px 9px;
	gap: 9px;

	color: var(--sidebar-text);
	font-family: inherit;
	text-align: left;

	background: transparent;
	border: 1px solid transparent;
	border-radius: 9px;

	cursor: pointer;

	transition:
		background-color 0.16s ease,
		border-color 0.16s ease,
		transform 0.16s ease;
}

.accordion-option--group {
	min-height: 55px;

	background: #f3f8fc;
	border-color: #dde9f1;
}

.accordion-option__text {
	display: flex;
	flex: 1;
	flex-direction: column;

	min-width: 0;
	gap: 2px;
}

.accordion-option__text strong {
	max-width: 100%;
	overflow: hidden;

	color: var(--sidebar-text);
	font-size: 0.78rem;
	font-weight: 700;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.accordion-option__text small {
	max-width: 100%;
	overflow: hidden;

	color: var(--sidebar-muted);
	font-size: 0.67rem;
	font-weight: 500;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.accordion-option__icon {
	flex: 0 0 auto;
	margin-left: auto;

	color: var(--sidebar-primary);
}

/* ========================================
 * COMPATIBILIDAD CON LA ESTRUCTURA ANTERIOR
 * ======================================== */

.rst {
	width: 100%;
	min-width: 0;
}

.rst ul {
	width: 100%;
	margin: 0;
	padding: 0;

	list-style: none;
}

.rst-title {
	margin-bottom: 6px;
	padding: 7px 8px;

	background: #f3f8fc;
	border: 1px solid #dde9f1;
	border-radius: 9px;
}

.title_flex {
	display: flex;
	align-items: center;
	width: 100%;
	gap: 8px;
}

.subtitle_flex {
	display: flex;
	align-items: center;
	margin-left: 0;
}

.btn-top-subtitle {
	min-width: 0;
	margin-top: 0;

	overflow: hidden;

	color: var(--sidebar-text);
	font-size: 0.78rem;
	font-weight: 700;
	text-align: left;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.flex-to-right {
	display: flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;

	margin-right: 0;
	margin-left: auto;

	color: var(--sidebar-primary);
	cursor: pointer;

	transition: transform 0.18s ease;
}

.pointer {
	cursor: pointer;
}

/* ========================================
 * SELECTOR ADMINISTRATIVO
 * ======================================== */

::v-deep .accordion-menu--select .v-select {
	width: 100%;
}

::v-deep .accordion-menu--select .vs__dropdown-toggle {
	min-height: 40px;

	background: #f8fbfd;
	border-color: var(--sidebar-border);
	border-radius: 9px;
}

::v-deep .accordion-menu--select .vs__selected-options {
	min-width: 0;
}

::v-deep .accordion-menu--select .vs__search {
	color: var(--sidebar-text);
	font-size: 0.78rem;
}

/* ========================================
 * PIE DEL SIDEBAR
 * ======================================== */

.footers {
	display: flex;
	flex: 0 0 auto;
	align-items: center;

	min-height: 43px;
	padding: 9px 78px 9px 12px;

	color: #47667d;
	font-size: 0.76rem;
	font-weight: 500;
	text-align: left;

	background: #edf6fc;
	border-top: 1px solid var(--sidebar-border);
}

.footers p {
	width: 100%;
	margin: 0;
	overflow: hidden;

	text-overflow: ellipsis;
	white-space: nowrap;
}

/* ========================================
 * TABLAS Y MODALES
 * ======================================== */

.table_component {
	width: 100%;
	overflow: auto;
}

.table_component table {
	width: 100%;

	border: 1px solid #dededf;
	border-collapse: collapse;

	table-layout: fixed;
	text-align: left;
}

.table_component th,
.table_component td {
	padding: 5px;
	border: 1px solid #dededf;
}

.table_component th {
	color: black;
	background-color: #eceff1;
}

.table_component td {
	color: black;
	background-color: white;
}

.caption-title {
	font-weight: 700;
}

.modal__content {
	margin: 50px;
}

/* ========================================
 * BOTÓN FLOTANTE
 * ======================================== */

.floating-help-button {
	position: fixed;
	right: 24px;
	bottom: 24px;
	z-index: 10000;

	display: flex;
	align-items: center;
	justify-content: center;

	width: 64px;
	height: 64px;
	padding: 4px;

	background-color: white;
	border: 1px solid #cbd5e0;
	border-radius: 50%;

	box-shadow:
		0 8px 20px rgba(0, 0, 0, 0.22),
		0 2px 6px rgba(0, 0, 0, 0.15);

	transition:
		transform 0.2s ease,
		box-shadow 0.2s ease;
}

/* ========================================
 * ANIMACIONES
 * ======================================== */

@keyframes shake {
	0% {
		transform: rotate(0deg);
	}

	15% {
		transform: rotate(-15deg);
	}

	30% {
		transform: rotate(15deg);
	}

	45% {
		transform: rotate(-10deg);
	}

	60% {
		transform: rotate(10deg);
	}

	75% {
		transform: rotate(-5deg);
	}

	90% {
		transform: rotate(5deg);
	}

	100% {
		transform: rotate(0deg);
	}
}

.bell-shake {
	animation: shake 0.8s ease;
}

/* ========================================
 * RESPONSIVE
 * ======================================== */

@media screen and (max-width: 1100px) {
	.layout {
		gap: 10px;
	}

	.grow1 {
		min-width: 235px;
	}

	.section-calendar {
		padding-top: 18px;
		padding-inline: 18px;
	}

	.headers {
		margin: 10px;
		padding: 15px 10px;
	}

	.infos {
		padding-inline: 10px;
	}

	:is(.sidebar-button, .acordeon-titulo, .acordeon-notification) {
		font-size: 0.76rem !important;
	}

	.vacation-card {
		min-height: 95px;
		padding: 8px 5px;
	}

	.vacation-card__title {
		font-size: 0.56rem;
	}

	.vacation-card__value {
		font-size: 1.25rem;
	}

	.vacation-card__subtitle {
		font-size: 0.48rem;
	}

	.vacation-card__warning {
		font-size: 0.5rem;
	}

	.accordion-option {
		padding: 6px 7px;
	}

	.accordion-option__text strong {
		font-size: 0.73rem;
	}

	.accordion-option__text small {
		font-size: 0.63rem;
	}
}

@media screen and (max-width: 700px) {
	.floating-help-button {
		right: 12px;
		bottom: 12px;

		width: 56px;
		height: 56px;
	}

	.modal__content {
		margin: 20px;
	}
}

/* ========================================
 * ESTADOS INTERACTIVOS
 * ======================================== */

.sidebar-button:hover {
	background: var(--sidebar-soft-hover) !important;
	border-color: #b9d8eb !important;

	box-shadow: 0 4px 10px rgba(31, 127, 195, 0.12) !important;

	transform: translateY(-1px);
}

.sidebar-button:active {
	transform: translateY(0);
}

.acordeon-item:hover {
	border-color: #b6d3e5;
	box-shadow: 0 4px 12px rgba(15, 47, 74, 0.07);
}

.acordeon-titulo:hover {
	color: var(--sidebar-primary-dark);
	background: var(--sidebar-soft-hover);
}

.acordeon-notification:hover {
	background: #ffedcc;
}

.accordion-option:hover {
	background: var(--sidebar-soft);
	border-color: #d1e4ef;
}

.accordion-option--group:hover {
	background: #e4f1f9;
	border-color: #bad8e9;
}

.accordion-option:active {
	transform: scale(0.99);
}

.flex-to-right:hover {
	transform: scale(1.12);
}

.floating-help-button:hover {
	transform: scale(1.08);

	box-shadow:
		0 10px 25px rgba(0, 0, 0, 0.28),
		0 3px 8px rgba(0, 0, 0, 0.18);
}

.accordion-title {
	display: flex;
	flex: 1;
	flex-direction: column;

	min-width: 0;
	gap: 2px;
}

.accordion-title__label {
	overflow: hidden;

	color: inherit;
	font-size: 0.8rem;
	font-weight: 700;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.accordion-title__description {
	overflow: hidden;

	color: var(--sidebar-muted);
	font-size: 0.63rem;
	font-weight: 500;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.acordeon-item--admin {
	border-color: rgba(35, 137, 215, 0.25);
}

.acordeon-item--admin > .acordeon-titulo {
	background: var(--sidebar-primary-soft);
}

.accordion-field {
	display: flex;
	flex-direction: column;

	width: 100%;
	gap: 5px;
}

.accordion-field__label {
	color: var(--sidebar-muted);
	font-size: 0.68rem;
	font-weight: 600;
	text-align: left;
}

.accordion-action-button {
	width: 100% !important;
	min-height: 38px !important;

	color: white !important;
	font-size: 0.76rem !important;
	font-weight: 700 !important;

	background: var(--sidebar-primary) !important;
	border: none !important;
	border-radius: 9px !important;
}

.sidebar-reset {
	margin-top: 10px;
	padding-top: 2px;
}

.accordion-action-button:hover {
	background: var(--sidebar-primary-dark) !important;
}
.accordion-action-button--all {
	color: var(--sidebar-primary-dark) !important;

	background: var(--sidebar-primary-soft) !important;
	border: 1px solid var(--sidebar-border-strong) !important;
}

.accordion-action-button--all:hover {
	color: white !important;

	background: var(--sidebar-primary) !important;
	border-color: var(--sidebar-primary) !important;
}
</style>
