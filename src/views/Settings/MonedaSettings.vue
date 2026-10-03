<template>
	<div class="monedas-y-tipo-cambio">
		<div v-if="loading">
			<div class="center-screen">
				<NcLoadingIcon :size="64" appearance="dark" :name="t('empleados', 'Loading...')" />
			</div>
		</div>
		<div v-else class="card">
			<div class="card-header">
				<h3 class="card-title">
					{{ t('empleados', 'Monedas') }}
				</h3>

				<NcButton type="primary" @click="abrirModalNueva">
					<template #icon>
						<PlusIcon :size="18" />
					</template>
					{{ t('empleados', 'Agregar moneda') }}
				</NcButton>
			</div>

			<div class="scroll-container">
				<p v-if="monedas.length === 0" class="empty">
					<CurrencyUsdIcon :size="40" fill-color="#c4c4c4" />
					<span>{{ t('empleados', 'No hay monedas registradas') }}</span>
				</p>

				<table v-else class="tabla-monedas">
					<thead>
						<tr>
							<th>{{ t('empleados', 'Type') }}</th>
							<th>{{ t('empleados', 'Serie Banxico') }}</th>
							<th class="col-acciones" />
						</tr>
					</thead>
					<tbody>
						<tr v-for="moneda in monedas" :key="moneda.id">
							<td>
								<span class="badge-tipo">{{ moneda.tipoMoneda }}</span>
							</td>
							<td>{{ moneda.serie }}</td>
							<td class="col-acciones">
								<NcActions :force-menu="true">
									<template #icon>
										<DotsVerticalIcon :size="20" />
									</template>
									<NcActionButton @click="abrirModalEditar(moneda)">
										<template #icon>
											<PencilOutlineIcon :size="18" />
										</template>
										{{ t('empleados', 'Edit') }}
									</NcActionButton>
									<NcActionButton @click="confirmarEliminar(moneda)">
										<template #icon>
											<DeleteOutlineIcon :size="18" />
										</template>
										{{ t('empleados', 'Delete') }}
									</NcActionButton>
								</NcActions>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Modal crear / editar -->
			<NcModal v-if="modalAbierto" size="small" @close="cerrarModal">
				<div class="modal-form">
					<div ref="focusTrap" tabindex="-1" class="focus-trap-inicial" />

					<h3 class="modal-titulo">
						{{ modoEdicion ? t('empleados', 'Editar moneda') : t('empleados', 'Agregar moneda') }}
					</h3>

					<div class="filtro-campo">
						<label>{{ t('empleados', 'Tipo de moneda') }}</label>
						<input
							v-model="form.tipoMoneda"
							type="text"
							class="input-texto"
							maxlength="10"
							placeholder="USD"
							@input="form.tipoMoneda = form.tipoMoneda.toUpperCase()">
					</div>

					<div class="filtro-campo">
						<label>{{ t('empleados', 'Serie Banxico') }}</label>
						<input
							v-model="form.serie"
							type="text"
							class="input-texto"
							maxlength="20"
							placeholder="SF43718">
					</div>

					<p v-if="errorForm" class="error-form">
						{{ errorForm }}
					</p>

					<div class="modal-acciones">
						<NcButton type="tertiary" @click="cerrarModal">
							{{ t('empleados', 'Cancel') }}
						</NcButton>
						<NcButton type="primary" :disabled="guardando" @click="guardar">
							{{ guardando ? t('empleados', 'Saving...') : t('empleados', 'Save') }}
						</NcButton>
					</div>
				</div>
			</NcModal>

			<!-- Modal confirmar eliminación -->
			<NcModal v-if="monedaAEliminar" size="small" @close="monedaAEliminar = null">
				<div class="modal-form">
					<h3 class="modal-titulo">
						{{ t('empleados', 'Eliminar moneda') }}
					</h3>
					<p>
						{{ t('empleados', '¿Seguro que deseas eliminar la moneda {tipo}? Esta acción no se puede deshacer.', { tipo: monedaAEliminar.tipoMoneda }) }}
					</p>
					<div class="modal-acciones">
						<NcButton type="tertiary" @click="monedaAEliminar = null">
							{{ t('empleados', 'Cancel') }}
						</NcButton>
						<NcButton
							class="btn-eliminar"
							:disabled="eliminando"
							@click="eliminar">
							{{ eliminando ? t('empleados', 'Eliminando...') : t('empleados', 'Eliminar') }}
						</NcButton>
					</div>
				</div>
			</NcModal>
		</div>

		<TipoCambioSettings v-if="!loading" :monedas="monedas" />
	</div>
</template>

<script>
import { NcLoadingIcon, NcModal, NcButton, NcActions, NcActionButton } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import PlusIcon from 'vue-material-design-icons/Plus.vue'
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/DeleteOutline.vue'
import DotsVerticalIcon from 'vue-material-design-icons/DotsVertical.vue'
import CurrencyUsdIcon from 'vue-material-design-icons/CurrencyUsd.vue'

import TipoCambioSettings from './TipoCambioSettings.vue'

const formVacio = () => ({
	id: null,
	tipoMoneda: '',
	serie: '',
})

export default {
	name: 'MonedaSettings',
	components: {
		NcLoadingIcon,
		NcModal,
		NcButton,
		NcActions,
		NcActionButton,
		PlusIcon,
		PencilOutlineIcon,
		DeleteOutlineIcon,
		DotsVerticalIcon,
		CurrencyUsdIcon,
		TipoCambioSettings,
	},
	data() {
		return {
			loading: true,
			monedas: [],

			modalAbierto: false,
			modoEdicion: false,
			form: formVacio(),
			errorForm: '',
			guardando: false,

			monedaAEliminar: null,
			eliminando: false,
		}
	},
	async mounted() {
		await this.fetchMonedas()
		this.loading = false
	},
	methods: {
		t,
		async fetchMonedas() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetMonedas'))
				this.monedas = response?.data ?? []
			} catch (err) {
				showError(t('empleados', 'Error al cargar las monedas'))
			}
		},
		abrirModalNueva() {
			this.modoEdicion = false
			this.form = formVacio()
			this.errorForm = ''
			this.modalAbierto = true
		},
		async abrirModalEditar(moneda) {
			this.modoEdicion = true
			this.form = { id: moneda.id, tipoMoneda: moneda.tipoMoneda, serie: moneda.serie }
			this.errorForm = ''
			this.modalAbierto = true
			await this.$nextTick()
			this.$refs.focusTrap?.focus()
		},
		cerrarModal() {
			this.modalAbierto = false
			this.form = formVacio()
			this.errorForm = ''
		},
		async guardar() {
			this.errorForm = ''

			if (!this.form.tipoMoneda.trim() || !this.form.serie.trim()) {
				this.errorForm = t('empleados', 'Tipo de moneda y serie son obligatorios')
				return
			}

			this.guardando = true
			try {
				if (this.modoEdicion) {
					await axios.post(generateUrl('/apps/empleados/ModificarMoneda'), {
						id: this.form.id,
						tipoMoneda: this.form.tipoMoneda,
						serie: this.form.serie,
					})
					showSuccess(t('empleados', 'Moneda actualizada'))
				} else {
					await axios.post(generateUrl('/apps/empleados/AgregarMoneda'), {
						tipoMoneda: this.form.tipoMoneda,
						serie: this.form.serie,
					})
					showSuccess(t('empleados', 'Moneda agregada'))
				}

				this.cerrarModal()
				await this.fetchMonedas()
			} catch (err) {
				this.errorForm = err?.response?.data?.error || t('empleados', 'Error al guardar la moneda')
			} finally {
				this.guardando = false
			}
		},
		confirmarEliminar(moneda) {
			this.monedaAEliminar = moneda
		},
		async eliminar() {
			if (!this.monedaAEliminar) return

			this.eliminando = true
			try {
				await axios.post(generateUrl('/apps/empleados/EliminarMoneda'), {
					id: this.monedaAEliminar.id,
				})
				showSuccess(t('empleados', 'Moneda eliminada'))
				this.monedaAEliminar = null
				await this.fetchMonedas()
			} catch (err) {
				showError(err?.response?.data?.error || t('empleados', 'Error al eliminar la moneda'))
			} finally {
				this.eliminando = false
			}
		},
	},
}
</script>

<style scoped>
.card {
	max-width: 700px;
	margin: 0 auto;
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-main-background);
	overflow: hidden;
}

.card-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 14px 16px;
	border-bottom: 1px solid var(--color-border);
}

.card-title {
	margin: 0;
	font-size: 1rem;
	font-weight: 700;
	color: var(--color-main-text);
}

.scroll-container {
	max-height: 420px;
	overflow-y: auto;
	padding: 6px 10px;
}

.empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 10px;
	text-align: center;
	color: var(--color-text-maxcontrast);
	padding: 50px 20px;
	font-size: 0.95rem;
}

.center-screen {
	display: flex;
	justify-content: center;
	align-items: center;
	min-height: 40vh;
}

/* ---------- Tabla ---------- */
.tabla-monedas {
	width: 100%;
	border-collapse: collapse;
}

.tabla-monedas th {
	text-align: left;
	font-size: 0.78rem;
	text-transform: uppercase;
	letter-spacing: 0.03em;
	color: var(--color-text-maxcontrast);
	padding: 10px 12px;
	border-bottom: 1px solid var(--color-border);
}

.tabla-monedas td {
	padding: 12px;
	border-bottom: 1px solid var(--color-border);
	font-size: 0.92rem;
	color: var(--color-main-text);
}

.tabla-monedas tr:last-child td {
	border-bottom: none;
}

.tabla-monedas tr:hover td {
	background-color: var(--color-background-hover);
}

.col-acciones {
	width: 44px;
	text-align: right;
}

.badge-tipo {
	display: inline-block;
	padding: 3px 15px;
	border-radius: 20px;
	background: #0b4905;
	color: var(--color-primary-element-text);
	font-weight: 650;
	font-size: .9rem;
	letter-spacing: 0.02em;
}

/* ---------- Modales ---------- */
.modal-form {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 16px;
	min-width: 320px;
}

.modal-titulo {
	margin: 0;
	font-size: 1.1rem;
	font-weight: 700;
	color: var(--color-main-text);
}

.filtro-campo {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.filtro-campo label {
	font-size: 0.8rem;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.input-texto {
	height: 42px;
	border-radius: var(--border-radius, 6px);
	border: 1px solid var(--color-border-maxcontrast);
	background: var(--color-main-background);
	color: var(--color-main-text);
	padding: 0 14px;
	font-size: 0.95rem;
	width: 100%;
	box-sizing: border-box;
}

.input-texto:focus {
	border-color: var(--color-primary-element);
	outline: none;
}

.error-form {
	color: var(--color-error);
	font-size: 0.85rem;
	margin: 0;
}

.focus-trap-inicial {
	position: absolute;
	width: 1px;
	height: 1px;
	overflow: hidden;
	outline: none;
}

.modal-acciones {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 8px;
}

.btn-eliminar {
	background-color: #c62828 !important;
	color: white !important;
}

.btn-eliminar:hover:not(:disabled) {
	background-color: #a61f1f !important;
}
</style>
