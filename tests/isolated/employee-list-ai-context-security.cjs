'use strict'

const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const source = fs.readFileSync(
	path.resolve(__dirname, '../../src/views/components/ListaEmpleados/EmployeeList.vue'),
	'utf8',
)

assert.match(source, /scope="empleados-completo"/)
assert.match(source, /:context="\{\}"/)
assert.match(source, /context-key="empleados-completo"/)
assert.doesNotMatch(source, /aiEmployee\s*\(/)
assert.doesNotMatch(source, /aiEmployees\s*\(/)
assert.doesNotMatch(source, /aiContext\s*\(/)
assert.doesNotMatch(source, /aiCalculateSeniority\s*\(/)
assert.doesNotMatch(source, /localStorage|sessionStorage|indexedDB/i)

console.log('EmployeeList server AI context security test: PASS')
