<template>
	<section class="checklist">
		<div v-if="localItems.length">
			<div v-for="item in localItems" :key="item.clave" class="checklist-row">
				<div>
					<strong>{{ item.etiqueta || item.clave }}</strong>
					<span v-if="!editable">{{ resultLabel(item.resultado) }}</span>
				</div>
				<template v-if="editable">
					<select v-model="item.resultado" :disabled="saving" @change="changed(item)">
						<option v-for="option in options" :key="option.value" :value="option.value">
							{{ option.label }}
						</option>
					</select>
					<textarea v-model="item.observacion"
						:disabled="saving"
						:placeholder="t('empleados', 'Observation')"
						@input="changed(item)" />
					<p v-if="item.resultado === 'attention' && !String(item.observacion || '').trim()" class="validation">
						{{ t('empleados', 'Attention observation is required') }}
					</p>
				</template>
				<p v-else-if="item.observacion" class="observation">
					{{ item.observacion }}
				</p>
			</div>
			<div v-if="editable" class="checklist-actions">
				<span v-if="dirtyKeys.length">{{ t('empleados', 'Unsaved changes') }}</span>
				<NcButton :disabled="saving || !dirtyKeys.length || !valid" @click="save">
					{{ saving ? t('empleados', 'Saving…') : t('empleados', 'Save checklist') }}
				</NcButton>
			</div>
		</div>
		<p v-else>
			{{ t('empleados', 'Checklist information is not available for this record.') }}
		</p>
	</section>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'

export default {
	name: 'MantenimientoChecklist',
	components: { NcButton },
	props: {
		items: { type: Array, default: () => [] },
		editable: { type: Boolean, default: false },
		saving: { type: Boolean, default: false },
	},
	data() {
		return { localItems: [], dirtyKeys: [] }
	},
	computed: {
		options() {
			return [
				{ value: 'pending', label: t('empleados', 'Pending') },
				{ value: 'ok', label: t('empleados', 'Correct') },
				{ value: 'attention', label: t('empleados', 'Requires attention') },
				{ value: 'not_applicable', label: t('empleados', 'Not applicable') },
			]
		},
		valid() {
			return this.localItems.every(item => item.resultado !== 'attention' || String(item.observacion || '').trim())
		},
	},
	watch: {
		items: { immediate: true, deep: true, handler(items) { this.reset(items) } },
	},
	methods: {
		t,
		reset(items) {
			this.localItems = (items || []).map(item => ({ ...item, observacion: item.observacion || '' }))
			this.dirtyKeys = []
			this.$emit('dirty-change', false)
		},
		changed(item) {
			if (!this.dirtyKeys.includes(item.clave)) this.dirtyKeys.push(item.clave)
			this.$emit('dirty-change', true)
		},
		resultLabel(result) {
			return this.options.find(option => option.value === result)?.label || result || '—'
		},
		save() {
			if (!this.valid || !this.dirtyKeys.length) return
			const dirty = new Set(this.dirtyKeys)
			this.$emit('save', this.localItems.filter(item => dirty.has(item.clave)).map(item => ({
				key: item.clave,
				result: item.resultado,
				observation: String(item.observacion || '').trim() || null,
			})))
		},
	},
}
</script>

<style scoped lang="scss">
.checklist-row { padding: 12px 0; border-bottom: 1px solid var(--color-border); }
.checklist-row > div { display: flex; justify-content: space-between; gap: 12px; }
select, textarea { width: 100%; margin-top: 8px; padding: 8px; border: 1px solid var(--color-border-maxcontrast); border-radius: var(--border-radius); }
textarea { min-height: 70px; resize: vertical; }
.validation { color: var(--color-error); margin: 4px 0 0; }
.observation { margin: 6px 0 0; color: var(--color-text-maxcontrast); }
.checklist-actions { display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 12px; }
</style>
