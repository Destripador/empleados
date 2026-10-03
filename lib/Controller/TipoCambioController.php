<?php
declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\Db\MonedaMapper;
use OCA\Empleados\Db\TipoCambio;
use OCA\Empleados\Db\TipoCambioMapper;
use OCA\Empleados\Service\BanxicoService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

class TipoCambioController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private TipoCambioMapper $tipoCambioMapper,
		private MonedaMapper $monedaMapper,
		private BanxicoService $banxicoService,
		private IL10N $l10n,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function GetTipoCambio(int $idMoneda, string $fechaInicio, string $fechaFin): DataResponse {
		$registros = array_map(
			fn (TipoCambio $tc) => $this->toArray($tc),
			$this->tipoCambioMapper->findByRango($idMoneda, $fechaInicio, $fechaFin)
		);
		return new DataResponse($registros);
	}

	#[NoAdminRequired]
	public function SincronizarTipoCambio(int $idMoneda, string $fechaInicio, string $fechaFin): DataResponse {
		try {
			$moneda = $this->monedaMapper->find($idMoneda);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['error' => $this->l10n->t('Currency not found')], Http::STATUS_NOT_FOUND);
		}

		try {
			$total = $this->banxicoService->sincronizarRango($moneda, $fechaInicio, $fechaFin);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
		}

		return new DataResponse(['insertados' => $total]);
	}

	private function toArray(TipoCambio $tc): array {
		return [
			'id' => $tc->getId(),
			'idMoneda' => $tc->getIdMoneda(),
			'fecha' => $tc->getFecha(),
			'valor' => $tc->getValor(),
		];
	}
}