<template id="content">
	<NcAppContent :name="t('empleados', 'Working time')">
		<div class="time-off-page">
			<NcNoteCard
				v-if="configuraciones.modulo_ausencias_readonly === 'true'"
				type="error"
				:heading="t('empleados', 'Attention')"
				:text="t('empleados', 'The module is in read-only mode')" />

			<header class="page-header">
				<div>
					<h2>{{ t('empleados', 'Vacation') }}</h2>
					<p>{{ vista_actual }}</p>
				</div>
				<NcButton @click="showAniversarioModal">
					<template #icon>
						<CalendarQuestionOutline :size="20" />
					</template>
					{{ t('empleados', 'My information') }}
				</NcButton>
			</header>

			<section class="layout">
				<div class="calendar-panel">
					<FullCalendar
						ref="fullCalendar"
						:options="calendarOptions"
						class="my-calendar" />
				</div>

				<aside class="side-panel">
					<section class="vacation-balance">
						<span>{{ t('empleados', 'Available days') }}</span>
						<strong v-if="Ausencias.dias_disponibles">
							{{ formatearDias(Ausencias.dias_disponibles) }}
						</strong>
						<NcLoadingIcon v-else />
					</section>

					<NcButton
						variant="secondary"
						wide
						@click="typePetition = null; $refs.fullCalendar.getApi().refetchEvents()">
						{{ t('empleados', 'Show my absences') }}
					</NcButton>

					<section v-if="notificaciones" class="filter-section">
						<button class="section-toggle" type="button" @click="toggle(0)">
							<span class="section-title">
								<BellOutline :class="{ 'bell-shake': isShaking }" :size="20" />
								{{ t('empleados', 'Pending') }}
								<NcCounterBubble :count="notifications_counter" />
							</span>
							<ChevronUp v-if="accordeon[0].abierto" :size="20" />
							<ChevronDown v-else :size="20" />
						</button>
						<div :class="['section-content', { abierto: accordeon[0].abierto }]">
							<NcListItem
								v-for="item in notifications_result"
								:key="item.id_historial_ausencias"
								:name="item.displayname ? item.displayname : item.Id_user"
								@click.prevent="selectNotification(item)">
								<template #icon>
									<NcAvatar
										disable-menu
										:size="44"
										:user="item.Id_user"
										:display-name="item.Id_user" />
								</template>
								<template #subname>
									{{ new Date(item.fecha_de).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' }) }}
								</template>
							</NcListItem>
						</div>
					</section>

					<section v-if="Object.keys(Equipo).length" class="filter-section">
						<button class="section-toggle" type="button" @click="toggle(1)">
							<span class="section-title">{{ t('empleados', 'Filter by team') }}</span>
							<ChevronUp v-if="accordeon[1].abierto" :size="20" />
							<ChevronDown v-else :size="20" />
						</button>
						<div :class="['section-content', { abierto: accordeon[1].abierto }]">
							<div class="team-heading">
								<NcAvatar :user="Equipo.Id_jefe_equipo" :display-name="Equipo.Id_jefe_equipo" :size="24" />
								<strong>{{ Equipo.Nombre }}</strong>
								<NcButton
									type="tertiary"
									:aria-label="t('empleados', 'Show all team')"
									@click="typePetition = 'all'; $refs.fullCalendar.getApi().refetchEvents()">
									<template #icon>
										<AccountGroup :size="20" />
									</template>
								</NcButton>
							</div>
							<NcListItem
								v-for="item in peopleEquipo.equipo"
								:key="item.Id_empleados"
								:name="item.displayname ? item.displayname : item.Id_user"
								@click.prevent="selectEmployee(item)">
								<template #icon>
									<NcAvatar
										disable-menu
										:size="44"
										:user="item.Id_user"
										:display-name="item.Id_user" />
								</template>
							</NcListItem>
						</div>
					</section>

					<section v-if="isAdmin()" class="filter-section">
						<button class="section-toggle" type="button" @click="toggle(2)">
							<span class="section-title">{{ t('empleados', 'Administrative') }}</span>
							<ChevronUp v-if="accordeon[2].abierto" :size="20" />
							<ChevronDown v-else :size="20" />
						</button>
						<div :class="['section-content', { abierto: accordeon[2].abierto }]">
							<NcSelect v-bind="propsEmployees" v-model="employees" />
						</div>
					</section>

					<section v-if="subordinates.length > 0" class="filter-section">
						<button class="section-toggle" type="button" @click="toggle(3)">
							<span class="section-title">{{ t('empleados', 'My subordinates') }}</span>
							<ChevronUp v-if="accordeon[3].abierto" :size="20" />
							<ChevronDown v-else :size="20" />
						</button>
						<div :class="['section-content', { abierto: accordeon[3].abierto }]">
							<div class="team-heading">
								<strong>{{ t('empleados', 'My subordinates') }}</strong>
								<NcButton
									type="tertiary"
									:aria-label="t('empleados', 'Show all subordinates')"
									@click="typePetition = 'all-employees'; $refs.fullCalendar.getApi().refetchEvents()">
									<template #icon>
										<AccountGroup :size="20" />
									</template>
								</NcButton>
							</div>
							<NcListItem
								v-for="item in subordinates"
								:key="item.Id_empleados"
								:name="item.displayname ? item.displayname : item.Id_user"
								@click.prevent="selectEmployee(item)">
								<template #icon>
									<NcAvatar
										disable-menu
										:size="44"
										:user="item.Id_user"
										:display-name="item.Id_user" />
								</template>
							</NcListItem>
						</div>
					</section>
				</aside>
			</section>
		</div>
		<!-- EVENT DETAILS MODAL -->
		<NcModal
			v-if="modalEvento"
			ref="modalRef"
			size="large"
			:name="t('empleados', 'Absence details')"
			@close="closeModalEvento">
			<div class="modal__content">
				<div class="form-group">
					{{ infoSelected }}
				</div>
			</div>
		</NcModal>
		<!-- END EVENT DETAILS MODAL -->

		<!-- ABSENCE REQUEST MODAL -->
		<NcModal
			v-if="modal"
			size="large"
			:name="t('empleados', 'Absence form')"
			@close="closeModal">
			<NuevaSolicitud
				v-if="modal"
				ref="modalRef"
				:date="date"
				:dias-solicitados="diasSolicitados"
				:dias-disponibles="Ausencias.dias_disponibles"
				:prima="Ausencias.prima_vacacional"
				:employees="propsEmployees.options"
				:admin="isAdmin()"
				@close="closeModal" />
		</NcModal>
		<!-- END ABSENCE REQUEST MODAL -->

		<!-- ANNIVERSARIES INFO MODAL -->
		<NcModal
			v-if="ModalAniversario"
			ref="modalRef"
			size="large"
			:name="t('empleados', 'Anniversary table')"
			@close="closeModalAniversario">
			<div class="modal__content anniversary-modal-content">
				<div class="anniversary-layout">
					<div>
						<TrofeosAniversarios :info="Ausencias" :acumular="configuraciones.acumular_vacaciones" />
						<div class="anniversary-table-wrap">
							<table class="anniversary-table">
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
												{{ t('empleados', '{from} to {to}', { from: grupo.desde, to: grupo.hasta }) }}
											</span>
										</td>
										<td>{{ grupo.dias }}</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
					<div>
						<MensajeAniversarios :info="Ausencias" :acumular="configuraciones.acumular_vacaciones" />
					</div>
				</div>
			</div>
		</NcModal>
		<!-- END ANNIVERSARIES INFO MODAL -->
	</NcAppContent>
</template>

<script>
// Importing necessary components
import MensajeAniversarios from './MensajeAniversarios.vue'
import TrofeosAniversarios from './TrofeosAniversarios.vue'
import NuevaSolicitud from './Modal/NuevaSolicitud.vue'

import FullCalendar from '@fullcalendar/vue'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import multiMonthPlugin from '@fullcalendar/multimonth'

import { ref } from 'vue'

import usernameToColor from '@nextcloud/vue/functions/usernameToColor'
import { showError, /* showSuccess */ showInfo } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

// icons
import BellOutline from 'vue-material-design-icons/BellOutline.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import CalendarQuestionOutline from 'vue-material-design-icons/CalendarQuestionOutline.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'

import {
	NcAppContent,
	NcModal,
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
		NcAppContent,
		NcModal,
		AccountGroup,
		CalendarQuestionOutline,
		ChevronDown,
		ChevronUp,
		FullCalendar,
		NcListItem,
		NcAvatar,
		NcButton,
		NcSelect,
		NcCounterBubble,
		BellOutline,
		NcLoadingIcon,
		NcNoteCard,
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
			selected_user: null, // selected user
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

	watch: {
		employees(news) {
			if (news !== null && news.length > 0) {
				this.selected_user = news
				this.typePetition = 'employee'
				this.$refs.fullCalendar.getApi().refetchEvents()
			}
		},
	},

	mounted() {
		this.$bus.on('close-solicitud', () => {
			this.GetAusencias()
			this.closeModal()
			this.$refs.fullCalendar.getApi().refetchEvents()
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
		async checkNotifications() {
			if (this.subordinates.length > 0) {
				try {
					await axios.get(generateUrl('/apps/empleados/GetNotificationsSubordinates'))
						.then((response) => {
							if (response.data.length > 0) {
							 this.notificaciones = true
							 this.notifications_counter = response.data.length
							 this.notifications_result = response.data
							 this.startShaking()
							} else {
							 this.notificaciones = false
							}
						})
				} catch (err) {
					showError(t('empleados', 'An exception has occurred [01] [{err}]', { err }))
				}
			}
		},

		startShaking() {
			setInterval(() => {
				this.isShaking = true
				setTimeout(() => {
					this.isShaking = false
				}, 900)
			}, 2000)
		},

		toggle(index) {
			this.accordeon = this.accordeon.map((item, i) => ({
				...item,
				abierto: i === index ? !item.abierto : false,
			}))
		},
		selectNotification(item) {
			this.employees = []
			this.typePetition = 'employee'
			this.selected_user = item
			this.$refs.fullCalendar.getApi().gotoDate(item.fecha_de)
			this.$refs.fullCalendar.getApi().refetchEvents()
		},
		selectEmployee(item) {
			this.employees = []
			this.typePetition = 'employee'
			this.selected_user = item
			this.$refs.fullCalendar.getApi().refetchEvents()
		},
		showAniversarioModal() {
			this.getAniversarios()
		},
		closeModal() {
			this.modal = false
		},
		closeModalAniversario() {
			this.ModalAniversario = false
		},
		closeModalEvento() {
			this.modalEvento = false
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

		getMyAusencias(fetchInfo, success, failure) {
			axios.post(generateUrl('/apps/empleados/GetAusenciasHistorial'), {
				desde: fetchInfo.startStr,
				hasta: fetchInfo.endStr,
			})
				.then(r => {
					const data = r?.data?.ocs?.data
					const events = data.map(item => {
						const fechaInicio = new Date(item.fecha_de)
						const fechaHasta = new Date(item.fecha_hasta)
						fechaHasta.setDate(fechaHasta.getDate() + 1)

						return {
							id: item.id_historial_ausencias,
							title: item.tipo_nombre,
							start: fechaInicio.toISOString(),
							end: fechaHasta.toISOString(),
							allDay: true,
							color: this.color(this.employee[0].Id_user),
							nombre_empleado: item.nombre_empleado,
						}
					})
					success(events)
				})
				.catch(error => {
					console.error(error)
					failure(error)
				})
		},

		GetAusenciasMyWorkers(fetchInfo, success, failure) {
			axios.post(generateUrl('/apps/empleados/GetAusenciasMyWorkers'), {
				desde: fetchInfo.startStr,
				hasta: fetchInfo.endStr,
			})
				.then(r => {
					const data = r.data.message || []
					const events = data.map(item => {
						const fechaInicio = new Date(item.fecha_de)
						const fechaHasta = new Date(item.fecha_hasta)
						fechaHasta.setDate(fechaHasta.getDate() + 1)

						return {
							id: item.id_historial_ausencias,
							title: item.nombre_empleado + ' - ' + item.tipo_nombre,
							start: fechaInicio.toISOString(),
							end: fechaHasta.toISOString(),
							allDay: true,
							color: this.color(item.nombre_empleado),
							nombre_empleado: item.nombre_empleado,
						}
					})
					success(events)
				})
				.catch(error => {
					console.error(error)
					failure(error)
				})
		},

		getAllAusencias(fetchInfo, success, failure) {
			axios.post(generateUrl('/apps/empleados/GetAusenciasHistorialAll'), {
				desde: fetchInfo.startStr,
				hasta: fetchInfo.endStr,
			})
				.then(r => {
					const data = r.data.message || []
					const events = data.map(item => {
						const fechaInicio = new Date(item.fecha_de)
						const fechaHasta = new Date(item.fecha_hasta)
						fechaHasta.setDate(fechaHasta.getDate() + 1)

						return {
							id: item.id_historial_ausencias,
							title: item.nombre_empleado + ' - ' + item.tipo_nombre,
							start: fechaInicio.toISOString(),
							end: fechaHasta.toISOString(),
							allDay: true,
							color: this.color(item.nombre_empleado),
							nombre_empleado: item.nombre_empleado,
						}
					})
					success(events)
				})
				.catch(error => {
					console.error(error)
					failure(error)
				})
		},

		getEmployeeAusencias(fetchInfo, success, failure) {
			axios.post(generateUrl('/apps/empleados/GetAusenciasEmployeeHistorial'), {
				id_employee: this.selected_user,
				desde: fetchInfo.startStr,
				hasta: fetchInfo.endStr,
			})
				.then(r => {
					const data = r.data.message || []
					const events = data.map(item => {
						const fechaInicio = new Date(item.fecha_de)
						const fechaHasta = new Date(item.fecha_hasta)
						fechaHasta.setDate(fechaHasta.getDate() + 1)

						return {
							id: item.id_historial_ausencias,
							title: `${item.nombre_empleado} - ${item.tipo_nombre}`,
							start: fechaInicio.toISOString(),
							end: fechaHasta.toISOString(),
							allDay: true,
							color: this.color?.(item.nombre_empleado) || '#3a87ad',
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
			this.infoSelected = info.event
			this.modalEvento = true
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
				if (this.configuraciones.modulo_ausencias_readonly === 'true') {
					return
				}
			}
			const nDate = new Date()
			this.range = selection
			if (!this.range || !this.range.start || !this.range.end) return

			const startDate = new Date(this.range.start)
			const endDate = new Date(this.range.end)
			endDate.setDate(endDate.getDate() - 1)

			// No past dates if not admin
			if (!this.isAdmin()) {
				if (
					new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate())
					< new Date(nDate.getFullYear(), nDate.getMonth(), nDate.getDate())
				) {
					showError(t('empleados', 'You cannot request absences on past dates'))
					return
				}
			}

			// No weekend start/end
			const dia = endDate.getDay()
			const diaInicio = startDate.getDay()
			if (dia === 0 || dia === 6 || diaInicio === 0 || diaInicio === 6) {
				showError(t('empleados', 'You cannot start or end your absence on a weekend'))
				return
			}

			// Business day count
			let fecha = new Date(startDate)
			let diasHabiles = 0
			while (fecha <= endDate) {
				const diaSemana = fecha.getDay()
				if (diaSemana !== 0 && diaSemana !== 6) {
					diasHabiles++
				}
				fecha = new Date(fecha.getFullYear(), fecha.getMonth(), fecha.getDate() + 1)
			}

			this.diasSolicitados = diasHabiles
			this.date = {
				start: startDate,
				end: endDate,
			}
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
	},
}
</script>
<style scoped>
.time-off-page {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 24px;
}

.page-header {
	display: flex;
	gap: 16px;
	justify-content: space-between;
	align-items: flex-start;
}

.page-header h2,
.page-header p {
	margin: 0;
}

.page-header p {
	color: var(--color-text-maxcontrast);
}

.layout {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(280px, 360px);
	gap: 16px;
	align-items: start;
}

.calendar-panel,
.side-panel {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-main-background);
}

.calendar-panel {
	min-width: 0;
	padding: 12px;
	overflow: auto;
}

.side-panel {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 12px;
}

.vacation-balance {
	display: grid;
	gap: 4px;
	padding: 14px;
	border-radius: var(--border-radius-large, 8px);
	background-color: var(--color-primary-element-light);
}

.vacation-balance span {
	color: var(--color-text-maxcontrast);
}

.vacation-balance strong {
	font-size: 24px;
	line-height: 1.2;
}

.filter-section {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	overflow: hidden;
}

.section-toggle {
	display: flex;
	align-items: center;
	justify-content: space-between;
	width: 100%;
	min-height: 44px;
	padding: 10px 12px;
	border: none;
	background: transparent;
	color: var(--color-main-text);
	cursor: pointer;
	font-weight: 700;
}

.section-toggle:hover,
.section-toggle:focus-visible {
	background-color: var(--color-background-hover);
}

.section-title {
	display: inline-flex;
	gap: 8px;
	align-items: center;
	min-width: 0;
}

.section-content {
	display: none;
	max-height: 320px;
	padding: 8px;
	overflow-y: auto;
	border-top: 1px solid var(--color-border);
}

.section-content.abierto {
	display: block;
}

.team-heading {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr) auto;
	gap: 8px;
	align-items: center;
	padding: 4px 4px 8px;
}

.modal__content {
	padding: 24px;
}

.anniversary-modal-content {
	width: min(980px, calc(100vw - 48px));
}

.anniversary-layout {
	display: grid;
	grid-template-columns: minmax(260px, 0.85fr) minmax(320px, 1fr);
	gap: 20px;
	align-items: start;
}

.anniversary-table-wrap {
	margin-top: 12px;
	overflow-x: auto;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
}

.anniversary-table {
	width: 100%;
	border-collapse: collapse;
	table-layout: fixed;
}

.anniversary-table caption {
	padding: 10px 12px;
	text-align: left;
	font-weight: 700;
}

.anniversary-table th,
.anniversary-table td {
	padding: 10px 12px;
	border-top: 1px solid var(--color-border);
	text-align: left;
}

.anniversary-table th {
	color: var(--color-text-maxcontrast);
	background-color: var(--color-background-hover);
}

.my-calendar {
	height: 100%;
	--color-background-dark: transparent !important;
}

@keyframes shake {
	0% { transform: rotate(0deg); }
	15% { transform: rotate(-15deg); }
	30% { transform: rotate(15deg); }
	45% { transform: rotate(-10deg); }
	60% { transform: rotate(10deg); }
	75% { transform: rotate(-5deg); }
	90% { transform: rotate(5deg); }
	100% { transform: rotate(0deg); }
}

.bell-shake {
	animation: shake 0.8s ease;
}

@media (max-width: 1050px) {
	.layout,
	.anniversary-layout {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 700px) {
	.time-off-page {
		padding: 12px;
	}

	.page-header {
		flex-direction: column;
		align-items: stretch;
	}
}
</style>
