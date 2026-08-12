/**
 * Eventos del día para la simulación de oficina.
 *
 * Los eventos se calculan en backend (aniversario / cumpleaños)
 * y aquí solo se enriquecen etiquetas, badges y resumen visual.
 * Extensible para: nuevo ingreso, vacaciones, festivos, etc.
 */

export const EVENT_TYPES = Object.freeze({
	WORK_ANNIVERSARY: 'work_anniversary',
	BIRTHDAY: 'birthday',
	NEW_HIRE: 'new_hire',
	MEETING: 'meeting',
	COFFEE_BREAK: 'coffee_break',
	TEMPORARY_SOCIAL_GROUP: 'temporary_social_group',
})

const CELEBRATION_TYPES = new Set([
	EVENT_TYPES.WORK_ANNIVERSARY,
	EVENT_TYPES.BIRTHDAY,
])

const REACTION_BY_TYPE = {
	[EVENT_TYPES.WORK_ANNIVERSARY]: ['🎉', '👏', '🥳'],
	[EVENT_TYPES.BIRTHDAY]: ['🎂', '🎈', '🥳', '👏'],
}

/**
 * Normaliza eventos crudos del backend en la forma usada por nodos.
 * @param {Array<{type?: string, icon?: string, years?: number}>|null|undefined} raw
 */
export function normalizeDailyEvents(raw) {
	if (!Array.isArray(raw) || raw.length === 0) {
		return []
	}
	return raw
		.filter((e) => e && typeof e.type === 'string')
		.map((e) => ({
			type: e.type,
			icon: e.icon || defaultIconForType(e.type),
			years: typeof e.years === 'number' ? e.years : null,
		}))
}

export function defaultIconForType(type) {
	switch (type) {
	case EVENT_TYPES.WORK_ANNIVERSARY:
		return '🎉'
	case EVENT_TYPES.BIRTHDAY:
		return '🎂'
	case EVENT_TYPES.NEW_HIRE:
		return '👋'
	case EVENT_TYPES.MEETING:
		return '📅'
	case EVENT_TYPES.COFFEE_BREAK:
		return '☕'
	case EVENT_TYPES.TEMPORARY_SOCIAL_GROUP:
		return '👋'
	default:
		return '✨'
	}
}

export function hasCelebrationEvent(events) {
	return (events || []).some((e) => CELEBRATION_TYPES.has(e.type))
}

export function primaryCelebration(events) {
	const list = events || []
	const birthday = list.find((e) => e.type === EVENT_TYPES.BIRTHDAY)
	if (birthday) return birthday
	return list.find((e) => e.type === EVENT_TYPES.WORK_ANNIVERSARY) || null
}

/**
 * Jerarquía de badge visible sobre avatar:
 * 1. evento especial del día
 * 2. icono de estado Nextcloud
 * 3. ninguno
 */
export function resolvePrimaryBadge(entity) {
	const celebration = primaryCelebration(entity?.specialEvents)
	if (celebration) {
		return {
			icon: celebration.icon || defaultIconForType(celebration.type),
			kind: celebration.type,
			secondary: entity?.statusIcon || null,
		}
	}
	if (entity?.statusIcon) {
		return {
			icon: entity.statusIcon,
			kind: 'status',
			secondary: null,
		}
	}
	return null
}

export function randomCelebrationReaction(type) {
	const pool = REACTION_BY_TYPE[type] || ['🎉']
	return pool[Math.floor(Math.random() * pool.length)]
}

/**
 * Resumen a partir de eventosHoy del backend o de la lista de empleados.
 * @param {{work_anniversary?: Array, birthday?: Array}|null} eventosHoy
 * @param {Array} employees
 */
export function buildTodayEventsSummary(eventosHoy, employees = []) {
	if (eventosHoy && (eventosHoy.work_anniversary || eventosHoy.birthday)) {
		return {
			workAnniversary: Array.isArray(eventosHoy.work_anniversary)
				? eventosHoy.work_anniversary
				: [],
			birthday: Array.isArray(eventosHoy.birthday) ? eventosHoy.birthday : [],
		}
	}

	const workAnniversary = []
	const birthday = []
	for (const emp of employees) {
		for (const event of normalizeDailyEvents(emp.dailyEvents)) {
			if (event.type === EVENT_TYPES.WORK_ANNIVERSARY) {
				workAnniversary.push({
					uid: emp.uid,
					displayName: emp.displayName,
					years: event.years || 0,
				})
			} else if (event.type === EVENT_TYPES.BIRTHDAY) {
				birthday.push({
					uid: emp.uid,
					displayName: emp.displayName,
				})
			}
		}
	}
	return { workAnniversary, birthday }
}

export function celebrationGlowColor(type) {
	if (type === EVENT_TYPES.BIRTHDAY) {
		return 'rgba(233, 120, 160, 0.55)'
	}
	if (type === EVENT_TYPES.WORK_ANNIVERSARY) {
		return 'rgba(232, 176, 60, 0.55)'
	}
	return 'rgba(120, 160, 200, 0.4)'
}
