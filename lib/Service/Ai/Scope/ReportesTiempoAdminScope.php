<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai\Scope;

use OCA\Empleados\Service\Ai\Context\ReportesTiempoContextProvider;

final class ReportesTiempoAdminScope extends AbstractContextScope implements ServerContextScopeInterface {
	private const MIN_YEAR = 2000;
	private const MAX_EMPLOYEE_REFERENCE_LENGTH = 64;

	public function __construct(
		private ReportesTiempoContextProvider $contextProvider,
	) {
	}

	public function getId(): string {
		return 'reportes-tiempo-admin';
	}

	public function getDescription(): string {
		return 'Análisis administrativo de reportes de tiempo, cumplimiento, actividades, proyectos, empleados y costos dentro del periodo seleccionado.';
	}

	public function getRequiredPermissions(): array {
		return ['reporte_tiempos.admin'];
	}

	public function getInstructions(): string {
		return <<<'INSTRUCTIONS'
Responde únicamente sobre reportes de tiempo e indica siempre el periodo analizado.
Distingue horas reportadas, cantidad de reportes y cantidad de empleados.
El costo es una estimación con la tarifa actual del empleado; no lo confundas con su sueldo
ni inventes una moneda. No extrapoles horas fuera del periodo.
No afirmes cumplimiento sin citar la fecha de referencia y la regla incluidas en el contexto.
Cuando no existan registros, dilo expresamente.
No consultes ni solicites información personal, fiscal o financiera ajena al cálculo del costo.
Las preguntas sobre RFC, CURP, cuentas bancarias, ahorro o expedientes pertenecen a otro submódulo.
Ante una de esas preguntas, comienza con: "Esa información pertenece a otro submódulo".
No mezcles estos datos con el asistente global de empleados.
INSTRUCTIONS;
	}

	public function sanitize(array $context): array {
		return [];
	}

	public function sanitizeParameters(array $parameters): array {
		$this->assertAllowedKeys($parameters, ['periodo', 'empleado']);

		$period = $parameters['periodo'] ?? [];
		$employee = $parameters['empleado'] ?? [];
		if (!is_array($period) || !is_array($employee)) {
			throw new \InvalidArgumentException('Los parámetros del alcance no son válidos.');
		}
		$this->assertAllowedKeys($period, ['mes_inicio', 'mes_fin', 'anio']);
		$this->assertAllowedKeys($employee, ['id']);

		$startMonth = $this->strictIntegerOrNull($period['mes_inicio'] ?? null, 'mes');
		$endMonth = $this->strictIntegerOrNull($period['mes_fin'] ?? null, 'mes');
		$year = $this->strictIntegerOrNull($period['anio'] ?? null, 'año');
		foreach ([$startMonth, $endMonth] as $month) {
			if ($month !== null && ($month < 1 || $month > 12)) {
				throw new \InvalidArgumentException('El mes seleccionado no es válido.');
			}
		}
		if ($startMonth === null && $endMonth !== null) {
			$startMonth = $endMonth;
		} elseif ($endMonth === null && $startMonth !== null) {
			$endMonth = $startMonth;
		}
		$hasPeriod = $startMonth !== null || $endMonth !== null || $year !== null;
		if ($hasPeriod
			&& ($startMonth === null || $endMonth === null || $year === null)) {
			throw new \InvalidArgumentException('El periodo seleccionado está incompleto.');
		}
		if ($startMonth !== null && $endMonth !== null && $startMonth > $endMonth) {
			[$startMonth, $endMonth] = [$endMonth, $startMonth];
		}
		$maximumYear = (int)(new \DateTimeImmutable('now'))->format('Y') + 1;
		if ($year !== null && ($year < self::MIN_YEAR || $year > $maximumYear)) {
			throw new \InvalidArgumentException('El año seleccionado no es válido.');
		}

		$employeeId = $employee['id'] ?? null;
		if (is_int($employeeId)) {
			if ($employeeId <= 0) {
				throw new \InvalidArgumentException('El empleado seleccionado no es válido.');
			}
		} elseif (is_string($employeeId)) {
			$employeeId = trim($employeeId);
			if ($employeeId === ''
				|| mb_strlen($employeeId, 'UTF-8') > self::MAX_EMPLOYEE_REFERENCE_LENGTH) {
				throw new \InvalidArgumentException('El empleado seleccionado no es válido.');
			}
			if (ctype_digit($employeeId)) {
				$employeeId = (int)$employeeId;
				if ($employeeId <= 0) {
					throw new \InvalidArgumentException('El empleado seleccionado no es válido.');
				}
			}
		} elseif ($employeeId !== null) {
			throw new \InvalidArgumentException('El empleado seleccionado no es válido.');
		}

		return [
			'periodo' => [
				'mes_inicio' => $startMonth,
				'mes_fin' => $endMonth,
				'anio' => $year,
			],
			'empleado' => ['id' => $employeeId],
		];
	}

	public function buildServerContext(
		string $question,
		string $userId,
		array $parameters,
	): array {
		return $this->contextProvider->build($question, $userId, $parameters);
	}

	private function assertAllowedKeys(array $value, array $allowed): void {
		if (array_diff(array_keys($value), $allowed) !== []) {
			throw new \InvalidArgumentException('Los parámetros contienen campos no permitidos.');
		}
	}

	private function strictIntegerOrNull(mixed $value, string $field): ?int {
		if ($value === null || is_int($value)) {
			return $value;
		}
		throw new \InvalidArgumentException("El {$field} seleccionado no es válido.");
	}
}
