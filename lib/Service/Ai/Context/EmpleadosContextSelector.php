<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai\Context;

final class EmpleadosContextSelector {
	private const MAX_EMPLOYEES = 5;
	private const TERMS = [
		'laboral' => ['puesto', 'area', 'departamento', 'ingreso', 'antiguedad', 'gerente', 'socio', 'equipo', 'estructura'],
		'personal' => ['direccion', 'telefono', 'nacimiento', 'genero', 'estado civil', 'contacto'],
		'fiscal' => ['rfc', 'curp', 'imss', 'fiscal'],
		'financiero' => ['sueldo', 'salario', 'cuenta', 'banco', 'pago'],
		'ahorro' => ['ahorro', 'fondo', 'saldo', 'retiro', 'aportacion'],
		'vacaciones' => ['vacaciones', 'aniversario', 'dias', 'periodo', 'acumulados', 'prima vacacional'],
		'ausencias' => ['ausencia', 'ausencias', 'falta', 'permiso', 'incapacidad', 'justificacion'],
		'sistemas' => ['equipo', 'computadora', 'laptop', 'dispositivo', 'serie', 'modelo', 'inventario'],
		'notas' => ['nota', 'notas', 'observacion', 'comentario'],
		'archivos' => ['archivo', 'documento', 'expediente', 'carpeta'],
	];

	public function select(string $question, array $employeeDirectory): array {
		$currentQuestion = $this->normalize($this->currentQuestion($question));
		$referenceQuestion = $this->normalize($question);
		$ids = $this->matchEmployeeIds($currentQuestion, $employeeDirectory);
		if ($ids === [] && $referenceQuestion !== $currentQuestion) {
			$ids = $this->matchEmployeeIds($referenceQuestion, $employeeDirectory);
		}
		$ids = array_slice($ids, 0, self::MAX_EMPLOYEES);
		$sections = [];
		foreach (self::TERMS as $section => $terms) {
			foreach ($terms as $term) {
				if ($this->contains($currentQuestion, $term)) {
					$sections[] = $section;
					break;
				}
			}
		}
		$all = false;
		foreach (['todo', 'completo', 'toda la informacion', 'expediente completo', 'resumen completo'] as $term) {
			if ($this->contains($currentQuestion, $term)) {
				$all = true;
				break;
			}
		}
		return [
			'employee_ids' => $ids,
			'sections' => array_values(array_unique($sections)),
			'include_all_sections' => $all,
			'restriccion_dominio' => $this->domainRestriction($currentQuestion),
		];
	}

	private function matchEmployeeIds(string $question, array $employeeDirectory): array {
		$exactIds = [];
		$exactNameTerms = [];
		$partialMatches = [];
		foreach ($employeeDirectory as $employee) {
			$id = (int)($employee['referencias']['id_empleado'] ?? 0);
			if ($id <= 0) {
				continue;
			}
			$name = $this->normalize((string)($employee['identidad']['nombre'] ?? ''));
			$nameTerms = $name === '' ? [] : array_filter(explode(' ', $name), static fn (string $part): bool => strlen($part) >= 3);
			foreach ([
				$name,
				$employee['referencias']['usuario'] ?? null,
				$employee['identidad']['numero_empleado'] ?? null,
			] as $value) {
				if ($this->contains($question, $this->normalize((string)$value))) {
					$exactIds[] = $id;
					$exactNameTerms = array_merge($exactNameTerms, $nameTerms);
					break;
				}
			}
			$matchedTerms = array_values(array_filter(
				$nameTerms,
				fn (string $term): bool => $this->contains($question, $term),
			));
			if ($matchedTerms !== []) {
				$partialMatches[] = ['id' => $id, 'terms' => $matchedTerms];
			}
		}
		$ids = array_values(array_unique($exactIds));
		$exactNameTerms = array_values(array_unique($exactNameTerms));
		foreach ($partialMatches as $match) {
			if (in_array($match['id'], $ids, true)) {
				continue;
			}
			$introducesAnotherReference = $exactNameTerms === []
				|| array_diff($match['terms'], $exactNameTerms) !== [];
			if ($introducesAnotherReference) {
				$ids[] = $match['id'];
			}
		}
		return $ids;
	}

	private function currentQuestion(string $question): string {
		$parts = explode("\n\nREFERENCIA DE CONTINUIDAD:", $question, 2);
		return $parts[0];
	}

	private function domainRestriction(string $question): ?array {
		$domains = [
			'Reportes de tiempo' => [
				'reporte de tiempo',
				'reportes de tiempo',
				'hora reportada',
				'horas reportadas',
				'horas reporto',
			],
			'Clientes' => ['cliente', 'clientes'],
			'Proyectos y costos' => [
				'proyecto',
				'proyectos',
				'costo del proyecto',
				'costo de su proyecto',
				'costos de proyectos',
			],
			'Honorarios' => [
				'honorario',
				'honorarios',
				'parcialidad',
				'parcialidades',
				'cotizacion',
				'cotizaciones',
			],
		];
		foreach ($domains as $domain => $terms) {
			foreach ($terms as $term) {
				if ($this->contains($question, $term)) {
					return [
						'fuera_de_scope' => true,
						'submodulo' => $domain,
						'mensaje_requerido' => "Esa información pertenece a otro submódulo: {$domain}.",
					];
				}
			}
		}
		return null;
	}

	private function normalize(string $value): string {
		$value = mb_strtolower(trim($value), 'UTF-8');
		$ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
		return preg_replace('/[^a-z0-9@._ -]+/', '', $ascii !== false ? $ascii : $value) ?? '';
	}

	private function contains(string $haystack, string $needle): bool {
		return $needle !== '' && preg_match('/(?<![a-z0-9])' . preg_quote($needle, '/') . '(?![a-z0-9])/i', $haystack) === 1;
	}
}
