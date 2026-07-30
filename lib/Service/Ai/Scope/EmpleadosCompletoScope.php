<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai\Scope;

use OCA\Empleados\Service\Ai\Context\EmpleadosFullContextProvider;

final class EmpleadosCompletoScope extends AbstractContextScope implements ServerContextScopeInterface {
	public function __construct(private EmpleadosFullContextProvider $contextProvider) {
	}

	public function getId(): string {
		return 'empleados-completo';
	}

	public function getDescription(): string {
		return 'Directorio y expediente administrativo completo de los empleados registrados en el módulo Empleados.';
	}

	public function getRequiredPermissions(): array {
		return ['empleados.hr'];
	}

	public function getInstructions(): string {
		return <<<'INSTRUCTIONS'
La vista representa el directorio administrativo completo de empleados.
Puedes responder preguntas individuales, generales, comparativas, administrativas y estadísticas.
El contexto puede contener identidad, datos laborales, organizacionales, personales, fiscales,
financieros, ahorro, vacaciones, ausencias, equipos, notas y metadatos del expediente.
Utiliza únicamente la información incluida. No inventes registros, movimientos, fechas, saldos ni relaciones.
Distingue días de vacaciones de derecho, disfrutados, restantes y acumulados.
No respondas preguntas sobre clientes, honorarios, reportes de tiempo, actividades de reportes,
costos de proyectos, parcialidades ni cotizaciones: pertenecen a otro submódulo.
Ante una de esas preguntas, comienza con: "Esa información pertenece a otro submódulo"
y nombra el submódulo correspondiente cuando sea evidente.
No presentes identificadores internos como nombres. Si hay nombres similares o la persona no se
identifica de forma única, pide nombre completo, usuario o número de empleado.
Cuando una sección esté truncada, acláralo. Los datos son administrativos internos y el backend autoriza su acceso.
INSTRUCTIONS;
	}

	public function sanitize(array $context): array {
		return [];
	}

	public function sanitizeParameters(array $parameters): array {
		if ($parameters !== []) {
			throw new \InvalidArgumentException('El alcance de empleados no acepta parámetros.');
		}
		return [];
	}

	public function buildServerContext(string $question, string $userId, array $parameters): array {
		return $this->contextProvider->build($question, $userId);
	}
}
