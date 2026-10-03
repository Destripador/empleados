<template>
	<div class="entity-network-wrapper">
		<header class="entity-network-header">
			<div class="entity-network-heading">
				<p class="entity-network-eyebrow">
					{{ eyebrow }}
				</p>
				<h2 class="entity-network-title">
					{{ title }}
				</h2>
				<p class="entity-network-hint">
					{{ hint }}
				</p>
			</div>

			<div class="entity-network-stats">
				<div class="entity-stat">
					<span>{{ t('empleados', 'Visible') }}</span>
					<strong>{{ stats.visible }}</strong>
				</div>
				<div class="entity-stat">
					<span>{{ t('empleados', 'Employees') }}</span>
					<strong>{{ stats.employees }}</strong>
				</div>
				<div class="entity-stat">
					<span>{{ t('empleados', 'Largest') }}</span>
					<strong>{{ stats.largestLabel }}</strong>
					<small v-if="stats.largestCount > 0">
						{{ stats.largestCount }} {{ t('empleados', 'employees') }}
					</small>
				</div>
			</div>
		</header>

		<div class="entity-network-content">
			<div
				ref="networkContainer"
				class="entity-network-canvas" />

			<div class="entity-network-fab" :class="{ 'is-open': menuOpen }">
				<div
					v-if="menuOpen"
					class="entity-network-menu"
					role="menu">
					<button
						type="button"
						class="menu-item"
						role="menuitem"
						:disabled="!normalizedItems.length || exporting"
						@click="onExportClick">
						<ExportVariant :size="18" />
						<span>{{ exporting
							? t('empleados', 'Exporting...')
							: t('empleados', 'Export to draw.io') }}</span>
					</button>

					<div class="menu-divider" />

					<p class="menu-section">
						{{ t('empleados', 'Map layout') }}
					</p>

					<button
						v-for="option in layoutOptions"
						:key="option.id"
						type="button"
						class="menu-item"
						:class="{ active: layoutMode === option.id }"
						role="menuitem"
						:disabled="!normalizedItems.length"
						@click="applyLayout(option.id)">
						<component :is="option.icon" :size="18" />
						<span>{{ option.label }}</span>
					</button>

					<div class="menu-divider" />

					<button
						type="button"
						class="menu-item"
						:class="{ active: floatingEnabled }"
						role="menuitem"
						:disabled="!normalizedItems.length"
						@click="toggleFloating">
						<WeatherWindy :size="18" />
						<span>{{ floatingEnabled
							? t('empleados', 'Floating: on')
							: t('empleados', 'Floating: off') }}</span>
					</button>
				</div>

				<button
					type="button"
					class="fab-btn"
					:aria-expanded="menuOpen ? 'true' : 'false'"
					:aria-label="t('empleados', 'Map options')"
					@click.stop="toggleMenu">
					<TuneVariant :size="22" />
				</button>
			</div>
		</div>
	</div>
</template>

<script>
import { Network } from 'vis-network'
import { translate as t } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'
import usernameToColor from '@nextcloud/vue/functions/usernameToColor'
import ArrowCollapseAll from 'vue-material-design-icons/ArrowCollapseAll.vue'
import ArrowExpandAll from 'vue-material-design-icons/ArrowExpandAll.vue'
import ExportVariant from 'vue-material-design-icons/ExportVariant.vue'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import ShuffleVariant from 'vue-material-design-icons/ShuffleVariant.vue'
import TuneVariant from 'vue-material-design-icons/TuneVariant.vue'
import WeatherWindy from 'vue-material-design-icons/WeatherWindy.vue'

const MIN_SIZE = 28
const MAX_SIZE = 90
const COLOR_ALPHA = 0.42
const COLOR_BORDER_ALPHA = 0.55
const COLOR_HIGHLIGHT_ALPHA = 0.62
const FLOAT_AMPLITUDE_X = 5
const FLOAT_AMPLITUDE_Y = 4
const FLOAT_SPEED = 0.65

const LAYOUT_PHYSICS = {
	default: {
		gravitationalConstant: -4200,
		centralGravity: 0.18,
		springLength: 210,
		springConstant: 0.035,
		damping: 0.42,
	},
	separate: {
		gravitationalConstant: -14000,
		centralGravity: 0.02,
		springLength: 340,
		springConstant: 0.015,
		damping: 0.35,
	},
	cluster: {
		gravitationalConstant: -900,
		centralGravity: 0.85,
		springLength: 90,
		springConstant: 0.08,
		damping: 0.55,
	},
}

export default {
	name: 'EntityCountNetwork',

	components: {
		ArrowCollapseAll,
		ArrowExpandAll,
		ExportVariant,
		Refresh,
		ShuffleVariant,
		TuneVariant,
		WeatherWindy,
	},

	props: {
		items: {
			type: Array,
			required: false,
			default: () => [],
		},
		/**
		 * 'area' | 'position' | 'team'
		 */
		entityType: {
			type: String,
			required: false,
			default: 'area',
		},
		/**
		 * Draw parent→child edges when items include parentId.
		 */
		showHierarchy: {
			type: Boolean,
			required: false,
			default: false,
		},
	},

	emits: ['select'],

	data() {
		const prefersReducedMotion = typeof window !== 'undefined'
			&& window.matchMedia
			&& window.matchMedia('(prefers-reduced-motion: reduce)').matches

		return {
			network: null,
			exporting: false,
			menuOpen: false,
			layoutMode: 'default',
			floatingEnabled: !prefersReducedMotion,
			floatBases: {},
			floatPhases: {},
			floatRaf: null,
			dragging: false,
			layoutBusy: false,
		}
	},

	computed: {
		eyebrow() {
			if (this.entityType === 'position') {
				return t('empleados', 'Positions map')
			}
			if (this.entityType === 'team') {
				return t('empleados', 'Teams map')
			}
			return t('empleados', 'Areas map')
		},

		title() {
			if (this.entityType === 'position') {
				return t('empleados', 'Distribution by position')
			}
			if (this.entityType === 'team') {
				return t('empleados', 'Distribution by team')
			}
			return t('empleados', 'Distribution by area')
		},

		hint() {
			if (this.entityType === 'position') {
				return t('empleados', 'Circle size shows how many employees have each position. Double-click a circle to open it.')
			}
			if (this.entityType === 'team') {
				return t('empleados', 'Circle size shows how many employees belong to each team. Double-click a circle to open it.')
			}
			return t('empleados', 'Circle size shows how many employees belong to each area. Double-click a circle to open it.')
		},

		exportFileName() {
			if (this.entityType === 'position') {
				return 'mapa-puestos.drawio'
			}
			if (this.entityType === 'team') {
				return 'mapa-equipos.drawio'
			}
			return 'mapa-areas.drawio'
		},

		diagramName() {
			return this.title
		},

		layoutOptions() {
			return [
				{
					id: 'default',
					label: t('empleados', 'Default layout'),
					icon: 'Refresh',
				},
				{
					id: 'separate',
					label: t('empleados', 'Separate all'),
					icon: 'ArrowExpandAll',
				},
				{
					id: 'cluster',
					label: t('empleados', 'Bring together'),
					icon: 'ArrowCollapseAll',
				},
				{
					id: 'random',
					label: t('empleados', 'Random layout'),
					icon: 'ShuffleVariant',
				},
			]
		},

		normalizedItems() {
			return (this.items || [])
				.map((item) => {
					const id = String(
						item.id
						?? item.Id_departamento
						?? item.Id_puestos
						?? item.Id_equipo
						?? '',
					)
					const label = String(
						item.label
						?? item.Nombre
						?? item.nombre
						?? id,
					)
					const count = Math.max(0, Number(
						item.count
						?? item.cantidad_empleados
						?? 0,
					) || 0)
					const parentIdRaw = item.parentId
						?? item.Id_padre
						?? null
					const parentId = parentIdRaw !== null && parentIdRaw !== undefined && String(parentIdRaw) !== ''
						? String(parentIdRaw)
						: null

					return {
						id,
						label,
						count,
						parentId,
						raw: item.raw ?? item,
					}
				})
				.filter(item => item.id !== '' && item.count > 0)
		},

		stats() {
			const items = this.normalizedItems
			const employees = items.reduce((total, item) => total + item.count, 0)
			const largest = [...items].sort((a, b) => b.count - a.count)[0] || null

			return {
				visible: items.length,
				employees,
				largestLabel: largest?.label || t('empleados', 'No data'),
				largestCount: largest?.count || 0,
			}
		},
	},

	watch: {
		items: {
			deep: true,
			handler() {
				this.$nextTick(() => this.buildNetwork())
			},
		},
		showHierarchy() {
			this.$nextTick(() => this.buildNetwork())
		},
	},

	mounted() {
		this.$nextTick(() => this.buildNetwork())
		window.addEventListener('resize', this.handleResize)
		window.addEventListener('keydown', this.onKeyDown)
		document.addEventListener('click', this.onDocumentClick)
	},

	beforeDestroy() {
		window.removeEventListener('resize', this.handleResize)
		window.removeEventListener('keydown', this.onKeyDown)
		document.removeEventListener('click', this.onDocumentClick)
		this.stopFloat()
		this.destroyNetwork()
	},

	methods: {
		t,

		handleResize() {
			if (this.network) {
				this.network.redraw()
			}
		},

		onKeyDown(event) {
			if (event.key !== 'Escape') {
				return
			}
			if (this.menuOpen) {
				this.menuOpen = false
				event.stopPropagation()
			}
		},

		onDocumentClick(event) {
			if (!this.menuOpen) {
				return
			}
			const fab = this.$el?.querySelector?.('.entity-network-fab')
			if (fab && !fab.contains(event.target)) {
				this.menuOpen = false
			}
		},

		toggleMenu() {
			this.menuOpen = !this.menuOpen
		},

		wrapLabelName(name, maxChars = 18) {
			const words = String(name || '').trim().split(/\s+/).filter(Boolean)
			if (!words.length) {
				return '—'
			}

			const lines = []
			let current = ''

			words.forEach((word) => {
				const next = current ? `${current} ${word}` : word
				if (next.length <= maxChars) {
					current = next
					return
				}
				if (current) {
					lines.push(current)
				}
				current = word.length > maxChars
					? `${word.slice(0, maxChars - 1)}…`
					: word
			})

			if (current) {
				lines.push(current)
			}

			if (lines.length <= 2) {
				return lines.join('\n')
			}

			return `${lines[0]}\n${lines[1].length >= maxChars
				? `${lines[1].slice(0, maxChars - 1)}…`
				: `${lines[1]}…`}`
		},

		formatNodeLabel(name, count) {
			const nameLines = this.wrapLabelName(name)
				.split('\n')
				.map(line => `*${line}*`)
				.join('\n')
			const countLine = t('empleados', '{count} employees', { count })
			return `${nameLines}\n${countLine}`
		},

		nodeFont(size) {
			return {
				size,
				color: '#4b5563',
				face: 'inherit',
				multi: 'markdown',
				align: 'center',
				vadjust: 16,
				strokeWidth: 6,
				strokeColor: '#ffffff',
				bold: {
					color: '#111827',
					size: size + 1,
					mod: 'bold',
					vadjust: 16,
				},
			}
		},

		onExportClick() {
			this.menuOpen = false
			this.exportarDrawio()
		},

		toggleFloating() {
			this.floatingEnabled = !this.floatingEnabled
			if (this.floatingEnabled) {
				this.captureFloatBases()
				this.startFloat()
			} else {
				this.stopFloat()
				this.restoreFloatBases()
			}
		},

		destroyNetwork() {
			this.stopFloat()
			if (this.network) {
				this.network.destroy()
				this.network = null
			}
		},

		nodeSize(count, maxCount) {
			const safeCount = Math.max(1, Number(count) || 1)
			const safeMax = Math.max(1, Number(maxCount) || safeCount)
			const ratio = Math.sqrt(safeCount / safeMax)
			return Math.round(MIN_SIZE + (MAX_SIZE - MIN_SIZE) * ratio)
		},

		softenRgb(rgb, amount = 0.28) {
			return {
				r: Math.round(rgb.r + (255 - rgb.r) * amount),
				g: Math.round(rgb.g + (255 - rgb.g) * amount),
				b: Math.round(rgb.b + (255 - rgb.b) * amount),
			}
		},

		rgba(rgb, alpha) {
			return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha})`
		},

		hexColor(rgb) {
			const toHex = (value) => Number(value).toString(16).padStart(2, '0')
			return `#${toHex(rgb.r)}${toHex(rgb.g)}${toHex(rgb.b)}`
		},

		paletteFor(label) {
			const base = this.softenRgb(usernameToColor(String(label || 'x')))
			const border = this.softenRgb({
				r: Math.max(0, base.r - 35),
				g: Math.max(0, base.g - 35),
				b: Math.max(0, base.b - 35),
			}, 0.12)
			const highlight = this.softenRgb({
				r: Math.min(255, base.r + 20),
				g: Math.min(255, base.g + 20),
				b: Math.min(255, base.b + 20),
			}, 0.18)

			return { base, border, highlight }
		},

		nodeColor(label) {
			const { base, border, highlight } = this.paletteFor(label)
			return {
				background: this.rgba(base, COLOR_ALPHA),
				border: this.rgba(border, COLOR_BORDER_ALPHA),
				highlight: {
					background: this.rgba(highlight, COLOR_HIGHLIGHT_ALPHA),
					border: this.rgba(border, COLOR_BORDER_ALPHA),
				},
			}
		},

		physicsOptions(mode = this.layoutMode) {
			const barnesHut = LAYOUT_PHYSICS[mode] || LAYOUT_PHYSICS.default
			return {
				enabled: true,
				stabilization: {
					enabled: true,
					iterations: 140,
					fit: true,
				},
				barnesHut: { ...barnesHut },
				maxVelocity: 40,
				minVelocity: 0.75,
				timestep: 0.4,
			}
		},

		buildNetwork() {
			const container = this.$refs.networkContainer
			if (!container) {
				return
			}

			this.destroyNetwork()

			const items = this.normalizedItems
			const ids = new Set(items.map(item => item.id))
			const maxCount = items.reduce((max, item) => Math.max(max, item.count), 1)

			const nodes = items.map((item) => {
				const size = this.nodeSize(item.count, maxCount)
				const fontSize = item.count >= maxCount * 0.55 ? 13 : 12
				return {
					id: item.id,
					label: this.formatNodeLabel(item.label, item.count),
					title: t('empleados', '{name}: {count} employees', {
						name: item.label,
						count: item.count,
					}),
					value: item.count,
					size,
					shape: 'dot',
					color: this.nodeColor(item.label),
					font: this.nodeFont(fontSize),
					borderWidth: 2,
					margin: {
						top: 10,
						right: 10,
						bottom: 22,
						left: 10,
					},
					widthConstraint: {
						maximum: 140,
					},
				}
			})

			const edges = []
			if (this.showHierarchy) {
				for (const item of items) {
					if (!item.parentId || !ids.has(item.parentId) || item.parentId === item.id) {
						continue
					}
					edges.push({
						from: item.parentId,
						to: item.id,
						arrows: 'to',
						color: { color: 'rgba(100, 116, 139, 0.45)' },
						smooth: {
							type: 'continuous',
							roundness: 0.35,
						},
					})
				}
			}

			const options = {
				autoResize: true,
				interaction: {
					hover: true,
					tooltipDelay: 120,
					multiselect: false,
					dragNodes: true,
					dragView: true,
					zoomView: true,
				},
				physics: this.physicsOptions(this.layoutMode === 'random' ? 'default' : this.layoutMode),
				nodes: {
					scaling: {
						min: MIN_SIZE,
						max: MAX_SIZE,
					},
					font: this.nodeFont(12),
					margin: {
						top: 8,
						right: 8,
						bottom: 20,
						left: 8,
					},
				},
				edges: {
					width: 1.5,
					selectionWidth: 2,
				},
			}

			this.network = new Network(
				container,
				{ nodes, edges },
				options,
			)

			this.network.on('doubleClick', (params) => {
				const nodeId = params?.nodes?.[0]
				if (nodeId === undefined || nodeId === null) {
					return
				}
				const match = items.find(item => String(item.id) === String(nodeId))
				if (match) {
					this.$emit('select', match.raw)
				}
			})

			this.network.on('dragStart', () => {
				this.dragging = true
				this.stopFloat()
			})

			this.network.on('dragEnd', () => {
				this.dragging = false
				this.captureFloatBases()
				if (this.floatingEnabled) {
					this.startFloat()
				}
			})

			this.network.once('stabilizationIterationsDone', () => {
				if (!this.network) {
					return
				}
				this.network.setOptions({ physics: { enabled: false } })
				this.network.fit({ animation: false })
				this.captureFloatBases()
				if (this.floatingEnabled) {
					this.startFloat()
				}
			})
		},

		captureFloatBases() {
			if (!this.network) {
				return
			}
			const positions = this.network.getPositions()
			this.floatBases = {}
			this.floatPhases = {}
			Object.keys(positions).forEach((id) => {
				this.floatBases[id] = {
					x: positions[id].x,
					y: positions[id].y,
				}
				if (this.floatPhases[id] === undefined) {
					this.floatPhases[id] = Math.random() * Math.PI * 2
				}
			})
		},

		restoreFloatBases() {
			if (!this.network || !Object.keys(this.floatBases).length) {
				return
			}
			Object.entries(this.floatBases).forEach(([id, pos]) => {
				this.network.moveNode(id, pos.x, pos.y)
			})
		},

		startFloat() {
			this.stopFloat()
			if (!this.network || !this.floatingEnabled || this.dragging) {
				return
			}
			if (!Object.keys(this.floatBases).length) {
				this.captureFloatBases()
			}

			const tick = (timestamp) => {
				if (!this.network || !this.floatingEnabled || this.dragging) {
					this.floatRaf = null
					return
				}

				const time = timestamp / 1000
				Object.entries(this.floatBases).forEach(([id, base]) => {
					const phase = this.floatPhases[id] || 0
					const x = base.x + Math.sin(time * FLOAT_SPEED + phase) * FLOAT_AMPLITUDE_X
					const y = base.y + Math.cos(time * (FLOAT_SPEED * 0.85) + phase * 1.35) * FLOAT_AMPLITUDE_Y
					this.network.moveNode(id, x, y)
				})

				this.floatRaf = requestAnimationFrame(tick)
			}

			this.floatRaf = requestAnimationFrame(tick)
		},

		stopFloat() {
			if (this.floatRaf !== null) {
				cancelAnimationFrame(this.floatRaf)
				this.floatRaf = null
			}
		},

		runPhysicsLayout(mode) {
			if (!this.network) {
				return
			}

			this.layoutBusy = true
			this.stopFloat()
			this.network.setOptions({
				physics: this.physicsOptions(mode),
			})
			this.network.stabilize(160)

			this.network.once('stabilized', () => {
				if (!this.network) {
					return
				}
				this.network.setOptions({ physics: { enabled: false } })
				this.network.fit({
					animation: {
						duration: 450,
						easingFunction: 'easeInOutQuad',
					},
				})
				this.captureFloatBases()
				this.layoutBusy = false
				if (this.floatingEnabled) {
					this.startFloat()
				}
			})
		},

		applyRandomLayout() {
			if (!this.network) {
				return
			}

			this.layoutBusy = true
			this.stopFloat()

			const count = Math.max(1, this.normalizedItems.length)
			const span = Math.max(360, 90 * Math.sqrt(count))

			this.normalizedItems.forEach((item) => {
				const angle = Math.random() * Math.PI * 2
				const radius = Math.random() * span * 0.5
				this.network.moveNode(
					item.id,
					Math.cos(angle) * radius,
					Math.sin(angle) * radius,
				)
			})

			this.network.setOptions({
				physics: this.physicsOptions('separate'),
			})
			this.network.stabilize(90)

			this.network.once('stabilized', () => {
				if (!this.network) {
					return
				}
				this.network.setOptions({ physics: { enabled: false } })
				this.network.fit({
					animation: {
						duration: 450,
						easingFunction: 'easeInOutQuad',
					},
				})
				this.captureFloatBases()
				this.layoutBusy = false
				if (this.floatingEnabled) {
					this.startFloat()
				}
			})
		},

		applyLayout(mode) {
			this.menuOpen = false
			if (!this.network || !this.normalizedItems.length || this.layoutBusy) {
				return
			}

			this.layoutMode = mode

			if (mode === 'random') {
				this.applyRandomLayout()
				return
			}

			if (mode === 'default') {
				this.buildNetwork()
				return
			}

			this.runPhysicsLayout(mode)
		},

		escapeXml(value) {
			return String(value ?? '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&apos;')
		},

		descargarArchivo(contenido, nombre, tipo) {
			const blob = new Blob([contenido], {
				type: `${tipo};charset=utf-8`,
			})
			const url = URL.createObjectURL(blob)
			const enlace = document.createElement('a')
			enlace.href = url
			enlace.download = nombre
			document.body.appendChild(enlace)
			enlace.click()
			document.body.removeChild(enlace)
			URL.revokeObjectURL(url)
		},

		exportarDrawio() {
			if (!this.network) {
				showError(t('empleados', 'The map is not available'))
				return
			}

			const items = this.normalizedItems
			if (!items.length) {
				showError(t('empleados', 'There is nothing to export'))
				return
			}

			this.exporting = true
			const wasFloating = this.floatingEnabled
			this.stopFloat()
			this.restoreFloatBases()

			try {
				const posiciones = this.network.getPositions()
				const maxCount = items.reduce((max, item) => Math.max(max, item.count), 1)
				const scale = 1.35
				const margin = 140
				const ids = new Set(items.map(item => item.id))

				const nodos = items.map((item) => {
					const posicion = posiciones[item.id] || { x: 0, y: 0 }
					const diameter = this.nodeSize(item.count, maxCount) * 2 * 0.9
					return {
						...item,
						x: Number(posicion.x) * scale || 0,
						y: Number(posicion.y) * scale || 0,
						diameter: Math.max(48, Math.round(diameter)),
						palette: this.paletteFor(item.label),
					}
				})

				const minX = Math.min(...nodos.map(item => item.x - item.diameter / 2))
				const maxX = Math.max(...nodos.map(item => item.x + item.diameter / 2))
				const minY = Math.min(...nodos.map(item => item.y - item.diameter / 2))
				const maxY = Math.max(...nodos.map(item => item.y + item.diameter / 2 + 56))

				const offsetX = margin - minX
				const offsetY = margin - minY
				const pageWidth = Math.ceil(maxX - minX + margin * 2)
				const pageHeight = Math.ceil(maxY - minY + margin * 2)

				const edgesXml = this.showHierarchy
					? items.map((item, index) => {
						if (!item.parentId || !ids.has(item.parentId) || item.parentId === item.id) {
							return ''
						}
						return `
			<mxCell
				id="conexion-${index}-${this.escapeXml(item.parentId)}-${this.escapeXml(item.id)}"
				value=""
				style="edgeStyle=none;curved=1;rounded=0;html=1;endArrow=block;endFill=1;endSize=6;strokeColor=#94a3b8;strokeWidth=1;"
				edge="1"
				parent="1"
				source="entidad-${this.escapeXml(item.parentId)}"
				target="entidad-${this.escapeXml(item.id)}">
				<mxGeometry relative="1" as="geometry"/>
			</mxCell>`
					}).join('')
					: ''

				const nodosXml = nodos.map((item) => {
					const fill = this.hexColor(item.palette.base)
					const stroke = this.hexColor(item.palette.border)
					const x = item.x + offsetX - item.diameter / 2
					const y = item.y + offsetY - item.diameter / 2
					const labelHeight = 42
					const value = this.escapeXml(
						`${item.label}\n${t('empleados', '{count} employees', { count: item.count })}`,
					)

					return `
		<mxCell
			id="entidad-${this.escapeXml(item.id)}"
			value=""
			style="ellipse;whiteSpace=wrap;html=1;aspect=fixed;align=center;verticalAlign=middle;fillColor=${fill};fillOpacity=45;strokeColor=${stroke};strokeWidth=2;"
			vertex="1"
			parent="1">
			<mxGeometry
				x="${Math.round(x)}"
				y="${Math.round(y)}"
				width="${item.diameter}"
				height="${item.diameter}"
				as="geometry"/>
		</mxCell>
		<mxCell
			id="etiqueta-${this.escapeXml(item.id)}"
			value="${value}"
			style="text;html=1;align=center;verticalAlign=top;whiteSpace=wrap;rounded=0;fontSize=12;fontColor=#1f2937;fontStyle=1;"
			vertex="1"
			parent="1">
			<mxGeometry
				x="${Math.round(x - 20)}"
				y="${Math.round(y + item.diameter + 4)}"
				width="${item.diameter + 40}"
				height="${labelHeight}"
				as="geometry"/>
		</mxCell>`
				}).join('')

				const fecha = new Date().toISOString()
				const xml = `<?xml version="1.0" encoding="UTF-8"?>
<mxfile
	host="app.diagrams.net"
	modified="${fecha}"
	agent="Nextcloud Empleados"
	version="24.7.17"
	type="device">
	<diagram id="mapa-${this.escapeXml(this.entityType)}" name="${this.escapeXml(this.diagramName)}">
		<mxGraphModel
			dx="${pageWidth}"
			dy="${pageHeight}"
			grid="1"
			gridSize="10"
			guides="1"
			tooltips="1"
			connect="1"
			arrows="1"
			fold="1"
			page="1"
			pageScale="1"
			pageWidth="${pageWidth}"
			pageHeight="${pageHeight}"
			math="0"
			shadow="0">
			<root>
				<mxCell id="0"/>
				<mxCell id="1" parent="0"/>
				${edgesXml}
				${nodosXml}
			</root>
		</mxGraphModel>
	</diagram>
</mxfile>`

				this.descargarArchivo(
					xml,
					this.exportFileName,
					'application/vnd.jgraph.mxfile',
				)

				showSuccess(t('empleados', 'Map exported successfully'))
			} catch (err) {
				showError(t('empleados', 'Could not export the map'))
				console.error(err)
			} finally {
				this.exporting = false
				if (wasFloating) {
					this.startFloat()
				}
			}
		},
	},
}
</script>

<style scoped lang="scss">
.entity-network-wrapper {
	display: flex;
	flex-direction: column;
	width: 100%;
	height: 100%;
	min-height: 0;
	gap: 14px;
}

.entity-network-header {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	padding: 18px 20px;
	border: 1px solid var(--color-border);
	border-radius: 14px;
	background:
		radial-gradient(circle at top right, rgba(59, 130, 246, 0.10), transparent 42%),
		linear-gradient(180deg, var(--color-main-background), var(--color-background-hover, rgba(15, 23, 42, 0.03)));
	box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}

.entity-network-eyebrow {
	margin: 0 0 4px;
	color: var(--color-primary-element, #0082c9);
	font-size: 12px;
	font-weight: 700;
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.entity-network-title {
	margin: 0;
	color: var(--color-main-text);
	font-size: 1.45rem;
	font-weight: 800;
	line-height: 1.2;
}

.entity-network-hint {
	max-width: 56ch;
	margin: 8px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	line-height: 1.45;
}

.entity-network-stats {
	display: grid;
	grid-template-columns: repeat(3, minmax(110px, 1fr));
	gap: 10px;
	min-width: min(100%, 420px);
}

.entity-stat {
	display: flex;
	flex-direction: column;
	gap: 4px;
	min-height: 78px;
	padding: 12px 14px;
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-main-background);
	box-shadow: 0 1px 0 rgba(15, 23, 42, 0.04);
}

.entity-stat span {
	color: var(--color-text-maxcontrast);
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
}

.entity-stat strong {
	color: var(--color-main-text);
	font-size: 1.2rem;
	font-weight: 800;
	line-height: 1.15;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.entity-stat small {
	color: var(--color-text-maxcontrast);
	font-size: 11px;
}

.entity-network-content {
	position: relative;
	flex: 1 1 auto;
	min-height: 0;
}

.entity-network-canvas {
	position: absolute;
	inset: 0;
	border: 1px solid var(--color-border);
	border-radius: 14px;
	background:
		radial-gradient(circle at 20% 20%, rgba(59, 130, 246, 0.06), transparent 28%),
		radial-gradient(circle at 80% 70%, rgba(16, 185, 129, 0.05), transparent 26%),
		var(--color-main-background, #fff);
	overflow: hidden;
	box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.5);
}

.entity-network-fab {
	position: absolute;
	left: 50%;
	bottom: 18px;
	z-index: 12;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 10px;
	transform: translateX(-50%);
}

.entity-network-menu {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 240px;
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: 16px;
	background: var(--color-main-background);
	box-shadow: 0 14px 36px rgba(15, 23, 42, 0.18);
}

.menu-section {
	margin: 4px 8px 2px;
	color: var(--color-text-maxcontrast);
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.menu-divider {
	height: 1px;
	margin: 6px 4px;
	background: var(--color-border);
}

.menu-item {
	display: flex;
	align-items: center;
	gap: 10px;
	width: 100%;
	border: 0;
	border-radius: 10px;
	background: transparent;
	color: var(--color-main-text);
	cursor: pointer;
	font-size: 13px;
	font-weight: 600;
	padding: 10px 12px;
	text-align: left;
}

.menu-item:disabled {
	cursor: default;
	opacity: 0.5;
}

.menu-item:hover:not(:disabled) {
	background: var(--color-background-hover, rgba(15, 23, 42, 0.05));
}

.menu-item.active {
	background: var(--color-primary-element-light, rgba(0, 130, 201, 0.12));
	color: var(--color-primary-element, #0082c9);
}

.fab-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 52px;
	height: 52px;
	border: 1px solid var(--color-border);
	border-radius: 999px;
	background: var(--color-main-background);
	box-shadow: 0 10px 28px rgba(15, 23, 42, 0.18);
	color: var(--color-main-text);
	cursor: pointer;
	transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}

.fab-btn:hover {
	background: var(--color-primary-element, #0082c9);
	border-color: transparent;
	color: var(--color-primary-element-text, #fff);
	box-shadow: 0 12px 30px rgba(0, 130, 201, 0.28);
	transform: scale(1.04);
}

.entity-network-fab.is-open .fab-btn {
	background: var(--color-primary-element, #0082c9);
	border-color: transparent;
	color: var(--color-primary-element-text, #fff);
	box-shadow: 0 12px 30px rgba(0, 130, 201, 0.28);
	transform: scale(1.04);
}

@media (max-width: 900px) {
	.entity-network-stats {
		width: 100%;
		grid-template-columns: 1fr;
	}

	.entity-network-menu {
		min-width: min(240px, calc(100vw - 48px));
	}
}

@media (prefers-reduced-motion: reduce) {
	.fab-btn {
		transition: none;
	}
}
</style>
