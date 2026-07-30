<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai\Scope;

use Psr\Container\ContainerInterface;

final class ContextScopeRegistry {
	private const SCOPES = [
		'vacaciones-empleado' => VacacionesEmpleadoScope::class,
		'empleado-laboral' => EmpleadoLaboralScope::class,
		'empleados-listado' => EmpleadosListadoScope::class,
		'empleados-completo' => EmpleadosCompletoScope::class,
		'reportes-tiempo-admin' => ReportesTiempoAdminScope::class,
	];

	public function __construct(
		private ContainerInterface $container,
	) {
	}

	public function get(string $scopeId): ContextScopeInterface {
		if (!array_key_exists($scopeId, self::SCOPES)) {
			throw new \InvalidArgumentException('El alcance solicitado no es válido.');
		}

		$scope = $this->container->get(self::SCOPES[$scopeId]);
		if (!$scope instanceof ContextScopeInterface) {
			throw new \RuntimeException('La definición del alcance no es válida.');
		}

		return $scope;
	}

	/**
	 * @return list<string>
	 */
	public function getAvailableScopeIds(): array {
		return array_keys(self::SCOPES);
	}
}
