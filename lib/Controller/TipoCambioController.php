<?php
declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\Db\MonedaMapper;
use OCA\Empleados\Db\TipoCambioMapper;
use OCA\Empleados\Service\BanxicoService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class TipoCambioController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private TipoCambioMapper $tipoCambioMapper,
		private MonedaMapper $monedaMapper,
		private BanxicoService $banxicoService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function GetTipoCambio(int $idMoneda, string $fechaInicio, string $fechaFin): DataResponse {
		$datos = $this->tipoCambioMapper->findByRango($idMoneda, $fechaInicio, $fechaFin);
		return new DataResponse($datos);
	}

	#[NoAdminRequired]
	public function SincronizarTipoCambio(int $idMoneda, string $fechaInicio, string $fechaFin): DataResponse {
		try {
			$moneda = $this->monedaMapper->find($idMoneda);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => 'Moneda no encontrada'], Http::STATUS_NOT_FOUND);
		}

		try {
			$total = $this->banxicoService->sincronizarRango($moneda, $fechaInicio, $fechaFin);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
		}

		return new DataResponse(['insertados' => $total]);
	}
}