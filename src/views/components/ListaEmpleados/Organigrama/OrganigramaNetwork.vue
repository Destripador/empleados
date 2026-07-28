<template>
	<div class="organigrama-wrapper">
		<NcEmptyContent v-if="loading" :name="t('empleados', 'Loading')">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>

		<template v-else>
			<div class="organigrama-hint" :class="{ 'organigrama-hint--active': connectMode }">
				<span>{{ viewHint }}</span>
				<button
					v-if="connectMode"
					type="button"
					class="cancel-connection-btn"
					@click="exitConnectMode">
					{{ t('empleados', 'Cancel') }}
				</button>
			</div>

			<div class="organigrama-content">
				<div
					v-show="viewMode === 'network'"
					ref="networkContainer"
					class="organigrama-network"
					:class="{ 'organigrama-network--connecting': connectMode }" />
				<OrganigramaTraditional
					v-if="viewMode === 'traditional'"
					class="organigrama-network"
					:empleados="empleados"
					:relaciones="relaciones" />
				<OrganigramaTable
					v-if="viewMode === 'table'"
					class="organigrama-network"
					:empleados="empleados"
					:relaciones="relaciones" />

				<div class="organigrama-view-switch">
					<button
						type="button"
						class="view-switch-btn"
						:class="{ active: viewMode === 'network' }"
						:aria-pressed="viewMode === 'network' ? 'true' : 'false'"
						@click="setViewMode('network')">
						{{ t('empleados', 'Network') }}
					</button>
					<button
						type="button"
						class="view-switch-btn"
						:class="{ active: viewMode === 'traditional' }"
						:aria-pressed="viewMode === 'traditional' ? 'true' : 'false'"
						@click="setViewMode('traditional')">
						{{ t('empleados', 'Organization chart') }}
					</button>
					<button
						type="button"
						class="view-switch-btn"
						:class="{ active: viewMode === 'table' }"
						:aria-pressed="viewMode === 'table' ? 'true' : 'false'"
						@click="setViewMode('table')">
						{{ t('empleados', 'Table') }}
					</button>
				</div>
			</div>
		</template>
	</div>
</template>

<script>
import { Network } from 'vis-network'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import OrganigramaTable from './OrganigramaTable.vue'
import OrganigramaTraditional from './OrganigramaTraditional.vue'

import {
	NcEmptyContent,
	NcLoadingIcon,
} from '@nextcloud/vue'

export default {
	name: 'OrganigramaNetwork',

	components: {
		NcEmptyContent,
		NcLoadingIcon,
		OrganigramaTable,
		OrganigramaTraditional,
	},

	data() {
		return {
			loading: true,
			network: null,
			empleados: [],
			relaciones: [],
			posiciones: {},
			connectMode: false,
			connectionSourceId: null,
			connectionPending: false,
			viewMode: 'network',
		}
	},

	computed: {
		viewHint() {
			if (this.connectMode) {
				const source = this.empleados.find(
					employee => String(employee.Id_empleados) === String(this.connectionSourceId),
				)
				const sourceName = source?.Id_user || t('empleados', 'the selected employee')
				return t(
					'empleados',
					'Creating a connection from {employee}. Click the employee who will report to them.',
					{ employee: sourceName },
				)
			}
			if (this.viewMode === 'traditional') {
				return t('empleados', 'This view shows the complete hierarchical structure. Edit relationships from the Network view.')
			}
			if (this.viewMode === 'table') {
				return t('empleados', 'Expand a manager to see all their direct and indirect reports.')
			}
			return t('empleados', 'Drag an avatar to move it. To create a connection, double-click the manager and then click their dependent. Double-click a connection to remove it.')
		},
	},

	async mounted() {
		window.addEventListener('keydown', this.handleKeydown)
		await this.cargarDatos()
		this.loading = false
		this.$nextTick(() => this.buildNetwork())
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.handleKeydown)
		if (this.network) {
			this.network.destroy()
		}
	},

	methods: {
		t,

		setViewMode(viewMode) {
			const previousViewMode = this.viewMode
			if (viewMode !== 'network') {
				this.exitConnectMode()
			}
			this.viewMode = viewMode

			if (viewMode === 'network' && previousViewMode !== 'network') {
				this.$nextTick(() => {
					if (!this.network) return
					this.network.redraw()
					this.network.fit()
				})
			}
		},

		async cargarDatos() {
			try {
				const response = await axios.get(generateUrl('/apps/empleados/GetOrganigrama'))
				const data = response?.data?.ocs?.data
				this.empleados = data?.empleados || []
				this.relaciones = data?.relaciones || []

				const posMap = {}
				;(data?.posiciones || []).forEach(p => {
					posMap[p.id_empleado] = { x: Number(p.pos_x), y: Number(p.pos_y) }
				})
				this.posiciones = posMap
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [01] [{error}]', { error: String(err) }))
			}
		},

		avatarUrl(userId) {
			return generateUrl('/avatar/{userId}/64', { userId })
		},

		buildNetwork() {
			const nodes = this.empleados.map(emp => {
				const guardada = this.posiciones[emp.Id_empleados]
				const nodo = {
					id: emp.Id_empleados,
					label: emp.Id_user,
					shape: 'circularImage',
					image: this.avatarUrl(emp.Id_user),
					brokenImage: this.avatarUrl(emp.Id_user),
					size: 28,
				}

				if (guardada) {
					// Nodo con posición fija: no participa en la simulación de física,
					// pero sigue siendo arrastrable manualmente.
					nodo.x = guardada.x
					nodo.y = guardada.y
					nodo.physics = false
				}

				return nodo
			})

			const edges = this.relaciones.map(rel => ({
				id: `${rel.id_empleado}-${rel.id_dependiente}`,
				from: rel.id_empleado,
				to: rel.id_dependiente,
				arrows: 'to',
			}))

			const options = {
				physics: {
					enabled: true,
					solver: 'forceAtlas2Based',
					stabilization: { iterations: 150 },
				},
				edges: {
					smooth: { type: 'continuous' },
					color: { color: '#8a8a8a', highlight: '#3478f6' },
				},
				nodes: {
					borderWidth: 2,
					font: { size: 12 },
				},
				manipulation: {
					enabled: false,
					addEdge: (edgeData, callback) => {
						if (edgeData.from === edgeData.to) {
							showError(t('empleados', 'An employee cannot depend on themselves'))
							callback(null)
							this.exitConnectMode()
							return
						}
						this.crearRelacion(edgeData.from, edgeData.to, callback)
					},
				},
				interaction: {
					hover: true,
				},
			}

			this.network = new Network(
				this.$refs.networkContainer,
				{ nodes, edges },
				options,
			)

			// Cuando la física termina de acomodar los nodos nuevos (sin posición guardada),
			// congelamos el layout y guardamos TODAS las posiciones para que el próximo
			// reload se vea exactamente igual.
			this.network.once('stabilizationIterationsDone', () => {
				this.network.setOptions({ physics: { enabled: false } })
				this.network.fit()
				this.guardarTodasLasPosiciones()
			})

			// Si arrastras un nodo manualmente, guarda solo esa posición.
			this.network.on('dragEnd', (params) => {
				if (params.nodes.length === 1) {
					const idEmpleado = params.nodes[0]
					const pos = this.network.getPositions([idEmpleado])[idEmpleado]
					if (pos) {
						this.guardarPosicion(idEmpleado, pos.x, pos.y)
					}
				}
			})

			this.network.on('doubleClick', (params) => {
				if (params.nodes.length === 1) {
					if (!this.connectMode) {
						this.enterConnectMode(params.nodes[0])
					}
				} else if (params.nodes.length === 0 && params.edges.length === 1) {
					const edgeId = params.edges[0]
					const edge = this.network.body.data.edges.get(edgeId)
					if (edge) {
						this.eliminarRelacion(edge.from, edge.to, edge.id)
					}
				}
			})

			this.network.on('click', (params) => {
				if (!this.connectMode || params.nodes.length !== 1) return
				this.completeConnection(params.nodes[0])
			})
		},

		handleKeydown(event) {
			if (event.key === 'Escape' && this.connectMode) {
				this.exitConnectMode()
			}
		},

		enterConnectMode(sourceId) {
			if (this.connectMode) return
			this.connectMode = true
			this.connectionSourceId = sourceId
			this.network.selectNodes([sourceId])
		},

		completeConnection(targetId) {
			if (
				!this.connectMode
				|| this.connectionPending
				|| String(targetId) === String(this.connectionSourceId)
			) {
				return
			}

			const sourceId = this.connectionSourceId
			const alreadyExists = this.relaciones.some(
				relation => String(relation.id_empleado) === String(sourceId)
					&& String(relation.id_dependiente) === String(targetId),
			)
			if (alreadyExists) {
				showError(t('empleados', 'This connection already exists'))
				this.exitConnectMode()
				return
			}

			this.connectionPending = true
			this.crearRelacion(sourceId, targetId, edgeData => {
				if (edgeData) {
					this.network.body.data.edges.add(edgeData)
				}
			})
		},

		exitConnectMode() {
			this.connectMode = false
			this.connectionSourceId = null
			this.connectionPending = false
			if (this.network) {
				this.network.unselectAll()
			}
		},

		async guardarPosicion(idEmpleado, x, y) {
			try {
				await axios.post(generateUrl('/apps/empleados/GuardarPosicionOrganigrama'), {
					id_empleado: idEmpleado,
					x,
					y,
				})
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async guardarTodasLasPosiciones() {
			try {
				const ids = this.empleados.map(e => e.Id_empleados)
				const posiciones = this.network.getPositions(ids)
				const payload = Object.keys(posiciones).map(id => ({
					id_empleado: id,
					x: posiciones[id].x,
					y: posiciones[id].y,
				}))

				await axios.post(generateUrl('/apps/empleados/GuardarPosicionesOrganigrama'), {
					posiciones: payload,
				})
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [03] [{error}]', { error: String(err) }))
			}
		},

		async crearRelacion(idEmpleado, idDependiente, callback) {
			try {
				await axios.post(generateUrl('/apps/empleados/CrearRelacionOrganigrama'), {
					id_empleado: idEmpleado,
					id_dependiente: idDependiente,
				})
				callback({
					id: `${idEmpleado}-${idDependiente}`,
					from: idEmpleado,
					to: idDependiente,
					arrows: 'to',
				})

				// Reflejamos el cambio en los datos reactivos: esto es lo que hace
				// que la tabla (y cualquier otra vista) se actualice sola, sin
				// necesidad de recargar la página.
				this.relaciones = [
					...this.relaciones,
					{ id_empleado: idEmpleado, id_dependiente: idDependiente },
				]

				showSuccess(t('empleados', 'Connection created'))
			} catch (err) {
				callback(null)
				showError(t('empleados', 'An exception has occurred [03] [{error}]', { error: String(err) }))
			} finally {
				this.exitConnectMode()
			}
		},

		async eliminarRelacion(idEmpleado, idDependiente, edgeId) {
			try {
				await axios.post(generateUrl('/apps/empleados/EliminarRelacionOrganigrama'), {
					id_empleado: idEmpleado,
					id_dependiente: idDependiente,
				})
				this.network.body.data.edges.remove(edgeId)

				// Igual que al crear: quitamos la relación del array reactivo
				// para que la tabla se refresque automáticamente.
				this.relaciones = this.relaciones.filter(
					rel => !(rel.id_empleado === idEmpleado && rel.id_dependiente === idDependiente),
				)

				showSuccess(t('empleados', 'Connection removed'))
			} catch (err) {
				showError(t('empleados', 'An exception has occurred [03] [{error}]', { error: String(err) }))
			}
		},
	},
}
</script>

<style scoped lang="scss">
.organigrama-wrapper {
	display: flex;
	flex-direction: column;
	height: 100%;
}

.organigrama-hint {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin: 0;
	padding: 8px 16px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.organigrama-hint--active {
	color: var(--color-main-text);
	background: var(--color-primary-element-light);
	border-radius: var(--border-radius-large);
}

.cancel-connection-btn {
	flex: 0 0 auto;
	border: 0;
	background: transparent;
	color: var(--color-primary-element);
	font-weight: 600;
	cursor: pointer;
}

.organigrama-content {
	position: relative;
	flex: 1;
	min-height: 500px;
}

.organigrama-network {
	position: absolute;
	inset: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.organigrama-network--connecting {
	:deep(canvas) {
		cursor: crosshair;
	}
}

.organigrama-view-switch {
	position: absolute;
	left: 16px;
	bottom: 16px;
	z-index: 10;
	display: inline-flex;
	gap: 2px;
	padding: 4px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 999px;
	box-shadow: 0 6px 20px rgba(15, 23, 42, 0.14);
	backdrop-filter: blur(8px);
}

.view-switch-btn {
	border: none;
	background: transparent;
	padding: 7px 18px;
	border-radius: 999px;
	font-size: 12.5px;
	font-weight: 600;
	letter-spacing: 0.02em;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;

	&:hover {
		color: var(--color-main-text);
	}

	&.active {
		background: var(--color-primary-element);
		color: var(--color-primary-element-text, #fff);
		box-shadow: 0 2px 10px rgba(52, 120, 246, 0.35);
	}
}

@media (max-width: 600px) {
	.organigrama-view-switch {
		right: 8px;
		bottom: 8px;
		left: 8px;
		justify-content: center;
	}

	.view-switch-btn {
		flex: 1;
		padding: 7px 8px;
	}
}
</style>
