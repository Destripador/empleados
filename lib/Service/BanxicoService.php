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
	private const OPORTUNO_URL = 'https://www.banxico.org.mx/SieAPIRest/service/v1/series/%s/datos/oportuno';

	public function __construct(
		private IClientService $clientService,
		private MonedaMapper $monedaMapper,
		private TipoCambioMapper $tipoCambioMapper,
		private LoggerInterface $logger,
		private \OCP\IConfig $config,
	) {
	}

	private function getToken(): string {
		$token = $this->config->getAppValue('empleados', 'banxico_token', '');
		if ($token === '') {
			throw new \RuntimeException('No se ha configurado el token de Banxico (banxico_token).');
		}
		return $token;
	}

	/**
	 * Guarda los datos de una respuesta de Banxico
	 */
	private function guardarDatos(Moneda $moneda, array $datos): int {
		$insertados = 0;
		foreach ($datos as $dato) {
			if ($dato['dato'] === 'N/E') {
				continue;
			}
			$fecha = $this->convertirFecha($dato['fecha']);
			$valor = (float) str_replace(',', '', $dato['dato']);

			$this->tipoCambioMapper->upsert($moneda->getId(), $fecha, $valor);
			$insertados++;
		}
		return $insertados;
	}

	/**
	 * Sincroniza un rango de fechas explícito
	 */
	public function sincronizarRango(Moneda $moneda, string $fechaInicio, string $fechaFin): int {
		$token = $this->getToken();

		$url = sprintf(self::BASE_URL, $moneda->getSerie(), $fechaInicio, $fechaFin);

		$client = $this->clientService->newClient();
		$response = $client->get($url, [
			'query' => ['token' => $token],
			'headers' => ['Accept' => 'application/json'],
		]);

		$body = json_decode($response->getBody(), true);
		$datos = $body['bmx']['series'][0]['datos'] ?? [];

		return $this->guardarDatos($moneda, $datos);
	}

	/**
	 * Sincroniza el valor más reciente publicado por Banxico
	 */
	public function sincronizarUltimo(Moneda $moneda): int {
		$token = $this->getToken();

		$url = sprintf(self::OPORTUNO_URL, $moneda->getSerie());

		$client = $this->clientService->newClient();
		$response = $client->get($url, [
			'query' => ['token' => $token],
			'headers' => ['Accept' => 'application/json'],
		]);

		$body = json_decode($response->getBody(), true);
		$datos = $body['bmx']['series'][0]['datos'] ?? [];

		return $this->guardarDatos($moneda, $datos);
	}

	/**
	 * Corre el rango de fechas y el dato oportuno
	 */
	public function sincronizarTodasLasMonedas(string $fechaInicio, string $fechaFin): void {
		foreach ($this->monedaMapper->findAll() as $moneda) {
			try {
				$n = $this->sincronizarRango($moneda, $fechaInicio, $fechaFin);
				$this->logger->info("Banxico: {$n} registros (rango) sincronizados para {$moneda->getTipoMoneda()}");
			} catch (\Throwable $e) {
				$this->logger->error('Error sincronizando rango ' . $moneda->getTipoMoneda() . ': ' . $e->getMessage());
			}

			try {
				$n = $this->sincronizarUltimo($moneda);
				$this->logger->info("Banxico: {$n} registro (oportuno) sincronizado para {$moneda->getTipoMoneda()}");
			} catch (\Throwable $e) {
				$this->logger->error('Error sincronizando oportuno ' . $moneda->getTipoMoneda() . ': ' . $e->getMessage());
			}
		}
	}

	private function convertirFecha(string $fechaBanxico): string {
		[$d, $m, $y] = explode('/', $fechaBanxico);
		return sprintf('%s-%s-%s', $y, $m, $d);
	}
}