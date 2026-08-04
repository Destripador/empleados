<template>
	<div class="mensaje-equipos">
		<h2>{{ t('empleados', 'Team information') }}</h2>
		<p>{{ t('empleados', 'Here you will find information about your teams.') }}</p>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'MensajeEquipos',
	methods: { t },
}
</script>
