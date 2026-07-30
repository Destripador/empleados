'use strict'

const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const source = fs.readFileSync(
	path.resolve(__dirname, '../../src/components/Ai/ContextAssistant.vue'),
	'utf8',
)
const renderer = fs.readFileSync(
	path.resolve(__dirname, '../../src/components/Ai/AiMessageContent.vue'),
	'utf8',
)

assert.match(source, /role="dialog"/)
assert.match(source, /aria-modal="false"/)
assert.match(source, /aria-live="polite"/)
assert.match(source, /message\.id/)
assert.match(source, /@keydown\.enter\.exact\.prevent="send"/)
assert.match(source, /@keydown\.esc="closePanel"/)
assert.match(source, /navigator\.clipboard\.writeText\(message\.text\)/)
assert.match(source, /error\?\.response\?\.status === 412/)
assert.match(source, /error\?\.response\?\.status === 403/)
assert.match(source, /capabilitiesPromise = null/)
assert.match(source, /suggestions\.slice\(0, 6\)/)
assert.match(source, /\{ scope: this\.scope, question, context, history \}/)
assert.match(source, /message\.successful === true/)
assert.match(source, /exchanges\.slice\(-12\)/)
assert.match(source, /\.slice\(0, 2000\)/)
assert.match(source, /replace\(\/<\\\/\?\[a-z\]\[\^>\]\*>\//)
assert.match(source, /contextKey\(\) \{\s*this\.resetConversation\(\)/)
assert.match(source, /scope\(\) \{\s*this\.resetConversation\(\)/)
assert.match(source, /const history = this\.getRequestHistory\(\)[\s\S]*this\.addMessage\('user'/)
assert.match(source, /userMessage\.successful = true/)
assert.match(source, /prefers-reduced-motion/)
assert.doesNotMatch(source, /localStorage|sessionStorage|indexedDB/i)
assert.doesNotMatch(renderer, /v-html/)
assert.match(source, /width: min\(480px, calc\(100vw - 32px\)\)/)
assert.match(source, /max-width: min\(82%, 360px\)/)
assert.match(source, /width: fit-content/)
assert.match(source, /opacity: \.72/)
assert.match(source, /\.ai-message::before/)

console.log('ContextAssistant UI isolated test: PASS')
