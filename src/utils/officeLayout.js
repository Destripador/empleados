/**
 * Layout visual de oficina: sizing por capacidad + packing balanceado.
 * Se calcula solo en carga/resize, nunca dentro del frame de simulación.
 */

import { translate as t } from '@nextcloud/l10n'
import { SIMULATION_CONFIG } from './officeSimulationConfig.js'

const AREA_COLORS = [
	'#4A90A4', '#5B8C5A', '#C4A35A', '#A15C6B',
	'#6B7B8C', '#7A6B8C', '#8C6B4A', '#4A8C7A',
]

export function colorForArea(key) {
	const str = String(key ?? 'none')
	let hash = 0
	for (let i = 0; i < str.length; i++) {
		hash = ((hash << 5) - hash) + str.charCodeAt(i)
		hash |= 0
	}
	return AREA_COLORS[Math.abs(hash) % AREA_COLORS.length]
}

/** Tamaños base; el packing puede escalarlos suavemente en viewports pequeños. */
export const FUNCTIONAL_ROOM_SIZES = Object.freeze({
	reception: { w: 126, h: 86 },
	coffee: { w: 154, h: 104 },
	meeting: { w: 184, h: 116 },
	lounge: { w: 158, h: 106 },
	printer: { w: 96, h: 78 },
})

const COMMON_ROOM_META = [
	{ id: 'reception', emoji: '🚪', label: t('empleados', 'Reception'), color: '#8C9AA8' },
	{ id: 'coffee', emoji: '☕', label: t('empleados', 'Coffee area'), color: '#C4A35A' },
	{ id: 'meeting', emoji: '📅', label: t('empleados', 'Meeting room'), color: '#6B7B8C' },
	{ id: 'lounge', emoji: '🛋️', label: t('empleados', 'Lounge / rest'), color: '#7A6B8C' },
	{ id: 'printer', emoji: '🖨️', label: t('empleados', 'Printer / hall'), color: '#4A8C7A' },
]

/** Límites de áreas de trabajo (px). */
export const WORK_AREA_BOUNDS = Object.freeze({
	minWidth: 104,
	minHeight: 84,
	maxWidth: 340,
	maxHeight: 238,
	fallbackMaxWidth: 380,
	fallbackMaxHeight: 260,
	gap: SIMULATION_CONFIG.layout.roomGap,
	padLabel: 18,
	employeeSpace: 41,
	baseArea: SIMULATION_CONFIG.layout.baseRoomArea,
})

function clamp(v, min, max) {
	return Math.max(min, Math.min(max, v))
}

/**
 * Tamaño intrínseco por headcount (sqrt), sin stretch.
 * @param {number} employeeCount Cantidad
 * @param {{isFallback?: boolean}} options Opciones
 * @return {{w: number, h: number, sizeFactor: number}}
 */
export function computeWorkAreaSize(employeeCount, options = {}) {
	const n = Math.max(1, Number(employeeCount) || 1)
	const sizeFactor = Math.sqrt(n)
	const b = WORK_AREA_BOUNDS
	const capacity = Math.max(2, Math.ceil(n * (1 + SIMULATION_CONFIG.layout.capacityPaddingRatio)))
	const furnitureCapacity = Math.min(n, 32)
	const movementPadding = Math.max(900, Math.sqrt(n) * 520)
	const desiredArea = b.baseArea
		+ capacity * SIMULATION_CONFIG.layout.employeeCellArea
		+ movementPadding
	// Aspecto ligeramente más ancho conforme crece el equipo.
	const aspect = n <= 2 ? 1.15 : (n <= 8 ? 1.3 : 1.45)
	let w = Math.sqrt(desiredArea * aspect)
	let h = w / aspect

	const maxW = options.isFallback ? b.fallbackMaxWidth : b.maxWidth
	const maxH = options.isFallback ? b.fallbackMaxHeight : b.maxHeight
	w = clamp(w, b.minWidth, maxW)
	h = clamp(h, b.minHeight, maxH)

	// Evitar aspect extremos tras clamp
	if (w / h > 2.1) w = h * 2.1
	if (h / w > 1.6) h = w * 1.6

	return {
		w: Math.round(w),
		h: Math.round(h),
		sizeFactor,
		capacity,
		furnitureCapacity,
		movementPadding: Math.round(movementPadding),
		desiredWidth: Math.round(w),
		desiredHeight: Math.round(h),
	}
}

function normalizedAreaName(value) {
	return String(value || '')
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.trim()
		.toLowerCase()
		.replace(/\s+/g, ' ')
}

/**
 * Clave estable para no enviar a fallback un área con nombre pero sin id utilizable.
 * @param {object} employee Empleado
 */
export function resolveEmployeeAreaKey(employee) {
	const rawId = employee?.area?.id ?? employee?.areaId
	if (rawId !== null && rawId !== undefined && rawId !== '') {
		return rawId
	}
	const name = normalizedAreaName(employee?.area?.nombre || employee?.areaName)
	if (name && !['sin area', 'general', 'no area'].includes(name)) {
		return `area-name:${name}`
	}
	return 'none'
}

/**
 * Shelf packing: coloca cajas en su tamaño intrínseco, sin estirar.
 * @param {Array<{id: *, w: number, h: number}>} boxes Cajas
 * @param {number} originX Origen X
 * @param {number} originY Origen Y
 * @param {number} maxWidth Ancho máximo del bin
 * @param {number} gap Separación
 */
export function shelfPack(boxes, originX, originY, maxWidth, gap = WORK_AREA_BOUNDS.gap) {
	const placed = []
	let x = originX
	let y = originY
	let rowH = 0
	const usableW = Math.max(80, maxWidth)

	for (const box of boxes) {
		const bw = box.w
		const bh = box.h
		if (x > originX && x + bw > originX + usableW) {
			x = originX
			y += rowH + gap
			rowH = 0
		}
		placed.push({
			...box,
			x,
			y,
			w: bw,
			h: bh,
		})
		x += bw + gap
		rowH = Math.max(rowH, bh)
	}

	return {
		items: placed,
		bounds: {
			x: originX,
			y: originY,
			w: usableW,
			h: (placed.length ? (Math.max(...placed.map((p) => p.y + p.h)) - originY) : 0),
		},
	}
}

function roundRectPath(ctx, x, y, w, h, r) {
	const rr = Math.min(r, w / 2, h / 2)
	ctx.beginPath()
	ctx.moveTo(x + rr, y)
	ctx.arcTo(x + w, y, x + w, y + h, rr)
	ctx.arcTo(x + w, y + h, x, y + h, rr)
	ctx.arcTo(x, y + h, x, y, rr)
	ctx.arcTo(x, y, x + w, y, rr)
	ctx.closePath()
}

function makeSpotsInRoom(room, pattern) {
	const spots = []
	const padX = Math.max(10, room.w * 0.1)
	const padY = Math.max(16, room.h * 0.14)
	const innerW = Math.max(20, room.w - padX * 2)
	const innerH = Math.max(20, room.h - padY * 2)
	const count = Math.max(1, room.employeeCount || 1)

	if (pattern === 'desks') {
		const deskCount = Math.min(Math.max(count, 1), 40)
		const cols = clamp(Math.ceil(Math.sqrt(deskCount * (room.w / Math.max(1, room.h)))), 1, 7)
		const rows = Math.max(1, Math.ceil(deskCount / cols))
		let i = 0
		for (let r = 0; r < rows; r++) {
			for (let c = 0; c < cols; c++) {
				if (i >= deskCount) break
				spots.push({
					id: `${room.id}-desk-${i}`,
					type: 'desk',
					x: room.x + padX + (c + 0.5) * (innerW / cols),
					y: room.y + padY + (r + 0.5) * (innerH / rows),
					occupiedBy: null,
				})
				i++
			}
		}
	} else if (pattern === 'meeting') {
		const cx = room.x + room.w / 2
		const cy = room.y + room.h / 2
		const seats = SIMULATION_CONFIG.spots.meeting
		const rx = Math.min(room.w, room.h) * 0.28
		const ry = Math.min(room.w, room.h) * 0.22
		for (let i = 0; i < seats; i++) {
			const a = (i / seats) * Math.PI * 2
			spots.push({
				id: `${room.id}-seat-${i}`,
				type: 'seat',
				x: cx + Math.cos(a) * rx,
				y: cy + Math.sin(a) * ry,
				occupiedBy: null,
			})
		}
	} else if (pattern === 'coffee') {
		[[0.22, 0.46], [0.39, 0.6], [0.61, 0.6], [0.78, 0.46], [0.36, 0.78], [0.64, 0.78]]
			.slice(0, SIMULATION_CONFIG.spots.coffee).forEach((p, i) => {
				spots.push({
					id: `${room.id}-spot-${i}`,
					type: i === 0 ? 'machine' : 'seat',
					x: room.x + room.w * p[0],
					y: room.y + room.h * p[1],
					occupiedBy: null,
				})
			})
	} else if (pattern === 'lounge') {
		[[0.24, 0.43], [0.5, 0.38], [0.76, 0.43], [0.36, 0.72], [0.64, 0.72]]
			.slice(0, SIMULATION_CONFIG.spots.lounge).forEach((p, i) => {
				spots.push({
					id: `${room.id}-sofa-${i}`,
					type: 'seat',
					x: room.x + room.w * p[0],
					y: room.y + room.h * p[1],
					occupiedBy: null,
				})
			})
	} else if (pattern === 'printer') {
		[[0.5, 0.4], [0.35, 0.7], [0.65, 0.7]].forEach((p, i) => {
			spots.push({
				id: `${room.id}-print-${i}`,
				type: i === 0 ? 'machine' : 'stand',
				x: room.x + room.w * p[0],
				y: room.y + room.h * p[1],
				occupiedBy: null,
			})
		})
	} else if (pattern === 'reception') {
		[[0.5, 0.55], [0.28, 0.72], [0.72, 0.72]].forEach((p, i) => {
			spots.push({
				id: `${room.id}-rec-${i}`,
				type: i === 0 ? 'desk' : 'stand',
				x: room.x + room.w * p[0],
				y: room.y + room.h * p[1],
				occupiedBy: null,
			})
		})
	} else {
		spots.push({
			id: `${room.id}-center`,
			type: 'stand',
			x: room.x + room.w / 2,
			y: room.y + room.h / 2,
			occupiedBy: null,
		})
	}

	return spots
}

/** Escritorio visual de tamaño fijo (no crece con la sala). */
const DESK_W = 28
const DESK_H = 14

function makeFurniture(room) {
	const items = []
	const cx = room.x + room.w / 2
	const cy = room.y + room.h / 2

	if (room.id === 'coffee') {
		items.push({ type: 'counter', x: room.x + 12, y: room.y + 18, w: room.w - 24, h: 12 })
		items.push({ type: 'machine', x: cx - 9, y: room.y + 22, w: 18, h: 16, emoji: '☕' })
		items.push({ type: 'table', x: cx - 18, y: cy + 6, w: 36, h: 22 })
		items.push({ type: 'plant', x: room.x + 8, y: room.y + room.h - 16, w: 8, h: 8 })
	} else if (room.id === 'meeting') {
		items.push({
			type: 'table',
			x: cx - Math.min(48, room.w * 0.36),
			y: cy - 14,
			w: Math.min(96, room.w * 0.72),
			h: 28,
		})
		items.push({ type: 'screen', x: room.x + room.w - 14, y: cy - 12, w: 6, h: 24 })
	} else if (room.id === 'lounge') {
		items.push({ type: 'sofa', x: room.x + 12, y: room.y + 22, w: 40, h: 18 })
		items.push({ type: 'sofa', x: room.x + room.w - 52, y: room.y + 22, w: 40, h: 18 })
		items.push({ type: 'table', x: cx - 14, y: cy + 4, w: 28, h: 18 })
		items.push({ type: 'plant', x: room.x + room.w - 16, y: room.y + 10, w: 8, h: 8 })
	} else if (room.id === 'printer') {
		items.push({ type: 'printer', x: cx - 12, y: room.y + 16, w: 24, h: 20, emoji: '🖨️' })
	} else if (room.id === 'reception') {
		items.push({ type: 'desk', x: cx - 26, y: cy - 4, w: 52, h: 14 })
		items.push({ type: 'plant', x: room.x + 8, y: room.y + 10, w: 8, h: 8 })
	} else if (room.kind === 'work') {
		const count = Math.max(1, room.employeeCount || 1)
		const deskCount = Math.min(count, 16)
		const cols = clamp(Math.ceil(Math.sqrt(deskCount)), 1, 4)
		const rows = Math.max(1, Math.ceil(deskCount / cols))
		const padX = 12
		const padY = 20
		const innerW = room.w - padX * 2
		const innerH = room.h - padY * 2
		let i = 0
		for (let r = 0; r < rows; r++) {
			for (let c = 0; c < cols; c++) {
				if (i >= deskCount) break
				const cellCx = room.x + padX + (c + 0.5) * (innerW / cols)
				const cellCy = room.y + padY + (r + 0.5) * (innerH / rows)
				items.push({
					type: 'desk',
					x: cellCx - DESK_W / 2,
					y: cellCy - DESK_H / 2,
					w: DESK_W,
					h: DESK_H,
				})
				i++
			}
		}
		if (room.w >= 90) {
			items.push({ type: 'plant', x: room.x + 6, y: room.y + 8, w: 7, h: 7 })
		}
	}

	return items
}

function isFallbackArea(id, name) {
	if (id === 'none' || id == null) return true
	const n = String(name || '').toLowerCase()
	return n === 'sin área' || n === 'sin area' || n === 'general' || n === 'no area'
}

/**
 * Distribuye cajas en filas de carga similar. Prueba distintas cantidades de
 * filas y conserva la que permite mayor escala sin desbordar el rectángulo.
 * @param {Array<object>} boxes Habitaciones por colocar
 * @param {{x:number,y:number,w:number,h:number}} region Rectángulo disponible
 * @param {number} gap Separación base
 */
export function balancedRowsPack(boxes, region, gap = WORK_AREA_BOUNDS.gap) {
	if (!boxes.length) return { items: [], rows: [], scale: 1 }
	const cfg = SIMULATION_CONFIG.layout
	let best = null
	const maxRows = Math.min(boxes.length, 7)

	for (let rowCount = 1; rowCount <= maxRows; rowCount++) {
		const rows = Array.from({ length: rowCount }, () => [])
		const loads = Array(rowCount).fill(0)
		const pinned = boxes.filter((box) => box.verticalPreference === 'bottom')
		const regular = boxes
			.filter((box) => box.verticalPreference !== 'bottom')
			.sort((a, b) => (b.w * b.h) - (a.w * a.h))

		for (const box of pinned) {
			rows[rowCount - 1].push(box)
			loads[rowCount - 1] += box.w
		}
		for (const box of regular) {
			let target = 0
			for (let i = 1; i < rowCount; i++) {
				if (loads[i] < loads[target]) target = i
			}
			rows[target].push(box)
			loads[target] += box.w + (rows[target].length > 1 ? gap : 0)
		}

		if (rows.some((row) => row.length === 0)) continue
		const widthScale = Math.min(...rows.map((row) => {
			const natural = row.reduce((sum, box) => sum + box.w, 0)
			return (region.w - gap * Math.max(0, row.length - 1)) / Math.max(1, natural)
		}))
		const heightNatural = rows.reduce(
			(sum, row) => sum + Math.max(...row.map((box) => box.h)),
			0,
		)
		const heightScale = (region.h - gap * Math.max(0, rowCount - 1)) / Math.max(1, heightNatural)
		const scale = Math.min(cfg.maxRoomScale, widthScale, heightScale)
		const shapePenalty = Math.abs(rowCount - Math.sqrt(boxes.length * region.h / Math.max(1, region.w))) * 0.025
		const score = scale - shapePenalty
		if (!best || score > best.score) best = { rows, scale, score }
	}

	// En viewports realmente pequeños prima no solapar; el min visual se conserva
	// siempre que el rectángulo disponible lo permite.
	const scale = Math.max(0.32, Math.min(cfg.maxRoomScale, best?.scale || 1))
	const rows = best?.rows || [boxes]
	const rowHeights = rows.map((row) => Math.max(...row.map((box) => box.h * scale)))
	const naturalHeight = rowHeights.reduce((sum, h) => sum + h, 0)
	const availableExtraY = Math.max(0, region.h - naturalHeight - gap * Math.max(0, rows.length - 1))
	const extraRowGap = rows.length > 1
		? Math.min(18, availableExtraY / (rows.length - 1))
		: 0
	const usedHeight = naturalHeight + (gap + extraRowGap) * Math.max(0, rows.length - 1)
	let y = region.y + Math.max(0, (region.h - usedHeight) / 2)
	const placed = []

	rows.forEach((row, rowIndex) => {
		const ordered = row.slice().sort((a, b) => {
			if (a.id === 'lounge') return -1
			if (b.id === 'lounge') return 1
			if (a.id === 'printer') return 1
			if (b.id === 'printer') return -1
			return (b.w * b.h) - (a.w * a.h)
		})
		const scaledWidth = ordered.reduce((sum, box) => sum + box.w * scale, 0)
		const free = Math.max(0, region.w - scaledWidth - gap * Math.max(0, ordered.length - 1))
		const extraGap = ordered.length > 1 ? Math.min(22, free / (ordered.length - 1)) : 0
		const usedWidth = scaledWidth + (gap + extraGap) * Math.max(0, ordered.length - 1)
		let x = region.x + Math.max(0, (region.w - usedWidth) / 2)
		for (const box of ordered) {
			const w = box.w * scale
			const h = box.h * scale
			placed.push({
				...box,
				x,
				y: y + (rowHeights[rowIndex] - h) / 2,
				w,
				h,
				rowIndex,
			})
			x += w + gap + extraGap
		}
		y += rowHeights[rowIndex] + gap + extraRowGap
	})

	return { items: placed, rows, scale }
}

/**
 * Construye el plano: servicios arriba y áreas/servicios secundarios en filas
 * balanceadas que abarcan el canvas sin convertir habitaciones en tarjetas gigantes.
 * @param {Array<object>} employees Empleados
 * @param {number} width Ancho del canvas
 * @param {number} height Alto del canvas
 */
export function buildOfficeLayout(employees, width, height) {
	const W = Math.max(320, width)
	const H = Math.max(320, height)
	const cfg = SIMULATION_CONFIG.layout
	const margin = Math.min(cfg.margin, Math.max(14, W * 0.035))
	const gap = Math.max(cfg.minRoomGap, Math.min(cfg.roomGap, W * 0.018))

	const areaStats = new Map()
	for (const emp of employees) {
		const id = resolveEmployeeAreaKey(emp)
		const name = emp.area?.nombre || emp.areaName || (id === 'none' ? t('empleados', 'No area') : t('empleados', 'Area {id}', { id }))
		if (!areaStats.has(id)) areaStats.set(id, { id, name, count: 0, activitySum: 0 })
		const row = areaStats.get(id)
		row.count += 1
		row.activitySum += typeof emp.activityScore === 'number' ? emp.activityScore : 0.4
	}

	const workAreas = Array.from(areaStats.values()).map((area) => {
		const fallback = isFallbackArea(area.id, area.name)
		return {
			...area,
			...computeWorkAreaSize(area.count, { isFallback: fallback }),
			kind: 'work',
			emoji: '',
			label: fallback ? (area.name || t('empleados', 'No area')) : area.name,
			color: colorForArea(area.id),
			isFallback: fallback,
			employeeCount: area.count,
		}
	})

	const topBandH = clamp(H * cfg.topBandRatio, cfg.topBandMin, cfg.topBandMax)
	const topDefs = [
		{ id: 'coffee', emoji: '☕', label: t('empleados', 'Coffee area'), color: '#C4A35A', ...FUNCTIONAL_ROOM_SIZES.coffee },
		{ id: 'reception', emoji: '🚪', label: t('empleados', 'Reception'), color: '#8C9AA8', ...FUNCTIONAL_ROOM_SIZES.reception },
		{ id: 'meeting', emoji: '📅', label: t('empleados', 'Meeting room'), color: '#6B7B8C', ...FUNCTIONAL_ROOM_SIZES.meeting },
	]
	const topAvailableW = W - margin * 2
	const topNaturalW = topDefs.reduce((sum, room) => sum + room.w, 0)
	const topScale = Math.min(1, (topAvailableW - gap * 2) / topNaturalW, (topBandH - 4) / 116)
	const topUsedW = topDefs.reduce((sum, room) => sum + room.w * topScale, 0)
	const topSpacing = Math.max(gap, (topAvailableW - topUsedW) / 2)
	let topX = margin
	const topRooms = topDefs.map((def) => {
		const room = {
			...def,
			kind: 'common',
			x: topX,
			y: margin + (topBandH - def.h * topScale) / 2,
			w: def.w * topScale,
			h: def.h * topScale,
			employeeCount: 0,
		}
		topX += room.w + topSpacing
		return room
	})

	const mainY = margin + topBandH + gap * 1.8
	const mainRegion = {
		x: margin,
		y: mainY,
		w: W - margin * 2,
		h: Math.max(100, H - mainY - margin),
	}
	const secondaryRooms = [
		{
			id: 'lounge',
			kind: 'common',
			emoji: '🛋️',
			label: t('empleados', 'Lounge / rest'),
			color: '#7A6B8C',
			...FUNCTIONAL_ROOM_SIZES.lounge,
			employeeCount: 0,
			verticalPreference: 'bottom',
		},
		{
			id: 'printer',
			kind: 'common',
			emoji: '🖨️',
			label: t('empleados', 'Printer / hall'),
			color: '#4A8C7A',
			...FUNCTIONAL_ROOM_SIZES.printer,
			employeeCount: 0,
			verticalPreference: 'bottom',
		},
	]
	const packed = balancedRowsPack([...workAreas, ...secondaryRooms], mainRegion, gap)
	const mainRooms = packed.items.map((room) => ({
		...room,
		capacity: room.capacity || 0,
		desiredWidth: room.desiredWidth || room.w,
		desiredHeight: room.desiredHeight || room.h,
	}))
	const workRooms = mainRooms.filter((room) => room.kind === 'work')
	const secondaryCommon = mainRooms.filter((room) => room.kind === 'common')
	const commonRooms = [...topRooms, ...secondaryCommon]
	const allRooms = [...commonRooms, ...workRooms]

	// Puertas y carriles quedan en los huecos del packing; no hay un gran pasillo rectangular.
	const topRouteY = mainY - gap * 0.7
	for (const room of topRooms) {
		room.door = { x: room.x + room.w / 2, y: room.y + room.h }
		room.routePoint = { x: room.door.x, y: topRouteY }
	}
	for (const room of mainRooms) {
		room.door = { x: room.x + room.w / 2, y: room.y }
		room.routePoint = { x: room.door.x, y: Math.max(topRouteY, room.y - gap * 0.55) }
	}
	const routeLanes = [
		{ id: 'lane-left', x: Math.max(18, margin * 0.62) },
		{ id: 'lane-right', x: Math.min(W - 18, W - margin * 0.62) },
	]
	const walkways = [
		{ x1: margin, y1: topRouteY, x2: W - margin, y2: topRouteY },
		...Array.from(new Set(mainRooms.map((room) => Math.round(room.routePoint.y))))
			.map((routeY) => ({ x1: margin, y1: routeY, x2: W - margin, y2: routeY })),
		...routeLanes.map((lane) => ({ x1: lane.x, y1: topRouteY, x2: lane.x, y2: H - margin })),
	]

	const furniture = []
	const spotsByZone = new Map()
	for (const room of allRooms) {
		let pattern = 'desks'
		if (room.id === 'meeting') pattern = 'meeting'
		else if (room.id === 'coffee') pattern = 'coffee'
		else if (room.id === 'lounge') pattern = 'lounge'
		else if (room.id === 'printer') pattern = 'printer'
		else if (room.id === 'reception') pattern = 'reception'
		else if (room.kind === 'work') pattern = 'desks'

		const spots = makeSpotsInRoom(room, pattern)
		room.spots = spots
		spotsByZone.set(room.id, spots)
		const items = makeFurniture(room)
		room.furniture = items
		furniture.push(...items.map((f) => ({ ...f, roomId: room.id, color: room.color })))
	}

	const areaCenters = new Map()
	for (const room of workRooms) {
		areaCenters.set(room.id, {
			x: room.x + room.w / 2,
			y: room.y + room.h / 2,
			color: room.color,
			employeeCount: room.employeeCount,
			radius: Math.min(room.w, room.h) * 0.4,
			room,
		})
	}

	const commonZones = COMMON_ROOM_META.map((def) => {
		const room = commonRooms.find((r) => r.id === def.id)
		if (!room) return null
		return {
			id: room.id,
			emoji: room.emoji,
			color: room.color,
			x: room.x + room.w / 2,
			y: room.y + room.h / 2,
			radius: Math.min(room.w, room.h) * 0.38,
			room,
		}
	}).filter(Boolean)

	return {
		width: W,
		height: H,
		corridor: null,
		hubs: allRooms.map((room) => ({ id: `hub-${room.id}`, ...room.routePoint })),
		routeLanes,
		walkways,
		workRooms,
		commonRooms,
		rooms: allRooms,
		furniture,
		spotsByZone,
		areaCenters,
		commonZones,
		packingScale: packed.scale,
	}
}

/**
 * Compat: peso legacy (ya no se usa para stretch).
 * @param {number} count Cantidad de empleados
 */
export function areaLayoutWeight(count) {
	return Math.sqrt(Math.max(1, count))
}

/**
 * Reserva un spot libre en la zona.
 * @param {object} layout Layout
 * @param {string|number} zoneId Zona
 * @param {number} entityId Id empleado
 * @param {{x:number,y:number}|null} preferNear Preferencia espacial
 */
export function claimSpot(layout, zoneId, entityId, preferNear = null) {
	if (!layout?.spotsByZone) return null
	const spots = layout.spotsByZone.get(zoneId) || layout.spotsByZone.get(String(zoneId))
	if (!spots || !spots.length) return null

	const free = spots.filter((s) => s.occupiedBy == null || s.occupiedBy === entityId)
	if (!free.length) {
		return { spot: null, full: true, occupiedCount: spots.length }
	}
	const pool = free
	let best = pool[0]
	let bestScore = Infinity

	for (const spot of pool) {
		let dist = 0
		if (preferNear) {
			dist = Math.hypot(spot.x - preferNear.x, spot.y - preferNear.y)
		}
		const score = dist + Math.random() * 8
		if (score < bestScore) {
			bestScore = score
			best = spot
		}
	}

	for (const s of spots) {
		if (s.occupiedBy === entityId) s.occupiedBy = null
	}
	best.occupiedBy = entityId
	return { spot: best, full: false, contested: false }
}

/**
 * Libera spots ocupados por el empleado.
 * @param {object} layout Layout
 * @param {object} entity Entidad
 */
export function releaseSpot(layout, entity) {
	if (!layout?.spotsByZone || !entity) return
	for (const spots of layout.spotsByZone.values()) {
		for (const s of spots) {
			if (s.occupiedBy === entity.id) s.occupiedBy = null
		}
	}
	entity.spotId = null
}

/**
 * Waypoints simples por los huecos entre filas y carriles laterales.
 * @param {object} layout Layout
 * @param {{x:number,y:number}} from Origen
 * @param {string|number} toZoneId Zona destino
 * @param {{x:number,y:number}} destination Destino final
 */
export function buildWaypointPath(layout, from, toZoneId, destination) {
	if (!layout) {
		return destination ? [destination] : []
	}

	const dest = destination || { x: from.x, y: from.y }
	const path = []
	const distDirect = Math.hypot(dest.x - from.x, dest.y - from.y)
	if (distDirect < 72) {
		path.push(dest)
		return path
	}

	const rooms = layout.rooms || []
	const contains = (room, point, padding = 3) => point.x >= room.x - padding
		&& point.x <= room.x + room.w + padding
		&& point.y >= room.y - padding
		&& point.y <= room.y + room.h + padding
	const sourceRoom = rooms.find((room) => contains(room, from)) || null
	const targetRoom = rooms.find((room) => room.id === toZoneId || String(room.id) === String(toZoneId))
		|| rooms.find((room) => contains(room, dest))
		|| null

	if (sourceRoom && targetRoom && sourceRoom.id === targetRoom.id) {
		return [dest]
	}

	const sourceRoute = sourceRoom?.routePoint || { x: from.x, y: from.y }
	const targetRoute = targetRoom?.routePoint || { x: dest.x, y: dest.y }
	if (sourceRoom?.door) path.push({ ...sourceRoom.door })
	if (sourceRoom?.routePoint) path.push({ ...sourceRoute })

	if (Math.abs(sourceRoute.y - targetRoute.y) < 12) {
		path.push({ x: targetRoute.x, y: targetRoute.y })
	} else {
		const lanes = layout.routeLanes || []
		let lane = lanes[0]
		if (lanes.length > 1) {
			const leftCost = Math.abs(sourceRoute.x - lanes[0].x) + Math.abs(targetRoute.x - lanes[0].x)
			const rightCost = Math.abs(sourceRoute.x - lanes[1].x) + Math.abs(targetRoute.x - lanes[1].x)
			lane = rightCost < leftCost ? lanes[1] : lanes[0]
		}
		if (lane) {
			path.push({ x: lane.x, y: sourceRoute.y })
			path.push({ x: lane.x, y: targetRoute.y })
		}
		path.push({ x: targetRoute.x, y: targetRoute.y })
	}
	if (targetRoom?.door) path.push({ ...targetRoom.door })
	path.push(dest)
	return path.filter((point, index) => index === 0
		|| Math.hypot(point.x - path[index - 1].x, point.y - path[index - 1].y) > 2)
}

export function hexAlpha(hex, alpha) {
	const full = String(hex || '#888888').replace('#', '')
	const r = parseInt(full.slice(0, 2), 16)
	const g = parseInt(full.slice(2, 4), 16)
	const b = parseInt(full.slice(4, 6), 16)
	return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

/**
 * Dibuja el plano: habitaciones, pasillo, muebles.
 * @param {CanvasRenderingContext2D} ctx Contexto
 * @param {object} layout Layout
 * @param {{showFurniture?: boolean}} options Opciones
 */
export function drawOfficeLayout(ctx, layout, { showFurniture = true } = {}) {
	if (!layout || !ctx) return

	const { width, height, corridor, walkways, rooms, furniture } = layout

	const floor = ctx.createLinearGradient(0, 0, width, height)
	floor.addColorStop(0, '#e8eef2')
	floor.addColorStop(1, '#dfe6ec')
	ctx.fillStyle = floor
	ctx.fillRect(0, 0, width, height)

	if (corridor) {
		ctx.fillStyle = 'rgba(255,255,255,0.55)'
		ctx.fillRect(corridor.x, corridor.y, corridor.w, corridor.h)
		ctx.strokeStyle = 'rgba(120,140,160,0.22)'
		ctx.lineWidth = 1
		ctx.strokeRect(corridor.x + 0.5, corridor.y + 0.5, corridor.w - 1, corridor.h - 1)
		ctx.strokeStyle = 'rgba(120,140,160,0.12)'
		ctx.setLineDash([5, 7])
		ctx.beginPath()
		ctx.moveTo(corridor.x + 8, corridor.y + corridor.h / 2)
		ctx.lineTo(corridor.x + corridor.w - 8, corridor.y + corridor.h / 2)
		ctx.stroke()
		ctx.setLineDash([])
	}

	if (walkways?.length) {
		ctx.save()
		ctx.strokeStyle = 'rgba(120,140,160,0.2)'
		ctx.lineWidth = 1
		ctx.setLineDash([4, 7])
		for (const segment of walkways) {
			ctx.beginPath()
			ctx.moveTo(segment.x1, segment.y1)
			ctx.lineTo(segment.x2, segment.y2)
			ctx.stroke()
		}
		ctx.restore()
	}

	for (const room of rooms || []) {
		const isWork = room.kind === 'work'
		roundRectPath(ctx, room.x, room.y, room.w, room.h, 10)
		ctx.fillStyle = hexAlpha(room.color, isWork ? 0.16 : 0.2)
		ctx.fill()
		ctx.strokeStyle = hexAlpha(room.color, 0.5)
		ctx.lineWidth = 1.4
		ctx.stroke()

		ctx.fillStyle = 'rgba(40,50,60,0.75)'
		ctx.font = isWork ? '600 10px sans-serif' : '600 11px sans-serif'
		ctx.textAlign = 'left'
		ctx.textBaseline = 'top'
		const title = room.emoji ? `${room.emoji} ${room.label}` : room.label
		const clipped = title.length > 18 ? `${title.slice(0, 16)}…` : title
		ctx.fillText(clipped, room.x + 6, room.y + 5)
		if (isWork && room.employeeCount) {
			ctx.font = '9px sans-serif'
			ctx.fillStyle = 'rgba(40,50,60,0.45)'
			ctx.fillText(`${room.employeeCount}`, room.x + room.w - 14, room.y + 5)
		}
	}

	if (!showFurniture) return

	for (const item of furniture || []) {
		const color = item.color || '#8a95a3'
		if (item.type === 'desk' || item.type === 'counter' || item.type === 'table' || item.type === 'screen') {
			roundRectPath(ctx, item.x, item.y, item.w, item.h, 3)
			ctx.fillStyle = hexAlpha(color, 0.38)
			ctx.fill()
			ctx.strokeStyle = hexAlpha(color, 0.55)
			ctx.lineWidth = 1
			ctx.stroke()
		} else if (item.type === 'sofa') {
			roundRectPath(ctx, item.x, item.y, item.w, item.h, 5)
			ctx.fillStyle = hexAlpha(color, 0.42)
			ctx.fill()
		} else if (item.type === 'plant') {
			ctx.beginPath()
			ctx.arc(item.x + item.w / 2, item.y + item.h / 2, item.w / 2, 0, Math.PI * 2)
			ctx.fillStyle = 'rgba(90, 140, 100, 0.45)'
			ctx.fill()
		} else if (item.type === 'machine' || item.type === 'printer') {
			roundRectPath(ctx, item.x, item.y, item.w, item.h, 3)
			ctx.fillStyle = 'rgba(70,80,90,0.28)'
			ctx.fill()
			if (item.emoji) {
				ctx.font = '13px sans-serif'
				ctx.textAlign = 'center'
				ctx.textBaseline = 'middle'
				ctx.fillText(item.emoji, item.x + item.w / 2, item.y + item.h / 2)
			}
		}
	}
}
