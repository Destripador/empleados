import debounce from 'debounce'
import { translate as t } from '@nextcloud/l10n'
import {
	loadPreference,
	savePreference,
	resetPreference,
} from '../utils/userPreferences.js'

const DEFAULT_LIST_FILTERS = Object.freeze({
	query: '',
	sortOrder: 'asc',
	hideEmpty: false,
})

/**
 * Mixin para persistir búsqueda / orden / hideEmpty en listados virtuales.
 * @param {string} preferenceKey Clave de PREFERENCE_KEYS.
 * @param {object} filterDefaults Overrides opcionales de defaults.
 * @return {object} Opciones de mixin Vue.
 */
export function createListFiltersMixin(preferenceKey, filterDefaults = {}) {
	const defaults = {
		...DEFAULT_LIST_FILTERS,
		...filterDefaults,
	}

	const preferenceDefaults = {
		filters: { ...defaults },
		view: {},
	}

	return {
		watch: {
			query() {
				this.scheduleSaveListPreferences()
			},
			sortOrder() {
				this.scheduleSaveListPreferences()
			},
			hideEmpty() {
				this.scheduleSaveListPreferences()
			},
		},

		created() {
			this._listPrefsReady = false
			this._debouncedSaveListPrefs = debounce(() => {
				this.persistListPreferences()
			}, 300)
		},

		mounted() {
			this.restoreListPreferences()
			this._listPrefsReady = true
		},

		beforeDestroy() {
			this._debouncedSaveListPrefs?.flush?.()
			this._debouncedSaveListPrefs?.clear?.()
		},

		methods: {
			listPreferenceDefaults() {
				return {
					filters: { ...defaults },
					view: {},
				}
			},

			restoreListPreferences() {
				const prefs = loadPreference(preferenceKey, preferenceDefaults)
				const filters = prefs.filters || {}

				this.query = typeof filters.query === 'string'
					? filters.query
					: (this.searchQuery || defaults.query)

				this.sortOrder = filters.sortOrder === 'desc' ? 'desc' : 'asc'
				this.hideEmpty = Boolean(filters.hideEmpty)
			},

			persistListPreferences() {
				if (!this._listPrefsReady) {
					return
				}

				savePreference(preferenceKey, {
					filters: {
						query: String(this.query || ''),
						sortOrder: this.sortOrder === 'desc' ? 'desc' : 'asc',
						hideEmpty: Boolean(this.hideEmpty),
					},
					view: {},
				})
			},

			scheduleSaveListPreferences() {
				if (!this._listPrefsReady) {
					return
				}
				this._debouncedSaveListPrefs?.()
			},

			resetListFilters() {
				const next = resetPreference(preferenceKey, preferenceDefaults)
				this._listPrefsReady = false
				this.query = next.filters.query
				this.sortOrder = next.filters.sortOrder
				this.hideEmpty = Boolean(next.filters.hideEmpty)
				this.showFilters = false
				this.$nextTick(() => {
					this._listPrefsReady = true
				})
			},

			resetListFiltersLabel() {
				return t('empleados', 'Clear filters')
			},
		},
	}
}
