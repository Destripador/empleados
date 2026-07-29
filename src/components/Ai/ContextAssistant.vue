<template>
	<div v-if="available" class="context-assistant">
		<button
			type="button"
			class="context-assistant__trigger"
			:aria-label="t('empleados', 'Abrir asistente de IA')"
			@click="open = !open">
			<RobotOutline :size="26" />
		</button>

		<section
			v-if="open"
			class="context-assistant__panel"
			role="dialog"
			:aria-label="displayTitle">
			<header class="context-assistant__header">
				<h3>{{ displayTitle }}</h3>
				<button
					type="button"
					class="context-assistant__close"
					:aria-label="t('empleados', 'Cerrar asistente')"
					@click="open = false">
					<Close :size="20" />
				</button>
			</header>

			<p class="context-assistant__notice">
				{{ notice }}
			</p>

			<div class="context-assistant__messages" aria-live="polite">
				<div
					v-for="(message, index) in visibleMessages"
					:key="index"
					class="context-assistant__message"
					:class="`context-assistant__message--${message.role}`">
					<span class="context-assistant__message-author">
						{{ message.role === 'user' ? t('empleados', 'Tú') : t('empleados', 'Asistente') }}
					</span>
					<p>{{ message.text }}</p>
				</div>
				<div v-if="loading" class="context-assistant__loading">
					<NcLoadingIcon :size="24" />
					<span>{{ t('empleados', 'Pensando…') }}</span>
				</div>
			</div>

			<div v-if="messages.length === 0 && suggestions.length > 0" class="context-assistant__suggestions">
				<button
					v-for="suggestion in suggestions"
					:key="suggestion"
					type="button"
					@click="useSuggestion(suggestion)">
					{{ suggestion }}
				</button>
			</div>

			<p v-if="error" class="context-assistant__error" role="alert">
				{{ error }}
			</p>

			<div class="context-assistant__composer">
				<label for="context-assistant-question" class="hidden-visually">
					{{ t('empleados', 'Pregunta para el asistente') }}
				</label>
				<textarea
					id="context-assistant-question"
					v-model="question"
					:placeholder="t('empleados', 'Escribe una pregunta…')"
					:disabled="loading"
					maxlength="1000"
					rows="2"
					@keydown.enter.exact.prevent="send" />
				<NcButton
					type="primary"
					:disabled="!canSend"
					:aria-label="t('empleados', 'Enviar pregunta')"
					@click="send">
					<template #icon>
						<Send :size="18" />
					</template>
					{{ t('empleados', 'Enviar') }}
				</NcButton>
			</div>
		</section>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import Close from 'vue-material-design-icons/Close.vue'
import RobotOutline from 'vue-material-design-icons/RobotOutline.vue'
import Send from 'vue-material-design-icons/Send.vue'

let capabilitiesPromise = null

function getCapabilities() {
	if (capabilitiesPromise === null) {
		capabilitiesPromise = axios.get(generateUrl('/apps/empleados/api/ai/capabilities'))
			.then(({ data }) => data?.ocs?.data ?? data)
			.catch(() => {
				capabilitiesPromise = null
				return { available: false, scopes: [] }
			})
	}
	return capabilitiesPromise
}

export default {
	name: 'ContextAssistant',

	components: {
		Close,
		NcButton,
		NcLoadingIcon,
		RobotOutline,
		Send,
	},

	props: {
		scope: {
			type: String,
			required: true,
		},
		context: {
			type: Object,
			required: true,
		},
		contextKey: {
			type: String,
			required: true,
		},
		title: {
			type: String,
			default: '',
		},
		notice: {
			type: String,
			default: () => t('empleados', 'Solo responde sobre la información visible en esta vista.'),
		},
		suggestions: {
			type: Array,
			default: () => [],
		},
	},

	data() {
		return {
			available: false,
			error: '',
			loading: false,
			messages: [],
			open: false,
			question: '',
			requestSequence: 0,
		}
	},

	computed: {
		canSend() {
			return this.question.trim() !== '' && !this.loading
		},
		displayTitle() {
			return this.title || t('empleados', 'Asistente de IA')
		},
		visibleMessages() {
			return this.messages.slice(-10)
		},
	},

	watch: {
		contextKey() {
			this.requestSequence += 1
			this.question = ''
			this.messages = []
			this.error = ''
			this.loading = false
		},
	},

	async mounted() {
		const capabilities = await getCapabilities()
		this.available = capabilities?.available === true
			&& Array.isArray(capabilities.scopes)
			&& capabilities.scopes.includes(this.scope)
	},

	methods: {
		t,
		useSuggestion(suggestion) {
			this.question = suggestion
			this.send()
		},
		async send() {
			if (!this.canSend) return

			const question = this.question.trim()
			const contextKey = this.contextKey
			const requestSequence = ++this.requestSequence
			this.question = ''
			this.error = ''
			this.loading = true
			this.messages.push({ role: 'user', text: question })

			try {
				const context = JSON.parse(JSON.stringify(this.context))
				const { data } = await axios.post(
					generateUrl('/apps/empleados/api/ai/ask'),
					{
						scope: this.scope,
						question,
						context,
					},
				)
				const answer = data?.ocs?.data?.answer ?? data?.answer
				if (typeof answer !== 'string' || answer.trim() === '') {
					throw new Error('Empty answer')
				}
				if (requestSequence !== this.requestSequence || contextKey !== this.contextKey) return
				this.messages.push({ role: 'assistant', text: answer.trim() })
			} catch (error) {
				if (requestSequence !== this.requestSequence || contextKey !== this.contextKey) return
				if (error?.response?.status === 400) {
					this.error = t('empleados', 'El contexto de esta vista no es válido.')
				} else if (error?.response?.status === 401) {
					this.error = t('empleados', 'La sesión ya no está disponible. Recarga la página e inténtalo de nuevo.')
				} else if (error?.response?.status === 412) {
					this.error = t('empleados', 'La IA no está disponible en esta instancia.')
				} else {
					this.error = t('empleados', 'No fue posible obtener una respuesta.')
				}
			} finally {
				if (requestSequence === this.requestSequence && contextKey === this.contextKey) {
					this.loading = false
				}
			}
		},
	},
}
</script>

<style scoped>
.context-assistant {
	position: fixed;
	right: 28px;
	bottom: 28px;
	z-index: 2100;
}

.context-assistant__trigger {
	display: flex;
	width: 52px;
	height: 52px;
	align-items: center;
	justify-content: center;
	border: 0;
	border-radius: 50%;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	box-shadow: 0 4px 16px var(--color-box-shadow);
	cursor: pointer;
}

.context-assistant__trigger:hover,
.context-assistant__trigger:focus-visible {
	background: var(--color-primary-element-hover);
}

.context-assistant__panel {
	position: absolute;
	right: 0;
	bottom: 64px;
	display: flex;
	width: 380px;
	max-height: 520px;
	flex-direction: column;
	overflow: hidden;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 8px 28px var(--color-box-shadow);
}

.context-assistant__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 12px 14px;
	border-bottom: 1px solid var(--color-border);
}

.context-assistant__header h3 {
	margin: 0;
	font-size: 17px;
}

.context-assistant__close {
	display: flex;
	width: 34px;
	height: 34px;
	align-items: center;
	justify-content: center;
	border: 0;
	border-radius: var(--border-radius-pill);
	background: transparent;
	color: var(--color-main-text);
	cursor: pointer;
}

.context-assistant__close:hover {
	background: var(--color-background-hover);
}

.context-assistant__notice {
	margin: 0;
	padding: 10px 14px;
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.context-assistant__messages {
	display: flex;
	min-height: 100px;
	flex: 1;
	flex-direction: column;
	gap: 10px;
	overflow-y: auto;
	padding: 14px;
}

.context-assistant__message {
	max-width: 88%;
	padding: 8px 10px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-dark);
	overflow-wrap: anywhere;
}

.context-assistant__message--user {
	align-self: flex-end;
	background: var(--color-primary-element-light);
}

.context-assistant__message-author {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 600;
}

.context-assistant__message p {
	margin: 2px 0 0;
	white-space: pre-wrap;
}

.context-assistant__loading {
	display: flex;
	align-items: center;
	gap: 8px;
	color: var(--color-text-maxcontrast);
}

.context-assistant__suggestions {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	padding: 0 14px 10px;
}

.context-assistant__suggestions button {
	padding: 5px 9px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-pill);
	background: var(--color-main-background);
	color: var(--color-main-text);
	cursor: pointer;
	font-size: 12px;
	text-align: left;
}

.context-assistant__suggestions button:hover {
	background: var(--color-background-hover);
}

.context-assistant__error {
	margin: 0 14px 8px;
	color: var(--color-error-text);
	font-size: 13px;
}

.context-assistant__composer {
	display: flex;
	align-items: flex-end;
	gap: 8px;
	padding: 10px 14px 14px;
	border-top: 1px solid var(--color-border);
}

.context-assistant__composer textarea {
	min-height: 42px;
	flex: 1;
	resize: vertical;
}

@media (max-width: 600px) {
	.context-assistant {
		right: 16px;
		bottom: 16px;
	}

	.context-assistant__panel {
		position: fixed;
		right: 10px;
		bottom: 80px;
		left: 10px;
		width: auto;
		max-height: calc(100vh - 100px);
	}
}
</style>
