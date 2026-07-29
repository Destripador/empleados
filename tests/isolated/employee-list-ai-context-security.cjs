'use strict'

const assert = require('node:assert/strict')
const fs = require('node:fs')
const nodePath = require('node:path')
const vm = require('node:vm')
const compiler = require('vue-template-compiler')
const parser = require('@babel/parser')
const traverse = require('@babel/traverse').default
const generate = require('@babel/generator').default
const babelTypes = require('@babel/types')

const repoRoot = nodePath.resolve(__dirname, '../..')
const componentPath = nodePath.join(
	repoRoot,
	'src/views/components/ListaEmpleados/EmployeeList.vue',
)
const descriptor = compiler.parseComponent(
	fs.readFileSync(componentPath, 'utf8'),
)

assert.ok(descriptor.script?.content, 'EmployeeList.vue debe contener un script')

const ast = parser.parse(descriptor.script.content, {
	sourceType: 'module',
	plugins: ['nullishCoalescingOperator', 'optionalChaining'],
})
const importedNames = new Set()

traverse(ast, {
	ImportDeclaration(path) {
		for (const specifier of path.node.specifiers) {
			importedNames.add(specifier.local.name)
		}
		path.remove()
	},
	ExportDefaultDeclaration(path) {
		path.replaceWith(
			babelTypes.expressionStatement(
				babelTypes.assignmentExpression(
					'=',
					babelTypes.memberExpression(
						babelTypes.identifier('module'),
						babelTypes.identifier('exports'),
					),
					path.node.declaration,
				),
			),
		)
	},
})

const sandbox = {
	module: { exports: {} },
	exports: {},
}
for (const importedName of importedNames) {
	sandbox[importedName] = importedName === 't'
		? (_app, text) => text
		: { name: `stub:${importedName}` }
}

vm.createContext(sandbox)
vm.runInContext(generate(ast).code, sandbox, { filename: componentPath })

const component = sandbox.module.exports
assert.equal(component.name, 'EmployeeList')

function componentVm(employees) {
	const instance = {
		empleadosProp: employees,
		loadingProp: false,
		searchQuery: '',
		data_empleado: {},
		aiContextVersion: 0,
	}

	for (const [name, method] of Object.entries(component.methods)) {
		instance[name] = method.bind(instance)
	}
	for (const [name, getter] of Object.entries(component.computed)) {
		Object.defineProperty(instance, name, {
			get: () => getter.call(instance),
		})
	}

	return instance
}

const allowedKeys = [
	'nombre',
	'usuario',
	'numero_empleado',
	'fecha_ingreso',
	'antiguedad_anios',
	'area',
	'puesto',
	'gerente',
	'socio',
	'equipo',
	'dias_vacaciones_derecho',
	'equipo_asignado',
	'estado_laboral',
]
const prohibitedKeys = [
	'Sueldo',
	'Numero_cuenta',
	'Fondo_clave',
	'Fondo_ahorro',
	'Rfc',
	'Curp',
	'Imss',
	'Direccion',
	'Telefono_contacto',
	'Fecha_nacimiento',
	'Contacto_emergencia',
	'Numero_emergencia',
	'Notas',
	'Id_empleados',
	'id_ahorro',
]

const sourceEmployee = Object.fromEntries(
	prohibitedKeys.map(key => [key, `SENSITIVE:${key}`]),
)
Object.assign(sourceEmployee, {
	displayname: 'Persona visible',
	uid: 'persona.visible',
	Id_user: 'uid-tecnico',
	Numero_empleado: '0042',
	Ingreso: '2020-02-29',
	area: { displayName: 'Tecnología', Sueldo: 'nested-sensitive' },
	puesto: { label: 'Analista', Tokens: ['nested-sensitive'] },
	gerente: { nombre: 'Gerencia visible', Rfc: 'nested-sensitive' },
	socio: { name: 'Socio visible', Curp: 'nested-sensitive' },
	equipo: { displayname: 'Equipo Norte', Notas: 'nested-sensitive' },
	dias_disponibles: '18.5',
	equipo_asignado_nombre: {
		user: 'Laptop visible',
		Numero_cuenta: 'nested-sensitive',
	},
	Estado: 1,
})

const instance = componentVm([sourceEmployee])
const employee = instance.aiEmployee(sourceEmployee)

assert.deepEqual(Object.keys(employee), allowedKeys)
for (const prohibitedKey of prohibitedKeys) {
	assert.equal(
		Object.hasOwn(employee, prohibitedKey),
		false,
		`aiEmployee() filtró incorrectamente ${prohibitedKey}`,
	)
}
assert.equal(JSON.stringify(employee).includes('nested-sensitive'), false)
assert.equal(employee.usuario, 'persona.visible')
assert.equal(employee.dias_vacaciones_derecho, 18.5)
assert.equal(employee.estado_laboral, 'Activo')

const sourceList = Array.from(
	{ length: 201 },
	(_, index) => ({
		...sourceEmployee,
		uid: `persona.${index}`,
	}),
)
const largeInstance = componentVm(sourceList)

assert.equal(largeInstance.aiEmployees.length, 200)
assert.equal(largeInstance.aiContext.resumen.total_empleados, 201)
assert.equal(largeInstance.aiContext.resumen.contexto_truncado, true)

const originalContext = JSON.stringify(largeInstance.aiContext)
const originalKey = largeInstance.aiContextKey
largeInstance.searchQuery = 'filtro local'
largeInstance.data_empleado = { Sueldo: 'selected-sensitive' }

assert.equal(JSON.stringify(largeInstance.aiContext), originalContext)
assert.equal(largeInstance.aiContextKey, originalKey)

console.log('EmployeeList AI context security test: PASS')
