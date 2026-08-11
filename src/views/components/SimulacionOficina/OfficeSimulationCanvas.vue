<template>
	<div ref="wrap" class="sim-canvas-wrap">
		<canvas
			ref="canvas"
			class="sim-canvas"
			@mousemove="onMouseMove"
			@mouseleave="onMouseLeave"
			@click="onClick" />

		<EmployeeSimulationTooltip
			v-if="hoverEntity && !selectedId"
			:employee="hoverEntity"
			:x="tooltipX"
			:y="tooltipY"
			:avatar-ready="isAvatarReady(hoverEntity.id)"
			@avatar-error="markAvatarFailed" />

		<EmployeeSimulationDetails
			v-if="selectedEntity"
			:employee="selectedEntity"
			:avatar-ready="isAvatarReady(selectedEntity.id)"
			:following="followSelected"
			@close="closeDetails"
			@follow="$emit('follow', $event)"
			@avatar-error="markAvatarFailed" />
	</div>
</template>

<script>
import {
	buildOfficeLayout,
	createEntity,
	hitTest,
	initialsFromName,
	resetPositions,
	stepSimulation,
	colorForArea,
	statusPulseColor,
	applyStatusSnapshot,
	applyStatusBehavior,
} from '../../../utils/officeSimulationPhysics.js'
import { drawOfficeLayout, resolveEmployeeAreaKey } from '../../../utils/officeLayout.js'
import {
	celebrationGlowColor,
	primaryCelebration,
} from '../../../utils/dailyOfficeEvents.js'
import {
	collectSocialDebugStats,
	drawConversationConnections,
	drawSocialDebugOverlay,
	drawSocialIndicator,
} from '../../../utils/officeRendering.js'
import {
	INTERACTION_TYPES,
	isOfficeSimDebugEnabled,
} from '../../../utils/officeSimulationConfig.js'
import { startSocialInteraction } from '../../../utils/officeSocialEvents.js'
import { getSimulacionStatuses } from '../../../services/simulacionOficinaService.js'
import EmployeeSimulationTooltip from './EmployeeSimulationTooltip.vue'
import EmployeeSimulationDetails from './EmployeeSimulationDetails.vue'

const SPEED_MAP = {
	calm: 0.7,
	normal: 1.15,
	active: 1.65,
}

const STATUS_POLL_INTERVAL = 10000
const HIGHLIGHT_MS = 3200

export default {
	name: 'OfficeSimulationCanvas',

	components: {
		EmployeeSimulationTooltip,
		EmployeeSimulationDetails,
	},

	props: {
		employees: {
			type: Array,
			default: () => [],
		},
		paused: {
			type: Boolean,
			default: false,
		},
		speedMode: {
			type: String,
			default: 'normal',
		},
		showNames: {
			type: Boolean,
			default: false,
		},
		showFurniture: {
			type: Boolean,
			default: true,
		},
		selectedId: {
			type: Number,
			default: null,
		},
		followSelected: {
			type: Boolean,
			default: false,
		},
		resetToken: {
			type: Number,
			default: 0,
		},
		highlightUids: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['select', 'follow'],

	data() {
		return {
			hoverEntity: null,
			tooltipX: 0,
			tooltipY: 0,
			failedAvatars: {},
		}
	},

	computed: {
		selectedEntity() {
			if (this.selectedId == null || !this._entities) {
				return null
			}
			return this._entities.find((e) => e.id === this.selectedId) || null
		},
	},

	watch: {
		employees: {
			handler(list) {
				this.rebuildEntities(list || [])
				this.$nextTick(() => {
					this.resizeCanvas()
					if (this._entities.length) {
						const { width, height } = this.getSize()
						resetPositions(this._entities, this._areaCenters, width, height, this._layout)
						this.bootstrapStatusBehavior()
					}
					this.startStatusUpdates()
				})
			},
		},
		resetToken() {
			if (this._entities && this._areaCenters) {
				const { width, height } = this.getSize()
				resetPositions(this._entities, this._areaCenters, width, height, this._layout)
			}
		},
		highlightUids: {
			handler(uids) {
				this._highlightUntil = Array.isArray(uids) && uids.length
					? performance.now() + HIGHLIGHT_MS
					: 0
				this._highlightSet = new Set(Array.isArray(uids) ? uids : [])
			},
			immediate: true,
		},
	},

	beforeCreate() {
		this._entities = []
		this._nodesByUid = new Map()
		this._areaCenters = new Map()
		this._commonZones = []
		this._layout = null
		this._avatarImages = new Map()
		this._raf = 0
		this._lastTs = 0
		this._running = false
		this._resizeObserver = null
		this._statusPollTimer = null
		this._statusRequestInFlight = false
		this._statusAbort = null
		this._onVisibilityChange = null
		this._highlightSet = new Set()
		this._highlightUntil = 0
		this._camX = 0
		this._camY = 0
		this._layoutWidth = 0
		this._layoutHeight = 0
		this._debugEnabled = false
		this._debugTotals = { conversations: 0, conflicts: 0 }
		this._debugActiveKeys = new Set()
		this._debugApi = null
	},

	mounted() {
		this._debugEnabled = isOfficeSimDebugEnabled()
		this.rebuildEntities(this.employees || [])
		this.resizeCanvas()
		this.$nextTick(() => {
			this.resizeCanvas()
			if (this._entities.length) {
				const { width, height } = this.getSize()
				resetPositions(this._entities, this._areaCenters, width, height, this._layout)
				this.bootstrapStatusBehavior()
			}
		})
		this.startLoop()
		this.startStatusUpdates()
		this.installDebugApi()
		window.addEventListener('resize', this.onWindowResize)

		if (typeof ResizeObserver !== 'undefined' && this.$refs.wrap) {
			this._resizeObserver = new ResizeObserver(() => this.resizeCanvas())
			this._resizeObserver.observe(this.$refs.wrap)
		}
	},

	beforeDestroy() {
		this.stopStatusUpdates()
		this.stopLoop()
		window.removeEventListener('resize', this.onWindowResize)
		if (this._resizeObserver) {
			this._resizeObserver.disconnect()
			this._resizeObserver = null
		}
		if (this._debugApi && typeof window !== 'undefined' && window.OfficeSimDebug === this._debugApi) {
			delete window.OfficeSimDebug
		}
	},

	methods: {
		installDebugApi() {
			if (!this._debugEnabled || typeof window === 'undefined') return
			const force = (type, uidA, uidB) => {
				const a = this._nodesByUid.get(String(uidA))
				const b = this._nodesByUid.get(String(uidB))
				if (!a || !b || a === b) {
					return { ok: false, error: 'Provide two different existing employee UIDs.' }
				}
				for (const entity of [a, b]) {
					const previousPartner = this._entities.find((candidate) => candidate.id === entity.interactionPartnerId)
					if (previousPartner && previousPartner !== a && previousPartner !== b) {
						previousPartner.currentInteraction = null
						previousPartner.interactionTimer = 0
						previousPartner.interactionPartnerId = null
						previousPartner.interactionPartnerUid = null
						previousPartner.visualReactionTimer = 0
						previousPartner.visualReactionType = null
					}
				}
				const duration = type === INTERACTION_TYPES.CONVERSATION ? 5 : 2
				startSocialInteraction(a, b, type, {
					duration,
					reason: 'debug-forced',
					icon: type === INTERACTION_TYPES.CONFLICT ? '💢' : undefined,
				})
				return {
					ok: true,
					type,
					duration,
					a: a.uid,
					b: b.uid,
				}
			}
			this._debugApi = {
				debugForceConversation: (uidA, uidB) => force(INTERACTION_TYPES.CONVERSATION, uidA, uidB),
				debugForceConflict: (uidA, uidB) => force(INTERACTION_TYPES.CONFLICT, uidA, uidB),
				snapshot: () => ({
					enabled: true,
					...collectSocialDebugStats(this._entities),
					totals: { ...this._debugTotals },
					uids: this._entities.map((entity) => entity.uid),
				}),
			}
			window.OfficeSimDebug = this._debugApi
		},

		onWindowResize() {
			this.resizeCanvas()
		},

		getSize() {
			const wrap = this.$refs.wrap
			if (!wrap) {
				return { width: 800, height: 500 }
			}
			const width = wrap.clientWidth || wrap.offsetWidth || 800
			const height = wrap.clientHeight || wrap.offsetHeight || 480
			return {
				width: Math.max(320, width),
				height: Math.max(320, height),
			}
		},

		resizeCanvas() {
			const canvas = this.$refs.canvas
			if (!canvas) {
				return
			}
			const { width, height } = this.getSize()
			const dpr = Math.min(window.devicePixelRatio || 1, 2)
			canvas.width = Math.floor(width * dpr)
			canvas.height = Math.floor(height * dpr)
			canvas.style.width = `${width}px`
			canvas.style.height = `${height}px`
			const ctx = canvas.getContext('2d')
			ctx.setTransform(dpr, 0, 0, dpr, 0, 0)

			const dimensionsChanged = width !== this._layoutWidth || height !== this._layoutHeight
			if (!this._layout || dimensionsChanged) {
				this.applyLayout(this.employees || [], width, height, this._entities.length > 0)
			}
		},

		applyLayout(list, width, height, preserveEntities = false) {
			const previous = this._layout
			const next = buildOfficeLayout(list, width, height)
			if (preserveEntities && previous && this._entities.length) {
				this.remapEntities(previous, next)
			}
			this._layout = next
			this._areaCenters = this._layout.areaCenters
			this._commonZones = this._layout.commonZones
			this._layoutWidth = width
			this._layoutHeight = height
		},

		remapEntities(previous, next) {
			const roomMap = new Map((next.rooms || []).map((room) => [String(room.id), room]))
			const findRoom = (layout, point) => (layout.rooms || []).find((room) => point.x >= room.x
				&& point.x <= room.x + room.w
				&& point.y >= room.y
				&& point.y <= room.y + room.h)
			const remapPoint = (point) => {
				const oldRoom = findRoom(previous, point)
				const newRoom = oldRoom ? roomMap.get(String(oldRoom.id)) : null
				if (oldRoom && newRoom) {
					const rx = (point.x - oldRoom.x) / Math.max(1, oldRoom.w)
					const ry = (point.y - oldRoom.y) / Math.max(1, oldRoom.h)
					return {
						x: newRoom.x + Math.max(0, Math.min(1, rx)) * newRoom.w,
						y: newRoom.y + Math.max(0, Math.min(1, ry)) * newRoom.h,
					}
				}
				return {
					x: point.x * next.width / Math.max(1, previous.width),
					y: point.y * next.height / Math.max(1, previous.height),
				}
			}

			for (const entity of this._entities) {
				const position = remapPoint(entity)
				const target = remapPoint({ x: entity.targetX, y: entity.targetY })
				entity.x = Math.max(entity.radius + 4, Math.min(next.width - entity.radius - 4, position.x))
				entity.y = Math.max(entity.radius + 4, Math.min(next.height - entity.radius - 4, position.y))
				entity.targetX = target.x
				entity.targetY = target.y
				entity.waypoints = (entity.waypoints || []).map(remapPoint)

				if (entity.spotId) {
					for (const spots of next.spotsByZone.values()) {
						const spot = spots.find((candidate) => candidate.id === entity.spotId)
						if (spot && spot.occupiedBy == null) {
							spot.occupiedBy = entity.id
							if (!entity.waypoints.length) {
								entity.targetX = spot.x
								entity.targetY = spot.y
							}
							break
						}
					}
				}
			}
		},

		rebuildEntities(list) {
			const { width, height } = this.getSize()
			this.applyLayout(list, width, height)
			this._avatarImages = new Map()
			this._nodesByUid = new Map()
			this._entities = list.map((emp) => {
				const center = this._areaCenters.get(resolveEmployeeAreaKey(emp))
					|| { x: width / 2, y: height / 2 }
				const entity = createEntity(emp, center)
				this.preloadAvatar(entity)
				if (entity.uid) {
					this._nodesByUid.set(entity.uid, entity)
				}
				return entity
			})
			this.hoverEntity = null
		},

		bootstrapStatusBehavior() {
			for (const entity of this._entities) {
				applyStatusBehavior(entity, this._areaCenters, this._commonZones, {
					forceRetarget: entity.behavior !== 'active' && entity.behavior !== 'idle',
					layout: this._layout,
				})
			}
		},

		startStatusUpdates() {
			this.stopStatusUpdates()
			if (!this._entities.length) {
				return
			}

			this._onVisibilityChange = () => {
				if (typeof document !== 'undefined' && document.hidden) {
					this.pauseStatusPolling()
					return
				}
				this.fetchStatuses()
				this.resumeStatusPolling()
			}

			if (typeof document !== 'undefined') {
				document.addEventListener('visibilitychange', this._onVisibilityChange)
			}

			if (typeof document === 'undefined' || !document.hidden) {
				this.fetchStatuses()
				this.resumeStatusPolling()
			}
		},

		stopStatusUpdates() {
			this.pauseStatusPolling()
			if (this._statusAbort) {
				this._statusAbort.abort()
				this._statusAbort = null
			}
			this._statusRequestInFlight = false
			if (this._onVisibilityChange && typeof document !== 'undefined') {
				document.removeEventListener('visibilitychange', this._onVisibilityChange)
			}
			this._onVisibilityChange = null
		},

		pauseStatusPolling() {
			if (this._statusPollTimer) {
				clearInterval(this._statusPollTimer)
				this._statusPollTimer = null
			}
		},

		resumeStatusPolling() {
			this.pauseStatusPolling()
			this._statusPollTimer = setInterval(() => {
				this.fetchStatuses()
			}, STATUS_POLL_INTERVAL)
		},

		async fetchStatuses() {
			if (this._statusRequestInFlight) {
				return
			}
			if (typeof document !== 'undefined' && document.hidden) {
				return
			}
			if (!this._entities.length) {
				return
			}

			this._statusRequestInFlight = true
			if (this._statusAbort) {
				this._statusAbort.abort()
			}
			this._statusAbort = typeof AbortController !== 'undefined'
				? new AbortController()
				: null

			try {
				const statuses = await getSimulacionStatuses(
					this._statusAbort
						? { signal: this._statusAbort.signal }
						: {},
				)
				this.applyStatusSnapshot(statuses)
			} catch (err) {
				if (err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError' || err?.name === 'AbortError') {
					return
				}
				// Silencioso: conservar último estado conocido.
			} finally {
				this._statusRequestInFlight = false
			}
		},

		applyStatusSnapshot(statuses) {
			if (!Array.isArray(statuses) || !statuses.length) {
				return
			}
			const changed = applyStatusSnapshot(
				this._nodesByUid,
				statuses,
				this._areaCenters,
				this._commonZones,
				this._layout,
			)
			if (changed.length && this.hoverEntity) {
				// Forzar refresco visual del tooltip (misma referencia mutada).
				this.hoverEntity = this._nodesByUid.get(this.hoverEntity.uid) || this.hoverEntity
			}
		},

		preloadAvatar(entity) {
			if (!entity.avatarUrl || this.failedAvatars[entity.id]) {
				return
			}
			if (this._avatarImages.has(entity.id)) {
				return
			}
			const img = new Image()
			img.decoding = 'async'
			img.onload = () => {
				this._avatarImages.set(entity.id, img)
			}
			img.onerror = () => {
				this.markAvatarFailed(entity.id)
			}
			img.src = entity.avatarUrl
		},

		isAvatarReady(id) {
			return this._avatarImages.has(id) && !this.failedAvatars[id]
		},

		markAvatarFailed(id) {
			this.$set(this.failedAvatars, id, true)
			this._avatarImages.delete(id)
		},

		startLoop() {
			if (this._running) {
				return
			}
			this._running = true
			this._lastTs = 0
			const frame = (ts) => {
				if (!this._running) {
					return
				}
				const last = this._lastTs || ts
				const dt = Math.min(0.05, (ts - last) / 1000)
				this._lastTs = ts

				if (!this.paused && this._entities.length) {
					const { width, height } = this.getSize()
					stepSimulation({
						entities: this._entities,
						areaCenters: this._areaCenters,
						commonZones: this._commonZones,
						layout: this._layout,
						width,
						height,
						dt,
						speedMultiplier: SPEED_MAP[this.speedMode] || 1.15,
					})
				}

				this.updateCamera()
				this.draw()
				this._raf = requestAnimationFrame(frame)
			}
			this._raf = requestAnimationFrame(frame)
		},

		stopLoop() {
			this._running = false
			if (this._raf) {
				cancelAnimationFrame(this._raf)
				this._raf = 0
			}
		},

		updateCamera() {
			if (!this.followSelected || !this.selectedEntity) {
				this._camX *= 0.85
				this._camY *= 0.85
				if (Math.abs(this._camX) < 0.3) this._camX = 0
				if (Math.abs(this._camY) < 0.3) this._camY = 0
				return
			}
			const { width, height } = this.getSize()
			const targetX = this.selectedEntity.x - width / 2
			const targetY = this.selectedEntity.y - height / 2
			this._camX += (targetX - this._camX) * 0.08
			this._camY += (targetY - this._camY) * 0.08
		},

		hexAlpha(hex, alpha) {
			const raw = String(hex || '#4A90A4').replace('#', '')
			const full = raw.length === 3
				? raw.split('').map((c) => c + c).join('')
				: raw.slice(0, 6)
			const r = parseInt(full.slice(0, 2), 16)
			const g = parseInt(full.slice(2, 4), 16)
			const b = parseInt(full.slice(4, 6), 16)
			return `rgba(${r}, ${g}, ${b}, ${alpha})`
		},

		draw() {
			const canvas = this.$refs.canvas
			if (!canvas) {
				return
			}
			const ctx = canvas.getContext('2d')
			const { width, height } = this.getSize()
			const dpr = Math.min(window.devicePixelRatio || 1, 2)

			ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
			ctx.clearRect(0, 0, width, height)

			ctx.save()
			ctx.translate(-this._camX, -this._camY)

			drawOfficeLayout(ctx, this._layout, { showFurniture: this.showFurniture })

			const selectedId = this.selectedId
			const now = performance.now()
			const highlightActive = this._highlightUntil > now
			for (const entity of this._entities) {
				const isSelected = selectedId != null && entity.id === selectedId
				const isHighlighted = highlightActive && this._highlightSet.has(entity.uid)
				const dimmed = (selectedId != null && !isSelected && !this.followSelected)
					|| (highlightActive && !isHighlighted && selectedId == null)
				const color = colorForArea(entity.areaId)
				const alpha = dimmed ? 0.22 : 1
				const celebration = primaryCelebration(entity.specialEvents)

				ctx.save()
				ctx.globalAlpha = alpha

				// Sombra
				ctx.beginPath()
				ctx.ellipse(entity.x, entity.y + entity.radius * 0.72, entity.radius * 0.72, entity.radius * 0.28, 0, 0, Math.PI * 2)
				ctx.fillStyle = 'rgba(30,40,50,0.18)'
				ctx.fill()

				if (celebration && !dimmed) {
					const pulse = 0.5 + 0.5 * (0.5 + 0.5 * Math.sin(now / 520 + entity.id))
					ctx.beginPath()
					ctx.arc(entity.x, entity.y, entity.radius + 6 + pulse * 3, 0, Math.PI * 2)
					ctx.strokeStyle = celebrationGlowColor(celebration.type)
					ctx.globalAlpha = alpha * (0.3 + pulse * 0.4)
					ctx.lineWidth = 2.5
					ctx.stroke()
					ctx.globalAlpha = alpha
				}

				const pulseColor = statusPulseColor(entity.userStatus)
				if (pulseColor && !dimmed && !celebration) {
					const pulse = 0.45 + 0.55 * (0.5 + 0.5 * Math.sin(now / 420 + entity.id))
					ctx.beginPath()
					ctx.arc(entity.x, entity.y, entity.radius + 4 + pulse * 2.5, 0, Math.PI * 2)
					ctx.strokeStyle = pulseColor
					ctx.globalAlpha = alpha * (0.25 + pulse * 0.45)
					ctx.lineWidth = 2
					ctx.stroke()
					ctx.globalAlpha = alpha
				}

				if (isHighlighted) {
					ctx.beginPath()
					ctx.arc(entity.x, entity.y, entity.radius + 9, 0, Math.PI * 2)
					ctx.strokeStyle = 'rgba(255, 214, 90, 0.85)'
					ctx.lineWidth = 3
					ctx.stroke()
				}

				if (isSelected) {
					ctx.beginPath()
					ctx.arc(entity.x, entity.y, entity.radius + 6, 0, Math.PI * 2)
					ctx.strokeStyle = color
					ctx.lineWidth = 2.5
					ctx.stroke()
				}

				// Indicador de orientación ligero
				if (!dimmed && Math.hypot(entity.vx, entity.vy) > 0.35) {
					const ang = entity.facingAngle || Math.atan2(entity.vy, entity.vx)
					ctx.beginPath()
					ctx.moveTo(
						entity.x + Math.cos(ang) * (entity.radius + 2),
						entity.y + Math.sin(ang) * (entity.radius + 2),
					)
					ctx.lineTo(
						entity.x + Math.cos(ang + 2.5) * (entity.radius - 4),
						entity.y + Math.sin(ang + 2.5) * (entity.radius - 4),
					)
					ctx.lineTo(
						entity.x + Math.cos(ang - 2.5) * (entity.radius - 4),
						entity.y + Math.sin(ang - 2.5) * (entity.radius - 4),
					)
					ctx.closePath()
					ctx.fillStyle = 'rgba(40,50,60,0.25)'
					ctx.fill()
				}

				const img = this._avatarImages.get(entity.id)
				if (img && !this.failedAvatars[entity.id]) {
					ctx.beginPath()
					ctx.arc(entity.x, entity.y, entity.radius, 0, Math.PI * 2)
					ctx.closePath()
					ctx.clip()
					ctx.drawImage(
						img,
						entity.x - entity.radius,
						entity.y - entity.radius,
						entity.radius * 2,
						entity.radius * 2,
					)
				} else {
					ctx.beginPath()
					ctx.arc(entity.x, entity.y, entity.radius, 0, Math.PI * 2)
					ctx.fillStyle = color
					ctx.fill()
					ctx.fillStyle = '#fff'
					ctx.font = `bold ${Math.max(10, entity.radius * 0.7)}px sans-serif`
					ctx.textAlign = 'center'
					ctx.textBaseline = 'middle'
					ctx.fillText(initialsFromName(entity.displayName), entity.x, entity.y + 0.5)
				}

				ctx.restore()

				if (celebration && !dimmed) {
					ctx.save()
					ctx.font = `${Math.max(15, Math.min(21, entity.radius))}px sans-serif`
					ctx.textAlign = 'center'
					ctx.textBaseline = 'middle'
					ctx.fillText(
						celebration.icon,
						entity.x,
						entity.y - entity.radius - 12,
					)
					ctx.restore()
				}

				if (entity.statusIcon && !dimmed) {
					ctx.save()
					ctx.font = `${Math.max(14, Math.min(19, entity.radius * 0.95))}px sans-serif`
					ctx.textAlign = 'center'
					ctx.textBaseline = 'middle'
					ctx.fillText(
						entity.statusIcon,
						entity.x + entity.radius + 6,
						entity.y - entity.radius + 1,
					)
					ctx.restore()
				}

				if (entity.statusReactionTimer > 0 && !dimmed) {
					const life = Math.min(1, entity.statusReactionTimer / 2.6)
					const bounce = Math.sin((1 - life) * Math.PI) * 10
					ctx.save()
					ctx.globalAlpha = 0.35 + life * 0.65
					ctx.font = `${Math.max(15, Math.min(22, entity.radius * 1.08))}px sans-serif`
					ctx.textAlign = 'center'
					ctx.textBaseline = 'bottom'
					ctx.fillText(
						entity.statusReactionIcon || celebration?.icon || '✨',
						entity.x,
						entity.y - entity.radius - 29 - bounce * 0.45,
					)
					ctx.restore()
				}

				if (this.showNames && !dimmed) {
					ctx.save()
					ctx.globalAlpha = alpha * 0.9
					ctx.fillStyle = '#333333'
					ctx.font = '11px sans-serif'
					ctx.textAlign = 'center'
					ctx.textBaseline = 'top'
					const label = entity.displayName.length > 18
						? `${entity.displayName.slice(0, 16)}…`
						: entity.displayName
					ctx.fillText(label, entity.x, entity.y + entity.radius + 4)
					ctx.restore()
				}
			}

			// Capa social final: nunca queda debajo del avatar, muebles o badges.
			const alphaFor = (entity) => {
				const isSelected = selectedId != null && entity.id === selectedId
				const isHighlighted = highlightActive && this._highlightSet.has(entity.uid)
				const dimmed = (selectedId != null && !isSelected && !this.followSelected)
					|| (highlightActive && !isHighlighted && selectedId == null)
				return dimmed ? 0.42 : 1
			}
			drawConversationConnections(ctx, this._entities, { alphaFor })
			for (const entity of this._entities) {
				drawSocialIndicator(ctx, entity, now, alphaFor(entity))
			}

			ctx.restore()

			if (this._debugEnabled) {
				const stats = collectSocialDebugStats(this._entities)
				for (const key of stats.activeKeys) {
					if (this._debugActiveKeys.has(key)) continue
					if (key.startsWith('conversation:')) this._debugTotals.conversations++
					else if (key.startsWith('conflict:')) this._debugTotals.conflicts++
				}
				this._debugActiveKeys = stats.activeKeys
				drawSocialDebugOverlay(ctx, stats, this._debugTotals)
			}
		},

		canvasPoint(event) {
			const canvas = this.$refs.canvas
			const rect = canvas.getBoundingClientRect()
			return {
				x: event.clientX - rect.left + this._camX,
				y: event.clientY - rect.top + this._camY,
			}
		},

		onMouseMove(event) {
			const { x, y } = this.canvasPoint(event)
			const hit = hitTest(this._entities, x, y)
			this.hoverEntity = hit
			this.tooltipX = event.clientX - this.$refs.canvas.getBoundingClientRect().left
			this.tooltipY = event.clientY - this.$refs.canvas.getBoundingClientRect().top
			if (this.$refs.canvas) {
				this.$refs.canvas.style.cursor = hit ? 'pointer' : 'default'
			}
		},

		onMouseLeave() {
			this.hoverEntity = null
		},

		onClick(event) {
			const { x, y } = this.canvasPoint(event)
			const hit = hitTest(this._entities, x, y)
			this.$emit('select', hit ? hit.id : null)
			if (!hit) {
				this.$emit('follow', false)
			}
		},

		closeDetails() {
			this.$emit('select', null)
			this.$emit('follow', false)
		},
	},
}
</script>

<style scoped lang="scss">
.sim-canvas-wrap {
	position: relative;
	width: 100%;
	height: 100%;
	min-height: 360px;
	overflow: hidden;
	border: 1px solid var(--color-border);
	border-radius: 14px;
	background: #e8eef2;
}

.sim-canvas {
	display: block;
	width: 100%;
	height: 100%;
}
</style>
