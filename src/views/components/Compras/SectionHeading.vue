<template>
	<header class="section-heading">
		<h3>{{ title }}</h3>
		<p v-if="subtitle">
			{{ subtitle }}
		</p>
	</header>
</template>

<script>
export default {
	name: 'SectionHeading',
	props: {
		title: {
			type: String,
			required: true,
		},
		subtitle: {
			type: String,
			default: '',
		},
	},
}
</script>

<style scoped>
.section-heading {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 14px;
}

.section-heading h3,
.section-heading p {
	margin: 0;
}

.section-heading h3 {
	font-size: 18px;
}

.section-heading p {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}
</style>
