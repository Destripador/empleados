<template>
	<div>
		<NcModal v-if="open"
			size="large"
			:name="title"
			@close="$emit('update:open', false)">
			<slot />
		</NcModal>

		<div class="floating-help-button">
			<NcActions>
				<NcActionButton @click="$emit('update:open', true)">
					<template #icon>
						<component :is="icon" :size="24" />
					</template>
					{{ title }}
				</NcActionButton>
			</NcActions>
		</div>
	</div>
</template>

<script>
import { NcModal, NcActions, NcActionButton } from '@nextcloud/vue'

export default {
	name: 'FloatingHelpButton',
	components: { NcModal, NcActions, NcActionButton },
	props: {
		open: { type: Boolean, default: false },
		title: { type: String, required: true },
		icon: { type: [Object, Function], required: true },
	},
}
</script>

<style scoped lang="scss">
.floating-help-button {
	position: fixed;
	right: 24px;
	bottom: 24px;
	z-index: 10000;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 64px;
	height: 64px;
	padding: 4px;
	background-color: white;
	border: 1px solid #cbd5e0;
	border-radius: 50%;
	box-shadow: 0 8px 20px rgba(0, 0, 0, 0.22), 0 2px 6px rgba(0, 0, 0, 0.15);
	transition: transform 0.2s ease, box-shadow 0.2s ease;

	&:hover {
		transform: scale(1.08);
		box-shadow: 0 10px 25px rgba(0, 0, 0, 0.28), 0 3px 8px rgba(0, 0, 0, 0.18);
	}
}
</style>
