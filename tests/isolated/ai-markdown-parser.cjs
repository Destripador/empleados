'use strict'

const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')

const parserPath = path.resolve(__dirname, '../../src/components/Ai/aiMarkdownParser.js')
let source = fs.readFileSync(parserPath, 'utf8')
source = source
	.replace('export function parseInlineMarkdown', 'function parseInlineMarkdown')
	.replace('export function parseAiMarkdown', 'function parseAiMarkdown')
	.concat('\nmodule.exports = { parseAiMarkdown, parseInlineMarkdown }\n')
const sandbox = { module: { exports: {} }, exports: {} }
vm.runInNewContext(source, sandbox, { filename: parserPath })
const { parseAiMarkdown } = sandbox.module.exports

const sample = parseAiMarkdown(`### Información general

**Nombre:** Juan Pérez
* Área: Auditoría
* Puesto: Gerente`)
assert.equal(sample[0].type, 'heading')
assert.equal(sample[0].level, 3)
assert.equal(sample[1].lines[0][0].type, 'strong')
assert.equal(sample[2].type, 'unordered-list')
assert.equal(sample[2].items.length, 2)

assert.equal(parseAiMarkdown('*cursiva*')[0].lines[0][0].type, 'emphasis')
assert.equal(parseAiMarkdown('1. Uno\n2. Dos')[0].type, 'ordered-list')
assert.equal(parseAiMarkdown('Usa `código` aquí')[0].lines[0][1].type, 'code')
assert.equal(parseAiMarkdown('<script>alert(1)</script>')[0].lines[0][0].content, '<script>alert(1)</script>')
assert.equal(parseAiMarkdown('Texto normal')[0].type, 'paragraph')
assert.equal(parseAiMarkdown('\n\nTexto\n\n').length, 1)
assert.equal(parseAiMarkdown('```\nconst safe = true\n```')[0].type, 'code-block')

const long = parseAiMarkdown('a'.repeat(60000))
assert.equal(long[0].lines[0][0].content.length, 50000)

console.log('AI Markdown parser isolated test: PASS')
