<template>
	<div class="well">
		<div class="notes-toolbar">
			<NcActions>
				<NcActionButton :close-after-click="true" @click="showEdit">
					<template #icon>
						<LanguageMarkdown :size="20" />
					</template>
					{{ showMarkdown ? t('empleados', 'Disable design') : t('empleados', 'Enable design') }}
				</NcActionButton>
			</NcActions>
		</div>

		<div class="top notes-content">
			<div class="notes-editor">
				<NcRichText
					v-if="showMarkdown"
					class="notes-preview"
					:class="{ 'plain-text': !useMarkdown }"
					:text="inputValue"
					:autolink="true"
					:use-markdown="useMarkdown" />

				<!-- Employee Notes -->
				<NcTextArea
					v-else
					input-class="model"
					class="notes-textarea"
					:label="t('empleados', 'Employee notes')"
					resize="vertical"
					:disabled="show"
					:value.sync="inputValue" />
			</div>

			<NcButton
				v-if="automaticsave === 'false'"
				class="save-note-button"
				:aria-label="t('empleados', 'Save note')"
				type="primary"
				@click="guardarNota">
				{{ t('empleados', 'Save note') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import 'vue-nav-tabs/themes/vue-tabs.css'
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import { translate as t } from '@nextcloud/l10n' // <-- agrega t

// ICONOS
import LanguageMarkdown from 'vue-material-design-icons/LanguageMarkdown.vue'

import {
	NcTextArea,
	NcButton,
	NcRichText,
	NcActions,
	NcActionButton,
} from '@nextcloud/vue'

export default {
	name: 'NotasTab',

	components: {
		NcTextArea,
		NcButton,
		NcRichText,
		NcActions,
		NcActionButton,
		LanguageMarkdown,
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
		automaticsave: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			notas: this.data.Notas ?? '',
			showMarkdown: false,
			useMarkdown: true,
		}
	},

	computed: {
		inputValue: {
			get() {
				return this.notas
			},
			set(value) {
				this.debouncePropertyChange(value.trim())
			},
		},
		debouncePropertyChange() {
			return debounce(function(value) {
				this.notas = value
				if (this.automaticsave === 'true') {
					this.guardarNota()
				}
			}, 900)
		},
	},

	watch: {
		data(news) {
			this.notas = news.Notas
		},
	},

	mounted() {
		this.notas = this.data.Notas
	},

	methods: {
		// expone t al template por si lo necesitas como método
		t,

		showEdit() {
			this.showMarkdown = !this.showMarkdown
		},

		async guardarNota() {
			try {
				this.debouncePropertyChange.flush?.()
				await axios.post(generateUrl('/apps/empleados/GuardarNota'), {
					id_empleados: this.data.Id_empleados,
					nota: this.notas,
				})
				showSuccess(t('empleados', 'Note has been updated'))
				this.$bus?.emit('getall')
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [03] [{error}]', { error: String(err) }))
			}
		},
	},
}
</script>

<style scoped>
.well {
	position: relative;
	min-height: 360px;
	padding-top: 2px;
	background: var(--color-main-background);
}

.notes-toolbar {
	position: absolute;
	top: 0;
	right: 0;
	z-index: 10;
	display: flex;
	justify-content: flex-end;
}

.top {
	margin-top: 14px;
}

.notes-content {
	display: flex;
	flex-direction: column;
	gap: 14px;
}

.notes-editor {
	min-height: 320px;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
}

.notes-preview {
	min-height: 282px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-main-text);
	line-height: 1.5;
	overflow-wrap: anywhere;
}

.plain-text {
	white-space: pre-line;
}

.notes-textarea {
	width: 100%;
}

:deep(textarea.model) {
	min-height: 300px !important;
	border-radius: var(--border-radius-large);
}

.save-note-button {
	align-self: center;
}

@media (max-width: 768px) {
	.well {
		padding-top: 44px;
	}

	.notes-toolbar {
		right: 0;
	}

	.notes-editor {
		padding: 12px;
	}
}
</style>
