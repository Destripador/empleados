/**
 * Perfiles de rol para anclas de trabajo en Office Simulation.
 *
 * homeArea = área organizacional del módulo (Capital Humano, etc.)
 * workAnchor = habitación funcional habitual (reception, homeArea, …)
 *
 * Futuro: si el catálogo de puestos expone `simulation_role`,
 * úsalo vía resolveRoleProfile({ simulationRole, puestoName }).
 */

export const ROLE_PROFILES = Object.freeze({
	receptionist: {
		workAnchor: 'reception',
		anchorStrength: 0.68,
		roaming: 0.32,
		preferredZones: ['reception', 'coffee', 'printer', 'lounge'],
	},
	manager: {
		workAnchor: 'homeArea',
		anchorStrength: 0.43,
		roaming: 0.56,
		preferredZones: ['homeArea', 'meeting', 'coffee', 'lounge'],
	},
	support: {
		workAnchor: 'homeArea',
		anchorStrength: 0.32,
		roaming: 0.68,
		preferredZones: ['homeArea', 'printer', 'reception', 'coffee', 'lounge'],
	},
	default: {
		workAnchor: 'homeArea',
		anchorStrength: 0.46,
		roaming: 0.52,
		preferredZones: ['homeArea', 'coffee', 'printer', 'lounge', 'meeting'],
	},
})

/** Aliases normalizados → simulation_role */
const ROLE_ALIASES = [
	{
		role: 'receptionist',
		patterns: [
			/^recepcionista$/,
			/^recepci[oó]n$/,
			/^receptionist$/,
			/^front\s*desk$/,
			/^asistente\s*de\s*recepci/,
		],
	},
	{
		role: 'manager',
		patterns: [
			/^gerente/,
			/^manager$/,
			/^director/,
			/^jefe\s*de/,
			/^coordinador/,
			/^lider/,
			/^líder/,
		],
	},
	{
		role: 'support',
		patterns: [
			/^soporte/,
			/^support$/,
			/^help\s*desk$/,
			/^asistente\s*administrativ/,
			/^auxiliar/,
			/^mensajer/,
		],
	},
]

/**
 * Normaliza texto de puesto.
 * @param {string} value Texto
 */
export function normalizePuestoLabel(value) {
	return String(value || '')
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.trim()
		.toLowerCase()
		.replace(/\s+/g, ' ')
}

/**
 * Resuelve simulation_role desde catálogo o nombre de puesto.
 * @param {{simulationRole?: string|null, puestoName?: string|null, puesto?: {nombre?: string, simulation_role?: string}}} input Datos
 */
export function resolveSimulationRole(input = {}) {
	const explicit = input.simulationRole
		|| input.puesto?.simulation_role
		|| input.puesto?.simulationRole
		|| null
	if (explicit && ROLE_PROFILES[explicit]) {
		return explicit
	}

	const label = normalizePuestoLabel(input.puestoName || input.puesto?.nombre || '')
	if (!label) {
		return 'default'
	}

	for (const entry of ROLE_ALIASES) {
		if (entry.patterns.some((re) => re.test(label))) {
			return entry.role
		}
	}
	return 'default'
}

/**
 * Perfil de rol efectivo.
 * @param {object} input Empleado / puesto
 */
export function resolveRoleProfile(input = {}) {
	const role = resolveSimulationRole(input)
	const profile = ROLE_PROFILES[role] || ROLE_PROFILES.default
	return {
		simulationRole: role,
		...profile,
	}
}

/**
 * Destino ancla concreto (id de zona común o 'home').
 * @param {object} entity Entidad
 */
export function resolveWorkAnchorZone(entity) {
	const profile = entity.roleProfile || ROLE_PROFILES.default
	if (profile.workAnchor === 'homeArea' || profile.workAnchor === 'home') {
		return 'home'
	}
	return profile.workAnchor || 'home'
}

/**
 * Elige zona preferida para roaming según perfil.
 * @param {object} entity Entidad
 * @param {Array} commonZones Zonas comunes
 */
export function pickPreferredRoamZone(entity, commonZones = []) {
	const profile = entity.roleProfile || ROLE_PROFILES.default
	const prefs = profile.preferredZones || []
	const available = new Set(commonZones.map((zone) => zone.id))
	let candidates = prefs
		.map((pref) => (pref === 'homeArea' ? 'home' : pref))
		.filter((pref) => pref !== 'otherAreas' && (pref === 'home' || available.has(pref)))

	// Roaming debe sacar al empleado de su ancla cuando hay alternativas.
	const current = entity.targetZone || entity.currentZone
	const awayFromCurrent = candidates.filter((pref) => pref !== current && pref !== 'home')
	if (awayFromCurrent.length) candidates = awayFromCurrent
	else {
		const away = candidates.filter((pref) => pref !== current)
		if (away.length) candidates = away
	}
	if (candidates.length) {
		return candidates[Math.floor(Math.random() * candidates.length)]
	}
	if (commonZones.length) {
		const alternatives = commonZones.filter((zone) => zone.id !== current)
		const pool = alternatives.length ? alternatives : commonZones
		return pool[Math.floor(Math.random() * pool.length)].id
	}
	return 'home'
}
