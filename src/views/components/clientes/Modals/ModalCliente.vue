<template>
	<NcModal v-if="open"
		ref="modalRef"
		:name="modalTitle"
		@close="$emit('close')">
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

			<div v-if="canEditLogo" class="logo-editor">
				<ClienteLogo
					:id="selectedClient.id"
					:logo="selectedClient.logo"
					:bust="logoBust"
					size="lg"
					:alt="selectedClient.nombre || t('empleados', 'Customer')" />
				<div class="logo-actions">
					<NcButton type="tertiary" @click="$emit('trigger-logo-upload')">
						{{ selectedClient.logo ? t('empleados', 'Replace logo') : t('empleados', 'Add logo') }}
					</NcButton>
					<NcButton v-if="selectedClient.logo" type="tertiary" @click="$emit('remove-logo')">
						{{ t('empleados', 'Remove logo') }}
					</NcButton>
				</div>
			</div>

			<div class="form-grid">
				<NcTextField required
					class="span-2"
					:value="form.nombre"
					:label="t('empleados', 'Company or group name')"
					@update:value="form.nombre = $event" />

				<NcTextArea class="span-2"
					:value="form.detalles"
					:label="t('empleados', 'Details')"
					:rows="3"
					@update:value="form.detalles = $event" />

				<NcTextField :value="form.razon_social"
					:label="t('empleados', 'Business name')"
					@update:value="form.razon_social = $event" />

				<NcTextField :value="form.correo"
					:label="t('empleados', 'Email')"
					@update:value="form.correo = $event" />

				<NcTextField :value="form.rfc"
					:label="t('empleados', 'RFC')"
					@update:value="form.rfc = $event" />

				<NcTextField :value="form.nombre_contacto"
					:label="t('empleados', 'Primary contact')"
					@update:value="form.nombre_contacto = $event" />

				<NcTextField :value="form.telefono"
					:label="t('empleados', 'Phone number')"
					@update:value="form.telefono = $event" />

				<NcTextField class="span-2"
					:value="form.ubicacion"
					:label="t('empleados', 'Location')"
					@update:value="form.ubicacion = $event" />

				<NcSelect :value="form.lider_proyecto"
					:input-label="t('empleados', 'Project leader')"
					:options="projectManagers"
					:clearable="true"
					label="label"
					track-by="value"
					@input="form.lider_proyecto = $event" />

				<NcSelect :value="form.colaboradores"
					:options="collaboratorOptions"
					:multiple="true"
					label="label"
					track-by="value"
					:placeholder="t('empleados', 'Collaborators')"
					class="aligned-select"
					@input="form.colaboradores = $event" />

				<div class="special-client-card span-2">
					<NcCheckboxRadioSwitch :checked="form.especial"
						type="switch"
						@update:checked="form.especial = $event" />
					<div class="special-client-info">
						<h3>{{ t('empleados', 'Special Client') }}</h3>
						<p>
							{{ t('empleados', 'Enable this option for special handling clients.') }}
						</p>
					</div>
				</div>

				<div class="special-client-card span-2">
					<NcCheckboxRadioSwitch :checked="form.estado"
						type="switch"
						@update:checked="form.estado = $event" />
					<div class="special-client-info">
						<h3>{{ t('empleados', 'Active') }}</h3>
						<p>
							{{ t('empleados', 'Disable to deactivate this client without deleting it.') }}
						</p>
					</div>
				</div>

				<NcSelect :key="parentOptions.length"
					:value="form.cliente_padre"
					class="span-2"
					:input-label="t('empleados', 'Parent group')"
					:options="parentOptions"
					:clearable="true"
					label="label"
					track-by="id"
					@input="form.cliente_padre = $event" />

				<NcNoteCard type="info" class="span-2">
					{{ t('empleados', 'Leave parent group empty to create a main group. Select a parent to create a sub-company.') }}
				</NcNoteCard>
			</div>

			<div class="modal-actions">
				<NcButton @click="$emit('close')">
					{{ t('empleados', 'Cancel') }}
				</NcButton>

				<NcButton type="primary" :disabled="!isFormValid || saving" @click="handleSave">
					{{ saving ? t('empleados', 'Saving...') : saveLabel }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import {
	NcModal,
	NcTextField,
	NcTextArea,
	NcButton,
	NcSelect,
	NcCheckboxRadioSwitch,
	NcNoteCard,
} from '@nextcloud/vue'

import ClienteLogo from '../../../../components/clientes/ClienteLogo.vue'

export default {
	name: 'ModalCliente',

	components: {
		NcModal,
		NcTextField,
		NcTextArea,
		NcButton,
		NcSelect,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		ClienteLogo,
	},

	props: {
		open: { type: Boolean, default: false },
		editing: { type: Boolean, default: false },
		saving: { type: Boolean, default: false },
		modalTitle: { type: String, required: true },
		saveLabel: { type: String, required: true },
		selectedClient: { type: Object, default: () => ({}) },
		canEditLogo: { type: Boolean, default: false },
		logoBust: { type: [Number, String], default: 0 },
		projectManagers: { type: Array, default: () => [] },
		parentOptions: { type: Array, default: () => [] },
	},

	data() {
		return {
			form: this.emptyForm(),
		}
	},

	computed: {
		isFormValid() {
			return String(this.form.nombre || '').trim().length > 0
		},

		collaboratorOptions() {
			if (!this.form.lider_proyecto) {
				return this.projectManagers
			}

			const leaderId = Number(this.form.lider_proyecto.value ?? this.form.lider_proyecto)

			return this.projectManagers.filter(
				emp => Number(emp.value) !== leaderId,
			)
		},
	},

	watch: {
		open(newVal) {
			if (newVal) {
				this.initForm()
			}
		},
	},

	created() {
		this.initForm()
	},

	methods: {
		t,

		emptyForm() {
			return {
				nombre: '',
				detalles: '',
				razon_social: '',
				nombre_contacto: '',
				telefono: '',
				correo: '',
				rfc: '',
				ubicacion: '',
				lider_proyecto: null,
				colaboradores: [],
				especial: false,
				estado: true,
				cliente_padre: null,
			}
		},

		initForm() {
			if (this.editing && this.selectedClient?.id) {
				this.form = this.formFromClient(this.selectedClient)
			} else {
				this.form = this.emptyForm()
			}
		},

		formFromClient(client) {
			const colabs = Array.isArray(client.colaboradores) ? client.colaboradores : []
			const parentId = client.cliente_padre || 0

			return {
				nombre: client.nombre || '',
				detalles: client.detalles || '',
				razon_social: client.razon_social || '',
				nombre_contacto: client.nombre_contacto || '',
				telefono: client.telefono || '',
				correo: client.correo || '',
				rfc: client.rfc || '',
				ubicacion: client.ubicacion || '',
				especial: Boolean(Number(client.especial)),
				estado: Boolean(Number(client.estado ?? 1)),
				lider_proyecto: this.projectManagers.find(
					(emp) => Number(emp.value) === Number(client.lider_proyecto),
				) || null,
				colaboradores: this.projectManagers.filter(emp =>
					colabs.includes(emp.value) || colabs.includes(String(emp.value)),
				),
				cliente_padre: Number(parentId) === 0
					? null
					: this.parentOptions.find(o => Number(o.value ?? o.id) === Number(parentId)) || null,
			}
		},

		getPayload() {
			return {
				nombre: String(this.form.nombre || '').trim(),
				razon_social: String(this.form.razon_social || '').trim(),
				lider_proyecto: this.form.lider_proyecto?.value ?? this.form.lider_proyecto ?? null,
				colaboradores: JSON.stringify(
					Array.isArray(this.form.colaboradores)
						? this.form.colaboradores.map(c => c.value ?? c)
						: [],
				),
				nombre_contacto: String(this.form.nombre_contacto || '').trim(),
				telefono: String(this.form.telefono || '').trim(),
				correo: String(this.form.correo || '').trim(),
				rfc: String(this.form.rfc || '').trim(),
				ubicacion: String(this.form.ubicacion || '').trim(),
				detalles: String(this.form.detalles || '').trim(),
				especial: this.form.especial ? 1 : 0,
				cliente_padre: this.form.cliente_padre?.value ?? this.form.cliente_padre?.id ?? null,
				estado: this.form.estado ? 1 : 0,
			}
		},

		handleSave() {
			if (!this.isFormValid || this.saving) {
				return
			}
			this.$emit('save', this.getPayload())
		},
	},
}
</script>

<style scoped lang="scss">
@import './modales-comunes.scss';
</style>
