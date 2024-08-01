<!-- eslint-disable object-curly-newline -->
<template>
	<div class="contacts-list__item-wrapper">
		<div v-if="Object.keys( data ).length == 0">
			<div class="emptycontent">
				<img src="../../../../../img/crowesito-think.png" width="170px">
				<h2>Selecciona un area para mas detalles</h2>
			</div>
		</div>
		<div v-else class="container">
			<div>
				<div class="container-search-profile">
					<div class="button-container-profile">
						<NcActions>
							<template #icon>
								<AccountCog :size="20" />
							</template>
							<NcActionButton @click="showEdit">
								<template #icon>
									<AccountEdit :size="20" />
								</template>
								Habilitar edicion
							</NcActionButton>
							<NcActionSeparator />
							<NcActionButton @click="showEdit">
								<template #icon>
									<AccountEdit :size="20" />
								</template>
								Exportar
							</NcActionButton>
							<NcActionButton @click="showEdit">
								<template #icon>
									<AccountEdit :size="20" />
								</template>
								Mostrar archivos en explorador
							</NcActionButton>
							<NcActionButton @click="showEdit">
								<template #icon>
									<AccountEdit :size="20" />
								</template>
								Enviar correo
							</NcActionButton>
							<NcActionButton @click="showEdit">
								<template #icon>
									<AccountEdit :size="20" />
								</template>
								Deshablitar usuario
							</NcActionButton>
						</NcActions>
					</div>
				</div>
			</div>
			<div class="center">
				<h2>{{ data }}</h2>
				<p>{{ peopleArea }}</p>
			</div>
		</div>
	</div>
</template>

<script>
// ICONOS
import AccountEdit from 'vue-material-design-icons/AccountEdit.vue'
import AccountCog from 'vue-material-design-icons/AccountCog.vue'
// import Cog from 'vue-material-design-icons/Cog.vue'

// import { generateUrl } from '@nextcloud/router'
// import axios from '@nextcloud/axios'
// import { showError /* showSuccess */ } from '@nextcloud/dialogs'

import {
	// NcAvatar,
	NcActions,
	NcActionButton,
	NcActionSeparator,
	// NcProgressBar,
} from '@nextcloud/vue'

export default {
	name: 'AreasDetails',

	components: {
		// NcAvatar,
		NcActionSeparator,
		NcActions,
		AccountCog,
		AccountEdit,
		NcActionButton,
	},

	props: {
		data: {
			type: Object,
			required: true,
		},
		peopleArea: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			show: false,
			Empleados: [],
		}
	},

	mounted() {
		this.$root.$on('show', (data) => {
			this.show = data
		})
	},

	methods: {
		showEdit() {
			this.show = !this.show
			if (this.show === true) {
				// eslint-disable-next-line no-console
				console.log('aaaaaaaaaaaaaaaaa')
				// this.getall()
			}
		},
	},
}
</script>
<style>
.button-container-profile {
  float: right;
  margin-top: -10px;
}
.container {
  margin-right: 10px;
  margin-left: 10px;
  margin-top: 20px;
  align-items: center;
}
</style>
