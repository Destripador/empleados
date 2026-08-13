export function normalizeSupportDuration(hours, minutes) {
	const normalizedHours = Number(hours)
	const normalizedMinutes = Number(minutes)
	if (!Number.isInteger(normalizedHours) || normalizedHours < 0) return null
	if (!Number.isInteger(normalizedMinutes) || normalizedMinutes < 0 || normalizedMinutes > 59) return null
	const total = normalizedHours * 60 + normalizedMinutes
	return total > 0 && total <= 1440 ? total : null
}

export function splitSupportDuration(totalMinutes) {
	const total = Number(totalMinutes)
	if (!Number.isInteger(total) || total <= 0) return { hours: 0, minutes: 0 }
	return { hours: Math.floor(total / 60), minutes: total % 60 }
}

export function formatSupportDuration(totalMinutes) {
	const parts = splitSupportDuration(totalMinutes)
	if (parts.hours === 0 && parts.minutes === 0) return ''
	return [parts.hours > 0 ? `${parts.hours} h` : '', parts.minutes > 0 ? `${parts.minutes} min` : '']
		.filter(Boolean)
		.join(' ')
}

export function isValidSupportDate(value) {
	if (typeof value !== 'string' || value.trim() === '') return false
	return !Number.isNaN(new Date(value).getTime())
}
