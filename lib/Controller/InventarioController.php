<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\InventarioModeloMapper;
use OCA\Empleados\Db\InventarioComputoMapper;
use OCA\Empleados\Db\SoporteHistorialMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Service\PermisosService;
use OCA\Empleados\Service\InventarioMovimientoService;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

class InventarioController extends BaseController {

	private InventarioModeloMapper $modelosMapper;
	private InventarioComputoMapper $computoMapper;
	private SoporteHistorialMapper $soporteMapper;
	private PermisosService $permisosService;
	private InventarioMovimientoService $movimientoService;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper,
		InventarioModeloMapper $modelosMapper,
		InventarioComputoMapper $computoMapper,
		SoporteHistorialMapper $soporteMapper,
		PermisosService $permisosService,
		InventarioMovimientoService $movimientoService
	) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);

		$this->modelosMapper = $modelosMapper;
		$this->computoMapper = $computoMapper;
		$this->soporteMapper = $soporteMapper;
		$this->permisosService = $permisosService;
		$this->movimientoService = $movimientoService;
	}

	private function requireInventarioAccess(): void {
		$this->permisosService->requireCanSee('inventario');
	}

	private function requireInventarioAdminAccess(): void {
		$this->permisosService->requireCanSee('inventario.admin');
	}

	/************************ MODELOS ************************/

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventarioModelos(?string $search = null, ?string $tipo = null): DataResponse {
		try {
			$this->requireInventarioAccess();

			return new DataResponse([
				'success' => true,
				'data' => $this->modelosMapper->findAll($search, $tipo),
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventarioModelo(int $id_modelo): DataResponse {
		try {
			$this->requireInventarioAccess();

			$modelo = $this->modelosMapper->findById($id_modelo);

			if (!$modelo) {
				return new DataResponse([
					'success' => false,
					'message' => 'Modelo no encontrado.',
				], Http::STATUS_NOT_FOUND);
			}

			return new DataResponse([
				'success' => true,
				'data' => $modelo,
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function CrearInventarioModelo(
		?string $marca = null,
		?string $modelo = null,
		?string $procesador = null,
		?string $ram = null,
		?string $disco_duro = null,
		?string $tipo = null,
		$touch = false
	): DataResponse {
		try {
			$this->requireInventarioAdminAccess();

			$id = $this->modelosMapper->create([
				'marca' => $marca,
				'modelo' => $modelo,
				'procesador' => $procesador,
				'ram' => $ram,
				'disco_duro' => $disco_duro,
				'tipo' => $tipo,
				'touch' => $this->toBool($touch),
			]);

			return new DataResponse([
				'success' => true,
				'id_modelo' => $id,
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function ActualizarInventarioModelo(
		int $id_modelo,
		?string $marca = null,
		?string $modelo = null,
		?string $procesador = null,
		?string $ram = null,
		?string $disco_duro = null,
		?string $tipo = null,
		$touch = false
	): DataResponse {
		try {
			$this->requireInventarioAdminAccess();

			$this->modelosMapper->updateById($id_modelo, [
				'marca' => $marca,
				'modelo' => $modelo,
				'procesador' => $procesador,
				'ram' => $ram,
				'disco_duro' => $disco_duro,
				'tipo' => $tipo,
				'touch' => $this->toBool($touch),
			]);

			return new DataResponse([
				'success' => true,
				'message' => 'Modelo actualizado correctamente.',
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function EliminarInventarioModelo(int $id_modelo): DataResponse {
		try {
			$this->requireInventarioAdminAccess();

			$this->modelosMapper->deleteById($id_modelo);

			return new DataResponse([
				'success' => true,
				'message' => 'Modelo eliminado correctamente.',
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	/************************ EQUIPOS ************************/

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventarioComputo(
		?string $search = null,
		?string $estado = null,
		?int $id_empleado = null,
		?string $asignacion = null,
		?int $id_modelo = null,
		int $limit = 25,
		int $offset = 0
	): DataResponse {
		try {
			if (!$this->permisosService->canSee('inventario')) {
				return new DataResponse(['success' => false, 'message' => 'No tienes permiso para consultar equipos.'], Http::STATUS_FORBIDDEN);
			}
			if ($limit <= 0 || $offset < 0 || ($id_empleado !== null && $id_empleado <= 0) || ($id_modelo !== null && $id_modelo <= 0)) {
				return new DataResponse(['success' => false, 'message' => 'Parámetros de inventario inválidos.'], Http::STATUS_BAD_REQUEST);
			}
			if ($asignacion !== null && !in_array($asignacion, ['', 'asignado', 'sin_asignar'], true)) {
				return new DataResponse(['success' => false, 'message' => 'Filtro de asignación inválido.'], Http::STATUS_BAD_REQUEST);
			}

			$limit = min(100, $limit);
			$items = $this->computoMapper->findAll($search, $estado, $id_empleado, $asignacion, $id_modelo, $limit, $offset);
			$total = $this->computoMapper->countAll($search, $estado, $id_empleado, $asignacion, $id_modelo);

			return new DataResponse([
				'success' => true,
				'data' => $items,
				'total' => $total,
				'limit' => $limit,
				'offset' => $offset,
				'filter_options' => [
					'empleados' => $this->computoMapper->findAssignedEmployees(),
				],
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventarioEquiposSelect(): DataResponse {
		try {
			$this->requireInventarioAccess();

			$employee = $this->request->getParam('employee', null);
			$onlyAvailable = (string)$this->request->getParam('onlyAvailable', 'true') !== 'false';
			$idEmpleado = $employee !== null && $employee !== '' ? (int)$employee : null;

			return new DataResponse([
				'success' => true,
				'data' => $this->computoMapper->findAllForSelect($idEmpleado, $onlyAvailable),
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventarioEquipo(int $id_equipo): DataResponse {
		try {
			if (!$this->permisosService->canSee('inventario')) {
				return new DataResponse([
					'success' => false,
					'message' => 'No tienes permiso para consultar inventario.',
				], Http::STATUS_FORBIDDEN);
			}

			if ($id_equipo <= 0) {
				return new DataResponse([
					'success' => false,
					'message' => 'Identificador de equipo inválido.',
				], Http::STATUS_BAD_REQUEST);
			}

			$equipo = $this->computoMapper->findById($id_equipo);

			if (!$equipo) {
				return new DataResponse([
					'success' => false,
					'message' => 'Equipo no encontrado.',
				], Http::STATUS_NOT_FOUND);
			}

			return new DataResponse([
				'success' => true,
				'data' => $equipo,
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventarioEmpleado(int $id_empleado): DataResponse {
		try {
			$this->requireInventarioAccess();

			return new DataResponse([
				'success' => true,
				'data' => $this->computoMapper->findByEmpleado($id_empleado),
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetEquiposEmpleado(int $id_empleado): DataResponse {
		if (!$this->permisosService->canSee('inventario')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para consultar equipos.'], Http::STATUS_FORBIDDEN);
		}
		try {
			if ($id_empleado <= 0) {
				return new DataResponse(['success' => false, 'message' => 'Identificador de empleado inválido.'], Http::STATUS_BAD_REQUEST);
			}
			if ($this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado((string)$id_empleado) === []) {
				return new DataResponse(['success' => false, 'message' => 'Empleado no encontrado.'], Http::STATUS_NOT_FOUND);
			}
			return new DataResponse([
				'success' => true,
				'equipos' => $this->computoMapper->findByEmpleado($id_empleado),
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function AsignarEquipoEmpleado(int $id_equipo, int $id_empleado): DataResponse {
		if (!$this->permisosService->canSee('inventario.admin')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para asignar equipos.'], Http::STATUS_FORBIDDEN);
		}
		try {
			$anterior = $this->computoMapper->findById($id_equipo);
			$sinCambios = $anterior !== null && (int)($anterior['id_empleado'] ?? 0) === $id_empleado;
			$this->movimientoService->asignarEquipo($id_equipo, $id_empleado);
			return new DataResponse([
				'success' => true,
				'equipos' => $this->computoMapper->findByEmpleado($id_empleado),
				'message' => $sinCambios ? 'El equipo ya estaba asignado al empleado.' : 'Equipo asignado correctamente.',
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->assignmentErrorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function DesasignarEquipoEmpleado(int $id_equipo): DataResponse {
		if (!$this->permisosService->canSee('inventario.admin')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para desasignar equipos.'], Http::STATUS_FORBIDDEN);
		}
		try {
			$anterior = $this->computoMapper->findById($id_equipo);
			$sinCambios = $anterior !== null && empty($anterior['id_empleado']);
			$idEmpleadoAnterior = (int)($anterior['id_empleado'] ?? 0);
			$this->movimientoService->desasignarEquipo($id_equipo);
			return new DataResponse([
				'success' => true,
				'equipos' => $idEmpleadoAnterior > 0 ? $this->computoMapper->findByEmpleado($idEmpleadoAnterior) : [],
				'message' => $sinCambios ? 'El equipo ya estaba desasignado.' : 'Equipo desasignado correctamente.',
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->assignmentErrorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function SincronizarEquiposEmpleado(int $id_empleado, array $equipos = []): DataResponse {
		if (!$this->permisosService->canSee('inventario.admin')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para actualizar asignaciones.'], Http::STATUS_FORBIDDEN);
		}
		try {
			$this->movimientoService->sincronizarEquiposEmpleado($id_empleado, $equipos);
			return new DataResponse([
				'success' => true,
				'equipos' => $this->computoMapper->findByEmpleado($id_empleado),
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->assignmentErrorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function CrearInventarioEquipo(
		?int $id_empleado = null,
		?int $id_modelo = null,
		?string $nombre_dispositivo = null,
		?string $nombre_sistema = null,
		?string $numero_serie = null,
		?string $estado = 'activo',
		?string $info = null
	): DataResponse {
		try {
			$this->requireInventarioAdminAccess();

			$id = $this->movimientoService->crearEquipo([
				'id_empleado' => $id_empleado,
				'id_modelo' => $id_modelo,
				'nombre_dispositivo' => $nombre_dispositivo,
				'nombre_sistema' => $nombre_sistema,
				'numero_serie' => $numero_serie,
				'estado' => $estado ?: 'activo',
				'info' => $info,
			]);

			return new DataResponse([
				'success' => true,
				'id_equipo' => $id,
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function ActualizarInventarioEquipo(
		int $id_equipo,
		?int $id_empleado = null,
		?int $id_modelo = null,
		?string $nombre_dispositivo = null,
		?string $nombre_sistema = null,
		?string $numero_serie = null,
		?string $estado = 'activo',
		?string $info = null
	): DataResponse {
		try {
			$this->requireInventarioAdminAccess();

			if ($id_equipo <= 0) {
				return new DataResponse(['success' => false, 'message' => 'Identificador de equipo inválido.'], Http::STATUS_BAD_REQUEST);
			}

			$data = [
				'id_modelo' => $id_modelo,
				'nombre_dispositivo' => $nombre_dispositivo,
				'nombre_sistema' => $nombre_sistema,
				'numero_serie' => $numero_serie,
				'estado' => $estado ?: 'activo',
				'info' => $info,
			];
			if ($this->request->getParam('id_empleado', '__missing__') !== '__missing__') {
				$data['id_empleado'] = $id_empleado;
			}
			$actualizado = $this->movimientoService->actualizarEquipo($id_equipo, $data);

			return new DataResponse([
				'success' => true,
				'message' => $actualizado ? 'Equipo actualizado correctamente.' : 'El equipo no tenía cambios.',
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function EliminarInventarioEquipo(int $id_equipo): DataResponse {
		try {
			$this->requireInventarioAdminAccess();

			if ($id_equipo <= 0) {
				return new DataResponse(['success' => false, 'message' => 'Identificador de equipo inválido.'], Http::STATUS_BAD_REQUEST);
			}

			$this->movimientoService->darDeBaja($id_equipo);

			return new DataResponse([
				'success' => true,
				'message' => 'Equipo dado de baja correctamente.',
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventarioHistorial(int $id_equipo, int $limit = 25, int $offset = 0): DataResponse {
		if (!$this->permisosService->canSee('inventario')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para consultar inventario.'], Http::STATUS_FORBIDDEN);
		}
		if ($id_equipo <= 0 || $limit <= 0 || $offset < 0) {
			return new DataResponse(['success' => false, 'message' => 'Parámetros de paginación inválidos.'], Http::STATUS_BAD_REQUEST);
		}
		try {
			return new DataResponse(['success' => true] + $this->movimientoService->listarHistorial($id_equipo, $limit, $offset), Http::STATUS_OK);
		} catch (\RuntimeException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function CrearInventarioNota(int $id_equipo, string $descripcion): DataResponse {
		if (!$this->permisosService->canSeeAny(['inventario.admin', 'soporte'])) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para agregar notas de inventario.'], Http::STATUS_FORBIDDEN);
		}
		if ($id_equipo <= 0) {
			return new DataResponse(['success' => false, 'message' => 'Identificador de equipo inválido.'], Http::STATUS_BAD_REQUEST);
		}
		try {
			return new DataResponse([
				'success' => true,
				'data' => $this->movimientoService->registrarNota($id_equipo, $descripcion),
			], Http::STATUS_CREATED);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\RuntimeException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/************************ SOPORTE ************************/

	#[UseSession]
	#[NoAdminRequired]
	public function GetSoporteEquipo(int $id_equipo): DataResponse {
		if (!$this->permisosService->canSee('soporte')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para consultar soporte.'], Http::STATUS_FORBIDDEN);
		}
		try {
			return new DataResponse([
				'success' => true,
				'data' => $this->soporteMapper->findByEquipo($id_equipo),
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function CrearSoporteEquipo(
		int $id_equipo,
		?string $accion = null,
		?string $detalles = null,
		?string $fecha = null,
		?string $usuario_actual = null,
		?string $usuario_soporte = null,
		?string $categoria = null,
		?string $prioridad = null,
		mixed $duracion_minutos = null
	): DataResponse {
		if (!$this->permisosService->canSee('inventario.admin')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para registrar soporte.'], Http::STATUS_FORBIDDEN);
		}
		try {
			$soporte = $this->movimientoService->registrarSoporte(
				$id_equipo,
				(string)$detalles,
				$categoria,
				$prioridad,
				$accion,
				$duracion_minutos,
				$fecha,
			);

			return new DataResponse([
				'success' => true,
				'id_soporte' => $soporte['id_soporte'],
				'data' => $soporte,
			], Http::STATUS_OK);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\RuntimeException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function ActualizarSoporteEquipo(
		int $id_soporte,
		?string $accion = null,
		?string $detalles = null,
		?string $fecha = null,
		?string $usuario_actual = null,
		?string $usuario_soporte = null,
		mixed $duracion_minutos = null,
		?string $categoria = null,
		?string $prioridad = null
	): DataResponse {
		if (!$this->permisosService->canSee('inventario.admin')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para actualizar soporte.'], Http::STATUS_FORBIDDEN);
		}
		try {
			$accionNormalizada = $categoria !== null
				? strtolower(trim($categoria)) . ' · ' . strtolower(trim((string)($prioridad ?: 'media')))
				: $accion;
			$data = $this->movimientoService->actualizarSoporte($id_soporte, [
				'accion' => $accionNormalizada,
				'detalles' => $detalles,
				'fecha' => $fecha,
				'duracion_minutos' => $duracion_minutos,
			]);

			return new DataResponse([
				'success' => true,
				'message' => 'Registro de soporte actualizado correctamente.',
				'data' => $data,
			], Http::STATUS_OK);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\RuntimeException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function EliminarSoporteEquipo(int $id_soporte): DataResponse {
		if (!$this->permisosService->canSee('inventario.admin')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para eliminar soporte.'], Http::STATUS_FORBIDDEN);
		}
		try {
			$this->movimientoService->eliminarSoporte($id_soporte);

			return new DataResponse([
				'success' => true,
				'message' => 'Registro de soporte eliminado correctamente.',
			], Http::STATUS_OK);
		} catch (\RuntimeException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	private function errorResponse(\Throwable $e): DataResponse {
		return new DataResponse([
			'success' => false,
			'message' => $e->getMessage(),
		], Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	private function assignmentErrorResponse(\Throwable $e): DataResponse {
		$status = match (true) {
			$e instanceof \InvalidArgumentException => Http::STATUS_BAD_REQUEST,
			$e instanceof \DomainException => Http::STATUS_CONFLICT,
			$e instanceof \RuntimeException => Http::STATUS_NOT_FOUND,
			default => Http::STATUS_INTERNAL_SERVER_ERROR,
		};
		return new DataResponse(['success' => false, 'message' => $e->getMessage()], $status);
	}

	private function toBool($value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value === 1;
		}

		if (is_string($value)) {
			return in_array(strtolower($value), ['1', 'true', 'yes', 'on', 'si', 'sí'], true);
		}

		return false;
	}
}
