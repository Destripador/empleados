<template>
	<div class="support-duration">
		<div class="support-duration__fields">
			<NcTextField
				:value.sync="hoursValue"
				type="number"
				min="0"
				step="1"
				inputmode="numeric"
				:disabled="disabled"
				:label="t('empleados', 'Hours')" />
			<NcTextField
				:value.sync="minutesValue"
				type="number"
				min="0"
				max="59"
				step="1"
				inputmode="numeric"
				:disabled="disabled"
				:label="t('empleados', 'Minutes')" />
		</div>
		<p :class="['support-duration__summary', { 'support-duration__summary--invalid': total === null }]">
			{{ summary }}
		</p>
		<small>{{ t('empleados', 'The time will be added automatically to your reports as a non-billable activity.') }}</small>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcTextField } from '@nextcloud/vue'

import { formatSupportDuration, normalizeSupportDuration, splitSupportDuration } from '../../utils/supportDuration.js'

export default {
	name: 'SupportDurationFields',
	components: { NcTextField },
	props: {
		value: { type: Number, default: null },
		disabled: { type: Boolean, default: false },
	},
	data() {
		const parts = splitSupportDuration(this.value)
		return { hoursValue: parts.hours, minutesValue: parts.minutes }
	},
	computed: {
		total() {
			return normalizeSupportDuration(Number(this.hoursValue), Number(this.minutesValue))
		},
		summary() {
			return this.total === null
				? t('empleados', 'Enter a duration greater than zero.')
				: t('empleados', 'Recorded time: {duration}', { duration: formatSupportDuration(this.total) })
		},
	},
	watch: {
		hoursValue() { this.emitValue() },
		minutesValue() { this.emitValue() },
		value(value) {
			if (value === this.total) return
			const parts = splitSupportDuration(value)
			this.hoursValue = parts.hours
			this.minutesValue = parts.minutes
		},
	},
	methods: {
		t,
		emitValue() {
			this.$emit('input', this.total)
			this.$emit('valid', this.total !== null)
		},
	},
}
</script>

<style scoped>
.support-duration {
	display: grid;
	gap: 6px;
}

.support-duration__fields {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 10px;
}

.support-duration__summary {
	margin: 0;
	color: var(--color-success);
}

.support-duration__summary--invalid {
	color: var(--color-error);
}

.support-duration small {
	color: var(--color-text-maxcontrast);
}
</style>
