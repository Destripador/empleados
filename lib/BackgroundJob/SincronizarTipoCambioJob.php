<?php

declare(strict_types=1);

namespace OCA\Empleados\BackgroundJob;

use OCA\Empleados\Service\BanxicoService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class SincronizarTipoCambioJob extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private BanxicoService $banxicoService,
	) {
		parent::__construct($time);
		$this->setInterval(24 * 60 * 60);
	}

	protected function run($argument): void {
		$hoy = date('Y-m-d');
		$this->banxicoService->sincronizarTodasLasMonedas($hoy, $hoy);
	}
}