<template>
	<div class="mensaje-areas">
		<h2>{{ t('empleados', 'Areas information') }}</h2>
		<p>{{ t('empleados', 'Here you will find information about your areas.') }}</p>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'MensajeAreas',
	methods: { t },
}
</script>
