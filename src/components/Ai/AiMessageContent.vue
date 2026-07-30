<template>
	<div class="ai-message-content">
		<template v-for="(block, blockIndex) in blocks">
			<component
				:is="`h${block.level}`"
				v-if="block.type === 'heading'"
				:key="`heading-${blockIndex}`">
				<template v-for="(node, nodeIndex) in block.children">
					<code v-if="node.type === 'code'" :key="nodeIndex">{{ node.content }}</code>
					<strong v-else-if="node.type === 'strong'" :key="nodeIndex">{{ node.content }}</strong>
					<em v-else-if="node.type === 'emphasis'" :key="nodeIndex">{{ node.content }}</em>
					<template v-else>
						{{ node.content }}
					</template>
				</template>
			</component>

			<p v-else-if="block.type === 'paragraph'" :key="`paragraph-${blockIndex}`">
				<template v-for="(line, lineIndex) in block.lines">
					<br v-if="lineIndex > 0" :key="`break-${lineIndex}`">
					<template v-for="(node, nodeIndex) in line">
						<code v-if="node.type === 'code'" :key="`${lineIndex}-${nodeIndex}`">{{ node.content }}</code>
						<strong v-else-if="node.type === 'strong'" :key="`${lineIndex}-${nodeIndex}`">{{ node.content }}</strong>
						<em v-else-if="node.type === 'emphasis'" :key="`${lineIndex}-${nodeIndex}`">{{ node.content }}</em>
						<template v-else>
							{{ node.content }}
						</template>
					</template>
				</template>
			</p>

			<ul v-else-if="block.type === 'unordered-list'" :key="`ul-${blockIndex}`">
				<li v-for="(item, itemIndex) in block.items" :key="itemIndex">
					<template v-for="(node, nodeIndex) in item">
						<code v-if="node.type === 'code'" :key="nodeIndex">{{ node.content }}</code>
						<strong v-else-if="node.type === 'strong'" :key="nodeIndex">{{ node.content }}</strong>
						<em v-else-if="node.type === 'emphasis'" :key="nodeIndex">{{ node.content }}</em>
						<template v-else>
							{{ node.content }}
						</template>
					</template>
				</li>
			</ul>

			<ol v-else-if="block.type === 'ordered-list'" :key="`ol-${blockIndex}`">
				<li v-for="(item, itemIndex) in block.items" :key="itemIndex">
					<template v-for="(node, nodeIndex) in item">
						<code v-if="node.type === 'code'" :key="nodeIndex">{{ node.content }}</code>
						<strong v-else-if="node.type === 'strong'" :key="nodeIndex">{{ node.content }}</strong>
						<em v-else-if="node.type === 'emphasis'" :key="nodeIndex">{{ node.content }}</em>
						<template v-else>
							{{ node.content }}
						</template>
					</template>
				</li>
			</ol>

			<pre v-else-if="block.type === 'code-block'" :key="`code-${blockIndex}`"><code>{{ block.content }}</code></pre>
		</template>
	</div>
</template>

<script>
import { parseAiMarkdown } from './aiMarkdownParser.js'

export default {
	name: 'AiMessageContent',
	props: {
		content: {
			type: String,
			required: true,
		},
	},
	computed: {
		blocks() {
			return parseAiMarkdown(this.content)
		},
	},
}
</script>

<style scoped>
.ai-message-content {
	line-height: 1.55;
	overflow-wrap: anywhere;
	user-select: text;
}

.ai-message-content :is(h1, h2, h3) {
	margin: 0 0 8px;
	font-size: 1em;
	line-height: 1.35;
}

.ai-message-content :is(p, ul, ol, pre) {
	margin: 0 0 10px;
}

.ai-message-content :is(p, ul, ol, pre):last-child {
	margin-bottom: 0;
}

.ai-message-content :is(ul, ol) {
	padding-inline-start: 22px;
}

.ai-message-content code {
	padding: 1px 4px;
	border-radius: var(--border-radius);
	background: var(--color-background-dark);
	font-family: monospace;
}

.ai-message-content pre {
	overflow-x: auto;
	padding: 10px;
	border-radius: var(--border-radius);
	background: var(--color-background-dark);
	white-space: pre-wrap;
}

.ai-message-content pre code {
	padding: 0;
	background: transparent;
}
</style>
