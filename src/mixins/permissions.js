export default {
	inject: {
		permissions: {
			default: () => ({
				uid: null,
				is_admin: false,
				groups: [],
				modules: {},
			}),
		},
	},

	methods: {
		canSee(permissionKey) {
			if (!permissionKey) {
				return false
			}

			const key = String(permissionKey).trim()

			if (key === '') {
				return false
			}

			if (key.includes('.')) {
				const [moduleName, permissionName] = key.split('.', 2)

				return this.canUseModulePermission(moduleName, permissionName)
			}

			return this.canViewModule(key)
		},

		canSeeAny(permissionKeys) {
			if (!Array.isArray(permissionKeys)) {
				return false
			}

			return permissionKeys.some(permissionKey => this.canSee(permissionKey))
		},

		canViewModule(moduleName) {
			return this.isTruthy(this.permissions?.modules?.[moduleName]?.view)
		},

		canUseModulePermission(moduleName, permissionName) {
			return this.isTruthy(this.permissions?.modules?.[moduleName]?.[permissionName])
                || this.isTruthy(this.permissions?.modules?.[moduleName]?.permissions?.[permissionName])
		},

		isAdminUser() {
			return this.isTruthy(this.permissions?.is_admin)
		},

		isTruthy(value) {
			return value === true
                || value === 'true'
                || value === 1
                || value === '1'
		},
	},
}
