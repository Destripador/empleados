<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai\Scope;

final class EmpleadosListadoScope extends AbstractContextScope {
	private const MAX_EMPLOYEES = 200;

	public function getId(): string {
		return 'empleados-listado';
	}

	public function getDescription(): string {
		return <<<'DESCRIPTION'
Directorio laboral de los empleados disponibles en el módulo Empleados.
El contexto puede contener múltiples empleados.
DESCRIPTION;
	}

	/**
	 * @return list<string>
	 */
	public function getRequiredPermissions(): array {
		return [
			'empleados.hr',
			'empleados.admin',
		];
	}

	public function getInstructions(): string {
		return <<<'INSTRUCTIONS'
La vista contiene un directorio laboral de múltiples empleados.

Puedes responder preguntas generales, comparativas y de búsqueda
sobre los empleados incluidos en el contexto.

Puedes:
- Contar empleados.
- Localizar empleados por nombre o usuario.
- Comparar fechas de ingreso y antigüedad.
- Agrupar por área, puesto, gerente, socio o equipo cuando esos datos
  estén disponibles.
- Identificar campos laborales faltantes.
- Comparar los días de vacaciones de derecho.
- Resumir la composición de la plantilla.

Utiliza únicamente los empleados incluidos en la lista.

Cuando la pregunta mencione a una persona:
- Busca coincidencias por nombre o usuario.
- Si hay varias coincidencias posibles, indica la ambigüedad.
- No inventes cuál persona quiso decir el usuario.

No afirmes que dias_vacaciones_derecho representa días restantes.
Ese campo contiene días asignados por derecho, no necesariamente días
todavía disponibles para tomar.

Cuando calcules un total, cuenta solamente los registros incluidos en
el contexto.

Si contexto_truncado es verdadero, aclara que el resultado solamente
considera los empleados incluidos en el contexto.

No respondas sobre sueldos, cuentas bancarias, fondos de ahorro,
datos fiscales, información personal, notas ni archivos.

Cuando la información no esté disponible, responde:
"Esa información no está disponible en el directorio actual."
INSTRUCTIONS;
	}

	public function sanitize(array $context): array {
		$this->assertMap($context);

		$summary = $this->requiredSection($context, 'resumen');
		if (!array_key_exists('total_empleados', $summary)
			|| !array_key_exists('contexto_truncado', $summary)) {
			throw new \InvalidArgumentException('El resumen del directorio no es válido.');
		}

		$totalEmployees = $this->numberOrNull($summary['total_empleados']);
		$isTruncated = $this->boolOrNull($summary['contexto_truncado']);

		if ($totalEmployees === null || $isTruncated === null) {
			throw new \InvalidArgumentException('El resumen del directorio no es válido.');
		}
		if (!array_key_exists('empleados', $context)) {
			throw new \InvalidArgumentException('La lista de empleados es obligatoria.');
		}

		return [
			'resumen' => [
				'total_empleados' => $totalEmployees,
				'contexto_truncado' => $isTruncated,
			],
			'empleados' => $this->sanitizeEmployees($context['empleados'] ?? null),
		];
	}

	private function sanitizeEmployees(mixed $employees): array {
		if (!is_array($employees) || !array_is_list($employees)) {
			throw new \InvalidArgumentException('La lista de empleados no es válida.');
		}
		if (count($employees) > self::MAX_EMPLOYEES) {
			throw new \InvalidArgumentException('Hay demasiados empleados en el contexto.');
		}

		$sanitized = [];
		foreach ($employees as $employee) {
			if (!is_array($employee) || ($employee !== [] && array_is_list($employee))) {
				throw new \InvalidArgumentException('Un empleado del contexto no es válido.');
			}

			$sanitized[] = [
				'nombre' => $this->stringOrNull($employee['nombre'] ?? null),
				'usuario' => $this->stringOrNull($employee['usuario'] ?? null),
				'numero_empleado' => $this->stringOrNull($employee['numero_empleado'] ?? null),
				'fecha_ingreso' => $this->stringOrNull($employee['fecha_ingreso'] ?? null),
				'antiguedad_anios' => $this->numberOrNull($employee['antiguedad_anios'] ?? null),
				'area' => $this->stringOrNull($employee['area'] ?? null),
				'puesto' => $this->stringOrNull($employee['puesto'] ?? null),
				'gerente' => $this->stringOrNull($employee['gerente'] ?? null),
				'socio' => $this->stringOrNull($employee['socio'] ?? null),
				'equipo' => $this->stringOrNull($employee['equipo'] ?? null),
				'dias_vacaciones_derecho' => $this->numberOrNull(
					$employee['dias_vacaciones_derecho'] ?? null
				),
				'equipo_asignado' => $this->stringOrNull($employee['equipo_asignado'] ?? null),
				'estado_laboral' => $this->stringOrNull($employee['estado_laboral'] ?? null),
			];
		}

		return $sanitized;
	}

	private function requiredSection(array $context, string $key): array {
		if (!array_key_exists($key, $context) || $context[$key] === null) {
			throw new \InvalidArgumentException('El contexto no contiene una sección obligatoria.');
		}

		$section = $this->arrayOrEmpty($context[$key]);
		$this->assertMap($section);
		return $section;
	}

	private function assertMap(array $value): void {
		if ($value !== [] && array_is_list($value)) {
			throw new \InvalidArgumentException('El contexto contiene una estructura inválida.');
		}
	}
}
