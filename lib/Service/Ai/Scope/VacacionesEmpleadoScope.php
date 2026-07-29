<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai\Scope;

final class VacacionesEmpleadoScope extends AbstractContextScope {
	private const MAX_ITEMS = 50;

	public function getId(): string {
		return 'vacaciones-empleado';
	}

	public function getDescription(): string {
		return 'Resumen de vacaciones de un único empleado y un periodo vacacional.';
	}

	public function getInstructions(): string {
		return <<<'INSTRUCTIONS'
La vista muestra el resumen de vacaciones de un único empleado.

Responde únicamente sobre periodos vacacionales, días de derecho,
días disfrutados, días restantes, días acumulados, ausencias visibles
y prima vacacional.

Los días de derecho, disfrutados, restantes y acumulados son conceptos
diferentes. No los mezcles.

Cuando un valor calculado ya esté incluido en el contexto, úsalo
directamente y no lo recalcules.
INSTRUCTIONS;
	}

	public function sanitize(array $context): array {
		$this->assertMap($context);

		$empleado = $this->section($context, 'empleado');
		$periodo = $this->section($context, 'periodo');
		$primaVacacional = $this->section($context, 'prima_vacacional');

		return [
			'empleado' => [
				'nombre' => $this->stringOrNull($empleado['nombre'] ?? null),
			],
			'periodo' => [
				'numero_aniversario' => $this->numberOrNull($periodo['numero_aniversario'] ?? null),
				'inicio' => $this->stringOrNull($periodo['inicio'] ?? null),
				'fin' => $this->stringOrNull($periodo['fin'] ?? null),
				'dias_derecho' => $this->numberOrNull($periodo['dias_derecho'] ?? null),
				'dias_disfrutados' => $this->numberOrNull($periodo['dias_disfrutados'] ?? null),
				'dias_restantes' => $this->numberOrNull($periodo['dias_restantes'] ?? null),
				'dias_acumulados' => $this->numberOrNull($periodo['dias_acumulados'] ?? null),
				'fecha_expiracion_acumulados' => $this->stringOrNull(
					$periodo['fecha_expiracion_acumulados'] ?? null
				),
			],
			'prima_vacacional' => [
				'solicitada' => $this->boolOrNull($primaVacacional['solicitada'] ?? null),
				'fecha' => $this->stringOrNull($primaVacacional['fecha'] ?? null),
			],
			'registros_visibles' => $this->sanitizeRecords(
				$context['registros_visibles'] ?? null
			),
		];
	}

	private function sanitizeRecords(mixed $records): array {
		$records = $this->arrayOrEmpty($records);
		if (!array_is_list($records)) {
			throw new \InvalidArgumentException('Los registros visibles no son válidos.');
		}
		if (count($records) > self::MAX_ITEMS) {
			throw new \InvalidArgumentException('Hay demasiados registros visibles.');
		}

		$sanitized = [];
		foreach ($records as $record) {
			if (!is_array($record) || ($record !== [] && array_is_list($record))) {
				throw new \InvalidArgumentException('Un registro visible no es válido.');
			}

			$sanitized[] = [
				'tipo' => $this->stringOrNull($record['tipo'] ?? null),
				'fecha_inicio' => $this->stringOrNull($record['fecha_inicio'] ?? null),
				'fecha_fin' => $this->stringOrNull($record['fecha_fin'] ?? null),
				'dias' => $this->numberOrNull($record['dias'] ?? null),
				'estado' => $this->stringOrNull($record['estado'] ?? null),
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
