<?php
declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\Db\Moneda;
use OCA\Empleados\Db\MonedaMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class MonedaController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private MonedaMapper $monedaMapper,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function GetMonedas(): DataResponse {
		$monedas = array_map(
			fn (Moneda $m) => $this->toArray($m),
			$this->monedaMapper->findAll()
		);
		return new DataResponse($monedas);
	}

	#[NoAdminRequired]
	public function AgregarMoneda(string $tipoMoneda, string $serie): DataResponse {
		$tipoMoneda = strtoupper(trim($tipoMoneda));
		$serie = trim($serie);

		if ($tipoMoneda === '' || $serie === '') {
			return new DataResponse(['error' => 'tipo_moneda y serie son obligatorios'], Http::STATUS_BAD_REQUEST);
		}

		if ($this->monedaMapper->existeTipo($tipoMoneda)) {
			return new DataResponse(['error' => 'Ya existe una moneda con ese tipo'], Http::STATUS_CONFLICT);
		}

		$moneda = new Moneda();
		$moneda->setTipoMoneda($tipoMoneda);
		$moneda->setSerie($serie);
		$moneda = $this->monedaMapper->insert($moneda);

		return new DataResponse($this->toArray($moneda), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	public function ModificarMoneda(int $id, string $tipoMoneda, string $serie): DataResponse {
		$tipoMoneda = strtoupper(trim($tipoMoneda));
		$serie = trim($serie);

		try {
			$moneda = $this->monedaMapper->find($id);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Moneda no encontrada'], Http::STATUS_NOT_FOUND);
		}

		if ($tipoMoneda === '' || $serie === '') {
			return new DataResponse(['error' => 'tipo_moneda y serie son obligatorios'], Http::STATUS_BAD_REQUEST);
		}

		if ($this->monedaMapper->existeTipo($tipoMoneda, $id)) {
			return new DataResponse(['error' => 'Ya existe otra moneda con ese tipo'], Http::STATUS_CONFLICT);
		}

		$moneda->setTipoMoneda($tipoMoneda);
		$moneda->setSerie($serie);
		$moneda = $this->monedaMapper->update($moneda);

		return new DataResponse($this->toArray($moneda));
	}

	#[NoAdminRequired]
	public function EliminarMoneda(int $id): DataResponse {
		try {
			$moneda = $this->monedaMapper->find($id);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Moneda no encontrada'], Http::STATUS_NOT_FOUND);
		}

		$this->monedaMapper->delete($moneda);

		return new DataResponse([]);
	}

	/**
	 * Serialización explícita: nunca depender de jsonSerialize() automático de Entity.
	 */
	private function toArray(Moneda $moneda): array {
		return [
			'id' => $moneda->getId(),
			'tipoMoneda' => $moneda->getTipoMoneda(),
			'serie' => $moneda->getSerie(),
		];
	}
}