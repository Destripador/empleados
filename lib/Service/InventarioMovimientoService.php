<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\InventarioComputoMapper;
use OCA\Empleados\Db\InventarioMovimiento;
use OCA\Empleados\Db\InventarioMovimientoMapper;
use OCA\Empleados\Db\SoporteHistorialMapper;
use OCP\IDBConnection;
use OCP\IUserSession;

class InventarioMovimientoService {
	public const SOPORTE_CATEGORIAS = ['mantenimiento', 'reparacion', 'diagnostico', 'configuracion', 'otro'];
	public const SOPORTE_PRIORIDADES = ['baja', 'media', 'alta', 'critica'];

	private const EQUIPO_FIELDS = [
		'id_empleado', 'id_modelo', 'nombre_dispositivo', 'nombre_sistema',
		'numero_serie', 'estado', 'info',
	];

	public function __construct(
		private IDBConnection $db,
		private IUserSession $userSession,
		private InventarioComputoMapper $computoMapper,
		private InventarioMovimientoMapper $movimientoMapper,
		private empleadosMapper $empleadosMapper,
		private SoporteHistorialMapper $soporteMapper,
		private SoporteReporteTiempoService $soporteReporteService,
	) {
	}

	public function crearEquipo(array $data): int {
		return $this->transactional(function () use ($data): int {
			$idEquipo = $this->computoMapper->create($data);
			$nuevo = $this->computoMapper->findById($idEquipo) ?? array_merge($data, ['id_equipo' => $idEquipo]);
			$this->registrarMovimiento(
				$idEquipo,
				InventarioMovimiento::TIPO_ALTA,
				null,
				$nuevo,
				'Equipo registrado en inventario.',
				$this->construirDiff([], $nuevo),
			);

			return $idEquipo;
		});
	}

	public function actualizarEquipo(int $idEquipo, array $data): bool {
		$anterior = $this->computoMapper->findById($idEquipo);
		if ($anterior === null) throw new \RuntimeException('Equipo no encontrado.');
		$nuevo = array_merge($anterior, $data);
		$cambios = $this->construirDiff($anterior, $nuevo);
		if ($cambios === []) return false;

		$this->transactional(function () use ($idEquipo, $anterior, $nuevo, $cambios): void {
			$this->computoMapper->updateById($idEquipo, $nuevo);
			$this->registrarMovimiento(
				$idEquipo,
				$this->resolverTipoMovimiento($anterior, $nuevo),
				$anterior,
				$nuevo,
				'Equipo actualizado.',
				$cambios,
			);
		});

		return true;
	}

	public function darDeBaja(int $idEquipo): bool {
		$equipo = $this->computoMapper->findById($idEquipo);
		if ($equipo === null) throw new \RuntimeException('Equipo no encontrado.');
		if (strtolower((string)($equipo['estado'] ?? '')) === 'baja') return false;

		$equipo['estado'] = 'baja';
		return $this->actualizarEquipo($idEquipo, $equipo);
	}

	public function ejecutarCambioAsignacion(
		int $idEmpleado,
		?int $equipoAnterior,
		?int $equipoNuevo,
		callable $actualizacion
	): void {
		$this->transactional(function () use ($idEmpleado, $equipoAnterior, $equipoNuevo, $actualizacion): void {
			$actualizacion();
			if ($equipoAnterior === $equipoNuevo) return;
			$empleado = $this->empleadoSnapshot($idEmpleado);

			if ($equipoAnterior !== null && $this->computoMapper->findById($equipoAnterior) !== null) {
				$this->registrarMovimientoDesdeSnapshots(
					$equipoAnterior,
					InventarioMovimiento::TIPO_DESASIGNACION,
					$empleado,
					null,
					'Equipo desasignado del empleado.',
				);
			}

			if ($equipoNuevo !== null && $this->computoMapper->findById($equipoNuevo) !== null) {
				$this->registrarMovimientoDesdeSnapshots(
					$equipoNuevo,
					InventarioMovimiento::TIPO_ASIGNACION,
					null,
					$empleado,
					'Equipo asignado al empleado.',
				);
			}
		});
	}

	public function listarHistorial(int $idEquipo, int $limit = 25, int $offset = 0): array {
		if ($this->computoMapper->findById($idEquipo) === null) throw new \RuntimeException('Equipo no encontrado.');
		$limit = max(1, min(100, $limit));
		$offset = max(0, $offset);

		return [
			'data' => array_map([$this, 'toSafeArray'], $this->movimientoMapper->findByEquipo($idEquipo, $limit, $offset)),
			'total' => $this->movimientoMapper->countByEquipo($idEquipo),
			'limit' => $limit,
			'offset' => $offset,
		];
	}

	public function registrarNota(int $idEquipo, string $descripcion): array {
		$descripcion = trim($descripcion);
		if ($descripcion === '') throw new \InvalidArgumentException('La nota no puede estar vacía.');
		if (mb_strlen($descripcion) > 2000) throw new \InvalidArgumentException('La nota excede la longitud permitida.');
		if ($this->computoMapper->findById($idEquipo) === null) throw new \RuntimeException('Equipo no encontrado.');

		$movimiento = $this->transactional(fn(): InventarioMovimiento => $this->registrarMovimientoDesdeSnapshots(
			$idEquipo,
			InventarioMovimiento::TIPO_NOTA,
			null,
			null,
			$descripcion,
		));

		return $this->toSafeArray($movimiento);
	}

	public function registrarSoporte(
		int $idEquipo,
		string $descripcion,
		?string $categoria = null,
		?string $prioridad = null,
		?string $accionLegacy = null,
		mixed $duracionMinutos = null,
		?string $fecha = null
	): array {
		if ($idEquipo <= 0) throw new \InvalidArgumentException('Identificador de equipo inválido.');
		$descripcion = trim($descripcion);
		if ($descripcion === '') throw new \InvalidArgumentException('La descripción es obligatoria.');
		if (mb_strlen($descripcion) > 4000) throw new \InvalidArgumentException('La descripción excede la longitud permitida.');

		$categoria = strtolower(trim((string)$categoria));
		$prioridad = strtolower(trim((string)($prioridad ?: 'media')));
		if ($categoria !== '' && !in_array($categoria, self::SOPORTE_CATEGORIAS, true)) {
			throw new \InvalidArgumentException('Categoría de soporte inválida.');
		}
		if (!in_array($prioridad, self::SOPORTE_PRIORIDADES, true)) {
			throw new \InvalidArgumentException('Prioridad de soporte inválida.');
		}

		$accionLegacy = trim((string)$accionLegacy);
		if ($categoria === '' && $accionLegacy === '') {
			throw new \InvalidArgumentException('La categoría de soporte es obligatoria.');
		}
		$accion = $categoria !== '' ? $categoria . ' · ' . $prioridad : $accionLegacy;
		if (mb_strlen($accion) > 150) throw new \InvalidArgumentException('La acción de soporte excede la longitud permitida.');

		$equipo = $this->computoMapper->findById($idEquipo);
		if ($equipo === null) throw new \RuntimeException('Equipo no encontrado.');
		$actor = $this->actorSnapshot();
		if ($actor['uid'] === 'sistema') throw new \RuntimeException('Usuario no autenticado.');
		$duration = $this->soporteReporteService->validarDuracion($duracionMinutos);
		$supportDate = $this->soporteReporteService->normalizarFecha($fecha, $actor['uid']);
		$snapshot = $equipo;
		$snapshot['id_empleado'] = $equipo['empleado_id'] ?? $equipo['id_empleado'] ?? null;
		$tipoMovimiento = match ($categoria) {
			'mantenimiento' => InventarioMovimiento::TIPO_MANTENIMIENTO,
			'reparacion' => InventarioMovimiento::TIPO_REPARACION,
			default => InventarioMovimiento::TIPO_ACTUALIZACION,
		};

		return $this->transactional(function () use ($idEquipo, $accion, $descripcion, $categoria, $prioridad, $equipo, $snapshot, $actor, $tipoMovimiento, $duration, $supportDate): array {
			$duplicado = $this->soporteMapper->findRecentDuplicate(
				$idEquipo,
				$accion,
				$descripcion,
				$actor['uid'],
				$supportDate,
				$duration,
				date('Y-m-d H:i:s', time() - 10),
			);
			if ($duplicado !== null) {
				$idReporte = $this->soporteReporteService->actualizarDesdeSoporte($duplicado, $equipo);
				return [
					'id_soporte' => (int)$duplicado['id_soporte'],
					'id_reporte' => $idReporte,
					'id_equipo' => $idEquipo,
					'accion' => $accion,
					'detalles' => $descripcion,
					'duplicado' => true,
					'duracion_minutos' => $duration,
				];
			}

			$idSoporte = $this->soporteMapper->create([
				'id_equipo' => $idEquipo,
				'accion' => $accion,
				'detalles' => $descripcion,
				'usuario_actual' => $equipo['empleado_uid'] ?? null,
				'usuario_soporte' => $actor['uid'],
				'fecha' => $supportDate,
				'duracion_minutos' => $duration,
			]);
			$this->registrarMovimiento(
				$idEquipo,
				$tipoMovimiento,
				$snapshot,
				$snapshot,
				'Soporte registrado: ' . $descripcion,
				[
					'categoria_soporte' => ['anterior' => null, 'nuevo' => $categoria ?: $accion],
					'prioridad_soporte' => ['anterior' => null, 'nuevo' => $prioridad],
				],
			);
			$soporte = $this->soporteMapper->findById($idSoporte);
			if ($soporte === null) throw new \RuntimeException('No se pudo recuperar el soporte creado.');
			$idReporte = $this->soporteReporteService->crearDesdeSoporte($soporte, $equipo);

			return [
				'id_soporte' => $idSoporte,
				'id_reporte' => $idReporte,
				'id_equipo' => $idEquipo,
				'accion' => $accion,
				'detalles' => $descripcion,
				'duplicado' => false,
				'duracion_minutos' => $duration,
			];
		});
	}

	public function actualizarSoporte(int $idSoporte, array $data): array {
		$anterior = $this->soporteMapper->findById($idSoporte);
		if ($anterior === null) throw new \RuntimeException('Registro de soporte no encontrado.');
		$equipo = $this->computoMapper->findById((int)$anterior['id_equipo']);
		if ($equipo === null) throw new \RuntimeException('Equipo no encontrado.');

		$duration = $this->soporteReporteService->validarDuracion($data['duracion_minutos'] ?? $anterior['duracion_minutos'] ?? null);
		$fecha = $this->soporteReporteService->normalizarFecha(
			isset($data['fecha']) ? (string)$data['fecha'] : (string)($anterior['fecha'] ?? ''),
			(string)$anterior['usuario_soporte']
		);
		$nuevo = array_merge($anterior, [
			'accion' => trim((string)($data['accion'] ?? $anterior['accion'])),
			'detalles' => trim((string)($data['detalles'] ?? $anterior['detalles'])),
			'fecha' => $fecha,
			'duracion_minutos' => $duration,
		]);
		if ($nuevo['accion'] === '' || $nuevo['detalles'] === '') {
			throw new \InvalidArgumentException('La acción y la descripción son obligatorias.');
		}
		$actionParts = array_map('trim', explode('·', strtolower($nuevo['accion'])));
		if (!in_array($actionParts[0] ?? '', self::SOPORTE_CATEGORIAS, true)
			|| !in_array($actionParts[1] ?? '', self::SOPORTE_PRIORIDADES, true)) {
			throw new \InvalidArgumentException('La categoría o prioridad de soporte no es válida.');
		}

		return $this->transactional(function () use ($idSoporte, $nuevo, $equipo): array {
			$this->soporteMapper->updateById($idSoporte, $nuevo);
			$idReporte = $this->soporteReporteService->actualizarDesdeSoporte($nuevo, $equipo);
			$this->registrarMovimiento(
				(int)$nuevo['id_equipo'],
				InventarioMovimiento::TIPO_ACTUALIZACION,
				$equipo,
				$equipo,
				'Soporte actualizado: ' . $nuevo['detalles'],
				['soporte' => ['anterior' => null, 'nuevo' => $idSoporte]],
			);

			return $nuevo + ['id_reporte' => $idReporte];
		});
	}

	public function eliminarSoporte(int $idSoporte): void {
		$soporte = $this->soporteMapper->findById($idSoporte);
		if ($soporte === null) throw new \RuntimeException('Registro de soporte no encontrado.');
		$this->transactional(function () use ($idSoporte): void {
			$this->soporteReporteService->eliminarDesdeSoporte($idSoporte);
			$this->soporteMapper->deleteById($idSoporte);
		});
	}

	public function construirDiff(array $anterior, array $nuevo): array {
		$diff = [];
		foreach (self::EQUIPO_FIELDS as $field) {
			$old = $this->normalizarValor($field, $anterior[$field] ?? null);
			$new = $this->normalizarValor($field, $nuevo[$field] ?? null);
			if ($old !== $new) $diff[$field] = ['anterior' => $old, 'nuevo' => $new];
		}

		return $diff;
	}

	public function toSafeArray(InventarioMovimiento $movimiento): array {
		$cambios = $movimiento->getCambios();
		return [
			'id' => $movimiento->getId(),
			'id_equipo' => $movimiento->getIdEquipo(),
			'tipo_movimiento' => $movimiento->getTipoMovimiento(),
			'actor_uid' => $movimiento->getActorUid(),
			'actor_nombre' => $movimiento->getActorNombre(),
			'empleado_anterior_uid' => $movimiento->getEmpleadoAnteriorUid(),
			'empleado_anterior_nombre' => $movimiento->getEmpleadoAnteriorNombre(),
			'empleado_nuevo_uid' => $movimiento->getEmpleadoNuevoUid(),
			'empleado_nuevo_nombre' => $movimiento->getEmpleadoNuevoNombre(),
			'estado_anterior' => $movimiento->getEstadoAnterior(),
			'estado_nuevo' => $movimiento->getEstadoNuevo(),
			'descripcion' => $movimiento->getDescripcion(),
			'cambios' => $cambios ? (json_decode($cambios, true) ?: []) : [],
			'fecha' => $movimiento->getFecha(),
		];
	}

	public function registrarMovimiento(int $idEquipo, string $tipo, ?array $anterior, ?array $nuevo, string $descripcion, array $cambios = []): InventarioMovimiento {
		if (!in_array($tipo, InventarioMovimiento::TIPOS_VALIDOS, true)) {
			throw new \InvalidArgumentException('Tipo de movimiento inválido.');
		}
		return $this->registrarMovimientoDesdeSnapshots(
			$idEquipo,
			$tipo,
			$this->empleadoSnapshot(isset($anterior['id_empleado']) ? (int)$anterior['id_empleado'] : null),
			$this->empleadoSnapshot(isset($nuevo['id_empleado']) ? (int)$nuevo['id_empleado'] : null),
			$descripcion,
			(string)($anterior['estado'] ?? ''),
			(string)($nuevo['estado'] ?? ''),
			array_diff_key($cambios, ['id_empleado' => true, 'estado' => true]),
		);
	}

	private function registrarMovimientoDesdeSnapshots(int $idEquipo, string $tipo, ?array $anterior, ?array $nuevo, string $descripcion, ?string $estadoAnterior = null, ?string $estadoNuevo = null, array $cambios = []): InventarioMovimiento {
		$actor = $this->actorSnapshot();
		$movimiento = new InventarioMovimiento();
		$movimiento->setIdEquipo($idEquipo);
		$movimiento->setTipoMovimiento($tipo);
		$movimiento->setActorUid($actor['uid']);
		$movimiento->setActorNombre($actor['nombre']);
		$movimiento->setEmpleadoAnteriorUid($anterior['uid'] ?? null);
		$movimiento->setEmpleadoAnteriorNombre($anterior['nombre'] ?? null);
		$movimiento->setEmpleadoNuevoUid($nuevo['uid'] ?? null);
		$movimiento->setEmpleadoNuevoNombre($nuevo['nombre'] ?? null);
		$movimiento->setEstadoAnterior($estadoAnterior ?: null);
		$movimiento->setEstadoNuevo($estadoNuevo ?: null);
		$movimiento->setDescripcion($descripcion);
		$movimiento->setCambios($cambios ? json_encode($cambios, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null);
		$movimiento->setFecha(date('Y-m-d H:i:s'));

		return $this->movimientoMapper->insert($movimiento);
	}

	private function resolverTipoMovimiento(array $anterior, array $nuevo): string {
		$oldEmployee = $this->normalizarValor('id_empleado', $anterior['id_empleado'] ?? null);
		$newEmployee = $this->normalizarValor('id_empleado', $nuevo['id_empleado'] ?? null);
		if ($oldEmployee !== $newEmployee) {
			if ($oldEmployee === null) return InventarioMovimiento::TIPO_ASIGNACION;
			if ($newEmployee === null) return InventarioMovimiento::TIPO_DESASIGNACION;
			return InventarioMovimiento::TIPO_REASIGNACION;
		}
		if (strtolower((string)($nuevo['estado'] ?? '')) === 'baja') return InventarioMovimiento::TIPO_BAJA;
		if (($anterior['estado'] ?? null) !== ($nuevo['estado'] ?? null)) return InventarioMovimiento::TIPO_CAMBIO_ESTADO;
		return InventarioMovimiento::TIPO_ACTUALIZACION;
	}

	private function empleadoSnapshot(?int $idEmpleado): ?array {
		if ($idEmpleado === null || $idEmpleado <= 0) return null;
		$rows = $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string)$idEmpleado);
		if ($rows === []) return null;
		$uid = (string)($rows[0]['Id_user'] ?? '');
		return ['uid' => $uid, 'nombre' => $this->empleadosMapper->getDisplayNameById($idEmpleado) ?? $uid];
	}

	private function actorSnapshot(): array {
		$user = $this->userSession->getUser();
		return $user === null
			? ['uid' => 'sistema', 'nombre' => 'Sistema']
			: ['uid' => $user->getUID(), 'nombre' => $user->getDisplayName()];
	}

	private function normalizarValor(string $field, mixed $value): mixed {
		if ($value === '' || $value === null) return null;
		return in_array($field, ['id_empleado', 'id_modelo'], true) ? (int)$value : (string)$value;
	}

	private function transactional(callable $callback): mixed {
		$this->db->beginTransaction();
		try {
			$result = $callback();
			$this->db->commit();
			return $result;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}
}
