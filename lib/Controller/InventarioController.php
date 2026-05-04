<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\InventarioModeloMapper;
use OCA\Empleados\Db\InventarioComputoMapper;
use OCA\Empleados\Db\SoporteHistorialMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;

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

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper,
		InventarioModeloMapper $modelosMapper,
		InventarioComputoMapper $computoMapper,
		SoporteHistorialMapper $soporteMapper
	) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);

		$this->modelosMapper = $modelosMapper;
		$this->computoMapper = $computoMapper;
		$this->soporteMapper = $soporteMapper;
	}

	/************************ MODELOS ************************/

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventarioModelos(?string $search = null, ?string $tipo = null): DataResponse {
		try {
			$this->checkAccess(['admin', 'empleados']);

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
			$this->checkAccess(['admin', 'empleados']);

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
			$this->checkAccess(['admin', 'empleados']);

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
			$this->checkAccess(['admin', 'empleados']);

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
			$this->checkAccess(['admin', 'empleados']);

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
	public function GetInventarioComputo(?string $search = null, ?string $estado = null, ?int $id_empleado = null): DataResponse {
		try {
			$this->checkAccess(['admin', 'empleados']);

			return new DataResponse([
				'success' => true,
				'data' => $this->computoMapper->findAll($search, $estado, $id_empleado),
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventarioEquipo(int $id_equipo): DataResponse {
		try {
			$this->checkAccess(['admin', 'empleados']);

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
			$this->checkAccess(['admin', 'empleados']);

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
			$this->checkAccess(['admin', 'empleados']);

			$id = $this->computoMapper->create([
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
			$this->checkAccess(['admin', 'empleados']);

			$this->computoMapper->updateById($id_equipo, [
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
				'message' => 'Equipo actualizado correctamente.',
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function EliminarInventarioEquipo(int $id_equipo): DataResponse {
		try {
			$this->checkAccess(['admin', 'empleados']);

			$this->computoMapper->deleteById($id_equipo);

			return new DataResponse([
				'success' => true,
				'message' => 'Equipo eliminado correctamente.',
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	/************************ SOPORTE ************************/

	#[UseSession]
	#[NoAdminRequired]
	public function GetSoporteEquipo(int $id_equipo): DataResponse {
		try {
			$this->checkAccess(['admin', 'empleados']);

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
		?string $usuario_soporte = null
	): DataResponse {
		try {
			$this->checkAccess(['admin', 'empleados']);

			$id = $this->soporteMapper->create([
				'id_equipo' => $id_equipo,
				'accion' => $accion,
				'detalles' => $detalles,
				'fecha' => $fecha,
				'usuario_actual' => $usuario_actual,
				'usuario_soporte' => $usuario_soporte,
			]);

			return new DataResponse([
				'success' => true,
				'id_soporte' => $id,
			], Http::STATUS_OK);
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
		?string $usuario_soporte = null
	): DataResponse {
		try {
			$this->checkAccess(['admin', 'empleados']);

			$this->soporteMapper->updateById($id_soporte, [
				'accion' => $accion,
				'detalles' => $detalles,
				'fecha' => $fecha,
				'usuario_actual' => $usuario_actual,
				'usuario_soporte' => $usuario_soporte,
			]);

			return new DataResponse([
				'success' => true,
				'message' => 'Registro de soporte actualizado correctamente.',
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function EliminarSoporteEquipo(int $id_soporte): DataResponse {
		try {
			$this->checkAccess(['admin', 'empleados']);

			$this->soporteMapper->deleteById($id_soporte);

			return new DataResponse([
				'success' => true,
				'message' => 'Registro de soporte eliminado correctamente.',
			], Http::STATUS_OK);
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