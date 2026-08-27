<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCA\Empleados\Db\Moneda;
use OCA\Empleados\Db\MonedaMapper;
use OCA\Empleados\Db\TipoCambioMapper;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class BanxicoService {

	private const BASE_URL = 'https://www.banxico.org.mx/SieAPIRest/service/v1/series/%s/datos/%s/%s';

	public function __construct(
		private IClientService $clientService,
		private MonedaMapper $monedaMapper,
		private TipoCambioMapper $tipoCambioMapper,
		private LoggerInterface $logger,
		private \OCP\IConfig $config,
	) {
	}

	public function sincronizarRango(Moneda $moneda, string $fechaInicio, string $fechaFin): int {
		$token = $this->config->getAppValue('empleados', 'banxico_token', '');
		if ($token === '') {
			throw new \RuntimeException('No se ha configurado el token de Banxico (banxico_token).');
		}

		$url = sprintf(self::BASE_URL, $moneda->getSerie(), $fechaInicio, $fechaFin);

		$client = $this->clientService->newClient();
		$response = $client->get($url, [
			'query' => [
				'token' => $token,
			],
			'headers' => [
				'Accept' => 'application/json',
			],
		]);

		$body = json_decode($response->getBody(), true);
		$datos = $body['bmx']['series'][0]['datos'] ?? [];

		$insertados = 0;
		foreach ($datos as $dato) {
			if ($dato['dato'] === 'N/E') {
				continue;
			}
			$fecha = $this->convertirFecha($dato['fecha']);
			$valor = (float)str_replace(',', '', $dato['dato']);

			$this->tipoCambioMapper->upsert($moneda->getId(), $fecha, $valor);
			$insertados++;
		}

		return $insertados;
	}

	public function sincronizarTodasLasMonedas(string $fechaInicio, string $fechaFin): void {
		foreach ($this->monedaMapper->findAll() as $moneda) {
			try {
				$n = $this->sincronizarRango($moneda, $fechaInicio, $fechaFin);
				$this->logger->info("Banxico: {$n} registros sincronizados para {$moneda->getTipoMoneda()}");
			} catch (\Throwable $e) {
				$this->logger->error('Error sincronizando ' . $moneda->getTipoMoneda() . ': ' . $e->getMessage());
			}
		}
	}

	private function convertirFecha(string $fechaBanxico): string {
		[$d, $m, $y] = explode('/', $fechaBanxico);
		return sprintf('%s-%s-%s', $y, $m, $d);
	}
}