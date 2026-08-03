export function canApprovePurchaseFlow(flow) {
	return flow?.can_approve === true
}

export function canRejectPurchaseFlow(flow) {
	return flow?.can_reject === true
}

export function createSingleFlight() {
	let pending = false

	return {
		isPending() {
			return pending
		},

		async run(action) {
			if (pending) {
				return false
			}

			pending = true
			try {
				await action()
				return true
			} finally {
				pending = false
			}
		},
	}
}
