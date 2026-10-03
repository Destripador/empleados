<template>
	<div class="organigrama-table-wrapper">
		<div class="organigrama-table-toolbar">
			<input
				v-model="search"
				type="text"
				class="organigrama-search"
				:placeholder="t('empleados', 'Search by name...')">
			<span class="organigrama-table-count">
				{{ t('empleados', '{count} managers', { count: filteredManagers.length }) }}
			</span>
		</div>

		<div v-if="filteredManagers.length === 0" class="organigrama-table-empty">
			{{ t('empleados', 'No hierarchical relationships found yet.') }}
		</div>

		<div v-else class="organigrama-table">
			<div
				v-for="manager in filteredManagers"
				:key="manager.id"
				class="manager-card"
				:class="{ 'manager-card--open': expanded.has(manager.id) }">
				<div class="manager-card__header" @click="toggle(manager.id)">
					<span class="manager-card__chevron">
						<ChevronRight :size="18" />
					</span>

					<img class="manager-card__avatar" :src="avatarUrl(manager.uid)" :alt="manager.uid">

					<div class="manager-card__info">
						<span class="manager-card__name">{{ manager.displayname || manager.uid }}</span>
						<span class="manager-card__uid">{{ manager.uid }}</span>
					</div>

					<div class="manager-card__stats">
						<span class="stat-pill stat-pill--direct">
							{{ manager.directCount }} {{ t('empleados', 'direct') }}
						</span>
						<span class="stat-pill stat-pill--total">
							{{ manager.totalCount }} {{ t('empleados', 'total') }}
						</span>
					</div>
				</div>

				<transition name="expand">
					<div v-if="expanded.has(manager.id)" class="manager-card__body">
						<table class="dependents-table">
							<thead>
								<tr>
									<th>{{ t('empleados', 'Employee') }}</th>
									<th>{{ t('empleados', 'Level') }}</th>
									<th>{{ t('empleados', 'Reports through') }}</th>
								</tr>
							</thead>
							<tbody>
								<tr
									v-for="dep in manager.descendants"
									:key="dep.id"
									class="dependent-row"
									:class="`dependent-row--depth-${Math.min(dep.depth, 4)}`">
									<td class="dependent-row__name-cell">
										<span
											class="dependent-row__indent"
											:style="{ width: (dep.depth - 1) * 18 + 'px' }" />
										<img class="dependent-row__avatar" :src="avatarUrl(dep.uid)" :alt="dep.uid">
										<span>{{ dep.displayname || dep.uid }}</span>
									</td>
									<td>
										<span class="level-badge" :class="`level-badge--${Math.min(dep.depth, 4)}`">
											{{ levelLabel(dep.depth) }}
										</span>
									</td>
									<td class="dependent-row__path">
										{{ dep.depth > 1 ? dep.pathLabel : '—' }}
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</transition>
			</div>
		</div>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'

export default {
	name: 'OrganigramaTable',

	components: {
		ChevronRight,
	},

	props: {
		empleados: {
			type: Array,
			default: () => [],
		},
		relaciones: {
			type: Array,
			default: () => [],
		},
	},

	data() {
		return {
			search: '',
			expanded: new Set(),
		}
	},

	computed: {
		employeesById() {
			const map = {}
			this.empleados.forEach(emp => {
				map[emp.Id_empleados] = emp
			})
			return map
		},

		childrenMap() {
			const map = {}
			this.relaciones.forEach(rel => {
				if (!map[rel.id_empleado]) map[rel.id_empleado] = []
				map[rel.id_empleado].push(rel.id_dependiente)
			})
			return map
		},

		managers() {
			const result = []

			Object.keys(this.childrenMap).forEach(idStr => {
				const id = Number(idStr)
				const emp = this.employeesById[id]
				if (!emp) return

				const descendants = this.getDescendants(id)

				result.push({
					id,
					uid: emp.Id_user,
					displayname: emp.displayname,
					directCount: (this.childrenMap[id] || []).length,
					totalCount: descendants.length,
					descendants,
				})
			})

			return result.sort((a, b) => b.totalCount - a.totalCount)
		},

		filteredManagers() {
			if (!this.search.trim()) return this.managers

			const q = this.search.trim().toLowerCase()
			const matches = (name, uid) => (name || '').toLowerCase().includes(q) || (uid || '').toLowerCase().includes(q)

			return this.managers
				.filter(manager => {
					if (matches(manager.displayname, manager.uid)) return true
					return manager.descendants.some(dep => matches(dep.displayname, dep.uid))
				})
		},
	},

	methods: {
		t,

		avatarUrl(userId) {
			return generateUrl('/avatar/{userId}/64', { userId })
		},

		toggle(id) {
			if (this.expanded.has(id)) {
				this.expanded.delete(id)
			} else {
				this.expanded.add(id)
			}
			// Forzar reactividad del Set
			this.expanded = new Set(this.expanded)
		},

		levelLabel(depth) {
			const labels = {
				1: this.t('empleados', 'Level 1'),
				2: this.t('empleados', 'Level 2'),
				3: this.t('empleados', 'Level 3'),
			}
			return labels[depth] || this.t('empleados', 'Level {n}', { n: depth })
		},
		getDescendants(rootId) {
			const result = []
			const visited = new Set([rootId])

			const walk = (currentId, depth, pathNames) => {
				const childrenIds = this.childrenMap[currentId] || []

				childrenIds.forEach(childId => {
					if (visited.has(childId)) return
					visited.add(childId)

					const emp = this.employeesById[childId]
					if (!emp) return

					const newPath = [...pathNames, emp.displayname || emp.Id_user]

					result.push({
						id: childId,
						uid: emp.Id_user,
						displayname: emp.displayname,
						depth,
						pathLabel: depth > 1
							? this.t('empleados', 'via {chain}', { chain: pathNames.join(' › ') })
							: '',
					})

					walk(childId, depth + 1, newPath)
				})
			}

			const rootEmp = this.employeesById[rootId]
			walk(rootId, 1, [rootEmp.displayname || rootEmp.Id_user])

			return result
		},
	},
}
</script>

<style scoped lang="scss">
.organigrama-table-wrapper {
	height: 100%;
	overflow-y: auto;
	padding: 4px;
}

.organigrama-table-toolbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 12px;
}

.organigrama-search {
	flex: 1;
	max-width: 320px;
	height: 34px;
	padding: 0 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 13px;
}

.organigrama-table-count {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	white-space: nowrap;
}

.organigrama-table-empty {
	padding: 40px;
	text-align: center;
	color: var(--color-text-maxcontrast);
}

.organigrama-table {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.manager-card {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	overflow: hidden;
	transition: box-shadow 0.15s ease;
}

.manager-card--open {
	box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
}

.manager-card__header {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 16px;
	cursor: pointer;
	user-select: none;

	&:hover {
		background: var(--color-background-hover);
	}
}

.manager-card__chevron {
	display: flex;
	align-items: center;
	justify-content: center;
	color: var(--color-text-maxcontrast);
	transition: transform 0.18s ease;
}

.manager-card--open .manager-card__chevron {
	transform: rotate(90deg);
}

.manager-card__avatar {
	width: 36px;
	height: 36px;
	border-radius: 50%;
	object-fit: cover;
	flex-shrink: 0;
}

.manager-card__info {
	display: flex;
	flex-direction: column;
	min-width: 0;
	flex: 1;
}

.manager-card__name {
	font-weight: 700;
	font-size: 14px;
	color: var(--color-main-text);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.manager-card__uid {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.manager-card__stats {
	display: flex;
	gap: 8px;
	flex-shrink: 0;
}

.stat-pill {
	display: inline-flex;
	align-items: center;
	padding: 4px 10px;
	border-radius: 999px;
	font-size: 11px;
	font-weight: 700;
	white-space: nowrap;
}

.stat-pill--direct {
	background: rgba(52, 120, 246, 0.12);
	color: var(--color-primary-element);
}

.stat-pill--total {
	background: var(--color-background-hover);
	color: var(--color-main-text);
	border: 1px solid var(--color-border);
}

.manager-card__body {
	border-top: 1px solid var(--color-border);
	padding: 4px 16px 12px;
	background: rgba(148, 163, 184, 0.04);
}

.dependents-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;

	th {
		text-align: left;
		padding: 8px 6px;
		color: var(--color-text-maxcontrast);
		font-size: 11px;
		text-transform: uppercase;
		letter-spacing: 0.03em;
		border-bottom: 1px solid var(--color-border);
	}

	td {
		padding: 8px 6px;
		border-bottom: 1px solid rgba(148, 163, 184, 0.15);
	}

	tr:last-child td {
		border-bottom: none;
	}
}

.dependent-row__name-cell {
	display: flex;
	align-items: center;
	gap: 8px;
}

.dependent-row__indent {
	flex-shrink: 0;
}

.dependent-row__avatar {
	width: 26px;
	height: 26px;
	border-radius: 50%;
	object-fit: cover;
	flex-shrink: 0;
}

.dependent-row__path {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}

.level-badge {
	display: inline-flex;
	padding: 3px 9px;
	border-radius: 999px;
	font-size: 11px;
	font-weight: 700;
	white-space: nowrap;
}

.level-badge--1 { background: rgba(52, 199, 89, 0.15); color: #1a7f37; }
.level-badge--2 { background: rgba(255, 159, 10, 0.18); color: #a15c00; }
.level-badge--3 { background: rgba(255, 69, 58, 0.15); color: #b3261e; }
.level-badge--4 { background: rgba(140, 82, 255, 0.16); color: #6f2bd6; }

.expand-enter-active,
.expand-leave-active {
	transition: opacity 0.15s ease;
}

.expand-enter,
.expand-leave-to {
	opacity: 0;
}
</style>
