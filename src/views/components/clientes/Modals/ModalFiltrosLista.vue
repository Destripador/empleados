<template>
	<NcModal v-if="open"
		size="small"
		:name="t('empleados', 'Filter companies')"
		@close="$emit('close')">
		<div class="modal-content">
			<div class="modal-header">
				<p class="section-label">
					{{ t('empleados', 'Companies and groups') }}
				</p>
				<h2>{{ t('empleados', 'Filter companies') }}</h2>
			</div>

			<div class="form-grid">
				<NcSelect :value="sortOrder"
					class="span-2"
					:input-label="t('empleados', 'Sort')"
					:options="sortOrderOptions"
					label="label"
					track-by="value"
					:searchable="false"
					:clearable="false"
					@input="$emit('update:sortOrder', $event)" />

				<NcSelect :value="tipoFiltro"
					class="span-2"
					:input-label="t('empleados', 'Show')"
					:options="tipoFiltroOptions"
					label="label"
					track-by="value"
					:searchable="false"
					:clearable="false"
					@input="$emit('update:tipoFiltro', $event)" />

				<NcSelect :value="estadoFiltro"
					class="span-2"
					:input-label="t('empleados', 'Status')"
					:options="estadoFiltroOptions"
					label="label"
					track-by="value"
					:searchable="false"
					:clearable="false"
					@input="$emit('update:estadoFiltro', $event)" />

				<div class="special-client-card span-2">
					<NcCheckboxRadioSwitch :checked="onlySpecial"
						type="switch"
						@update:checked="$emit('update:onlySpecial', $event)" />
					<div class="special-client-info">
						<h3>{{ t('empleados', 'Only Special Clients') }}</h3>
						<p>{{ t('empleados', 'Show only clients marked as special.') }}</p>
					</div>
				</div>
			</div>

			<div class="modal-actions">
				<NcButton @click="$emit('reset')">
					{{ t('empleados', 'Clear filters') }}
				</NcButton>
				<NcButton type="primary" @click="$emit('close')">
					{{ t('empleados', 'Apply') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import {
	NcModal,
	NcButton,
	NcSelect,
	NcCheckboxRadioSwitch,
} from '@nextcloud/vue'

export default {
	name: 'ModalFiltrosLista',

	components: {
		NcModal,
		NcButton,
		NcSelect,
		NcCheckboxRadioSwitch,
	},

	props: {
		open: { type: Boolean, default: false },
		sortOrder: { type: Object, default: null },
		tipoFiltro: { type: Object, default: null },
		estadoFiltro: { type: Object, default: null },
		onlySpecial: { type: Boolean, default: false },
	},

	data() {
		return {
			sortOrderOptions: [
				{ label: t('empleados', 'A to Z'), value: 'az' },
				{ label: t('empleados', 'Z to A'), value: 'za' },
			],
			tipoFiltroOptions: [
				{ label: t('empleados', 'All customers'), value: 'todos' },
				{ label: t('empleados', 'Only Main Groups'), value: 'grupos' },
				{ label: t('empleados', 'Only subsidiaries'), value: 'subsidiarias' },
			],
			estadoFiltroOptions: [
				{ label: t('empleados', 'Active'), value: 'activos' },
				{ label: t('empleados', 'Only Disabled'), value: 'inactivos' },
				{ label: t('empleados', 'All'), value: 'todos' },
			],
		}
	},

	methods: { t },
}
</script>

<style scoped lang="scss">
@import './modales-comunes.scss';
</style>
