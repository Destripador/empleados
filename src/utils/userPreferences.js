import { getCurrentUser } from '@nextcloud/auth'

/**
 * Claves de preferencias de UI (sin prefijo ni UID).
 * La clave completa en localStorage es: empleados:<uid>:v1:<key>
 */
export const PREFERENCE_KEYS = Object.freeze({
	ADMIN_REPORTS: 'admin-reports',
	AREAS: 'areas',
	POSITIONS: 'puestos',
	EQUIPMENT: 'equipos',
})

const APP_PREFIX = 'empleados'
const PREFERENCE_VERSION = 'v1'

/**
 * Obtiene el UID del usuario actual de Nextcloud.
 * @return {string}
 */
export function getPreferenceUserId() {
	try {
		const user = getCurrentUser()
		if (user?.uid) {
			return String(user.uid)
		}
	} catch (error) {
		// Ignorar y probar fallbacks.
	}

	try {
		const user = typeof window !== 'undefined'
			? window.OC?.getCurrentUser?.()
			: null
		if (user?.uid) {
			return String(user.uid)
		}
	} catch (error) {
		// Ignorar.
	}

	return 'anonymous'
}

/**
 * Construye la clave namespaced por app, usuario y versión.
 * @param {string} key Clave lógica (p. ej. PREFERENCE_KEYS.AREAS).
 * @return {string}
 */
export function buildPreferenceStorageKey(key) {
	return `${APP_PREFIX}:${getPreferenceUserId()}:${PREFERENCE_VERSION}:${key}`
}

/**
 * Indica si localStorage está disponible.
 * @return {boolean}
 */
function canUseLocalStorage() {
	try {
		if (typeof window === 'undefined' || !window.localStorage) {
			return false
		}
		const probe = '__empleados_prefs_probe__'
		window.localStorage.setItem(probe, '1')
		window.localStorage.removeItem(probe)
		return true
	} catch (error) {
		return false
	}
}

/**
 * Fusiona preferencias almacenadas sobre defaults sin mutar defaults.
 * Solo copia claves conocidas en defaults (filters / view).
 * @param {object} defaults Preferencias base.
 * @param {object|null} stored Preferencias leídas de localStorage.
 * @return {object}
 */
export function mergePreferences(defaults, stored) {
	const base = defaults && typeof defaults === 'object' ? defaults : {}
	const incoming = stored && typeof stored === 'object' ? stored : {}

	const result = {
		filters: {
			...(base.filters && typeof base.filters === 'object' ? base.filters : {}),
		},
		view: {
			...(base.view && typeof base.view === 'object' ? base.view : {}),
		},
	}

	if (incoming.filters && typeof incoming.filters === 'object') {
		Object.keys(result.filters).forEach((key) => {
			if (Object.prototype.hasOwnProperty.call(incoming.filters, key)) {
				result.filters[key] = incoming.filters[key]
			}
		})
		// Conservar claves nuevas del stored que aún no estén en defaults
		// solo si defaults.filters está vacío (compatibilidad flexible).
		if (Object.keys(result.filters).length === 0) {
			result.filters = { ...incoming.filters }
		} else {
			Object.keys(incoming.filters).forEach((key) => {
				if (!Object.prototype.hasOwnProperty.call(result.filters, key)) {
					result.filters[key] = incoming.filters[key]
				}
			})
		}
	}

	if (incoming.view && typeof incoming.view === 'object') {
		Object.keys(result.view).forEach((key) => {
			if (Object.prototype.hasOwnProperty.call(incoming.view, key)) {
				result.view[key] = incoming.view[key]
			}
		})
		Object.keys(incoming.view).forEach((key) => {
			if (!Object.prototype.hasOwnProperty.call(result.view, key)) {
				result.view[key] = incoming.view[key]
			}
		})
	}

	return result
}

/**
 * Indica si existe una preferencia almacenada para la clave.
 * @param {string} key Clave lógica.
 * @return {boolean}
 */
export function hasPreference(key) {
	if (!key || !canUseLocalStorage()) {
		return false
	}

	try {
		return window.localStorage.getItem(buildPreferenceStorageKey(key)) !== null
	} catch (error) {
		return false
	}
}

/**
 * Carga una preferencia desde localStorage.
 * @param {string} key Clave lógica.
 * @param {object} defaults Valor por defecto si no hay datos o fallan.
 * @return {object}
 */
export function loadPreference(key, defaults = {}) {
	const fallback = defaults && typeof defaults === 'object'
		? mergePreferences(defaults, null)
		: { filters: {}, view: {} }

	if (!key || !canUseLocalStorage()) {
		return fallback
	}

	try {
		const raw = window.localStorage.getItem(buildPreferenceStorageKey(key))
		if (raw === null || raw === undefined || raw === '') {
			return fallback
		}

		const parsed = JSON.parse(raw)
		if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
			return fallback
		}

		return mergePreferences(fallback, parsed)
	} catch (error) {
		return fallback
	}
}

/**
 * Guarda una preferencia en localStorage.
 * @param {string} key Clave lógica.
 * @param {object} value Objeto { filters, view }.
 * @return {boolean} true si se guardó correctamente.
 */
export function savePreference(key, value) {
	if (!key || !canUseLocalStorage()) {
		return false
	}

	try {
		const payload = {
			filters: value?.filters && typeof value.filters === 'object' ? value.filters : {},
			view: value?.view && typeof value.view === 'object' ? value.view : {},
		}
		window.localStorage.setItem(buildPreferenceStorageKey(key), JSON.stringify(payload))
		return true
	} catch (error) {
		return false
	}
}

/**
 * Elimina una preferencia almacenada.
 * @param {string} key Clave lógica.
 * @return {boolean}
 */
export function removePreference(key) {
	if (!key || !canUseLocalStorage()) {
		return false
	}

	try {
		window.localStorage.removeItem(buildPreferenceStorageKey(key))
		return true
	} catch (error) {
		return false
	}
}

/**
 * Restablece una preferencia a sus defaults y los persiste.
 * @param {string} key Clave lógica.
 * @param {object} defaults Preferencias por defecto a aplicar y guardar.
 * @return {object} Preferencias aplicadas.
 */
export function resetPreference(key, defaults = {}) {
	const next = mergePreferences(defaults, null)
	savePreference(key, next)
	return next
}
