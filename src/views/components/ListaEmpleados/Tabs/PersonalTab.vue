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

		<!-- Emergency contact -->
		<div class="emergency-contact">
			<div class="external-label">
				<label for="Contacto_emergencia" class="labeltype">
					<Badgeaccountoutline :size="20" />
					{{ t('empleados', 'Emergency contact name') }}
				</label>
				<input
					id="Contacto_emergencia"
					v-model="Contacto_emergencia"
					type="text"
					:disabled="!show"
					class="inputtype">
			</div>

			<div class="external-label">
				<label for="Numero_emergencia" class="labeltype">
					<Badgeaccountoutline :size="20" />
					{{ t('empleados', 'Emergency contact number') }}
				</label>
				<input
					id="Numero_emergencia"
					v-model="Numero_emergencia"
					type="text"
					:disabled="!show"
					class="inputtype">
			</div>

			<br>
		</div>

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
	NcSelect,
} from '@nextcloud/vue'

export default {
	name: 'PersonalTab',

	components: {
		Badgeaccountoutline,
		MapMarkerOutline,
		CakeVariantOutline,
		EmailOutline,
		NcButton,
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

	watch: {
		data(news) {
			if (news) {
				this.setAttr(news)
			}
		},
	},

	mounted() {
		this.setAttr(this.data)
	},

	methods: {
		t,

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

.emergency-contact {
	display: grid;
	grid-column: 1 / -1;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 14px;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.emergency-contact br,
.top > br {
	display: none;
}

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
	.emergency-contact {
		grid-template-columns: 1fr;
	}

	.top {
		gap: 12px;
		margin-top: 8px;
	}

	.emergency-contact {
		padding: 14px;
	}
}
</style>
