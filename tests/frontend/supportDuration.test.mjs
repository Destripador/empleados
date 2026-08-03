import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const source = await readFile(new URL('../../src/utils/supportDuration.js', import.meta.url), 'utf8')
const moduleUrl = `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
const { formatSupportDuration, isValidSupportDate, normalizeSupportDuration, splitSupportDuration } = await import(moduleUrl)

assert.equal(normalizeSupportDuration(1, 30), 90)
assert.equal(normalizeSupportDuration(1, 15), 75)
assert.equal(normalizeSupportDuration(0, 0), null)
assert.equal(normalizeSupportDuration(0, 59), 59)
assert.equal(normalizeSupportDuration(0, 60), null)
assert.equal(normalizeSupportDuration(0, -1), null)
assert.equal(normalizeSupportDuration(24, 1), null)
assert.deepEqual(splitSupportDuration(75), { hours: 1, minutes: 15 })
assert.equal(formatSupportDuration(75), '1 h 15 min')
assert.equal(isValidSupportDate('2026-08-01T10:30'), true)
assert.equal(isValidSupportDate(''), false)
