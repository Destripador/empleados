<template>
	<div class="device-history">
		<div v-if="loading && entries.length === 0" class="device-history__state">
			<NcLoadingIcon :size="42" />
		</div>

		<div v-else-if="error" class="device-history__error" role="alert">
			<p>{{ error }}</p>
			<NcButton @click="$emit('retry')">
				{{ t('empleados', 'Try again') }}
			</NcButton>
		</div>

		<NcEmptyContent
			v-else-if="entries.length === 0"
			:name="t('empleados', 'No history records found')" />

		<ol v-else class="device-history__timeline">
			<li
				v-for="entry in entries"
				:key="entry.id"
				class="device-history__item"
				:class="'device-history__item--' + typeTone(entry.tipo_movimiento)">
				<div class="device-history__marker" aria-hidden="true" />
				<article class="device-history__card">
					<header class="device-history__header">
						<span class="device-history__type">{{ movementTypeLabel(entry.tipo_movimiento) }}</span>
						<time :datetime="entry.fecha">{{ formatDateTime(entry.fecha) }}</time>
					</header>
					<p v-if="summary(entry)" class="device-history__summary">
						{{ summary(entry) }}
					</p>
					<dl v-if="facts(entry).length" class="device-history__facts">
						<div v-for="fact in facts(entry)" :key="fact.label">
							<dt>{{ fact.label }}</dt>
							<dd>{{ fact.value }}</dd>
						</div>
					</dl>
					<ul v-if="visibleChanges(entry).length" class="device-history__changes">
						<li v-for="change in visibleChanges(entry)" :key="change.field">
							<span class="device-history__field">{{ fieldLabel(change.field) }}</span>
							<span class="device-history__from">{{ displayValue(change.anterior) }}</span>
							<span class="device-history__arrow" aria-hidden="true">→</span>
							<span class="device-history__to">{{ displayValue(change.nuevo) }}</span>
						</li>
					</ul>
				</article>
			</li>
		</ol>

		<div v-if="entries.length < total && !error" class="device-history__more">
			<NcButton :disabled="loading" @click="$emit('load-more')">
				<template #icon>
					<NcLoadingIcon v-if="loading" :size="20" />
				</template>
				{{ t('empleados', 'Load more') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'

export default {
	name: 'DeviceHistoryList',
	components: { NcButton, NcEmptyContent, NcLoadingIcon },
	props: {
		entries: { type: Array, default: () => [] },
		loading: { type: Boolean, default: false },
		error: { type: String, default: '' },
		total: { type: Number, default: 0 },
	},
	emits: ['retry', 'load-more'],
	methods: {
		t,
		displayValue(value) {
			return value === null || value === undefined || value === '' ? '—' : value
		},
		movementTypeLabel(type) {
			const labels = {
				alta: t('empleados', 'Registered'),
				asignacion: t('empleados', 'Assignment'),
				reasignacion: t('empleados', 'Reassignment'),
				desasignacion: t('empleados', 'Unassignment'),
				cambio_estado: t('empleados', 'Status change'),
				actualizacion: t('empleados', 'Update'),
				mantenimiento: t('empleados', 'Maintenance'),
				reparacion: t('empleados', 'Repair'),
				baja: t('empleados', 'Retirement'),
				nota: t('empleados', 'Note'),
			}
			return labels[type] || type || t('empleados', 'Movement')
		},
		typeTone(type) {
			if (['asignacion', 'alta'].includes(type)) return 'success'
			if (['desasignacion', 'baja'].includes(type)) return 'muted'
			if (['mantenimiento', 'reparacion', 'cambio_estado'].includes(type)) return 'warning'
			return 'info'
		},
		formatDateTime(value) {
			if (!value) return ''
			const date = new Date(String(value).replace(' ', 'T'))
			return Number.isNaN(date.getTime())
				? String(value)
				: new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date)
		},
		isGroupUid(uid) {
			return String(uid || '').startsWith('grupo:')
		},
		assigneeLabel(uid, nombre) {
			const name = nombre || uid
			if (!name) return ''
			return this.isGroupUid(uid)
				? t('empleados', 'Group {name}', { name: nombre || String(uid).replace(/^grupo:/, '') })
				: name
		},
		summary(entry) {
			if (entry?.descripcion) return entry.descripcion
			return ''
		},
		facts(entry) {
			const items = []
			if (entry.actor_nombre || entry.actor_uid) {
				items.push({ label: t('empleados', 'Recorded by'), value: entry.actor_nombre || entry.actor_uid })
			}
			const previous = this.assigneeLabel(entry.empleado_anterior_uid, entry.empleado_anterior_nombre)
			const next = this.assigneeLabel(entry.empleado_nuevo_uid, entry.empleado_nuevo_nombre)
			if (previous && previous !== next) {
				items.push({
					label: this.isGroupUid(entry.empleado_anterior_uid) ? t('empleados', 'Previous group') : t('empleados', 'Previous employee'),
					value: previous,
				})
			}
			if (next && next !== previous) {
				items.push({
					label: this.isGroupUid(entry.empleado_nuevo_uid) ? t('empleados', 'Assigned group') : t('empleados', 'Assigned employee'),
					value: next,
				})
			}
			if (entry.estado_anterior && entry.estado_anterior !== entry.estado_nuevo) {
				items.push({ label: t('empleados', 'Previous status'), value: entry.estado_anterior })
			}
			if (entry.estado_nuevo && entry.estado_nuevo !== entry.estado_anterior) {
				items.push({ label: t('empleados', 'New status'), value: entry.estado_nuevo })
			}
			return items
		},
		visibleChanges(entry) {
			if (!entry?.cambios || typeof entry.cambios !== 'object' || Array.isArray(entry.cambios)) return []
			return Object.entries(entry.cambios)
				.filter(([field]) => !['id_empleado', 'gid', 'estado'].includes(field))
				.map(([field, values]) => ({
					field,
					anterior: values?.anterior,
					nuevo: values?.nuevo,
				}))
		},
		fieldLabel(field) {
			const labels = {
				id_modelo: t('empleados', 'Model'),
				nombre_dispositivo: t('empleados', 'Device name'),
				nombre_sistema: t('empleados', 'System name'),
				numero_serie: t('empleados', 'Serial number'),
				info: t('empleados', 'Information'),
				categoria_soporte: t('empleados', 'Support category'),
				prioridad_soporte: t('empleados', 'Support priority'),
				gid: t('empleados', 'Group'),
			}
			return labels[field] || field
		},
	},
}
</script>

<style scoped>
.device-history {
	display: flex;
	flex-direction: column;
	gap: 16px;
	min-height: 160px;
}

.device-history__state {
	display: grid;
	place-items: center;
	min-height: 180px;
}

.device-history__error {
	display: flex;
	gap: 12px;
	align-items: center;
	justify-content: space-between;
	padding: 14px;
	border: 1px solid var(--color-error);
	border-radius: var(--border-radius-large, 8px);
}

.device-history__error p {
	margin: 0;
}

.device-history__timeline {
	display: grid;
	gap: 0;
	margin: 0;
	padding: 0 0 0 12px;
	list-style: none;
	border-left: 2px solid var(--color-border);
}

.device-history__item {
	position: relative;
	padding: 0 0 16px 20px;
}

.device-history__item:last-child {
	padding-bottom: 0;
}

.device-history__marker {
	position: absolute;
	top: 18px;
	left: -7px;
	width: 12px;
	height: 12px;
	border-radius: 50%;
	background: var(--color-primary-element);
	box-shadow: 0 0 0 4px var(--color-main-background);
}

.device-history__item--success .device-history__marker {
	background: var(--color-success);
}

.device-history__item--warning .device-history__marker {
	background: var(--color-warning);
}

.device-history__item--muted .device-history__marker {
	background: var(--color-text-maxcontrast);
}

.device-history__card {
	display: grid;
	gap: 8px;
	padding: 14px 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	background: var(--color-main-background);
}

.device-history__header {
	display: flex;
	gap: 12px;
	align-items: center;
	justify-content: space-between;
}

.device-history__header time {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	white-space: nowrap;
}

.device-history__type {
	display: inline-flex;
	padding: 3px 9px;
	border-radius: 999px;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	font-size: 12px;
	font-weight: 700;
}

.device-history__item--success .device-history__type {
	background: var(--color-success);
	color: var(--color-primary-element-text, #fff);
}

.device-history__item--warning .device-history__type {
	background: var(--color-warning);
	color: var(--color-main-text);
}

.device-history__item--muted .device-history__type {
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}

.device-history__summary {
	margin: 0;
	line-height: 1.45;
}

.device-history__facts {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 8px 16px;
	margin: 0;
}

.device-history__facts dt {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	font-weight: 600;
}

.device-history__facts dd {
	margin: 0;
	font-weight: 600;
}

.device-history__changes {
	display: grid;
	gap: 6px;
	margin: 0;
	padding: 10px 12px;
	list-style: none;
	border-radius: var(--border-radius-large, 8px);
	background: var(--color-background-hover);
}

.device-history__changes li {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	align-items: center;
}

.device-history__field {
	min-width: 110px;
	color: var(--color-text-maxcontrast);
	font-weight: 700;
}

.device-history__from,
.device-history__to {
	font-weight: 600;
}

.device-history__arrow {
	color: var(--color-text-maxcontrast);
}

.device-history__more {
	display: flex;
	justify-content: center;
}

@media (max-width: 700px) {
	.device-history__header,
	.device-history__facts {
		grid-template-columns: 1fr;
		flex-direction: column;
		align-items: flex-start;
	}
}
</style>
