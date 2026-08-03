<template>
	<div class="top">
		<!-- Marital status -->
		<div class="label-input-trabajo">
			<NcSelect
				v-model="Estado_civil"
				class="select"
				:disabled="!show"
				:input-label="t('empleados', 'Marital status')"
				:options="EstadoCiviloptions" />
		</div>

		<!-- Gender -->
		<div class="label-input-trabajo">
			<NcSelect
				v-model="Genero"
				class="select"
				:disabled="!show"
				:input-label="t('empleados', 'Gender')"
				:options="GeneroOptions" />
		</div>

		<!-- Contact phone -->
		<div class="external-label">
			<label for="Telefono_contacto" class="labeltype">
				<Badgeaccountoutline :size="20" />
				{{ t('empleados', 'Contact number') }}
			</label>
			<input
				id="Telefono_contacto"
				v-model="Telefono_contacto"
				type="text"
				:disabled="!show"
				class="inputtype">
		</div>

		<br>

		<!-- Address -->
		<div class="external-label field-wide">
			<label for="Direccion" class="labeltype">
				<MapMarkerOutline :size="20" />
				{{ t('empleados', 'Address') }}
			</label>
			<input
				id="Direccion"
				v-model="Direccion"
				type="text"
				:disabled="!show"
				class="inputtype">
		</div>

		<!-- RFC -->
		<div class="external-label">
			<label for="Rfc" class="labeltype">
				<Badgeaccountoutline :size="20" />
				{{ t('empleados', 'RFC') }}
			</label>
			<input
				id="Rfc"
				v-model="Rfc"
				type="text"
				:disabled="!show"
				class="inputtype">
		</div>

		<!-- IMSS -->
		<div class="external-label">
			<label for="Imss" class="labeltype">
				<Badgeaccountoutline :size="20" />
				{{ t('empleados', 'IMSS') }}
			</label>
			<input
				id="Imss"
				v-model="Imss"
				type="text"
				:disabled="!show"
				class="inputtype">
		</div>

		<!-- CURP -->
		<div class="external-label">
			<label for="Curp" class="labeltype">
				<Badgeaccountoutline :size="20" />
				{{ t('empleados', 'CURP') }}
			</label>
			<input
				id="Curp"
				v-model="Curp"
				type="text"
				:disabled="!show"
				class="inputtype">
		</div>

		<!-- Birth date -->
		<div class="external-label">
			<label for="Fecha_nacimiento" class="labeltype">
				<CakeVariantOutline :size="20" />
				{{ t('empleados', 'Birth date') }}
			</label>
			<input
				id="Fecha_nacimiento"
				v-model="Fecha_nacimiento"
				type="date"
				:disabled="!show"
				class="inputtype">
		</div>

		<!-- Email -->
		<div class="external-label">
			<label for="Correo_contacto" class="labeltype">
				<EmailOutline :size="20" />
				{{ t('empleados', 'Email') }}
			</label>
			<input
				id="Correo_contacto"
				v-model="Correo_contacto"
				type="email"
				:disabled="!show"
				class="inputtype">
		</div>

		<br>

		<section class="emergency-contacts">
			<div class="section-heading">
				<div>
					<h3>{{ t('empleados', 'Emergency contacts') }}</h3>
					<p>{{ t('empleados', 'People to contact in case of an emergency.') }}</p>
				</div>
				<NcButton v-if="show" type="secondary" @click="openContactDialog()">
					{{ t('empleados', 'Add contact') }}
				</NcButton>
			</div>
			<NcLoadingIcon v-if="loadingContacts" :size="32" />
			<p v-else-if="contacts.length === 0" class="empty-state">
				{{ t('empleados', 'No emergency contacts registered.') }}
			</p>
			<div v-else class="contact-grid">
				<article v-for="contact in contacts" :key="contact.id" class="contact-card">
					<div class="contact-title">
						<div><strong>{{ contact.nombre }}</strong><span>{{ contact.relacion }}</span></div>
						<span v-if="contact.es_principal" class="primary-badge">{{ t('empleados', 'Primary contact') }}</span>
					</div>
					<dl>
						<div><dt>{{ t('empleados', 'Contact number') }}</dt><dd>{{ contact.numero_contacto }}</dd></div>
						<div v-if="contact.tipo_ayuda">
							<dt>{{ t('empleados', 'Help type') }}</dt><dd>{{ contact.tipo_ayuda }}</dd>
						</div>
						<div v-if="contact.medio_alternativo">
							<dt>{{ t('empleados', 'Alternative contact method') }}</dt><dd>{{ contact.medio_alternativo }}</dd>
						</div>
						<div v-if="contact.notas">
							<dt>{{ t('empleados', 'Notes') }}</dt><dd>{{ contact.notas }}</dd>
						</div>
					</dl>
					<div v-if="show" class="contact-actions">
						<NcButton type="tertiary" @click="openContactDialog(contact)">
							{{ t('empleados', 'Edit') }}
						</NcButton>
						<NcButton v-if="!contact.es_principal" type="tertiary" @click="markPrimary(contact)">
							{{ t('empleados', 'Mark as primary') }}
						</NcButton>
						<NcButton type="error" @click="confirmDelete(contact)">
							{{ t('empleados', 'Delete') }}
						</NcButton>
					</div>
				</article>
			</div>
		</section>

		<br>

		<!-- Apply changes -->
		<div class="div-center">
			<NcButton
				v-if="show"
				:aria-label="t('empleados', 'Apply changes')"
				type="primary"
				@click="CambiosPersonal">
				{{ t('empleados', 'Apply changes') }}
			</NcButton>
		</div>

		<NcDialog :open.sync="showContactDialog"
			is-form
			:buttons="contactDialogButtons"
			:name="editingContact ? t('empleados', 'Edit emergency contact') : t('empleados', 'Add emergency contact')"
			@submit="saveContact">
			<div class="contact-form">
				<label>{{ t('empleados', 'Full name') }} *<input v-model="contactForm.nombre"
					class="inputtype"
					maxlength="200"
					required></label>
				<label>{{ t('empleados', 'Relationship') }} *<input v-model="contactForm.relacion"
					class="inputtype"
					maxlength="120"
					required></label>
				<label>{{ t('empleados', 'Contact number') }} *<input v-model="contactForm.numero_contacto"
					class="inputtype"
					maxlength="80"
					required></label>
				<label>{{ t('empleados', 'Alternative contact method') }}<input v-model="contactForm.medio_alternativo" class="inputtype" maxlength="255"></label>
				<label>{{ t('empleados', 'Help type') }}<input v-model="contactForm.tipo_ayuda"
					class="inputtype"
					maxlength="255"
					:placeholder="t('empleados', 'For example: medical contact or transportation')"></label>
				<label class="form-wide">{{ t('empleados', 'Notes') }}<textarea v-model="contactForm.notas" class="inputtype contact-notes" maxlength="2000" /></label>
				<NcCheckboxRadioSwitch v-model="contactForm.es_principal" class="form-wide" type="switch">
					{{ t('empleados', 'Primary contact') }}
				</NcCheckboxRadioSwitch>
				<p v-if="formError" class="form-error form-wide">
					{{ formError }}
				</p>
			</div>
		</NcDialog>
		<NcDialog :open.sync="showDeleteDialog"
			:name="t('empleados', 'Delete emergency contact?')"
			:message="t('empleados', 'This emergency contact will be permanently deleted.')"
			:buttons="deleteDialogButtons" />
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import 'vue-nav-tabs/themes/vue-tabs.css'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

// ICONOS
import EmailOutline from 'vue-material-design-icons/EmailOutline.vue'
import Badgeaccountoutline from 'vue-material-design-icons/BadgeAccountOutline.vue'
import MapMarkerOutline from 'vue-material-design-icons/MapMarkerOutline.vue'
import CakeVariantOutline from 'vue-material-design-icons/CakeVariantOutline.vue'

import {
	NcButton,
	NcCheckboxRadioSwitch,
	NcDialog,
	NcLoadingIcon,
	NcSelect,
} from '@nextcloud/vue'

const emptyContact = () => ({ nombre: '', relacion: '', numero_contacto: '', medio_alternativo: '', tipo_ayuda: '', notas: '', es_principal: false })

export default {
	name: 'PersonalTab',

	components: {
		Badgeaccountoutline,
		MapMarkerOutline,
		CakeVariantOutline,
		EmailOutline,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcLoadingIcon,
		NcSelect,
	},

	props: {
		data: {
			type: Object,
			required: true,
		},
		show: {
			type: Boolean,
			required: true,
		},
		empleados: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
			contacts: [],
			loadingContacts: false,
			savingContact: false,
			showContactDialog: false,
			showDeleteDialog: false,
			editingContact: null,
			deletingContact: null,
			contactForm: emptyContact(),
			formError: '',
			Direccion: '',
			Estado_civil: '',
			Telefono_contacto: '',
			Rfc: '',
			Imss: '',
			Contacto_emergencia: '',
			Numero_emergencia: '',
			Curp: '',
			Fecha_nacimiento: '',
			Correo_contacto: '',
			Genero: '',
			// Opciones traducidas (keys en inglés)
			GeneroOptions: [t('empleados', 'Male'), t('empleados', 'Female')],
			EstadoCiviloptions: [
				t('empleados', 'Single'),
				t('empleados', 'Married'),
				t('empleados', 'Divorced'),
				t('empleados', 'Widowed'),
				t('empleados', 'Domestic partnership'),
			],
		}
	},

	computed: {
		contactDialogButtons() {
			return [
				{ label: t('empleados', 'Cancel'), callback: () => { this.showContactDialog = false } },
				{ label: t('empleados', 'Save'), type: 'primary', nativeType: 'submit', disabled: this.savingContact },
			]
		},
		deleteDialogButtons() {
			return [
				{ label: t('empleados', 'Cancel'), callback: () => { this.showDeleteDialog = false } },
				{ label: t('empleados', 'Delete'), type: 'error', disabled: this.savingContact, callback: this.deleteContact },
			]
		},
	},

	watch: {
		data(news) {
			if (news) {
				this.setAttr(news)
				this.loadContacts()
			}
		},
	},

	mounted() {
		this.setAttr(this.data)
		this.loadContacts()
	},

	methods: {
		t,

		contactsUrl(suffix = '') {
			return generateUrl(`/apps/empleados/empleados/${this.data.Id_empleados}/contactos-emergencia${suffix}`)
		},

		async loadContacts() {
			if (!this.data.Id_empleados) return
			this.loadingContacts = true
			try {
				const response = await axios.get(this.contactsUrl())
				this.contacts = response?.data?.ocs?.data.contactos || []
			} catch (error) {
				showError(t('empleados', 'Could not load emergency contacts: {error}', { error: this.errorMessage(error) }))
			} finally {
				this.loadingContacts = false
			}
		},

		openContactDialog(contact = null) {
			this.editingContact = contact
			this.contactForm = contact ? { ...emptyContact(), ...contact } : emptyContact()
			this.formError = ''
			this.showContactDialog = true
		},

		async saveContact() {
			if (this.savingContact) return
			const payload = Object.fromEntries(Object.entries(this.contactForm).map(([key, value]) => [key, typeof value === 'string' ? value.trim() : value]))
			if (!payload.nombre || !payload.relacion || !payload.numero_contacto) {
				this.formError = t('empleados', 'Full name, relationship and contact number are required.')
				return
			}
			this.savingContact = true
			try {
				if (this.editingContact) await axios.put(this.contactsUrl(`/${this.editingContact.id}`), payload)
				else await axios.post(this.contactsUrl(), payload)
				this.showContactDialog = false
				showSuccess(t('empleados', 'Emergency contact saved.'))
				await this.loadContacts()
			} catch (error) {
				showError(t('empleados', 'Could not save emergency contact: {error}', { error: this.errorMessage(error) }))
			} finally {
				this.savingContact = false
			}
		},

		confirmDelete(contact) {
			this.deletingContact = contact
			this.showDeleteDialog = true
		},

		async deleteContact() {
			if (!this.deletingContact || this.savingContact) return
			this.savingContact = true
			try {
				await axios.delete(this.contactsUrl(`/${this.deletingContact.id}`))
				this.showDeleteDialog = false
				showSuccess(t('empleados', 'Emergency contact deleted.'))
				await this.loadContacts()
			} catch (error) {
				showError(t('empleados', 'Could not delete emergency contact: {error}', { error: this.errorMessage(error) }))
			} finally {
				this.savingContact = false
			}
		},

		async markPrimary(contact) {
			if (this.savingContact) return
			this.savingContact = true
			try {
				await axios.post(this.contactsUrl(`/${contact.id}/principal`))
				showSuccess(t('empleados', 'Primary emergency contact updated.'))
				await this.loadContacts()
			} catch (error) {
				showError(t('empleados', 'Could not update primary contact: {error}', { error: this.errorMessage(error) }))
			} finally {
				this.savingContact = false
			}
		},

		errorMessage(error) {
			return error?.response?.data?.message || error?.message || String(error)
		},

		setAttr(data) {
			this.Direccion = this.checknull(data.Direccion)
			this.Estado_civil = this.checknull(data.Estado_civil)
			this.Telefono_contacto = this.checknull(data.Telefono_contacto)
			this.Rfc = this.checknull(data.Rfc)
			this.Imss = this.checknull(data.Imss)
			this.Contacto_emergencia = this.checknull(data.Contacto_emergencia)
			this.Numero_emergencia = this.checknull(data.Numero_emergencia)
			this.Curp = this.checknull(data.Curp)
			this.Fecha_nacimiento = this.checknull(data.Fecha_nacimiento)
			this.Correo_contacto = this.checknull(data.Correo_contacto)
			this.Genero = this.checknull(data.Genero)
		},

		checknull(value) {
			return value === null ? '' : value
		},

		async CambiosPersonal() {
			try {
				await axios.post(generateUrl('/apps/empleados/CambiosPersonal'), {
					Id_empleados: this.data.Id_empleados,
					Direccion: this.checknull(this.Direccion),
					Estado_civil: this.checknull(this.Estado_civil),
					Telefono_contacto: this.checknull(this.Telefono_contacto), // fix: sin tilde
					Rfc: this.checknull(this.Rfc),
					Imss: this.checknull(this.Imss),
					Contacto_emergencia: this.checknull(this.Contacto_emergencia),
					Numero_emergencia: this.checknull(this.Numero_emergencia),
					Curp: this.checknull(this.Curp),
					Fecha_nacimiento: this.checknull(this.Fecha_nacimiento),
					Correo_contacto: this.checknull(this.Correo_contacto),
					Genero: this.checknull(this.Genero),
				})
				this.$bus.emit('getall')
				this.$bus.emit('show', false)
				showSuccess(t('empleados', 'Data updated'))
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [03] [{error}]', { error: String(err) }))
			}
		},
	},
}
</script>

<style scoped>
.wrapper {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	align-items: flex-end;
}

.external-label {
	display: flex;
	flex-direction: column;
	align-items: stretch;
	min-width: 0;
	gap: 6px;
}

.labeltype {
	display: inline-flex;
	align-items: center;
	min-height: 24px;
	gap: 8px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 600;
}

.labeltype .material-design-icon {
	color: var(--color-primary-element);
}

.inputtype {
	width: 100%;
	min-height: 40px;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 14px;
	transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
}

.inputtype:focus {
	border-color: var(--color-primary-element);
	box-shadow: 0 0 0 2px var(--color-primary-element-light);
	outline: none;
}

.inputtype:disabled {
	background: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
	cursor: not-allowed;
	opacity: 1;
}

.inputtype:hover:not(:disabled) {
	border-color: var(--color-primary-element-light);
}

.emergency-contacts {
	grid-column: 1 / -1;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.top > br {
	display: none;
}

.section-heading,
.contact-title,
.contact-actions {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.section-heading h3 { margin: 0; font-size: 18px; }
.section-heading p { margin: 3px 0 0; color: var(--color-text-maxcontrast); }
.empty-state { padding: 24px; text-align: center; color: var(--color-text-maxcontrast); }
.contact-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 16px; }
.contact-card { min-width: 0; padding: 16px; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); background: var(--color-background-hover); }
.contact-title > div { display: flex; flex-direction: column; min-width: 0; }
.contact-title strong { overflow-wrap: anywhere; font-size: 16px; }
.contact-title span:not(.primary-badge) { color: var(--color-text-maxcontrast); }
.primary-badge { padding: 3px 8px; border-radius: 12px; background: var(--color-primary-element-light); color: var(--color-primary-element-text); font-size: 12px; white-space: nowrap; }
.contact-card dl { margin: 14px 0; }
.contact-card dl > div { margin-top: 8px; }
.contact-card dt { color: var(--color-text-maxcontrast); font-size: 12px; font-weight: 600; }
.contact-card dd { margin: 2px 0 0; overflow-wrap: anywhere; white-space: pre-wrap; }
.contact-actions { justify-content: flex-end; flex-wrap: wrap; }
.contact-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; padding: 12px; }
.contact-form label { display: flex; flex-direction: column; gap: 5px; font-weight: 600; }
.form-wide { grid-column: 1 / -1; }
.contact-notes { min-height: 90px; resize: vertical; }
.form-error { margin: 0; color: var(--color-error); }

.top {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 14px;
	margin-top: 14px;
}

.label-input-trabajo {
	min-width: 0;
}

.select {
	width: 100%;
}

.field-wide {
	grid-column: 1 / -1;
}

.div-center {
	display: flex;
	grid-column: 1 / -1;
	justify-content: center;
	margin-top: 8px;
}

@media (max-width: 768px) {
	.top,
	.contact-grid,
	.contact-form {
		grid-template-columns: 1fr;
	}

	.top {
		gap: 12px;
		margin-top: 8px;
	}

	.emergency-contacts {
		padding: 14px;
	}

	.section-heading { align-items: flex-start; flex-direction: column; }
	.form-wide { grid-column: auto; }
}
</style>
