/**
 * Conflictos cómicos y conversaciones/grupos espontáneos.
 * Timers ligeros; sin DOM.
 */

import {
	INTERACTION_TYPES,
	SIMULATION_CONFIG,
	isOfficeSimDebugEnabled,
} from './officeSimulationConfig.js'

const SOCIAL_ZONES = new Set(['coffee', 'lounge', 'printer', 'meeting', 'reception', 'corridor'])

/**
 * Inicializa estado lógico y visual usando un único contrato de tipos.
 * @param {object} a Entidad A
 * @param {object} b Entidad B
 * @param {string} type Tipo de interacción
 * @param {{duration?: number, reason?: string, celebrating?: boolean, icon?: string}} options Opciones
 * @return {number} Duración lógica en segundos
 */
export function startSocialInteraction(a, b, type, options = {}) {
	const celebrating = options.celebrating === true
	let duration = Number(options.duration) || 0
	let icon = null

	a.currentInteraction = type
	b.currentInteraction = type
	a.interactionPartnerId = b.id
	b.interactionPartnerId = a.id
	a.interactionPartnerUid = b.uid || null
	b.interactionPartnerUid = a.uid || null

	if (type === INTERACTION_TYPES.CONVERSATION) {
		duration = duration || SIMULATION_CONFIG.social.conversationDurationMin
			+ Math.random() * (
				SIMULATION_CONFIG.social.conversationDurationMax
				- SIMULATION_CONFIG.social.conversationDurationMin
			)
			+ (celebrating ? 1.2 : 0)
		const cooldown = SIMULATION_CONFIG.social.cooldownMin
			+ Math.random() * (SIMULATION_CONFIG.social.cooldownMax - SIMULATION_CONFIG.social.cooldownMin)
		a.interactionCooldown = cooldown
		b.interactionCooldown = cooldown
		a.pauseTimer = Math.max(a.pauseTimer || 0, duration * 0.85)
		b.pauseTimer = Math.max(b.pauseTimer || 0, duration * 0.85)
		a.facingAngle = Math.atan2(b.y - a.y, b.x - a.x)
		b.facingAngle = Math.atan2(a.y - b.y, a.x - b.x)
		a.vx *= 0.2
		a.vy *= 0.2
		b.vx *= 0.2
		b.vy *= 0.2
		icon = '💬'
	} else if (type === INTERACTION_TYPES.CONFLICT) {
		duration = duration || 1.4 + Math.random() * 0.8
		const cooldown = SIMULATION_CONFIG.conflicts.cooldownMin
			+ Math.random() * (SIMULATION_CONFIG.conflicts.cooldownMax - SIMULATION_CONFIG.conflicts.cooldownMin)
		a.conflictTimer = duration
		b.conflictTimer = duration
		a.conflictCooldown = cooldown
		b.conflictCooldown = cooldown
		a.interactionCooldown = cooldown
		b.interactionCooldown = cooldown
		a.pauseTimer = Math.max(a.pauseTimer || 0, 0.6)
		b.pauseTimer = Math.max(b.pauseTimer || 0, 0.6)
		const dist = Math.hypot(b.x - a.x, b.y - a.y) || 1
		const nx = (b.x - a.x) / dist
		const ny = (b.y - a.y) / dist
		a.vx -= nx * 1.8
		a.vy -= ny * 1.8
		b.vx += nx * 1.8
		b.vy += ny * 1.8
		a.facingAngle = Math.atan2(ny, nx)
		b.facingAngle = Math.atan2(-ny, -nx)
		icon = options.icon || (Math.random() < 0.65 ? '💢' : '!')
	} else {
		duration = duration || 0.55
		a.interactionCooldown = 3 + Math.random() * 4
		b.interactionCooldown = 3 + Math.random() * 4
		icon = '!'
	}

	a.interactionTimer = duration
	b.interactionTimer = duration
	const visualDuration = type === INTERACTION_TYPES.CONVERSATION
		? duration
		: (type === INTERACTION_TYPES.CONFLICT ? Math.max(1.6, duration) : 0.7)
	for (const entity of [a, b]) {
		entity.visualReactionType = type
		entity.visualReactionIcon = icon
		entity.visualReactionTimer = visualDuration
		entity.visualReactionDuration = visualDuration
	}

	if (isOfficeSimDebugEnabled() && typeof console !== 'undefined') {
		console.debug(`[OfficeSim ${type}]`, {
			a: a.uid,
			b: b.uid,
			reason: options.reason || 'proximity',
			duration,
		})
	}
	return duration
}

/**
 * Inicia interacción entre dos nodos cercanos.
 * @param {object} a Entidad A
 * @param {object} b Entidad B
 * @param {number} dist Distancia
 * @param {number} minDist Distancia mínima
 * @param {object} [ctx] Contexto opcional { densityBoost, contestedSpot }
 */
export function maybeStartSocialInteraction(a, b, dist, minDist, ctx = {}) {
	if (a.interactionCooldown > 0 || b.interactionCooldown > 0) return
	if (a.conflictCooldown > 0 || b.conflictCooldown > 0) return
	if (a.currentInteraction || b.currentInteraction) return
	if (a.socialProbeCooldown > 0 || b.socialProbeCooldown > 0) return
	if (dist > minDist * SIMULATION_CONFIG.social.interactionRadiusFactor) return
	if ((a.mobilityMultiplier ?? 1) < 0.12 || (b.mobilityMultiplier ?? 1) < 0.12) return
	const probeCooldown = SIMULATION_CONFIG.social.probeCooldownMin
		+ Math.random() * (
			SIMULATION_CONFIG.social.probeCooldownMax
			- SIMULATION_CONFIG.social.probeCooldownMin
		)
	a.socialProbeCooldown = probeCooldown
	b.socialProbeCooldown = probeCooldown
	if (a.userStatus === 'offline' || b.userStatus === 'offline') {
		if (Math.random() < (a.userStatus === b.userStatus ? 0.82 : 0.62)) return
	}

	if (a.mood === 'sick' || b.mood === 'sick' || a.mood === 'vacation' || b.mood === 'vacation') {
		if (Math.random() < 0.88) return
	}
	if (a.userStatus === 'dnd' || b.userStatus === 'dnd') {
		if (Math.random() < 0.82) return
	}

	const sameZone = a.destinationId && a.destinationId === b.destinationId
	const zoneId = a.destinationId || a.currentZone || b.currentZone
	const socialZone = SOCIAL_ZONES.has(zoneId) || sameZone
	const density = ctx.densityBoost || 0
	const contested = ctx.contestedSpot === true
	const celebrating = !!(a.eventMood || b.eventMood)

	const roll = Math.random()
	let type = null

	// Conflicto: spot disputado, densidad alta, choque cercano
	const conflictChance = contested
		? 0.008
		: (density >= SIMULATION_CONFIG.conflicts.congestedZoneSize
			? SIMULATION_CONFIG.conflicts.crowdedChance
			: SIMULATION_CONFIG.conflicts.baseChance)
			+ (dist < minDist * 0.85 ? SIMULATION_CONFIG.conflicts.closeCollisionBonus : 0)

	if (roll < conflictChance) {
		type = INTERACTION_TYPES.CONFLICT
	} else if (socialZone && roll < conflictChance
		+ SIMULATION_CONFIG.social.zoneConversationChance + (celebrating ? 0.05 : 0)) {
		type = INTERACTION_TYPES.CONVERSATION
	} else if (roll < conflictChance + SIMULATION_CONFIG.social.ambientConversationChance) {
		type = INTERACTION_TYPES.CONVERSATION
	} else if (roll < conflictChance + 0.055) {
		type = INTERACTION_TYPES.BUMP
	}

	if (!type) return
	const reason = contested
		? 'contested-spot'
		: (density >= SIMULATION_CONFIG.conflicts.congestedZoneSize ? 'zone-congestion' : 'proximity')
	startSocialInteraction(a, b, type, { celebrating, reason })
}

/**
 * Un tercero puede unirse a un chat cercano.
 * @param {object} entity Candidato
 * @param {Array} entities Todos
 * @param {number} tNow Tiempo
 */
export function maybeJoinChatGroup(entity, entities, tNow) {
	if (entity.currentInteraction || entity.interactionCooldown > 0) return
	if (entity.userStatus === 'dnd') return
	if (entity.userStatus === 'offline' && Math.random() < 0.85) return
	if (['meeting', 'focused', 'sick'].includes(entity.behavior)) return
	if (Math.random() > SIMULATION_CONFIG.social.joinGroupChance) return

	for (const other of entities) {
		if (other === entity) continue
		if (other.currentInteraction !== INTERACTION_TYPES.CONVERSATION) continue
		const dist = Math.hypot(other.x - entity.x, other.y - entity.y)
		if (dist > SIMULATION_CONFIG.social.joinRadius) continue
		const nearbyGroupSize = entities.filter((candidate) => candidate.currentInteraction === INTERACTION_TYPES.CONVERSATION
			&& Math.hypot(other.x - candidate.x, other.y - candidate.y)
				<= SIMULATION_CONFIG.social.joinRadius).length
		if (nearbyGroupSize >= SIMULATION_CONFIG.social.maxGroupSize) continue
		if (Math.random() > 0.35) continue

		entity.currentInteraction = INTERACTION_TYPES.CONVERSATION
		entity.interactionPartnerId = other.id
		entity.interactionPartnerUid = other.uid || null
		entity.interactionTimer = 2 + Math.random() * 2.5
		entity.visualReactionType = INTERACTION_TYPES.CONVERSATION
		entity.visualReactionIcon = '💬'
		entity.visualReactionTimer = entity.interactionTimer
		entity.visualReactionDuration = entity.interactionTimer
		entity.interactionCooldown = 10
		entity.pauseTimer = Math.max(entity.pauseTimer || 0, entity.interactionTimer * 0.8)
		entity.facingAngle = Math.atan2(other.y - entity.y, other.x - entity.x)
		entity.socialClusterUntil = tNow + entity.interactionTimer
		break
	}
}
