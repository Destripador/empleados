const MAX_SOURCE_LENGTH = 50000
const MAX_BLOCKS = 500

export function parseInlineMarkdown(source) {
	const text = String(source ?? '')
	const nodes = []
	const pattern = /(`[^`\n]+`|\*\*[^*\n]+\*\*|\*[^*\n]+\*)/g
	let offset = 0
	let match

	while ((match = pattern.exec(text)) !== null) {
		if (match.index > offset) {
			nodes.push({ type: 'text', content: text.slice(offset, match.index) })
		}
		const token = match[0]
		if (token.startsWith('`')) {
			nodes.push({ type: 'code', content: token.slice(1, -1) })
		} else if (token.startsWith('**')) {
			nodes.push({ type: 'strong', content: token.slice(2, -2) })
		} else {
			nodes.push({ type: 'emphasis', content: token.slice(1, -1) })
		}
		offset = match.index + token.length
	}

	if (offset < text.length) {
		nodes.push({ type: 'text', content: text.slice(offset) })
	}
	return nodes.length > 0 ? nodes : [{ type: 'text', content: text }]
}

export function parseAiMarkdown(source) {
	const normalized = String(source ?? '')
		.slice(0, MAX_SOURCE_LENGTH)
		.replace(/\r\n?/g, '\n')
	const lines = normalized.split('\n')
	const blocks = []
	let index = 0

	while (index < lines.length && blocks.length < MAX_BLOCKS) {
		const line = lines[index]
		if (line.trim() === '') {
			index++
			continue
		}

		if (/^```/.test(line.trim())) {
			const code = []
			index++
			while (index < lines.length && !/^```/.test(lines[index].trim())) {
				code.push(lines[index++])
			}
			if (index < lines.length) index++
			blocks.push({ type: 'code-block', content: code.join('\n') })
			continue
		}

		const heading = line.match(/^(#{1,3})\s+(.+)$/)
		if (heading) {
			blocks.push({
				type: 'heading',
				level: heading[1].length,
				children: parseInlineMarkdown(heading[2].trim()),
			})
			index++
			continue
		}

		const unordered = line.match(/^\s*[-*]\s+(.+)$/)
		if (unordered) {
			const items = []
			while (index < lines.length) {
				const item = lines[index].match(/^\s*[-*]\s+(.+)$/)
				if (!item) break
				items.push(parseInlineMarkdown(item[1].trim()))
				index++
			}
			blocks.push({ type: 'unordered-list', items })
			continue
		}

		const ordered = line.match(/^\s*\d+[.)]\s+(.+)$/)
		if (ordered) {
			const items = []
			while (index < lines.length) {
				const item = lines[index].match(/^\s*\d+[.)]\s+(.+)$/)
				if (!item) break
				items.push(parseInlineMarkdown(item[1].trim()))
				index++
			}
			blocks.push({ type: 'ordered-list', items })
			continue
		}

		const paragraph = [line.trim()]
		index++
		while (
			index < lines.length
			&& lines[index].trim() !== ''
			&& !/^(#{1,3})\s+/.test(lines[index])
			&& !/^\s*[-*]\s+/.test(lines[index])
			&& !/^\s*\d+[.)]\s+/.test(lines[index])
			&& !/^```/.test(lines[index].trim())
		) {
			paragraph.push(lines[index].trim())
			index++
		}
		blocks.push({
			type: 'paragraph',
			lines: paragraph.map(parseInlineMarkdown),
		})
	}

	return blocks
}
