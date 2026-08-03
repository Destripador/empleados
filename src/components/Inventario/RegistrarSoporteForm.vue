<template>
	<div class="support-form-shell">
		<NcNoteCard v-if="supportSuccess" type="success" class="support-success">
			<strong>{{ t('empleados', 'Support was registered successfully.') }}</strong>
			<span>{{ t('empleados', 'Recorded time: {duration}', { duration: formattedSuccessDuration }) }}</span>
			<span>{{ t('empleados', 'The time was added to your reports as a non-billable activity.') }}</span>
			<a :href="supportedDeviceUrl">{{ t('empleados', 'View device') }}</a>
		</NcNoteCard>

		<form v-else @submit.prevent="submitSupport">
			<section aria-labelledby="support-device-heading">
				<h3 id="support-device-heading">
					{{ t('empleados', 'Device') }}
				</h3>
				<div v-if="!selectedDevice" class="support-search">
					<NcTextField
						ref="searchField"
						:value.sync="search"
						:label="t('empleados', 'Search device')"
						:disabled="submitting"
						@update:value="scheduleSearch">
						<template #icon>
							<Magnify :size="20" />
						</template>
					</NcTextField>
					<div v-if="searching" class="support-search-state" role="status">
						<NcLoadingIcon :size="24" />
						<span>{{ t('empleados', 'Searching devices') }}</span>
					</div>
					<div v-else-if="searchCompleted && results.length === 0" class="support-search-state">
						{{ t('empleados', 'No results') }}
					</div>
					<div v-else-if="results.length" class="support-results">
						<button v-for="device in results"
							:key="device.id_equipo"
							type="button"
							class="support-result"
							@click="selectDevice(device)">
							<Laptop :size="20" />
							<span><strong>{{ device.nombre_dispositivo || device.nombre_sistema || t('empleados', 'Device') }}</strong><small>{{ deviceSecondary(device) }}</small></span>
						</button>
					</div>
				</div>

				<div v-else class="support-device-card">
					<div class="support-device-main">
						<NcAvatar v-if="selectedDevice.empleado_uid"
							:user="selectedDevice.empleado_uid"
							:display-name="employeeName"
							:show-user-status="false"
							:show-user-status-compact="false"
							:size="42"
							disable-menu />
						<span v-else class="support-neutral-avatar"><AccountOutline :size="24" /></span>
						<div><strong>{{ selectedDevice.nombre_dispositivo || selectedDevice.nombre_sistema }}</strong><small>{{ employeeName }}</small></div>
						<NcButton type="tertiary"
							:aria-label="t('empleados', 'Clear selected device')"
							:disabled="submitting"
							@click="clearDevice">
							<template #icon>
								<Close :size="20" />
							</template>
						</NcButton>
					</div>
					<dl>
						<div v-if="selectedDevice.numero_serie">
							<dt>{{ t('empleados', 'Serial number') }}</dt><dd>{{ selectedDevice.numero_serie }}</dd>
						</div>
						<div v-if="modelName">
							<dt>{{ t('empleados', 'Model') }}</dt><dd>{{ modelName }}</dd>
						</div>
						<div v-if="selectedDevice.estado">
							<dt>{{ t('empleados', 'Status') }}</dt><dd>{{ selectedDevice.estado }}</dd>
						</div>
						<div><dt>{{ t('empleados', 'Responsible') }}</dt><dd>{{ responsibleName }}</dd></div>
					</dl>
				</div>
			</section>

			<section aria-labelledby="support-classification-heading">
				<h3 id="support-classification-heading">
					{{ t('empleados', 'Classification') }}
				</h3>
				<div class="support-fields">
					<label><span>{{ t('empleados', 'Category') }}</span><select v-model="form.categoria" :disabled="submitting"><option value="mantenimiento">{{ t('empleados', 'Maintenance') }}</option><option value="reparacion">{{ t('empleados', 'Repair') }}</option><option value="diagnostico">{{ t('empleados', 'Diagnostics') }}</option><option value="configuracion">{{ t('empleados', 'Configuration') }}</option><option value="otro">{{ t('empleados', 'Other') }}</option></select></label>
					<label><span>{{ t('empleados', 'Priority') }}</span><select v-model="form.prioridad" :disabled="submitting"><option value="baja">{{ t('empleados', 'Low') }}</option><option value="media">{{ t('empleados', 'Medium') }}</option><option value="alta">{{ t('empleados', 'High') }}</option><option value="critica">{{ t('empleados', 'Critical') }}</option></select></label>
				</div>
			</section>

			<section aria-labelledby="support-duration-heading">
				<h3 id="support-duration-heading">
					{{ t('empleados', 'Time spent') }}
				</h3>
				<div class="support-time-grid">
					<SupportDurationFields v-model="form.duracion_minutos" :disabled="submitting" />
					<label class="support-date"><span>{{ t('empleados', 'Support date') }}</span><input v-model="form.fecha" type="datetime-local" :disabled="submitting"></label>
				</div>
			</section>

			<section aria-labelledby="support-description-heading">
				<h3 id="support-description-heading">
					{{ t('empleados', 'Description') }}
				</h3>
				<NcTextArea :value.sync="form.descripcion" :label="t('empleados', 'Description')" :disabled="submitting" />
			</section>

			<div class="support-actions">
				<NcButton type="secondary" :disabled="submitting" @click="$emit('cancel')">
					{{ t('empleados', 'Cancel') }}
				</NcButton>
				<NcButton type="primary" native-type="submit" :disabled="!formValid">
					<template #icon>
						<NcLoadingIcon v-if="submitting" :size="20" /><Wrench v-else :size="20" />
					</template>{{ submitting ? t('empleados', 'Registering') : t('empleados', 'Register support') }}
				</NcButton>
			</div>
		</form>
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { NcAvatar, NcButton, NcLoadingIcon, NcNoteCard, NcTextArea, NcTextField } from '@nextcloud/vue'
import AccountOutline from 'vue-material-design-icons/AccountOutline.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Laptop from 'vue-material-design-icons/Laptop.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Wrench from 'vue-material-design-icons/Wrench.vue'
import inventarioService from '../../services/inventarioService.js'
import { formatSupportDuration, isValidSupportDate } from '../../utils/supportDuration.js'
import SupportDurationFields from './SupportDurationFields.vue'

function localDateTimeValue() {
	const now = new Date()
	now.setMinutes(now.getMinutes() - now.getTimezoneOffset())
	return now.toISOString().slice(0, 16)
}

function initialForm() {
	return { categoria: 'mantenimiento', prioridad: 'media', descripcion: '', fecha: localDateTimeValue(), duracion_minutos: null }
}

export default {
	name: 'RegistrarSoporteForm',
	components: { AccountOutline, Close, Laptop, Magnify, NcAvatar, NcButton, NcLoadingIcon, NcNoteCard, NcTextArea, NcTextField, SupportDurationFields, Wrench },
	data() {
		return { search: '', searching: false, searchCompleted: false, results: [], searchTimer: null, searchSequence: 0, selectedDevice: null, submitting: false, supportSuccess: null, form: initialForm() }
	},
	computed: {
		employeeName() { return this.selectedDevice?.empleado_displayname || this.selectedDevice?.empleado_uid || t('empleados', 'Unassigned') },
		modelName() { return [this.selectedDevice?.marca, this.selectedDevice?.modelo].filter(Boolean).join(' ') },
		responsibleName() { const user = getCurrentUser(); return user?.displayName || user?.uid || t('empleados', 'Current user') },
		formValid() { return this.selectedDevice !== null && this.form.categoria !== '' && this.form.prioridad !== '' && this.form.descripcion.trim() !== '' && Number.isInteger(this.form.duracion_minutos) && this.form.duracion_minutos > 0 && isValidSupportDate(this.form.fecha) && !this.submitting },
		formattedSuccessDuration() { return formatSupportDuration(this.supportSuccess?.duracion_minutos) },
		supportedDeviceUrl() { return this.supportSuccess?.id_equipo ? `${generateUrl('/apps/empleados/')}#/Inventario?deviceId=${encodeURIComponent(this.supportSuccess.id_equipo)}` : '' },
	},
	beforeDestroy() { this.cancelSearch() },
	methods: {
		t,
		focusInitialField() { this.$refs.searchField?.$el?.querySelector('input')?.focus() },
		scheduleSearch() { if (this.searchTimer) clearTimeout(this.searchTimer); const query = this.search.trim(); if (query.length < 2) { this.cancelSearch(); this.searchCompleted = false; this.results = []; return } const sequence = ++this.searchSequence; this.searchTimer = setTimeout(() => this.searchDevices(query, sequence), 400) },
		async searchDevices(query, sequence) { if (sequence !== this.searchSequence || query !== this.search.trim()) return; this.searching = true; this.searchCompleted = false; try { const response = await inventarioService.getEquipos({ search: query, limit: 8, offset: 0 }); if (sequence !== this.searchSequence) return; this.results = this.normalizeDevices(response?.data).slice(0, 8); this.searchCompleted = true } catch (error) { if (sequence !== this.searchSequence) return; this.results = []; this.searchCompleted = true; showError(t('empleados', 'Devices could not be loaded.')) } finally { if (sequence === this.searchSequence) this.searching = false } },
		cancelSearch() { if (this.searchTimer) clearTimeout(this.searchTimer); this.searchTimer = null; this.searchSequence += 1; this.searching = false },
		normalizeDevices(value) { if (Array.isArray(value)) return value; if (value && typeof value === 'object') return Object.values(value); return [] },
		deviceSecondary(device) { return [device.nombre_sistema, device.numero_serie, device.empleado_displayname || device.empleado_uid].filter(Boolean).join(' · ') },
		selectDevice(device) { if (this.submitting) return; this.cancelSearch(); this.selectedDevice = { ...device }; this.results = []; this.searchCompleted = false },
		clearDevice() { if (this.submitting) return; this.selectedDevice = null; this.search = ''; this.results = []; this.searchCompleted = false },
		resetAfterSuccess() { this.selectedDevice = null; this.search = ''; this.results = []; this.searchCompleted = false; this.form = initialForm() },
		async submitSupport() {
			if (!this.formValid || this.submitting) return
			this.submitting = true
			const payload = { id_equipo: Number(this.selectedDevice.id_equipo), categoria: this.form.categoria, prioridad: this.form.prioridad, detalles: this.form.descripcion.trim(), fecha: this.form.fecha, duracion_minutos: this.form.duracion_minutos }
			try {
				const response = await inventarioService.crearSoporte(payload)
				const result = response?.data ?? response
				this.supportSuccess = { id_soporte: Number(result?.id_soporte), id_reporte: Number(result?.id_reporte), id_equipo: Number(result?.id_equipo || payload.id_equipo), duracion_minutos: Number(result?.duracion_minutos || payload.duracion_minutos), duplicado: Boolean(result?.duplicado) }
				this.resetAfterSuccess()
				showSuccess(t('empleados', 'Support was registered successfully.'))
				this.$emit('success', this.supportSuccess)
			} catch (error) {
				const data = error?.response?.data?.ocs?.data ?? error?.response?.data
				showError(data?.message || t('empleados', 'Support could not be registered.'))
			} finally { this.submitting = false }
		},
	},
}
</script>

<style scoped lang="scss">
.support-form-shell form,
.support-form-shell section,
.support-search { display: grid; gap: 12px; }
.support-form-shell form { gap: 20px; }
.support-form-shell h3 { margin: 0; font-size: 16px; }
.support-search-state { display: flex; gap: 8px; align-items: center; justify-content: center; min-height: 44px; color: var(--color-text-maxcontrast); }
.support-results { display: grid; gap: 4px; max-height: min(240px, 32vh); padding: 4px; overflow-y: auto; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); }
.support-result { display: flex; gap: 10px; align-items: center; width: 100%; min-height: 48px; padding: 8px 10px; border: 0; border-radius: var(--border-radius-large); background: transparent; color: var(--color-main-text); cursor: pointer; text-align: start; }
.support-result:hover, .support-result:focus-visible { background: var(--color-background-hover); outline: 2px solid var(--color-primary-element); }
.support-result span, .support-result strong, .support-result small { display: block; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.support-result span { flex: 1; }
.support-result small, .support-device-main small { color: var(--color-text-maxcontrast); }
.support-device-card { display: grid; gap: 10px; padding: 12px; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); background: var(--color-background-hover); }
.support-device-main { display: flex; gap: 10px; align-items: center; }
.support-device-main > div { display: grid; flex: 1; min-width: 0; }
.support-device-main strong, .support-device-main small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.support-neutral-avatar { display: grid; flex: 0 0 42px; place-items: center; width: 42px; height: 42px; border-radius: 50%; background: var(--color-background-dark); color: var(--color-text-maxcontrast); }
.support-device-card dl { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px 12px; margin: 0; }
.support-device-card dt { color: var(--color-text-maxcontrast); font-size: 11px; }
.support-device-card dd { margin: 2px 0 0; overflow: hidden; font-size: 12px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.support-fields, .support-time-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.support-fields label, .support-date { display: grid; gap: 4px; }
.support-fields span, .support-date span { color: var(--color-text-maxcontrast); font-size: 12px; font-weight: 700; }
.support-fields select, .support-date input { width: 100%; min-height: 44px; padding: 6px 10px; border: 1px solid var(--color-border-maxcontrast); border-radius: var(--border-radius-large); background: var(--color-main-background); color: var(--color-main-text); }
.support-form-shell :deep(textarea) { min-height: 96px; resize: vertical; }
.support-actions { display: flex; gap: 10px; justify-content: flex-end; padding-top: 16px; border-top: 1px solid var(--color-border); }
.support-success :deep(.notecard__content) { display: grid; gap: 6px; }
.support-success a { width: fit-content; font-weight: 700; }
@media (max-width: 600px) { .support-fields, .support-time-grid, .support-device-card dl { grid-template-columns: 1fr; } .support-actions { flex-direction: column-reverse; } .support-actions button { width: 100%; } }
</style>
