export function isPurchaseDetailReadOnly() {
	return true
}

export function getPurchaseDetailActions({ solicitud = {}, permissions = {}, flow = null, currentUserId = '' } = {}) {
	const state = String(solicitud.estado || '')
	const isOwner = String(solicitud.id_user || '') === String(currentUserId || '')
	const canManageRequest = permissions.can_select_requester === true
	const canManageDocuments = permissions.can_process_purchase === true && state === 'autorizada'
	const canModifyDraft = state === 'borrador' && (isOwner || canManageRequest)

	return {
		exportPdf: Boolean(solicitud.id_solicitud),
		savePdf: canManageDocuments,
		uploadSigned: canManageDocuments,
		edit: canModifyDraft,
		send: canModifyDraft,
		approve: flow?.can_approve === true,
		reject: flow?.can_reject === true,
		cancel: ['borrador', 'pendiente_autorizacion'].includes(state) && (isOwner || canManageRequest),
		close: true,
	}
}
