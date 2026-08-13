export function parseInventoryDeviceId(value) {
	if (Array.isArray(value) || typeof value === 'object' || value === null || value === undefined) {
		return null
	}

	const normalized = String(value).trim()
	if (!/^[1-9]\d*$/.test(normalized)) {
		return null
	}

	const id = Number(normalized)
	return Number.isSafeInteger(id) ? id : null
}
