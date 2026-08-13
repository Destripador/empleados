/**
 * Física e interacciones de la simulación de oficina.
 * Sin reactividad Vue: pensado para requestAnimationFrame.
 */

import {
	hasCelebrationEvent,
	normalizeDailyEvents,
	primaryCelebration,
	randomCelebrationReaction,
} from './dailyOfficeEvents.js'
import {
	buildOfficeLayout,
	buildWaypointPath,
	claimSpot,
	releaseSpot,
	resolveEmployeeAreaKey,
} from './officeLayout.js'
import {
	maybeJoinChatGroup,
	maybeStartSocialInteraction,
} from './officeSocialEvents.js'
import {
	pickPreferredRoamZone,
	resolveRoleProfile,
	resolveWorkAnchorZone,
} from './officeRoleProfiles.js'
import { INTERACTION_TYPES, SIMULATION_CONFIG } from './officeSimulationConfig.js'

export { colorForArea } from './officeLayout.js'

const ENERGY_MIN = 0.15
const ENERGY_MAX = 0.85
const REPORTS_SOFT_CAP = 40

const MOBILITY_BY_STATUS = {
	online: 1,
	away: 0.65,
	busy: 0.55,
	dnd: 0.45,
	offline: 0.15,
	invisible: 0.15,
}

const TARGET_BY_MOOD = {
	sick: 'lounge',
	vacation: 'lounge',
	meeting: 'meeting',
	call: 'meeting',
	break: 'coffee',
	away: 'coffee',
	commuting: 'printer',
	focused: 'home',
	remote: 'home',
	active: null,
	idle: null,
}

/** Zonas comunes tipo Habbo / oficina. */
export const COMMON_ZONE_DEFS = [
	{ id: 'coffee', emoji: '☕', color: '#C4A35A', x: 0.50, y: 0.16, radius: 70 },
	{ id: 'meeting', emoji: '📅', color: '#6B7B8C', x: 0.84, y: 0.48, radius: 68 },
	{ id: 'lounge', emoji: '🛋️', color: '#7A6B8C', x: 0.18, y: 0.78, radius: 72 },
	{ id: 'printer', emoji: '🖨️', color: '#4A8C7A', x: 0.52, y: 0.55, radius: 52 },
]

export function normalizeEnergy(reportes) {
	const n = Math.max(0, Number(reportes) || 0)
	const ratio = Math.min(1, n / REPORTS_SOFT_CAP)
	const curved = Math.sqrt(ratio)
	return ENERGY_MIN + curved * (ENERGY_MAX - ENERGY_MIN)
}

export function resolveEnergy(employee) {
	if (typeof employee?.activityScore === 'number' && !Number.isNaN(employee.activityScore)) {
		return Math.min(ENERGY_MAX + 0.07, Math.max(ENERGY_MIN, employee.activityScore))
	}
	return normalizeEnergy(employee?.reportesPeriodo)
}

export function statusPulseColor(status) {
	switch (status) {
	case 'online':
		return '#3FA66B'
	case 'away':
		return '#C4A35A'
	case 'busy':
	case 'dnd':
		return '#A15C6B'
	default:
		return null
	}
}

export function initialsFromName(name) {
	const parts = String(name || '')
		.trim()
		.split(/\s+/)
		.filter(Boolean)
	if (parts.length === 0) {
		return '?'
	}
	if (parts.length === 1) {
		return parts[0].slice(0, 2).toUpperCase()
	}
	return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

export function resolveStatusMood(employee) {
	const icon = String(employee?.statusIcon || '')
	const message = String(employee?.statusMessage || '').toLowerCase()
	const status = employee?.userStatus || 'offline'
	const blob = `${icon} ${message}`

	if (/🤒|🥴|🤢|😷|🤕|sick|enferm|out sick|indispuest/.test(blob)) {
		return 'sick'
	}
	if (/🌴|🏖️|⛱️|vacation|vacacion|holidays|ooo|out of office|🛑/.test(blob)) {
		return 'vacation'
	}
	if (/📅|meeting|reunión|reunion|junta|in a meeting/.test(blob)) {
		return 'meeting'
	}
	if (/💬|call|llamada|in a call|phone|teléfono|telefono/.test(blob)) {
		return 'call'
	}
	if (/🏡|🏠|remote|remot|home office|teletrabajo/.test(blob)) {
		return 'remote'
	}
	if (/🚌|🚗|commuting|traslad|camino|viaje/.test(blob)) {
		return 'commuting'
	}
	if (/☕|🍵|🍕|🍔|🍩|lunch|café|cafe|comida|break|descanso|tomando café|tomando cafe/.test(blob)) {
		return 'break'
	}
	if (/🛋|🛋️|lounge/.test(blob)) {
		return 'vacation'
	}
	if (/🖨|🖨️|printer|impresión|impresion/.test(blob)) {
		return 'commuting'
	}
	if (/focused|ocupado|concentrad|busy|dnd|no molestar/.test(blob)) {
		return 'focused'
	}
	if (status === 'away') {
		return 'away'
	}
	if (status === 'busy' || status === 'dnd') {
		return 'focused'
	}
	if (status === 'online') {
		return 'active'
	}
	return 'idle'
}

export function resolveBehavior(statusPayload) {
	const mood = resolveStatusMood({
		userStatus: statusPayload.status,
		statusIcon: statusPayload.icon,
		statusMessage: statusPayload.message,
	})
	const targetZone = TARGET_BY_MOOD[mood] ?? null
	return {
		mood,
		behavior: mood,
		targetZone,
		mobilityMultiplier: MOBILITY_BY_STATUS[statusPayload.status] ?? 0.8,
		priority: targetZone !== null,
	}
}

export function hasStatusChanged(oldStatus, newStatus) {
	return (oldStatus.status || 'offline') !== (newStatus.status || 'offline')
		|| (oldStatus.icon || null) !== (newStatus.icon || null)
		|| (oldStatus.message || null) !== (newStatus.message || null)
		|| (oldStatus.clearAt ?? null) !== (newStatus.clearAt ?? null)
}

export function buildCommonZones(width, height) {
	return buildOfficeLayout([], width, height).commonZones
}

/**
 * Escala suave (sqrt) del radio visual del área según headcount.
 * Evita desproporciones lineales y acota min/max.
 * @param {number} employeeCount Cantidad
 * @param {number} width Ancho
 * @param {number} height Alto
 */
export function areaVisualRadius(employeeCount, width, height) {
	const count = Math.max(1, Number(employeeCount) || 1)
	const softCap = 28
	const t = Math.min(1, Math.sqrt(count) / Math.sqrt(softCap))
	const base = Math.min(width, height)
	const minR = Math.max(34, base * 0.055)
	const maxR = Math.max(minR + 20, Math.min(118, base * 0.16))
	return minR + t * (maxR - minR)
}

export function buildAreaCenters(employees, width, height) {
	return buildOfficeLayout(employees, width, height).areaCenters
}

export { buildOfficeLayout }

function zoneById(commonZones, id) {
	return commonZones.find((z) => z.id === id) || null
}

function nowSec() {
	return (typeof performance !== 'undefined' ? performance.now() : Date.now()) / 1000
}

function scheduleNextDecision(
	entity,
	min = SIMULATION_CONFIG.movement.decisionMinSeconds,
	max = SIMULATION_CONFIG.movement.decisionMaxSeconds,
) {
	const energyBoost = entity.energy > 0.6 ? 0.75 : 1
	const lowMobilityFactor = (entity.mobilityMultiplier ?? 1) < 0.3 ? 1.35 : 1
	const adjustedMin = min * lowMobilityFactor
	const span = (max - min) * energyBoost * lowMobilityFactor
	entity.nextDecisionAt = nowSec() + adjustedMin + Math.random() * span
}

function applyPath(entity, path) {
	entity.waypoints = Array.isArray(path) ? path.slice() : []
	const next = entity.waypoints.shift()
	if (next) {
		entity.targetX = next.x
		entity.targetY = next.y
	}
}

/**
 * Asigna destino a zona/escritorio con spot + waypoints.
 * zoneKey 'home' respeta workAnchor del rol (p.ej. reception).
 * @param {object} entity Entidad
 * @param {string} zoneKey Zona
 * @param {Map} areaCenters Centros
 * @param {Array} commonZones Zonas
 * @param {object} options Opciones
 */
export function setDestinationForZone(entity, zoneKey, areaCenters, commonZones, options = {}) {
	const dwellBase = options.dwellBase ?? 2
	const urgent = options.urgent === true
	const layout = options.layout || null
	entity.previousTargetZone = entity.targetZone
	entity.dwellTimer = 0
	releaseSpot(layout, entity)
	entity.spotWaitUntil = 0
	entity._pendingConflictChance = false

	let resolvedKey = zoneKey || 'home'
	if (resolvedKey === 'home' || resolvedKey === 'homeArea') {
		const anchor = resolveWorkAnchorZone(entity)
		if (anchor && anchor !== 'home') {
			resolvedKey = anchor
		} else {
			resolvedKey = 'home'
		}
	}
	entity.targetZone = resolvedKey

	let dest = null
	let contested = false

	if (resolvedKey && resolvedKey !== 'home') {
		const zone = zoneById(commonZones, resolvedKey)
		if (zone) {
			entity.destinationType = 'zone'
			entity.destinationId = zone.id
			entity.currentZone = zone.id
			const claimed = claimSpot(layout, zone.id, entity.id, { x: entity.x, y: entity.y })
			if (claimed?.spot) {
				dest = { x: claimed.spot.x, y: claimed.spot.y }
				entity.spotId = claimed.spot.id
				contested = claimed.contested
			} else {
				const room = zone.room
				const spanX = Math.max(18, (room?.w || zone.radius * 2) * 0.55)
				const spanY = Math.max(18, (room?.h || zone.radius * 2) * 0.45)
				dest = {
					x: zone.x + (Math.random() - 0.5) * spanX,
					y: zone.y + (Math.random() - 0.5) * spanY,
				}
				if (claimed?.full) {
					entity.spotWaitUntil = nowSec()
						+ SIMULATION_CONFIG.movement.spotRetryMinSeconds
						+ Math.random() * (
							SIMULATION_CONFIG.movement.spotRetryMaxSeconds
							- SIMULATION_CONFIG.movement.spotRetryMinSeconds
						)
					entity._pendingConflictChance = true
				}
			}
			entity._pendingDwell = dwellBase + Math.random() * 4
			if (resolvedKey === 'meeting') {
				entity._pendingDwell = Math.max(entity._pendingDwell, 5 + Math.random() * 5)
			}
			if (resolvedKey === 'lounge') {
				entity._pendingDwell = Math.max(entity._pendingDwell, 3.5 + Math.random() * 4)
			}
			if (resolvedKey === 'reception') {
				entity._pendingDwell = Math.max(entity._pendingDwell, 4 + Math.random() * 4)
			}
			entity.intent = `go-to-${zone.id}`
			if (contested) {
				entity._pendingConflictChance = true
			}
			const path = buildWaypointPath(layout, entity, zone.id, dest)
			applyPath(entity, path)
			if (urgent) {
				entity.nextDecisionAt = nowSec() + 8 + Math.random() * 12
			} else if (entity.spotWaitUntil) {
				entity.nextDecisionAt = entity.spotWaitUntil
			}
			return
		}
	}

	// Visita ocasional a otra área organizacional (sin crear zonas artificiales).
	if (resolvedKey !== 'home' && areaCenters.has(resolvedKey)) {
		const area = areaCenters.get(resolvedKey)
		const claimed = claimSpot(layout, resolvedKey, entity.id, { x: entity.x, y: entity.y })
		entity.destinationType = 'area'
		entity.destinationId = resolvedKey
		entity.currentZone = resolvedKey
		if (claimed?.spot) {
			dest = { x: claimed.spot.x, y: claimed.spot.y }
			entity.spotId = claimed.spot.id
		} else {
			dest = {
				x: area.x + (Math.random() - 0.5) * area.radius,
				y: area.y + (Math.random() - 0.5) * area.radius,
			}
			if (claimed?.full) entity.spotWaitUntil = nowSec() + 3 + Math.random() * 4
		}
		entity._pendingDwell = dwellBase + Math.random() * 2.5
		entity.intent = 'visit-area'
		applyPath(entity, buildWaypointPath(layout, entity, resolvedKey, dest))
		return
	}

	const desk = areaCenters.get(entity.areaId ?? 'none')
		|| { x: entity.x, y: entity.y, radius: 70 }
	const homeZoneId = entity.areaId ?? 'none'
	const claimed = claimSpot(layout, homeZoneId, entity.id, { x: entity.x, y: entity.y })
	entity.destinationType = 'desk'
	entity.destinationId = null
	entity.currentZone = 'home'
	entity.targetZone = 'home'
	if (claimed?.spot) {
		dest = { x: claimed.spot.x, y: claimed.spot.y }
		entity.spotId = claimed.spot.id
		contested = claimed.contested
	} else {
		const deskSpan = (desk.radius || 70) * 0.7
		dest = {
			x: desk.x + (Math.random() - 0.5) * deskSpan,
			y: desk.y + (Math.random() - 0.5) * deskSpan,
		}
	}
	entity._pendingDwell = dwellBase + Math.random() * 3
	entity.intent = 'go-home'
	if (contested) entity._pendingConflictChance = true
	const path = buildWaypointPath(layout, entity, homeZoneId, dest)
	applyPath(entity, path)
	if (urgent) {
		entity.nextDecisionAt = nowSec() + 8 + Math.random() * 12
	} else if (claimed?.full) {
		entity.spotWaitUntil = nowSec() + 3 + Math.random() * 4
		entity.nextDecisionAt = entity.spotWaitUntil
	}
}

export function applyStatusBehavior(entity, areaCenters, commonZones, { forceRetarget = true, layout = null } = {}) {
	const behavior = resolveBehavior({
		status: entity.userStatus,
		icon: entity.statusIcon,
		message: entity.statusMessage,
	})

	entity.mood = behavior.mood
	entity.behavior = behavior.behavior
	entity.mobilityMultiplier = behavior.mobilityMultiplier
	entity.intent = behavior.targetZone
		? `go-to-${behavior.targetZone}`
		: 'roam'
	entity.statusChangedAt = Date.now()

	if (forceRetarget && behavior.targetZone) {
		setDestinationForZone(entity, behavior.targetZone, areaCenters, commonZones, {
			urgent: true,
			dwellBase: behavior.priority ? 3.5 : 1.5,
			layout,
		})
	} else if (forceRetarget) {
		// Al limpiar un estado especial no teletransportar ni forzar regreso:
		// conservar la ruta actual y reincorporar una decisión normal pronto.
		entity.intent = 'resume-routine'
		entity.nextDecisionAt = nowSec() + 1.5 + Math.random() * 3
	}
}

function maybeInviteNearby(leader, entities, areaCenters, commonZones, layout = null) {
	if (!['coffee', 'lounge', 'meeting'].includes(leader.targetZone)) {
		return
	}

	for (const other of entities) {
		if (other === leader) continue
		if (['meeting', 'sick', 'vacation', 'focused', 'call'].includes(other.behavior)) continue
		if (other.userStatus === 'dnd' || other.userStatus === 'offline') continue

		const sameTeam = other.areaId === leader.areaId || (
			other.puestoId != null && other.puestoId === leader.puestoId
		)
		if (!sameTeam) continue

		const dist = Math.hypot(other.x - leader.x, other.y - leader.y)
		if (dist > 150) continue
		if (Math.random() > 0.14) continue

		other.previousTargetZone = other.targetZone
		other.targetZone = leader.targetZone
		other.intent = `follow-${leader.uid}`
		other.statusReactionTimer = 1.6
		other.statusReactionIcon = leader.statusIcon || '👋'
		setDestinationForZone(other, leader.targetZone, areaCenters, commonZones, {
			urgent: true,
			dwellBase: 2.5,
			layout,
		})
	}
}

/**
 * Reacciones sociales ligeras alrededor de quien celebra hoy.
 * Limitadas: pocos compañeros, misma área, cooldown largo.
 * @param {object} celebrant Empleado celebrado
 * @param {Array<object>} entities Entidades activas
 * @param {Map} areaCenters Centros de áreas
 * @param {Array<object>} commonZones Zonas comunes
 * @param {number} tNow Tiempo actual
 * @param {object|null} layout Plano de oficina
 */
function maybeCelebrateWithCoworkers(celebrant, entities, areaCenters, commonZones, tNow, layout = null) {
	const celebration = primaryCelebration(celebrant.specialEvents)
	if (!celebration) return

	let invited = 0
	const maxInvite = 2

	for (const other of entities) {
		if (invited >= maxInvite) break
		if (other === celebrant) continue
		if (other.areaId !== celebrant.areaId) continue
		if (other.userStatus === 'dnd' || other.userStatus === 'offline') continue
		if (['sick', 'vacation', 'focused', 'call', 'meeting'].includes(other.behavior)) continue
		if (other.socialClusterUntil > tNow) continue

		const dist = Math.hypot(other.x - celebrant.x, other.y - celebrant.y)
		if (dist > 170) continue
		if (Math.random() > 0.22) continue

		invited++
		other.socialClusterUntil = tNow + 6 + Math.random() * 4
		other.statusReactionTimer = 2.2
		other.statusReactionIcon = randomCelebrationReaction(celebration.type)
		other.intent = `celebrate-${celebrant.uid}`
		other.pauseTimer = Math.max(other.pauseTimer || 0, 1.2 + Math.random())

		const approach = 0.55 + Math.random() * 0.2
		other.targetX = celebrant.x + (Math.random() - 0.5) * 36
		other.targetY = celebrant.y + (Math.random() - 0.5) * 36
		other.destinationType = 'social'
		other.destinationId = null
		other.targetZone = celebrant.targetZone || 'home'
		other._pendingDwell = 2.5 + Math.random() * 2
		other.nextDecisionAt = tNow + 7 + Math.random() * 5
		other.vx += (other.targetX - other.x) * 0.01 * approach
		other.vy += (other.targetY - other.y) * 0.01 * approach

		// A veces mueven el mini-grupo hacia lounge/café
		if (Math.random() < 0.28 && commonZones.length) {
			const socialZone = Math.random() < 0.55 ? 'lounge' : 'coffee'
			setDestinationForZone(other, socialZone, areaCenters, commonZones, {
				urgent: true,
				dwellBase: 2.2,
				layout,
			})
			if (Math.random() < 0.4) {
				setDestinationForZone(celebrant, socialZone, areaCenters, commonZones, {
					urgent: false,
					dwellBase: 3,
					layout,
				})
			}
		}
	}

	celebrant.celebrationInviteAt = tNow + 18 + Math.random() * 28
	if (invited > 0 && celebrant.statusReactionTimer <= 0) {
		celebrant.statusReactionTimer = 2.4
		celebrant.statusReactionIcon = celebration.icon || randomCelebrationReaction(celebration.type)
	}
}

export function applyStatusSnapshot(entitiesByUid, statuses, areaCenters, commonZones, layout = null) {
	const entities = Array.from(entitiesByUid.values())
	const changed = []

	for (const row of statuses) {
		const uid = String(row?.uid || '')
		if (!uid) continue
		const node = entitiesByUid.get(uid)
		if (!node) continue

		const next = {
			status: row.status || 'offline',
			icon: row.icon || null,
			message: row.message || null,
			clearAt: row.clearAt ?? null,
		}
		const prev = {
			status: node.userStatus || 'offline',
			icon: node.statusIcon || null,
			message: node.statusMessage || null,
			clearAt: node.statusClearAt ?? null,
		}
		if (!hasStatusChanged(prev, next)) {
			continue
		}

		node.userStatus = next.status
		node.statusIcon = next.icon
		node.statusMessage = next.message
		node.statusClearAt = next.clearAt
		node.statusReactionTimer = 2.6
		node.statusReactionIcon = next.icon || '💬'

		applyStatusBehavior(node, areaCenters, commonZones, { forceRetarget: true, layout })
		maybeInviteNearby(node, entities, areaCenters, commonZones, layout)
		changed.push(node)
	}

	return changed
}

export function createEntity(employee, center, radius = 16) {
	const energy = resolveEnergy(employee)
	const areaKey = resolveEmployeeAreaKey(employee)
	const areaRadius = center?.radius || 70
	const jitter = areaRadius * 0.3 + Math.random() * areaRadius * 0.45
	const angle = Math.random() * Math.PI * 2
	const userStatus = employee.userStatus || 'offline'
	const behavior = resolveBehavior({
		status: userStatus,
		icon: employee.statusIcon,
		message: employee.statusMessage,
	})
	const specialEvents = normalizeDailyEvents(employee.dailyEvents)
	const celebration = primaryCelebration(specialEvents)
	const roleProfile = resolveRoleProfile({
		puestoName: employee.puesto?.nombre,
		puesto: employee.puesto,
		simulationRole: employee.simulationRole || employee.puesto?.simulation_role,
	})
	const workAnchor = roleProfile.workAnchor === 'homeArea' ? 'home' : roleProfile.workAnchor

	const entity = {
		id: employee.id,
		uid: employee.uid,
		displayName: employee.displayName,
		avatarUrl: employee.avatarUrl || '',
		areaId: areaKey,
		areaName: employee.area?.nombre || '',
		homeAreaId: areaKey,
		puestoId: employee.puesto?.id ?? null,
		puestoName: employee.puesto?.nombre || '',
		roleProfile,
		simulationRole: roleProfile.simulationRole,
		workAnchor,
		reportesPeriodo: Number(employee.reportesPeriodo) || 0,
		reportesHoy: Number(employee.reportesHoy) || 0,
		reportesSemana: Number(employee.reportesSemana) || 0,
		minutosHoy: Number(employee.minutosHoy) || 0,
		lastLoginAt: employee.lastLoginAt || null,
		userStatus,
		statusIcon: employee.statusIcon || null,
		statusMessage: employee.statusMessage || null,
		statusClearAt: employee.statusClearAt ?? null,
		specialEvents,
		dailyEventBadges: specialEvents.map((e) => e.icon),
		eventMood: celebration ? celebration.type : null,
		mood: behavior.mood,
		behavior: behavior.behavior,
		mobilityMultiplier: behavior.mobilityMultiplier,
		activityScore: typeof employee.activityScore === 'number'
			? employee.activityScore
			: energy,
		energy,
		radius,
		x: center.x + Math.cos(angle) * jitter,
		y: center.y + Math.sin(angle) * jitter,
		vx: (Math.random() - 0.5) * 1.2,
		vy: (Math.random() - 0.5) * 1.2,
		targetX: center.x,
		targetY: center.y,
		homeZone: 'home',
		currentZone: 'home',
		targetZone: behavior.targetZone || workAnchor || 'home',
		previousTargetZone: null,
		intent: behavior.targetZone ? `go-to-${behavior.targetZone}` : 'roam',
		destinationType: 'desk',
		destinationId: null,
		nextDecisionAt: nowSec() + 2 + Math.random() * 8,
		statusChangedAt: null,
		statusReactionTimer: 0,
		statusReactionIcon: null,
		celebrationInviteAt: celebration ? nowSec() + 4 + Math.random() * 10 : 0,
		socialClusterUntil: 0,
		dwellTimer: 0,
		interactionCooldown: 0,
		socialProbeCooldown: Math.random() * SIMULATION_CONFIG.social.probeCooldownMax,
		currentInteraction: null,
		interactionTimer: 0,
		interactionPartnerId: null,
		interactionPartnerUid: null,
		visualReactionType: null,
		visualReactionIcon: null,
		visualReactionTimer: 0,
		visualReactionDuration: 0,
		conflictTimer: 0,
		conflictCooldown: 0,
		facingAngle: Math.random() * Math.PI * 2,
		waypoints: [],
		spotId: null,
		spotWaitUntil: 0,
		_pendingConflictChance: false,
		localEnergyInfluence: 0,
		pauseTimer: 0,
		_pendingDwell: 0,
	}

	return entity
}

export function resetPositions(entities, areaCenters, width, height, layout = null) {
	for (const entity of entities) {
		releaseSpot(layout, entity)
		const anchorZone = resolveWorkAnchorZone(entity)
		const spawnZoneId = (anchorZone && anchorZone !== 'home')
			? anchorZone
			: (entity.areaId ?? 'none')

		const claimed = claimSpot(layout, spawnZoneId, entity.id, null)
		const center = (anchorZone && anchorZone !== 'home' && layout?.commonZones)
			? (layout.commonZones.find((z) => z.id === anchorZone)
				|| areaCenters.get(entity.areaId ?? 'none'))
			: (areaCenters.get(entity.areaId ?? 'none') || { x: width / 2, y: height / 2, radius: 40 })

		if (claimed?.spot) {
			entity.x = claimed.spot.x + (Math.random() - 0.5) * 4
			entity.y = claimed.spot.y + (Math.random() - 0.5) * 4
			entity.spotId = claimed.spot.id
			entity.targetX = claimed.spot.x
			entity.targetY = claimed.spot.y
			entity.currentZone = anchorZone !== 'home' ? anchorZone : 'home'
			entity.targetZone = entity.currentZone
			entity.destinationType = anchorZone !== 'home' ? 'zone' : 'desk'
			entity.destinationId = anchorZone !== 'home' ? anchorZone : null
		} else {
			const areaRadius = center?.radius || 40
			const jitter = areaRadius * 0.2 + Math.random() * areaRadius * 0.25
			const angle = Math.random() * Math.PI * 2
			entity.x = (center?.x || width / 2) + Math.cos(angle) * jitter
			entity.y = (center?.y || height / 2) + Math.sin(angle) * jitter
			entity.targetX = center?.x || width / 2
			entity.targetY = center?.y || height / 2
			entity.destinationType = 'desk'
			entity.destinationId = null
			entity.currentZone = 'home'
			entity.targetZone = 'home'
		}
		entity.vx = (Math.random() - 0.5) * 0.8
		entity.vy = (Math.random() - 0.5) * 0.8
		entity.intent = 'roam'
		entity.waypoints = []
		entity.nextDecisionAt = nowSec() + 1 + Math.random() * 4
		entity.dwellTimer = 0
		entity.currentInteraction = null
		entity.interactionTimer = 0
		entity.interactionCooldown = 0
		entity.socialProbeCooldown = Math.random() * SIMULATION_CONFIG.social.probeCooldownMax
		entity.interactionPartnerId = null
		entity.interactionPartnerUid = null
		entity.visualReactionType = null
		entity.visualReactionIcon = null
		entity.visualReactionTimer = 0
		entity.visualReactionDuration = 0
		entity.conflictTimer = 0
		entity.conflictCooldown = 0
		entity.pauseTimer = 0
		entity.statusReactionTimer = 0
		entity.socialClusterUntil = 0
		entity.spotWaitUntil = 0
		entity._pendingConflictChance = false
		if (hasCelebrationEvent(entity.specialEvents)) {
			entity.celebrationInviteAt = nowSec() + 3 + Math.random() * 8
		}
	}
}

function setMicroDestination(entity, layout) {
	const rooms = layout?.rooms || []
	const currentId = entity.destinationId || (entity.currentZone === 'home' ? entity.areaId : entity.currentZone)
	const room = rooms.find((candidate) => candidate.id === currentId
		|| String(candidate.id) === String(currentId))
	if (!room) return false

	releaseSpot(layout, entity)
	const padX = Math.min(room.w * 0.3, Math.max(entity.radius + 5, 22))
	const padTop = Math.min(room.h * 0.35, Math.max(entity.radius + 12, 30))
	const padBottom = Math.min(room.h * 0.3, Math.max(entity.radius + 4, 20))
	entity.previousTargetZone = entity.targetZone
	entity.targetX = room.x + padX + Math.random() * Math.max(1, room.w - padX * 2)
	entity.targetY = room.y + padTop + Math.random() * Math.max(1, room.h - padTop - padBottom)
	entity.destinationType = 'micro'
	entity.destinationId = room.kind === 'work' ? room.id : entity.destinationId
	entity.targetZone = room.kind === 'work' && room.id === entity.areaId ? 'home' : room.id
	entity.intent = 'micro-movement'
	entity._pendingDwell = 0.8 + Math.random() * 1.8
	applyPath(entity, [{ x: entity.targetX, y: entity.targetY }])
	return true
}

function chooseNextDestination(entity, areaCenters, commonZones, layout = null) {
	// 1) Estado Nextcloud con destino explícito
	const preferred = TARGET_BY_MOOD[entity.mood]
	if (preferred) {
		setDestinationForZone(entity, preferred, areaCenters, commonZones, {
			dwellBase: preferred === 'meeting' || preferred === 'lounge' ? 4 : 2.5,
			layout,
		})
		scheduleNextDecision(
			entity,
			SIMULATION_CONFIG.movement.statusDecisionMinSeconds,
			SIMULATION_CONFIG.movement.statusDecisionMaxSeconds,
		)
		return
	}

	const profile = entity.roleProfile || { anchorStrength: 0.46, roaming: 0.52 }
	const mobility = entity.mobilityMultiplier ?? 1
	const anchorWeight = (profile.anchorStrength ?? 0.46)
		* (entity.workAnchor !== 'home' ? 1.08 : 1)
	const roamingWeight = (profile.roaming ?? 0.52)
		* (0.72 + entity.energy * 0.48)
		* Math.max(0.28, mobility)
	const microWeight = 0.2 + Math.max(0, 0.45 - mobility) * 0.45
	const total = anchorWeight + roamingWeight + microWeight
	const roll = Math.random() * total

	if (roll < microWeight && setMicroDestination(entity, layout)) {
		scheduleNextDecision(entity)
		return
	}

	if (roll < microWeight + roamingWeight && commonZones.length) {
		let zoneId = pickPreferredRoamZone(entity, commonZones)
		// Una visita breve a otra área crea cruces sin convertirla en ancla.
		if (Math.random() < 0.07 && areaCenters.size > 1) {
			const otherAreas = Array.from(areaCenters.keys()).filter((id) => id !== entity.areaId)
			if (otherAreas.length) zoneId = otherAreas[Math.floor(Math.random() * otherAreas.length)]
		}
		setDestinationForZone(entity, zoneId, areaCenters, commonZones, {
			dwellBase: 1.8,
			layout,
		})
		scheduleNextDecision(entity)
		return
	}

	setDestinationForZone(entity, 'home', areaCenters, commonZones, { dwellBase: 1.8, layout })
	scheduleNextDecision(entity)
}

export function stepSimulation({
	entities,
	areaCenters,
	commonZones = [],
	layout = null,
	width,
	height,
	dt,
	speedMultiplier = 1,
}) {
	const n = entities.length
	if (n === 0) {
		return
	}

	// Todos los timers sociales están expresados en segundos reales de simulación.
	// speedMultiplier solo acelera movimiento/dwell, no acorta indicadores visuales.
	const dtSeconds = Math.min(0.05, Math.max(0.008, dt))
	const dtScaled = dtSeconds * speedMultiplier
	const zones = commonZones.length
		? commonZones
		: (layout?.commonZones || buildCommonZones(width, height))
	const tNow = nowSec()

	// Densidad por zona (para conflictos)
	const zoneDensity = new Map()
	for (const e of entities) {
		const z = e.destinationId || e.currentZone || 'home'
		zoneDensity.set(z, (zoneDensity.get(z) || 0) + 1)
	}

	for (let i = 0; i < n; i++) {
		const a = entities[i]
		let nearbyActive = 0
		let nearbyCount = 0

		for (let j = 0; j < n; j++) {
			if (i === j) continue
			const b = entities[j]
			const dx = b.x - a.x
			const dy = b.y - a.y
			const dist2 = dx * dx + dy * dy
			if (dist2 < 100 * 100) {
				nearbyCount++
				if (b.energy > 0.55) nearbyActive++
			}
		}

		if (nearbyActive >= 3) {
			a.localEnergyInfluence = Math.min(0.28, a.localEnergyInfluence + 0.025)
		} else if (nearbyCount === 0) {
			a.localEnergyInfluence = Math.max(-0.08, a.localEnergyInfluence - 0.01)
		} else {
			a.localEnergyInfluence *= 0.9
		}

		if (nearbyCount >= 8 && a.nextDecisionAt > tNow + 0.8) {
			a.nextDecisionAt = tNow + 0.35
		}
	}

	for (const entity of entities) {
		if (entity.statusReactionTimer > 0) {
			entity.statusReactionTimer = Math.max(0, entity.statusReactionTimer - dtSeconds)
		}
		if (entity.conflictTimer > 0) {
			entity.conflictTimer = Math.max(0, entity.conflictTimer - dtSeconds)
		}
		if (entity.conflictCooldown > 0) {
			entity.conflictCooldown = Math.max(0, entity.conflictCooldown - dtSeconds)
		}
		if (entity.interactionCooldown > 0) entity.interactionCooldown -= dtSeconds
		if (entity.socialProbeCooldown > 0) entity.socialProbeCooldown -= dtSeconds
		if (entity.visualReactionTimer > 0) {
			entity.visualReactionTimer = Math.max(0, entity.visualReactionTimer - dtSeconds)
			if (entity.visualReactionTimer === 0) {
				entity.visualReactionType = null
				entity.visualReactionIcon = null
			}
		}
		if (entity.interactionTimer > 0) {
			entity.interactionTimer -= dtSeconds
			if (entity.interactionTimer <= 0) {
				entity.interactionTimer = 0
				entity.currentInteraction = null
				entity.interactionPartnerId = null
				entity.interactionPartnerUid = null
			}
		}
		if (entity.pauseTimer > 0) {
			entity.pauseTimer -= dtSeconds
			entity.vx *= 0.82
			entity.vy *= 0.82
			continue
		}

		if (
			entity.eventMood
			&& entity.celebrationInviteAt > 0
			&& tNow >= entity.celebrationInviteAt
		) {
			maybeCelebrateWithCoworkers(entity, entities, areaCenters, zones, tNow, layout)
		}

		maybeJoinChatGroup(entity, entities, tNow)

		const mobility = entity.mobilityMultiplier ?? 1

		if (entity.dwellTimer > 0) {
			entity.dwellTimer -= dtScaled
			const dwellDamp = entity.behavior === 'meeting' || entity.mood === 'meeting'
				? 0.88
				: (entity.currentZone === 'lounge' ? 0.92 : 0.9)
			entity.vx *= dwellDamp
			entity.vy *= dwellDamp
			if (Math.random() < 0.03 * mobility) {
				entity.vx += (Math.random() - 0.5) * 0.18
				entity.vy += (Math.random() - 0.5) * 0.18
			}
			if (entity.dwellTimer <= 0 && entity.nextDecisionAt > tNow + 2) {
				entity.nextDecisionAt = tNow + 0.4 + Math.random()
			}
			continue
		}

		const routeDistance = Math.hypot(entity.targetX - entity.x, entity.targetY - entity.y)
		const routeInProgress = routeDistance > SIMULATION_CONFIG.movement.arrivalDistance * 1.5
			|| (entity.waypoints && entity.waypoints.length > 0)
		const spotRetryDue = entity.spotWaitUntil > 0 && tNow >= entity.spotWaitUntil
		if (tNow >= (entity.nextDecisionAt || 0) && (!routeInProgress || spotRetryDue)) {
			chooseNextDestination(entity, areaCenters, zones, layout)
		}

		const statusBoost = entity.userStatus === 'online'
			? 0.14
			: (entity.userStatus === 'away' || entity.userStatus === 'busy' ? 0.05 : 0)
		let moodMult = 1
		if (entity.mood === 'sick' || entity.mood === 'vacation') moodMult = 0.55
		else if (entity.mood === 'focused') moodMult = 0.72
		else if (entity.mood === 'active') moodMult = 1.28
		else if (entity.mood === 'commuting') moodMult = 1.15
		else if (entity.currentZone === 'lounge') moodMult = 0.7

		const effectiveEnergy = Math.min(
			ENERGY_MAX + 0.1,
			Math.max(ENERGY_MIN, (entity.energy + entity.localEnergyInfluence + statusBoost) * moodMult * mobility),
		)

		const toX = entity.targetX - entity.x
		const toY = entity.targetY - entity.y
		const dist = Math.hypot(toX, toY) || 1

		if (dist < SIMULATION_CONFIG.movement.arrivalDistance) {
			if (entity.waypoints && entity.waypoints.length) {
				const next = entity.waypoints.shift()
				entity.targetX = next.x
				entity.targetY = next.y
				continue
			}
			entity.dwellTimer = entity._pendingDwell || (1.5 + Math.random() * 2)
			if (entity.behavior === 'meeting' || entity.mood === 'meeting') {
				entity.dwellTimer = Math.max(entity.dwellTimer, 4 + Math.random() * 4)
			}
			if (entity.mood === 'break') {
				entity.dwellTimer = Math.max(entity.dwellTimer, 2.5 + Math.random() * 3)
			}
			entity._pendingDwell = 0
			entity.vx *= 0.4
			entity.vy *= 0.4
			entity.facingAngle = Math.atan2(toY, toX)
			continue
		}

		const seek = (0.05 + effectiveEnergy * 0.07) * Math.max(0.25, mobility)
		entity.vx += (toX / dist) * seek
		entity.vy += (toY / dist) * seek
		entity.facingAngle = Math.atan2(entity.vy, entity.vx)

		if (entity.destinationType === 'desk' && mobility > 0.4) {
			let samePuestoCount = 0
			let px = 0
			let py = 0
			for (const other of entities) {
				if (other === entity) continue
				if (entity.puestoId == null || other.puestoId !== entity.puestoId) continue
				if ((other.areaId ?? null) !== (entity.areaId ?? null)) continue
				px += other.x
				py += other.y
				samePuestoCount++
			}
			if (samePuestoCount > 0) {
				px /= samePuestoCount
				py /= samePuestoCount
				entity.vx += (px - entity.x) * 0.002 * mobility
				entity.vy += (py - entity.y) * 0.002 * mobility
			}
		}

		const wanderChance = (0.03 + effectiveEnergy * 0.04) * mobility
		if (Math.random() < wanderChance) {
			const amp = 0.3 + effectiveEnergy * 0.45
			entity.vx += (Math.random() - 0.5) * amp
			entity.vy += (Math.random() - 0.5) * amp
		}
	}

	for (let i = 0; i < n; i++) {
		const a = entities[i]
		for (let j = i + 1; j < n; j++) {
			const b = entities[j]
			const dx = b.x - a.x
			const dy = b.y - a.y
			const dist = Math.hypot(dx, dy) || 0.0001
			const minDist = a.radius + b.radius + 10

			if (dist < minDist * 2.2) {
				const overlap = minDist - dist
				const nx = dx / dist
				const ny = dy / dist
				const densityBoost = dist < minDist ? 2.2 : 1.2
				const push = Math.max(0.01, overlap) * 0.06 * densityBoost
				a.vx -= nx * push
				a.vy -= ny * push
				b.vx += nx * push
				b.vy += ny * push

				if (dist < minDist) {
					const sep = (minDist - dist) * 0.55
					a.x -= nx * sep
					a.y -= ny * sep
					b.x += nx * sep
					b.y += ny * sep
				}

				const zoneKey = a.destinationId || a.currentZone
				const density = zoneDensity.get(zoneKey) || 0
				const contested = !!(a._pendingConflictChance || b._pendingConflictChance
					|| (a.spotId && a.spotId === b.spotId))
				maybeStartSocialInteraction(a, b, dist, minDist, {
					densityBoost: density,
					contestedSpot: contested,
				})
				if (a.currentInteraction === INTERACTION_TYPES.CONFLICT
					|| b.currentInteraction === INTERACTION_TYPES.CONFLICT) {
					a._pendingConflictChance = false
					b._pendingConflictChance = false
				}
			}
		}
	}

	for (const entity of entities) {
		const mobility = entity.mobilityMultiplier ?? 1
		const moodSlow = (entity.mood === 'sick' || entity.mood === 'vacation' || entity.currentZone === 'lounge')
			? 0.65
			: 1
		const effectiveEnergy = Math.min(
			ENERGY_MAX + 0.1,
			Math.max(ENERGY_MIN, entity.energy + entity.localEnergyInfluence),
		)
		const maxSpeed = (1.25 + effectiveEnergy * 1.9) * moodSlow * Math.max(0.2, mobility)
		const speed = Math.hypot(entity.vx, entity.vy)
		if (speed > maxSpeed) {
			entity.vx = (entity.vx / speed) * maxSpeed
			entity.vy = (entity.vy / speed) * maxSpeed
		}

		// Micro-vibración en conflicto
		if (entity.conflictTimer > 0) {
			entity.x += (Math.random() - 0.5) * 1.4
			entity.y += (Math.random() - 0.5) * 1.4
		}

		entity.vx *= 0.955
		entity.vy *= 0.955

		entity.x += entity.vx * dtScaled * 60
		entity.y += entity.vy * dtScaled * 60

		const margin = entity.radius + 4
		if (entity.x < margin) {
			entity.x = margin
			entity.vx = Math.abs(entity.vx) * 0.5
		} else if (entity.x > width - margin) {
			entity.x = width - margin
			entity.vx = -Math.abs(entity.vx) * 0.5
		}
		if (entity.y < margin) {
			entity.y = margin
			entity.vy = Math.abs(entity.vy) * 0.5
		} else if (entity.y > height - margin) {
			entity.y = height - margin
			entity.vy = -Math.abs(entity.vy) * 0.5
		}
	}
}

export function hitTest(entities, x, y) {
	let best = null
	let bestDist = Infinity
	for (const entity of entities) {
		const d = Math.hypot(entity.x - x, entity.y - y)
		if (d <= entity.radius + 4 && d < bestDist) {
			best = entity
			bestDist = d
		}
	}
	return best
}
