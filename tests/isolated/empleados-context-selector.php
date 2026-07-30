<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/Service/Ai/Context/EmpleadosContextSelector.php';

use OCA\Empleados\Service\Ai\Context\EmpleadosContextSelector;

$selector = new EmpleadosContextSelector();
$directory = [[
	'referencias' => ['id_empleado' => 42, 'usuario' => 'juan.perez'],
	'identidad' => ['nombre' => 'Juan Pérez', 'numero_empleado' => '0042'],
]];

$byName = $selector->select('¿Cuál es el RFC de Juan Perez?', $directory);
assert($byName['employee_ids'] === [42]);
assert(in_array('fiscal', $byName['sections'], true));

$byUser = $selector->select('Notas de juan.perez', $directory);
assert($byUser['employee_ids'] === [42]);
assert(in_array('notas', $byUser['sections'], true));
assert($selector->select('Muéstrame la información de Juan', $directory)['employee_ids'] === [42]);

$similarNames = [
	...$directory,
	[
		'referencias' => ['id_empleado' => 43, 'usuario' => 'juan.lopez'],
		'identidad' => ['nombre' => 'Juan López', 'numero_empleado' => '0043'],
	],
];
assert(
	$selector->select('Resume toda la información de Juan Pérez', $similarNames)['employee_ids']
		=== [42]
);
assert(
	$selector->select('Compara a Juan Pérez y Juan López', $similarNames)['employee_ids']
		=== [42, 43]
);
$followUp = $selector->select(
	"¿Y cuál es su sueldo?\n\nREFERENCIA DE CONTINUIDAD: "
		. 'Resume toda la información de Juan Pérez',
	$similarNames,
);
assert($followUp['employee_ids'] === [42]);
assert($followUp['sections'] === ['financiero']);
assert($followUp['include_all_sections'] === false);
$explicitCurrentPerson = $selector->select(
	"¿Y cuál es el sueldo de Juan López?\n\nREFERENCIA DE CONTINUIDAD: "
		. 'Resume a Juan Pérez',
	$similarNames,
);
assert($explicitCurrentPerson['employee_ids'] === [43]);

$categories = [
	'laboral' => 'antigüedad',
	'personal' => 'teléfono',
	'fiscal' => 'CURP',
	'financiero' => 'sueldo',
	'ahorro' => 'retiro del fondo',
	'vacaciones' => 'vacaciones acumuladas',
	'ausencias' => 'incapacidad',
	'sistemas' => 'número de serie de la laptop',
	'notas' => 'observación',
	'archivos' => 'documentos del expediente',
];
foreach ($categories as $category => $question) {
	assert(in_array($category, $selector->select($question, $directory)['sections'], true));
}
assert($selector->select('Expediente completo de Juan Pérez', $directory)['include_all_sections'] === true);
assert(
	($selector->select('¿Cuántas horas reportó Juan?', [])['restriccion_dominio']['submodulo'] ?? null)
		=== 'Reportes de tiempo'
);
assert(
	($selector->select('¿Cuál es el costo de su proyecto?', [])['restriccion_dominio']['submodulo'] ?? null)
		=== 'Proyectos y costos'
);
assert(
	($selector->select('¿Qué clientes atiende?', [])['restriccion_dominio']['submodulo'] ?? null)
		=== 'Clientes'
);
assert(
	($selector->select('Muéstrame las cotizaciones y parcialidades', [])['restriccion_dominio']['submodulo'] ?? null)
		=== 'Honorarios'
);

echo "EmpleadosContextSelector isolated test: PASS\n";
