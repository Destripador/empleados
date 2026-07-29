<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai\Scope;

final class EmpleadoLaboralScope extends AbstractContextScope {
	private const MAX_ITEMS = 50;

	public function getId(): string {
		return 'empleado-laboral';
	}

	public function getDescription(): string {
		return 'Información laboral, organizacional, vacacional y de equipo asignado del empleado mostrado en la pestaña Empleado.';
	}

	public function getInstructions(): string {
		return <<<'INSTRUCTIONS'
La vista muestra la información laboral de un único empleado.

Puedes responder sobre:
- Nombre y correo corporativo visible.
- Número de empleado.
- Fecha de ingreso y antigüedad.
- Área y puesto.
- Gerente y socio.
- Equipo de trabajo y miembros visibles.
- Días de vacaciones de derecho mostrados.
- Aniversario laboral actual.
- Equipo de cómputo asignado.

No respondas sobre:
- Sueldo.
- Cuenta bancaria.
- Fondo de ahorro.
- Datos fiscales.
- Datos personales.
- Notas.
- Archivos.
- Otros empleados que no aparezcan en la lista visible.

El campo dias_derecho representa los días asignados por derecho.
No afirmes que son días restantes o disponibles para tomar.

Cuando se pregunte por antigüedad, utiliza antiguedad_anios si está presente.
No recalcules la antigüedad usando la fecha del sistema.
INSTRUCTIONS;
	}

	public function sanitize(array $context): array {
		$this->assertMap($context);

		$empleado = $this->section($context, 'empleado');
		$estructura = $this->section($context, 'estructura');
		$vacaciones = $this->section($context, 'vacaciones');
		$sistemas = $this->section($context, 'sistemas');
		$equipoAsignado = $this->section($sistemas, 'equipo_asignado');

		return [
			'empleado' => [
				'nombre' => $this->stringOrNull($empleado['nombre'] ?? null),
				'correo' => $this->stringOrNull($empleado['correo'] ?? null),
				'numero_empleado' => $this->stringOrNull($empleado['numero_empleado'] ?? null),
				'fecha_ingreso' => $this->stringOrNull($empleado['fecha_ingreso'] ?? null),
				'antiguedad_anios' => $this->numberOrNull($empleado['antiguedad_anios'] ?? null),
			],
			'estructura' => [
				'area' => $this->stringOrNull($estructura['area'] ?? null),
				'puesto' => $this->stringOrNull($estructura['puesto'] ?? null),
				'gerente' => $this->stringOrNull($estructura['gerente'] ?? null),
				'socio' => $this->stringOrNull($estructura['socio'] ?? null),
				'equipo' => $this->stringOrNull($estructura['equipo'] ?? null),
				'jefe_equipo' => $this->stringOrNull($estructura['jefe_equipo'] ?? null),
				'miembros_visibles' => $this->sanitizeMembers(
					$estructura['miembros_visibles'] ?? null
				),
			],
			'vacaciones' => [
				'aniversario_actual' => $this->numberOrNull($vacaciones['aniversario_actual'] ?? null),
				'dias_derecho' => $this->numberOrNull($vacaciones['dias_derecho'] ?? null),
			],
			'sistemas' => [
				'equipo_asignado' => [
					'nombre_dispositivo' => $this->stringOrNull(
						$equipoAsignado['nombre_dispositivo'] ?? null
					),
					'nombre_sistema' => $this->stringOrNull(
						$equipoAsignado['nombre_sistema'] ?? null
					),
					'numero_serie' => $this->stringOrNull($equipoAsignado['numero_serie'] ?? null),
					'marca' => $this->stringOrNull($equipoAsignado['marca'] ?? null),
					'modelo' => $this->stringOrNull($equipoAsignado['modelo'] ?? null),
					'estado' => $this->stringOrNull($equipoAsignado['estado'] ?? null),
				],
			],
		];
	}

	private function sanitizeMembers(mixed $members): array {
		$members = $this->arrayOrEmpty($members);
		if (!array_is_list($members)) {
			throw new \InvalidArgumentException('Los miembros visibles no son válidos.');
		}

		$sanitized = [];
		foreach (array_slice($members, 0, self::MAX_ITEMS) as $member) {
			if (!is_array($member) || ($member !== [] && array_is_list($member))) {
				throw new \InvalidArgumentException('Un miembro visible no es válido.');
			}

			$sanitized[] = [
				'nombre' => $this->stringOrNull($member['nombre'] ?? null),
				'puesto' => $this->stringOrNull($member['puesto'] ?? null),
			];
		}

		return $sanitized;
	}

	private function section(array $context, string $key): array {
		$section = $this->arrayOrEmpty($context[$key] ?? null);
		$this->assertMap($section);
		return $section;
	}

	private function assertMap(array $value): void {
		if ($value !== [] && array_is_list($value)) {
			throw new \InvalidArgumentException('El contexto contiene una estructura inválida.');
		}
	}
}
