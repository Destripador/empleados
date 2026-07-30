<template>
	<div v-if="available" class="context-assistant">
		<button
			v-show="!open"
			ref="trigger"
			type="button"
			class="context-assistant__trigger"
			:title="t('empleados', 'Abrir asistente')"
			:aria-label="t('empleados', 'Abrir asistente')"
			@click="openPanel">
			<RobotOutline :size="27" />
		</button>

		<section
			v-if="open"
			class="context-assistant__panel"
			role="dialog"
			aria-modal="false"
			:aria-labelledby="titleId"
			:aria-describedby="descriptionId"
			@keydown.esc="closePanel">
			<header class="context-assistant__header">
				<div class="context-assistant__identity">
					<span class="context-assistant__header-icon" aria-hidden="true">
						<RobotOutline :size="24" />
					</span>
					<div>
						<h2 :id="titleId">
							{{ displayTitle }}
						</h2>
						<p class="context-assistant__status">
							<span class="context-assistant__status-dot" />
							{{ loading ? t('empleados', 'Analizando información…') : t('empleados', 'IA disponible') }}
						</p>
					</div>
				</div>
				<button
					type="button"
					class="context-assistant__close"
					:aria-label="t('empleados', 'Cerrar asistente')"
					:title="t('empleados', 'Cerrar asistente')"
					@click="closePanel">
					<Close :size="21" />
				</button>
			</header>

			<p :id="descriptionId" class="context-assistant__description">
				{{ effectiveDescription }}
			</p>

			<div
				ref="messageRegion"
				class="context-assistant__messages"
				aria-live="polite"
				aria-relevant="additions">
				<div v-if="messages.length === 0" class="context-assistant__welcome">
					<span class="context-assistant__welcome-icon" aria-hidden="true">
						<RobotOutline :size="42" />
					</span>
					<h3>{{ t('empleados', '¿En qué puedo ayudarte?') }}</h3>
					<p>{{ t('empleados', 'Haz una pregunta sobre la información disponible en este contexto.') }}</p>

					<div v-if="suggestions.length > 0" class="context-assistant__suggestions">
						<button
							v-for="suggestion in suggestions.slice(0, 6)"
							:key="suggestion"
							type="button"
							:disabled="loading"
							@click="useSuggestion(suggestion)">
							<span>{{ suggestion }}</span>
							<ChevronRight :size="18" aria-hidden="true" />
						</button>
					</div>
				</div>

				<div
					v-for="message in visibleMessages"
					:key="message.id"
					class="ai-message"
					:class="`ai-message--${message.role}`">
					<div v-if="message.role === 'assistant'" class="ai-message__avatar" aria-hidden="true">
						<RobotOutline :size="18" />
					</div>
					<div class="ai-message__content">
						<div class="ai-message__bubble">
							<AiMessageContent
								v-if="message.role === 'assistant'"
								:content="message.text" />
							<p v-else>
								{{ message.text }}
							</p>
						</div>
						<button
							v-if="message.role === 'assistant'"
							type="button"
							class="ai-message__copy"
							:aria-label="t('empleados', 'Copiar respuesta')"
							@click="copyMessage(message)">
							<Check v-if="copiedMessageId === message.id" :size="15" />
							<ContentCopy v-else :size="15" />
							{{ copiedMessageId === message.id ? t('empleados', 'Copiado') : t('empleados', 'Copiar') }}
						</button>
					</div>
				</div>

				<div v-if="loading" class="ai-message ai-message--assistant">
					<div class="ai-message__avatar" aria-hidden="true">
						<RobotOutline :size="18" />
					</div>
					<div
						class="ai-message__bubble context-assistant__typing"
						:aria-label="t('empleados', 'Analizando…')">
						<span />
						<span />
						<span />
					</div>
				</div>
			</div>

			<footer class="context-assistant__footer">
				<div class="context-assistant__composer">
					<label :for="textareaId" class="hidden-visually">
						{{ t('empleados', 'Pregunta para el asistente') }}
					</label>
					<textarea
						:id="textareaId"
						ref="questionInput"
						v-model="question"
						:placeholder="t('empleados', 'Escribe una pregunta…')"
						:disabled="loading"
						aria-describedby="context-assistant-composer-help"
						maxlength="1000"
						rows="1"
						@input="resizeTextarea"
						@keydown.enter.exact.prevent="send" />
					<button
						type="button"
						class="context-assistant__send"
						:disabled="!canSend"
						:aria-label="t('empleados', 'Enviar')"
						@click="send">
						<Send :size="19" />
						<span>{{ t('empleados', 'Enviar') }}</span>
					</button>
				</div>
				<span id="context-assistant-composer-help" class="hidden-visually">
					{{ t('empleados', 'Pulsa Enter para enviar o Mayús más Enter para una nueva línea.') }}
				</span>
			</footer>
		</section>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import Check from 'vue-material-design-icons/Check.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import Close from 'vue-material-design-icons/Close.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import RobotOutline from 'vue-material-design-icons/RobotOutline.vue'
import Send from 'vue-material-design-icons/Send.vue'
import AiMessageContent from './AiMessageContent.vue'

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
		AiMessageContent,
		Check,
		ChevronRight,
		Close,
		ContentCopy,
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
		description: {
			type: String,
			default: '',
		},
		notice: {
			type: String,
			default: '',
		},
		suggestions: {
			type: Array,
			default: () => [],
		},
	},
	data() {
		return {
			available: false,
			copiedMessageId: null,
			copyTimer: null,
			loading: false,
			messageSequence: 0,
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
		descriptionId() {
			return `context-assistant-description-${this._uid}`
		},
		displayTitle() {
			return this.title || t('empleados', 'Asistente de IA')
		},
		effectiveDescription() {
			return this.description
				|| this.notice
				|| t('empleados', 'Consulta información autorizada disponible en este contexto.')
		},
		textareaId() {
			return `context-assistant-question-${this._uid}`
		},
		titleId() {
			return `context-assistant-title-${this._uid}`
		},
		visibleMessages() {
			return this.messages.slice(-10)
		},
	},
	watch: {
		contextKey() {
			this.resetConversation()
		},
		scope() {
			this.resetConversation()
		},
		visibleMessages() {
			this.scrollToLatest()
		},
		loading() {
			this.scrollToLatest()
		},
	},
	async mounted() {
		const capabilities = await getCapabilities()
		this.available = capabilities?.available === true
			&& Array.isArray(capabilities.scopes)
			&& capabilities.scopes.includes(this.scope)
	},
	beforeDestroy() {
		if (this.copyTimer !== null) {
			window.clearTimeout(this.copyTimer)
		}
	},
	methods: {
		t,
		addMessage(role, text, successful = false) {
			const message = {
				id: ++this.messageSequence,
				role,
				text,
				successful,
			}
			this.messages.push(message)
			return message
		},
		getRequestHistory() {
			const messages = this.messages
				.filter(message => message.successful === true
					&& (message.role === 'user' || message.role === 'assistant'))
			const exchanges = []
			for (let index = 0; index + 1 < messages.length; index += 2) {
				const userMessage = messages[index]
				const assistantMessage = messages[index + 1]
				if (userMessage.role !== 'user' || assistantMessage.role !== 'assistant') {
					continue
				}
				const userContent = this.historyText(userMessage.text)
				const assistantContent = this.historyText(assistantMessage.text)
				if (userContent === '' || assistantContent === '') {
					continue
				}
				exchanges.push(
					{ role: 'user', content: userContent },
					{ role: 'assistant', content: assistantContent },
				)
			}
			return exchanges.slice(-12)
		},
		historyText(value) {
			return String(value)
				.replace(/<\/?[a-z][^>]*>/giu, '')
				.trim()
				.slice(0, 2000)
		},
		async copyMessage(message) {
			try {
				await navigator.clipboard.writeText(message.text)
				this.copiedMessageId = message.id
				if (this.copyTimer !== null) window.clearTimeout(this.copyTimer)
				this.copyTimer = window.setTimeout(() => {
					this.copiedMessageId = null
				}, 1800)
			} catch {
				this.addMessage('system', t('empleados', 'No fue posible copiar la respuesta.'))
			}
		},
		closePanel() {
			this.open = false
			this.$nextTick(() => this.$refs.trigger?.focus())
		},
		openPanel() {
			this.open = true
			this.$nextTick(() => {
				this.$refs.questionInput?.focus()
				this.resizeTextarea()
				this.scrollToLatest()
			})
		},
		resetTextarea() {
			this.$nextTick(() => {
				if (this.$refs.questionInput) {
					this.$refs.questionInput.style.height = ''
				}
			})
		},
		resetConversation() {
			this.requestSequence += 1
			this.question = ''
			this.messages = []
			this.loading = false
			this.resetTextarea()
		},
		resizeTextarea() {
			const textarea = this.$refs.questionInput
			if (!textarea) return
			textarea.style.height = 'auto'
			textarea.style.height = `${Math.min(textarea.scrollHeight, 108)}px`
		},
		scrollToLatest() {
			this.$nextTick(() => {
				const region = this.$refs.messageRegion
				if (region) {
					region.scrollTo({ top: region.scrollHeight, behavior: 'smooth' })
				}
			})
		},
		useSuggestion(suggestion) {
			if (this.loading) return
			this.question = suggestion
			this.send()
		},
		async send() {
			if (!this.canSend) return

			const question = this.question.trim()
			const contextKey = this.contextKey
			const requestSequence = ++this.requestSequence
			const history = this.getRequestHistory()
			this.question = ''
			this.loading = true
			const userMessage = this.addMessage('user', question)
			this.resetTextarea()

			try {
				const context = JSON.parse(JSON.stringify(this.context))
				const { data } = await axios.post(
					generateUrl('/apps/empleados/api/ai/ask'),
					{ scope: this.scope, question, context, history },
				)
				const answer = data?.ocs?.data?.answer ?? data?.answer
				if (typeof answer !== 'string' || answer.trim() === '') {
					throw new Error('Empty answer')
				}
				if (requestSequence !== this.requestSequence || contextKey !== this.contextKey) return
				userMessage.successful = true
				this.addMessage('assistant', answer.trim(), true)
			} catch (error) {
				if (requestSequence !== this.requestSequence || contextKey !== this.contextKey) return
				if (error?.response?.status === 400) {
					this.addMessage('system', t('empleados', 'El contexto de esta vista no es válido.'))
				} else if (error?.response?.status === 401) {
					this.addMessage('system', t('empleados', 'La sesión ya no está disponible. Recarga la página e inténtalo de nuevo.'))
				} else if (error?.response?.status === 403) {
					this.addMessage('system', t('empleados', 'No tienes permiso para usar este asistente.'))
				} else if (error?.response?.status === 412) {
					this.addMessage('system', t('empleados', 'El servicio de inteligencia artificial ya no está disponible.'))
					capabilitiesPromise = null
					this.available = false
					this.open = false
				} else {
					this.addMessage('system', t('empleados', 'No fue posible obtener una respuesta. Inténtalo nuevamente.'))
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
	transition: transform 120ms ease, box-shadow 120ms ease;
}

.context-assistant__trigger:hover {
	box-shadow: 0 6px 20px var(--color-box-shadow);
	transform: translateY(-1px);
}

.context-assistant__panel {
	position: absolute;
	right: 0;
	bottom: 0;
	display: flex;
	width: min(480px, calc(100vw - 32px));
	max-height: min(760px, calc(100vh - 96px));
	flex-direction: column;
	overflow: hidden;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 10px 36px var(--color-box-shadow);
	color: var(--color-main-text);
}

.context-assistant__header {
	display: flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: space-between;
	padding: 14px 14px 12px;
	border-bottom: 1px solid var(--color-border);
}

.context-assistant__identity {
	display: flex;
	min-width: 0;
	align-items: center;
	gap: 10px;
}

.context-assistant__header-icon,
.context-assistant__welcome-icon,
.ai-message__avatar {
	display: flex;
	align-items: center;
	justify-content: center;
	background: var(--color-background-hover);
	color: var(--color-primary-element);
}

.context-assistant__header-icon {
	width: 38px;
	height: 38px;
	flex: 0 0 38px;
	border-radius: 50%;
}

.context-assistant__header h2 {
	overflow: hidden;
	margin: 0;
	font-size: 17px;
	line-height: 1.25;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.context-assistant__status {
	display: flex;
	align-items: center;
	gap: 6px;
	margin: 3px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.context-assistant__status-dot {
	width: 7px;
	height: 7px;
	border-radius: 50%;
	background: var(--color-success);
}

.context-assistant__close {
	display: flex;
	width: 40px;
	height: 40px;
	flex: 0 0 40px;
	align-items: center;
	justify-content: center;
	border: 0;
	border-radius: 50%;
	background: transparent;
	color: var(--color-main-text);
	cursor: pointer;
}

.context-assistant__close:hover {
	background: var(--color-background-hover);
}

.context-assistant__description {
	margin: 0;
	padding: 9px 16px;
	border-bottom: 1px solid var(--color-border);
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	line-height: 1.4;
}

.context-assistant__messages {
	display: flex;
	min-height: 260px;
	max-height: min(65vh, 620px);
	flex: 1 1 auto;
	flex-direction: column;
	gap: 9px;
	overflow-y: auto;
	overscroll-behavior: contain;
	padding: 16px 22px 18px 14px;
	scrollbar-gutter: stable;
}

.context-assistant__welcome {
	display: flex;
	max-width: 100%;
	flex: 1;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 12px 4px;
	text-align: center;
}

.context-assistant__welcome-icon {
	width: 68px;
	height: 68px;
	margin-bottom: 12px;
	border-radius: 50%;
}

.context-assistant__welcome h3 {
	margin: 0 0 6px;
	font-size: 18px;
}

.context-assistant__welcome > p {
	max-width: 330px;
	margin: 0;
	color: var(--color-text-maxcontrast);
	line-height: 1.45;
}

.context-assistant__suggestions {
	display: grid;
	width: 100%;
	margin-top: 18px;
	gap: 8px;
	grid-template-columns: repeat(2, minmax(0, 1fr));
}

.context-assistant__suggestions button {
	display: flex;
	min-height: 48px;
	align-items: center;
	justify-content: space-between;
	gap: 6px;
	padding: 9px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
	cursor: pointer;
	font-size: 13px;
	line-height: 1.3;
	text-align: left;
}

.context-assistant__suggestions button:hover {
	background: var(--color-background-hover);
}

.ai-message {
	display: flex;
	width: 100%;
	align-items: flex-start;
	padding-inline-start: 0;
	border-inline-start: 0;
	gap: 8px;
}

.ai-message::before {
	display: none;
	content: none;
}

.ai-message--user {
	justify-content: flex-end;
}

.ai-message__avatar {
	width: 30px;
	height: 30px;
	flex: 0 0 30px;
	border-radius: 50%;
}

.ai-message__content {
	display: flex;
	width: fit-content;
	max-width: 90%;
	flex-direction: column;
	align-items: flex-start;
}

.ai-message__bubble {
	padding: 10px 12px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	overflow-wrap: anywhere;
}

.ai-message--user .ai-message__content {
	width: fit-content;
	min-width: 0;
	max-width: min(82%, 360px);
	align-items: flex-end;
}

.ai-message--user .ai-message__bubble {
	box-sizing: border-box;
	width: fit-content;
	min-width: 0;
	max-width: 100%;
	padding: 12px 16px;
	border-radius: 16px 16px 4px;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	line-height: 1.45;
	text-align: left;
	white-space: pre-wrap;
}

.ai-message--user p,
.ai-message--system p {
	margin: 0;
	overflow-wrap: anywhere;
}

.ai-message--system {
	justify-content: center;
}

.ai-message--system .ai-message__content {
	width: fit-content;
	max-width: min(88%, 380px);
	align-items: center;
}

.ai-message--system .ai-message__bubble {
	width: fit-content;
	padding: 7px 11px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	line-height: 1.35;
	text-align: center;
}

.ai-message__copy {
	display: flex;
	min-height: 30px;
	align-items: center;
	gap: 4px;
	margin-top: 2px;
	padding: 3px 6px;
	border: 0;
	border-radius: var(--border-radius);
	background: var(--color-background-dark);
	color: var(--color-main-text);
	cursor: pointer;
	font-size: 12px;
	font-weight: 500;
	opacity: .72;
	transition: opacity 120ms ease, background 120ms ease;
}

.ai-message__copy:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.ai-message__copy:hover {
	background: var(--color-background-hover);
	opacity: 1;
}

.ai-message:hover .ai-message__copy,
.ai-message:focus-within .ai-message__copy {
	opacity: 1;
}

.context-assistant__typing {
	display: flex;
	min-width: 54px;
	align-items: center;
	justify-content: center;
	gap: 5px;
	padding-block: 14px;
}

.context-assistant__typing span {
	width: 6px;
	height: 6px;
	border-radius: 50%;
	background: var(--color-text-maxcontrast);
	animation: context-assistant-bounce 1.2s infinite ease-in-out;
}

.context-assistant__typing span:nth-child(2) {
	animation-delay: 150ms;
}

.context-assistant__typing span:nth-child(3) {
	animation-delay: 300ms;
}

.context-assistant__footer {
	flex: 0 0 auto;
	padding: 8px 10px 10px;
	border-top: 1px solid var(--color-border);
	background: var(--color-main-background);
}

.context-assistant__composer {
	display: flex;
	align-items: flex-end;
	gap: 4px;
	padding: 3px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.context-assistant__composer:focus-within {
	border-color: var(--color-primary-element);
	box-shadow: 0 0 0 1px var(--color-primary-element);
}

.context-assistant__composer textarea {
	min-height: 32px;
	max-height: 108px;
	flex: 1;
	padding: 6px 8px;
	overflow-y: auto;
	border: 0;
	background: transparent;
	box-shadow: none;
	color: var(--color-main-text);
	font: inherit;
	line-height: 20px;
	resize: none;
}

.context-assistant__composer textarea:focus {
	outline: 0;
}

.context-assistant__send {
	display: flex;
	min-width: 88px;
	height: 36px;
	align-items: center;
	justify-content: center;
	gap: 6px;
	padding: 0 12px;
	border: 0;
	border-radius: var(--border-radius);
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	cursor: pointer;
	font-weight: 600;
}

.context-assistant__send:disabled {
	cursor: default;
	opacity: .5;
}

.context-assistant__trigger:focus-visible,
.context-assistant__close:focus-visible,
.context-assistant__send:focus-visible,
.context-assistant__suggestions button:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

@keyframes context-assistant-bounce {
	0%,
	60%,
	100% {
		transform: translateY(0);
	}
	30% {
		transform: translateY(-4px);
	}
}

@media (max-width: 600px) {
	.context-assistant {
		right: 8px;
		bottom: max(8px, env(safe-area-inset-bottom));
		left: 8px;
	}

	.context-assistant__panel {
		position: fixed;
		right: 8px;
		bottom: max(8px, env(safe-area-inset-bottom));
		left: 8px;
		width: auto;
		max-height: calc(100vh - 72px);
		max-height: calc(100dvh - 72px);
	}

	.context-assistant__messages {
		min-height: 220px;
	}

	.context-assistant__suggestions {
		grid-template-columns: 1fr;
	}

	.context-assistant__send {
		min-width: 42px;
		width: 42px;
		padding: 0;
	}

	.context-assistant__send span {
		display: none;
	}
}

@media (prefers-reduced-motion: reduce) {
	.context-assistant__trigger,
	.ai-message__copy,
	.context-assistant__typing span {
		animation: none;
		transition: none;
	}
}
</style>
