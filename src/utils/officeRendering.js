/** Capa visual de interacciones. No muta nodos ni depende de Vue. */

import { INTERACTION_TYPES } from './officeSimulationConfig.js'

function clamp(value, min, max) {
	return Math.max(min, Math.min(max, value))
}

export function visibleInteractionType(entity) {
	if (entity.currentInteraction === INTERACTION_TYPES.CONVERSATION && entity.interactionTimer > 0) {
		return INTERACTION_TYPES.CONVERSATION
	}
	if (entity.visualReactionTimer > 0 && entity.visualReactionType) {
		return entity.visualReactionType
	}
	if (entity.conflictTimer > 0) return INTERACTION_TYPES.CONFLICT
	return null
}

/**
 * Conexión corta entre participantes reales de una conversación activa.
 * @param {CanvasRenderingContext2D} ctx Contexto
 * @param {Array<object>} entities Entidades
 * @param {{alphaFor?: Function}} options Opciones
 */
export function drawConversationConnections(ctx, entities, options = {}) {
	const byId = new Map(entities.map((entity) => [entity.id, entity]))
	const drawnPairs = new Set()
	for (const entity of entities) {
		if (entity.currentInteraction !== INTERACTION_TYPES.CONVERSATION) continue
		const partner = byId.get(entity.interactionPartnerId)
		if (!partner || partner.currentInteraction !== INTERACTION_TYPES.CONVERSATION) continue
		const pairKey = [String(entity.id), String(partner.id)].sort().join(':')
		if (drawnPairs.has(pairKey)) continue
		drawnPairs.add(pairKey)
		const dx = partner.x - entity.x
		const dy = partner.y - entity.y
		const distance = Math.hypot(dx, dy)
		if (distance < 1 || distance > 110) continue
		const nx = dx / distance
		const ny = dy / distance
		const startPad = entity.radius + 4
		const endPad = partner.radius + 4
		const alpha = Math.min(
			options.alphaFor?.(entity) ?? 1,
			options.alphaFor?.(partner) ?? 1,
		)

		ctx.save()
		ctx.globalAlpha = Math.max(0.22, alpha * 0.55)
		ctx.strokeStyle = 'rgba(55, 75, 95, 0.75)'
		ctx.lineWidth = 1.2
		ctx.setLineDash([2, 4])
		ctx.beginPath()
		ctx.moveTo(entity.x + nx * startPad, entity.y + ny * startPad)
		ctx.lineTo(partner.x - nx * endPad, partner.y - ny * endPad)
		ctx.stroke()
		ctx.restore()
	}
}

/**
 * Dibuja el slot social arriba-izquierda, siempre después de los avatares.
 * @param {CanvasRenderingContext2D} ctx Contexto
 * @param {object} entity Entidad
 * @param {number} nowMs Tiempo de animación en milisegundos
 * @param {number} alpha Alpha efectivo
 * @return {boolean} Si se dibujó un indicador
 */
export function drawSocialIndicator(ctx, entity, nowMs, alpha = 1) {
	const type = visibleInteractionType(entity)
	if (!type) return false

	const fontSize = clamp(entity.radius * 1.08, 15, 22)
	const bob = Math.sin(nowMs / 260 + Number(entity.id || 0) * 0.9) * 2
	const groupOffset = ((Number(entity.id || 0) % 3) - 1) * 2
	const icon = type === INTERACTION_TYPES.CONVERSATION
		? '💬'
		: (entity.visualReactionIcon || (type === INTERACTION_TYPES.CONFLICT ? '💢' : '!'))

	ctx.save()
	ctx.globalAlpha = Math.max(0.42, alpha)
	ctx.font = `${type === INTERACTION_TYPES.CONFLICT ? 'bold ' : ''}${fontSize}px sans-serif`
	ctx.textAlign = 'center'
	ctx.textBaseline = 'middle'
	ctx.fillStyle = type === INTERACTION_TYPES.CONFLICT ? '#b83227' : '#263746'
	ctx.shadowColor = 'rgba(255,255,255,0.9)'
	ctx.shadowBlur = 3
	ctx.fillText(
		icon,
		entity.x - entity.radius - 7 + groupOffset,
		entity.y - entity.radius + 1 + bob,
	)
	ctx.restore()
	return true
}

export function collectSocialDebugStats(entities) {
	const pairs = new Set()
	const conflicts = new Set()
	const graph = new Map()
	let visualReactions = 0

	for (const entity of entities) {
		if (entity.visualReactionTimer > 0) visualReactions++
		if (!entity.interactionPartnerId) continue
		const pair = [String(entity.id), String(entity.interactionPartnerId)].sort().join(':')
		if (entity.currentInteraction === INTERACTION_TYPES.CONVERSATION) {
			pairs.add(pair)
			if (!graph.has(entity.id)) graph.set(entity.id, new Set())
			if (!graph.has(entity.interactionPartnerId)) graph.set(entity.interactionPartnerId, new Set())
			graph.get(entity.id).add(entity.interactionPartnerId)
			graph.get(entity.interactionPartnerId).add(entity.id)
		} else if (entity.currentInteraction === INTERACTION_TYPES.CONFLICT) {
			conflicts.add(pair)
		}
	}

	let groupsActive = 0
	const visited = new Set()
	for (const id of graph.keys()) {
		if (visited.has(id)) continue
		const pending = [id]
		let size = 0
		while (pending.length) {
			const current = pending.pop()
			if (visited.has(current)) continue
			visited.add(current)
			size++
			for (const neighbor of graph.get(current) || []) pending.push(neighbor)
		}
		if (size >= 3) groupsActive++
	}

	return {
		conversationsActive: pairs.size,
		conflictsActive: conflicts.size,
		groupsActive,
		visualReactions,
		activeKeys: new Set([...pairs].map((key) => `conversation:${key}`)
			.concat([...conflicts].map((key) => `conflict:${key}`))),
	}
}

/**
 * Overlay fijo en pantalla, fuera de la transformación de cámara.
 * @param {CanvasRenderingContext2D} ctx Contexto
 * @param {object} stats Contadores activos
 * @param {object} totals Contadores acumulados
 */
export function drawSocialDebugOverlay(ctx, stats, totals = {}) {
	const lines = [
		'Social debug',
		`Conversations active: ${stats.conversationsActive}`,
		`Conflicts active: ${stats.conflictsActive}`,
		`Groups active: ${stats.groupsActive}`,
		`Visual reactions: ${stats.visualReactions}`,
		`Conversation total: ${totals.conversations || 0}`,
		`Conflict total: ${totals.conflicts || 0}`,
	]
	ctx.save()
	ctx.globalAlpha = 0.9
	ctx.fillStyle = 'rgba(25, 33, 43, 0.88)'
	ctx.fillRect(10, 10, 190, 18 + lines.length * 16)
	ctx.fillStyle = '#fff'
	ctx.font = '12px monospace'
	ctx.textAlign = 'left'
	ctx.textBaseline = 'top'
	lines.forEach((line, index) => ctx.fillText(line, 18, 18 + index * 16))
	ctx.restore()
}
