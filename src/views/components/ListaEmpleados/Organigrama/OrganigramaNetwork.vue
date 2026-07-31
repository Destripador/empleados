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
			ringRadii: [0, 0, 0, 0, 0],
			espacioPorEmpleado: 70,
			radioMinimoEntreAnillos: 300,
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

		ringLabels() {
			return [
				null,
				t('empleados', 'Socios'),
				t('empleados', 'Gerentes'),
				t('empleados', 'Supervisores'),
				t('empleados', 'Staff'),
			]
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

		// Calcula el nivel jerárquico (anillo) de cada empleado a partir de las
		// relaciones jefe -> dependiente, vía BFS desde las "raíces" (quienes no
		// dependen de nadie, es decir, el/los patrón(es)).
		calcularNiveles() {
			const niveles = {}
			const hijosDe = {}
			const esDependiente = new Set()

			this.relaciones.forEach(rel => {
				esDependiente.add(String(rel.id_dependiente))
				if (!hijosDe[rel.id_empleado]) hijosDe[rel.id_empleado] = []
				hijosDe[rel.id_empleado].push(rel.id_dependiente)
			})

			const raices = this.empleados
				.map(emp => emp.Id_empleados)
				.filter(id => !esDependiente.has(String(id)))

			const visitado = new Set(raices.map(id => String(id)))
			const cola = raices.map(id => ({ id, nivel: 0 }))

			while (cola.length) {
				const { id, nivel } = cola.shift()
				niveles[id] = Math.min(nivel, this.ringLabels.length - 1)
				const hijos = hijosDe[id] || []
				hijos.forEach(hijoId => {
					if (!visitado.has(String(hijoId))) {
						visitado.add(String(hijoId))
						cola.push({ id: hijoId, nivel: nivel + 1 })
					}
				})
			}

			// Empleados sin ninguna relación: los mandamos al anillo exterior (staff)
			this.empleados.forEach(emp => {
				if (!(emp.Id_empleados in niveles)) {
					niveles[emp.Id_empleados] = this.ringLabels.length - 1
				}
			})

			return niveles
		},

		// Calcula el radio de cada anillo según cuántos empleados le tocan.
		// Entre más gente en un nivel, más grande su circunferencia, para que
		// siempre haya "espacioPorEmpleado" px de separación entre avatares.
		calcularRadios(niveles) {
			const conteoPorNivel = [0, 0, 0, 0, 0]
			Object.values(niveles).forEach(nivel => {
				conteoPorNivel[nivel] = (conteoPorNivel[nivel] || 0) + 1
			})

			const radios = [0]
			for (let nivel = 1; nivel < conteoPorNivel.length; nivel++) {
				const cantidad = conteoPorNivel[nivel] || 0
				// Radio necesario para que la circunferencia completa (2πr) alcance
				// a darle "espacioPorEmpleado" px a cada quien.
				const radioPorCantidad = (cantidad * this.espacioPorEmpleado) / (2 * Math.PI)
				// Nunca más chico que el anillo anterior + un mínimo de separación.
				const radioMinimo = radios[nivel - 1] + this.radioMinimoEntreAnillos
				radios.push(Math.max(radioPorCantidad, radioMinimo))
			}

			return radios
		},

		// Calcula el ángulo de cada empleado dentro de su anillo, agrupando a
		// los hijos cerca del ángulo de su jefe para minimizar cruces de líneas.
		calcularAngulos(niveles) {
			const angulos = {}
			const padreDe = {}
			this.relaciones.forEach(rel => {
				padreDe[rel.id_dependiente] = rel.id_empleado
			})

			const raices = this.empleados
				.map(emp => emp.Id_empleados)
				.filter(id => niveles[id] === 0)

			raices.forEach((id, index) => {
				angulos[id] = (2 * Math.PI * index) / Math.max(raices.length, 1)
			})

			const maxNivel = Math.max(0, ...Object.values(niveles))
			for (let nivel = 1; nivel <= maxNivel; nivel++) {
				const idsDelNivel = this.empleados
					.map(emp => emp.Id_empleados)
					.filter(id => niveles[id] === nivel)

				idsDelNivel.sort((a, b) => {
					const anguloA = angulos[padreDe[a]] ?? 0
					const anguloB = angulos[padreDe[b]] ?? 0
					if (anguloA !== anguloB) return anguloA - anguloB
					return String(a).localeCompare(String(b))
				})

				idsDelNivel.forEach((id, index) => {
					angulos[id] = (2 * Math.PI * index) / Math.max(idsDelNivel.length, 1)
				})
			}

			return angulos
		},

		buildNetwork() {
			const niveles = this.calcularNiveles()
			this.ringRadii = this.calcularRadios(niveles)
			const angulos = this.calcularAngulos(niveles)

			const nodes = this.empleados.map(emp => {
				const guardada = this.posiciones[emp.Id_empleados]
				const nodo = {
					id: emp.Id_empleados,
					label: emp.Id_user,
					shape: 'circularImage',
					image: this.avatarUrl(emp.Id_user),
					brokenImage: this.avatarUrl(emp.Id_user),
					size: 28,
					physics: false,
				}

				if (guardada) {
					nodo.x = guardada.x
					nodo.y = guardada.y
				} else {
					const nivel = niveles[emp.Id_empleados] ?? this.ringLabels.length - 1
					const angulo = angulos[emp.Id_empleados] ?? 0
					const radio = this.ringRadii[nivel]
					nodo.x = radio * Math.cos(angulo)
					nodo.y = radio * Math.sin(angulo)
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
					enabled: false,
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

			this.network.on('beforeDrawing', (ctx) => {
				ctx.save()

				for (let nivel = 1; nivel < this.ringRadii.length; nivel++) {
					const radio = this.ringRadii[nivel]
					if (!radio) continue

					ctx.beginPath()
					ctx.arc(0, 0, radio, 0, 2 * Math.PI)
					ctx.strokeStyle = '#3478f6'
					ctx.lineWidth = 1
					ctx.setLineDash([4, 6])
					ctx.stroke()

					const etiqueta = this.ringLabels[nivel]
					if (etiqueta) {
						ctx.font = '11px sans-serif'
						ctx.fillStyle = 'rgba(150, 150, 150, 0.5)'
						ctx.textAlign = 'center'
						ctx.setLineDash([])
						ctx.fillText(etiqueta, 0, -radio - 6)
					}
				}

				ctx.restore()
			})

			this.network.fit()

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
