<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\festivos;
use OCA\Empleados\Db\festivosMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';

class FestivosController extends BaseController {

	protected festivosMapper $festivosMapper;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper,
		festivosMapper $festivosMapper
	) {

		parent::__construct(
			Application::APP_ID,
			$request,
			$userSession,
			$groupManager,
			$empleadosMapper,
			$configuracionesMapper
		);

		$this->festivosMapper = $festivosMapper;
	}

	#[UseSession]
	#[NoAdminRequired]
	public function getFestivos(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->festivosMapper->findAll(),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findById(
		int $id_festivo
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->festivosMapper->findById($id_festivo),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findByFecha(
		string $fecha
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->festivosMapper->findByFecha($fecha),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function crearFestivo(
		string $nombre,
		string $fecha
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		// fecha viene como YYYY-MM-DD desde el input date, extraer MM-DD
		$fecha = substr($fecha, 5);

		if ($this->festivosMapper->existeFecha($fecha)) {
			return new DataResponse(
				[
					'status' => 'error',
					'message' => 'Ya existe un festivo para esa fecha.'
				],
				Http::STATUS_CONFLICT
			);
		}

		// Los festivos creados manualmente desde la UI siempre son
		// tipo 'fijo' y NO oficiales (oficial = 0 por default en el mapper).
		$this->festivosMapper->createFestivo($nombre, $fecha);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function modificarFestivo(
		int $id_festivo,
		string $nombre,
		string $fecha
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		// fecha viene como YYYY-MM-DD desde el input date, extraer MM-DD
		$fecha = substr($fecha, 5);

		$this->festivosMapper->updateFestivo(
			$id_festivo,
			$nombre,
			$fecha
		);

		// Nota: si este festivo es de tipo 'variable' (Constitución, Juárez,
		// Revolución), su fecha se sobrescribirá de nuevo automáticamente
		// el próximo 1 de enero por RecalcularFestivosVariablesJob, sin
		// importar lo que se edite aquí manualmente. Eso queda advertido
		// en la UI (modal de edición), no se fuerza nada aquí.

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function deleteById(
		int $id_festivo
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		if ($this->festivosMapper->esOficial($id_festivo)) {
			return new DataResponse(
				[
					'status' => 'error',
					'message' => 'Los festivos oficiales no se pueden eliminar.'
				],
				Http::STATUS_FORBIDDEN
			);
		}

		$this->festivosMapper->deleteById($id_festivo);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function importarFestivos(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		$file = $this->getUploadedFile('festivosfileXLSX');
		$xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name']);

		if (!$xlsx) {
			return new DataResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
		}

		$rows = $xlsx->rows();
		if (count($rows) < 2) {
			return new DataResponse(['status' => 'error', 'message' => 'Sin datos'], Http::STATUS_BAD_REQUEST);
		}

		$headers = array_map(fn($h) => mb_strtolower(trim((string)$h)), $rows[0]);
		$colNombre = array_search('nombre', $headers);
		$colFecha  = array_search('fecha', $headers);

		if ($colNombre === false || $colFecha === false) {
			return new DataResponse(['status' => 'error', 'message' => 'Columnas nombre/fecha no encontradas'], Http::STATUS_BAD_REQUEST);
		}

		$creados = 0;
		foreach (array_slice($rows, 1) as $row) {
			$nombre   = trim((string)($row[$colNombre] ?? ''));
			$fechaRaw = substr(trim((string)($row[$colFecha] ?? '')), 0, 10); // YYYY-MM-DD o MM-DD
			$fecha    = strlen($fechaRaw) === 10 ? substr($fechaRaw, 5) : $fechaRaw; // siempre MM-DD
			if (!$nombre || !$fecha) continue;
			if ($this->festivosMapper->existeFecha($fecha)) continue;
			// Los festivos importados por XLSX también son 'fijo' y NO oficiales.
			$this->festivosMapper->createFestivo($nombre, $fecha);
			$creados++;
		}

		return new DataResponse(['status' => 'ok', 'creados' => $creados], Http::STATUS_OK);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function exportarFestivos(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		$festivos = $this->festivosMapper->findAll();

		$rows = [['nombre', 'fecha']];
		foreach ($festivos as $f) {
			$rows[] = [$f['nombre'], $f['fecha']];
		}

		$xlsx = \Shuchkin\SimpleXLSXGen::fromArray($rows);
		$xlsx->downloadAs('festivos_' . date('Y-m-d') . '.xlsx');

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function vaciarFestivos(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);
		// deleteAll() ya preserva internamente los festivos oficiales.
		$this->festivosMapper->deleteAll();
		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	private function getUploadedFile(string $key): array {
		$file = $this->request->getUploadedFile($key);

		if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			throw new \Exception('Error en la subida del archivo.');
		}

		return $file;
	}
}