/**
 * Parámetros ajustables de Office Simulation.
 * Mantener aquí solo valores que cambian la experiencia, no constantes de dibujo.
 */
export const SIMULATION_CONFIG = Object.freeze({
	layout: Object.freeze({
		margin: 30,
		roomGap: 14,
		minRoomGap: 8,
		topBandRatio: 0.2,
		topBandMin: 82,
		topBandMax: 126,
		maxRoomScale: 1.28,
		minRoomScale: 0.5,
		employeeCellArea: 1650,
		baseRoomArea: 3200,
		capacityPaddingRatio: 0.15,
	}),
	movement: Object.freeze({
		decisionMinSeconds: 8,
		decisionMaxSeconds: 35,
		statusDecisionMinSeconds: 18,
		statusDecisionMaxSeconds: 50,
		spotRetryMinSeconds: 3,
		spotRetryMaxSeconds: 7,
		arrivalDistance: 14,
	}),
	social: Object.freeze({
		interactionRadiusFactor: 1.55,
		zoneConversationChance: 0.015,
		ambientConversationChance: 0.004,
		conversationDurationMin: 3.2,
		conversationDurationMax: 6.8,
		cooldownMin: 7,
		cooldownMax: 15,
		joinGroupChance: 0.014,
		joinRadius: 62,
		maxGroupSize: 3,
		probeCooldownMin: 0.55,
		probeCooldownMax: 1.2,
	}),
	conflicts: Object.freeze({
		baseChance: 0.0002,
		crowdedChance: 0.0015,
		congestedZoneSize: 5,
		closeCollisionBonus: 0.0005,
		cooldownMin: 22,
		cooldownMax: 42,
	}),
	spots: Object.freeze({
		meeting: 8,
		coffee: 6,
		lounge: 5,
		printer: 3,
		reception: 3,
	}),
})

export const INTERACTION_TYPES = Object.freeze({
	CONVERSATION: 'conversation',
	CONFLICT: 'conflict',
	BUMP: 'bump',
})

/** Debug opt-in; nunca se activa por defecto. */
export function isOfficeSimDebugEnabled() {
	if (typeof window === 'undefined') return false
	if (window.OFFICE_SIM_DEBUG === true) return true
	try {
		const hashQuery = String(window.location.hash || '').split('?')[1] || ''
		return window.localStorage?.getItem('OFFICE_SIM_DEBUG') === '1'
			|| new URLSearchParams(window.location.search).get('officeSimDebug') === '1'
			|| new URLSearchParams(hashQuery).get('officeSimDebug') === '1'
	} catch (_error) {
		return false
	}
}
