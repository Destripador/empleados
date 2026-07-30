<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai\Context;

final class ReportesTiempoContextSelector {
	public function select(string $question): array {
		$currentQuestion = explode(
			"\n\nREFERENCIA DE CONTINUIDAD:",
			$question,
			2,
		)[0];
		$normalized = mb_strtolower($currentQuestion, 'UTF-8');
		$focus = 'resumen';
		$patterns = [
			'cumplimiento' => ['pendient', 'cumpl', 'falt', 'reportaron hoy'],
			'empleados' => ['emplead', 'persona', 'quién', 'quien', 'compar'],
			'actividades' => ['actividad', 'cargable'],
			'proyectos' => ['proyecto', 'cliente'],
			'costos' => ['costo', 'coste', 'tarifa'],
			'detalle' => ['detalle', 'descripción', 'descripcion', 'registro', 'fecha'],
		];
		foreach ($patterns as $candidate => $needles) {
			foreach ($needles as $needle) {
				if (str_contains($normalized, $needle)) {
					$focus = $candidate;
					break 2;
				}
			}
		}

		return [
			'enfoque' => $focus,
			'secciones_prioritarias' => match ($focus) {
				'cumplimiento' => ['kpis', 'pendientes', 'resumen_por_empleado'],
				'empleados' => ['resumen_por_empleado', 'kpis', 'reportes'],
				'actividades' => ['resumen_por_actividad', 'kpis', 'reportes'],
				'proyectos', 'costos' => ['resumen_por_proyecto', 'kpis', 'reportes'],
				'detalle' => ['reportes', 'kpis'],
				default => ['kpis', 'resumen_por_empleado', 'resumen_por_actividad', 'resumen_por_proyecto'],
			},
			'restriccion_dominio' => $this->domainRestriction($normalized),
		];
	}

	private function domainRestriction(string $question): ?array {
		foreach ([
			'rfc',
			'curp',
			'imss',
			'cuenta bancaria',
			'fondo de ahorro',
			'sueldo',
			'salario',
			'dirección personal',
			'direccion personal',
			'expediente del empleado',
		] as $term) {
			if (str_contains($question, $term)) {
				return [
					'fuera_de_scope' => true,
					'submodulo' => 'Empleados',
					'mensaje_requerido' => 'Esa información pertenece a otro submódulo: Empleados.',
				];
			}
		}
		return null;
	}
}
