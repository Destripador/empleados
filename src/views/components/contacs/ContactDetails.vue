<template>
	<div class="contacts-list__item-wrapper">
		<div v-if="Object.keys( data ).length == 0">
			<div class="emptycontent">
				<img src="../../../../img/crowesito-think.png" width="170px">
				<h2>Selecciona un empleado para empezar</h2>
			</div>
		</div>
		<div v-else class="container">
			<div class="center">
				<NcAvatar :user="data.uid" :display-name="data.uid" :size="120" />
				<div v-if="data.displayname">
					<h2>{{ data.displayname }}</h2>
				</div>
				<div v-else>
					<h2>{{ data.uid }}</h2>
				</div>
			</div>
		</div>
	</div>
</template>

<script>

import {
	NcAvatar,
} from '@nextcloud/vue'

export default {
	name: 'ContactDetails',

	components: {
		NcAvatar,
	},

	props: {
		data: {
			type: Object,
			required: true,
		},
	},

}
</script>
<style lang="scss" scoped>

.envelope {
	.app-content-list-item-icon {
		height: 40px; // To prevent some unexpected spacing below the avatar
	}

	&__subtitle {
		display: flex;
		gap: 4px;

		&__subject {
			color: var(--color-main-text);
			line-height: 130%;
			overflow: hidden;
			text-overflow: ellipsis;
		}
	}
}

.list-item-style {
	list-style: none;
}

</style>
<style lang="scss">
.contacts-list__item-wrapper {
	&[draggable='true'] .avatardiv * {
		cursor: move !important;
	}

	&[draggable='false'] .avatardiv * {
		cursor: not-allowed !important;
	}
}
#emptycontent, .emptycontent {
    margin-top: 2vh;
    }
.center-screen {
		display: flex;
		justify-content: center;
		align-items: center;
		text-align: center;
		min-height: 100vh;
}
.container {
		margin-right: 10px;
		margin-left: 10px;
		margin-top: 20px;
		align-items: center;
	}
</style>
