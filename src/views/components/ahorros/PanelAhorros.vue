<template id="content">
	<NcAppContent>
		<div v-if="loading">
			<div class="center-screen">
				<NcLoadingIcon :size="64" appearance="dark" name="Loading on light background" />
			</div>
		</div>
		<div v-else class="panel-page">
			<div v-if="historial.length >= 0">
				<section class="panel-header">
					<div>
						<p class="section-label">
							{{ t('empleados', 'Savings loans') }}
						</p>
						<h2>
							<Archive :size="22"
								decorative
								class="icon" />
							<span>{{ t('empleados', 'Pending requests') }}</span>
						</h2>
						<p>{{ t('empleados', 'Review employee loan requests, filter by status and export the period.') }}</p>
					</div>

					<NcButton alignment="end" type="primary" @click="showModal">
						<template #icon>
							<DatabaseExport :size="20" />
						</template>
						{{ t('empleados', 'export') }}
					</NcButton>
				</section>

				<section class="filters-card">
					<div class="filters-grid">
						<div>
							<NcSelect v-model="options_estado_values"
								class="container__select"
								:input-label="t('empleados', 'status')"
								:options="options_estado"
								required
								@option:selected="gethistorial()" />
						</div>

						<div>
							<NcSelect v-model="options_fechas_value"
								class="container__select"
								:input-label="t('empleados', 'Year')"
								:options="options_fechas"
								required
								@option:selected="gethistorial()" />
						</div>
					</div>

					<div class="request-summary">
						<span>{{ t('empleados', 'Requests') }}</span>
						<strong>{{ historial.length }}</strong>
						<small>{{ options_estado_values }} · {{ options_fechas_value }}</small>
					</div>
				</section>

				<section v-if="historial.length > 0" class="requests-list">
					<article
						v-for="(item, itemIndex) in historial"
						:key="item.id_historial"
						class="request-card">
						<div class="request-person" @click.prevent="showModaldetails(itemIndex)">
							<div>
								<NcAvatar disable-menu
									:size="44"
									:user="item.uid"
									:display-name="item.uid" />
							</div>
							<div>
								<strong>{{ item.displayname }}</strong>
								<span>{{ item.nota || t('empleados', 'No note provided.') }}</span>
							</div>
						</div>

						<div class="request-amount">
							<span>{{ t('empleados', 'Requested') }}</span>
							<strong>{{ formatMoney(item.cantidad_solicitada) }}</strong>
						</div>

						<div class="request-actions">
							<NcButton @click="showModaldetails(itemIndex)">
								<template #icon>
									<Eye :size="20" />
								</template>
								{{ t('empleados', 'View request') }}
							</NcButton>
							<NcButton v-if="item.estado == 0" type="primary" @click="accion('aceptar', item.id_historial, item.id_user)">
								{{ t('empleados', 'Approve') }}
							</NcButton>
							<NcButton v-if="item.estado == 0" type="error" @click="accion('denegar', item.id_historial, item.id_user)">
								{{ t('empleados', 'Delete') }}
							</NcButton>
						</div>
					</article>
				</section>

				<section v-else class="empty-state">
					<h2>{{ t('empleados', 'No movements have been recorded yet.') }}</h2>
				</section>
			</div>
			<div v-else id="emptycontent">
				<h2>
					{{ t('empleados', 'No movements have been recorded yet.') }}
				</h2>
			</div>
		</div>

		<NcModal
			v-if="modal"
			ref="modalRef"
			:name="t('empleados', 'Export information')"
			@close="closeModal">
			<div class="center">
				<div v-if="exportardata">
					<div>
						<h2>{{ t('empleados', 'Exporting') }}</h2>
						<form class="center" @submit.prevent>
							<NcProgressBar :value="exportardata_value" size="medium" />
						</form>
					</div>
				</div>
				<div v-else>
					<h2>{{ t('empleados', 'Select the period and request type') }}</h2>
					<br>
					<div>
						<NcSelect v-model="export_estado_values"
							class="container__select"
							:input-label="t('empleados', 'status')"
							:options="options_estado"
							required />
					</div>

					<div>
						<NcSelect v-model="export_fechas_value"
							class="container__select"
							:input-label="t('empleados', 'status')"
							:options="options_fechas"
							required />
					</div>
					<br>
					<NcButton class="center" @click="exportar">
						{{ t('empleados', 'export') }}
					</NcButton>
				</div>
			</div>
		</NcModal>

		<NcModal
			v-if="modaldetails"
			ref="modalRef"
			size="large"
			:name="t('empleados', 'View request')"
			@close="closeModaldetails">
			<div class="center">
				<ul>
					<li>
						<NcAvatar :user="historial[index].uid" :display-name="historial[index].uid" :size="100" />
					</li>
					<li>
						<p>{{ historial[index].displayname }}</p>
					</li>
					<li>
						<p>{{ historial[index]['data'] }}</p>
					</li>
					<li style="margin: 25px">
						<NcNoteCard v-if="historial[index].estado == 0" type="info">
							<p>{{ t('empleados', 'This request has not been answered yet') }}</p>
						</NcNoteCard>
					</li>
					<li>
						<ul>
							<NcListItem
								:name="t('empleados', 'Total Savings:')"
								:compact="true"
								one-line
								@click.prevent>
								<template #subname>
									${{ historial[index].cantidad_total }}
								</template>
							</NcListItem>
							<NcListItem
								:name="t('empleados', 'Requested Savings')"
								:compact="true"
								one-line
								@click.prevent>
								<template #subname>
									${{ historial[index].cantidad_solicitada }}
								</template>
							</NcListItem>
							<NcListItem
								:name="t('empleados', 'Request note')"
								:compact="true"
								one-line
								@click.prevent>
								<template #subname>
									{{ historial[index].nota }}
								</template>
							</NcListItem>
						</ul>
					</li>
					<li>
						<div v-if="historial[index].estado == 0" style="display: flex; flex-direction: column; gap: 12px; margin: 10px;">
							<div style="display: flex; gap: 12px;">
								<div style="display: flex; flex-direction: column; gap: 12px; flex: 1">
									<NcButton type="secondary" wide @click="accion('aceptar', historial[index].id_historial, historial[index].id_user)">
										{{ t('empleados', 'ACCEPT') }}
									</NcButton>
								</div>
								<div style="display: flex; flex-direction: column; gap: 12px; flex: 1">
									<NcButton type="error" wide @click="accion('denegar', historial[index].id_historial, historial[index].id_user)">
										{{ t('empleados', 'DENY') }}
									</NcButton>
								</div>
							</div>
						</div>
					</li>
				</ul>
			</div>
		</NcModal>
	</NcAppContent>
</template>

<script>
import {
	NcAppContent,
	NcLoadingIcon,
	NcButton,
	NcListItem,
	NcAvatar,
	NcModal,
	NcSelect,
	NcProgressBar,
	NcNoteCard,
} from '@nextcloud/vue'

import '@nextcloud/dialogs/styles/toast.scss'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import Archive from 'vue-material-design-icons/Archive.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import DatabaseExport from 'vue-material-design-icons/DatabaseExport.vue'
import { ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'PanelAhorros',
	components: {
		NcAppContent,
		NcLoadingIcon,
		NcButton,
		NcListItem,
		Archive,
		NcAvatar,
		NcModal,
		DatabaseExport,
		NcSelect,
		NcProgressBar,
		NcNoteCard,
		Eye,
	},

	setup() {
		return {
			modalRef: ref(null),
		}
	},

	data() {
		return {
			historial: [],
			loading: true,
			userdata: [],
			userdataahorro: [],
			send: false,

			modal: false,
			modaldetails: false,
			singleValue: null,
			exportardata: false,
			exportardata_value: 0,
			index: 0,
			options_fechas_value: new Date().getFullYear(),
			options_estado_values: t('empleados', 'Pendientes'),
			options_fechas: this.generateYears(),
			options_estado: [t('empleados', 'Pendientes'), t('empleados', 'Aprobados')],

			export_estado_values: t('empleados', 'Aprobados'),
			export_fechas_value: new Date().getFullYear(),
		}
	},

	mounted() {
		this.gethistorial()
	},

	methods: {
		// expone t al template si lo prefieres como método (además del import)
		t,

		formatMoney(value) {
			return Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value) || 0)
		},

		async gethistorial() {
			let state
			try {
				state = (this.options_estado_values === t('empleados', 'Pendientes')) ? '0' : '1'
				const response = await axios.get(generateUrl('apps/empleados/GetHistorialPanel/' + this.options_fechas_value + '/' + state))
				if (response?.data?.ocs?.meta?.status !== 'ok') {
					showError(response?.data?.ocs?.meta?.message)
					this.loading = false
					window.location.href = '/apps/empleados/#/'
					return
				}
				this.Empleados = response?.data?.ocs?.data.Empleados
				this.loading = false

				this.historial = response?.data?.ocs?.data
				this.loading = false
			} catch (e) {
				console.error(e)
				showError(t('empleados', 'Could not fetch your information'))
			}
		},

		showModal() {
			this.modal = true
		},
		showModaldetails(index) {
			this.index = index
			this.modaldetails = true
		},
		closeModal() {
			this.modal = false
		},
		closeModaldetails() {
			this.modaldetails = false
		},
		exportar() {
			const state = (this.export_estado_values === t('empleados', 'Pendientes')) ? '0' : '1'
			this.exportardata = true
			axios.get(
				generateUrl('/apps/empleados/GenerateReport/' + this.export_fechas_value + '/' + state),
				{ responseType: 'blob' },
			).then(
				(response) => {
					this.exportardata_value = 10
					const url = URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.ms-excel' }))
					const link = document.createElement('a')
					link.href = url
					link.setAttribute('download', 'historial.xlsx')
					document.body.appendChild(link)
					link.click()
					this.exportardata = false
					this.exportardata_value = 0
				},
				(err) => {
					showError(err)
					this.exportardata = false
				},
			)
		},

		generateYears() {
			const currentYear = new Date().getFullYear()
			const startYear = 2023
			const years = []
			for (let year = startYear; year <= currentYear; year++) {
				years.push(year)
			}
			return years
		},

		accion(accion, idahorro, id) {
			this.modaldetails = false
			this.modal = false

			if (accion === 'aceptar') {
				axios.post(
					generateUrl('/apps/empleados/AceptarAhorro'),
					{ id_ahorro: idahorro, id },
				).then(
					async () => {
						this.send = true
						await this.gethistorial()
						showSuccess(t('empleados', 'Solicitud aceptada'))
					},
					(err) => { showError(err) },
				)
			} else {
				axios.post(
					generateUrl('/apps/empleados/DenegarAhorro'),
					{ id_ahorro: idahorro, id },
				).then(
					async () => {
						this.send = true
						await this.gethistorial()
						showSuccess(t('empleados', 'Solicitud Denegada'))
					},
					(err) => { showError(err) },
				)
			}
		},
	},
}
</script>

<style scoped>
.center-screen {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 100vh;
	text-align: center;
}

.center {
	margin: auto;
	padding: 10px;
}

.panel-page {
	display: grid;
	gap: 14px;
	padding: 20px;
}

.panel-header,
.filters-card,
.request-card,
.empty-state {
	border: 1px solid var(--color-border);
	border-radius: 8px;
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
}

.panel-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 18px;
}

.section-label {
	margin: 0 0 4px;
	color: var(--color-primary-element);
	font-size: 12px;
	font-weight: 700;
	letter-spacing: .04em;
	text-transform: uppercase;
}

.panel-header h2 {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0;
	color: var(--color-main-text);
	font-size: 22px;
}

.panel-header p {
	margin: 6px 0 0;
	color: var(--color-text-maxcontrast);
}

.filters-card {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 16px;
}

.filters-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(180px, 240px));
	gap: 12px;
}

.request-summary {
	display: grid;
	justify-items: end;
	gap: 4px;
	min-width: 130px;
}

.request-summary span,
.request-amount span {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
}

.request-summary strong {
	color: var(--color-main-text);
	font-size: 28px;
	line-height: 1;
}

.request-summary small {
	color: var(--color-text-maxcontrast);
}

.requests-list {
	display: grid;
	gap: 10px;
}

.request-card {
	display: grid;
	grid-template-columns: minmax(240px, 1fr) minmax(140px, auto) auto;
	align-items: center;
	gap: 16px;
	padding: 14px;
}

.request-person {
	display: flex;
	align-items: center;
	gap: 12px;
	min-width: 0;
	cursor: pointer;
}

.request-person strong,
.request-person span {
	display: block;
}

.request-person strong {
	color: var(--color-main-text);
}

.request-person span {
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.request-amount {
	display: grid;
	gap: 4px;
}

.request-amount strong {
	color: var(--color-main-text);
	font-size: 20px;
}

.request-actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: 8px;
}

.empty-state {
	padding: 28px;
	text-align: center;
}

.modal__content {
	margin: 50px;
}

.modal__content h2 {
	text-align: center;
}

@media (max-width: 900px) {
	.panel-page {
		padding: 12px;
	}

	.panel-header,
	.filters-card {
		align-items: flex-start;
		flex-direction: column;
	}

	.filters-grid {
		grid-template-columns: 1fr;
		width: 100%;
	}

	.request-summary {
		justify-items: start;
	}

	.request-card {
		grid-template-columns: 1fr;
	}

	.request-actions {
		justify-content: flex-start;
	}
}
</style>
