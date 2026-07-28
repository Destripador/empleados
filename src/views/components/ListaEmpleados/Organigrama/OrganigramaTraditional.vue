<template>
	<div class="traditional-organigram">
		<NcEmptyContent v-if="!organizationTree"
			class="traditional-organigram__empty"
			:name="t('empleados', 'No employees to display.')"
			:description="t('empleados', 'There is no valid employee information available for the organization chart.')">
			<template #icon>
				<AccountGroupOutline :size="64" />
			</template>
		</NcEmptyContent>

		<OrganizationChart v-else
			class="traditional-organigram__chart"
			:datasource="organizationTree">
			<template slot-scope="{ nodeData }">
				<article v-if="nodeData.virtual" class="organization-node">
					<OfficeBuildingOutline :size="28" aria-hidden="true" />
					<strong>{{ nodeData.name }}</strong>
				</article>

				<article v-else class="employee-node">
					<div class="employee-node__avatar">
						<img v-if="nodeData.uid"
							:src="avatarUrl(nodeData.uid)"
							:alt="nodeData.name">
						<span v-else aria-hidden="true">
							{{ nodeInitial(nodeData.name) }}
						</span>
					</div>

					<div class="employee-node__identity">
						<strong :title="nodeData.name">{{ nodeData.name }}</strong>
						<span class="employee-node__uid" :title="nodeData.uid">{{ nodeData.uid }}</span>
						<span class="employee-node__level">
							{{ t('empleados', 'Level {n}', { n: nodeData.depth }) }}
						</span>
					</div>

					<span v-if="nodeData.directCount"
						class="employee-node__reports"
						:title="t('empleados', '{count} direct reports', { count: nodeData.directCount })">
						{{ nodeData.directCount }}
					</span>
				</article>
			</template>
		</OrganizationChart>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import { NcEmptyContent } from '@nextcloud/vue'
import AccountGroupOutline from 'vue-material-design-icons/AccountGroupOutline.vue'
import OfficeBuildingOutline from 'vue-material-design-icons/OfficeBuildingOutline.vue'
import OrganizationChart from 'vue-organization-chart'
import 'vue-organization-chart/dist/orgchart.css'

export default {
	name: 'OrganigramaTraditional',

	components: {
		AccountGroupOutline,
		NcEmptyContent,
		OfficeBuildingOutline,
		OrganizationChart,
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

	computed: {
		organizationTree() {
			return this.buildOrganizationTree()
		},
	},

	methods: {
		t,

		avatarUrl(userId) {
			return generateUrl('/avatar/{userId}/64', { userId })
		},

		nodeInitial(name) {
			return String(name || '?').slice(0, 1).toLocaleUpperCase()
		},

		buildEmployeeMap() {
			const employeesById = new Map()

			this.empleados.forEach(employee => {
				const employeeId = this.normalizeEmployeeId(employee?.Id_empleados)
				if (employeeId === null || employeesById.has(employeeId)) return
				employeesById.set(employeeId, employee)
			})

			return employeesById
		},

		buildParentMap(employeesById) {
			const parentByEmployeeId = new Map()
			const managersSeenByEmployeeId = new Map()
			const relationKeys = new Set()

			this.getOrderedRelations().forEach(relation => {
				const managerId = this.normalizeEmployeeId(relation?.id_empleado)
				const dependentId = this.normalizeEmployeeId(relation?.id_dependiente)

				if (managerId === null
					|| dependentId === null
					|| !employeesById.has(managerId)
					|| !employeesById.has(dependentId)) {
					return
				}

				const relationKey = `${managerId}\u0000${dependentId}`
				if (relationKeys.has(relationKey)) return
				relationKeys.add(relationKey)

				if (managerId === dependentId) {
					this.warnHierarchy('A self-referencing organization relationship was ignored.', {
						employeeId: dependentId,
					})
					return
				}

				if (!managersSeenByEmployeeId.has(dependentId)) {
					managersSeenByEmployeeId.set(dependentId, new Set())
				}
				const managersSeen = managersSeenByEmployeeId.get(dependentId)
				if (managersSeen.size && !managersSeen.has(managerId)) {
					this.warnHierarchy('An employee has more than one manager. The first valid relationship was kept.', {
						employeeId: dependentId,
						managerIds: [...managersSeen, managerId],
					})
				}
				managersSeen.add(managerId)

				if (parentByEmployeeId.has(dependentId)) {
					return
				}

				if (this.wouldCreateCycle(managerId, dependentId, parentByEmployeeId)) {
					this.warnHierarchy('A cyclic organization relationship was ignored.', {
						managerId,
						employeeId: dependentId,
					})
					return
				}

				parentByEmployeeId.set(dependentId, managerId)
			})

			return parentByEmployeeId
		},

		getOrderedRelations() {
			return this.relaciones
				.map((relation, sourceIndex) => {
					const rawId = relation?.id
					const hasId = rawId !== null
						&& rawId !== undefined
						&& String(rawId).trim() !== ''
					return {
						relation,
						sourceIndex,
						sortId: hasId ? Number(rawId) : Number.NaN,
					}
				})
				.sort((first, second) => {
					const firstHasId = Number.isFinite(first.sortId)
					const secondHasId = Number.isFinite(second.sortId)

					if (firstHasId && secondHasId && first.sortId !== second.sortId) {
						return first.sortId - second.sortId
					}
					if (firstHasId !== secondHasId) return firstHasId ? -1 : 1
					return first.sourceIndex - second.sourceIndex
				})
				.map(entry => entry.relation)
		},

		buildChildrenMap(employeesById, parentByEmployeeId) {
			const childrenById = new Map()
			for (const employeeId of employeesById.keys()) {
				childrenById.set(employeeId, [])
			}

			parentByEmployeeId.forEach((managerId, dependentId) => {
				childrenById.get(managerId)?.push(dependentId)
			})

			return childrenById
		},

		findRootEmployees(employeesById, parentByEmployeeId) {
			return Array.from(employeesById.keys())
				.filter(employeeId => !parentByEmployeeId.has(employeeId))
		},

		buildTreeNode(employeeId, depth, branchVisited, context) {
			if (branchVisited.has(employeeId)) {
				this.warnHierarchy('A cycle was detected while rendering the organization chart.', {
					employeeId,
				})
				return null
			}

			if (context.renderedEmployeeIds.has(employeeId)) return null

			const employee = context.employeesById.get(employeeId)
			if (!employee) return null

			const currentBranch = new Set(branchVisited)
			currentBranch.add(employeeId)
			context.renderedEmployeeIds.add(employeeId)

			const children = (context.childrenById.get(employeeId) || [])
				.map(childId => this.buildTreeNode(childId, depth + 1, currentBranch, context))
				.filter(Boolean)

			const uid = String(employee.Id_user || '').trim()
			const displayName = String(employee.displayname || '').trim()

			return {
				id: this.employeeNodeId(employeeId),
				employeeId: employee.Id_empleados,
				uid,
				name: displayName || uid || t('empleados', 'Unknown employee'),
				depth,
				directCount: children.length,
				children,
			}
		},

		buildOrganizationTree() {
			const employeesById = this.buildEmployeeMap()
			if (!employeesById.size) return null

			const parentByEmployeeId = this.buildParentMap(employeesById)
			const childrenById = this.buildChildrenMap(employeesById, parentByEmployeeId)
			const rootEmployeeIds = this.findRootEmployees(employeesById, parentByEmployeeId)
			const context = {
				employeesById,
				childrenById,
				renderedEmployeeIds: new Set(),
			}

			const children = rootEmployeeIds
				.map(employeeId => this.buildTreeNode(employeeId, 1, new Set(), context))
				.filter(Boolean)

			// Defensive fallback: even malformed data must remain attached to the
			// single visible Organization root.
			for (const employeeId of employeesById.keys()) {
				if (context.renderedEmployeeIds.has(employeeId)) continue
				const node = this.buildTreeNode(employeeId, 1, new Set(), context)
				if (node) children.push(node)
			}

			return {
				id: 'organization-root',
				name: t('empleados', 'Organization'),
				virtual: true,
				depth: 0,
				children,
			}
		},

		wouldCreateCycle(managerId, dependentId, parentByEmployeeId) {
			const visited = new Set()
			let currentId = managerId

			while (currentId !== undefined) {
				if (currentId === dependentId || visited.has(currentId)) return true
				visited.add(currentId)
				currentId = parentByEmployeeId.get(currentId)
			}

			return false
		},

		warnHierarchy(message, details) {
			// eslint-disable-next-line no-console
			console.warn(`[empleados] ${message}`, details)
		},

		normalizeEmployeeId(value) {
			if (value === null || value === undefined) return null
			if (typeof value !== 'string' && typeof value !== 'number') return null
			if (typeof value === 'number' && !Number.isFinite(value)) return null

			const normalized = String(value).trim()
			if (!normalized) return null

			if (/^[+-]?\d+$/.test(normalized)) {
				const numericId = Number(normalized)
				if (Number.isSafeInteger(numericId)) return String(numericId)
			}

			return normalized
		},

		employeeNodeId(employeeId) {
			return `employee-${encodeURIComponent(employeeId)}`
		},
	},
}
</script>

<style scoped lang="scss">
.traditional-organigram {
	width: 100%;
	height: 100%;
	min-width: 0;
	min-height: 0;
	overflow: hidden;
	color: var(--color-main-text);
	background: var(--color-main-background);
}

.traditional-organigram__empty {
	height: 100%;
	padding: 24px;
}

::v-deep .traditional-organigram__chart.orgchart-container {
	display: block;
	width: 100%;
	height: 100%;
	overflow: auto;
	border: 0;
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	text-align: center;
}

::v-deep .traditional-organigram__chart .orgchart {
	min-width: 900px;
	min-height: 100%;
	padding: 32px 48px 104px;
	border: 0;
	background: var(--color-main-background);
	background-image: none;
}

::v-deep .traditional-organigram__chart .orgchart .node {
	width: 194px;
	padding: 4px;
	border: 0;
	background: transparent;
}

::v-deep .traditional-organigram__chart .orgchart .node:hover,
::v-deep .traditional-organigram__chart .orgchart .node.focused {
	background: transparent;
}

::v-deep .traditional-organigram__chart .orgchart td {
	background: var(--color-main-background);
}

::v-deep .traditional-organigram__chart .orgchart .lines .topLine {
	border-top-color: var(--color-border);
}

::v-deep .traditional-organigram__chart .orgchart .lines .rightLine {
	border-right-color: var(--color-border);
}

::v-deep .traditional-organigram__chart .orgchart .lines .leftLine {
	border-left-color: var(--color-border);
}

::v-deep .traditional-organigram__chart .orgchart .lines .downLine {
	background-color: var(--color-border);
}

.organization-node {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 10px;
	width: 186px;
	min-height: 58px;
	padding: 10px 12px;
	border: 1px solid var(--color-primary-element);
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	text-align: center;

	strong {
		overflow: hidden;
		color: inherit;
		font-size: 14px;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}

.employee-node {
	position: relative;
	display: flex;
	align-items: center;
	gap: 9px;
	width: 186px;
	min-height: 58px;
	padding: 8px 9px;
	overflow: hidden;
	border: 1px solid var(--color-border);
	border-left: 3px solid var(--color-primary-element);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	color: var(--color-main-text);
	text-align: left;
}

.employee-node__avatar {
	display: flex;
	flex: 0 0 40px;
	align-items: center;
	justify-content: center;
	width: 40px;
	height: 40px;
	overflow: hidden;
	border: 1px solid var(--color-border);
	border-radius: 50%;
	background: var(--color-background-hover);
	color: var(--color-main-text);
	font-size: 16px;
	font-weight: 700;

	img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
}

.employee-node__identity {
	display: flex;
	flex: 1;
	flex-direction: column;
	min-width: 0;

	strong,
	span {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	strong {
		color: var(--color-main-text);
		font-size: 13px;
		line-height: 18px;
	}

	span {
		color: var(--color-text-maxcontrast);
		font-size: 11px;
		line-height: 16px;
	}
}

.employee-node__identity .employee-node__level {
	font-size: 10px;
}

.employee-node__reports {
	display: inline-flex;
	flex: 0 0 auto;
	align-items: center;
	justify-content: center;
	min-width: 22px;
	height: 22px;
	padding: 0 6px;
	border: 1px solid var(--color-primary-element);
	border-radius: 999px;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-size: 11px;
	font-weight: 700;
}

@media (max-width: 600px) {
	::v-deep .traditional-organigram__chart .orgchart {
		min-width: 720px;
		padding: 24px 28px 104px;
	}
}
</style>
