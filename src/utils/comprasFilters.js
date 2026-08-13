export function buildPurchaseListParams({
	showOnlyMine,
	canViewAll,
	estado,
	page,
	pageSize,
}) {
	const normalizedPage = Math.max(1, Number(page) || 1)
	const normalizedPageSize = Math.max(1, Number(pageSize) || 20)
	const params = {
		todas: canViewAll && !showOnlyMine ? 1 : 0,
		limit: normalizedPageSize,
		offset: (normalizedPage - 1) * normalizedPageSize,
	}

	if (estado && estado !== 'todos') {
		params.estado = estado
	}

	return params
}
