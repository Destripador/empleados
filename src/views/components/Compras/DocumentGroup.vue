<template>
	<section class="document-group">
		<h4>{{ title }}</h4>
		<p v-if="documents.length === 0" class="document-group__empty">
			{{ emptyText }}
		</p>
		<ul v-else>
			<li v-for="(document, index) in documents" :key="documentKey(document, index)">
				<div class="document-group__description">
					<strong>{{ documentName(document) }}</strong>
					<span>{{ documentMetadata(document) }}</span>
				</div>
				<div v-if="$scopedSlots.actions" class="document-group__actions">
					<slot name="actions" :document="document" />
				</div>
			</li>
		</ul>
	</section>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'DocumentGroup',
	props: {
		title: {
			type: String,
			required: true,
		},
		documents: {
			type: Array,
			default: () => [],
		},
		emptyText: {
			type: String,
			required: true,
		},
	},
	methods: {
		documentKey(document, index) {
			return document.file_id || document.id || document.id_cotizacion || document.id_adjunto || index
		},
		documentName(document) {
			return document.name || document.nombre_archivo || t('empleados', 'Unnamed document')
		},
		documentMetadata(document) {
			const type = document.type || document.mime || document.mime_type
			const date = document.date || document.created_at || document.fecha
			const user = document.user || document.created_by || document.usuario
			return [type, this.formatDateTime(date), user].filter(Boolean).join(' · ')
		},
		formatDateTime(value) {
			if (!value) {
				return ''
			}

			const date = new Date(String(value).replace(' ', 'T'))
			return Number.isNaN(date.getTime())
				? value
				: new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
		},
	},
}
</script>

<style scoped>
.document-group {
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.document-group h4 {
	margin: 0 0 10px;
	font-size: 15px;
}

.document-group ul {
	margin: 0;
	padding: 0;
	list-style: none;
}

.document-group li {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.document-group__description {
	display: flex;
	min-width: 0;
	flex-direction: column;
}

.document-group__description strong {
	overflow-wrap: anywhere;
}

.document-group__description span,
.document-group__empty {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.document-group__empty {
	margin: 0;
}

.document-group__actions {
	display: flex;
	flex-shrink: 0;
	gap: 6px;
}

@media (max-width: 640px) {
	.document-group li {
		align-items: flex-start;
		flex-direction: column;
	}
}
</style>
