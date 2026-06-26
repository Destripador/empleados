<template id="List">
	<NcAppContent v-if="loading" name="Loading">
		<NcEmptyContent class="empty-content" :name="t('empleados', 'Loading')">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>
	</NcAppContent>

	<NcAppContent v-else name="Loading">
		<!-- contacts list -->
		<template #list>
			<FullList
				:listas="listas"
				:reload-bus="reloadBus"
				:defaultbuttons="defaultbuttons">
				<template #custombuttons>
					<slot name="custombuttons" />
				</template>
			</FullList>
		</template>
		<!-- main details -->
		<div class="Details">
			<div class="contacts-list__item-wrapper">
				<div v-if="custom == true && Object.keys(select).length == 0">
					<slot name="custom" />
				</div>
				<div v-else-if="custom == false && Object.keys(select).length == 0">
					<div class="emptycontent">
						<DatabaseSearchOutline :size="60" />
						<h2>{{ t('empleados', 'Select something') }}</h2>
					</div>
				</div>
				<div v-else>
					<div>
						<div class="position-hero__actions">
							<div v-if="showOptions" class="button-container">
								<NcActions>
									<template #icon>
										<AccountCog :size="20" />
									</template>

									<NcActionButton
										v-if="showToggleEstado"
										:close-after-click="true"
										@click="$root.$emit('toggleEstado')">
										<template #icon>
											<EyeOffOutline :size="20" />
										</template>
										{{ toggleEstadoLabel }}
									</NcActionButton>
									<NcActionSeparator v-if="showToggleEstado" />

									<slot name="buttons" />
									<NcActionButton
										:close-after-click="true"
										@click="edit()">
										<template #icon>
											<AccountEdit :size="20" />
										</template>
										{{ t('empleados', 'Enable editing') }}
									</NcActionButton>
									<NcActionSeparator />
									<NcActionButton
										:close-after-click="true"
										@click="showDialog = true">
										<template #icon>
											<DeleteAlert :size="20" />
										</template>
										{{ t('empleados', 'Delete') }}
									</NcActionButton>
									<NcDialog
										:open.sync="showDialog"
										:name="t('empleados', 'Confirm')"
										:message="t('empleados', 'Do you want to delete this item?')"
										:buttons="buttons" />
								</NcActions>
							</div>
						</div>
					</div>
					<slot name="details" />
				</div>
			</div>
		</div>
	</NcAppContent>
</template>

<script>
// ICONOS
import DatabaseSearchOutline from 'vue-material-design-icons/DatabaseSearchOutline.vue'
import DeleteAlert from 'vue-material-design-icons/DeleteAlert.vue'
import AccountEdit from 'vue-material-design-icons/AccountEdit.vue'
import AccountCog from 'vue-material-design-icons/AccountCog.vue'
import EyeOffOutline from 'vue-material-design-icons/EyeOffOutline.vue'

// agregados
import FullList from './FullList.vue'

// import axios from '@nextcloud/axios'
import mitt from 'mitt'
import { translate as t } from '@nextcloud/l10n'

import {
	NcEmptyContent,
	NcAppContent,
	NcLoadingIcon,
	NcActionButton,
	// NcAvatar,
	NcActions,
	NcActionSeparator,
	NcDialog,
	// NcTextField,
	// NcSelect,
	// NcButton,
	// NcListItem,
	// NcModal,
} from '@nextcloud/vue'

export default {
	name: 'List',
	components: {
		FullList,
		NcEmptyContent,
		NcAppContent,
		NcLoadingIcon,
		NcActionButton,
		// NcAvatar,
		NcActionSeparator,
		NcActions,
		AccountCog,
		AccountEdit,
		// NcActionButton,
		DeleteAlert,
		NcDialog,
		// NcTextField,
		// NcSelect,
		// NcButton,
		// NcListItem,
		// NcModal,
		// AreasDetails,
		DatabaseSearchOutline,
		EyeOffOutline,
	},

	props: {
		loading: { type: Boolean, required: true },
		listas: { type: Array, required: true },
		select: { type: Array, required: true },
		custom: { type: Boolean, default: false, required: false },
		defaultbuttons: { type: Boolean, default: true, required: false },
		showOptions: { type: Boolean, default: false, required: false },
		showToggleEstado: { type: Boolean, default: false, required: false },
		toggleEstadoLabel: { type: String, default: 'Toggle status' },
		// reloadBus: { type: Object, required: true },
	},

	data() {
		return {
			reloadBus: mitt(),
			showDialog: false,
		}
	},

	computed: {
		buttons() {
			return [
				{
					label: this.t('empleados', 'Cancelar'),
					callback: () => { this.lastResponse = 'Pressed "Cancel"' },
				},
				{
					label: this.t('empleados', 'Eliminar'),
					type: 'primary',
					callback: () => { this.delete() },
				},
			]
		},
	},

	async mounted() {
		window.addEventListener('keydown', this.onKeyDown)
	},

	methods: {
		t, // exponer i18n a la plantilla
		 onKeyDown(e) {
			if (e.key === 'Escape') this.onEsc()
		},

		onEsc() {
			this.showDialog = false
		},
		delete() {
			this.showDialog = false
			this.$root.$emit('delete')
		},
		edit() {
			this.$root.$emit('edit')
		},
	},
}
</script>

<style scoped lang="scss">
	.container {
		padding-left: 5px;
	}
	.board-title {
		padding-left: 60px;
		margin-right: 10px;
		margin-top: 14px;
		font-size: 25px;
		display: flex;
		align-items: center;
		font-weight: bold;
		.icon {
			margin-right: 8px;
		}
	}
</style>
