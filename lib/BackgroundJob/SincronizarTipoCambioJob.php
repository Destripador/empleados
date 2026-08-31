<?php

declare(strict_types=1);

namespace OCA\Empleados\BackgroundJob;

use OCA\Empleados\Service\BanxicoService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class SincronizarTipoCambioJob extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private BanxicoService $banxicoService,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		// Se ejecuta cada 24 horas (86400 segundos)
		$this->setInterval(24 * 60 * 60);

		$this->setTimeSensitivity(self::TIME_SENSITIVE);
	}

	protected function run($argument): void {
		$hoy = $this->getTime()->getDateTime()->format('Y-m-d');

		try {
			$this->banxicoService->sincronizarTodasLasMonedas($hoy, $hoy);
			$this->logger->info("SincronizarTipoCambioJob: sincronización de {$hoy} completada.");
		} catch (\Throwable $e) {
			$this->logger->error('SincronizarTipoCambioJob: error al sincronizar tipo de cambio: ' . $e->getMessage());
		}
	}
}