'use strict'

const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const source = fs.readFileSync(
	path.resolve(__dirname, '../../src/views/components/reports/admin/Adminreports.vue'),
	'utf8',
)

assert.match(source, /import ContextAssistant from/)
assert.match(source, /scope="reportes-tiempo-admin"/)
assert.match(source, /:context="aiParameters"/)
assert.match(source, /:context-key="aiContextKey"/)
assert.match(source, /selectedEmployeeId: null/)
assert.match(source, /this\.selectedEmployeeId = id/)
assert.match(source, /this\.selectedEmployeeId = null/)
assert.match(source, /\[\s*'reportes-tiempo-admin',[\s\S]*\]\.join\(':'\)/)

const parametersStart = source.indexOf('\t\taiParameters()')
const contextKeyStart = source.indexOf('\t\taiContextKey()', parametersStart)
assert.ok(parametersStart >= 0 && contextKeyStart > parametersStart)
const parametersSource = source.slice(parametersStart, contextKeyStart)
assert.match(parametersSource, /mes_inicio: this\.activePeriod\.periodo_inicio/)
assert.match(parametersSource, /mes_fin: this\.activePeriod\.periodo_fin/)
assert.match(parametersSource, /anio: this\.activePeriod\.anio/)
assert.match(parametersSource, /id: this\.selectedEmployeeId/)
assert.doesNotMatch(
	parametersSource,
	/listas|resumenGeneral|actividades|temp_listas|sueldo|axios|response/,
)
assert.match(source, /appliedPeriod: \{/)
assert.match(source, /this\.appliedPeriod = \{ \.\.\.period \}/)
assert.match(source, /const requestSequence = \+\+this\.employeeRequestSequence/)
assert.match(source, /requestSequence !== this\.employeeRequestSequence/)
assert.match(source, /this\.select = \[\][\s\S]*this\.selectedEmployeeId = null/)
assert.match(source, /periodRequestSequence: 0/)
assert.match(source, /const requestSequence = \+\+this\.periodRequestSequence/)
assert.match(source, /requestSequence !== this\.periodRequestSequence/)
assert.match(source, /const period = \{ \.\.\.this\.activePeriod \}/)
assert.match(source, /this\.GetEmpleadosReports\(requestSequence, period\)/)
assert.match(source, /this\.GetAdminReportsSummary\(requestSequence, period\)/)

for (const suggestion of [
	'Resume el periodo seleccionado.',
	'¿Cuántas horas fueron reportadas?',
	'¿Qué empleados tienen reportes pendientes?',
	'¿Qué actividades acumularon más horas?',
	'¿Qué proyectos tuvieron mayor costo?',
	'Compara el cumplimiento de los empleados.',
]) {
	assert.ok(source.includes(suggestion), `Missing suggestion: ${suggestion}`)
}

console.log('Adminreports AI integration isolated test: PASS')
